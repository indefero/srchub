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

Pluf::loadFunction('Pluf_Text_MarkDown_parse');
Pluf::loadFunction('IDF_Template_safePregReplace');

/**
 * Make the links to issues and commits.
 */
class IDF_Template_Markdown extends Pluf_Template_Tag
{
    private $project = null;
    private $request = null;
    private $scm = null;

    function start($text, $request)
    {
        $this->project = $request->project;
        $this->request = $request;
        // Replace like in the issue text
        $tag = new IDF_Template_IssueComment();
        $text = $tag->start($text, $request, false, false, false, false);
        // Replace [[[path/to/file.mdtext, commit]]] with embedding
        // the content of the file into the wki page
        if ($this->request->rights['hasSourceAccess']) {
            $text = IDF_Template_safePregReplace('#\[\[\[([^\,]+)(?:, ([^/]+))?\]\]\]#im',
                                                 array($this, 'callbackEmbeddedDoc'),
                                                 $text);
        }
        // Replace [Page]([[PageName]]) with corresponding link to the page, with link text being Page.
        $text = IDF_Template_safePregReplace('#\[([^\]]+)\]\(\[\[([A-Za-z0-9\-]+)\]\]\)#im',
                                             array($this, 'callbackWikiPage'),
                                             $text);
        // Replace [[PageName]] with corresponding link to the page.
        $text = IDF_Template_safePregReplace('#\[\[([A-Za-z0-9\-]+)\]\]#im',
					     array($this, 'callbackWikiPageNoName'),
					     $text);

        $filter = new IDF_Template_MarkdownPrefilter();
        $text = $filter->go(Pluf_Text_MarkDown_parse($text));

        // Replace [[!ResourceName]] with corresponding HTML for the resource;
        // we need to do that after the HTML filtering as we'd otherwise be unable to use
        // certain HTML elements, such as iframes, that are used to display text content
        // FIXME: no support for escaping yet in place
        echo IDF_Template_safePregReplace('#\[\[!([A-Za-z0-9\-]+)(?:,\s*([^\]]+))?\]\]#im',
                                          array($this, 'callbackWikiResource'),
                                          $text);
    }

    function callbackWikiPageNoName($m)
    {
        $m[2] = $m[1]; //Set the link text to be the same as the page name.
        return $this->callbackWikiPage($m);
    }

    function callbackWikiPage($m)
    {
        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($this->project->id, $m[2]));
        $pages = Pluf::factory('IDF_Wiki_Page')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() != 1 and $this->request->rights['hasWikiAccess']
            and !$this->request->user->isAnonymous()) {
            return '<img style="vertical-align: text-bottom;" alt=" " src="'.Pluf::f('url_media').'/idf/img/add.png" /><a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::createPage', array($this->project->shortname), array('name'=>$m[2])).'" title="'.__('Create this documentation page').'">'.$m[1].'</a>';
        }
        if (!$this->request->rights['hasWikiAccess'] or $pages->count() == 0) {
            return $m[1];
        }
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewPage', array($this->project->shortname, $pages[0]->title)).'" title="'.Pluf_esc($pages[0]->summary).'">'.$m[1].'</a>';
    }

    function callbackWikiResource($m)
    {
        @list($match, $resourceName, $opts) = $m;

        if (!$this->request->rights['hasWikiAccess']) {
            return '<span title="'.__('You are not allowed to access the wiki.').'">'.$match.'</span>';
        }

        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($this->project->id, $resourceName));
        $resources = Pluf::factory('IDF_Wiki_Resource')->getList(array('filter'=>$sql->gen()));

        if ($resources->count() == 0) {
            if ($this->request->user->isAnonymous()) {
                return '<span title="'.__('The wiki resource has not been found.').'">'.$match.'</span>';
            }

            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::createResource',
                                            array($this->project->shortname),
                                            array('name' => $resourceName));
            return '<img style="vertical-align: text-bottom;" alt=" " src="'.Pluf::f('url_media').'/idf/img/add.png" />'.
                   '<a href="'.$url.'" title="'.__('The wiki resource has not been found. Create it!').'">'.$match.'</a>';
        }

        // by default, render the most recent revision
        $resourceRevision = $resources[0]->get_current_revision();

        list($urlConf, $urlMatches) = $this->request->view;

        // if we currently look at an existing wiki page, look up its name and find the proper resource (if any)
        if ($urlConf['model'] == 'IDF_Views_Wiki' && $urlConf['method'] == 'viewPage') {
            $sql = new Pluf_SQL('project=%s AND title=%s',
                                array($this->project->id, $urlMatches[2]));
            $pages = Pluf::factory('IDF_Wiki_Page')->getList(array('filter'=>$sql->gen()));
            if ($pages->count() == 0) throw new Exception('page not found');
            $pageRevision = $pages[0]->get_current_revision();

            // if we look at an old version of the page, figure out the resource version back then
            if (isset($this->request->GET['rev']) and preg_match('/^[0-9]+$/', $this->request->GET['rev'])) {
                $pageRevision = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_PageRevision',
                                                              $this->request->GET['rev']);
                // this is actually an invariant since we came so far looking at
                // and rendering the old revision already
                if ($pageRevision == null) {
                    throw new Exception('page revision with id '.$this->request->GET['rev'].' not found');
                }
            }

            $sql = new Pluf_SQL('wikiresource=%s AND idf_wiki_pagerevision_id=%s',
                                array($resources[0]->id, $pageRevision->id));
            $resourceRevision = Pluf::factory('IDF_Wiki_ResourceRevision')->getOne(
                array('filter' => $sql->gen(), 'view' => 'join_pagerevision'));

            if ($resourceRevision == null) {
                return '<span title="'.__('This revision of the resource is no longer available.').'">'.$match.'</span>';
            }
        }

        $validOpts = array(
            'align' => '/^(left|right|center)$/',
            'width' => '/^\d+(%|px|em)?$/',
            'height' => '/^\d+(%|px|em)?$/',
            'preview' => '/^yes|no$/',
            'title' => '/.+/',
        );

        $parsedOpts = array();
        // FIXME: no support for escaping yet in place
        $opts = preg_split('/\s*,\s*/', $opts, -1, PREG_SPLIT_NO_EMPTY);
        foreach ((array)@$opts as $opt)
        {
            list($key, $value) = preg_split('/\s*=\s*/', $opt, 2);
            if (!array_key_exists($key, $validOpts)) {
                continue;
            }
            if (!preg_match($validOpts[$key], $value)) {
                continue;
            }
            $parsedOpts[$key] = $value;
        }

        return $resourceRevision->render($parsedOpts);
    }

    function callbackEmbeddedDoc($m)
    {
        $scm = IDF_Scm::get($this->request->project);
        if (!$scm->isAvailable()) {
            return $m[0];
        }
        $view_source = new IDF_Views_Source();
        $match = array('dummy', $this->request->project->shortname);
        $match[] = (isset($m[2])) ? $m[2] : $scm->getMainBranch();
        $match[] = $m[1];
        $res = $view_source->getFile($this->request, $match);
        if ($res->status_code != 200) {
            return $m[0];
        }
        $info = pathinfo($m[1]);
        $fileinfo = array($res->headers['Content-Type'], $m[1],
                          isset($info['extension']) ? $info['extension'] : 'bin');
        if (!IDF_FileUtil::isText($fileinfo)) {
            return $m[0];
        }
        return $res->content;
    }
}

