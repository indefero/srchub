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
 * Monotone scm class
 *
 * @author Thomas Keller <me@thomaskeller.biz>
 */
class IDF_Scm_Monotone extends IDF_Scm
{
    /** the minimum supported interface version */
    public static $MIN_INTERFACE_VERSION = 13.0;

    private static $instances = array();

    private $stdio;

    /**
     * Constructor
     */
    public function __construct(IDF_Project $project, IDF_Scm_Monotone_IStdio $stdio)
    {
        $this->project = $project;
        $this->stdio = $stdio;
    }

    /**
     * Returns the stdio instance in use
     *
     * @return IDF_Scm_Monotone_Stdio
     */
    public function getStdio()
    {
        return $this->stdio;
    }

    /**
     * @see IDF_Scm::getRepositorySize()
     */
    public function getRepositorySize()
    {
        // FIXME: this obviously won't work with remote databases - upstream
        // needs to implement mtn db info in automate at first
        $repo = sprintf(Pluf::f('mtn_repositories'), $this->project->shortname);
        if (!file_exists($repo)) {
            return 0;
        }

        $cmd = Pluf::f('idf_exec_cmd_prefix', '').'du -sk '
            .escapeshellarg($repo);
        $out = explode(' ',
                       self::shell_exec('IDF_Scm_Monotone::getRepositorySize', $cmd),
                       2);
        return (int) $out[0]*1024;
    }

    /**
     * @see IDF_Scm::isAvailable()
     */
    public function isAvailable()
    {
        try
        {
            $out = $this->stdio->exec(array('interface_version'));
            return floatval($out) >= self::$MIN_INTERFACE_VERSION;
        }
        catch (IDF_Scm_Exception $e) {}

        return false;
    }

    /**
     * @see IDF_Scm::getBranches()
     */
    public function getBranches()
    {
        if (isset($this->cache['branches'])) {
            return $this->cache['branches'];
        }
        // FIXME: we could / should introduce handling of suspended
        // (i.e. dead) branches here by hiding them from the user's eye...
        $out = $this->stdio->exec(array('branches'));

        // note: we could expand each branch with one of its head revisions
        // here, but these would soon become bogus anyway and we cannot
        // map multiple head revisions here either, so we just use the
        // selector as placeholder
        $res = array();
        foreach (preg_split("/\n/", $out, -1, PREG_SPLIT_NO_EMPTY) as $b) {
            $res["h:$b"] = $b;
        }

        $this->cache['branches'] = $res;
        return $res;
    }

    /**
     * monotone has no concept of a "main" branch, so just return
     * the configured one. Ensure however that we can select revisions
     * with it at all.
     *
     * @see IDF_Scm::getMainBranch()
     */
    public function getMainBranch()
    {
        $conf = $this->project->getConf();
        if (false === ($branch = $conf->getVal('mtn_master_branch', false))
            || empty($branch)) {
            $branch = "*";
        }

        return $branch;
    }

    /**
     * @see IDF_Scm::getArchiveStream
     */
    public function getArchiveStream($commit, $prefix = null)
    {
        $revs = $this->_resolveSelector($commit);
        // sanity: this should actually not happen, because the
        // revision is validated before already
        if (count($revs) == 0) {
            throw new IDF_Scm_Exception("$commit is not a valid revision");
        }
        return new IDF_Scm_Monotone_ZipRender($this->stdio, $revs[0]);
    }

