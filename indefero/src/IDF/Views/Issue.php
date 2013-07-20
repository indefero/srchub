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
 * Issues' views.
 */
class IDF_Views_Issue
{
    /**
     * View list of issues for a given project.
     */
    public $index_precond = array('IDF_Precondition::accessIssues');
    public function index($request, $match, $api=false)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Open Issues'), (string) $prj);
        // Get stats about the issues
        $open = $prj->getIssueCountByStatus('open');
        $closed = $prj->getIssueCountByStatus('closed');
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the open issues.');
        $otags = $prj->getTagIdsByStatus('open');
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $otags).')', array($prj->id));
        $pag->action = array('IDF_Views_Issue::index', array($prj->shortname));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $pag->sort_link_title = true;
        $pag->extra_classes = array('a-c', '', 'a-c', '');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('id', 'status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        $params = array('project' => $prj,
                        'page_title' => $title,
                        'open' => $open,
                        'closed' => $closed,
                        'issues' => $pag,
                        'cloud' => 'issues',
                );
        if ($api) return $params;
        return Pluf_Shortcuts_RenderToResponse('idf/issues/index.html',
                                               $params, $request);
    }

    /**
     * View the issue summary.
     * TODO Add thoses data in cache, and process it only after an issue update
     */
    public $summary_precond = array('IDF_Precondition::accessIssues');
    public function summary($request, $match)
    {
        $tagStatistics = array();
        $ownerStatistics = array();
        $status = array();
        $isTrackerEmpty = false;

        $prj = $request->project;
        $opened = $prj->getIssueCountByStatus('open');
        $closed = $prj->getIssueCountByStatus('closed');

        // Check if the tracker is empty
        if ($opened === 0 && $closed === 0) {
            $isTrackerEmpty = true;
        } else {
            if ($opened > 0 || $closed > 0) {
                // Issue status statistics
                $status['Open'] = array($opened, (int)(100 * $opened / ($opened + $closed)));
                $status['Closed'] = array($closed, (int)(100 * $closed / ($opened + $closed)));
            }

            if ($opened > 0) {
                // Issue owner statistics
                $owners = $prj->getIssueCountByOwner('open');
                foreach ($owners as $user => $nb) {
                    if ($user === '') {
                        $key = __('Not assigned');
                        $login = null;
                    } else {
                        $obj = Pluf::factory('Pluf_User')->getOne(array('filter'=>'id='.$user));
                        $key = $obj->first_name . ' ' . $obj->last_name;
                        $login = $obj->login;
                    }
                    $ownerStatistics[$key] = array($nb, (int)(100 * $nb / $opened), $login);
                }
                arsort($ownerStatistics);

                // Issue class tag statistics
                $grouped_tags = $prj->getTagCloud();
                foreach ($grouped_tags as $class => $tags) {
                    foreach ($tags as $tag) {
                        $tagStatistics[$class][$tag->name] = array($tag->nb_use, $tag->id);
                    }
                    uasort($tagStatistics[$class], function ($a, $b) {
                        if ($a[0] === $b[0])
                            return 0;

                        return ($a[0] > $b[0]) ? -1 : 1;
                    });
                }
                foreach($tagStatistics as $k => $v) {
                    $nbIssueInClass = 0;
                    foreach ($v as $val) {
                        $nbIssueInClass += $val[0];
                    }
                    foreach ($v as $kk => $vv) {
                        $tagStatistics[$k][$kk] = array($vv[0], (int)(100 * $vv[0] / $nbIssueInClass), $vv[1]);
                    }
                }
            }
        }

        $title = sprintf(__('Summary of tracked issues in %s.'), (string) $prj);

        return Pluf_Shortcuts_RenderToResponse('idf/issues/summary.html',
                                               array('page_title' => $title,
                                                     'trackerEmpty' => $isTrackerEmpty,
                                                     'project' => $prj,
                                                     'tagStatistics' => $tagStatistics,
                                                     'ownerStatistics' => $ownerStatistics,
                                                     'status' => $status,
                                                     ),
                                               $request);
    }

    /**
     * View the issues watch list of a given user.
     * Limited to a specified project
     */
    public $watchList_precond = array('IDF_Precondition::accessIssues',
                                      'Pluf_Precondition::loginRequired');
    public function watchList($request, $match)
    {
        $prj = $request->project;
        $otags = $prj->getTagIdsByStatus('open');
        $ctags = $prj->getTagIdsByStatus('closed');
        if (count($otags) == 0) $otags[] = 0;
        if (count($ctags) == 0) $ctags[] = 0;

         // Get the id list of issue in the user watch list (for all projects !)
        $db =& Pluf::db();
        $sql_results = $db->select('SELECT idf_issue_id as id FROM '.Pluf::f('db_table_prefix', '').'idf_issue_pluf_user_assoc WHERE pluf_user_id='.$request->user->id);
        $issue_ids = array(0);
        foreach ($sql_results as $id) {
           $issue_ids[] = $id['id'];
        }
        $issue_ids = implode (',', $issue_ids);

        // Count open and close issues
        $sql = new Pluf_SQL('project=%s AND id IN ('.$issue_ids.') AND status IN ('.implode(', ', $otags).')', array($prj->id));
        $nb_open = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        $sql = new Pluf_SQL('project=%s AND id IN ('.$issue_ids.') AND status IN ('.implode(', ', $ctags).')', array($prj->id));
        $nb_closed = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));

        // Generate a filter for the paginator
        switch ($match[2]) {
        case 'closed':
            $title = sprintf(__('Watch List: Closed Issues for %s'), (string) $prj);
            $summary = __('This table shows the closed issues in your watch list for %s project.', (string) $prj);
            $f_sql = new Pluf_SQL('project=%s AND id IN ('.$issue_ids.') AND status IN ('.implode(', ', $ctags).')', array($prj->id));
            break;
        case 'open':
        default:
            $title = sprintf(__('Watch List: Open Issues for %s'), (string) $prj);
            $summary = __('This table shows the open issues in your watch list for %s project.', (string) $prj);
            $f_sql = new Pluf_SQL('project=%s AND id IN ('.$issue_ids.') AND status IN ('.implode(', ', $otags).')', array($prj->id));
            break;
        }

        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = $summary;
        $pag->forced_where = $f_sql;
        $pag->action = array('IDF_Views_Issue::watchList', array($prj->shortname, $match[1]));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $pag->sort_link_title = true;
        $pag->extra_classes = array('a-c', '', 'a-c', '');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('id', 'status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/project-watchlist.html',
                                               array('project' => $prj,
                                                     'page_title' => $title,
                                                     'open' => $nb_open,
                                                     'closed' => $nb_closed,
                                                     'issues' => $pag,
                                                     ),
                                               $request);
    }

    /**
     * View the issues watch list of a given user.
     * For all projects
     */
    public $forgeWatchList_precond = array('Pluf_Precondition::loginRequired');
    public function forgeWatchList($request, $match)
    {
        $otags = array();
        $ctags = array();
        // Note that this approach does not scale, we will need to add
        // a table to cache the meaning of the tags for large forges.
        foreach (IDF_Views::getProjects($request->user) as $project) {
            $otags = array_merge($otags, $project->getTagIdsByStatus('open'));
        }
        foreach (IDF_Views::getProjects($request->user) as $project) {
            $ctags = array_merge($ctags, $project->getTagIdsByStatus('closed'));
        }
        if (count($otags) == 0) $otags[] = 0;
        if (count($ctags) == 0) $ctags[] = 0;

         // Get the id list of issue in the user watch list (for all projects !)
        $db =& Pluf::db();
        $sql_results = $db->select('SELECT idf_issue_id as id FROM '.Pluf::f('db_table_prefix', '').'idf_issue_pluf_user_assoc WHERE pluf_user_id='.$request->user->id);
        $issue_ids = array(0);
        foreach ($sql_results as $id) {
           $issue_ids[] = $id['id'];
        }
        $issue_ids = implode (',', $issue_ids);

        // Count open and close issues
        $sql = new Pluf_SQL('id IN ('.$issue_ids.') AND status IN ('.implode(', ', $otags).')', array());
        $nb_open = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        $sql = new Pluf_SQL('id IN ('.$issue_ids.') AND status IN ('.implode(', ', $ctags).')', array());
        $nb_closed = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));

        // Generate a filter for the paginator
        switch ($match[1]) {
        case 'closed':
            $title = sprintf(__('Watch List: Closed Issues'));
            $summary = __('This table shows the closed issues in your watch list.');
            $f_sql = new Pluf_SQL('id IN ('.$issue_ids.') AND status IN ('.implode(', ', $ctags).')', array());
            break;
        case 'open':
        default:
            $title = sprintf(__('Watch List: Open Issues'));
            $summary = __('This table shows the open issues in your watch list.');
            $f_sql = new Pluf_SQL('id IN ('.$issue_ids.') AND status IN ('.implode(', ', $otags).')', array());
            break;
        }

        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('current_user' => $request->user);
        $pag->summary = $summary;
        $pag->forced_where = $f_sql;
        $pag->action = array('IDF_Views_Issue::forgeWatchList', array($match[1]));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $pag->sort_link_title = true;
        $pag->extra_classes = array('a-c', '', 'a-c', 'a-c', 'a-c');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabelsUnknownProject', __('Summary')),
             array('project', 'Pluf_Paginator_FkToString', __('Project')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('id', 'project', 'status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/forge-watchlist.html',
                                               array('page_title' => $title,
                                                     'open' => $nb_open,
                                                     'closed' => $nb_closed,
                                                     'issues' => $pag,
                                                     ),
                                               $request);
        }

    /**
     * View the issues of a given user.
     *
     * Only open issues are shown.
     */
    public $userIssues_precond = array('IDF_Precondition::accessIssues');
    public function userIssues($request, $match)
    {
        $prj = $request->project;

        $sql = new Pluf_SQL('login=%s', array($match[2]));
        $user = Pluf::factory('Pluf_User')->getOne(array('filter' => $sql->gen()));
        if ($user === null) {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::index',
                                            array($prj->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $otags = $prj->getTagIdsByStatus('open');
        $ctags = $prj->getTagIdsByStatus('closed');
        if (count($otags) == 0) $otags[] = 0;
        if (count($ctags) == 0) $ctags[] = 0;
        switch ($match[3]) {
        case 'submit':
            $titleFormat = __('%1$s %2$s Submitted %3$s Issues');
            $f_sql = new Pluf_SQL('project=%s AND submitter=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $user->id));
            break;
        case 'submitclosed':
            $titleFormat = __('%1$s %2$s Closed Submitted %3$s Issues');
            $f_sql = new Pluf_SQL('project=%s AND submitter=%s AND status IN ('.implode(', ', $ctags).')', array($prj->id, $user->id));
            break;
        case 'ownerclosed':
            $titleFormat = __('%1$s %2$s Closed Working %3$s Issues');
            $f_sql = new Pluf_SQL('project=%s AND owner=%s AND status IN ('.implode(', ', $ctags).')', array($prj->id, $user->id));
            break;
        default:
            $titleFormat = __('%1$s %2$s Working %3$s Issues');
            $f_sql = new Pluf_SQL('project=%s AND owner=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $user->id));
            break;
        }
        $title = sprintf($titleFormat,
                         $user->first_name,
                         $user->last_name,
                         (string) $prj);

        // Get stats about the issues
        $sql = new Pluf_SQL('project=%s AND submitter=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $user->id));
        $nb_submit = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        $sql = new Pluf_SQL('project=%s AND owner=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $user->id));
        $nb_owner = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        // Closed issues
        $sql = new Pluf_SQL('project=%s AND submitter=%s AND status IN ('.implode(', ', $ctags).')', array($prj->id, $user->id));
        $nb_submit_closed = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        $sql = new Pluf_SQL('project=%s AND owner=%s AND status IN ('.implode(', ', $ctags).')', array($prj->id, $user->id));
        $nb_owner_closed = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));

        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the open issues.');
        $pag->forced_where = $f_sql;
        $pag->action = array('IDF_Views_Issue::userIssues', array($prj->shortname, $match[2]));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $pag->sort_link_title = true;
        $pag->extra_classes = array('a-c', '', 'a-c', '');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('id', 'status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/userIssues.html',
                                               array('project' => $prj,
                                                     'page_title' => $title,
                                                     'login' => $user->login,
                                                     'nb_submit' => $nb_submit,
                                                     'nb_owner' => $nb_owner,
                                                     'nb_submit_closed' => $nb_submit_closed,
                                                     'nb_owner_closed' => $nb_owner_closed,
                                                     'issues' => $pag,
                                                     ),
                                               $request);
    }

    public $create_precond = array('IDF_Precondition::accessIssues',
                                   'Pluf_Precondition::loginRequired');
    public function create($request, $match, $api=false)
    {
        $prj = $request->project;
        $title = __('Submit a new issue');
        $params = array(
                        'project' => $prj,
                        'user' => $request->user);
        $preview = (isset($request->POST['preview'])) ?
            $request->POST['content'] : false;
        if ($request->method == 'POST') {
            $form = new IDF_Form_IssueCreate(array_merge($request->POST,
                                                         $request->FILES),
                                             $params);
            if (!isset($request->POST['preview']) and $form->isValid()) {
                $issue = $form->save();
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                                array($prj->shortname, $issue->id));
                $issue->notify($request->conf);
                if ($api) return $issue;
                $request->user->setMessage(sprintf(__('<a href="%1$s">Issue %2$d</a> has been created.'), $url, $issue->id));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_IssueCreate(null, $params);
        }
        $params = array_merge(
                              array('project' => $prj,
                                    'form' => $form,
                                    'page_title' => $title,
                                    'preview' => $preview,
                                    'issue' => new IDF_Issue(),
                                    ),
                              self::autoCompleteArrays($prj)
                              );
        if ($api == true) return $params;
        return Pluf_Shortcuts_RenderToResponse('idf/issues/create.html',
                                               $params, $request);
    }

    public $search_precond = array('IDF_Precondition::accessIssues');
    public function search($request, $match)
    {
        $query = !isset($request->REQUEST['q']) ? '' : $request->REQUEST['q'];
        return $this->doSearch($request, $query, 'open');
    }

    public $searchStatus_precond = array('IDF_Precondition::accessIssues');
    public function searchStatus($request, $match)
    {
        $query  = !isset($request->REQUEST['q']) ? '' : $request->REQUEST['q'];
        $status = in_array($match[2], array('open', 'closed')) ? $match[2] : 'open';
        return $this->doSearch($request, $query, $status);
    }

    public $searchLabel_precond = array('IDF_Precondition::accessIssues');
    public function searchLabel($request, $match)
    {
        $query  = !isset($request->REQUEST['q']) ? '' : $request->REQUEST['q'];
        $tag_id = intval($match[2]);
        $status = in_array($match[3], array('open', 'closed')) ? $match[3] : 'open';
        return $this->doSearch($request, $query, $status, $tag_id);
    }

    private function doSearch($request, $query, $status, $tag_id=null)
    {
        $prj = $request->project;
        if (trim($query) == '') {
            $url =  Pluf_HTTP_URL_urlForView('IDF_Views_Issue::index', array($prj->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $tag = null;
        if ($tag_id !== null) {
            $tag = Pluf_Shortcuts_GetObjectOr404('IDF_Tag', $tag_id);
        }

        $title = sprintf(__('Search issues - %s'), $query);
        if ($status === 'closed') {
            $title = sprintf(__('Search closed issues - %s'), $query);
        }

        // using Plufs ResultSet implementation here is inefficient, because
        // it makes a SELECT for each item and does not allow for further
        // filtering neither, so we just return the ids and filter by them
        // and other things in the next round
        $results = IDF_Search::mySearch($query, $prj, 'IDF_Issue');

        $issue_ids = array(0);
        foreach ($results as $result) {
            $issue_ids[] = $result['model_id'];
        }

        $otags = $prj->getTagIdsByStatus($status);
        if (count($otags) == 0) $otags[] = 0;
        $sql = new Pluf_SQL(
            'id IN ('.implode(',', $issue_ids).') '.
            'AND status IN ('.implode(', ', $otags).') '.
            ($tag_id !== null ? 'AND idf_tag_id='.$tag_id.' ' : '')
        );
        $model = new IDF_Issue();
        $issues = $model->getList(array('filter' => $sql->gen(), 'view' => 'join_tags'));

        // we unfortunately loose the original sort order,
        // so we manually have to apply it here again
        $sorted_issues = new ArrayObject();
        $filtered_issue_ids = array(0);
        foreach ($issue_ids as $issue_id) {
            foreach ($issues as $issue) {
                if ($issue->id != $issue_id)
                    continue;
                if (array_key_exists($issue_id, $sorted_issues))
                    continue;
                $sorted_issues[$issue_id] = $issue;
                $filtered_issue_ids[] = $issue_id;
            }
        }

        $pag = new Pluf_Paginator();
        $pag->class = 'recent-issues';
        $pag->items = $sorted_issues;
        $pag->item_extra_props = array(
            'project_m' => $prj,
            'shortname' => $prj->shortname,
            'current_user' => $request->user
        );
        $pag->summary = __('This table shows the found issues.');
        $pag->extra_classes = array('a-c', '', 'a-c', '');
        $pag->configure(array(
            'id' => __('Id'),
            array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
            array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
            array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
        ));
        // disable paginating
        $pag->items_per_page = PHP_INT_MAX;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);

        if ($tag_id === null) {
            $pag->action = array('IDF_Views_Issue::searchStatus',
                array($prj->shortname, $status),
                array('q'=> $query),
            );
        } else {
            $pag->action = array('IDF_Views_Issue::searchLabel',
                array($prj->shortname, $tag_id, $status),
                array('q'=> $query),
            );
        }

        // get stats about the issues
        $open = $prj->getIssueCountByStatus('open', $tag, $issue_ids);
        $closed = $prj->getIssueCountByStatus('closed', $tag, $issue_ids);

        // query the available tags for this search result
        $all_tags = $prj->getTagsByIssues($filtered_issue_ids);
        $grouped_tags = array();
        foreach ($all_tags as $atag) {
            // group by class
            if (!array_key_exists($atag->class, $grouped_tags)) {
                $grouped_tags[$atag->class] = array();
            }
            $grouped_tags[$atag->class][] = $atag;
        }

        $params = array(
            'page_title' => $title,
            'issues' => $pag,
            'query' => $query,
            'status' => $status,
            'open' => $open,
            'closed' => $closed,
            'tag' => $tag,
            'all_tags' => $grouped_tags,
        );

        return Pluf_Shortcuts_RenderToResponse('idf/issues/search.html', $params, $request);
    }

    public $view_precond = array('IDF_Precondition::accessIssues');
    public function view($request, $match)
    {
        $prj = $request->project;
        $issue = Pluf_Shortcuts_GetObjectOr404('IDF_Issue', $match[2]);
        $prj->inOr404($issue);
        $comments = $issue->get_comments_list(array('order' => 'id ASC'));
        $related_issues = $issue->getGroupedRelatedIssues(array('order' => 'other_issue ASC'));

        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                        array($prj->shortname, $issue->id));
        $title = Pluf_Template::markSafe(sprintf(__('Issue <a href="%1$s">%2$d</a>: %3$s'), $url, $issue->id, Pluf_esc($issue->summary)));
        $form = false; // The form is available only if logged in.
        $starred = false;
        $closed = in_array($issue->status, $prj->getTagIdsByStatus('closed'));
        $interested = $issue->get_interested_list();
        $preview = (isset($request->POST['preview'])) ?
            $request->POST['content'] : false;
        if (!$request->user->isAnonymous()) {
            $starred = Pluf_Model_InArray($request->user, $issue->get_interested_list());
            $params = array(
                            'project' => $prj,
                            'user' => $request->user,
                            'issue' => $issue,
                            );
            if ($request->method == 'POST') {
                $form = new IDF_Form_IssueUpdate(array_merge($request->POST,
                                                             $request->FILES),
                                                 $params);
                if (!isset($request->POST['preview']) && $form->isValid()) {
                    $issue = $form->save(); // Note, should return the
                                            // last comment
                    $issue->notify($request->conf, false);
                    $comments = $issue->get_comments_list(array('order' => 'id DESC'));
                    $url .= '#ic' . $comments[0]->id;
                    $request->user->setMessage(sprintf(__('<a href="%1$s">Issue %2$d</a> has been updated.'), $url, $issue->id));
                    return new Pluf_HTTP_Response_Redirect($url);
                }
            } else {
                $form = new IDF_Form_IssueUpdate(null, $params);
            }
        }

        // Search previous and next issue id
        $octags = $prj->getTagIdsByStatus(($closed) ? 'closed' : 'open');
        if (count($octags) == 0) $octags[] = 0;
        $sql_previous = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $octags).') AND id<%s',
                                     array($prj->id, $match[2])
                                    );
        $sql_next = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $octags).') AND id>%s',
                                     array($prj->id, $match[2])
                                );
        $previous_issue = Pluf::factory('IDF_Issue')->getList(array('filter' => $sql_previous->gen(),
                                                                    'order' => 'id DESC',
                                                                    'nb' => 1
                                                                   ));
        $next_issue = Pluf::factory('IDF_Issue')->getList(array('filter' => $sql_next->gen(),
                                                                'order' => 'id ASC',
                                                                'nb' => 1
                                                               ));
        $previous_issue_id = (isset($previous_issue[0])) ? $previous_issue[0]->id : 0;
        $next_issue_id = (isset($next_issue[0])) ? $next_issue[0]->id : 0;

        $arrays = self::autoCompleteArrays($prj);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/view.html',
                                               array_merge(
                                               array(
                                                     'issue' => $issue,
                                                     'comments' => $comments,
                                                     'form' => $form,
                                                     'starred' => $starred,
                                                     'page_title' => $title,
                                                     'closed' => $closed,
                                                     'preview' => $preview,
                                                     'interested' => $interested->count(),
                                                     'previous_issue_id' => $previous_issue_id,
                                                     'next_issue_id' => $next_issue_id,
                                                     'related_issues' => $related_issues,
                                                     ),
                                               $arrays),
                                               $request);
    }


    /**
     * Download a given attachment.
     */
    public $getAttachment_precond = array('IDF_Precondition::accessIssues');
    public function getAttachment($request, $match)
    {
        $prj = $request->project;
        $attach = Pluf_Shortcuts_GetObjectOr404('IDF_IssueFile', $match[2]);
        $prj->inOr404($attach->get_comment()->get_issue());
        $info = IDF_FileUtil::getMimeType($attach->filename);
        $mime = 'application/octet-stream';
        if (strpos($info[0], 'image/') === 0) {
            $mime = $info[0];
        }
        $res = new Pluf_HTTP_Response_File(Pluf::f('upload_issue_path').'/'.$attach->attachment,
                                           $mime);
        if ($mime == 'application/octet-stream') {
            $res->headers['Content-Disposition'] = 'attachment; filename="'.$attach->filename.'"';
        }
        return $res;
    }

    /**
     * View a given attachment.
     */
    public $viewAttachment_precond = array('IDF_Precondition::accessIssues');
    public function viewAttachment($request, $match)
    {
        $prj = $request->project;
        $attach = Pluf_Shortcuts_GetObjectOr404('IDF_IssueFile', $match[2]);
        $prj->inOr404($attach->get_comment()->get_issue());
        // If one cannot see the attachement, redirect to the
        // getAttachment view.
        $info = IDF_FileUtil::getMimeType($attach->filename);
        if (!IDF_FileUtil::isText($info)) {
            return $this->getAttachment($request, $match);
        }
        // Now we want to look at the file but with links back to the
        // issue.
        $file = IDF_FileUtil::highLight($info,
                                        file_get_contents(Pluf::f('upload_issue_path').'/'.$attach->attachment));
        $title = sprintf(__('View %s'), $attach->filename);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/attachment.html',
                                               array(
                                                     'attachment' => $attach,
                                                     'page_title' => $title,
                                                     'comment' => $attach->get_comment(),
                                                     'issue' => $attach->get_comment()->get_issue(),
                                                     'file' => $file,
                                                     ),
                                               $request);
    }

    /**
     * View list of issues for a given project with a given status.
     */
    public $listStatus_precond = array('IDF_Precondition::accessIssues');
    public function listStatus($request, $match)
    {
        $prj = $request->project;
        $status = $match[2];

        if (mb_strtolower($status) == 'open') {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::index',
                                            array($prj->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }

        $title = sprintf(__('%s Closed Issues'), (string) $prj);
        // Get stats about the issues
        $open = $prj->getIssueCountByStatus('open');
        $closed = $prj->getIssueCountByStatus('closed');
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the closed issues.');
        $otags = $prj->getTagIdsByStatus('closed');
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $otags).')', array($prj->id));
        $pag->action = array('IDF_Views_Issue::listStatus', array($prj->shortname, $status));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $pag->sort_link_title = true;
        $pag->extra_classes = array('a-c', '', 'a-c', '');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('id', 'status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/index.html',
                                               array('project' => $prj,
                                                     'page_title' => $title,
                                                     'open' => $open,
                                                     'closed' => $closed,
                                                     'issues' => $pag,
                                                     'cloud' => 'closed_issues',
                                                     ),
                                               $request);
    }

    /**
     * View list of issues for a given project with a given label.
     */
    public $listLabel_precond = array('IDF_Precondition::accessIssues');
    public function listLabel($request, $match)
    {
        $prj = $request->project;
        $tag = Pluf_Shortcuts_GetObjectOr404('IDF_Tag', $match[2]);
        $status = $match[3];
        if ($tag->project != $prj->id or !in_array($status, array('open', 'closed'))) {
            throw new Pluf_HTTP_Error404();
        }
        if ($status == 'open') {
            $title = sprintf(__('%1$s Issues with Label %2$s'), (string) $prj,
                             (string) $tag);
        } else {
            $title = sprintf(__('%1$s Closed Issues with Label %2$s'),
                             (string) $prj, (string) $tag);
        }
        // Get stats about the open/closed issues having this tag.
        $open = $prj->getIssueCountByStatus('open', $tag);
        $closed = $prj->getIssueCountByStatus('closed', $tag);
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->model_view = 'join_tags';
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = sprintf(__('This table shows the issues with label %s.'), (string) $tag);
        $otags = $prj->getTagIdsByStatus($status);
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND idf_tag_id=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $tag->id));
        $pag->action = array('IDF_Views_Issue::listLabel', array($prj->shortname, $tag->id, $status));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $pag->sort_link_title = true;
        $pag->extra_classes = array('a-c', '', 'a-c', '');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('id', 'status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        if (($open+$closed) > 0) {
            $completion = sprintf('%01.0f%%', (100*$closed)/((float) $open+$closed));
        } else {
            $completion = false;
        }
        return Pluf_Shortcuts_RenderToResponse('idf/issues/by-label.html',
                                               array('project' => $prj,
                                                     'completion' => $completion,
                                                     'page_title' => $title,
                                                     'open' => $open,
                                                     'label' => $tag,
                                                     'closed' => $closed,
                                                     'issues' => $pag,
                                                     ),
                                               $request);
    }

    /**
     * Renders a JSON string containing completed issue information
     * based on the queried / partial string
     */
    public $autoCompleteIssueList_precond = array('IDF_Precondition::accessIssues');
    public function autoCompleteIssueList($request, $match)
    {
        $prj = $request->project;
        $issue_id = !empty($match[2]) ? intval($match[2]) : 0;
        $query = trim($request->REQUEST['q']);
        $limit = !empty($request->REQUEST['limit']) ? intval($request->REQUEST['limit']) : 0;
        $limit = max(10, $limit);

        $issues = array();

        // empty search, return the most recently updated issues
        if (empty($query)) {
                $sql = new Pluf_SQL('project=%s', array($prj->id));
                $tmp = Pluf::factory('IDF_Issue')->getList(array(
                    'filter' => $sql->gen(),
                    'order' => 'modif_dtime DESC'
                ));
                $issues += $tmp->getArrayCopy();
        }
        else {
            // ID-based search
            if (is_numeric($query)) {
                $sql = 'project=%s AND CAST(id AS VARCHAR) LIKE %s';
                // MySQL can't cast to VARCHAR and a CAST to CHAR converts
                // the whole number, not just the first digit
                if (strtolower(Pluf::f('db_engine')) == 'mysql') {
                    $sql = 'project=%s AND CAST(id AS CHAR) LIKE %s';
                }
                $sql = new Pluf_SQL($sql, array($prj->id, $query.'%'));
                $tmp = Pluf::factory('IDF_Issue')->getList(array(
                    'filter' => $sql->gen(),
                    'order' => 'id ASC'
                ));
                $issues += $tmp->getArrayCopy();
            }

            // text-based search
            $res = new Pluf_Search_ResultSet(
                IDF_Search::mySearch($query, $prj, 'IDF_Issue')
            );
            foreach ($res as $issue)
                $issues[] = $issue;
        }

        // Autocomplete from jQuery UI works with JSON, this old one still
        // expects a parsable string; since we'd need to bump jQuery beyond
        // 1.2.6 for this to use as well, we're trying to cope with the old format.
        // see http://www.learningjquery.com/2010/06/autocomplete-migration-guide
        $out = '';
        $ids = array();
        foreach ($issues as $issue)
        {
            if ($issue->id == $issue_id)
                continue;

            if (in_array($issue->id, $ids))
                continue;

            if (--$limit < 0)
                break;

            $out .= str_replace('|', '&#124;', $issue->summary) .'|'.$issue->id."\n";
            $ids[] = $issue->id;
        }

        return new Pluf_HTTP_Response($out);
    }

    /**
     * Star/Unstar an issue.
     */
    public $star_precond = array('IDF_Precondition::accessIssues',
                                 'Pluf_Precondition::loginRequired');
    public function star($request, $match)
    {
        $prj = $request->project;
        $issue = Pluf_Shortcuts_GetObjectOr404('IDF_Issue', $match[2]);
        $prj->inOr404($issue);
        if ($request->method == 'POST') {
            $starred = Pluf_Model_InArray($request->user, $issue->get_interested_list());
            if ($starred) {
                $issue->delAssoc($request->user);
                $request->user->setMessage(__('The issue has been removed from your watch list.'));
            } else {
                $issue->setAssoc($request->user);
                $request->user->setMessage(__('The issue has been added to your watch list.'));
            }
        }
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                        array($prj->shortname, $issue->id));
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Create the autocomplete arrays for the little AJAX stuff.
     */
    public static function autoCompleteArrays($project)
    {
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $auto = array('auto_status' => '', 'auto_labels' => '');
        $auto_raw = array('auto_status' => '', 'auto_labels' => '');
        $st = $conf->getVal('labels_issue_open', IDF_Form_IssueTrackingConf::init_open);
        $st .= "\n".$conf->getVal('labels_issue_closed', IDF_Form_IssueTrackingConf::init_closed);
        $auto_raw['auto_status'] = $st;
        $auto_raw['auto_labels'] = $conf->getVal('labels_issue_predefined', IDF_Form_IssueTrackingConf::init_predefined);
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
        // Get the members/owners
        $m = $project->getMembershipData();
        $auto['_auto_owner'] = $m['members'];
        $auto['auto_owner'] = '';
        foreach ($m['owners'] as $owner) {
            if (!Pluf_Model_InArray($owner, $auto['_auto_owner'])) {
                $auto['_auto_owner'][] = $owner;
            }
        }
        foreach ($auto['_auto_owner'] as $owner) {
            $auto['auto_owner'] .= sprintf('{ name: "%s", to: "%s" }, ',
                                           Pluf_esc($owner),
                                           Pluf_esc($owner->login));
        }
        $auto['auto_owner'] = substr($auto['auto_owner'], 0, -2);
        unset($auto['_auto_owner']);
        // Get issue relations
        $r = $project->getRelationsFromConfig();
        $auto['auto_relation_types'] = '';
        foreach ($r as $rt) {
            $auto['auto_relation_types'] .= sprintf('{ name: "%s", to: "%s" }, ',
                                                    Pluf_esc(__($rt)), Pluf_esc($rt));
        }
        $auto['auto_relation_types'] = substr($auto['auto_relation_types'], 0, -2);
        return $auto;
    }
}

