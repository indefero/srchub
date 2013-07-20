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
 * Subversion backend.
 * When a branch is not a branch.
 *
 * Contrary to most other SCMs, Subversion is using folders to manage
 * the branches and so what is either the commit or the branch in
 * other SCMs is the revision number with Subversion. So, do not be
 * surprised if you have the feeling that the methods are not really
 * returning what could be expected from their names.
 */
class IDF_Scm_Svn extends IDF_Scm
{
    public $username = '';
    public $password = '';
    private $assoc = array('dir' => 'tree',
                           'file' => 'blob');

    public function __construct($repo, $project=null)
    {
        $this->repo = $repo;
        $this->project = $project;
        $this->cache['commitmess'] = array();
    }

    public function isAvailable()
    {
        $cmd = $this->svnCmd(array('info', '--xml'), $this->repo);
        $xmlInfo = self::shell_exec('IDF_Scm_Svn::isAvailable', $cmd);

        try {
            $xml = simplexml_load_string($xmlInfo);
        }
        catch (Exception $e) {
            return false;
        }
        if (!isset($xml->entry->commit['revision'])) {
            return false;
        }
        if (0 == (int)$xml->entry->commit['revision']) {
            return false;
        }
        return true;
    }

    public function getRepositorySize()
    {
        if (strpos($this->repo, 'file://') !== 0) {
            return -1;
        }
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').'du -sk '
            .escapeshellarg(substr($this->repo, 7));
        $out = explode(' ', self::shell_exec('IDF_Scm_Svn::getRepositorySize', $cmd), 2);
        return (int) $out[0]*1024;
    }

    /**
     * Given the string describing the author from the log find the
     * author in the database.
     *
     * @param string Author
     * @return mixed Pluf_User or null
     */
    public function findAuthor($author)
    {
        $sql = new Pluf_SQL('login=%s', array(trim($author)));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        return ($users->count() > 0) ? $users[0] : null;
    }

    /**
     * Returns the URL of the subversion repository.
     *
     * @param IDF_Project
     * @param string
     * @return string URL
     */
    public static function getAnonymousAccessUrl($project,$commit=null)
    {
        $conf = $project->getConf();
        if (false !== ($url=$conf->getVal('svn_remote_url', false))
            && !empty($url)) {
            // Remote repository
            return $url;
        }
        return sprintf(Pluf::f('svn_remote_url'), $project->shortname);
    }

    /**
     * Returns the URL of the subversion repository.
     *
     * @param IDF_Project
     * @param string
     * @return string URL
     */
    public static function getAuthAccessUrl($project, $user, $commit=null)
    {
        $conf = $project->getConf();
        if (false !== ($url=$conf->getVal('svn_remote_url', false))
            && !empty($url)) {
            // Remote repository
            return $url;
        }
        return sprintf(Pluf::f('svn_remote_url'), $project->shortname);
    }

    /**
     * Returns this object correctly initialized for the project.
     *
     * @param IDF_Project
     * @return IDF_Scm_Svn
     */
    public static function factory($project)
    {
        $conf = $project->getConf();
        // Find the repository
        if (false !== ($rep=$conf->getVal('svn_remote_url', false))
            && !empty($rep)) {
            // Remote repository
            $scm = new IDF_Scm_Svn($rep, $project);
            $scm->username = $conf->getVal('svn_username');
            $scm->password = $conf->getVal('svn_password');
            return $scm;
        } else {
            $rep = sprintf(Pluf::f('svn_repositories'), $project->shortname);
            return new IDF_Scm_Svn($rep, $project);
        }
    }

    /**
     * Subversion revisions are either a number or 'HEAD'.
     */
    public function validateRevision($rev)
    {
        if ($rev == 'HEAD') {
            return IDF_Scm::REVISION_VALID;
        }

        $cmd = $this->svnCmd(array('info'), $this->repo, $rev);
        self::exec('IDF_Scm_Svn::validateRevision', $cmd, $out, $ret);

        if ($ret == 0)
            return IDF_Scm::REVISION_VALID;
        return IDF_Scm::REVISION_INVALID;
    }

    /**
     * Test a given object hash.
     *
     * @param string Object hash.
     * @return mixed false if not valid or 'blob', 'tree', 'commit'
     */
    public function testHash($rev, $path='')
    {
        // OK if HEAD on /
        if ($rev === 'HEAD' && $path === '') {
            return 'commit';
        }

        // Else, test the path on revision
        $cmd = $this->svnCmd(array('info', '--xml'),
                             $this->repo.'/'.self::smartEncode($path),
                             $rev);
        $xmlInfo = self::shell_exec('IDF_Scm_Svn::testHash', $cmd);

        // If exception is thrown, return false
        try {
            $xml = simplexml_load_string($xmlInfo);
        }
        catch (Exception $e) {
            return false;
        }

        // If the entry node does exists, params are wrong
        if (!isset($xml->entry)) {
            return false;
        }

        // Else, enjoy it :)
        return 'commit';
    }