    /**
     * expands a selector or a partial revision id to zero, one or
     * multiple 40 byte revision ids
     *
     * @param string $selector
     * @return array
     */
    private function _resolveSelector($selector)
    {
        $out = $this->stdio->exec(array('select', $selector));
        return preg_split("/\n/", $out, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Queries the certs for a given revision and returns them in an
     * associative array array("branch" => array("branch1", ...), ...)
     *
     * @param string
     * @param array
     */
    private function _getCerts($rev)
    {
        $cache = Pluf_Cache::factory();
        $cachekey = 'mtn-plugin-certs-for-rev-' . $rev;
        $certs = $cache->get($cachekey);

        if ($certs === null) {
            $out = $this->stdio->exec(array('certs', $rev));

            $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);
            $certs = array();
            foreach ($stanzas as $stanza) {
                $certname = null;
                foreach ($stanza as $stanzaline) {
                    // luckily, name always comes before value
                    if ($stanzaline['key'] == 'name') {
                        $certname = $stanzaline['values'][0];
                        continue;
                    }

                    if ($stanzaline['key'] == 'value') {
                        if (!array_key_exists($certname, $certs)) {
                            $certs[$certname] = array();
                        }

                        $certs[$certname][] = $stanzaline['values'][0];
                        break;
                    }
                }
            }
            $cache->set($cachekey, $certs);
        }

        return $certs;
    }

    /**
     * Returns unique certificate values for the given revs and the specific
     * cert name, optionally prefixed with $prefix
     *
     * @param array
     * @param string
     * @param string
     * @return array
     */
    private function _getUniqueCertValuesFor($revs, $certName, $prefix)
    {
        $certValues = array();
        foreach ($revs as $rev) {
            $certs = $this->_getCerts($rev);
            if (!array_key_exists($certName, $certs))
                continue;
            foreach ($certs[$certName] as $certValue) {
                $certValues[] = "$prefix$certValue";
            }
        }
        return array_unique($certValues);
    }

    /**
     * @see IDF_Scm::inBranches()
     */
    public function inBranches($commit, $path)
    {
        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0) return array();
        return $this->_getUniqueCertValuesFor($revs, 'branch', 'h:');
    }

    /**
     * @see IDF_Scm::getTags()
     */
    public function getTags()
    {
        if (isset($this->cache['tags'])) {
            return $this->cache['tags'];
        }

        $out = $this->stdio->exec(array('tags'));

        $tags = array();
        $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);
        foreach ($stanzas as $stanza) {
            $tagname = null;
            foreach ($stanza as $stanzaline) {
                // revision comes directly after the tag stanza
                if ($stanzaline['key'] == 'tag') {
                    $tagname = $stanzaline['values'][0];
                    continue;
                }
                if ($stanzaline['key'] == 'revision') {
                    // FIXME: warn if multiple revisions have
                    // equally named tags
                    if (!array_key_exists("t:$tagname", $tags)) {
                        $tags["t:$tagname"] = $tagname;
                    }
                    break;
                }
            }
        }

