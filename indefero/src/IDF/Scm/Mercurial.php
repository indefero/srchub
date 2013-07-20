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

/**
 * A simple RAII helper that manages style files to format hg's log output
 */
class IDF_Scm_Mercurial_LogStyle
{
    const FULL_LOG = 1;
    const CHANGES = 2;

    public function __construct($type)
    {
        $this->file = tempnam(Pluf::f('tmp_folder'), 'hg-log-style-');

        if ($type == self::FULL_LOG) {
            $style = 'changeset = "'
                . 'changeset: {node|short}\n'
                . 'branch: {branch}\n'
                . 'author: {author}\n'
                . 'date: {date|isodate}\n'
                . 'parents: {parents}\n\n'
                . '{desc}\n'
                . '\0\n"'
                . "\n"
                . 'parent = "{node|short} "'
                . "\n";
        } elseif ($type == self::CHANGES) {
            $style = 'changeset = "'
                . 'file_mods: {file_mods}\n'
                . 'file_adds: {file_adds}\n'
                . 'file_dels: {file_dels}\n'
                . 'file_copies: {file_copies}\n\n'
                . '\0\n"'
                . "\n"
                . 'file_mod = "{file_mod}\0"'
                . "\n"
                . 'file_add = "{file_add}\0"'
                . "\n"
                . 'file_del = "{file_del}\0"'
                . "\n"
                . 'file_copy = "{source}\0{name}\0"'
                . "\n";
        } else {
            throw new IDF_Scm_Exception('invalid type ' . $type);
        }

        file_put_contents($this->file, $style);
    }

    public function __destruct()
    {
        @unlink($this->file);
    }

    public function get()
    {
        return $this->file;
    }
}

/**
 * Main SCM class for Mercurial
 *
 * Note: Some commands take a --debug option, this is not lousy coding, but
 *       totally wanted, as hg returns additional / different data in this
 *       mode on which this largely depends.
 */
class IDF_Scm_Mercurial extends IDF_Scm
{
    public function __construct($repo, $project=null)
    {
        $this->repo = $repo;
        $this->project = $project;
   }

    public function getRepositorySize()
    {
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').'du -sk '
            .escapeshellarg($this->repo);
        $out = explode(' ',
                       self::shell_exec('IDF_Scm_Mercurial::getRepositorySize',
                                        $cmd),
                       2);
        return (int) $out[0]*1024;
    }

    public static function factory($project)
    {
        $rep = sprintf(Pluf::f('mercurial_repositories'), $project->shortname);
        return new IDF_Scm_Mercurial($rep, $project);
    }

    public function isAvailable()
    {
        try {
            $branches = $this->getBranches();
        } catch (IDF_Scm_Exception $e) {
            return false;
        }
        return (count($branches) > 0);
    }

    public function findAuthor($author)
    {
        // We extract the email.
        $match = array();
        if (!preg_match('/<(.*)>/', $author, $match)) {
            return null;
        }
        return Pluf::factory('IDF_EmailAddress')->get_user_for_email_address($match[1]);
    }

    public function getMainBranch()
    {
        return 'tip';
    }

    public static function getAnonymousAccessUrl($project, $commit=null)
    {
        return sprintf(Pluf::f('mercurial_remote_url'), $project->shortname);
    }

    public static function getAuthAccessUrl($project, $user, $commit=null)
    {
        return sprintf(Pluf::f('mercurial_remote_url'), $project->shortname);
    }

    public function validateRevision($rev)
    {
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' log -R %s -r %s',
                       escapeshellarg($this->repo),
                       escapeshellarg($rev));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::validateRevision', $cmd, $out, $ret);