/**
 * When you access to your forge watch list, issue don't known
 * the project shortname.
 */
function IDF_Views_Issue_SummaryAndLabelsUnknownProject($field, $issue, $extra='')
{
    $shortname = $issue->get_project()->shortname;
    $issue->__set('shortname', $shortname);
    return IDF_Views_Issue_SummaryAndLabels ($field, $issue, $extra);
}

/**
 * Display the summary of an issue, then on a new line, display the
 * list of labels with a link to a view "by label only".
 *
 * The summary of the issue is linking to the issue.
 */
function IDF_Views_Issue_SummaryAndLabels($field, $issue, $extra='')
{
    $edit = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                     array($issue->shortname, $issue->id));
    $tags = array();
    foreach ($issue->get_tags_list() as $tag) {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::listLabel',
                                        array($issue->shortname, $tag->id, 'open'));
        $tags[] = sprintf('<a class="label" href="%s">%s</a>', $url, Pluf_esc((string) $tag));
    }
    $s = '';
    if (!$issue->current_user->isAnonymous() and
        Pluf_Model_InArray($issue->current_user, $issue->get_interested_list())) {
        $s = '<img style="vertical-align: text-bottom;" src="'.Pluf_Template_Tag_MediaUrl::url('/idf/img/star.png').'" alt="'.__('On your watch list.').'" /> ';
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="note">'.implode(', ', $tags).'</span>';
    }
    return $s.sprintf('<a href="%s">%s</a>', $edit, Pluf_esc($issue->summary)).$out;
}

/**
 * Display the status in the issue listings.
 *
 */
function IDF_Views_Issue_ShowStatus($field, $issue, $extra='')
{
    return Pluf_esc($issue->get_status()->name);
}


