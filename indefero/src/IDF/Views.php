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
 * Base views of InDefero.
 */
class IDF_Views
{
    /**
     * The index view.
     */
    public function index($request, $match)
    {
        $forge = IDF_Forge::instance();
        if (!$forge->isCustomForgePageEnabled()) {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views::listProjects');
            return new Pluf_HTTP_Response_Redirect($url);
        }

        return Pluf_Shortcuts_RenderToResponse('idf/index.html',
                                                array('page_title' => __('Welcome'),
                                                      'content' => $forge->getCustomForgePageContent(),
                                                ),
                                                $request);
    }

    /**
     * List all projects unfiltered
     *
     * @param unknown_type $request
     * @param unknown_type $match
     * @return Pluf_HTTP_Response
     */
    public function listProjects($request, $match)
    {
        $match = array('', 'all', 'name');
        return $this->listProjectsByLabel($request, $match);
    }

    /**
     * List projects, optionally filtered by label
     *
     * @param unknown_type $request
     * @param unknown_type $match
     * @return Pluf_HTTP_Response
     */
    public function listProjectsByLabel($request, $match)
    {
        list(, $tagId, $order) = $match;

        $tag = false;
        if ($tagId !== 'all') {
            $tag = Pluf::factory('IDF_Tag')->get($match[1]);
            // ignore non-global tags
            if ($tag !== false && $tag->project > 0) {
                $tag = false;
            }
        }
        $order = in_array($order, array('name', 'activity')) ? $order : 'name';

        $projects = self::getProjects($request->user, $tag, $order);
        $stats = self::getProjectsStatistics($projects);
        $projectLabels = self::getProjectLabelsWithCounts($request->user);

        return Pluf_Shortcuts_RenderToResponse('idf/listProjects.html',
                                               array('page_title' => __('Projects'),
                                                     'projects' => $projects,
                                                     'projectLabels' => $projectLabels,
                                                     'tag' => $tag,
                                                     'order' => $order,
                                                     'stats' => new Pluf_Template_ContextVars($stats)),
                                               $request);
    }

