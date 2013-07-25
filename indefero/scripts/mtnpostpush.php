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

/**
 * This script will send the notifications after a push in your 
 * repository.
 */

require dirname(__FILE__).'/../src/IDF/conf/path.php';
require 'Pluf.php';
Pluf::start(dirname(__FILE__).'/../src/IDF/conf/idf.php');
Pluf_Dispatcher::loadControllers(Pluf::f('idf_views'));

/**
 * [signal]
 *
 * mtnpostpush.php::run
 *
 * [sender]
 *
 * mtnpostpush.php
 *
 * [description]
 *
 * This signal allows an application to perform a set of tasks
 * after a push to a monotone repository.
 *
 * [parameters]
 *
 * array('project' => 'name-of-the-project',
 *       'revisions' => array('123abc...', '456def...', ...));
 *
 */

fwrite(STDERR, "waiting for revisions on STDIN...\n");
$stdin = file_get_contents('php://stdin');

$params = array('project' => $argv[1],
                'revisions' => explode("\n", chop($stdin)));
Pluf_Signal::send('mtnpostpush.php::run', 'mtnpostpush.php', $params);



