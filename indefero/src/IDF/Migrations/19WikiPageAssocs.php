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

function IDF_Migrations_19WikiPageAssocs_up($params=null)
{
    $engine = Pluf::f('db_engine');
    if (!in_array($engine, array('MySQL', 'PostgreSQL'))) {
        throw new Exception('unsupported engine '.$engine);
    }

    $db = Pluf::db();
    $intro = new Pluf_DB_Introspect($db);
    if (in_array($db->pfx.'idf_tag_idf_wiki_page_assoc', $intro->listTables())) {
        echo '19 skipping up migration - relation table has correct name already'."\n";
        return;
    }

    $db->execute('ALTER TABLE '.$db->pfx.'idf_tag_idf_wikipage_assoc RENAME TO '.$db->pfx.'idf_tag_idf_wiki_page_assoc');
    $db->execute('ALTER TABLE '.$db->pfx.'idf_wikipage_pluf_user_assoc RENAME TO '.$db->pfx.'idf_wiki_page_pluf_user_assoc');

    if ($engine === 'PostgreSQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tag_idf_wiki_page_assoc RENAME COLUMN idf_wikipage_id TO idf_wiki_page_id');
        $db->execute('ALTER TABLE '.$db->pfx.'idf_wiki_page_pluf_user_assoc RENAME COLUMN idf_wikipage_id TO idf_wiki_page_id');
    } else if ($engine === 'MySQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tag_idf_wiki_page_assoc CHANGE idf_wikipage_id idf_wiki_page_id MEDIUMINT NOT NULL');
        $db->execute('ALTER TABLE '.$db->pfx.'idf_wiki_page_pluf_user_assoc CHANGE idf_wikipage_id idf_wiki_page_id MEDIUMINT NOT NULL');
    }
}

function IDF_Migrations_19WikiPageAssocs_down($params=null)
{
    $engine = Pluf::f('db_engine');
    if (!in_array($engine, array('MySQL', 'PostgreSQL'))) {
        throw new Exception('unsupported engine '.$engine);
    }

    $db = Pluf::db();
    $intro = new Pluf_DB_Introspect($db);
    if (in_array($db->pfx.'idf_tag_idf_wikipage_assoc', $intro->listTables())) {
        echo '19 skipping down migration - relation table has correct name already'."\n";
        return;
    }

    $db->execute('ALTER TABLE '.$db->pfx.'idf_tag_idf_wiki_page_assoc RENAME TO '.$db->pfx.'idf_tag_idf_wikipage_assoc');
    $db->execute('ALTER TABLE '.$db->pfx.'idf_wiki_page_pluf_user_assoc RENAME TO '.$db->pfx.'idf_wikipage_pluf_user_assoc');

    if ($engine === 'PostgreSQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tag_idf_wikipage_assoc RENAME COLUMN idf_wiki_page_id TO idf_wikipage_id');
        $db->execute('ALTER TABLE '.$db->pfx.'idf_wikipage_pluf_user_assoc RENAME COLUMN idf_wiki_page_id TO idf_wikipage_id');
    } else if ($engine === 'MySQL') {
        $db->execute('ALTER TABLE '.$db->pfx.'idf_tag_idf_wikipage_assoc CHANGE idf_wiki_page_id idf_wikipage_id MEDIUMINT NOT NULL');
        $db->execute('ALTER TABLE '.$db->pfx.'idf_wikipage_pluf_user_assoc CHANGE idf_wiki_page_id idf_wikipage_id MEDIUMINT NOT NULL');
    }
}