    public function getTree($commit, $folder='/', $branch=null)
    {
        $cmd = $this->svnCmd(array('ls', '--xml'),
                             $this->repo.'/'.self::smartEncode($folder),
                             $commit);
        $xml = simplexml_load_string(self::shell_exec('IDF_Scm_Svn::getTree', $cmd));
        $res = array();
        $folder = (strlen($folder) and ($folder != '/')) ? $folder.'/' : '';
        foreach ($xml->list->entry as $entry) {
            $file = array();
            $file['type'] = $this->assoc[(string) $entry['kind']];
            $file['file'] = (string) $entry->name;
            $file['fullpath'] = $folder.((string) $entry->name);
            $file['efullpath'] = self::smartEncode($file['fullpath']);
            $file['date'] = gmdate('Y-m-d H:i:s',
                                   strtotime((string) $entry->commit->date));
            $file['rev'] = (string) $entry->commit['revision'];
            $file['log'] = $this->getCommitMessage($file['rev']);
            // Get the size if the type is blob
            if ($file['type'] == 'blob') {
                $file['size'] = (string) $entry->size;
            }
            $file['author'] = (string) $entry->commit->author;
            $file['perm'] = '';
            $res[] = (object) $file;
        }
        return $res;
    }

    /**
     * Get the commit message of a revision revision.
     *
     * @param string Commit ('HEAD')
     * @return String commit message
     */
    private function getCommitMessage($rev='HEAD')
    {
        if (isset($this->cache['commitmess'][$rev])) {
            return $this->cache['commitmess'][$rev];
        }
        $cmd = $this->svnCmd(array('log', '--xml', '--limit', '1'), $this->repo, $rev);
        try {
            $xml = simplexml_load_string(self::shell_exec('IDF_Scm_Svn::getCommitMessage', $cmd));
            $this->cache['commitmess'][$rev] = (string) $xml->logentry->msg;
        }
        catch (Exception $e) {
            $this->cache['commitmess'][$rev] = '';
        }
        return $this->cache['commitmess'][$rev];
    }

