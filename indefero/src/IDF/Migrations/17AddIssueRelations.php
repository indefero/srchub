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
 * Add the new IDF_IssueRelation model.
 *
 */

function IDF_Migrations_17AddIssueRelations_up($params=null)
{
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    $schema->model = new IDF_IssueRelation();
    $schema->createTables();

    // change the serialization format for added / removed labels in IDF_IssueComment
    $comments = Pluf::factory('IDF_IssueComment')->getList();
    foreach ($comments as $comment) {
        if (!isset($comment->changes['lb'])) continue;
        $changes = $comment->changes;
        $adds = $removals = array();
        foreach ($comment->changes['lb'] as $lb) {
            if (substr($lb, 0, 1) == '-')
                $removals[] = substr($lb, 1);
            else
                $adds[] = $lb;
        }
        $changes['lb'] = array();
        if (count($adds) > 0)
            $changes['lb']['add'] = $adds;
        if (count($removals) > 0)
            $changes['lb']['rem'] = $removals;
        $comment->changes = $changes;
        $comment->update();
    }
}

function IDF_Migrations_17AddIssueRelations_down($params=null)
{
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    $schema->model = new IDF_IssueRelation();
    $schema->dropTables();

    // change the serialization format for added / removed labels in IDF_IssueComment
    $comments = Pluf::factory('IDF_IssueComment')->getList();
    foreach ($comments as $comment) {
        $changes = $comment->changes;
        if (empty($changes))
            continue;
        if (isset($changes['lb'])) {
            $labels = array();
            foreach ($changes['lb'] as $type => $lbs) {
                if (!is_array($lbs)) {
                    $labels[] = $lbs;
                    continue;
                }
                foreach ($lbs as $lb) {
                    $labels[] = ($type == 'rem' ? '-' : '') . $lb;
                }
            }
            $changes['lb'] = $labels;
        }
        // while we're at it, remove any 'rel' changes
        unset($changes['rel']);
        $comment->changes = $changes;
        $comment->update();
    }
}

