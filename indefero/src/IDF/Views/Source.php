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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
Pluf::loadFunction('Pluf_Shortcuts_RenderToResponse');
Pluf::loadFunction('Pluf_Shortcuts_GetObjectOr404');
Pluf::loadFunction('Pluf_Shortcuts_GetFormForModel');

/**
 * View SCM repository.
 */
class IDF_Views_Source
{
    /**
     * Display help on how to checkout etc.
     */
    public $help_precond = array('IDF_Precondition::accessSource');
    public function help($request, $match)
    {
        $title = sprintf(__('%s Source Help'), (string) $request->project);
        $scm = IDF_Scm::get($request->project);
        $scmConf = $request->conf->getVal('scm', 'git');
        $params = array(
                        'page_title' => $title,
                        'title' => $title,
                        'scm' => $scmConf,
                        );
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/help.html',
                                               $params, $request);
    }

    /**
     * Is displayed in case an invalid revision is requested
     */
    public $invalidRevision_precond = array('IDF_Precondition::accessSource');
    public function invalidRevision($request, $match)
    {
        $title = sprintf(__('%s Invalid Revision'), (string) $request->project);
        $scm = IDF_Scm::get($request->project);
        $branches = $scm->getBranches();

        $commit = $match[2];
        $params = array(
                        'page_title' => $title,
                        'title' => $title,
                        'commit' => $commit,
                        'branches' => $branches,
                        );
        $scmConf = $request->conf->getVal('scm', 'git');
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/invalid_revision.html',
                                               $params, $request);
    }

    /**
     * Is displayed in case a revision identifier cannot be uniquely resolved
     * to one single revision
     */
    public $disambiguateRevision_precond = array('IDF_Precondition::accessSource',
                                                 'IDF_Views_Source_Precondition::scmAvailable');
    public function disambiguateRevision($request, $match)
    {
        $title = sprintf(__('%s Ambiguous Revision'), (string) $request->project);
        $commit = $match[2];
        $redirect = $match[3];
        $scm = IDF_Scm::get($request->project);
        $revisions = $scm->disambiguateRevision($commit);
        $params = array(
                        'page_title' => $title,
                        'title' => $title,
                        'commit' => $commit,
                        'revisions' => $revisions,
                        'redirect' => $redirect,
                        );
        return Pluf_Shortcuts_RenderToResponse('idf/source/disambiguate_revision.html',
                                               $params, $request);
    }

    public $changeLog_precond = array('IDF_Precondition::accessSource',
                                      'IDF_Views_Source_Precondition::scmAvailable',
                                      'IDF_Views_Source_Precondition::revisionValid');
    public function changeLog($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $branches = $scm->getBranches();
        $commit = $match[2];

        $title = sprintf(__('%1$s %2$s Change Log'), (string) $request->project,
                         $this->getScmType($request));
        $changes = $scm->getChangeLog($commit, 25);
        $rchanges = array();
        // Sync with the database
        foreach ($changes as $change) {
            $rchanges[] = IDF_Commit::getOrAdd($change, $request->project);
        }
        $rchanges = new Pluf_Template_ContextVars($rchanges);
        $scmConf = $request->conf->getVal('scm', 'git');
        $in_branches = $scm->inBranches($commit, '');
        $tags = $scm->getTags();
        $in_tags = $scm->inTags($commit, '');
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/changelog.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'changes' => $rchanges,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     'tree_in' => $in_branches,
                                                     'tags' => $tags,
                                                     'tags_in' => $in_tags,
                                                     'scm' => $scmConf,
                                                     ),
                                               $request);
    }

    public function repository($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        return $scm->repository($request, $match);
    }

    public $treeBase_precond = array('IDF_Precondition::accessSource',
                                     'IDF_Views_Source_Precondition::scmAvailable',
                                     'IDF_Views_Source_Precondition::revisionValid');
    public function treeBase($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];

        $cobject = $scm->getCommit($commit);
        if (!$cobject) {
            throw new Exception('could not retrieve commit object for '. $commit);
        }
        $title = sprintf(__('%1$s %2$s Source Tree'),
                         $request->project, $this->getScmType($request));
        $branches = $scm->getBranches();
        $in_branches = $scm->inBranches($commit, '');
        $tags = $scm->getTags();
        $in_tags = $scm->inTags($commit, '');
        $cache = Pluf_Cache::factory();
        $key = sprintf('Project:%s::IDF_Views_Source::treeBase:%s::',
                       $request->project->id, $commit);
        if (null === ($res=$cache->get($key))) {
            $res = new Pluf_Template_ContextVars($scm->getTree($commit));
            $cache->set($key, $res);
        }
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = $scm->getProperties($commit);
        $res->uasort(array('IDF_Views_Source', 'treeSort'));
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/tree.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'files' => $res,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'tree_in' => $in_branches,
                                                     'branches' => $branches,
                                                     'tags' => $tags,
                                                     'tags_in' => $in_tags,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    public $tree_precond = array('IDF_Precondition::accessSource',
                                 'IDF_Views_Source_Precondition::scmAvailable',
                                 'IDF_Views_Source_Precondition::revisionValid');
    public function tree($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];

        $request_file = $match[3];
        if (substr($request_file, -1) == '/') {
            $request_file = substr($request_file, 0, -1);
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree',
                                            array($match[1], $match[2],
                                                  $request_file));
            return new Pluf_HTTP_Response_Redirect($url, 301);
        }

        $request_file_info = $scm->getPathInfo($request_file, $commit);
        if (!$request_file_info) {
            // Redirect to the main branch
            $fburl = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                      array($request->project->shortname,
                                            $scm->getMainBranch()));
            return new Pluf_HTTP_Response_Redirect($fburl);
        }
        $branches = $scm->getBranches();
        $tags = $scm->getTags();
        if ($request_file_info->type != 'tree') {
            $info = self::getRequestedFileMimeType($request_file_info,
                                                   $commit, $scm);
            if (!IDF_FileUtil::isText($info)) {
                $rep = new Pluf_HTTP_Response($scm->getFile($request_file_info),
                                              $info[0]);
                $rep->headers['Content-Disposition'] = 'attachment; filename="'.$info[1].'"';
                return $rep;
            } else {
                // We want to display the content of the file as text
                $extra = array('branches' => $branches,
                               'tags' => $tags,
                               'commit' => $commit,
                               'request_file' => $request_file,
                               'request_file_info' => $request_file_info,
                               'mime' => $info,
                               );
                return $this->viewFile($request, $match, $extra);
            }
        }

        $bc = self::makeBreadCrumb($request->project, $commit, $request_file_info->fullpath);
        $title = sprintf(__('%1$s %2$s Source Tree'),
                         $request->project, $this->getScmType($request));

        $page_title = $bc.' - '.$title;
        $cobject = $scm->getCommit($commit);
        if (!$cobject) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $scm->getMainBranch()));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $in_branches = $scm->inBranches($commit, $request_file);
        $in_tags = $scm->inTags($commit, $request_file);
        $cache = Pluf_Cache::factory();
        $key = sprintf('Project:%s::IDF_Views_Source::tree:%s::%s',
                       $request->project->id, $commit, $request_file);
        if (null === ($res=$cache->get($key))) {
            $res = new Pluf_Template_ContextVars($scm->getTree($commit, $request_file));
            $cache->set($key, $res);
        }
        // try to find the previous level if it exists.
        $prev = explode('/', $request_file);
        $l = array_pop($prev);
        $previous = substr($request_file, 0, -strlen($l.' '));
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = $scm->getProperties($commit, $request_file);
        $res->uasort(array('IDF_Views_Source', 'treeSort'));
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/tree.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'breadcrumb' => $bc,
                                                     'files' => $res,
                                                     'commit' => $commit,
                                                     'cobject' => $cobject,
                                                     'base' => $request_file_info->file,
                                                     'prev' => $previous,
                                                     'tree_in' => $in_branches,
                                                     'branches' => $branches,
                                                     'tags' => $tags,
                                                     'tags_in' => $in_tags,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    public static function makeBreadCrumb($project, $commit, $file, $sep='/')
    {
        $elts = explode('/', $file);
        $out = array();
        $stack = '';
        $i = 0;
        foreach ($elts as $elt) {
            $stack .= ($i==0) ? rawurlencode($elt) : '/'.rawurlencode($elt);
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree',
                                            array($project->shortname,
                                                  $commit, $stack));
            $out[] = '<a href="'.$url.'">'.Pluf_esc($elt).'</a>';
            $i++;
        }
        return '<span class="breadcrumb">'.implode('<span class="sep">'.$sep.'</span>', $out).'</span>';
    }

    public $commit_precond = array('IDF_Precondition::accessSource',
                                   'IDF_Views_Source_Precondition::scmAvailable',
                                   'IDF_Views_Source_Precondition::revisionValid');
    public function commit($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];
        $large = $scm->isCommitLarge($commit);
        $cobject = $scm->getCommit($commit, !$large);
        if (!$cobject) {
            throw new Exception('could not retrieve commit object for '. $commit);
        }
        $title = sprintf(__('%s Commit Details'), (string) $request->project);
        $page_title = sprintf(__('%1$s Commit Details - %2$s'), (string) $request->project, $commit);
        $rcommit = IDF_Commit::getOrAdd($cobject, $request->project);
        $diff = new IDF_Diff($cobject->diff, $scm->getDiffPathStripLevel());
        $cobject->diff = null;
        $diff->parse();
        $scmConf = $request->conf->getVal('scm', 'git');
        $changes = $scm->getChanges($commit);
        $branches = $scm->getBranches();
        $in_branches = $scm->inBranches($cobject->commit, '');
        $tags = $scm->getTags();
        $in_tags = $scm->inTags($cobject->commit, '');
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/commit.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'diff' => $diff,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'changes' => $changes,
                                                     'branches' => $branches,
                                                     'tree_in' => $in_branches,
                                                     'tags' => $tags,
                                                     'tags_in' => $in_tags,
                                                     'scm' => $scmConf,
                                                     'rcommit' => $rcommit,
                                                     'large_commit' => $large,
                                                     ),
                                               $request);
    }

    public $downloadDiff_precond = array('IDF_Precondition::accessSource',
                                         'IDF_Views_Source_Precondition::scmAvailable',
                                         'IDF_Views_Source_Precondition::revisionValid');
    public function downloadDiff($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];
        $cobject = $scm->getCommit($commit, true);
        if (!$cobject) {
            throw new Exception('could not retrieve commit object for '. $commit);
        }
        $rep = new Pluf_HTTP_Response($cobject->diff, 'text/plain');
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$commit.'.diff"';
        return $rep;
    }

    /**
     * Should only be called through self::tree
     */
    public function viewFile($request, $match, $extra)
    {
        $title = sprintf(__('%1$s %2$s Source Tree'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request->project);
        $branches = $extra['branches'];
        $tags = $extra['tags'];
        $commit = $extra['commit'];
        $request_file = $extra['request_file'];
        $request_file_info = $extra['request_file_info'];
        $bc = self::makeBreadCrumb($request->project, $commit, $request_file_info->fullpath);
        $page_title = $bc.' - '.$title;
        $cobject = $scm->getCommit($commit);
        $in_branches = $scm->inBranches($commit, $request_file);
        $in_tags = $scm->inTags($commit, '');
        // try to find the previous level if it exists.
        $prev = explode('/', $request_file);
        $l = array_pop($prev);
        $previous = substr($request_file, 0, -strlen($l.' '));
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = $scm->getProperties($commit, $request_file);
        $content = IDF_FileUtil::highLight($extra['mime'], $scm->getFile($request_file_info));
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/file.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'breadcrumb' => $bc,
                                                     'file' => $content,
                                                     'commit' => $commit,
                                                     'cobject' => $cobject,
                                                     'fullpath' => $request_file,
                                                     'efullpath' => IDF_Scm::smartEncode($request_file),
                                                     'base' => $request_file_info->file,
                                                     'prev' => $previous,
                                                     'tree_in' => $in_branches,
                                                     'branches' => $branches,
                                                     'tags' => $tags,
                                                     'tags_in' => $in_tags,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    /**
     * Get a given file at a given commit.
     *
     */
    public $getFile_precond = array('IDF_Precondition::accessSource',
                                    'IDF_Views_Source_Precondition::scmAvailable',
                                    'IDF_Views_Source_Precondition::revisionValid');
    public function getFile($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];
        $request_file = $match[3];
        $request_file_info = $scm->getPathInfo($request_file, $commit);
        if (!$request_file_info or $request_file_info->type == 'tree') {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $scm->getMainBranch()));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $info = self::getRequestedFileMimeType($request_file_info,
                                                   $commit, $scm);
        $rep = new Pluf_HTTP_Response($scm->getFile($request_file_info),
                                      $info[0]);
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$info[1].'"';
        return $rep;
    }

    /**
     * Get a zip archive of the current commit.
     *
     */
    public $download_precond = array('IDF_Precondition::accessSource',
                                     'IDF_Views_Source_Precondition::scmAvailable',
                                     'IDF_Views_Source_Precondition::revisionValid');
    public function download($request, $match)
    {
        $commit = trim($match[2]);
        $scm = IDF_Scm::get($request->project);
        $base = $request->project->shortname.'-'.$commit;
        $rep = $scm->getArchiveStream($commit, $base.'/');
        $rep->headers['Content-Transfer-Encoding'] = 'binary';
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$base.'.zip"';
        return $rep;
    }

    /**
     * Find the mime type of a requested file.
     *
     * @param stdClass Request file info
     * @param string Commit at which we want the file
     * @param IDF_Scm SCM object
     * @param array  Mime type found or 'application/octet-stream', basename, extension
     */
    public static function getRequestedFileMimeType($file_info, $commit, $scm)
    {
        $mime = IDF_FileUtil::getMimeType($file_info->file);
        if ('application/octet-stream' != $mime[0]) {
            return $mime;
        }
        return IDF_FileUtil::getMimeTypeFromContent($file_info->file,
                                                    $scm->getFile($file_info));
    }

    /**
     * Callback function to sort tree entries
     */
    public static function treeSort($a, $b)
    {
        // compare two nodes of the same type
        if ($a->type === $b->type) {
            if (mb_convert_case($a->file, MB_CASE_LOWER) <
                mb_convert_case ($b->file, MB_CASE_LOWER)) {
                return -1;
            }
            return 1;
        }

        // compare two nodes of different types, directories ("tree")
        // should come before files ("blob")
        if ($a->type > $b->type) {
            return -1;
        }
        return 1;
    }

    /**
     * Get the scm type for page title
     *
     * @return String
     */
    private function getScmType($request)
    {
        return mb_convert_case($request->conf->getVal('scm', 'git'),
                               MB_CASE_TITLE, 'UTF-8');
    }
}

function IDF_Views_Source_PrettySize($size)
{
    return Pluf_Template::markSafe(str_replace(' ', '&nbsp;',
                                               Pluf_Utils::prettySize($size)));
}

function IDF_Views_Source_PrettySizeSimple($size)
{
    return Pluf_Utils::prettySize($size);
}

function IDF_Views_Source_ShortenString($string, $length)
{
    $ellipse = "...";
    $length = max(mb_strlen($ellipse) + 2, $length);
    $preflen = ceil($length / 10);

    if (mb_strlen($string) < $length)
        return $string;

    return mb_substr($string, 0, $preflen).$ellipse.
           mb_substr($string, -($length - $preflen - mb_strlen($ellipse)));
}
