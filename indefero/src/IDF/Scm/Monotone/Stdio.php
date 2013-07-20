<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008-2011 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

require_once 'IDF/Scm/Monotone/IStdio.php';

/**
 * Monotone stdio class
 *
 * Connects to a monotone process and executes commands via its
 * stdio interface
 *
 * @author Thomas Keller <me@thomaskeller.biz>
 */
class IDF_Scm_Monotone_Stdio implements IDF_Scm_Monotone_IStdio
{
    /** this is the most recent STDIO version. The number is output
        at the protocol start. Older versions of monotone (prior 0.47)
        do not output it and are therefor incompatible */
    public static $SUPPORTED_STDIO_VERSION = 2;

    private $project;
    private $proc;
    private $pipes;
    private $oob;
    private $cmdnum;
    private $lastcmd;

    /**
     * Constructor - starts the stdio process
     *
     * @param IDF_Project
     */
    public function __construct(IDF_Project $project)
    {
        $this->project = $project;
        $this->start();
    }

    /**
     * Destructor - stops the stdio process
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Returns a string with additional options which are passed to
     * an mtn instance connecting to remote databases
     *
     * @return string
     */
    private function _getAuthOptions()
    {
        $prjconf = $this->project->getConf();
        $name = $prjconf->getVal('mtn_client_key_name', false);
        $hash = $prjconf->getVal('mtn_client_key_hash', false);

        if (!$name || !$hash) {
            throw new IDF_Scm_Exception(sprintf(
                __('Monotone client key name or hash not in project conf.')
            ));
        }

        $keydir = Pluf::f('tmp_folder').'/mtn-client-keys';
        if (!file_exists($keydir)) {
            if (!mkdir($keydir)) {
                throw new IDF_Scm_Exception(sprintf(
                    __('The key directory %s could not be created.'), $keydir
                ));
            }
        }

        // in case somebody cleaned out the cache, we restore the key here
        $keyfile = $keydir . '/' . $name .'.'. $hash;
        if (!file_exists($keyfile)) {
            $data = $prjconf->getVal('mtn_client_key_data');
            if (!file_put_contents($keyfile, $data, LOCK_EX)) {
                throw new IDF_Scm_Exception(sprintf(
                    __('Could not write client key "%s"'), $keyfile
                ));
            }
        }

        return sprintf('--keydir=%s --key=%s ',
               escapeshellarg($keydir),
               escapeshellarg($hash)
        );
    }

    /**
     * Starts the stdio process and resets the command counter
     */
    public function start()
    {
        if (is_resource($this->proc))
            $this->stop();

        $remote_db_access = Pluf::f('mtn_db_access', 'remote') == 'remote';

        $cmd = Pluf::f('idf_exec_cmd_prefix', '') .
               escapeshellarg(Pluf::f('mtn_path', 'mtn')) . ' ';

        $opts = Pluf::f('mtn_opts', array());
        foreach ($opts as $opt) {
            $cmd .= sprintf('%s ', escapeshellarg($opt));
        }

        if ($remote_db_access) {
            $cmd .= $this->_getAuthOptions();
            $host = sprintf(Pluf::f('mtn_remote_url'), $this->project->shortname);
            $cmd .= sprintf('automate remote_stdio %s', escapeshellarg($host));
        }
        else
        {
            $repo = sprintf(Pluf::f('mtn_repositories'), $this->project->shortname);
            if (!file_exists($repo)) {
                throw new IDF_Scm_Exception(
                    "repository file '$repo' does not exist"
                );
            }
            $cmd .= sprintf('--db %s automate stdio', escapeshellarg($repo));
        }

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $env = array('LANG' => 'en_US.UTF-8');
        $this->proc = proc_open($cmd, $descriptors, $this->pipes,
                                null, $env);

        if (!is_resource($this->proc)) {
            throw new IDF_Scm_Exception('could not start stdio process');
        }

        $this->_checkVersion();

        $this->cmdnum = -1;
    }

    /**
     * Stops the stdio process and closes all pipes
     */
    public function stop()
    {
        if (!is_resource($this->proc))
            return;

        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        fclose($this->pipes[2]);

        proc_close($this->proc);
        $this->proc = null;
    }

    /**
     * select()'s on stdout and returns true as soon as we got new
     * data to read, false if the select() timed out
     *
     * @return boolean
     * @throws IDF_Scm_Exception
     */
    private function _waitForReadyRead()
    {
        if (!is_resource($this->pipes[1]))
            return false;

        $read = array($this->pipes[1], $this->pipes[2]);
        $write = $except = null;
        $streamsChanged = stream_select(
            $read, $write, $except, 0, 20000
        );

        if ($streamsChanged === false) {
            throw new IDF_Scm_Exception(
                'Could not select() on read pipe'
            );
        }

        if ($streamsChanged == 0) {
            return false;
        }

        return true;
    }

