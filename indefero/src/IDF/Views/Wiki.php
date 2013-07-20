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
 * Documentation pages views.
 */
class IDF_Views_Wiki
{
    /**
     * View list of pages for a given project.
     */
    public $listPages_precond = array('IDF_Precondition::accessWiki');
    public function listPages($request, $match, $api=false)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Documentation'), (string) $prj);
        // Paginator to paginate the pages
        $pag = new Pluf_Paginator(new IDF_Wiki_Page());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the documentation pages.');
        $pag->action = array('IDF_Views_Wiki::listPages', array($prj->shortname));
        $pag->edit_action = array('IDF_Views_Wiki::viewPage', 'shortname', 'title');
        $sql = 'project=%s';
        $ptags = self::getWikiTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $ids = self::getDeprecatedPagesIds($prj, $dtag);
        if (count($ids)) {
            $sql .= ' AND id NOT IN ('.implode(',', $ids).')';
        }
        $pag->forced_where = new Pluf_SQL($sql, array($prj->id));
        $pag->extra_classes = array('right', '', 'a-c');
        $list_display = array(
             'title' => __('Page Title'),
             array('summary', 'IDF_Views_Wiki_SummaryAndLabels', __('Summary')),
             array('modif_dtime', 'Pluf_Paginator_DateYMD', __('Updated')),
                              );
        $pag->configure($list_display, array(), array('title', 'modif_dtime'));
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No documentation pages were found.');
        $pag->sort_order = array('title', 'ASC');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/listPages.html',
                                               array(
                                                     'page_title' => $title,
                                                     'pages' => $pag,
                                                     'deprecated' => count($ids),
                                                     'dlabel' => $dtag,
                                                     ),
                                               $request);
    }

    /**
     * View list of resources for a given project.
     */
    public $listResources_precond = array('IDF_Precondition::accessWiki',
                                          'Pluf_Precondition::loginRequired');
    public function listResources($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Documentation Resources'), (string) $prj);
        $pag = new Pluf_Paginator(new IDF_Wiki_Resource());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the resources that can be used on documentation pages.');
        $pag->action = array('IDF_Views_Wiki::listResources', array($prj->shortname));
        $pag->edit_action = array('IDF_Views_Wiki::viewResource', 'shortname', 'title');
        $pag->forced_where = new Pluf_SQL('project=%s', array($prj->id));
        $pag->extra_classes = array('right', 'a-c', 'left', 'a-c');
        $list_display = array(
            'title' => __('Resource Title'),
            'mime_type' => __('MIME type'),
            'summary' => __('Description'),
            array('modif_dtime', 'Pluf_Paginator_DateYMD', __('Updated')),
        );
        $pag->configure($list_display, array(), array('title', 'modif_dtime'));
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No resources were found.');
        $pag->sort_order = array('title', 'ASC');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/listResources.html',
            array(
                'page_title' => $title,
                'resources' => $pag,
            ),
            $request);
    }

    public $search_precond = array('IDF_Precondition::accessWiki',);
    public function search($request, $match)
    {
        $prj = $request->project;
        if (!isset($request->REQUEST['q']) or trim($request->REQUEST['q']) == '') {
            $url =  Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listPages',
                                             array($prj->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $q = $request->REQUEST['q'];
        $title = sprintf(__('Documentation Search - %s'), $q);
        $pages = new Pluf_Search_ResultSet(IDF_Search::mySearch($q, $prj, 'IDF_Wiki_Page'));
        if (count($pages) > 100) {
            $pages->results = array_slice($pages->results, 0, 100);
        }
        $pag = new Pluf_Paginator();
        $pag->items = $pages;
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the pages found.');
        $pag->action = array('IDF_Views_Wiki::search', array($prj->shortname), array('q'=> $q));
        $pag->edit_action = array('IDF_Views_Wiki::viewPage', 'shortname', 'title');
        $pag->extra_classes = array('right', '', 'a-c');
        $list_display = array(
             'title' => __('Page Title'),
             array('summary', 'IDF_Views_Wiki_SummaryAndLabels', __('Summary')),
             array('modif_dtime', 'Pluf_Paginator_DateYMD', __('Updated')),
                              );
        $pag->configure($list_display);
        $pag->items_per_page = 100;
        $pag->no_results_text = __('No pages were found.');
        $pag->setFromRequest($request);
        $params = array('page_title' => $title,
                        'pages' => $pag,
                        'q' => $q,
                        );
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/search.html', $params, $request);

    }

    /**
     * View list of pages with a given label.
     */
    public $listPagesWithLabel_precond = array('IDF_Precondition::accessWiki');
    public function listPagesWithLabel($request, $match)
    {
        $prj = $request->project;
        $tag = Pluf_Shortcuts_GetObjectOr404('IDF_Tag', $match[2]);
        $prj->inOr404($tag);
        $title = sprintf(__('%1$s Documentation Pages with Label %2$s'), (string) $prj,
                         (string) $tag);
        // Paginator to paginate the pages
        $ptags = self::getWikiTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $pag = new Pluf_Paginator(new IDF_Wiki_Page());
        $pag->model_view = 'join_tags';
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname);
        $pag->summary = sprintf(__('This table shows the documentation pages with label %s.'), (string) $tag);
        $pag->forced_where = new Pluf_SQL('project=%s AND idf_tag_id=%s', array($prj->id, $tag->id));
        $pag->action = array('IDF_Views_Wiki::listPagesWithLabel', array($prj->shortname, $tag->id));
        $pag->edit_action = array('IDF_Views_Wiki::viewPage', 'shortname', 'title');
        $pag->extra_classes = array('right', '', 'a-c');
        $list_display = array(
             'title' => __('Page Title'),
             array('summary', 'IDF_Views_Wiki_SummaryAndLabels', __('Summary')),
             array('modif_dtime', 'Pluf_Paginator_DateYMD', __('Updated')),
                              );
        $pag->configure($list_display, array(), array('title', 'modif_dtime'));
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No documentation pages were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/listPages.html',
                                               array(
                                                     'page_title' => $title,
                                                     'label' => $tag,
                                                     'pages' => $pag,
                                                     'dlabel' => $dtag,
                                                     ),
                                               $request);
    }

    /**
     * Create a new documentation page.
     */
    public $createPage_precond = array('IDF_Precondition::accessWiki',
                                       'Pluf_Precondition::loginRequired');
    public function createPage($request, $match)
    {
        $prj = $request->project;
        $title = __('New Page');
        $preview = false;
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiPageCreate($request->POST,
                                            array('project' => $prj,
                                                  'user' => $request->user
                                                  ));
            if ($form->isValid() and !isset($request->POST['preview'])) {
                $page = $form->save();
                $urlpage = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewPage',
                                                    array($prj->shortname, $page->title));
                $request->user->setMessage(sprintf(__('The page <a href="%1$s">%2$s</a> has been created.'),
                                           $urlpage, Pluf_esc($page->title)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listPages',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            } elseif (isset($request->POST['preview'])) {
                $preview = $request->POST['content'];
            }
        } else {
            $pagename = (isset($request->GET['name'])) ?
                $request->GET['name'] : '';
            $form = new IDF_Form_WikiPageCreate(null,
                                            array('name' => $pagename,
                                                  'project' => $prj,
                                                  'user' => $request->user));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/createPage.html',
                                               array(
                                                     'auto_labels' => self::autoCompleteArrays($prj),
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'preview' => $preview,
                                                     ),
                                               $request);
    }

    /**
     * Create a new resource.
     */
    public $createResource_precond = array('IDF_Precondition::accessWiki',
                                           'Pluf_Precondition::loginRequired');
    public function createResource($request, $match)
    {
        $prj = $request->project;
        $title = __('New Resource');
        $preview = false;
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiResourceCreate(array_merge($request->POST, $request->FILES),
                                                    array('project' => $prj, 'user' => $request->user));
            if ($form->isValid()) {
                $resource = $form->save();
                $urlresource = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                                        array($prj->shortname, $resource->title));
                $request->user->setMessage(sprintf(__('The resource <a href="%1$s">%2$s</a> has been created.'), $urlresource, Pluf_esc($resource->title)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listResources',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $resourcename = (isset($request->GET['name'])) ?
                $request->GET['name'] : '';
            $form = new IDF_Form_WikiResourceCreate(null,
                                                    array('name' => $resourcename,
                                                          'project' => $prj, 'user' => $request->user));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/createResource.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * View a documentation page.
     */
    public $viewPage_precond = array('IDF_Precondition::accessWiki');
    public function viewPage($request, $match)
    {
        $prj = $request->project;
        // Find the page
        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($prj->id, $match[2]));
        $pages = Pluf::factory('IDF_Wiki_Page')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() != 1) {
            return new Pluf_HTTP_Response_NotFound($request);
        }
        $page = $pages[0];
        $revision = $page->get_current_revision();

        // We grab the old revision if requested.
        if (isset($request->GET['rev']) and preg_match('/^[0-9]+$/', $request->GET['rev'])) {
            $revision = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_PageRevision',
                                                    $request->GET['rev']);
            if ($revision->wikipage != $page->id) {
                return new Pluf_HTTP_Response_NotFound($request);
            }
        }
        $ptags = self::getWikiTags($prj);
        $dtag = array_pop($ptags); // The last tag is the deprecated tag.
        $tags = $page->get_tags_list();
        $dep = Pluf_Model_InArray($dtag, $tags);
        $title = $page->title;
        $false = Pluf_DB_BooleanToDb(false, $page->getDbConnection());
        $revs = $page->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/viewPage.html',
                                               array(
                                                     'page_title' => $title,
                                                     'page' => $page,
                                                     'rev' => $revision,
                                                     'revs' => $revs,
                                                     'tags' => $tags,
                                                     'deprecated' => $dep,
                                                     ),
                                               $request);
    }

    /**
     * View a documentation resource.
     */
    public $viewResource_precond = array('IDF_Precondition::accessWiki');
    public function viewResource($request, $match)
    {
        $prj = $request->project;
        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($prj->id, $match[2]));
        $resources = Pluf::factory('IDF_Wiki_Resource')->getList(array('filter'=>$sql->gen()));
        if ($resources->count() != 1) {
            return new Pluf_HTTP_Response_NotFound($request);
        }
        $resource = $resources[0];
        $revision = $resource->get_current_revision();

        // grab the old revision if requested.
        if (isset($request->GET['rev']) and preg_match('/^[0-9]+$/', $request->GET['rev'])) {
            $revision = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_ResourceRevision',
                                                      $request->GET['rev']);
            if ($revision->wikiresource != $resource->id) {
                return new Pluf_HTTP_Response_NotFound($request);
            }
        }
        $pagerevs = $revision->getPageRevisions();
        $title = $resource->title;
        $false = Pluf_DB_BooleanToDb(false, $resource->getDbConnection());
        $revs = $resource->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                    'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/viewResource.html',
            array(
                'page_title' => $title,
                'resource' => $resource,
                'rev' => $revision,
                'revs' => $revs,
                'pagerevs' => $pagerevs,
            ),
            $request);
    }


    /**
     * Returns a bytestream to the given raw resource revision
     */
    public $rawResource_precond = array('IDF_Precondition::accessWiki');
    public function rawResource($request, $match)
    {
        $prj = $request->project;
        $rev = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_ResourceRevision',
                                             $match[2]);
        $res = $rev->get_wikiresource();
        if ($res->get_project()->id != $prj->id) {
            return new Pluf_HTTP_Response_NotFound($request);
        }

        $response = new Pluf_HTTP_Response_File($rev->getFilePath(), $res->mime_type);
        if (isset($request->GET['attachment']) && $request->GET['attachment']) {
            $response->headers['Content-Disposition'] =
                'attachment; filename="'.$res->title.'.'.$rev->fileext.'"';
        }
        return $response;
    }

    /**
     * Remove a revision of a page.
     */
    public $deletePageRev_precond = array('IDF_Precondition::accessWiki',
                                          'IDF_Precondition::projectMemberOrOwner');
    public function deletePageRev($request, $match)
    {
        $prj = $request->project;
        $oldrev = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_PageRevision', $match[2]);
        $page = $oldrev->get_wikipage();
        $prj->inOr404($page);
        if ($oldrev->is_head == true) {
            return new Pluf_HTTP_Response_NotFound($request);
        }
        if ($request->method == 'POST') {
            $oldrev->delete();
            $request->user->setMessage(__('The old revision has been deleted.'));
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewPage',
                                            array($prj->shortname, $page->title));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $title = sprintf(__('Delete Old Revision of %s'), $page->title);
        $revision = $page->get_current_revision();
        $false = Pluf_DB_BooleanToDb(false, $page->getDbConnection());
        $revs = $page->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/deletePageRev.html',
                                               array(
                                                     'page_title' => $title,
                                                     'page' => $page,
                                                     'oldrev' => $oldrev,
                                                     'rev' => $revision,
                                                     'revs' => $revs,
                                                     'tags' => $page->get_tags_list(),
                                                     ),
                                               $request);
    }

    /**
     * Remove a revision of a resource.
     */
    public $deleteResourceRev_precond = array('IDF_Precondition::accessWiki',
                                              'IDF_Precondition::projectMemberOrOwner');
    public function deleteResourceRev($request, $match)
    {
        $prj = $request->project;
        $oldrev = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_ResourceRevision', $match[2]);
        $resource = $oldrev->get_wikiresource();
        $prj->inOr404($resource);
        if ($oldrev->is_head == true) {
            return new Pluf_HTTP_Response_NotFound($request);
        }
        if ($request->method == 'POST') {
            $oldrev->delete();
            $request->user->setMessage(__('The old revision has been deleted.'));
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                            array($prj->shortname, $resource->title));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $title = sprintf(__('Delete Old Revision of %s'), $resource->title);
        $false = Pluf_DB_BooleanToDb(false, $resource->getDbConnection());
        $revs = $resource->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                    'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/deleteResourceRev.html',
                                               array(
                                                     'page_title' => $title,
                                                     'resource' => $resource,
                                                     'oldrev' => $oldrev,
                                                     'revs' => $revs,
                                                     ),
                                               $request);
    }

    /**
     * Update a documentation page.
     */
    public $updatePage_precond = array('IDF_Precondition::accessWiki',
                                       'Pluf_Precondition::loginRequired');
    public function updatePage($request, $match)
    {
        $prj = $request->project;
        // Find the page
        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($prj->id, $match[2]));
        $pages = Pluf::factory('IDF_Wiki_Page')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() != 1) {
            return new Pluf_HTTP_Response_NotFound($request);
        }
        $page = $pages[0];
        $title = sprintf(__('Update %s'), $page->title);
        $revision = $page->get_current_revision();
        $preview = false;
        $params = array('project' => $prj,
                        'user' => $request->user,
                        'page' => $page);
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiPageUpdate($request->POST, $params);
            if ($form->isValid() and !isset($request->POST['preview'])) {
                $page = $form->save();
                $urlpage = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewPage',
                                                    array($prj->shortname, $page->title));
                $request->user->setMessage(sprintf(__('The page <a href="%1$s">%2$s</a> has been updated.'),
                                           $urlpage, Pluf_esc($page->title)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listPages',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            } elseif (isset($request->POST['preview'])) {
                $preview = $request->POST['content'];
            }
        } else {

            $form = new IDF_Form_WikiPageUpdate(null, $params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/updatePage.html',
                                               array(
                                                     'auto_labels' => self::autoCompleteArrays($prj),
                                                     'page_title' => $title,
                                                     'page' => $page,
                                                     'rev' => $revision,
                                                     'form' => $form,
                                                     'preview' => $preview,
                                                     ),
                                               $request);
    }

    /**
     * Update a documentation resource.
     */
    public $updateResource_precond = array('IDF_Precondition::accessWiki',
                                           'Pluf_Precondition::loginRequired');
    public function updateResource($request, $match)
    {
        $prj = $request->project;
        // Find the page
        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($prj->id, $match[2]));
        $resources = Pluf::factory('IDF_Wiki_Resource')->getList(array('filter'=>$sql->gen()));
        if ($resources->count() != 1) {
            return new Pluf_HTTP_Response_NotFound($request);
        }
        $resource = $resources[0];
        $title = sprintf(__('Update %s'), $resource->title);
        $revision = $resource->get_current_revision();
        $params = array('project' => $prj,
                        'user' => $request->user,
                        'resource' => $resource);
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiResourceUpdate(array_merge($request->POST, $request->FILES),
                                                    $params);
            if ($form->isValid()) {
                $page = $form->save();
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                                array($prj->shortname, $resource->title));
                $request->user->setMessage(sprintf(__('The resource <a href="%1$s">%2$s</a> has been updated.'),
                                                   $url, Pluf_esc($resource->title)));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listResources',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_WikiResourceUpdate(null, $params);
        }

        return Pluf_Shortcuts_RenderToResponse('idf/wiki/updateResource.html',
                                               array(
                                                     'page_title' => $title,
                                                     'resource' => $resource,
                                                     'rev' => $revision,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Delete a Wiki page.
     */
    public $deletePage_precond = array('IDF_Precondition::accessWiki',
                                       'IDF_Precondition::projectMemberOrOwner');
    public function deletePage($request, $match)
    {
        $prj = $request->project;
        $page = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_Page', $match[2]);
        $prj->inOr404($page);
        $params = array('page' => $page);
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiPageDelete($request->POST, $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The documentation page has been deleted.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listPages',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_WikiPageDelete(null, $params);
        }
        $title = sprintf(__('Delete Page %s'), $page->title);
        $revision = $page->get_current_revision();
        $false = Pluf_DB_BooleanToDb(false, $page->getDbConnection());
        $revs = $page->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/deletePage.html',
                                               array(
                                                     'page_title' => $title,
                                                     'page' => $page,
                                                     'form' => $form,
                                                     'rev' => $revision,
                                                     'revs' => $revs,
                                                     'tags' => $page->get_tags_list(),
                                                     ),
                                               $request);
    }

    /**
     * Delete a Wiki resource.
     */
    public $deleteResource_precond = array('IDF_Precondition::accessWiki',
                                           'IDF_Precondition::projectMemberOrOwner');
    public function deleteResource($request, $match)
    {
        $prj = $request->project;
        $resource = Pluf_Shortcuts_GetObjectOr404('IDF_Wiki_Resource', $match[2]);
        $prj->inOr404($resource);
        $params = array('resource' => $resource);
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiResourceDelete($request->POST, $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The documentation resource has been deleted.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::listResources',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_WikiResourceDelete(null, $params);
        }
        $title = sprintf(__('Delete Resource %s'), $resource->title);
        $revision = $resource->get_current_revision();
        $false = Pluf_DB_BooleanToDb(false, $resource->getDbConnection());
        $revs = $resource->get_revisions_list(array('order' => 'creation_dtime DESC',
                                                    'filter' => 'is_head='.$false));
        return Pluf_Shortcuts_RenderToResponse('idf/wiki/deleteResource.html',
                                               array(
                                                     'page_title' => $title,
                                                     'resource' => $resource,
                                                     'form' => $form,
                                                     'rev' => $revision,
                                                     'revs' => $revs,
                                                     ),
                                               $request);
    }

    /**
     * Get the wiki tags.
     *
     * @param IDF_Project
     * @return ArrayObject The tags
     */
    public static function getWikiTags($project)
    {
        return $project->getTagsFromConfig('labels_wiki_predefined',
                                           IDF_Form_WikiConf::init_predefined);

    }

    /**
     * Get deprecated page ids.
     *
     * @param IDF_Project
     * @param IDF_Tag Deprecated tag (null)
     * @return array Ids of the deprecated pages.
     */
    public static function getDeprecatedPagesIds($project, $dtag=null)
    {
        if (is_null($dtag)) {
            $ptags = self::getWikiTags($project);
            $dtag = array_pop($ptags); // The last tag is the deprecated tag
        }
        $sql = new Pluf_SQL('project=%s AND idf_tag_id=%s', array($project->id,
                                                                  $dtag->id));
        $ids = array();
        foreach (Pluf::factory('IDF_Wiki_Page')->getList(array('filter' => $sql->gen(), 'view' => 'join_tags'))
                 as $file) {
            $ids[] = (int) $file->id;
        }
        return $ids;
    }

    /**
     * Create the autocomplete arrays for the little AJAX stuff.
     */
    public static function autoCompleteArrays($project)
    {
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $st = preg_split("/\015\012|\015|\012/",
                         $conf->getVal('labels_wiki_predefined', IDF_Form_WikiConf::init_predefined), -1, PREG_SPLIT_NO_EMPTY);
        $auto = '';
        foreach ($st as $s) {
            $v = '';
            $d = '';
            $_s = explode('=', $s, 2);
            if (count($_s) > 1) {
                $v = trim($_s[0]);
                $d = trim($_s[1]);
            } else {
                $v = trim($_s[0]);
            }
            $auto .= sprintf('{ name: "%s", to: "%s" }, ',
                             Pluf_esc($d), Pluf_esc($v));
        }
        return substr($auto, 0, -2);
    }
}

/**
 * Display the summary of a page, then on a new line, display the
 * list of labels.
 */
function IDF_Views_Wiki_SummaryAndLabels($field, $page, $extra='')
{
    $tags = array();
    foreach ($page->get_tags_list() as $tag) {
        $tags[] = Pluf_esc((string) $tag);
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="note label">'.implode(', ', $tags).'</span>';
    }
    return Pluf_esc($page->summary).$out;
}
