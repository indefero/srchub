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

require_once("simpletest/autorun.php");

/**
 * Test the monotone class.
 */
class IDF_Tests_TestMonotone extends UnitTestCase
{
    private $tmpdir, $dbfile, $mtnInstance;

    private function mtnCall($args, $stdin = null, $dir = null)
    {
        // if you have an SSH agent running for key caching,
        // please disable it
        $cmdline = array("mtn",
                        "--confdir", $this->tmpdir,
                        "--db", $this->dbfile,
                        "--norc",
                        "--timestamps");

        $cmdline = array_merge($cmdline, $args);

        $descriptorspec = array(
           0 => array("pipe", "r"),
           1 => array("pipe", "w"),
           2 => array("file", "{$this->tmpdir}/mtn-errors", "a")
        );

        $pipes = array();
        $dir = !empty($dir) ? $dir : $this->tmpdir;
        $process = proc_open(implode(" ", $cmdline),
                             $descriptorspec,
                             $pipes,
                             $dir);

        if (!is_resource($process)) {
            throw new Exception("could not create process");
        }

        if (!empty($stdin)) {
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $ret = proc_close($process);
        if ($ret != 0) {
            throw new Exception(
                "call ended with a non-zero error code (complete cmdline was: ".
                implode(" ", $cmdline).")"
            );
        }

        return $stdout;
    }

    public function __construct()
    {
        parent::__construct("Test the monotone class.");

        $this->tmpdir = sys_get_temp_dir() . "/mtn-test";
        $this->dbfile = "{$this->tmpdir}/test.mtn";

        set_include_path(get_include_path() . ":../../../pluf-master/src");
        require_once("Pluf.php");

        Pluf::start(dirname(__FILE__)."/../conf/idf.php");

        // Pluf::f() mocking
        $GLOBALS['_PX_config']['mtn_repositories'] = "{$this->tmpdir}/%s.mtn";
    }

    private static function deleteRecursive($dirname)
    {
        if (is_dir($dirname))
            $dir_handle=opendir($dirname);

        while ($file = readdir($dir_handle)) {
            if ($file!="." && $file!="..") {
                if (!is_dir($dirname."/".$file)) {
                    unlink ($dirname."/".$file);
                    continue;
                }
                self::deleteRecursive($dirname."/".$file);
            }
        }

        closedir($dir_handle);
        rmdir($dirname);

        return true;
    }

    public function setUp()
    {
        if (is_dir($this->tmpdir)) {
            self::deleteRecursive($this->tmpdir);
        }

        mkdir($this->tmpdir);

        $this->mtnCall(array("db", "init"));

        $this->mtnCall(array("genkey", "test@test.de"), "\n\n");

        $workspaceRoot = "{$this->tmpdir}/test-workspace";
        mkdir($workspaceRoot);

        $this->mtnCall(array("setup", "-b", "testbranch", "."), null, $workspaceRoot);

        file_put_contents("$workspaceRoot/foo", "blubber");
        $this->mtnCall(array("add", "foo"), null, $workspaceRoot);

        $this->mtnCall(array("commit", "-m", "initial"), null, $workspaceRoot);

        file_put_contents("$workspaceRoot/bar", "blafoo");
        mkdir("$workspaceRoot/subdir");
        file_put_contents("$workspaceRoot/subdir/bla", "blabla");
        $this->mtnCall(array("add", "-R", "--unknown"), null, $workspaceRoot);

        $this->mtnCall(array("commit", "-m", "second"), null, $workspaceRoot);

        $rev = $this->mtnCall(array("au", "get_base_revision_id"), null, $workspaceRoot);
        $this->mtnCall(array("tag", rtrim($rev), "release-1.0"));

        $project = new IDF_Project();
        $project->shortname = "test";
        $this->mtnInstance = new IDF_Scm_Monotone($project);
    }

    public function testIsAvailable()
    {
        $this->assertTrue($this->mtnInstance->isAvailable());
    }

    public function testGetBranches()
    {
        $branches = $this->mtnInstance->getBranches();
        $this->assertEqual(1, count($branches));
        list($key, $value) = each($branches);
        $this->assertEqual("h:testbranch", $key);
        $this->assertEqual("testbranch", $value);
    }

    public function testGetTags()
    {
        $tags = $this->mtnInstance->getTags();
        $this->assertEqual(1, count($tags));
        list($key, $value) = each($tags);
        $this->assertEqual("t:release-1.0", $key);
        $this->assertEqual("release-1.0", $value);
    }

    public function testInBranches()
    {
        $revOut = $this->mtnCall(array("au", "select", "b:testbranch"));
        $revs = preg_split('/\n/', $revOut, -1, PREG_SPLIT_NO_EMPTY);

        $branches = $this->mtnInstance->inBranches($revs[0], null);
        $this->assertEqual(1, count($branches));
        $this->assertEqual("h:testbranch", $branches[0]);

        $branches = $this->mtnInstance->inBranches("t:release-1.0", null);
        $this->assertEqual(1, count($branches));
        $this->assertEqual("h:testbranch", $branches[0]);
    }

    public function testInTags()
    {
        $rev = $this->mtnCall(array("au", "select", "t:release-1.0"));
        $tags = $this->mtnInstance->inTags(rtrim($rev), null);
        $this->assertEqual(1, count($tags));
        $this->assertEqual("t:release-1.0", $tags[0]);

        // pick the first (root) revisions in this database
        $rev = $this->mtnCall(array("au", "roots"));
        $tags = $this->mtnInstance->inTags(rtrim($rev), null);
        $this->assertEqual(0, count($tags));
    }

    public function testGetTree()
    {
        $files = $this->mtnInstance->getTree("t:release-1.0");
        $this->assertEqual(3, count($files));

        $this->assertEqual("bar", $files[0]->file);
        $this->assertEqual("blob", $files[0]->type);
        $this->assertEqual(6, $files[0]->size); // "blafoo"
        $this->assertEqual("second\n", $files[0]->log);

        $this->assertEqual("foo", $files[1]->file);
        $this->assertEqual("blob", $files[1]->type);
        $this->assertEqual(7, $files[1]->size); // "blubber"
        $this->assertEqual("initial\n", $files[1]->log);

        $this->assertEqual("subdir", $files[2]->file);
        $this->assertEqual("tree", $files[2]->type);
        $this->assertEqual(0, $files[2]->size);

        $files = $this->mtnInstance->getTree("t:release-1.0", "subdir");
        $this->assertEqual(1, count($files));

        $this->assertEqual("bla", $files[0]->file);
        $this->assertEqual("subdir/bla", $files[0]->fullpath);
        $this->assertEqual("blob", $files[0]->type);
        $this->assertEqual(6, $files[0]->size); // "blabla"
        $this->assertEqual("second\n", $files[0]->log);
    }

    public function testvalidateRevision()
    {
        $this->assertEquals(IDF_Scm::REVISION_VALID, $this->mtnInstance->validateRevision("t:release-1.0"));
        $this->assertEquals(IDF_Scm::REVISION_INVALID, $this->mtnInstance->validateRevision("abcdef12345"));
    }
}
