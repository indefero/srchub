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
 * Update a project.
 *
 * A kind of merge of the member configuration and overview in the
 * project administration area.
 *
 */
class IDF_Form_Admin_ProjectUpdate extends Pluf_Form
{
    public $project = null;

    public function initFields($extra=array())
    {
        $this->project = $extra['project'];
        $members = $this->project->getMembershipData('string');
        $conf = $this->project->getConf();

        $this->fields['name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Name'),
                                            'initial' => $this->project->name,
                                            ));

        $this->fields['shortdesc'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Short description'),
                                            'help_text' => __('A one line description of the project.'),
                                            'initial' => $this->project->shortdesc,
                                            'widget_attrs' => array('size' => '35'),
                                            ));

        $this->fields['external_project_url'] = new Pluf_Form_Field_Varchar(
                                array('required' => false,
                                      'label' => __('External URL'),
                                      'widget_attrs' => array('size' => '35'),
                                      'initial' => $conf->getVal('external_project_url'),
        ));

        if ($this->project->getConf()->getVal('scm') == 'mtn') {
            $this->fields['mtn_master_branch'] = new Pluf_Form_Field_Varchar(
                                          array('required' => false,
                                                'label' => __('Master branch'),
                                                'initial' => $conf->getVal('mtn_master_branch'),
                                                'widget_attrs' => array('size' => '35'),
                                                'help_text' => __('This should be a world-wide unique identifier for your project. A reverse DNS notation like "com.my-domain.my-project" is a good idea.'),
                                                ));
        }

        $tags = $this->project->get_tags_list();
        for ($i=1;$i<7;$i++) {
            $initial = '';
            if (isset($tags[$i-1])) {
                if ($tags[$i-1]->class != 'Other') {
                    $initial = (string) $tags[$i-1];
                } else {
                    $initial = $tags[$i-1]->name;
                }
            }
            $this->fields['label'.$i] = new Pluf_Form_Field_Varchar(
                                    array('required' => false,
                                          'label' => __('Labels'),
                                          'initial' => $initial,
                                          'widget_attrs' => array(
                                          'maxlength' => 50,
                                          'size' => 20,
                                          ),
            ));
        }
        $this->fields['owners'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Project owners'),
                                            'initial' => $members['owners'],
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array('rows' => 5,
                                                                    'cols' => 40),
                                            ));
        $this->fields['members'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Project members'),
                                            'initial' => $members['members'],
                                            'widget_attrs' => array('rows' => 7,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            ));
    }

    public function clean_mtn_master_branch()
    {
        $mtn_master_branch = mb_strtolower($this->cleaned_data['mtn_master_branch']);
        if (!preg_match('/^([\w\d]+([-][\w\d]+)*)(\.[\w\d]+([-][\w\d]+)*)*$/',
                        $mtn_master_branch)) {
            throw new Pluf_Form_Invalid(__(
                'The master branch is empty or contains illegal characters, '.
                'please use only letters, digits, dashes and dots as separators.'
            ));
        }

        $sql = new Pluf_SQL('vkey=%s AND vdesc=%s AND project!=%s',
                            array('mtn_master_branch', $mtn_master_branch,
                                  (string)$this->project->id));
        $l = Pluf::factory('IDF_Conf')->getList(array('filter'=>$sql->gen()));
        if ($l->count() > 0) {
            throw new Pluf_Form_Invalid(__(
                'This master branch is already used. Please select another one.'
            ));
        }

        return $mtn_master_branch;
    }

    public function clean_owners()
    {
        return IDF_Form_MembersConf::checkBadLogins($this->cleaned_data['owners']);
    }

    public function clean_members()
    {
        return IDF_Form_MembersConf::checkBadLogins($this->cleaned_data['members']);
    }

    public function clean_external_project_url()
    {
        return IDF_Form_ProjectConf::checkWebURL($this->cleaned_data['external_project_url']);
    }

    public function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }

        // Add a tag for each label
        $tagids = array();
        for ($i=1;$i<7;$i++) {
            if (strlen($this->cleaned_data['label'.$i]) > 0) {
                if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                    list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                    list($class, $name) = array(trim($class), trim($name));
                } else {
                    $class = 'Other';
                    $name = trim($this->cleaned_data['label'.$i]);
                }
                $tag = IDF_Tag::addGlobal($name, $class);
                $tagids[] = $tag->id;
            }
        }
        $this->project->batchAssoc('IDF_Tag', $tagids);

        IDF_Form_MembersConf::updateMemberships($this->project,
                                                $this->cleaned_data);
        $this->project->membershipsUpdated();

        $this->project->name = $this->cleaned_data['name'];
        $this->project->shortdesc = $this->cleaned_data['shortdesc'];
        $this->project->update();

        $conf = $this->project->getConf();
        $keys = array('mtn_master_branch', 'external_project_url');
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->cleaned_data)) {
                if (!empty($this->cleaned_data[$key])) {
                    $conf->setVal($key, $this->cleaned_data[$key]);
                }
                else {
                    $conf->delVal($key);
                }
            }
        }
    }
}


