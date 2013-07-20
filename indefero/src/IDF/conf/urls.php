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

$ctl = array();
$base = Pluf::f('idf_base');

$ctl[] = array('regex' => '#^/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'index');

$ctl[] = array('regex' => '#^/projects/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'listProjects');

$ctl[] = array('regex' => '#^/projects/label/(\w+)/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'listProjectsByLabel');

$ctl[] = array('regex' => '#^/login/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'login',
               'name' => 'login_view');

$ctl[] = array('regex' => '#^/preferences/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'myAccount');

$ctl[] = array('regex' => '#^/dashboard/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'dashboard',
               'name' => 'idf_dashboard');

$ctl[] = array('regex' => '#^/dashboard/submitted/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'dashboard',
               'params' => false,
               'name' => 'idf_dashboard_submit');

$ctl[] = array('regex' => '#^/u/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'view');

$ctl[] = array('regex' => '#^/logout/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'logout');

$ctl[] = array('regex' => '#^/help/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'faq',
               'name' => 'idf_faq');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'home');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/logo/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'logo');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/timeline/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timeline');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/feed/timeline/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineFeed',
               'name' => 'idf_project_timeline_feed');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/feed/timeline/(\w+)/token/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineFeed',
               'name' => 'idf_project_timeline_feed_auth');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/timeline/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineCompat');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/feed/timeline/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineFeedCompat',
               'name' => 'idf_project_timeline_feed');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/feed/timeline/token/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'timelineFeedCompat',
               'name' => 'idf_project_timeline_feed_auth');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/summary/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'summary');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/search/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'search');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/search/status/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'searchStatus');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/search/label/(\d+)/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'searchLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(\d+)/star/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'star');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/status/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'listStatus');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/label/(\d+)/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/(.*)/(\w+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'userIssues');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/attachment/(\d+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'getAttachment');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/view/attachment/(\d+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'viewAttachment');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/watchlist/(\w+)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'watchList');

$ctl[] = array('regex' => '#^/watchlist/(\w+)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'forgeWatchList');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/issues/autocomplete/(\d*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Issue',
               'method' => 'autoCompleteIssueList');

// ---------- SCM ----------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/help/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'help');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/invalid/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'invalidRevision');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/disambiguate/([^/]+)/from/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'disambiguateRevision');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/tree/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'treeBase');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/tree/([^/]+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'tree');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/changes/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'changeLog');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/commit/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'commit');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/ddiff/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'downloadDiff');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/download/([^/]+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'download');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/file/([^/]+)/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'getFile');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/treerev/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source_Svn',
               'method' => 'treeRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/changesrev/$#',
               'base' => $base,
               'model' => 'IDF_Views_Source_Svn',
               'method' => 'changelogRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/source/repo/(.*)$#',
               'base' => $base,
               'model' => 'IDF_Views_Source',
               'method' => 'repository');

// ---------- WIKI -----------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'listPages');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/res/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'listResources');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'createPage');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/res/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'createResource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/search/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'search');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/label/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'listPagesWithLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/update/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'updatePage');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/res/update/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'updateResource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/delrev/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'deletePageRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/res/delrev/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'deleteResourceRev');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/doc/delete/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'deletePage');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/res/delete/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'deleteResource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/res/raw/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'rawResource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/page/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'viewPage');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/resource/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Wiki',
               'method' => 'viewResource');

// ---------- Downloads ------------------------------------

$ctl[] = array('regex' => '#^/help/archive-format/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'faqArchiveFormat');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/label/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'listLabel');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/get/(.+)$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'download');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/get/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'downloadById');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/create/archive/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'createFromArchive');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/downloads/(\d+)/delete/$#',
               'base' => $base,
               'model' => 'IDF_Views_Download',
               'method' => 'delete');

// ---------- CODE REVIEW --------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'index');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'view');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'create');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/review/getpatch/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Review',
               'method' => 'getPatch');