    /**
     * Login view.
     */
    public function login($request, $match)
    {
        if (isset($request->POST['action'])
            and $request->POST['action'] == 'new-user') {
            $login = (isset($request->POST['login'])) ? $request->POST['login'] : '';
            $url = Pluf_HTTP_URL_urlForView('IDF_Views::register', array(),
                                            array('login' => $login));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $v = new Pluf_Views();
        $request->POST['login'] = (isset($request->POST['login'])) ? mb_strtolower($request->POST['login']) : '';
        return $v->login($request, $match, Pluf::f('login_success_url'),
                         array(), 'idf/login_form.html');
    }

    /**
     * Logout view.
     */
    function logout($request, $match)
    {
        $views = new Pluf_Views();
        return $views->logout($request, $match, Pluf::f('after_logout_page'));
    }

    /**
     * Registration.
     *
     * We just ask for login, email and to agree with the terms. Then,
     * we go ahead and send a confirmation email. The confirmation
     * email will allow to set the password, first name and last name
     * of the user.
     */
    function register($request, $match)
    {
        $title = __('Create Your Account');
        $params = array('request'=>$request);
        if ($request->method == 'POST') {
            $form = new IDF_Form_Register(array_merge(
            									(array)$request->POST,
												(array)$request->FILES
												), $params);
            if ($form->isValid()) {
                $user = $form->save(); // It is sending the confirmation email
                $url = Pluf_HTTP_URL_urlForView('IDF_Views::registerInputKey');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            if (isset($request->GET['login'])) {
                $params['initial'] = array('login' => $request->GET['login']);
            }
            $form = new IDF_Form_Register(null, $params);
        }
        $context = new Pluf_Template_Context(array());
        $tmpl = new Pluf_Template('idf/terms.html');
        $terms = Pluf_Template::markSafe($tmpl->render($context));
        return Pluf_Shortcuts_RenderToResponse('idf/register/index.html',
                                               array('page_title' => $title,
                                                     'form' => $form,
                                                     'terms' => $terms),
                                               $request);
    }

    /**
     * Input the registration confirmation key.
     *
     * Very simple view just to redirect to the register confirmation
     * views to input the password.
     */
    function registerInputKey($request, $match)
    {
        $title = __('Confirm Your Account Creation');
        if ($request->method == 'POST') {
            $form = new IDF_Form_RegisterInputKey($request->POST);
            if ($form->isValid()) {
                $url = $form->save();
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_RegisterInputKey();
        }
        return Pluf_Shortcuts_RenderToResponse('idf/register/inputkey.html',
                                               array('page_title' => $title,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * Registration confirmation.
     *
     * Input first/last name, password and sign in the user.
     *
     * Maybe in the future send the user to its personal page for
     * customization.
     */
    function registerConfirmation($request, $match)
    {
        $title = __('Confirm Your Account Creation');
        $key = $match[1];
        // first "check", full check is done in the form.
        $email_id = IDF_Form_RegisterInputKey::checkKeyHash($key);
        if (false == $email_id) {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views::registerInputKey');
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $user = new Pluf_User($email_id[1]);
        $extra = array('key' => $key,
                       'user' => $user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_RegisterConfirmation($request->POST, $extra);
            if ($form->isValid()) {
                $user = $form->save();
                $request->user = $user;
                $request->session->clear();
                $request->session->setData('login_time', gmdate('Y-m-d H:i:s'));
                $user->last_login = gmdate('Y-m-d H:i:s');
                $user->update();
                $request->user->setMessage(__('Welcome! You can now participate in the life of your project of choice.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views::index');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_RegisterConfirmation(null, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/register/confirmation.html',
                                               array('page_title' => $title,
                                                     'new_user' => $user,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * Password recovery.
     *
     * Request the login or the email of the user and if the login or
     * email is available in the database, send an email with a key to
     * reset the password.
     *
     * If the user is not yet confirmed, send the confirmation key one
     * more time.
     */
    function passwordRecoveryAsk($request, $match)
    {
        $title = __('Password Recovery');
        if ($request->method == 'POST') {
            $form = new IDF_Form_Password($request->POST);
            if ($form->isValid()) {
                $url = $form->save();
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Password();
        }
        return Pluf_Shortcuts_RenderToResponse('idf/user/passrecovery-ask.html',
                                               array('page_title' => $title,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * If the key is valid, provide a nice form to reset the password
     * and automatically login the user.
     *
     * This is also firing the password change event for the plugins.
     */
    public function passwordRecovery($request, $match)
    {
        $title = __('Password Recovery');
        $key = $match[1];
        // first "check", full check is done in the form.
        $email_id = IDF_Form_PasswordInputKey::checkKeyHash($key);
        if (false == $email_id) {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views::passwordRecoveryInputKey');
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $user = new Pluf_User($email_id[1]);
        $extra = array('key' => $key,
                       'user' => $user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_PasswordReset($request->POST, $extra);
            if ($form->isValid()) {
                $user = $form->save();
                $request->user = $user;
                $request->session->clear();
                $request->session->setData('login_time', gmdate('Y-m-d H:i:s'));
                $user->last_login = gmdate('Y-m-d H:i:s');
                $user->update();
                $request->user->setMessage(__('Welcome back! Next time, you can use your broswer options to remember the password.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views::index');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_PasswordReset(null, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/user/passrecovery.html',
                                               array('page_title' => $title,
                                                     'new_user' => $user,
                                                     'form' => $form),
                                               $request);

    }

    /**
     * Just a simple input box to provide the code and redirect to
     * passwordRecovery
     */
    public function passwordRecoveryInputCode($request, $match)
    {
        $title = __('Password Recovery');
        if ($request->method == 'POST') {
            $form = new IDF_Form_PasswordInputKey($request->POST);
            if ($form->isValid()) {
                $url = $form->save();
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_PasswordInputKey();
        }
        return Pluf_Shortcuts_RenderToResponse('idf/user/passrecovery-inputkey.html',
                                               array('page_title' => $title,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * FAQ.
     */
    public function faq($request, $match)
    {
        $title = __('Here to Help You!');
        $projects = self::getProjects($request->user);
        return Pluf_Shortcuts_RenderToResponse('idf/faq.html',
                                               array(
                                                     'page_title' => $title,
                                                     'projects' => $projects,
                                                     ),
                                               $request);

    }

    /**
     * Download archive FAQ.
     */
    public function faqArchiveFormat($request, $match)
    {
        $title = __('InDefero Upload Archive Format');
        $projects = self::getProjects($request->user);
        return Pluf_Shortcuts_RenderToResponse('idf/faq-archive-format.html',
                                               array(
                                                     'page_title' => $title,
                                                     'projects' => $projects,
                                                     ),
                                               $request);

    }

    /**
     * API FAQ.
     */
    public function faqApi($request, $match)
    {
        $title = __('InDefero API (Application Programming Interface)');
        $projects = self::getProjects($request->user);
        return Pluf_Shortcuts_RenderToResponse('idf/faq-api.html',
                                               array(
                                                     'page_title' => $title,
                                                     'projects' => $projects,
                                                     ),
                                               $request);

    }

    /**
     * Returns a list of ids of projects that are visible for the given user
     *
     * @param Pluf_User $user
     */
    private static function getUserVisibleProjectIds($user)
    {
        $db =& Pluf::db();
        // the administrator can see all projects
        if ($user->administrator) {
            $ids = array();
            $sql_results = $db->select(
                'SELECT id FROM '.Pluf::f('db_table_prefix', '').'idf_projects'
            );
            foreach ($sql_results as $id) {
                $ids[] = $id['id'];
            }
            return $ids;
        }

        // anonymous users can only see non-private projects
        $false = Pluf_DB_BooleanToDb(false, $db);
        $true = Pluf_DB_BooleanToDb(true, $db);

        $dbpfx = $db->pfx;
        $disabled_results = $db->select("SELECT id FROM ${dbpfx}idf_projects WHERE disabled = $true");
        $disabled_ids = [];
        foreach($disabled_results as $id) {
            $disabled_ids[] = $id['id'];
        }

        $sql_results = $db->select(
            'SELECT id FROM '.$db->pfx.'idf_projects '.
            'WHERE '.$db->qn('private').'='.$false
        );

        $ids = array();
        foreach ($sql_results as $id) {
            if (!in_array($id['id'], $disabled_ids)) {
                $ids[] = $id['id'];
            }
        }



        // registered users may additionally see private projects with which
        // they're somehow affiliated
        if (!$user->isAnonymous()) {
            $perms = array(
                Pluf_Permission::getFromString('IDF.project-member'),
                Pluf_Permission::getFromString('IDF.project-owner'),
                Pluf_Permission::getFromString('IDF.project-authorized-user')
            );
            $permSql = new Pluf_SQL(
                "model_class='IDF_Project' AND owner_class='Pluf_User' ".
                "AND owner_id=%s AND negative=".$false, $user->id
            );
            $rows = Pluf::factory('Pluf_RowPermission')->getList(array('filter' => $permSql->gen()));
            if ($rows->count() > 0) {
                foreach ($rows as $row) {
                    if (in_array($row->model_id, $ids) || in_array($row->model_id, $disabled_ids))
                        continue;
                    $ids[] = $row->model_id;
                }
            }
        }
        return $ids;
    }

    /**
     * Returns a list of projects accessible for the user and optionally filtered by tag.
     *
     * @param Pluf_User
     * @param IDF_Tag
     * @return ArrayObject IDF_Project
     */
    public static function getProjects($user, $tag = false, $order = 'name')
    {
        $db =& Pluf::db();
        $sql = new Pluf_SQL('1=1');
        if ($tag !== false) {
            $sql->SAnd(new Pluf_SQL('idf_tag_id=%s', $tag->id));
        }

        $projectIds = self::getUserVisibleProjectIds($user);
        if (count($projectIds) == 0) {
            return new ArrayObject();
        }

        $sql->SAnd(new Pluf_SQL(sprintf($db->pfx.'idf_projects.id IN (%s)', implode(', ', $projectIds))));

        $orderTypes = array(
            'name' => 'name ASC',
            'activity' => 'value DESC, name ASC',
        );
        return Pluf::factory('IDF_Project')->getList(array(
            'filter'=> $sql->gen(),
            'view' => 'join_activities_and_tags',
            'order' => $orderTypes[$order],
        ));
    }

    public static function getOwnedProjects($user)
    {
        $db =& Pluf::db();
        $false = Pluf_DB_BooleanToDb(false, $db);
        $permSql = new Pluf_SQL(
            "model_class='IDF_Project' AND owner_class='Pluf_User' ".
            "AND owner_id=%s AND negative=".$false, $user->id
        );
        $rows = Pluf::factory('Pluf_RowPermission')->getList(array('filter' => $permSql->gen()));
        $ids = array();
        if ($rows->count() > 0) {
            foreach ($rows as $row) {
                if (in_array($row->model_id, $ids))
                    continue;
                $ids[] = $row->model_id;
            }
        }
        if (count($ids) > 0)
        {
            $sql = new Pluf_SQL(sprintf("id IN (%s)", implode(", ", $ids)));
            return Pluf::factory('IDF_Project')->getList(array(
                'filter'=> $sql->gen(),
            ));
        } else {
            return null;
        }
    }

    /**
     * Returns a list of global tags each carrying the number of projects that have the
     * particular tag set
     *
     * @param Pluf_User $user
     * @return array
     */
    public static function getProjectLabelsWithCounts($user) {

        $sql = new Pluf_SQL('project IS NULL');

        $projectIds = self::getUserVisibleProjectIds($user);
        if (count($projectIds) == 0) {
            return new ArrayObject();
        }

        $sql->SAnd(new Pluf_SQL(sprintf('idf_project_id IN (%s)', implode(', ', $projectIds))));

        $tagList = Pluf::factory('IDF_Tag')->getList(array(
            'filter' => $sql->gen(),
            'view' => 'join_projects',
            'order' => 'class ASC, lcname ASC'
        ));

        $maxProjectCount = 0;
        foreach ($tagList as $tag) {
            $maxProjectCount = max($maxProjectCount, $tag->project_count);
        }

        $tags = array();
        foreach ($tagList as $tag) {
            // group by class
            if (!array_key_exists($tag->class, $tags)) {
                $tags[$tag->class] = array();
            }
            $tag->rel_project_count = $tag->project_count / (double) $maxProjectCount;
            $tags[$tag->class][] = $tag;
        }
        return $tags;
    }


    /**
     * Returns statistics on a list of projects.
     *
     * @param ArrayObject IDF_Project
     * @return Associative array of statistics
     */
    public static function getProjectsStatistics($projects)
    {
        $projectIds = array(0);
        foreach ($projects as $project) {
            $projectIds[] = $project->id;
        }

        $forgestats = array();

        // count overall project stats
        $forgestats['total'] = 0;
        $what = array(
            'downloads' => 'IDF_Upload',
            'reviews'   => 'IDF_Review',
            'issues'    => 'IDF_Issue',
            'docpages'  => 'IDF_Wiki_Page',
            'commits'   => 'IDF_Commit',
        );

        foreach ($what as $key => $model) {
            if ($key == "issues") {

                $nb_open = Pluf::factory("IDF_Issue")->getCount(array('view'=>'project_find_open', 'filter' => sprintf('indefero_idf_issues.project IN (%s)', implode(', ', $projectIds))));
                $nb_closed = Pluf::factory("IDF_Issue")->getCount(array('view'=>'project_find_closed', 'filter' => sprintf('indefero_idf_issues.project IN (%s)', implode(', ', $projectIds))));

                $count = $nb_closed + $nb_open;

                $forgestats["openissues"] = $nb_open;
                $forgestats["closedissues"] = $nb_closed;
            } else {
                $count = Pluf::factory($model)->getCount(array(
                    'filter' => sprintf('project IN (%s)', implode(', ', $projectIds))
                ));

                $forgestats[$key] = $count;

            }

            $forgestats['total'] += $count;

        }
	    $forgestats['proj_count'] = count($projects);
        return $forgestats;
    }

    /**
     * Returns statistics by a userID
     *
     * @param ArrayObject IDF_Project
     * @return Associative array of statistics
     */
    public static function getProjectsStatisticsByUser($uid)
    {
        $forgestats = array();

        // count overall project stats
        $forgestats['total'] = 0;
        $what = array(
            'downloads' => 'IDF_Upload',
            'reviews'   => 'IDF_Review',
            'issues'    => 'IDF_Issue',
            'docpages'  => 'IDF_Wiki_Page',

        );
        foreach ($what as $key => $model) {
            $count = Pluf::factory($model)->getCount(array(
                'filter' => sprintf('submitter = %d', $uid)
            ));
            $forgestats[$key] = $count;
            $forgestats['total'] += $count;
        }
        //             'commits'   => 'IDF_Commit',
        $count = Pluf::factory('IDF_Commit')->getCount(array(
            'filter' => sprintf('author = %d', $uid)
        ));
        $forgestats['commits'] = $count;
        $forgestats['total'] += $count;

        return $forgestats;
    }
}
