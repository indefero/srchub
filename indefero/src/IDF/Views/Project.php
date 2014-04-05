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
 * Project's views.
 */
class IDF_Views_Project
{
    /**
     * Home page of a project.
     */
    public $logo_precond = array('IDF_Precondition::baseAccess');
    public function logo($request, $match)
    {
        $prj = $request->project;

        $logo = $prj->getConf()->getVal('logo');
        if (empty($logo)) {
            $url = Pluf::f('url_media') . '/idf/img/no_logo.png';
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $info = IDF_FileUtil::getMimeType($logo);
        return new Pluf_HTTP_Response_File(Pluf::f('upload_path') . '/' . $prj->shortname . $logo,
                                           $info[0]);
    }

    /**
     * Home page of a project.
     */
    public $home_precond = array('IDF_Precondition::baseAccess');
    public function home($request, $match)
    {
        $prj = $request->project;
        $team = $prj->getMembershipData();
        $title = (string) $prj;
        $downloads = array();
        if ($request->rights['hasDownloadsAccess']) {
            $tags = IDF_Views_Download::getDownloadTags($prj);
            // the first tag is the featured, the last is the deprecated.
            $downloads = $tags[0]->get_idf_upload_list();
        }
        $pages = array();
        if ($request->rights['hasWikiAccess']) {
            $tags = IDF_Views_Wiki::getWikiTags($prj);
            $pages = $tags[0]->get_idf_wiki_page_list();
        }
        return Pluf_Shortcuts_RenderToResponse('idf/project/home.html',
                                               array(
                                                     'page_title' => $title,
                                                     'team' => $team,
                                                     'downloads' => $downloads,
                                                     'pages' => $pages,
                                                     ),
                                               $request);
    }

    /**
     * Returns an associative array with all accessible model filters
     *
     * @return array
     */
    private static function getAccessibleModelFilters($request)
    {
        $filters = array('all' => __('All Updates'));

        if (true === IDF_Precondition::accessSource($request))
            $filters['commits'] = __('Commits');
        if (true === IDF_Precondition::accessIssues($request))
            $filters['issues'] = __('Issues and Comments');
        if (true === IDF_Precondition::accessDownloads($request))
            $filters['downloads'] = __('Downloads');
        if (true === IDF_Precondition::accessWiki($request))
            $filters['documents'] = __('Documents');
        if (true === IDF_Precondition::accessReview($request))
            $filters['reviews'] = __('Reviews and Patches');

        return $filters;
    }

    /**
     * Returns an array of model classes for which the current user
     * has rights and which should be used according to his filter
     *
     * @param object $request
     * @param string $model_filter
     * @return array
     */
    private static function determineModelClasses($request, $model_filter = 'all')
    {
        $classes = array();
        if (true === IDF_Precondition::accessSource($request) &&
            ($model_filter == 'all' || $model_filter == 'commits')) {
            $classes[] = '\'IDF_Commit\'';
            // FIXME: this looks like a hack...
            IDF_Scm::syncTimeline($request->project);
        }
        if (true === IDF_Precondition::accessIssues($request) &&
            ($model_filter == 'all' || $model_filter == 'issues')) {
            $classes[] = '\'IDF_Issue\'';
            $classes[] = '\'IDF_IssueComment\'';
        }
        if (true === IDF_Precondition::accessDownloads($request) &&
            ($model_filter == 'all' || $model_filter == 'downloads')) {
            $classes[] = '\'IDF_Upload\'';
        }
        if (true === IDF_Precondition::accessWiki($request) &&
            ($model_filter == 'all' || $model_filter == 'documents')) {
            $classes[] = '\'IDF_Wiki_Page\'';
            $classes[] = '\'IDF_Wiki_PageRevision\'';
            $classes[] = '\'IDF_Wiki_Resource\'';
            $classes[] = '\'IDF_Wiki_ResourceRevision\'';
        }
        if (true === IDF_Precondition::accessReview($request) &&
            ($model_filter == 'all' || $model_filter == 'reviews')) {
            $classes[] = '\'IDF_Review_Comment\'';
            $classes[] = '\'IDF_Review_Patch\'';
        }
        if (count($classes) == 0) {
            $classes[] = '\'IDF_Dummy\'';
        }

        return $classes;
    }

    /**
     * This action serves as URI compatibility layer for v1.0.
     *
     * @deprecated
     */
    public function timelineCompat($request, $match)
    {
        $match[2] = 'all';
        return $this->timeline($request, $match);
    }

    /**
     * Timeline of the project.
     */
    public $timeline_precond = array('IDF_Precondition::baseAccess');
    public function timeline($request, $match)
    {
        $prj = $request->project;

        $model_filter = @$match[2];
        $accessible_model_filters = self::getAccessibleModelFilters($request);
        if (!array_key_exists($model_filter, $accessible_model_filters)) {
            $model_filter = 'all';
        }
        $title = (string)$prj . ' ' . $accessible_model_filters[$model_filter];

        $pag = new IDF_Timeline_Paginator(new IDF_Timeline());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('request' => $request);
        $pag->summary = __('This table shows the project updates.');

        $classes = self::determineModelClasses($request, $model_filter);
        $sql = sprintf('model_class IN (%s)', implode(', ', $classes));
        $pag->forced_where = new Pluf_SQL('project=%s AND '.$sql,
                                          array($prj->id));
        $pag->sort_order = array('creation_dtime', 'ASC');
        $pag->sort_reverse_order = array('creation_dtime');
        $pag->action = array('IDF_Views_Project::timeline', array($prj->shortname, $model_filter));
        $list_display = array(
             'creation_dtime' => __('Age'),
             'id' => __('Change'),
                              );
        $pag->configure($list_display, array(), array('creation_dtime'));
        $pag->items_per_page = 20;
        $pag->no_results_text = __('No changes were found.');
        $pag->setFromRequest($request);

        if (!$request->user->isAnonymous() and $prj->isRestricted()) {
            $feedurl = Pluf_HTTP_URL_urlForView('idf_project_timeline_feed_auth',
                                                array($prj->shortname,
                                                      $model_filter,
                                                      IDF_Precondition::genFeedToken($prj, $request->user)));
        } else {
            $feedurl = Pluf_HTTP_URL_urlForView('idf_project_timeline_feed',
                                                array($prj->shortname, $model_filter));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/project/timeline.html',
                                               array(
                                                     'page_title' => $title,
                                                     'feedurl' => $feedurl,
                                                     'timeline' => $pag,
                                                     'model_filter' => $model_filter,
                                                     'accessible_model_filters' => $accessible_model_filters,
                                                     ),
                                               $request);

    }

    /**
     * This action serves as URI compatibility layer for v1.0.
     *
     * @deprecated
     */
    public function timelineFeedCompat($request, $match)
    {
        $match[2] = 'all';
        return $this->timelineFeed($request, $match);
    }

    /**
     * Timeline feed.
     *
     * A custom view to have a bit more control on the way to handle
     * it and optimize the output.
     *
     */
    public $timelineFeed_precond = array('IDF_Precondition::feedSetUser',
                                         'IDF_Precondition::baseAccess');
    public function timelineFeed($request, $match)
    {
        $prj = $request->project;
        $model_filter = @$match[2];

        $accessible_model_filters = self::getAccessibleModelFilters($request);
        if (!array_key_exists($model_filter, $accessible_model_filters)) {
            $model_filter = 'all';
        }
        $title = $accessible_model_filters[$model_filter];

        $classes = self::determineModelClasses($request, $model_filter);
        $sqls = sprintf('model_class IN (%s)', implode(', ', $classes));
        $sql = new Pluf_SQL('project=%s AND '.$sqls, array($prj->id));
        $params = array(
                        'filter' => $sql->gen(),
                        'order' => 'creation_dtime DESC',
                        'nb' => 20,
                        );
        $items = Pluf::factory('IDF_Timeline')->getList($params);
        $set = new Pluf_Model_Set($items,
                                  array('public_dtime' => 'public_dtime'));
        $out = array();
        foreach ($set as $item) {
            if ($item->id) {
                $out[] = $item->feedFragment($request);
            }
        }
        if ($items->count() > 0) {
            $date = Pluf_Date::gmDateToGmString($items[0]->creation_dtime);
        } else {
            $date = gmdate('c');
        }
        $out = Pluf_Template::markSafe(implode("\n", $out));
        $tmpl = new Pluf_Template('idf/index.atom');
        $feedurl = Pluf::f('url_base').Pluf::f('idf_base').$request->query;
        $viewurl = Pluf_HTTP_URL_urlForView('IDF_Views_Project::timeline',
                                            array($prj->shortname, $model_filter));
        $context = new Pluf_Template_Context_Request($request,
                                                     array('body' => $out,
                                                           'date' => $date,
                                                           'title' => $title,
                                                           'feedurl' => $feedurl,
                                                           'viewurl' => $viewurl));
        return new Pluf_HTTP_Response('<?xml version="1.0" encoding="utf-8"?>'
                                      ."\n".$tmpl->render($context),
                                      'application/atom+xml; charset=utf-8');
    }


    /**
     * Administrate the summary of a project.
     */
    public $admin_precond = array('IDF_Precondition::projectOwner');
    public function admin($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Project Summary'), (string) $prj);
        $extra = array('project' => $prj, 'user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_ProjectConf(array_merge($request->POST,
                                                         $request->FILES),
                                             $extra);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The project has been updated.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::admin',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_ProjectConf(null, $extra);
        }

