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
 * File upload.
 *
 * You can set labels on files.
 */
class IDF_Upload extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_uploads';
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
                                  'relate_name' => 'downloads',
                                  ),
                            'summary' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('summary'),
                                  ),
                            'changelog' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => true,
                                  'verbose' => __('changes'),
                                  ),
                            'file' =>
                            array(
                                  'type' => 'Pluf_DB_Field_File',
                                  'blank' => false,
                                  'default' => 0,
                                  'verbose' => __('file'),
                                  'help_text' => __('The path is relative to the upload path.'),
                                  ),
                            'filesize' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'default' => 0,
                                  'verbose' => __('file size in bytes'),
                                  ),
                            'md5' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => true,
                                  'verbose' => __('MD5'),
                                  ),
                            'submitter' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'submitted_downloads',
                                  ),
                            'tags' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany',
                                  'blank' => true,
                                  'model' => 'IDF_Tag',
                                  'verbose' => __('labels'),
                                  ),
                            'downloads' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'default' => 0,
                                  'verbose' => __('number of downloads'),
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            'modif_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('modification date'),
                                  ),
                            );
        $this->_a['idx'] = array(
                            'modif_dtime_idx' =>
                            array(
                                  'col' => 'modif_dtime',
                                  'type' => 'normal',
                                  ),
                            );
        $table = $this->_con->pfx.'idf_tag_idf_upload_assoc';
        $this->_a['views'] = array(
                              'join_tags' =>
                              array(
                                    'join' => 'LEFT JOIN '.$table
                                    .' ON idf_upload_id=id',
                                    ),
                                   );
    }

    function __toString()
    {
        return $this->file;
    }

    function _toIndex()
    {
        return '';
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
            $this->modif_dtime = gmdate('Y-m-d H:i:s');
            $this->md5 = md5_file ($this->getFullPath());
        }
    }

    function postSave($create=false)
    {
        if ($create) {
            IDF_Timeline::insert($this, $this->get_project(),
                                 $this->get_submitter(), $this->creation_dtime);
        }
    }

    function getAbsoluteUrl($project)
    {
        return Pluf::f('url_upload').'/'.$project->shortname.'/files/'.$this->file;
    }

    function getFullPath()
    {
        return(Pluf::f('upload_path').'/'.$this->get_project()->shortname.'/files/'.$this->file);
    }

    /**
     * We drop the information from the timeline.
     */
    function preDelete()
    {
        IDF_Timeline::remove($this);
        @unlink(Pluf::f('upload_path').'/'.$this->project->shortname.'/files/'.$this->file);
    }

    /**
     * Returns the timeline fragment for the file.
     *
     *
     * @param Pluf_HTTP_Request
     * @return Pluf_Template_SafeString
     */
    public function timelineFragment($request)
    {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Download::view',
                                        array($request->project->shortname,
                                              $this->id));
        $out = '<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);
        $out .= sprintf(__('<a href="%1$s" title="View download">Download %2$d</a>, %3$s'), $url, $this->id, Pluf_esc($this->summary)).'</td>';
        $out .= '</tr>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Addition of <a href="%1$s">download %2$d</a>, by %3$s'), $url, $this->id, $user).'</div></td></tr>';
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Download::view',
                                      array($request->project->shortname,
                                            $this->id));
        $title = sprintf(__('%1$s: Download %2$d added - %3$s'),
                         $request->project->name,
                         $this->id, $this->summary);
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
                       $request,
                       array('url' => $url,
                             'title' => $title,
                             'file' => $this,
                             'date' => $date)
                                                     );
        $tmpl = new Pluf_Template('idf/downloads/feedfragment.xml');
        return $tmpl->render($context);
    }

    /**
     * Notification of change of the object.
     *
     * @param IDF_Conf Current configuration
     * @param bool Creation (true)
     */
    public function notify($conf, $create=true)
    {
        $project = $this->get_project();
        $url = str_replace(array('%p', '%d'),
                           array($project->shortname, $this->id),
                           $conf->getVal('upload_webhook_url', ''));

        $tags = array();
        foreach ($this->get_tags_list() as $tag) {
            $tags[] = $tag->class.':'.$tag->name;
        }

        $submitter = $this->get_submitter();
        $payload = array(
            'to_send' => array(
                'project' => $project->shortname,
                'id' => $this->id,
                'summary' => $this->summary,
                'changelog' => $this->changelog,
                'filename' => $this->file,
                'filesize' => $this->filesize,
                'md5sum' => $this->md5,
                'submitter_login' => $submitter->login,
                'submitter_email' => $submitter->email,
                'tags' => $tags,
            ),
            'project_id' => $project->id,
            'authkey' => $project->getWebHookKey(),
            'url' => $url,
        );

        if ($create === true) {
            $payload['method'] = 'PUT';
            $payload['to_send']['creation_date'] = $this->creation_dtime;
        } else {
            $payload['method'] = 'POST';
            $payload['to_send']['update_date'] = $this->modif_dtime;
        }

        $item = new IDF_Queue();
        $item->type = 'upload';
        $item->payload = $payload;
        $item->create();

        $current_locale = Pluf_Translation::getLocale();

        $from_email = Pluf::f('from_email');
        $messageId  = '<'.md5('upload'.$this->id.md5(Pluf::f('secret_key'))).'@'.Pluf::f('mail_host', 'localhost').'>';
        $recipients = $project->getNotificationRecipientsForTab('downloads');

        foreach ($recipients as $address => $language) {

            if ($this->get_submitter()->email === $address) {
                continue;
            }

            Pluf_Translation::loadSetLocale($language);

            $context = new Pluf_Template_Context(array(
                'file'    => $this,
                'urlfile' => $this->getAbsoluteUrl($project),
                'project' => $project,
                'tags'    => $this->get_tags_list(),
            ));

            $tplfile = 'idf/downloads/download-created-email.txt';
            $subject = __('New download - %1$s (%2$s)');
            $headers = array('Message-ID' => $messageId);
            if (!$create) {
                $tplfile = 'idf/downloads/download-updated-email.txt';
                $subject = __('Updated download - %1$s (%2$s)');
                $headers = array('References' => $messageId);
            }

            $tmpl = new Pluf_Template($tplfile);
            $text_email = $tmpl->render($context);

            $email = new Pluf_Mail($from_email,
                                   $address,
                                   sprintf($subject,
                                           $this->summary,
                                           $project->shortname));
            $email->addTextMessage($text_email);
            $email->addHeaders($headers);
            $email->sendMail();
        }

        Pluf_Translation::loadSetLocale($current_locale);
    }
}