// ---------- ADMIN --------------------------------------

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'admin');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/issues/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminIssues');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/downloads/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminDownloads');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/wiki/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminWiki');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/source/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminSource');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/members/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminMembers');

$ctl[] = array('regex' => '#^/p/([\-\w]+)/admin/tabs/$#',
               'base' => $base,
               'model' => 'IDF_Views_Project',
               'method' => 'adminTabs');

// ---------- API ----------------------------------------

$ctl[] = array('regex' => '#^/help/api/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'faqApi');

$ctl[] = array('regex' => '#^/api/p/([\-\w]+)/issues/$#',
               'base' => $base,
               'model' => 'IDF_Views_Api',
               'method' => 'issuesIndex');

$ctl[] = array('regex' => '#^/api/p/([\-\w]+)/issues/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Api',
               'method' => 'issueCreate');

$ctl[] = array('regex' => '#^/api/$#',
               'base' => $base,
               'model' => 'IDF_Views_Api',
               'method' => 'projectIndex');

// ---------- FORGE ADMIN --------------------------------

$ctl[] = array('regex' => '#^/admin/forge/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'forge');

$ctl[] = array('regex' => '#^/admin/projects/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projects');

$ctl[] = array('regex' => '#^/admin/projects/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectUpdate');

$ctl[] = array('regex' => '#^/admin/projects/labels/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectLabels');

$ctl[] = array('regex' => '#^/admin/projects/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectCreate');

$ctl[] = array('regex' => '#^/admin/projects/(\d+)/delete/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'projectDelete');

$ctl[] = array('regex' => '#^/admin/users/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'users');

$ctl[] = array('regex' => '#^/admin/users/create/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'userCreate');

$ctl[] = array('regex' => '#^/admin/users/notvalid/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'usersNotValidated');

$ctl[] = array('regex' => '#^/admin/users/(\d+)/$#',
               'base' => $base,
               'model' => 'IDF_Views_Admin',
               'method' => 'userUpdate');

if (Pluf::f("mtn_usher_conf", null) !== null)
{
    $ctl[] = array('regex' => '#^/admin/usher/$#',
                   'base' => $base,
                   'model' => 'IDF_Views_Admin',
                   'method' => 'usher');

    $ctl[] = array('regex' => '#^/admin/usher/control/(.*)$#',
                   'base' => $base,
                   'model' => 'IDF_Views_Admin',
                   'method' => 'usherControl');

    $ctl[] = array('regex' => '#^/admin/usher/server/(.+)/control/(.+)$#',
                   'base' => $base,
                   'model' => 'IDF_Views_Admin',
                   'method' => 'usherServerControl');

    $ctl[] = array('regex' => '#^/admin/usher/server/(.+)/connections/$#',
                   'base' => $base,
                   'model' => 'IDF_Views_Admin',
                   'method' => 'usherServerConnections');
}

// ---------- UTILITY VIEWS -------------------------------

$ctl[] = array('regex' => '#^/register/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'register');

$ctl[] = array('regex' => '#^/register/k/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'registerConfirmation');

$ctl[] = array('regex' => '#^/register/ik/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'registerInputKey');

$ctl[] = array('regex' => '#^/password/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'passwordRecoveryAsk');

$ctl[] = array('regex' => '#^/password/ik/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'passwordRecoveryInputCode');

$ctl[] = array('regex' => '#^/password/k/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views',
               'method' => 'passwordRecovery');

$ctl[] = array('regex' => '#^/preferences/email/ik/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'changeEmailInputKey');

$ctl[] = array('regex' => '#^/preferences/email/ak/(.*)/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'changeEmailDo');

$ctl[] = array('regex' => '#^/preferences/email/(\d+)/delete/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'deleteMail');

$ctl[] = array('regex' => '#^/preferences/key/(\d+)/delete/$#',
               'base' => $base,
               'model' => 'IDF_Views_User',
               'method' => 'deleteKey');


return $ctl;