        // FIXME: apparently a given hg revision can also be ambigious -
        //        handle this case here sometime
        if ($ret == 0 && count($out) > 0)
            return IDF_Scm::REVISION_VALID;
        return IDF_Scm::REVISION_INVALID;
    }

    /**
     * Test a given object hash.
     *
     * @param string Object hash.
     * @param null to be svn client compatible
     * @return mixed false if not valid or 'blob', 'tree', 'commit'
     */
    public function testHash($hash, $dummy=null)
    {
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' log -R %s -r %s',
                       escapeshellarg($this->repo),
                       escapeshellarg($hash));
        $ret = 0;
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::testHash', $cmd, $out, $ret);
        return ($ret != 0) ? false : 'commit';
    }

    public function getTree($commit, $folder='/', $branch=null)
    {
        // now we grab the info about this commit including its tree.
        $folder = ($folder == '/') ? '' : $folder;
        $co = $this->getCommit($commit);
        if ($folder) {
            // As we are limiting to a given folder, we need to find
            // the tree corresponding to this folder.
            $found = false;
            foreach ($this->getTreeInfo($co->tree, true, '', true) as $file) {
                if ($file->type == 'tree' and $file->file == $folder) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new Exception(sprintf(__('Folder %1$s not found in commit %2$s.'), $folder, $commit));
            }
        }
        $res = $this->getTreeInfo($commit, $recurse=true, $folder);
        return $res;
    }

    /**
     * Get the tree info.
     *
     * @param string Tree hash
     * @param bool Do we recurse in subtrees (true)
     * @return array Array of file information.
     */
    public function getTreeInfo($tree, $recurse=true, $folder='', $root=false)
    {
        if ('commit' != $this->testHash($tree)) {
            throw new Exception(sprintf(__('Not a valid tree: %s.'), $tree));
        }
        $cmd_tmpl = Pluf::f('hg_path', 'hg').' manifest -R %s --debug -r %s';
        $cmd = sprintf($cmd_tmpl, escapeshellarg($this->repo),
                                  escapeshellarg($tree));
        $out = array();
        $res = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getTreeInfo', $cmd, $out);
        $tmp_hack = array();
        while (null !== ($line = array_pop($out))) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            $dir = explode('/', $file, -1);
            $tmp = '';
            for ($i=0, $n=count($dir); $i<$n; $i++) {
                if ($i > 0) {
                    $tmp .= '/';
                }
                $tmp .= $dir[$i];
                if (!isset($tmp_hack["empty\t000\t\t$tmp/"])) {
                    $out[] = "empty\t000\t\t$tmp/";
                    $tmp_hack["empty\t000\t\t$tmp/"] = 1;
                }
            }
            if (preg_match('/^(.*)\/$/', $file, $match)) {
                $type = 'tree';
                $file = $match[1];
            } else {
                $type = 'blob';
            }
            if (!$root and !$folder and preg_match('/^.*\/.*$/', $file)) {
                continue;
            }
            if ($folder) {
                preg_match('|^'.$folder.'[/]?([^/]+)?$|', $file,$match);
                if (count($match) > 1) {
                    $file = $match[1];
                } else {
                    continue;
                }
            }
            $fullpath = ($folder) ? $folder.'/'.$file : $file;
            $efullpath = self::smartEncode($fullpath);
            $res[] = (object) array('perm' => $perm, 'type' => $type,
                                    'hash' => $hash, 'fullpath' => $fullpath,
                                    'efullpath' => $efullpath, 'file' => $file);
        }
        return $res;
    }

    public function getPathInfo($totest, $commit='tip')
    {
        $cmd_tmpl = Pluf::f('hg_path', 'hg').' manifest -R %s --debug -r %s';
        $cmd = sprintf($cmd_tmpl, escapeshellarg($this->repo),
                                  escapeshellarg($commit));
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getPathInfo', $cmd, $out);
        $tmp_hack = array();
        while (null !== ($line = array_pop($out))) {
            list($hash, $perm, $exec, $file) = preg_split('/ |\t/', $line, 4);
            $file = trim($file);
            $dir = explode('/', $file, -1);
            $tmp = '';
            for ($i=0, $n=count($dir); $i<$n; $i++) {
                if ($i > 0) {
                    $tmp .= '/';
                }
                $tmp .= $dir[$i];
                if ($tmp == $totest) {
                    $pathinfo = pathinfo($totest);
                    return (object) array('perm' => '000', 'type' => 'tree',
                                          'hash' => $hash,
                                          'fullpath' => $totest,
                                          'file' => $pathinfo['basename'],
                                          'commit' => $commit
                                          );
                }
                if (!isset($tmp_hack["empty\t000\t\t$tmp/"])) {
                    $out[] = "empty\t000\t\t$tmp/";
                    $tmp_hack["empty\t000\t\t$tmp/"] = 1;
                }
            }
            if (preg_match('/^(.*)\/$/', $file, $match)) {
                $type = 'tree';
                $file = $match[1];
            } else {
                $type = 'blob';
            }
            if ($totest == $file) {
                $pathinfo = pathinfo($totest);
                return (object) array('perm' => $perm, 'type' => $type,
                                      'hash' => $hash,
                                      'fullpath' => $totest,
                                      'file' => $pathinfo['basename'],
                                      'commit' => $commit
                                      );
            }
        }
        return false;
    }

    public function getFile($def, $cmd_only=false)
    {
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' cat -R %s -r %s %s',
                       escapeshellarg($this->repo),
                       escapeshellarg($def->commit),
                       escapeshellarg($this->repo.'/'.$def->fullpath));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        return ($cmd_only) ?
            $cmd : self::shell_exec('IDF_Scm_Mercurial::getFile', $cmd);
    }

    /**
     * Get the branches.
     *
     * @return array Branches.
     */
    public function getBranches()
    {
        if (isset($this->cache['branches'])) {
            return $this->cache['branches'];
        }
        $out = array();
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' branches -R %s',
                       escapeshellarg($this->repo));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getBranches', $cmd, $out);
        $res = array();
        foreach ($out as $b) {
            preg_match('/(.+?)\s+\S+:(\S+)/', $b, $match);
            $res[$match[1]] = '';
        }
        $this->cache['branches'] = $res;
        return $res;
    }

    /**
     * Get the tags.
     *
     * @return array Tags.
     */
    public function getTags()
    {
        if (isset($this->cache['tags'])) {
            return $this->cache['tags'];
        }
        $out = array();
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' tags -R %s',
                       escapeshellarg($this->repo));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getTags', $cmd, $out);
        $res = array();
        foreach ($out as $b) {
            preg_match('/(.+?)\s+\S+:(\S+)/', $b, $match);
            $res[$match[1]] = '';
        }
        $this->cache['tags'] = $res;
        return $res;
    }

    public function inBranches($commit, $path)
    {
        return (in_array($commit, array_keys($this->getBranches())))
                ? array($commit) : array();
    }

    public function inTags($commit, $path)
    {
        return (in_array($commit, array_keys($this->getTags())))
                ? array($commit) : array();
    }

    /**
     * Get commit details.
     *
     * @param string Commit ('HEAD')
     * @param bool Get commit diff (false)
     * @return array Changes
     */
    public function getCommit($commit, $getdiff=false)
    {
        if ($this->validateRevision($commit) != IDF_Scm::REVISION_VALID) {
            return false;
        }

        $logStyle = new IDF_Scm_Mercurial_LogStyle(IDF_Scm_Mercurial_LogStyle::FULL_LOG);
        $tmpl = ($getdiff)
            ? Pluf::f('hg_path', 'hg').' log --debug -p -r %s -R %s --style %s'
            : Pluf::f('hg_path', 'hg').' log --debug -r %s -R %s --style %s';
        $cmd = sprintf($tmpl,
                       escapeshellarg($commit),
                       escapeshellarg($this->repo),
                       escapeshellarg($logStyle->get()));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        $out = self::shell_exec('IDF_Scm_Mercurial::getCommit', $cmd);
        if (strlen($out) == 0) {
            return false;
        }

        $diffStart = strpos($out, 'diff -r');
        $diff = '';
        if ($diffStart !== false) {
            $log = substr($out, 0, $diffStart);
            $diff = substr($out, $diffStart);
        } else {
            $log = $out;
        }

        $out = self::parseLog(preg_split('/\r\n|\n/', $log));
        $out[0]->diff = $diff;
        return $out[0];
    }

    /**
     * @see IDF_Scm::getChanges()
     */
    public function getChanges($commit)
    {
        if ($this->validateRevision($commit) != IDF_Scm::REVISION_VALID) {
            return null;
        }

        $logStyle = new IDF_Scm_Mercurial_LogStyle(IDF_Scm_Mercurial_LogStyle::CHANGES);
        $tmpl = Pluf::f('hg_path', 'hg').' log --debug -r %s -R %s --style %s';
        $cmd = sprintf($tmpl,
                       escapeshellarg($commit),
                       escapeshellarg($this->repo),
                       escapeshellarg($logStyle->get()));
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getChanges', $cmd, $out);
        $log = self::parseLog($out);
        // we expect only one log entry that contains all the needed information
        $log = $log[0];

        $return = (object) array(
            'additions'  => preg_split('/\0/', $log->file_adds, -1, PREG_SPLIT_NO_EMPTY),
            'deletions'  => preg_split('/\0/', $log->file_dels, -1, PREG_SPLIT_NO_EMPTY),
            'patches'    => preg_split('/\0/', $log->file_mods, -1, PREG_SPLIT_NO_EMPTY),
            // hg has no support for built-in attributes, so this keeps empty
            'properties' => array(),
            // these two are filled below
            'copies'     => array(),
            'renames'    => array(),
        );

        $file_copies = preg_split('/\0/', $log->file_copies, -1, PREG_SPLIT_NO_EMPTY);

        // copies are treated as renames if they have an add _and_ a drop;
        // only if they only have an add, but no drop, they're treated as copies
        for ($i=0; $i<count($file_copies); $i+=2) {
            $src = $file_copies[$i];
            $trg = $file_copies[$i+1];
            $srcidx = array_search($src, $return->deletions);
            $trgidx = array_search($trg, $return->additions);
            if ($srcidx !== false && $trgidx !== false) {
                $return->renames[$src] = $trg;
                unset($return->deletions[$srcidx]);
                unset($return->additions[$trgidx]);
                continue;
            }
            if ($srcidx === false && $trgidx !== false) {
                $return->copies[$src] = $trg;
                unset($return->additions[$trgidx]);
                continue;
            }
            // file sutures (counter-operation to copy) not supported
        }

        return $return;
    }

    /**
     * Check if a commit is big.
     *
     * @param string Commit ('HEAD')
     * @return bool The commit is big
     */
    public function isCommitLarge($commit='HEAD')
    {
        return false;
    }

    /**
     * Get latest changes.
     *
     * @param string Commit ('HEAD').
     * @param int Number of changes (10).
     * @return array Changes.
     */
    public function getChangeLog($commit='tip', $n=10)
    {
        $logStyle = new IDF_Scm_Mercurial_LogStyle(IDF_Scm_Mercurial_LogStyle::FULL_LOG);

        // hg accepts revision IDs as arguments to --branch / -b as well and
        // uses the branch of the revision in question to filter the other
        // revisions
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' log --debug -R %s -l%s --style %s -b %s',
                       escapeshellarg($this->repo),
                       $n,
                       escapeshellarg($logStyle->get()),
                       escapeshellarg($commit));
        $out = array();
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        self::exec('IDF_Scm_Mercurial::getChangeLog', $cmd, $out);
        return self::parseLog($out);
    }

    /**
     * Parse the log lines of our custom style format.
     *
     * @param array Lines.
     * @return array Change log.
     */
    public static function parseLog($lines)
    {
        $res = array();
        $c = array();
        $headers_processed = false;
        foreach ($lines as $line) {
            if ($line == "\0") {
                $headers_processed = false;
                if (count($c) > 0) {
                    if (array_key_exists('full_message', $c))
                        $c['full_message'] = trim($c['full_message']);
                    $res[] = (object) $c;
                }
                continue;
            }
            if (!$headers_processed && empty($line)) {
                $headers_processed = true;
                continue;
            }
            if (!$headers_processed && preg_match('/^(\S+):\s*(.*)/', $line, $match)) {
                $match[1] = strtolower($match[1]);
                if ($match[1] == 'changeset') {
                    $c = array();
                    $c['commit'] = $match[2];
                    $c['tree'] = $c['commit'];
                    $c['full_message'] = '';
                } elseif ($match[1] == 'author') {
                    $c['author'] = $match[2];
                } elseif ($match[1] == 'branch') {
                    $c['branch'] = empty($match[2]) ? 'default' : $match[2];
                } elseif ($match[1] == 'parents') {
                    $parents = preg_split('/\s+/', $match[2], -1, PREG_SPLIT_NO_EMPTY);
                    for ($i=0, $j=count($parents); $i<$j; ++$i) {
                        if ($parents[$i] == '000000000000')
                            unset($parents[$i]);
                    }
                    $c['parents'] = $parents;
                } else {
                    $c[$match[1]] = trim($match[2]);
                }
                if ($match[1] == 'date') {
                    $c['date'] = gmdate('Y-m-d H:i:s', strtotime($match[2]));
                }
                continue;
            }
            if ($headers_processed) {
                if (empty($c['title']))
                    $c['title'] = trim($line);
                else
                    $c['full_message'] .= trim($line)."\n";
                continue;
            }
        }
        return $res;
    }

    /**
     * Generate a zip archive at a given commit.
     *
     * @param string Commit
     * @param string Prefix ('git-repo-dump')
     * @return Pluf_HTTP_Response The HTTP response containing the zip archive
     */
    public function getArchiveStream($commit, $prefix='')
    {
        $cmd = sprintf(Pluf::f('idf_exec_cmd_prefix', '').
                       Pluf::f('hg_path', 'hg').' archive --type=zip -R %s -r %s -',
                       escapeshellarg($this->repo),
                       escapeshellarg($commit));
        return new Pluf_HTTP_Response_CommandPassThru($cmd, 'application/x-zip');
    }

    /**
     * @see IDF_Scm::getDiffPathStripLevel()
     */
    public function getDiffPathStripLevel()
    {
        return 1;
    }
}
