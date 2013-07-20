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
 * A revision of a wiki page.
 *
 */
class IDF_Wiki_PageRevision extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_wikipagerevs';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true,
                                  ),
                            'wikipage' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Wiki_Page',
                                  'blank' => false,
                                  'verbose' => __('page'),
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
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Compressed',
                                  'blank' => false,
                                  'verbose' => __('content'),
                                  ),
                            'submitter' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  ),
                            'changes' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Serialized',
                                  'blank' => true,
                                  'verbose' => __('changes'),
                                  'help_text' => 'Serialized array of the changes in the page.',
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            );
        $this->_a['idx'] = array(
                            'creation_dtime_idx' =>
                            array(
                                  'col' => 'creation_dtime',
                                  'type' => 'normal',
                                  ),
                            );
        $table = $this->_con->pfx.'idf_wiki_pagerevision_idf_wiki_resourcerevision_assoc';
        $this->_a['views'] = array(
            'join_pagerevision' =>
                array(
                    'join' => 'LEFT JOIN '.$table
                             .' ON idf_wiki_pagerevision_id=id',
            ),
        );
    }

    function changedRevision()
    {
        return (is_array($this->changes) and count($this->changes) > 0);
    }

    function _toIndex()
    {
        return $this->content;
    }

    /**
     * We drop the information from the timeline.
     */
    function preDelete()
    {
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
        $page = $this->get_wikipage();

        if ($create) {
            // Check if more than one revision for this page. We do
            // not want to insert the first revision in the timeline
            // as the page itself is inserted.  We do not insert on
            // update as update is performed to change the is_head
            // flag.
            $sql = new Pluf_SQL('wikipage=%s', array($this->wikipage));
            $rev = Pluf::factory('IDF_Wiki_PageRevision')->getList(array('filter'=>$sql->gen()));
            if ($rev->count() > 1) {
                IDF_Timeline::insert($this, $page->get_project(), $this->get_submitter());
                foreach ($rev as $r) {
                    if ($r->id != $this->id and $r->is_head) {
                        $r->is_head = false;
                        $r->update();
                    }
                }
            }
        }

        IDF_Search::index($page);
        $page->update(); // Will update the modification timestamp.

        // remember the resource revisions used in this page revision
        if ($this->is_head) {
            preg_match_all('#\[\[!([A-Za-z0-9\-]+)[^\]]*\]\]#im', $this->content, $matches, PREG_PATTERN_ORDER);
            if (count($matches) > 1 && count($matches[1]) > 0) {
                foreach ($matches[1] as $resourceName) {
                    $sql = new Pluf_SQL('project=%s AND title=%s',
                                        array($page->get_project()->id, $resourceName));
                    $resources = Pluf::factory('IDF_Wiki_Resource')->getList(array('filter'=>$sql->gen()));
                    if ($resources->count() == 0)
                        continue;

                    $current_revision = $resources[0]->get_current_revision();
                    $current_revision->setAssoc($this);
                    $this->setAssoc($current_revision);
                }
            }
        }
    }

    public function timelineFragment($request)
    {
        $page = $this->get_wikipage();
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewPage',
                                      array($request->project->shortname,
                                            $page->title),
                                      array('rev' => $this->id));
        $out = "\n".'<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);
        $out .= sprintf(__('<a href="%1$s" title="View page">%2$s</a>, %3$s'), $url, Pluf_esc($page->title), Pluf_esc($this->summary));
        if ($this->changedRevision()) {
            $out .= '<div class="issue-changes-timeline">';
            $changes = $this->changes;
            foreach ($changes as $w => $v) {
                $out .= '<strong>';
                switch ($w) {
                case 'lb':
                    $out .= __('Labels:'); break;
                }
                $out .= '</strong>&nbsp;';
                if ($w == 'lb') {
                    $out .= Pluf_esc(implode(', ', $v));
                } else {
                    $out .= Pluf_esc($v);
                }
                $out .= ' ';
            }
            $out .= '</div>';
        }
        $out .= '</td></tr>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Change of <a href="%1$s">%2$s</a>, by %3$s'), $url, Pluf_esc($page->title), $user).'</div></td></tr>';
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $page = $this->get_wikipage();
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::viewPage',
                                      array($request->project->shortname,
                                            $page->title),
                                      array('rev' => $this->id));

        $title = sprintf(__('%1$s: Documentation page %2$s updated - %3$s'),
                         $request->project->name,
                         $page->title, $page->summary);
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
                       $request,
                       array('url' => $url,
                             'title' => $title,
                             'page' => $page,
                             'rev' => $this,
                             'create' => false,
                             'date' => $date)
                                                     );
        $tmpl = new Pluf_Template('idf/wiki/feedfragment-page.xml');
        return $tmpl->render($context);
    }



    /**
     * Notification of change of a Wiki Page.
     *
     * The content of a WikiPage is in the IDF_WikiRevision object,
     * this is why we send the notificatin from there. This means that
     * when the create flag is set, this is for the creation of a
     * wikipage and not, for the addition of a new revision.
     *
     * Usage:
     * <pre>
     * $this->notify($conf); // Notify the creation of a wiki page
     * $this->notify($conf, false); // Notify the update of the page
     * </pre>
     *
     * @param IDF_Conf Current configuration
     * @param bool Creation (true)
     */
    public function notify($conf, $create=true)
    {
        $wikipage = $this->get_wikipage();
        $project  = $wikipage->get_project();
        $current_locale = Pluf_Translation::getLocale();

        $from_email = Pluf::f('from_email');
        $messageId  = '<'.md5('wiki'.$wikipage->id.md5(Pluf::f('secret_key'))).'@'.Pluf::f('mail_host', 'localhost').'>';
        $recipients = $project->getNotificationRecipientsForTab('wiki');

        foreach ($recipients as $address => $language) {

            if ($this->get_submitter()->email === $address) {
                continue;
            }

            Pluf_Translation::loadSetLocale($language);

            $context = new Pluf_Template_Context(array(
                'page'     => $wikipage,
                'rev'      => $this,
                'project'  => $project,
                'url_base' => Pluf::f('url_base'),
            ));

            $tplfile = 'idf/wiki/wiki-created-email.txt';
            $subject = __('New Documentation Page %1$s - %2$s (%3$s)');
            $headers = array('Message-ID' => $messageId);
            if (!$create) {
                $tplfile = 'idf/wiki/wiki-updated-email.txt';
                $subject = __('Documentation Page Changed %1$s - %2$s (%3$s)');
                $headers = array('References' => $messageId);
            }

            $tmpl = new Pluf_Template($tplfile);
            $text_email = $tmpl->render($context);

            $email = new Pluf_Mail($from_email,
                                   $address,
                                   sprintf($subject,
                                           $wikipage->title,
                                           $wikipage->summary,
                                           $project->shortname));
            $email->addTextMessage($text_email);
            $email->addHeaders($headers);
            $email->sendMail();
        }

        Pluf_Translation::loadSetLocale($current_locale);
    }
}
