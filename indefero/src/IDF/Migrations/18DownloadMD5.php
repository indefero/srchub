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
 * Add the md5 column for the download model.
 */

function IDF_Migrations_18DownloadMD5_up($params=null)
{
    // Add the row
    $table = Pluf::factory('IDF_Upload')->getSqlTable();
    $sql = array();
    $sql['PostgreSQL'] = 'ALTER TABLE '.$table.' ADD COLUMN "md5" VARCHAR(32) DEFAULT \'\'';
    $sql['MySQL'] = 'ALTER TABLE '.$table.' ADD COLUMN `md5` VARCHAR(32) DEFAULT \'\'';
    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }
    $db->execute($sql[$engine]);
    
    // Process md5 of already uploaded file
    $files = Pluf::factory('IDF_Upload')->getList();
    foreach ($files as $f) {
        $f->md5 = md5_file (Pluf::f('upload_path') . '/' . $f->get_project()->shortname . '/files/' . $f->file);
        $f->update();
    }
}

function IDF_Migrations_18DownloadMD5_down($params=null)
{
    // Remove the row
    $table = Pluf::factory('IDF_Upload')->getSqlTable();
    $sql = array();
    $sql['PostgreSQL'] = 'ALTER TABLE '.$table.' DROP COLUMN "md5"';
    $sql['MySQL'] = 'ALTER TABLE '.$table.' DROP COLUMN `md5`';
    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }
    $db->execute($sql[$engine]);
}
