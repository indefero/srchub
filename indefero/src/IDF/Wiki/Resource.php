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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
Pluf::loadFunction('Pluf_Template_dateAgo');

/**
 * Base definition of a wiki resource.
 *
 * The resource groups several logical versions of a file into one and gives
 * it a unique title across a project's wiki. The current file version is
 * saved in a IDF_Wiki_ResourceRevision, which is also the entity that is
 * linked with a specific IDF_Wiki_PageRevision on which the resource is
 * displayed.
 */
class IDF_Wiki_Resource extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_wikiresources';
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
                                  'relate_name' => 'wikipages',
                                  ),
                            'title' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('title'),
                                  'help_text' => __('The title of the resource must only contain letters, digits, dots or the dash character. For example: my-resource.png.'),
                                  ),
                            'mime_type' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 100,
                                  'verbose' => __('MIME media type'),
                                  'help_text' => __('The MIME media type of the resource.'),
                                  ),
                            'summary' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('summary'),
                                  'help_text' => __('A one line description of the resource.'),
                                  ),
                            'submitter' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'submitted_wikipages',
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
    }

    function __toString()
    {
        return $this->title.' - '.$this->summary;
    }

    /**
     * We drop the information from the timeline.
     */
    function preDelete()
    {
        IDF_Timeline::remove($this);
        IDF_Search::remove($this);
    }

    function get_current_revision()
    {
        $true = Pluf_DB_BooleanToDb(true, $this->getDbConnection());
        $rev = $this->get_revisions_list(array('filter' => 'is_head='.$true,
                                               'nb' => 1));
        return ($rev->count() == 1) ? $rev[0] : null;
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
        $this->modif_dtime = gmdate('Y-m-d H:i:s');
    }

    function postSave($create=false)
    {
        // Note: No indexing is performed here. The indexing is
        // triggered in the postSave step of the revision to ensure
        // that the page as a given revision in the database when
        // doing the indexing.
        if ($create) {
            IDF_Timeline::insert($this, $this->get_project(),
                                 $this->get_submitter());
        }
    }

    /**
     * Returns an HTML fragment used to display this resource in the
     * timeline.
     *
     * The request object is given to be able to check the rights and
     * as such create links to other items etc. You can consider that
     * if displayed, you can create a link to it.
     *
     * @param Pluf_HTTP_Request
     * @return Pluf_Template_SafeString
     */
    public function timelineFragment($request)
    {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                        array($request->project->shortname,
                                              $this->title));
        $out = '<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);
        $out .= sprintf(__('<a href="%1$s" title="View resource">%2$s</a>, %3$s'), $url, Pluf_esc($this->title), Pluf_esc($this->summary)).'</td>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Creation of <a href="%1$s">resource %2$s</a>, by %3$s'), $url, Pluf_esc($this->title), $user).'</div></td></tr>';
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                      array($request->project->shortname,
                                            $this->title));
        $title = sprintf(__('%1$s: Documentation resource %2$s added - %3$s'),
                         $request->project->name,
                         $this->title, $this->summary);
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
                       $request,
                       array('url' => $url,
                             'title' => $title,
                             'resource' => $this,
                             'rev' => $this->get_current_revision(),
                             'create' => true,
                             'date' => $date)
        );
        $tmpl = new Pluf_Template('idf/wiki/feedfragment-resource.xml');
        return $tmpl->render($context);
    }
}