    public function getPathInfo($filename, $rev=null)
    {
        if ($rev == null) {
            $rev = 'HEAD';
        }
        $cmd = $this->svnCmd(array('info', '--xml'),
                             $this->repo.'/'.self::smartEncode($filename), $rev);
        $xml = simplexml_load_string(self::shell_exec('IDF_Scm_Svn::getPathInfo', $cmd));
        if (!isset($xml->entry)) {
            return false;
        }
        $entry = $xml->entry;
        $file = array();
        $file['fullpath'] = $filename;
        $file['hash'] = (string) $entry->repository->uuid;
        $file['type'] = $this->assoc[(string) $entry['kind']];
        $pathinfo = pathinfo($filename);
        $file['file'] = $pathinfo['basename'];
        $file['rev'] = $rev;
        $file['author'] = (string) $entry->author;
        $file['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $entry->commit->date));
        $file['size'] = (string) $entry->size;
        $file['log'] = '';
        return (object) $file;
    }

    public function getFile($def, $cmd_only=false)
    {
        $cmd = $this->svnCmd(array('cat'),
                             $this->repo.'/'.self::smartEncode($def->fullpath),
                             $def->rev);
        return ($cmd_only) ?
            $cmd : self::shell_exec('IDF_Scm_Svn::getFile', $cmd);
    }

    /**
     * Subversion branches are folder based.
     *
     * One need to list the folder to know them.
     */
    public function getBranches()
    {
        if (isset($this->cache['branches'])) {
            return $this->cache['branches'];
        }
        $res = array();
        $cmd = $this->svnCmd(array('ls'), $this->repo.'/branches', 'HEAD');
        self::exec('IDF_Scm_Svn::getBranches', $cmd, $out, $ret);
        if ($ret == 0) {
            foreach ($out as $entry) {
                if (substr(trim($entry), -1) == '/') {
                    $branch = substr(trim($entry), 0, -1);
                    $res[$branch] = 'branches/'.$branch;
                }
            }
        }
        ksort($res);

        $cmd = $this->svnCmd(array('info'), $this->repo.'/trunk', 'HEAD');
        self::exec('IDF_Scm_Svn::getBranches', $cmd, $out, $ret);
        if ($ret == 0) {
            $res = array('trunk' => 'trunk') + $res;
        }
        $this->cache['branches'] = $res;
        return $res;
    }

    /**
     * Subversion tags are folder based.
     *
     * One need to list the folder to know them.
     */
    public function getTags()
    {
        if (isset($this->cache['tags'])) {
            return $this->cache['tags'];
        }
        $res = array();
        $cmd = $this->svnCmd(array('ls'), $this->repo.'/tags', 'HEAD');
        self::exec('IDF_Scm_Svn::getTags', $cmd, $out, $ret);
        if ($ret == 0) {
            foreach ($out as $entry) {
                if (substr(trim($entry), -1) == '/') {
                    $tag = substr(trim($entry), 0, -1);
                    $res[$tag] = 'tags/'.$tag;
                }
            }
        }
        ksort($res);
        $this->cache['tags'] = $res;
        return $res;
    }

    public function getMainBranch()
    {
        return 'HEAD';
    }

    public function inBranches($commit, $path)
    {
        foreach ($this->getBranches() as $branch => $bpath) {
            if ($bpath and 0 === strpos($path, $bpath)) {
                return array($branch);
            }
        }
        return array();
    }

    public function inTags($commit, $path)
    {
        foreach ($this->getTags() as $tag => $tpath) {
            if ($tpath and 0 === strpos($path, $tpath)) {
                return array($tag);
            }
        }
        return array();
    }

    /**
     * Get commit details.
     *
     * @param string Commit
     * @param bool Get commit diff (false)
     * @return array Changes
     */
    public function getCommit($commit, $getdiff=false)
    {
        if ($this->validateRevision($commit) != IDF_Scm::REVISION_VALID) {
            return false;
        }
        $res = array();
        $cmd = $this->svnCmd(array('log', '--xml', '--limit', '1', '-v'),
                             $this->repo, $commit);
        $xmlRes = self::shell_exec('IDF_Scm_Svn::getCommit', $cmd);
        $xml = simplexml_load_string($xmlRes);
        $res['author'] = (string) $xml->logentry->author;
        $res['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $xml->logentry->date));
        $res['title'] = (string) $xml->logentry->msg;
        $res['commit'] = (string) $xml->logentry['revision'];
        $res['parents'] = $xml->logentry['revision'] > 1
            ? array((string) $xml->logentry['revision'] - 1)
            : array();
        $res['diff'] = ($getdiff) ? $this->getDiff($commit) : '';
        $res['tree'] = '';
        $res['branch'] = '';
        return (object) $res;
    }

    /**
     * Check if a commit is big.
     *
     * @param string Commit ('HEAD')
     * @return bool The commit is big
     */
    public function isCommitLarge($commit='HEAD')
    {
        if (substr($this->repo, 0, 7) != 'file://') {
            return false;
        }
        // We have a locally hosted repository, we can query it with
        // svnlook
        $repo = substr($this->repo, 7);
        $cmd = sprintf(Pluf::f('svnlook_path', 'svnlook').' changed -r %s %s',
                       escapeshellarg($commit),
                       escapeshellarg($repo));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        $out = self::shell_exec('IDF_Scm_Svn::isCommitLarge', $cmd);
        $lines = preg_split("/\015\012|\015|\012/", $out);
        return (count($lines) > 100);
    }

    private function getDiff($rev='HEAD')
    {
        $res = array();
        $cmd = $this->svnCmd(array('diff', '-c', $rev), $this->repo);
        return self::shell_exec('IDF_Scm_Svn::getDiff', $cmd);
    }

    /**
     * @see IDF_Scm::getChanges()
     */
    public function getChanges($commit)
    {
        if ($this->validateRevision($commit) != IDF_Scm::REVISION_VALID) {
            return null;
        }
        $cmd = $this->svnCmd(array('log', '--xml', '-v'), $this->repo, $commit);
        $out = array();
        $out = self::shell_exec('IDF_Scm_Svn::getChanges', $cmd);
        $xml = simplexml_load_string($out);
        if (count($xml) == 0) {
            return null;
        }
        $entry = current($xml);

        $return = (object) array(
            'additions'  => array(),
            'deletions'  => array(),
            'patches'    => array(),
            // while SVN has support for attributes, we cannot see their changes
            // in the log's XML unfortunately
            'properties' => array(),
            'copies'     => array(),
            'renames'    => array(),
        );

        foreach ($entry->paths->path as $p) {
            $path = (string) $p;
            foreach ($p->attributes() as $k => $v) {
                $key = (string) $k;
                $val = (string) $v;
                if ($key != 'action')
                    continue;
                if ($val == 'M')
                    $return->patches[] = $path;
                else if ($val == 'A')
                    $return->additions[] = $path;
                else if ($val == 'D')
                    $return->deletions[] = $path;
            }
        }

        // copies are treated as renames if they have an add _and_ a drop;
        // only if they only have an add, but no drop, they're treated as copies
        foreach ($entry->paths->path as $p) {
            $trg = (string) $p;
            $src = null;
            foreach ($p->attributes() as $k => $v) {
                if ((string) $k == 'copyfrom-path') {
                    $src = (string) $v;
                    break;
                }
            }

            if ($src == null)
                continue;

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
     * Get latest changes.
     *
     * @param string Revision or ('HEAD').
     * @param int Number of changes (10).
     *
     * @return array Changes.
     */
    public function getChangeLog($rev=null, $n=10)
    {
        if ($rev != 'HEAD' and !preg_match('/^\d+$/', $rev)) {
            // we accept only revisions or HEAD
            $rev = 'HEAD';
        }
        $res = array();
        $cmd = $this->svnCmd(array('log', '--xml', '-v', '--limit', $n),
                             $this->repo.'@'.$rev);
        $xmlRes = self::shell_exec('IDF_Scm_Svn::getChangeLog', $cmd);
        $xml = simplexml_load_string($xmlRes);
        foreach ($xml->logentry as $entry) {
            $log = array();
            $log['author'] = (string) $entry->author;
            $log['date'] = gmdate('Y-m-d H:i:s', strtotime((string) $entry->date));
            $split = preg_split("[\n\r]", (string) $entry->msg, 2);
            $log['title'] = $split[0];
            $log['commit'] = (string) $entry['revision'];
            $log['full_message'] = (isset($split[1])) ? trim($split[1]) : '';
            $res[] = (object) $log;
        }
        return $res;
    }

    /**
     * Get additionnals properties on path and revision
     *
     * @param string File
     * @param string Commit ('HEAD')
     * @return array
     */
    public function getProperties($rev, $path='')
    {
        $res = array();
        $cmd = $this->svnCmd(array('proplist', '--xml'),
                             $this->repo.'/'.self::smartEncode($path), $rev);
        $xmlProps = self::shell_exec('IDF_Scm_Svn::getProperties', $cmd);
        $props = simplexml_load_string($xmlProps);

        // No properties, returns an empty array
        if (!isset($props->target)) {
            return $res;
        }

        // Get the value of each property
        foreach ($props->target->property as $prop) {
            $key = (string) $prop['name'];
            $res[$key] = $this->getProperty($key, $rev, $path);
        }

        return $res;
    }

    /**
     * Get a specific additionnal property on path and revision
     *
     * @param string Property
     * @param string File
     * @param string Commit ('HEAD')
     * @return string the property value
     */
    private function getProperty($property, $rev, $path='')
    {
        $res = array();
        $cmd = $this->svnCmd(array('propget', $property, '--xml'),
                             $this->repo.'/'.self::smartEncode($path), $rev);
        $xmlProp = self::shell_exec('IDF_Scm_Svn::getProperty', $cmd);
        $prop = simplexml_load_string($xmlProp);

        return (string) $prop->target->property;
    }

    /**
     * Get the number of the last commit in the repository.
     *
     * @param string Commit ('HEAD').
     *
     * @return String last number commit
     */
    public function getLastCommit($rev='HEAD')
    {
        $xmlInfo = '';
        $cmd = $this->svnCmd(array('info', '--xml'), $this->repo, $rev);
        $xmlInfo = self::shell_exec('IDF_Scm_Svn::getLastCommit', $cmd);

        $xml = simplexml_load_string($xmlInfo);
        return (string) $xml->entry->commit['revision'];
    }

    private function svnCmd($args = array(), $repoarg = null, $revarg = null)
    {
        $cmdline = array();
        $cmdline[] = Pluf::f('idf_exec_cmd_prefix', '');
        $cmdline[] = Pluf::f('svn_path', 'svn');
        $cmdline[] = '--no-auth-cache';
        $cmdline[] = '--username='.escapeshellarg($this->username);
        $cmdline[] = '--password='.escapeshellarg($this->password);

        foreach ($args as $arg) {
            $cmdline[] = escapeshellarg($arg);
        }

        if ($repoarg != null) {
            if ($revarg != null) {
                $repoarg .= '@'.$revarg;
            }
            $cmdline[] = escapeshellarg($repoarg);
        }

        if ($revarg != null) {
            $cmdline[] = '--revision='.escapeshellarg($revarg);
        }

        return implode(' ', $cmdline);
    }
}

