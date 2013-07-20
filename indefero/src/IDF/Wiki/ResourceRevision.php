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
 * A single resource revision.
 */
class IDF_Wiki_ResourceRevision extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_wikiresourcerevs';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true,
                                  ),
                            'wikiresource' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Wiki_Resource',
                                  'blank' => false,
                                  'verbose' => __('resource'),
                                  'relate_name' => 'revisions',
                                  ),
                            'is_head' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Boolean',
                                  'blank' => false,
                                  'default' => false,
                                  'help_text' => 'If this revision is the latest, we mark it as being the head revision.',
                                  'index' => true,
                                  ),
                            'summary' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('summary'),
                                  'help_text' => __('A one line description of the changes.'),
                                  ),
                            'filesize' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'default' => 0,
                                  'verbose' => __('file size in bytes'),
                                  ),
                            'fileext' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 10,
                                  'verbose' => __('File extension'),
                                  'help_text' => __('The file extension of the uploaded resource.'),
                                  ),
                            'submitter' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'submitted_downloads',
                                  ),
                            'pageusage' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany',
                                  'model' => 'IDF_Wiki_PageRevision',
                                  'blank' => true,
                                  'verbose' => __('page usage'),
                                  'help_text' => 'Records on which pages this resource revision is used.',
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            );
        $table = $this->_con->pfx.'idf_wiki_pagerevision_idf_wiki_resourcerevision_assoc';
        $this->_a['views'] = array(
            'join_pagerevision' =>
                array(
                    'join' => 'LEFT JOIN '.$table
                             .' ON idf_wiki_resourcerevision_id=id',
                ),
        );
    }

    function __toString()
    {
        return sprintf(__('id %d: %s'), $this->id, $this->summary);
    }

    function _toIndex()
    {
        return '';
    }

    function preDelete()
    {
        // if we kill off a head revision, ensure that we either mark a previous revision as head
        if ($this->is_head) {
            $sql = new Pluf_SQL('wikiresource=%s and id!=%s', array($this->wikiresource, $this->id));
            $revs = Pluf::factory('IDF_Wiki_ResourceRevision')->getList(array('filter'=>$sql->gen(), 'order'=>'id DESC'));
            if ($revs->count() > 0) {
                $previous = $revs[0];
                $previous->is_head = true;
                $previous->update();
            }
        }

        @unlink($this->getFilePath());
        IDF_Timeline::remove($this);
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
            $this->is_head = true;
        }
    }

    function postSave($create=false)
    {
        $resource = $this->get_wikiresource();

        if ($create) {
            $sql = new Pluf_SQL('wikiresource=%s', array($this->wikiresource));
            $rev = Pluf::factory('IDF_Wiki_ResourceRevision')->getList(array('filter'=>$sql->gen()));
            if ($rev->count() > 1) {
                IDF_Timeline::insert($this, $resource->get_project(), $this->get_submitter());
                foreach ($rev as $r) {
                    if ($r->id != $this->id and $r->is_head) {
                        $r->is_head = false;
                        $r->update();
                    }
                }
            }
        }

        // update the modification timestamp
        $resource->update();
    }

    function getFilePath()
    {
        return sprintf(Pluf::f('upload_path').'/'.$this->get_wikiresource()->get_project()->shortname.'/wiki/res/%d/%d.%s',
            $this->get_wikiresource()->id, $this->id, $this->fileext);
    }

    function getViewURL()
    {
        $prj = $this->get_wikiresource()->get_project();
        $resource = $this->get_wikiresource();
        return Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                        array($prj->shortname, $resource->title),
                                        array('rev' => $this->id));
    }

    function getRawURL($attachment = false)
    {
        $query = $attachment ? array('attachment' => 1) : array();
        $prj = $this->get_wikiresource()->get_project();
        return Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::rawResource',
                                        array($prj->shortname, $this->id),
                                        $query);
    }

    /**
     * Returns the page revisions which contain references to this resource revision
     */
    function getPageRevisions()
    {
        $db =& Pluf::db();
        $sql_results = $db->select(
            'SELECT idf_wiki_pagerevision_id as id '.
            'FROM '.Pluf::f('db_table_prefix', '').'idf_wiki_pagerevision_idf_wiki_resourcerevision_assoc '.
            'WHERE idf_wiki_resourcerevision_id='.$this->id
        );
        $ids = array(0);
        foreach ($sql_results as $id) {
            $ids[] = $id['id'];
        }
        $ids = implode (',', $ids);

        $sql = new Pluf_SQL('id IN ('.$ids.')');
        return Pluf::factory('IDF_Wiki_PageRevision')
            ->getList(array('filter' => $sql->gen()));
    }

    /**
     * Renders the resource with the given view options, including a link to the resource' detail page
     */
    function render($opts = array())
    {
        // give some reasonable defaults
        $opts = array_merge(array(
            'align' => 'left',
            'width' => '',
            'height' => '',
            'preview' => 'yes', // if possible
            'title' => '',
        ), $opts);

        $attrs  = array('class="resource-container"');
        $styles = array();
        if (!empty($opts['align'])) {
            switch ($opts['align']) {
                case 'left':
                    $styles[] = 'float: left';
                    $styles[] = 'margin-right: 10px';
                    break;
                case 'center':
                    $styles[] = 'margin: 0 auto 0 auto';
                    break;
                case 'right':
                    $styles[] = 'float: right';
                    $styles[] = 'margin-left: 10px';
                    break;
            }
        }
        if (!empty($opts['width'])) {
            $styles[] = 'width:'.$opts['width'];
        }
        if (!empty($opts['height'])) {
            $styles[] = 'height:'.$opts['height'];
        }

        $raw = $this->renderRaw();
        $viewUrl = $this->getViewURL();
        $download = '';
        $html = '<div class="resource-container" style="'.implode(';', $styles).'">';
        if ($opts['preview'] == 'yes' && !empty($raw)) {
            $html .= '<div class="preview">'.$raw.'</div>'."\n";
        } else {
            $rawUrl = $this->getRawURL(true);
            $download = '<a href="'.$rawUrl.'" class="download" title="'.sprintf(__('Download (%s)'), Pluf_Utils::prettySize($this->filesize)).'"></a>';
        }
        $resource = $this->get_wikiresource();
        $title = $opts['title'];
        if (empty($title)) {
            $title = $resource->title.' - '.$resource->mime_type.' - '.Pluf_Utils::prettySize($this->filesize);
        }
        $html .= '<div class="title">'.$download.'<a href="'.$viewUrl.'" title="'.__('View resource details').'">'.$title.'</a></div>'."\n";
        $html .= '</div>';
        return $html;
    }

    /**
     * Renders a raw version of the resource, without any possibilities of formatting or the like
     */
    function renderRaw()
    {
        $resource = $this->get_wikiresource();
        $url = $this->getRawURL();
        if (preg_match('#^image/(gif|jpeg|png|tiff)$#', $resource->mime_type)) {
            return sprintf('<img src="%s" alt="%s" />', $url, $resource->title);
        }

        if (preg_match('#^text/(plain|xml|html|sgml|javascript|ecmascript|css)$#', $resource->mime_type)) {
            return sprintf('<iframe src="%s" alt="%s"></iframe>', $url, $resource->title);
        }

        return '';
    }


    public function timelineFragment($request)
    {
        $resource = $this->get_wikiresource();
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                      array($request->project->shortname,
                                            $resource->title),
                                      array('rev' => $this->id));

        $out = "\n".'<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);
        $out .= sprintf(__('<a href="%1$s" title="View resource">%2$s</a>, %3$s'), $url, Pluf_esc($resource->title), Pluf_esc($this->summary));
        $out .= '</td></tr>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Change of <a href="%1$s">%2$s</a>, by %3$s'), $url, Pluf_esc($resource->title), $user).'</div></td></tr>';
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $resource = $this->get_wikiresource();
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewResource',
                                      array($request->project->shortname,
                                            $resource->title),
                                      array('rev' => $this->id));

        $title = sprintf(__('%1$s: Documentation resource %2$s updated - %3$s'),
                         $request->project->name,
                         $resource->title, $resource->summary);
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
            $request,
            array('url' => $url,
                  'title' => $title,
                  'resource' => $resource,
                  'rev' => $this,
                  'create' => false,
                  'date' => $date)
        );
        $tmpl = new Pluf_Template('idf/wiki/feedfragment-resource.xml');
        return $tmpl->render($context);
    }
}
