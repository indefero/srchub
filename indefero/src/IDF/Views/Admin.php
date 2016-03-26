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
 * Administration's views.
 */
class IDF_Views_Admin
{
    /**
     * Start page of the administration.
     */
    public $forge_precond = array('Pluf_Precondition::staffRequired');
    public function forge($request, $match)
    {
        $title = __('Forge Management');
        $forge = IDF_Forge::instance();
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_ForgeConf($request->POST);
            if ($form->isValid()) {
                $forge->setCustomForgePageEnabled($form->cleaned_data['enabled']);
                $forge->setCustomForgePageContent($form->cleaned_data['content']);
                $request->user->setMessage(__('The forge configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::forge');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $params['enabled'] = $forge->isCustomForgePageEnabled();
            if (($content = $forge->getCustomForgePageContent(false)) !== false) {
                $params['content'] = $content;
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_Admin_ForgeConf($params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/forge/index.html',
                                               array(
                                                   'page_title' => $title,
                                                   'form' => $form,
                                                   ),
                                               $request);
            }

    /**
     * Projects overview.
     *
     */
    public $projects_precond = array('Pluf_Precondition::staffRequired');
    public function projects($request, $match)
    {
        $title = __('Projects');
        $pag = new Pluf_Paginator(new IDF_Project());
        $pag->class = 'recent-issues';
        $pag->summary = __('This table shows the projects in the forge.');
        $pag->action = 'IDF_Views_Admin::projects';
        $pag->edit_action = array('IDF_Views_Admin::projectUpdate', 'id');
        $pag->sort_order = array('shortname', 'ASC');
        $pag->model_view = "repo_size";
        $list_display = array(
             'shortname' => __('Short Name'),
             'name' => __('Name'),
             'scm' => ["scm", "IDF_Views_Admin_projectSCM", __('Type')],
             'size' => ["size", "IDF_Views_Admin_projectSize2", __('Repository Size')]

                              );
        $pag->configure($list_display, array('shortname', 'shortname'),
                        array('shortname', 'name', 'scm', 'size' ));
        $pag->extra_classes = array('', '', '', 'right');
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No projects were found.');
        $pag->setFromRequest($request);
	
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'projects' => $pag,
                                                     'size' => IDF_Views_Admin_getForgeSize(),
                                                     ),
                                               $request);
    }

    /**
     * Administrate the labels of a project.
     */
    public $projectLabels_precond = array('Pluf_Precondition::staffRequired');
    public function projectLabels($request, $match)
    {
        $title = __('Project Labels');
        $forge = IDF_Forge::instance();
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_LabelConf($request->POST);
            if ($form->isValid()) {
                $forge->setProjectLabels($form->cleaned_data['project_labels']);
                $request->user->setMessage(__('The label configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::projectLabels');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            if (($labels = $forge->getProjectLabels(false)) !== false) {
                $params['project_labels'] = $labels;
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_Admin_LabelConf($params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/labels.html',
                                               array(
                                                   'page_title' => $title,
                                                   'form' => $form,
                                                   ),
                                               $request);
    }

    /**
     * Edition of a project.
     *
     * One cannot switch from one source backend to another.
     */
    public $projectUpdate_precond = array('Pluf_Precondition::staffRequired');
    public function projectUpdate($request, $match)
    {
        $project = Pluf_Shortcuts_GetObjectOr404('IDF_Project', $match[1]);
        $title = sprintf(__('Update %s'), $project->name);
        $params = array(
                        'project' => $project,
                        );
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_ProjectUpdate($request->POST, $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The project has been updated.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::projectUpdate',
                                                array($project->id));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_ProjectUpdate(null, $params);
        }
        $arrays = IDF_Views_Project::autoCompleteArrays();
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/update.html',
                                               array_merge(
                                                   array(
                                                         'page_title' => $title,
                                                         'project' => $project,
                                                         'form' => $form,
                                                         ),
                                                   $arrays
                                               ),
                                               $request);
    }

    public $projectRequestCreate_precond = array('Pluf_Precondition::staffRequired');
    public function projectRequestCreate($request, $match)
    {
        $title = __('Create Requested Project');
        $createdtext = "";
        $form = null;
        $errors = null;
        if (count($match) == 2)
        {

            $projreqobj = new IDF_ProjectRequest($match[1]);
            $form = new IDF_Form_Admin_ProjectCreate(array(
                "name" => $projreqobj->shortname,
                "shortname" => $projreqobj->shortname,
                "shortdesc" => $projreqobj->desc,
                "scm" => $projreqobj->repotype,
                "owners" => $projreqobj->get_submitter->login,
                "template" => "--"
            ), array("user" => $projreqobj->get_submitter));
            if ($form->isValid())
            {



                Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
                $from_email = Pluf::f('from_email');
                $tmpl = new Pluf_Template('idf/admin/request-email.txt');
                $context = new Pluf_Template_Context(array("user" => $projreqobj->get_submitter, "shortname" => $projreqobj->shortname));
                $text_email = $tmpl->render($context);
                $email = new Pluf_Mail($from_email, $projreqobj->get_submitter->email,
                    __('Status of repository request'));
                $email->addTextMessage($text_email);
                $email->sendMail();

                $form->save();
                $projreqobj->delete();
                $createdtext = "Repo was created!";
            } else {
                $errors = $form->errors;
                $createdtext = "There was an error creating the repo!";
            }
        }

        $projectreqs = Pluf::factory("IDF_ProjectRequest")->getList();
        foreach($projectreqs as $p) {
            $p->creation_dtime = Pluf_Date::gmDateToString($p->creation_dtime);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/approveprojects.html', array (
            'page_title' => $title,
            'requests' => $projectreqs,
            'createdtext' => $createdtext,
            'form' => $form,
            'errors' => $errors
        ), $request);
    }

    public $projectRequestReject_precond = array('Pluf_Precondition::staffRequired');
    public function projectRequestReject($request, $match)
    {
        $title = __('Reject Requested Project');
        $createdtext = "";
        $errors = null;
        if (count($match) == 2)
        {
            $projreqobj = new IDF_ProjectRequest($match[1]);
            Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
            $from_email = Pluf::f('from_email');
            $tmpl = new Pluf_Template('idf/admin/request-email-reject.txt');
            $context = new Pluf_Template_Context(array("user" => $projreqobj->get_submitter, "shortname" => $projreqobj->shortname));
            $text_email = $tmpl->render($context);
            $email = new Pluf_Mail($from_email, $projreqobj->get_submitter->email,
                __('Status of repository request'));
            $email->addTextMessage($text_email);
            $email->sendMail();

            $projreqobj->delete();
            $createdtext = "Request was deleted!";

        }

        $projectreqs = Pluf::factory("IDF_ProjectRequest")->getList();
        foreach($projectreqs as $p) {
            $p->creation_dtime = Pluf_Date::gmDateToString($p->creation_dtime);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/approveprojects.html', array (
            'page_title' => $title,
            'requests' => $projectreqs,
            'createdtext' => $createdtext,
            'form' => null,
            'errors' => $errors
        ), $request);
    }