        $logo = $prj->getConf()->getVal('logo');
        $arrays = self::autoCompleteArrays();
        return Pluf_Shortcuts_RenderToResponse('idf/admin/summary.html',
                                               array_merge(
                                                   array(
                                                         'page_title' => $title,
                                                         'form' => $form,
                                                         'project' => $prj,
                                                         'logo' => $logo,
                                                         ),
                                                   $arrays
                                               ),
                                               $request);
    }

    public $backup_precond = array('IDF_Precondition::projectOwner');
    public function backup($request, $match)
    {
        $prj = $request->project;

        $to_json = array();
        $to_json["IDF_Project"] = Pluf_Test_Fixture::prepare(Pluf::factory("IDF_Project")->getOne(array("filter" => "id=" . $prj->id)));

        $to_json["IDF_Issue"] = array();
        $to_json["IDF_Upload"] = array();
        $to_json["IDF_Wiki_Page"] = array();


        foreach(Pluf::factory("IDF_Issue")->getList(array("filter"=>"project=".$prj->id)) as $item)
        {
            $tmp = array();
            $tmp = Pluf_Test_Fixture::dump($item, false);
            $tmp = $tmp[0];
            $tmp["comments"] = array();
            foreach($item->get_comments_list() as $item2)
                $tmp["comments"][] =  Pluf_Test_Fixture::dump($item2, false)[0];
            $to_json["IDF_Issue"][] = $tmp;
        }
        foreach(Pluf::factory("IDF_Upload")->getList(array("filter"=>"project=".$prj->id)) as $item)
        {
            $path = $item->getFullPath();
            $file = file_get_contents($path);

            $tmp = Pluf_Test_Fixture::dump($item, false);
            $tmp[0]["file_encoded"] = base64_encode($file);
            $to_json["IDF_Upload"][] = $tmp[0];

        }

        foreach(Pluf::factory("IDF_Wiki_Page")->getList(array("filter"=>"project=".$prj->id)) as $item)
        {
            $tmp = Pluf_Test_Fixture::dump($item, false)[0];
            $tmp["WikiPageRevs"] = array();
            foreach($item->get_revisions_list() as $item2)
                $tmp["WikiPageRevs"][] = Pluf_Test_Fixture::dump($item2, false)[0];
            $to_json["IDF_Wiki_Page"][] = $tmp;
        }
        $render = new Pluf_HTTP_Response(json_encode($to_json), "application/json");
        $render->headers['Content-Disposition'] = 'attachment; filename="backup-' . $prj->name . '-' . date("YmdGis") . '.json"';
        return $render;
    }

    /**
     * Administrate the issue tracking of a project.
     */
    public $adminIssues_precond = array('IDF_Precondition::projectOwner');
    public function adminIssues($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Issue Tracking Configuration'), (string) $prj);
        $conf = new IDF_Conf();
        $conf->setProject($prj);
        if ($request->method == 'POST') {
            $form = new IDF_Form_IssueTrackingConf($request->POST);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The issue tracking configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminIssues',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('labels_issue_template',
                          'labels_issue_open', 'labels_issue_closed',
                          'labels_issue_predefined', 'labels_issue_one_max',
                          'issue_relations');
            foreach ($keys as $key) {
                $_val = $conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_IssueTrackingConf($params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/issue-tracking.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the downloads of a project.
     */
    public $adminDownloads_precond = array('IDF_Precondition::projectOwner');
    public function adminDownloads($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Downloads Configuration'), (string) $prj);
        $conf = new IDF_Conf();
        $conf->setProject($prj);
        $extra = array(
            'conf' => $conf,
        );
        if ($request->method == 'POST') {
            $form = new IDF_Form_UploadConf($request->POST, $extra);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The downloads configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminDownloads',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('labels_download_predefined', 'labels_download_one_max', 'upload_webhook_url');
            foreach ($keys as $key) {
                $_val = $conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_UploadConf($params, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/downloads.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'hookkey' => $prj->getWebHookKey(),
                                                     ),
                                               $request);
    }

    /**
     * Administrate the information pages of a project.
     */
    public $adminWiki_precond = array('IDF_Precondition::projectOwner');
    public function adminWiki($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Documentation Configuration'), (string) $prj);
        $conf = new IDF_Conf();
        $conf->setProject($prj);
        if ($request->method == 'POST') {
            $form = new IDF_Form_WikiConf($request->POST);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The documentation configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminWiki',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('labels_wiki_predefined', 'labels_wiki_one_max');
            foreach ($keys as $key) {
                $_val = $conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_WikiConf($params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/wiki.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the members of a project.
     */
    public $adminMembers_precond = array('IDF_Precondition::projectOwner');
    public function adminMembers($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Project Members'), (string) $prj);
        $params = array(
                        'project' => $prj,
                        'user' => $request->user,
                        );
        if ($request->method == 'POST') {
            $form = new IDF_Form_MembersConf($request->POST, $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The project membership has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminMembers',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_MembersConf($prj->getMembershipData('string'), $params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/members.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the access rights to the tabs.
     */
    public $adminTabs_precond = array('IDF_Precondition::projectOwner');
    public function adminTabs($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Tabs Access Rights'), (string) $prj);
        $extra = array(
                       'project' => $prj,
                       'conf' => $request->conf,
                       );
        if ($request->method == 'POST') {
            $form = new IDF_Form_TabsConf($request->POST, $extra);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    if (!in_array($key, array('private_project', 'authorized_users'))) {
                        $request->conf->setVal($key, $val);
                    }
                }
                $form->save(); // Save the authorized users.
                $request->user->setMessage(__('The project tabs access rights and notification settings have been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminTabs',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $sections = array('downloads', 'wiki', 'source', 'issues', 'review');
            $keys = array();

            foreach ($sections as $section) {
                $keys[] = $section.'_access_rights';
                $keys[] = $section.'_notification_owners_enabled';
                $keys[] = $section.'_notification_members_enabled';
                $keys[] = $section.'_notification_email_enabled';
                $keys[] = $section.'_notification_email';
            }

            foreach ($keys as $key) {
                $_val = $request->conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            // Add the authorized users.
            $md = $prj->getMembershipData('string');
            $params['authorized_users'] = $md['authorized'];
            $params['private_project'] = $prj->private;
            $form = new IDF_Form_TabsConf($params, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/tabs.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'from_email' => Pluf::f('from_email'),
                                                     ),
                                               $request);
    }

    /**
     * Administrate the source control.
     *
     * There, the login/password of the subversion remote repo can be
     * change together with the webhook url.
     */
    public $adminSource_precond = array('IDF_Precondition::projectOwner');
    public function adminSource($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Source'), (string) $prj);

        $remote_svn = ($request->conf->getVal('scm') == 'svn' and
                       strlen($request->conf->getVal('svn_remote_url')) > 0);
        $extra = array(
                       'conf' => $request->conf,
                       'remote_svn' => $remote_svn,
                       );
        if ($request->method == 'POST') {
            $form = new IDF_Form_SourceConf($request->POST, $extra);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $request->conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The project source configuration  has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminSource',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            foreach (array('svn_username', 'svn_password', 'webhook_url') as $key) {
                $_val = $request->conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_SourceConf($params, $extra);
        }
        $scm = $request->conf->getVal('scm', 'git');
        $options = array(
                         'git' => __('git'),
                         'svn' => __('Subversion'),
                         'mercurial' => __('mercurial'),
                         'mtn' => __('monotone'),
                         );
        $repository_type = $options[$scm];
        $hook_request_method = 'PUT';
        if (Pluf::f('webhook_processing','') === 'compat') {
            $hook_request_method = 'POST';
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/source.html',
                                               array(
                                                     'remote_svn' => $remote_svn,
                                                     'repository_access' => $prj->getRemoteAccessUrl(),
                                                     'repository_type' => $repository_type,
                                                     'repository_size' => $prj->getRepositorySize(),
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'hookkey' => $prj->getWebHookKey(),
                                                     'hook_request_method' => $hook_request_method,
                                                     ),
                                               $request);
    }

    /**
     * Create the autocomplete arrays for the little AJAX stuff.
     */
    public static function autoCompleteArrays()
    {
        $forge = IDF_Forge::instance();
        $labels = $forge->getProjectLabels(IDF_Form_Admin_LabelConf::init_project_labels);

        $auto = array('auto_labels' => '');
        $auto_raw = array('auto_labels' => $labels);
        foreach ($auto_raw as $key => $st) {
            $st = preg_split("/\015\012|\015|\012/", $st, -1, PREG_SPLIT_NO_EMPTY);
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
                $auto[$key] .= sprintf('{ name: "%s", to: "%s" }, ',
                Pluf_esc($d),
                Pluf_esc($v));
            }
            $auto[$key] = substr($auto[$key], 0, -2);
        }

        return $auto;
    }
}
