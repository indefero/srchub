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
 * This script recalculates the "project activity" for all of the
 * forge's projects for the given date.
 * If no date is given, yesterday's date is used.
 *
 * This script should run once a day. You can configure its behaviour
 * with $cfg['activity_section_weights'] and $cfg['activity_lookback'].
 *
 * If the script runs more than once with the same date argument,
 * previously recorded project activity values are replaced with the
 * newly created ones.
 */

require dirname(__FILE__).'/../src/IDF/conf/path.php';
require 'Pluf.php';
Pluf::start(dirname(__FILE__).'/../src/IDF/conf/idf.php');

$date = new DateTime('yesterday');
if (count($_SERVER['argv']) > 1) {
    $date = new DateTime($_SERVER['argv'][1]);
}

echo 'recalculating project activity for '.$date->format('Y-m-d')."\n";
IDF_ActivityTaxonomy::recalculateTaxnomies($date);

