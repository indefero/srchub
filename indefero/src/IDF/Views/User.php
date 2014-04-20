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

/**
 * User management views.
 *
 * Edit your account.
 * Add emails for the link between a commit and an account.
 */
class IDF_Views_User
{
    public $requestproject_precond = array('Pluf_Precondition::loginRequired');
    public function requestproject($request)
    {
        $params = array("user" => $request->user);
        $title = __('Request Project');
        $success = false;
        $error = false;
        if ($request->method == 'POST') {
            $form = new IDF_Form_ProjectRequest((array)$request->POST, $params);
            if ($form->isValid()) {
                $check = $form->save();
                if ($check)
                {
                    $form = new IDF_Form_ProjectRequest(null, $params);
                    $success = true;
                } else {
                    $error = "Repository shortname has already been taken.";
                }
            }

        } else {
            $form = new IDF_Form_ProjectRequest(null, $params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/user/projectrequest.html',
            array('page_title' => $title,
                'form' => $form,
                'success' => $success,
                'error' => $error),
            $request);

    }
    /**
     * Dashboard of a user.
     *
     * Shows all the open issues assigned to the user.
     *
     * TODO: This views is a SQL horror. What needs to be done to cut
     * by many the number of SQL queries:
     * - Add a table to cache the open/closed status ids for all the
     *   projects.
     * - Left join the issues with the project to get the shortname.
     *
     */
    public $dashboard_precond = array('Pluf_Precondition::loginRequired');
    public function dashboard($request, $match, $working=true)
    {

        /*
         * This is a little bit better - but still not perfect
         * It has a N*3 SQL query for each issue that is assigned to the user
         * As long as the user isn't assigned 100+ issues - it should hold
         */
        $ownedissues = Pluf::factory("IDF_Issue")->getList(array('filter' => "owner = " . $request->user->id, 'view' => 'project_find'));
        $submittedissues = Pluf::factory("IDF_Issue")->getList(array('filter' => "submitter = " . $request->user->id, 'view' => 'project_find'));
        $nb_owner = count($ownedissues);
        $nb_submit = count($submittedissues);

        // Paginator to paginate the issues
        $pag = null;
        if ($working)
        {
            $pag = new Pluf_Paginator(new IDF_Issue());
            $title = __('Your Dashboard - Working Issues');
            $pag->forced_where = new Pluf_SQL("owner = " . $request->user->id);


        }
        else
        {
            $pag = new Pluf_Paginator(new IDF_Issue());
            $title = __('Your Dashboard - Submitted Issues');
            $pag->forced_where = new Pluf_SQL("submitter = " . $request->user->id);

        }
        $pag->class = 'recent-issues';
        $pag->model_view = 'project_find';
        $pag->item_extra_props = array('current_user' => $request->user);
        $pag->summary = __('This table shows the open issues.');
        $pag->action = ($working) ? 'idf_dashboard' : 'idf_dashboard_submit';
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
            'id' => __('Id'),
            array('project', 'Pluf_Paginator_FkToString', __('Project')),
            array('summary', 'IDF_Views_IssueSummaryAndLabels', __('Summary')),
            array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
            array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
        );
        $pag->configure($list_display, array(), array('status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = ($working) ? __('No issues are assigned to you, yeah!') : __('All the issues you submitted are fixed, yeah!');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/user/dashboard.html',
            array(
                'page_title' => $title,
                'nb_submit' => $nb_submit,
                'nb_owner' => $nb_owner,
                'issues' => $pag,
            ),
            $request);
    }

    /**
     * Simple management of the base info of the user.
     */
    public $myAccount_precond = array('Pluf_Precondition::loginRequired');
    public function myAccount($request, $match)
    {
        // As the password is salted, we can directly take the sha1 of
        // the salted password.
        $api_key = sha1($request->user->password);
        $ext_pass = substr(sha1($request->user->password.Pluf::f('secret_key')), 0, 8);
        $params = array('user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_UserAccount(array_merge(
                (array)$request->POST,
                (array)$request->FILES
            ), $params);
            if ($form->isValid()) {
                $user = $form->save();
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_User::myAccount');
                $request->session->setData('pluf_language', $user->language);
                $request->user->setMessage(__('Your personal information has been updated.'));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $data = $request->user->getData();
            unset($data['password']);
            $form = new IDF_Form_UserAccount(null, $params);
        }
        $keys = $request->user->get_idf_key_list();
        $mailaddrs = Pluf::factory('IDF_EmailAddress')->get_email_addresses_for_user($request->user);

        return Pluf_Shortcuts_RenderToResponse('idf/user/myaccount.html',
            array('page_title' => __('Your Account'),
                'api_key' => $api_key,
                'ext_pass' => $ext_pass,
                'keys' => $keys,
                'mailaddrs' => $mailaddrs,
                'form' => $form),
            $request);
    }

