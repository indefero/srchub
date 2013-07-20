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
 * Upload and process an archive file.
 *
 */
class IDF_Form_UploadArchive extends Pluf_Form
{
    public $user = null;
    public $project = null;
    private $archiveHelper = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->project = $extra['project'];

        $this->fields['archive'] = new Pluf_Form_Field_File(
                                      array('required' => true,
                                            'label' => __('Archive file'),
                                            'initial' => '',
                                            'max_size' => Pluf::f('max_upload_archive_size', 20971520),
                                            'move_function_params' => array(
                                                'upload_path' => Pluf::f('upload_path').'/'.$this->project->shortname.'/archives',
                                                'upload_path_create' => true,
                                                'upload_overwrite' => true,
                                            )));
    }


    public function clean_archive()
    {
        $this->archiveHelper = new IDF_Form_UploadArchiveHelper(
            Pluf::f('upload_path').'/'.$this->project->shortname.'/archives/'.$this->cleaned_data['archive']);

        // basic archive validation
        $this->archiveHelper->validate();

        // extension validation
        $fileNames = $this->archiveHelper->getEntryNames();
        foreach ($fileNames as $fileName) {
            $extra = strtolower(implode('|', explode(' ', Pluf::f('idf_extra_upload_ext'))));
            if (strlen($extra)) $extra .= '|';
            if (!preg_match('/\.('.$extra.'png|jpg|jpeg|gif|bmp|psd|tif|aiff|asf|avi|bz2|css|doc|eps|gz|jar|mdtext|mid|mov|mp3|mpg|ogg|pdf|ppt|ps|qt|ra|ram|rm|rtf|sdd|sdw|sit|sxi|sxw|swf|tgz|txt|wav|xls|xml|war|wmv|zip)$/i', $fileName)) {
                @unlink(Pluf::f('upload_path').'/'.$this->project->shortname.'/files/'.$this->cleaned_data['archive']);
                throw new Pluf_Form_Invalid(sprintf(__('For security reasons, you cannot upload a file (%s) with this extension.'), $fileName));
            }
        }

        // label and file name validation
        $conf = new IDF_Conf();
        $conf->setProject($this->project);
        $onemax = array();
        foreach (explode(',', $conf->getVal('labels_download_one_max', IDF_Form_UploadConf::init_one_max)) as $class) {
            if (trim($class) != '') {
                $onemax[] = mb_strtolower(trim($class));
            }
        }

        foreach ($fileNames as $fileName) {
            $meta = $this->archiveHelper->getMetaData($fileName);
            $count = array();
            foreach ($meta['labels'] as $label) {
                $label = trim($label);
                if (strpos($label, ':') !== false) {
                    list($class, $name) = explode(':', $label, 2);
                    list($class, $name) = array(mb_strtolower(trim($class)),
                    trim($name));
                } else {
                    $class = 'other';
                    $name = $label;
                }
                if (!isset($count[$class])) $count[$class] = 1;
                else $count[$class] += 1;
                if (in_array($class, $onemax) and $count[$class] > 1) {
                    throw new Pluf_Form_Invalid(
                       sprintf(__('You cannot provide more than label from the %1$s class to a download (%2$s).'), $class, $name)
                    );
                }
            }

            $sql = new Pluf_SQL('file=%s AND project=%s', array($fileName, $this->project->id));
            $upload = Pluf::factory('IDF_Upload')->getOne(array('filter' => $sql->gen()));

            $meta = $this->archiveHelper->getMetaData($fileName);
            if ($upload != null && $meta['replaces'] !== $fileName) {
                throw new Pluf_Form_Invalid(
                    sprintf(__('A file with the name "%s" has already been uploaded and is not marked to be replaced.'), $fileName));
            }
        }

        return $this->cleaned_data['archive'];
    }

    /**
     * If we have uploaded a file, but the form failed remove it.
     *
     */
    function failed()
    {
        if (!empty($this->cleaned_data['archive'])
            and file_exists(Pluf::f('upload_path').'/'.$this->project->shortname.'/archives/'.$this->cleaned_data['archive'])) {
            @unlink(Pluf::f('upload_path').'/'.$this->project->shortname.'/archives/'.$this->cleaned_data['archive']);
        }
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }

        $uploadDir = Pluf::f('upload_path').'/'.$this->project->shortname.'/files/';
        $fileNames = $this->archiveHelper->getEntryNames();

        foreach ($fileNames as $fileName) {
            $meta = $this->archiveHelper->getMetaData($fileName);

            // add a tag for each label
            $tags = array();
            foreach ($meta['labels'] as $label) {
                $label = trim($label);
                if (strlen($label) > 0) {
                    if (strpos($label, ':') !== false) {
                        list($class, $name) = explode(':', $label, 2);
                        list($class, $name) = array(trim($class), trim($name));
                    } else {
                        $class = 'Other';
                        $name = $label;
                    }
                    $tags[] = IDF_Tag::add($name, $this->project, $class);
                }
            }

            // process a possible replacement
            if (!empty($meta['replaces'])) {
                $sql = new Pluf_SQL('file=%s AND project=%s', array($meta['replaces'], $this->project->id));
                $oldUpload = Pluf::factory('IDF_Upload')->getOne(array('filter' => $sql->gen()));

                if ($oldUpload) {
                    if ($meta['replaces'] === $fileName) {
                        $oldUpload->delete();
                    } else {
                        $tags = $this->project->getTagsFromConfig('labels_download_predefined',
                                                                  IDF_Form_UploadConf::init_predefined);
                        // the deprecate tag is - by definition - always the last one
                        $deprecatedTag = array_pop($tags);
                        $oldUpload->setAssoc($deprecatedTag);
                    }
                }
            }

            // extract the file
            $this->archiveHelper->extract($fileName, $uploadDir);

            // create the upload
            $upload = new IDF_Upload();
            $upload->project = $this->project;
            $upload->submitter = $this->user;
            $upload->summary = trim($meta['summary']);
            $upload->changelog = trim($meta['description']);
            $upload->file = $fileName;
            $upload->filesize = filesize($uploadDir.$fileName);
            $upload->downloads = 0;
            $upload->create();
            foreach ($tags as $tag) {
                $upload->setAssoc($tag);
            }

            // send the notification
            $upload->notify($this->project->getConf());
            /**
             * [signal]
             *
             * IDF_Upload::create
             *
             * [sender]
             *
             * IDF_Form_Upload
             *
             * [description]
             *
             * This signal allows an application to perform a set of tasks
             * just after the upload of a file and after the notification run.
             *
             * [parameters]
             *
             * array('upload' => $upload);
             *
             */
            $params = array('upload' => $upload);
            Pluf_Signal::send('IDF_Upload::create', 'IDF_Form_Upload',
                              $params);
        }

        // finally unlink the uploaded archive
        @unlink(Pluf::f('upload_path').'/'.$this->project->shortname.'/archives/'.$this->cleaned_data['archive']);
    }
}