    /**
     * Checks the version of the used stdio protocol
     *
     * @throws IDF_Scm_Exception
     */
    private function _checkVersion()
    {
        $this->_waitForReadyRead();

        $version = fgets($this->pipes[1]);
        if ($version === false) {
            throw new IDF_Scm_Exception(
                "Could not determine stdio version, stderr is:\n".
                $this->_readStderr()
            );
        }

        if (!preg_match('/^format-version: (\d+)$/', $version, $m) ||
            $m[1] != self::$SUPPORTED_STDIO_VERSION)
        {
            throw new IDF_Scm_Exception(
                'stdio format version mismatch, expected "'.
                self::$SUPPORTED_STDIO_VERSION.'", got "'.@$m[1].'"'
            );
        }

        fgets($this->pipes[1]);
    }

    /**
     * Writes a command to stdio
     *
     * @param array
     * @param array
     * @throws IDF_Scm_Exception
     */
    private function _write(array $args, array $options = array())
    {
        $cmd = '';
        if (count($options) > 0) {
            $cmd = 'o';
            foreach ($options as $k => $vals) {
                if (!is_array($vals))
                    $vals = array($vals);

                foreach ($vals as $v) {
                    $cmd .= strlen((string)$k) . ':' . (string)$k;
                    $cmd .= strlen((string)$v) . ':' . (string)$v;
                }
            }
            $cmd .= 'e ';
        }

        $cmd .= 'l';
        foreach ($args as $arg) {
            $cmd .= strlen((string)$arg) . ':' . (string)$arg;
        }
        $cmd .= "e\n";

        if (!fwrite($this->pipes[0], $cmd)) {
            throw new IDF_Scm_Exception("could not write '$cmd' to process");
        }

        $this->lastcmd = $cmd;
        $this->cmdnum++;
    }

    /**
     * Reads all output from stderr and returns it
     *
     * @return string
     */
    private function _readStderr()
    {
        $err = "";
        while (($line = fgets($this->pipes[2])) !== false) {
            $err .= $line;
        }
        return empty($err) ? '<empty>' : $err;
    }

    /**
     * Reads the last output from the stdio process, parses and returns it
     *
     * @return string
     * @throws IDF_Scm_Exception
     */
    private function _readStdout()
    {
        $this->oob = array('w' => array(),
                           'p' => array(),
                           't' => array(),
                           'e' => array());

        $output = "";
        $errcode = 0;

        while (true) {
            if (!$this->_waitForReadyRead())
                continue;

            $data = array(0,"",0);
            $idx = 0;
            while (true) {
                $c = fgetc($this->pipes[1]);
                if ($c === false) {
                    throw new IDF_Scm_Exception(
                        "No data on stdin, stderr is:\n".
                        $this->_readStderr()
                    );
                }

                if ($c == ':') {
                    if ($idx == 2)
                        break;

                    ++$idx;
                    continue;
                }

                if (is_numeric($c))
                    $data[$idx] = $data[$idx] * 10 + $c;
                else
                    $data[$idx] .= $c;
            }

            // sanity
            if ($this->cmdnum != $data[0]) {
                throw new IDF_Scm_Exception(
                    'command numbers out of sync; expected '.
                    $this->cmdnum .', got '. $data[0]
                );
            }

            $toRead = $data[2];
            $buffer = "";
            while ($toRead > 0) {
                $buffer .= fread($this->pipes[1], $toRead);
                $toRead = $data[2] - strlen($buffer);
            }

            switch ($data[1]) {
                case 'w':
                case 'p':
                case 't':
                case 'e':
                    $this->oob[$data[1]][] = $buffer;
                    continue;
                case 'm':
                    $output .= $buffer;
                    continue;
                case 'l':
                    $errcode = $buffer;
                    break 2;
            }
        }

        if ($errcode != 0) {
            throw new IDF_Scm_Exception(
                "command '{$this->lastcmd}' returned error code $errcode: ".
                implode(' ', $this->oob['e'])
            );
        }

        return $output;
    }

    /**
     * Executes a command over stdio and returns its result
     *
     * @param array Array of arguments
     * @param array Array of options as key-value pairs. Multiple options
     *              can be defined in sub-arrays, like
     *              "r" => array("123...", "456...")
     * @return string
     */
    public function exec(array $args, array $options = array())
    {
        $this->_write($args, $options);
        return $this->_readStdout();
    }

    /**
     * Returns the last out-of-band output for a previously executed
     * command as associative array with 'e' (error), 'w' (warning),
     * 'p' (progress) and 't' (ticker, unparsed) as keys
     *
     * @return array
     */
    public function getLastOutOfBandOutput()
    {
        return $this->oob;
    }
}