    /**
     * Delete a public key.
     *
     * This is redirecting to the preferences
     */
    public $deleteKey_precond = array('Pluf_Precondition::loginRequired');
    public function deleteKey($request, $match)
    {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_User::myAccount');
        if ($request->method == 'POST') {
            $key = Pluf_Shortcuts_GetObjectOr404('IDF_Key', $match[1]);
            if ($key->user != $request->user->id) {
                return new Pluf_HTTP_Response_Forbidden($request);
            }
            $key->delete();
            $request->user->setMessage(__('The public key has been deleted.'));
        }
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Delete a mail address.
     *
     * This is redirecting to the preferences
     */
    public $deleteMail_precond = array('Pluf_Precondition::loginRequired');
    public function deleteMail($request, $match)
    {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_User::myAccount');
        if ($request->method == 'POST') {
            $address = Pluf_Shortcuts_GetObjectOr404('IDF_EmailAddress', $match[1]);
            if ($address->user != $request->user->id) {
                return new Pluf_HTTP_Response_Forbidden($request);
            }
            $address->delete();
            $request->user->setMessage(__('The address has been deleted.'));
        }
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Enter the key to change an email address.
     *
     * This is redirecting to changeEmailDo
     */
    public $changeEmailInputKey_precond = array('Pluf_Precondition::loginRequired');
    public function changeEmailInputKey($request, $match)
    {
        if ($request->method == 'POST') {
            $form = new IDF_Form_UserChangeEmail($request->POST);
            if ($form->isValid()) {
                $url = $form->save();
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_UserChangeEmail();
        }
        return Pluf_Shortcuts_RenderToResponse('idf/user/changeemail.html',
            array('page_title' => __('Confirm The Email Change'),
                'form' => $form),
            $request);

    }

    /**
     * Really change the email address.
     */
    public $changeEmailDo_precond = array('Pluf_Precondition::loginRequired');
    public function changeEmailDo($request, $match)
    {
        $key = $match[1];
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_User::changeEmailInputKey');
        try {
            list($email, $id, $time, $type) = IDF_Form_UserChangeEmail::validateKey($key);
        } catch (Pluf_Form_Invalid $e) {
            return new Pluf_HTTP_Response_Redirect($url);
        }
        if ($id != $request->user->id) {
            return new Pluf_HTTP_Response_Redirect($url);
        }
        // Now we have a change link coming from the right user.
        if ($type == "primary") {
            $request->user->email = $email;
            $request->user->update();
        } else {
            $mailaddress = new IDF_EmailAddress();
            $mailaddress->user = $request->user;
            $mailaddress->address = $email;
            $mailaddress->create();
        }
        $request->user->setMessage(sprintf(__('Your new email address "%s" has been validated. Thank you!'), Pluf_esc($email)));
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_User::myAccount');
        return new Pluf_HTTP_Response_Redirect($url);
    }


    /**
     * Public profile of a user.
     */
    public function view($request, $match)
    {
        $db =& Pluf::db();
        $sql = new Pluf_SQL('login=%s', array($match[1]));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if (count($users) != 1 or !$users[0]->active) {
            throw new Pluf_HTTP_Error404();
        }

        $user = $users[0];
        $user_data = IDF_UserData::factory($user);

        $ownedprojects = IDF_Views::getOwnedProjects($user);

        $pubprojects = array();
        $privprojects = 0;

        if ($ownedprojects != null && count($ownedprojects) > 0 )
        {
            foreach($ownedprojects as $proj)
            {
                if ($proj->private == 1)
                {
                    $privprojects += 1;
                    continue;
                } else {
                    $pubprojects[] = $proj;
                }
            }
        }
        //$projectstats = IDF_Views::getProjectsStatistics($pubprojects);
        $projectstats = IDF_Views::getProjectsStatisticsByUser($user->id);
        $projectstats["proj_count"] = count($pubprojects);
        //print_r($projectstats);
        //echo $privprojects;

        //$pubprojects = Pluf::factory("IDF_Project")->getList(array('filter' => ""));

        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->model_view = "project_find_private";
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('current_user' => $user);
        $pag->summary = __('This table shows the open issues.');
        $pag->forced_where = new Pluf_SQL("owner = " . $user->id);
        $pag->action = array('idf_user_view', array($user->login));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
            //'id' => __('Id'),
            //array('project', 'Pluf_Paginator_FkToString', __('Project')),
            array('project', 'IDF_Views_ProjectLink', __('Project')),
            array('summary', 'IDF_Views_IssueSummaryAndLabels', __('Summary')),
            array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
            array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
        );
        $pag->configure($list_display, array(), array('status', 'modif_dtime'));
        $pag->items_per_page = 20;
        $pag->no_results_text = __('This user has no issues assigned to them!');
        $pag->setFromRequest($request);

        $pag2 = new Pluf_Paginator(new IDF_Commit());
        $pag2->nb_items = 20;
        $pag2->model_view = "project_find_private";
        $pag2->class = 'recent-issues';
        $pag2->summary = __('This table shows the latest commits of this user.');
        $pag2->forced_where = new Pluf_SQL("author = " . $user->id);
        $pag2->action = array('idf_user_view', array($user->login));
        $pag2->sort_order = array('creation_dtime', 'ASC'); // will be reverted
        $pag2->sort_reverse_order = array('creation_dtime');
        $list_display = array(
            //'id' => __('Id'),
            array('project', 'IDF_Views_ProjectLink', __('Project')),
            'summary' => __('Summary'),
            array('creation_dtime', 'Pluf_Paginator_DateAgo', __('Commit sent')),
        );
        $pag2->configure($list_display, array(), array('creation_dtime'));
        $pag2->items_per_page = 10;
        $pag2->no_results_text = __('This user has not made any commits yet!');
        $pag2->setFromRequest($request);

        $projects = IDF_Views::getOwnedProjects($user);
        return Pluf_Shortcuts_RenderToResponse('idf/user/public.html',
            array('page_title' => (string) $user,
                'member' => $user,
                'user_data' => $user_data,
                'projects' => $projects,
                'issues' => $pag,
                'commits' => $pag2,
                'stats' => new Pluf_Template_ContextVars($projectstats),
                'privatecount' => $privprojects
            ),
            $request);
    }

}

function IDF_Views_ProjectLink($field, $issue, $extra='')
{
    $project = $issue->get_project();
    $edit = Pluf_HTTP_URL_urlForView('IDF_Views_Project::home',
        array($project->shortname));

    return sprintf('<a href="%s">%s</a>', $edit, $project->shortname);
}

/**
 * Display the summary of an issue, then on a new line, display the
 * list of labels with a link to a view "by label only".
 *
 * The summary of the issue is linking to the issue.
 */
function IDF_Views_IssueSummaryAndLabels($field, $issue, $extra='')
{
    $project = $issue->get_project();
    $edit = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
        array($project->shortname, $issue->id));
    $tags = array();
    foreach ($issue->get_tags_list() as $tag) {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::listLabel',
            array($project->shortname, $tag->id, 'open'));
        $tags[] = sprintf('<a class="label" href="%s">%s</a>', $url, Pluf_esc((string) $tag));
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="note">'.implode(', ', $tags).'</span>';
    }
    return sprintf('<a href="%s">%s</a>', $edit, Pluf_esc($issue->summary)).$out;
}