        $this->cache['tags'] = $tags;
        return $tags;
    }

    /**
     * @see IDF_Scm::inTags()
     */
    public function inTags($commit, $path)
    {
        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0) return array();
        return $this->_getUniqueCertValuesFor($revs, 'tag', 't:');
    }

    /**
     * Takes a single stanza coming from an extended manifest output
     * and converts it into a file structure used by IDF
     *
     * @param string $forceBasedir  If given then the element's path is checked
     *                              to be directly beneath the given directory.
     *                              If not, null is returned and the parsing is
     *                              aborted.
     * @return array | null
     */
    private function _fillFileEntry(array $manifestEntry, $forceBasedir = null)
    {
        $fullpath = $manifestEntry[0]['values'][0];
        $filename = basename($fullpath);
        $dirname = dirname($fullpath);
        $dirname = $dirname == '.' ? '' : $dirname;

        if ($forceBasedir !== null && $forceBasedir != $dirname) {
            return null;
        }

        $file = array();
        $file['file'] = $filename;
        $file['fullpath'] = $fullpath;
        $file['efullpath'] = self::smartEncode($fullpath);

        $wanted_mark = '';
        if ($manifestEntry[0]['key'] == 'dir') {
            $file['type'] = 'tree';
            $file['size'] = 0;
            $wanted_mark = 'path_mark';
        }
        else {
            $file['type'] = 'blob';
            $file['hash'] = $manifestEntry[1]['hash'];
            $size = 0;
            foreach ($manifestEntry as $line) {
                if ($line['key'] == 'size') {
                    $size = $line['values'][0];
                    break;
                }
            }
            $file['size'] = $size;
            $wanted_mark = 'content_mark';
        }

        $rev_mark = null;
        foreach ($manifestEntry as $line) {
            if ($line['key'] == $wanted_mark) {
                $rev_mark = $line['hash'];
                break;
            }
        }

        if ($rev_mark !== null) {
            $file['rev'] = $rev_mark;
            $certs = $this->_getCerts($rev_mark);

            // FIXME: this assumes that author, date and changelog are always given
            $file['author'] = implode(", ", $certs['author']);

            $dates = array();
            foreach ($certs['date'] as $date)
                $dates[] = date('Y-m-d H:i:s', strtotime($date));
            $file['date'] = implode(', ', $dates);
            $combinedChangelog = implode("\n---\n", $certs['changelog']);
            $split = preg_split("/[\n\r]/", $combinedChangelog, 2);
            // FIXME: the complete log message is currently not used in the
            // tree view (the same is true for the other SCM implementations)
            // but we _should_ really use or at least return that here
            // in case we want to do fancy stuff like described in
            // issue 492
            $file['log'] =  $split[0];
        }

        return $file;
    }

    /**
     * @see IDF_Scm::getTree()
     */
    public function getTree($commit, $folder='/', $branch=null)
    {
        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0) {
            return array();
        }

        $out = $this->stdio->exec(array(
            'get_extended_manifest_of', $revs[0]
        ));

        $files = array();
        $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);
        $folder = $folder == '/' || empty($folder) ? '' : $folder;

        foreach ($stanzas as $stanza) {
            if ($stanza[0]['key'] == 'format_version')
                continue;

            $file = $this->_fillFileEntry($stanza, $folder);
            if ($file === null)
                continue;

            $files[] = (object) $file;
        }
        return $files;
    }

    /**
     * @see IDF_Scm::findAuthor()
     */
    public function findAuthor($author)
    {
        // We extract anything which looks like an email.
        $match = array();
        if (!preg_match('/([^ ]+@[^ ]+)/', $author, $match)) {
            return null;
        }
        $sql = new Pluf_SQL('login=%s', array($match[1]));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if ($users->count() > 0) {
            return $users[0];
        }
        return Pluf::factory('IDF_EmailAddress')->get_user_for_email_address($match[1]);
    }

    /**
     * @see IDF_Scm::getAnonymousAccessUrl()
     */
    public static function getAnonymousAccessUrl($project, $commit = null)
    {
        $scm = IDF_Scm::get($project);
        $branch = $scm->getMainBranch();

        if (!empty($commit)) {
            $revs = $scm->_resolveSelector($commit);
            if (count($revs) > 0) {
                $certs = $scm->_getCerts($revs[0]);
                // for the very seldom case that a revision
                // has no branch certificate
                if (!array_key_exists('branch', $certs)) {
                    $branch = '*';
                }
                else
                {
                    $branch = $certs['branch'][0];
                }
            }
        }

        $remote_url = Pluf::f('mtn_remote_url', '');
        if (empty($remote_url)) {
            return '';
        }

        return sprintf($remote_url, $project->shortname).'?'.$branch;
    }

    /**
     * @see IDF_Scm::getAuthAccessUrl()
     */
    public static function getAuthAccessUrl($project, $user, $commit = null)
    {
        $url = self::getAnonymousAccessUrl($project, $commit);
        return preg_replace("#^ssh://#", "ssh://$user@", $url);
    }

    /**
     * Returns this object correctly initialized for the project.
     *
     * @param IDF_Project
     * @return IDF_Scm_Monotone
     */
    public static function factory($project)
    {
        if (!array_key_exists($project->shortname, self::$instances)) {
            $stdio = new IDF_Scm_Monotone_Stdio($project);
            self::$instances[$project->shortname] =
                new IDF_Scm_Monotone($project, $stdio);
        }
        return self::$instances[$project->shortname];
    }

    /**
     * @see IDF_Scm::validateRevision()
     */
    public function validateRevision($commit)
    {
        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0)
            return IDF_Scm::REVISION_INVALID;

        if (count($revs) > 1)
            return IDF_Scm::REVISION_AMBIGUOUS;

        return IDF_Scm::REVISION_VALID;
    }

    /**
     * @see IDF_Scm::disambiguateRevision
     */
    public function disambiguateRevision($commit)
    {
        $revs = $this->_resolveSelector($commit);

        $out = array();
        foreach ($revs as $rev)
        {
            $certs = $this->_getCerts($rev);

            $log = array();
            $log['author'] = implode(', ', $certs['author']);

            $log['branch'] = implode(', ', $certs['branch']);

            $dates = array();
            foreach ($certs['date'] as $date)
                $dates[] = date('Y-m-d H:i:s', strtotime($date));
            $log['date'] = implode(', ', $dates);

            $combinedChangelog = implode("\n---\n", $certs['changelog']);
            $split = preg_split("/[\n\r]/", $combinedChangelog, 2);
            $log['title'] = $split[0];
            $log['full_message'] = (isset($split[1])) ? trim($split[1]) : '';

            $log['commit'] = $rev;

            $out[] = (object)$log;
        }

        return $out;
    }

    /**
     * @see IDF_Scm::getPathInfo()
     */
    public function getPathInfo($file, $commit = null)
    {
        if ($commit === null) {
            $commit = 'h:' . $this->getMainBranch();
        }

        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0)
            return false;

        $out = $this->stdio->exec(array(
            'get_extended_manifest_of', $revs[0]
        ));

        $files = array();
        $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);

        foreach ($stanzas as $stanza) {
            if ($stanza[0]['values'][0] != $file)
                continue;

            $file = $this->_fillFileEntry($stanza);
            return (object) $file;
        }
        return false;
    }

    /**
     * @see IDF_Scm::getFile()
     */
    public function getFile($def, $cmd_only=false)
    {
        // this won't work with remote databases
        if ($cmd_only) {
            throw new Pluf_Exception_NotImplemented();
        }

        return $this->stdio->exec(array('get_file', $def->hash));
    }

    /**
     * Returns the differences between two revisions as unified diff
     *
     * @param string    The target of the diff
     * @param string    The source of the diff, if not given, the first
     *                  parent of the target is used
     * @return string
     */
    private function _getDiff($target, $source = null)
    {
        if (empty($source)) {
            $source = "p:$target";
        }

        // FIXME: add real support for merge revisions here which have
        // two distinct diff sets
        $targets = $this->_resolveSelector($target);
        $sources = $this->_resolveSelector($source);

        if (count($targets) == 0 || count($sources) == 0) {
            return '';
        }

        // if target contains a root revision, we cannot produce a diff
        if (empty($sources[0])) {
            return '';
        }

        return $this->stdio->exec(
            array('content_diff'),
            array('r' => array($sources[0], $targets[0]))
        );
    }

    /**
     * @see IDF_Scm::getChanges()
     */
    public function getChanges($commit)
    {
        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0)
            return false;

        $revision = $revs[0];
        $out = $this->stdio->exec(array('get_revision', $revision));
        $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);

        $return = (object) array(
            'additions'  => array(),
            'deletions'  => array(),
            'renames'    => array(),
            'copies'     => array(),
            'patches'    => array(),
            'properties' => array(),
        );

        foreach ($stanzas as $stanza) {
            if ($stanza[0]['key'] == 'format_version' ||
                $stanza[0]['key'] == 'old_revision' ||
                $stanza[0]['key'] == 'new_manifest')
                continue;

            if ($stanza[0]['key'] == 'add_file' ||
                $stanza[0]['key'] == 'add_dir') {
                $return->additions[] = $stanza[0]['values'][0];
                continue;
            }

            if ($stanza[0]['key'] == 'delete') {
                $return->deletions[] = $stanza[0]['values'][0];
                continue;
            }

            if ($stanza[0]['key'] == 'rename') {
                $return->renames[$stanza[0]['values'][0]] =
                    $stanza[1]['values'][0];
                continue;
            }

            if ($stanza[0]['key'] == 'patch') {
                $return->patches[] = $stanza[0]['values'][0];
                continue;
            }

            if ($stanza[0]['key'] == 'clear' ||
                $stanza[0]['key'] == 'set') {

                $filename = $stanza[0]['values'][0];
                if (!array_key_exists($filename, $return->properties)) {
                    $return->properties[$filename] = array();
                }
                $key = $stanza[1]['values'][0];
                $value = null;
                if (isset($stanza[2])) {
                    $value = $stanza[2]['values'][0];
                }
                $return->properties[$filename][$key] = $value;
                continue;
            }
        }

        return $return;
    }

    /**
     * @see IDF_Scm::getCommit()
     */
    public function getCommit($commit, $getdiff=false)
    {
        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0)
            return false;

        $res = array();

        $parents = $this->stdio->exec(array('parents', $revs[0]));
        $res['parents'] = preg_split("/\n/", $parents, -1, PREG_SPLIT_NO_EMPTY);

        $certs = $this->_getCerts($revs[0]);

        // FIXME: this assumes that author, date and changelog are always given
        $res['author'] = implode(', ', $certs['author']);

        $dates = array();
        foreach ($certs['date'] as $date)
            $dates[] = date('Y-m-d H:i:s', strtotime($date));
        $res['date'] = implode(', ', $dates);

        $combinedChangelog = implode("\n---\n", $certs['changelog']);
        $split = preg_split("/[\n\r]/", $combinedChangelog, 2);
        $res['title'] = $split[0];
        $res['full_message'] = (isset($split[1])) ? trim($split[1]) : '';

        $res['branch'] = implode(', ', $certs['branch']);
        $res['commit'] = $revs[0];

        $res['diff'] = ($getdiff) ? $this->_getDiff($revs[0]) : '';

        return (object) $res;
    }

    /**
     * @see IDF_Scm::getProperties()
     */
    public function getProperties($rev, $path='')
    {
        $out = $this->stdio->exec(array('interface_version'));
        // support for querying file attributes of committed revisions
        // was added for mtn 1.1 (interface version 13.1)
        if (floatval($out) < 13.1)
            return array();

        $out = $this->stdio->exec(array('get_attributes', $path), array('r' => $rev));
        $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);
        $res = array();

        foreach ($stanzas as $stanza) {
            $line = $stanza[0];
            $res[$line['values'][0]] = $line['values'][1];
        }

        return $res;
    }

    /**
     * @see IDF_Scm::getExtraProperties
     */
    public function getExtraProperties($obj)
    {
        return (isset($obj->parents)) ? array('parents' => $obj->parents) : array();
    }

    /**
     * @see IDF_Scm::isCommitLarge()
     */
    public function isCommitLarge($commit=null)
    {
        if (empty($commit)) {
            $commit = 'h:'.$this->getMainBranch();
        }

        $revs = $this->_resolveSelector($commit);
        if (count($revs) == 0)
            return false;

        $out = $this->stdio->exec(array(
            'get_revision', $revs[0]
        ));

        $newAndPatchedFiles = 0;
        $stanzas = IDF_Scm_Monotone_BasicIO::parse($out);

        foreach ($stanzas as $stanza) {
            if ($stanza[0]['key'] == 'patch' || $stanza[0]['key'] == 'add_file')
                $newAndPatchedFiles++;
        }

        return $newAndPatchedFiles > 100;
    }

    /**
     * @see IDF_Scm::getChangeLog()
     */
    public function getChangeLog($commit=null, $n=10)
    {
        $horizont = $this->_resolveSelector($commit);
        $initialBranches = array();
        $logs = array();

        while (!empty($horizont) && $n > 0) {
            if (count($horizont) > 1) {
                $out = $this->stdio->exec(array('toposort') + $horizont);
                $horizont = preg_split("/\n/", $out, -1, PREG_SPLIT_NO_EMPTY);
            }

            $rev = array_shift($horizont);
            $certs = $this->_getCerts($rev);

            // read in the initial branches we should follow
            if (count($initialBranches) == 0) {
                if (!isset($certs['branch'])) {
                    // this revision has no branch cert, we cannot start logging
                    // from this revision
                    continue;
                }
                $initialBranches = $certs['branch'];
            }

            // only add it to our log if it is on one of the initial branches
            // ignore revisions without any branch certificate
            if (count(array_intersect($initialBranches, (array)@$certs['branch'])) > 0) {
                --$n;

                $log = array();
                $log['author'] = implode(', ', $certs['author']);

                $dates = array();
                foreach ($certs['date'] as $date)
                    $dates[] = date('Y-m-d H:i:s', strtotime($date));
                $log['date'] = implode(', ', $dates);

                $combinedChangelog = implode("\n---\n", $certs['changelog']);
                $split = preg_split("/[\n\r]/", $combinedChangelog, 2);
                $log['title'] = $split[0];
                $log['full_message'] = (isset($split[1])) ? trim($split[1]) : '';

                $log['commit'] = $rev;

                $logs[] = (object)$log;

                $out = $this->stdio->exec(array('parents', $rev));
                $horizont += preg_split("/\n/", $out, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        return $logs;
    }
}

