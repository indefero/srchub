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

function IDF_Migrations_21WikiPageRevisionName_up($params=null)
{
    $engine = Pluf::f('db_engine');
    if (!in_array($engine, array('MySQL', 'PostgreSQL'))) {
        throw new Exception('unsupported engine '.$engine);
    }

    $db = Pluf::db();
    $intro = new Pluf_DB_Introspect($db);
    if (in_array($db->pfx.'idf_wikipagerevs', $intro->listTables())) {
        echo '21 skipping up migration - table has correct name already'."\n";
        return;
    }

    $db->execute('ALTER TABLE '.$db->pfx.'idf_wikirevisions RENAME TO '.$db->pfx.'idf_wikipagerevs');
    $db->execute("UPDATE ".$db->pfx."idf_timeline SET model_class='IDF_Wiki_Page' WHERE model_class LIKE 'IDF_WikiPage'");
    $db->execute("UPDATE ".$db->pfx."idf_timeline SET model_class='IDF_Wiki_PageRevision' WHERE model_class LIKE 'IDF_WikiRevision'");
    $db->execute("UPDATE ".$db->pfx."idf_search_occs SET model_class='IDF_Wiki_Page' WHERE model_class LIKE 'IDF_WikiPage'");
    $db->execute("UPDATE ".$db->pfx."idf_search_occs SET model_class='IDF_Wiki_PageRevision' WHERE model_class LIKE 'IDF_WikiRevision'");
    $db->execute("UPDATE ".$db->pfx."pluf_search_stats SET model_class='IDF_Wiki_Page' WHERE model_class LIKE 'IDF_WikiPage'");
    $db->execute("UPDATE ".$db->pfx."pluf_search_stats SET model_class='IDF_Wiki_PageRevision' WHERE model_class LIKE 'IDF_WikiRevision'");
}

function IDF_Migrations_21WikiPageRevisionName_down($params=null)
{
    $engine = Pluf::f('db_engine');
    if (!in_array($engine, array('MySQL', 'PostgreSQL'))) {
        throw new Exception('unsupported engine '.$engine);
    }

    $db = Pluf::db();
    $intro = new Pluf_DB_Introspect($db);
    if (in_array($db->pfx.'idf_wikirevisions', $intro->listTables())) {
        echo '21 skipping down migration - table has correct name already'."\n";
        return;
    }

    $db->execute('ALTER TABLE '.$db->pfx.'idf_wikipagerevs RENAME TO '.$db->pfx.'idf_wikirevisions');
    $db->execute("UPDATE ".$db->pfx."idf_timeline SET model_class='IDF_WikiPage' WHERE model_class LIKE 'IDF_Wiki_Page'");
    $db->execute("UPDATE ".$db->pfx."idf_timeline SET model_class='IDF_WikiRevision' WHERE model_class LIKE 'IDF_Wiki_PageRevision'");
    $db->execute("UPDATE ".$db->pfx."idf_search_occs SET model_class='IDF_WikiPage' WHERE model_class LIKE 'IDF_Wiki_Page'");
    $db->execute("UPDATE ".$db->pfx."idf_search_occs SET model_class='IDF_WikiRevision' WHERE model_class LIKE 'IDF_Wiki_PageRevision'");
    $db->execute("UPDATE ".$db->pfx."pluf_search_stats SET model_class='IDF_WikiPage' WHERE model_class LIKE 'IDF_Wiki_Page'");
    $db->execute("UPDATE ".$db->pfx."pluf_search_stats SET model_class='IDF_WikiRevision' WHERE model_class LIKE 'IDF_Wiki_PageRevision'");
}
