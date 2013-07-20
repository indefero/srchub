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
 * Create a new resource.
 *
 * This create a new resource and the corresponding revision.
 *
 */
class IDF_Form_WikiResourceCreate extends Pluf_Form
{
    public $user = null;
    public $project = null;
    public $show_full = false;

    public function initFields($extra=array())
    {
        $this->project = $extra['project'];
        $this->user = $extra['user'];
        $initname = (!empty($extra['name'])) ? $extra['name'] : __('ResourceName');

        $this->fields['title'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Resource title'),
                                            'initial' => $initname,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            'help_text' => __('The resource name must contains only letters, digits and the dash (-) character.'),
                                            ));
        $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Description'),
                                            'help_text' => __('This one line description is displayed in the list of resources.'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));

        $this->fields['file'] = new Pluf_Form_Field_File(
                                     array('required' => true,
                                           'label' => __('File'),
                                           'initial' => '',
                                           'max_size' => Pluf::f('max_upload_size', 2097152),
                                           'move_function_params' => array('upload_path' => $this->getTempUploadPath(),
                                           'upload_path_create' => true,
                                           'upload_overwrite' => true),
                                           ));
    }

    public function clean_title()
    {
        $title = $this->cleaned_data['title'];
        if (preg_match('/[^a-zA-Z0-9\-]/', $title)) {
            throw new Pluf_Form_Invalid(__('The title contains invalid characters.'));
        }
        $sql = new Pluf_SQL('project=%s AND title=%s',
                            array($this->project->id, $title));
        $resources = Pluf::factory('IDF_Wiki_Resource')->getList(array('filter'=>$sql->gen()));
        if ($resources->count() > 0) {
            throw new Pluf_Form_Invalid(__('A resource with this title already exists.'));
        }
        return $title;
    }

    public function clean_file()
    {
        // FIXME: we do the same in IDF_Form_Upload and a couple of other places as well
        $extra = strtolower(implode('|', explode(' ', Pluf::f('idf_extra_upload_ext'))));
        if (strlen($extra)) $extra .= '|';
        if (!preg_match('/\.('.$extra.'png|jpg|jpeg|gif|bmp|psd|tif|aiff|asf|avi|bz2|css|doc|eps|gz|jar|mdtext|mid|mov|mp3|mpg|ogg|pdf|ppt|ps|qt|ra|ram|rm|rtf|sdd|sdw|sit|sxi|sxw|swf|tgz|txt|wav|xls|xml|war|wmv|zip)$/i', $this->cleaned_data['file'])) {
            @unlink($this->getTempUploadPath().$this->cleaned_data['file']);
            throw new Pluf_Form_Invalid(__('For security reasons, you cannot upload a file with this extension.'));
        }
        return $this->cleaned_data['file'];
    }

    /**
     * If we have uploaded a file, but the form failed remove it.
     *
     */
    function failed()
    {
        if (!empty($this->cleaned_data['file'])
            and file_exists($this->getTempUploadPath().$this->cleaned_data['file'])) {
            @unlink($this->getTempUploadPath().$this->cleaned_data['file']);
        }
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return Object Model with data set from the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }

        $tempFile = $this->getTempUploadPath().$this->cleaned_data['file'];
        list($mimeType, , $extension) = IDF_FileUtil::getMimeType($tempFile);

        // create the resource
        $resource = new IDF_Wiki_Resource();
        $resource->project = $this->project;
        $resource->submitter = $this->user;
        $resource->summary = trim($this->cleaned_data['summary']);
        $resource->title = trim($this->cleaned_data['title']);
        $resource->mime_type = $mimeType;
        $resource->create();

        // add the first revision
        $rev = new IDF_Wiki_ResourceRevision();
        $rev->wikiresource = $resource;
        $rev->submitter = $this->user;
        $rev->summary = __('Initial resource creation');
        $rev->filesize = filesize($tempFile);
        $rev->fileext = $extension;
        $rev->create();

        $finalFile = $rev->getFilePath();
        if (!@mkdir(dirname($finalFile), 0755, true)) {
            @unlink($tempFile);
            $rev->delete();
            $resource->delete();
            throw new Exception('could not create final resource path');
        }

        if (!@rename($tempFile, $finalFile)) {
            @unlink($tempFile);
            $rev->delete();
            $resource->delete();
            throw new Exception('could not move resource to final location');
        }

        return $resource;
    }

    private function getTempUploadPath()
    {
        return Pluf::f('upload_path').'/'.$this->project->shortname.'/wiki/temp/';
    }
}
