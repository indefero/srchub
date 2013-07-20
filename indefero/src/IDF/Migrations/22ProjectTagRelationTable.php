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

function IDF_Migrations_22ProjectTagRelationTable_up($params=null)
{
    $db = Pluf::db();
    $table = $db->pfx.'idf_project_idf_tag_assoc';
    if (!in_array($db->engine, array('MySQL', 'PostgreSQL'))) {
        throw new Exception('unsupported engine '.$engine);
    }

    $intro = new Pluf_DB_Introspect($db);
    if (in_array($table, $intro->listTables())) {
        echo '21 skipping up migration - table already exists'."\n";
        return;
    }

    $schema = Pluf::factory('Pluf_DB_Schema_'.$db->engine, $db);
    $sql = $schema->getSqlCreate(new IDF_Project());
    $db->execute($sql[$table]);
}

function IDF_Migrations_22ProjectTagRelationTable_down($params=null)
{
    $db = Pluf::db();
    $table = $db->pfx.'idf_project_idf_tag_assoc';
    if (!in_array($db->engine, array('MySQL', 'PostgreSQL'))) {
        throw new Exception('unsupported engine '.$engine);
    }

    $intro = new Pluf_DB_Introspect($db);
    if (!in_array($table, $intro->listTables())) {
        echo '22 skipping down migration - table does not exist'."\n";
        return;
    }

    $schema = Pluf::factory('Pluf_DB_Schema_'.$db->engine, $db);
    $sql = $schema->getSqlDelete(new IDF_Project());
    $db->execute($sql[$table]);
}