    /**
     * Creation of a project.
     *
     * A project can use another project as template. In that case,
     * everything but the data in the input at creation time is
     * reused, including the wiki pages.
     */
    public $projectCreate_precond = array('Pluf_Precondition::staffRequired');
    public function projectCreate($request, $match)
    {
        $title = __('Create Project');
        $extra = array('user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_ProjectCreate($request->POST, $extra);
            if ($form->isValid()) {
                $project = $form->save();
                $request->user->setMessage(__('The project has been created.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::projects');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_ProjectCreate(null, $extra);
        }
        $base = Pluf::f('url_base').Pluf::f('idf_base').'/p/';

        $arrays = IDF_Views_Project::autoCompleteArrays();
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/create.html',
                                               array_merge(
                                                   array(
                                                         'page_title' => $title,
                                                         'form' => $form,
                                                         'base_url' => $base,
                                                         ),
                                                   $arrays
                                               ),
                                               $request);
    }

    /**
     * Deletion of a project.
     *
     * Only the forge administrator can perform this operation.
     */
    public $projectDelete_precond = array('Pluf_Precondition::adminRequired');
    public function projectDelete($request, $match)
    {
        $project = Pluf_Shortcuts_GetObjectOr404('IDF_Project', $match[1]);
        $title = sprintf(__('Delete %s Project'), $project);
        $extra = array('project' => $project,
                       'user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_ProjectDelete($request->POST, $extra);
            if ($form->isValid()) {
                $project = $form->save();
                $request->user->setMessage(__('The project has been deleted.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::projects');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_ProjectDelete(null, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/delete.html',
                                               array(
                                                     'project' => $project,
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'stats' => $project->getStats(),
                                                     'code' => $form->getCode(),
                                                     ),
                                               $request);
    }

    /**
     * Users overview.
     *
     */
    public $users_precond = array('Pluf_Precondition::staffRequired');
    public function users($request, $match, $pageType=null)
    {
        $pag = new Pluf_Paginator(new Pluf_User());
        $db =& Pluf::db();
        $true = Pluf_DB_BooleanToDb(true, $db);
        if ($pageType === 0) {
            $pag->forced_where = new Pluf_SQL('first_name = \'---\' AND active!='.$true);
            $title = __('Not Validated User List');
            $pag->action = 'IDF_Views_Admin::usersNotValidated';
        } elseif ($pageType == 1) {
            $pag->forced_where = new Pluf_SQL('first_name != \'---\' AND active!='.$true);
            $title = __('Not Active User List');
            $pag->action = 'IDF_Views_Admin::usersNotValidated';
        } else {
            $pag->forced_where = new Pluf_SQL('first_name != \'---\' AND active='.$true);
            $title = __('User List');
            $pag->action = 'IDF_Views_Admin::users';
        }
        $pag->class = 'recent-issues';
        $pag->summary = __('This table shows the users in the forge.');
        $pag->edit_action = array('IDF_Views_Admin::userUpdate', 'id');
        $pag->sort_order = array('login', 'ASC');
        $list_display = array(
             'login' => __('login'),
             array('last_name', 'Pluf_Paginator_ToString', __('Name')),
             array('staff', 'IDF_Views_Admin_bool', __('Staff')),
             array('administrator', 'IDF_Views_Admin_bool', __('Admin')),
             array('active', 'IDF_Views_Admin_bool', __('Active')),
             array('last_login', 'Pluf_Paginator_DateYMDHM', __('Last Login')),
                              );
        $pag->extra_classes = array('', '', 'a-c', 'a-c', 'a-c', 'a-c');
        $pag->configure($list_display,
                        array('login', 'last_name', 'email'),
                        array('login', 'last_login'));
        $pag->items_per_page = 50;
        $pag->no_results_text = __('No users were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/users/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'users' => $pag,
                                                     ),
                                               $request);
    }

    /**
     * Not validated users.
     */
    public $usersNotValidated_precond = array('Pluf_Precondition::staffRequired');
    public function usersNotValidated($request, $match)
    {
        return $this->users($request, $match, 0);
    }

    /**
     * Not validated users.
     */
    public $usersInActive_precond = array('Pluf_Precondition::staffRequired');
    public function usersInActive($request, $match)
    {
        return $this->users($request, $match, 1);
    }

    /**
     * Edition of a user.
     *
     * Staff cannot edit other staff people and only admin can edit
     * staff.
     */
    public $userUpdate_precond = array('Pluf_Precondition::staffRequired');
    public function userUpdate($request, $match)
    {
        $user = Pluf_Shortcuts_GetObjectOr404('Pluf_User', $match[1]);
        $title = sprintf(__('Update %s'), $user->__toString());
        $params = array(
                        'user' => $user,
                        'request' => $request,
                        );
        // Check the rights.
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::users');
        $error = __('You do not have the rights to update this user.');
        if ($user->administrator and $request->user->id != $user->id) {
            $request->user->setMessage($error);
            return new Pluf_HTTP_Response_Redirect($url);
        }
        if ($user->staff) {
            if (!$request->user->administrator and $request->user->id != $user->id) {
                $request->user->setMessage($error);
                return new Pluf_HTTP_Response_Redirect($url);
            }
        }

        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_UserUpdate(array_merge($request->POST,
                                                              $request->FILES),
                                                  $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The user has been updated.'));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_UserUpdate(null, $params);
        }
        $ownedProjects = \IDF_Views::getOwnedProjects($user);
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/users/update.html',
                                               array(
                                                     'page_title' => $title,
                                                     'cuser' => $user,
                                                     'form' => $form,
                                                     'ownedProjects' => $ownedProjects
                                                     ),
                                               $request);
    }

    /**
     * Create a new user.
     *
     * Only staff can add a user. The user can be added together with
     * a public ssh key.
     */
    public $userCreate_precond = array('Pluf_Precondition::staffRequired');
    public function userCreate($request, $match)
    {
        $params = array(
                        'request' => $request,
                        );
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_UserCreate(array_merge($request->POST,
                                                              $request->FILES),
                                                  $params);
            if ($form->isValid()) {
                $cuser = $form->save();
                $request->user->setMessage(sprintf(__('The user %s has been created.'), (string) $cuser));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::users');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_UserCreate(null, $params);
        }
        $title = __('Add User');
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/users/create.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Usher servers overview
     *
     */
    public $usher_precond = array('Pluf_Precondition::staffRequired');
    public function usher($request, $match)
    {
        $title = __('Usher management');
        $servers = array();
        foreach (IDF_Scm_Monotone_Usher::getServerList() as $server) {
            $servers[] = (object)array(
                "name" => $server,
                "status" => IDF_Scm_Monotone_Usher::getStatus($server),
            );
        }

        return Pluf_Shortcuts_RenderToResponse(
            'idf/gadmin/usher/index.html',
            array(
                 'page_title' => $title,
                 'servers' => $servers,
                 ),
            $request
        );
    }

    /**
     * Usher control
     *
     */
    public $usherControl_precond = array('Pluf_Precondition::staffRequired');
    public function usherControl($request, $match)
    {
        $title = __('Usher control');
        $action = $match[1];

        if (!empty($action)) {
            if (!in_array($action, array('reload', 'shutdown', 'startup'))) {
                throw new Pluf_HTTP_Error404();
            }

            $msg = null;
            if ($action == 'reload') {
                IDF_Scm_Monotone_Usher::reload();
                $msg = __('Usher configuration has been reloaded');
            }
            else if ($action == 'shutdown') {
                IDF_Scm_Monotone_Usher::shutDown();
                $msg = __('Usher has been shut down');
            }
            else
            {
                IDF_Scm_Monotone_Usher::startUp();
                $msg = __('Usher has been started up');
            }

            $request->user->setMessage($msg);
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::usherControl', array(''));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        return Pluf_Shortcuts_RenderToResponse(
            'idf/gadmin/usher/control.html',
            array(
                 'page_title' => $title,
                 'status' => IDF_Scm_Monotone_Usher::getStatus(),
                 ),
            $request
        );
    }

    /**
     * Usher control
     *
     */
    public $usherServerControl_precond = array('Pluf_Precondition::staffRequired');
    public function usherServerControl($request, $match)
    {
        $server = $match[1];
        if (!in_array($server, IDF_Scm_Monotone_Usher::getServerList())) {
            throw new Pluf_HTTP_Error404();
        }

        $action = $match[2];
        if (!in_array($action, array('start', 'stop', 'kill'))) {
            throw new Pluf_HTTP_Error404();
        }

        $msg = null;
        if ($action == 'start') {
            IDF_Scm_Monotone_Usher::startServer($server);
            $msg = sprintf(__('The server "%s" has been started'), $server);
        }
        else if ($action == 'stop') {
            IDF_Scm_Monotone_Usher::stopServer($server);
            $msg = sprintf(__('The server "%s" has been stopped'), $server);
        }
        else
        {
            IDF_Scm_Monotone_Usher::killServer($server);
            $msg = sprintf(__('The server "%s" has been killed'), $server);
        }

        $request->user->setMessage($msg);
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::usher');
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Open connections for a configured server
     *
     */
    public $usherServerConnections_precond = array('Pluf_Precondition::staffRequired');
    public function usherServerConnections($request, $match)
    {
        $server = $match[1];
        if (!in_array($server, IDF_Scm_Monotone_Usher::getServerList())) {
            throw new Pluf_HTTP_Error404();
        }

        $title = sprintf(__('Open connections for "%s"'), $server);

        $connections = IDF_Scm_Monotone_Usher::getConnectionList($server);
        if (count($connections) == 0) {
            $request->user->setMessage(sprintf(
               __('no connections for server "%s"'), $server
            ));
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::usher');
            return new Pluf_HTTP_Response_Redirect($url);
        }

        return Pluf_Shortcuts_RenderToResponse(
            'idf/gadmin/usher/connections.html',
            array(
                 'page_title' => $title,
                 'server' => $server,
                 'connections' => $connections,
                 ),
            $request
        );
    }
}

function IDF_Views_Admin_bool($field, $item)
{
    $img = ($item->$field) ? 'day' : 'night';
    $text = ($item->$field) ? __('Yes') : __('No');
    return sprintf('<img src="'.Pluf::f('url_media').'/idf/img/%s.png" alt="%s" /> ', $img, $text);
}

/**
 * Display the size of the project.
 *
 * @param string Field
 * @param IDF_Project
 * @return string
 */
function IDF_Views_Admin_projectSize($field, $project)
{
    $size = $project->getRepositorySize();
    if ($size == -1) {
        return '';
    }
    return Pluf_Utils::prettySize($size);
}

function IDF_Views_Admin_projectSize2($field, $project)
{
    return Pluf_Utils::prettySize($project->size);
}

function IDF_Views_Admin_projectSCM($field, $project)
{
    return $project->getConf()->getVal("scm");
}

/**
 * Get a forge size.
 *
 * @return array Associative array with the size of each element
 */
function IDF_Views_Admin_getForgeSize($force=false)
{
    $conf = new IDF_Gconf();
    $conf->setModel((object) array('_model'=>'IDF_Forge', 'id'=> 1));
    $res = array();
    $res['repositories'] = 0;
    foreach (Pluf::factory('IDF_Project')->getList() as $prj) {
        $size = $prj->getRepositorySize($force);
        if ($size != -1) {
            $res['repositories'] += $size;
        }
    }
    $last_eval = $conf->getVal('downloads_size_check_date', 0);
    if (Pluf::f('idf_no_size_check', false) or
        (!$force and $last_eval > time()-172800)) {
        $res['downloads'] = $conf->getVal('downloads_size', 0);
    } else {
        $conf->setVal('downloads_size_check_date', time());
        $total = 0;
        foreach(Pluf::factory("IDF_Upload")->getList() as $issuefile)
        {
            $total += $issuefile->filesize;
        }
        $res['downloads'] = $total;
        $conf->setVal('downloads_size', $res['downloads']);
    }
    $last_eval = $conf->getVal('attachments_size_check_date', 0);
    if (Pluf::f('idf_no_size_check', false) or
        (!$force and $last_eval > time()-172800)) {
        $res['attachments'] = $conf->getVal('attachments_size', 0);
    } else {
        $total = 0;
        foreach(Pluf::factory("IDF_IssueFile")->getList() as $issuefile)
        {
            $total += $issuefile->filesize;
        }
        $res['attachments'] = $total;
        $conf->setVal('attachments_size_check_date', time());
        $conf->setVal('attachments_size', $res['attachments']);
    }
    $last_eval = $conf->getVal('database_size_check_date', 0);
    if (Pluf::f('idf_no_size_check', false) or
        (!$force and $last_eval > time()-172800)) {
        $res['database'] = $conf->getVal('database_size', 0);
    } else {
        $conf->setVal('database_size_check_date', time());
        $res['database'] = IDF_Views_Admin_getForgeDbSize();
        $conf->setVal('database_size', $res['database']);
    }
    $res['total'] = $res['repositories'] + $res['downloads'] + $res['attachments'] + $res['database'];
    return $res;
}

/**
 * Get the database size as given by the database.
 *
 * @return int Database size
 */
function IDF_Views_Admin_getForgeDbSize()
{
    $db = Pluf::db();
    if (Pluf::f('db_engine') == 'SQLite') {
        return filesize(Pluf::f('db_database'));
    }
    switch (Pluf::f('db_engine')) {
    case 'PostgreSQL':
        $sql = 'SELECT relname, pg_total_relation_size(CAST(relname AS
TEXT)) AS size FROM pg_class AS pgc, pg_namespace AS pgn
     WHERE pg_table_is_visible(pgc.oid) IS TRUE AND relkind = \'r\'
     AND pgc.relnamespace = pgn.oid
     AND pgn.nspname NOT IN (\'information_schema\', \'pg_catalog\')';
        break;
    case 'MySQL':
    default:
        $sql = 'SHOW TABLE STATUS FROM `'.Pluf::f('db_database').'`';
        break;
    }
    $rs = $db->select($sql);
    $total = 0;
    switch (Pluf::f('db_engine')) {
    case 'PostgreSQL':
        foreach ($rs as $table) {
            $total += $table['size'];
        }
        break;
    case 'MySQL':
    default:
        foreach ($rs as $table) {
            $total += $table['Data_length'] + $table['Index_length'];
        }
        break;
    }
    return $total;
}
