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
 * Update a documentation page.
 *
 * This add a corresponding revision.
 *
 */
class IDF_Form_WikiResourceUpdate extends Pluf_Form
{
    public $user = null;
    public $project = null;
    public $page = null;
    public $show_full = false;


    public function initFields($extra=array())
    {
        $this->resource = $extra['resource'];
        $this->user = $extra['user'];
        $this->project = $extra['project'];

        $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                        'label' => __('Description'),
                                        'help_text' => __('This one line description is displayed in the list of resources.'),
                                        'initial' => $this->resource->summary,
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

        $this->fields['comment'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Comment'),
                                            'help_text' => __('One line to describe the changes you made.'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
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

        list($mimeType, , $extension) = IDF_FileUtil::getMimeType($this->getTempUploadPath().$this->cleaned_data['file']);
        if ($this->resource->mime_type != $mimeType) {
            throw new Pluf_Form_Invalid(sprintf(
                __('The mime type of the uploaded file "%1$s" does not match the mime type of this resource "%2$s"'),
                $mimeType, $this->resource->mime_type
            ));
        }
        $this->cleaned_data['fileext'] = $extension;

        if (md5_file($this->getTempUploadPath().$this->cleaned_data['file']) ===
            md5_file($this->resource->get_current_revision()->getFilePath())) {
            throw new Pluf_Form_Invalid(__('The current version of the resource and the uploaded file are equal.'));
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

        $this->resource->summary = trim($this->cleaned_data['summary']);
        $this->resource->update();

        // add the new revision
        $rev = new IDF_Wiki_ResourceRevision();
        $rev->wikiresource = $this->resource;
        $rev->submitter = $this->user;
        $rev->summary = $this->cleaned_data['comment'];
        $rev->filesize = filesize($tempFile);
        $rev->fileext = $this->cleaned_data['fileext'];
        $rev->create();

        $finalFile = $rev->getFilePath();
        if (!is_dir(dirname($finalFile))) {
            @unlink($tempFile);
            $rev->delete();
            throw new Exception('resource path does not exist');
        }

        if (!@rename($tempFile, $finalFile)) {
            @unlink($tempFile);
            $rev->delete();
            throw new Exception('could not move resource to final location');
        }

        return $this->resource;
    }

    private function getTempUploadPath()
    {
        return Pluf::f('upload_path').'/'.$this->project->shortname.'/wiki/temp/';
    }
}
