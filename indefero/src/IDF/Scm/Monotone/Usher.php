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

require_once(dirname(__FILE__) . "/BasicIO.php");

/**
 * Connects with the admininistrative interface of usher,
 * the monotone proxy. This class contains only static methods because
 * there is really no state to keep between each invocation, as usher
 * closes the connection after every command.
 *
 * @author Thomas Keller <me@thomaskeller.biz>
 */
class IDF_Scm_Monotone_Usher
{
    /**
     * Without giving a specific state, returns an array of all servers.
     * When a state is given, the array contains only servers which are
     * in the given state.
     *
     * @param string $state One of REMOTE, ACTIVE, WAITING, SLEEPING,
     *                      STOPPING, STOPPED, SHUTTINGDOWN or SHUTDOWN
     * @return array
     */
    public static function getServerList($state = null)
    {
        $conn = self::_triggerCommand('LIST '.$state);
        if ($conn == 'none')
            return array();

        return preg_split('/[ ]/', $conn, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Returns an array of all open connections to the given server, or to
     * any server if no server is specified.
     * If there are no connections to list, an empty array is returned.
     *
     * Example:
     *    array("server1" => array(
     *              array("address" => "192.168.1.0", "port" => "13456"),
     *              ...
     *          ),
     *          "server2" => ...
     *    )
     *
     * @param string $server
     * @return array
     */
    public static function getConnectionList($server = null)
    {
        $conn = self::_triggerCommand('LISTCONNECTIONS '.$server);
        if ($conn == 'none')
            return array();

        $single_conns = preg_split('/[ ]/', $conn, -1, PREG_SPLIT_NO_EMPTY);
        $ret = array();
        foreach ($single_conns as $conn) {
            preg_match('/\(([^)]+)\)([^:]+):(\d+)/', $conn, $matches);
            $ret[$matches[1]][] = (object)array(
                'server'    => $matches[1],
                'address'   => $matches[2],
                'port'      => $matches[3],
            );
        }

        if ($server !== null) {
            if (array_key_exists($server, $ret))
                return $ret[$server];
            return array();
        }

        return $ret;
    }

    /**
     * Get the status of a particular server, or of the usher as a whole if
     * no server is specified.
     *
     * @param string $server
     * @return One of REMOTE, SLEEPING, STOPPING, STOPPED for servers or
     *         ACTIVE, WAITING, SHUTTINGDOWN or SHUTDOWN for usher itself
     */
    public static function getStatus($server = null)
    {
        return self::_triggerCommand('STATUS '.$server);
    }

    /**
     * Looks up the name of the server that would be used for an incoming
     * connection having the given host and pattern.
     *
     * @param string $host      Host
     * @param string $pattern   Branch pattern
     * @return server name
     * @throws IDF_Scm_Exception
     */
    public static function matchServer($host, $pattern)
    {
        $ret = self::_triggerCommand('MATCH '.$host.' '.$pattern);
        if (preg_match('/^OK: (.+)/', $ret, $m))
            return $m[1];
        preg_match('/^ERROR: (.+)/', $ret, $m);
        throw new IDF_Scm_Exception('could not match server: '.$m[1]);
    }

    /**
     * Prevent the given local server from receiving further connections,
     * and stop it once all connections are closed. The return value will
     * be the new status of that server: ACTIVE local servers will become
     * STOPPING, and WAITING and SLEEPING serveres become STOPPED.
     * Servers in other states are not affected.
     *
     * @param string $server
     * @return string State of the server after the command
     */
    public static function stopServer($server)
    {
        return self::_triggerCommand("STOP $server");
    }

    /**
     * Allow a STOPPED or STOPPING server to receive connections again.
     * The return value is the new status of that server: STOPPING servers
     * become ACTIVE, and STOPPED servers become SLEEPING. Servers in other
     * states are not affected.
     *
     * @param string $server
     * @return string State of the server after the command
     */
    public static function startServer($server)
    {
        return self::_triggerCommand('START '.$server);
    }

    /**
     * Immediately kill the given local server, dropping any open connections,
     * and prevent is from receiving new connections and restarting. The named
     * server will immediately change to state STOPPED.
     *
     * @param string $server
     * @return bool True if successful
     */
    public static function killServer($server)
    {
        return self::_triggerCommand('KILL_NOW '.$server) == 'ok';
    }

    /**
     * Do not accept new connections for any servers, local or remote.
     *
     * @return bool True if successful
     */
    public static function shutDown()
    {
        return self::_triggerCommand('SHUTDOWN') == 'ok';
    }

    /**
     * Begin accepting connections after a SHUTDOWN.
     *
     * @return bool True if successful
     */
    public static function startUp()
    {
        return self::_triggerCommand('STARTUP') == 'ok';
    }

    /**
     * Reload the config file, the same as sending SIGHUP.
     *
     * @return bool True if successful (after the configuration was reloaded)
     */
    public static function reload()
    {
        return self::_triggerCommand('RELOAD') == 'ok';
    }

    private static function _triggerCommand($cmd)
    {
        $uc = Pluf::f('mtn_usher_conf', false);
        if (!$uc || !is_readable($uc)) {
            throw new IDF_Scm_Exception(
                '"mtn_usher_conf" is not configured or not readable'
            );
        }

        $parsed_config =
            IDF_Scm_Monotone_BasicIO::parse(file_get_contents($uc));
        $host = $port = $user = $pass = null;
        foreach ($parsed_config as $stanza) {
            foreach ($stanza as $line) {
                if ($line['key'] == 'adminaddr') {
                    list($host, $port) = explode(":", @$line['values'][0]);
                    break;
                }
                if ($line['key'] == 'userpass') {
                    $user = @$line['values'][0];
                    $pass = @$line['values'][1];
                }
            }
        }

        if (empty($host)) {
            throw new IDF_Scm_Exception('usher host is empty');
        }
        if (!preg_match('/^\d+$/', $port))
        {
            throw new IDF_Scm_Exception('usher port is invalid');
        }

        if (empty($user)) {
            throw new IDF_Scm_Exception('usher user is empty');
        }

        if (empty($pass)) {
            throw new IDF_Scm_Exception('usher pass is empty');
        }

        $sock = @fsockopen($host, $port, $errno, $errstr);
        if (!$sock) {
            throw new IDF_Scm_Exception(
                "could not connect to usher: $errstr ($errno)"
            );
        }

        fwrite($sock, 'USERPASS '.$user.' '.$pass."\n");
        if (feof($sock)) {
            throw new IDF_Scm_Exception(
                'usher closed the connection - this should not happen'
            );
        }

        fwrite($sock, $cmd."\n");
        $out = '';
        while (!feof($sock)) {
            $out .= fgets($sock);
        }
        fclose($sock);
        $out = rtrim($out);

        if ($out == 'unknown command') {
            throw new IDF_Scm_Exception('unknown command: '.$cmd);
        }

        return $out;
    }
}

