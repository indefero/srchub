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
 * Models the activity value for a project and a given date
 *
 * @author tommyd
 */
class IDF_ProjectActivity extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_projectactivities';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true,
                                  ),
                            'project' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Project',
                                  'blank' => false,
                                  'verbose' => __('project'),
                                  'relate_name' => 'activities',
                                  ),
                            'date' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => false,
                                  'verbose' => __('date'),
                                  ),
                            'value' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Float',
                                  'blank' => false,
                                  'verbose' => __('value'),
                                  'default' => 0,
                                  ),
        );
    }

    function postSave($create=false)
    {
        $prj = $this->get_project();
        $sql = new Pluf_SQL('project=%s', array($prj->id));
        $list = Pluf::factory('IDF_ProjectActivity')->getList(array('filter' => $sql->gen(), 'order' => 'date desc'));
        if (count($list) > 0 && $prj->current_activity != $list[0]->id) {
            $prj->current_activity = $list[0];
            $prj->update();
        }
    }
}
