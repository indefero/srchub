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
 * Class that calculates the activity value for all projects on a
 * specific date and time.
 *
 * We do this by counting adds or updates of database objects in
 * the particular section (according to the timeline) and relate this
 * value to the overall activity of a section in the forge.
 *
 * To illustrate the behaviour, a simple example could be a forge with
 * only two projects that both have only issue tracking enabled.
 * The first project created or updated 10 tickets during the past period,
 * the other 20. The activity index for the first should therefor be
 * calculated as 0.33 and the second as 0.66.
 * Note that this simple example doesn't take activity in other
 * sections into account, so the the total activity of all projects
 * for a certain time period might add up to more than 1.0.
 *
 * @author tommyd
 */
class IDF_ActivityTaxonomy
{
    public static function recalculateTaxnomies(DateTime $date)
    {
        $sectionWeights = Pluf::f('activity_section_weights', null);
        $lookback = Pluf::f('activity_lookback', null);
        if ($sectionWeights === null || $lookback === null) {
            throw new LogicException('activity configuration is missing in idf.php');
        }

        //
        // query and normalize the section weights
        //
        $allWeights = array_sum($sectionWeights);
        if ($allWeights == 0) {
            throw new LogicException('the sum of all "activity_section_weights" must not be 0');
        }
        foreach ($sectionWeights as $section => $weight) {
            $sectionWeights[$section] = $weight / (float) $allWeights;
        }

        //
        // determine the date boundaries
        //
        if ($lookback < 1) {
            throw new LogicException('lookback must be greater or equal to 1');
        }
        $dateCopy = new DateTime();
        $dateCopy->setTimestamp($date->getTimestamp());
        $dateBoundaries = array(
            $dateCopy->format('Y-m-d 23:59:59'),
            $dateCopy->sub(new DateInterval('P'.$lookback.'D'))->format('Y-m-d 00:00:00')
        );

        //
        // now recalculate the values for all projects
        //
        $projects = Pluf::factory('IDF_Project')->getList();
        foreach ($projects as $project) {
            self::recalculateTaxonomy($date, $project, $dateBoundaries, $sectionWeights);
        }
    }

    private static function recalculateTaxonomy(DateTime $date, IDF_Project $project, array $dateBoundaries, array $sectionWeights)
    {
        $conf = new IDF_Conf();
        $conf->setProject($project);

        $sectionClasses = array(
            'source'    => array('IDF_Commit'),
            'issues'    => array('IDF_Issue'),
            'wiki'      => array('IDF_Wiki_Page', 'IDF_Wiki_Resource'),
            'review'    => array('IDF_Review'),
            'downloads' => array('IDF_Upload')
        );

        $value = 0;
        foreach ($sectionWeights as $section => $weight) {
            // skip closed / non-existant sections
            if ($conf->getVal($section.'_access_rights') === 'none')
                continue;

            if (!array_key_exists($section, $sectionClasses))
                continue;

            $sectionValue = self::calculateActivityValue(
                $dateBoundaries, $sectionClasses[$section], $project->id);
            $value = ((1 - $weight) * $value) + ($weight * $sectionValue);
        }

        echo "project {$project->name} has an activity value of $value\n";

        $sql = new Pluf_SQL('project=%s AND date=%s', array($project->id, $date->format('Y-m-d')));
        $activity = Pluf::factory('IDF_ProjectActivity')->getOne(array('filter' => $sql->gen()));

        if ($activity == null) {
            $activity = new IDF_ProjectActivity();
            $activity->project = $project;
            $activity->date = $date->format('Y-m-d');
            $activity->value = $value;
            $activity->create();
        } else {
            $activity->value = $value;
            $activity->update();
        }
    }

    private static function calculateActivityValue(array $dateBoundaries, array $classes, $projectId)
    {
        $allCount = self::countActivityFor($dateBoundaries, $classes);
        if ($allCount == 0) return 0;
        $prjCount = self::countActivityFor($dateBoundaries, $classes, $projectId);
        return $prjCount / (float) $allCount;
    }

    private static function countActivityFor(array $dateBoundaries, array $classes, $projectId = null)
    {
        static $cache = array();
        $argIdent = md5(serialize(func_get_args()));
        if (array_key_exists($argIdent, $cache)) {
            return $cache[$argIdent];
        }

        $cache[$argIdent] = 0;
        list($higher, $lower) = $dateBoundaries;
        $db = Pluf::db();
        $classes_esc = array();
        foreach ($classes as $class) {
            $classes_esc[] = $db->esc($class);
        }
        $sql = new Pluf_SQL('model_class IN ('.implode(',', $classes_esc).') '.
                            'AND creation_dtime >= %s AND creation_dtime <= %s',
                         array($lower, $higher));

        if ($projectId !== null) {
            $sql->SAnd(new Pluf_SQL('project=%s', array($projectId)));
        }

        $cache[$argIdent] = Pluf::factory('IDF_Timeline')->getCount(array('filter' => $sql->gen()));

        return $cache[$argIdent];
    }
}