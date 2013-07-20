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
 * Update an issue.
 *
 * We extend IDF_Form_IssueCreate to reuse the validation logic.
 */
class IDF_Form_IssueUpdate  extends IDF_Form_IssueCreate
{
    public $issue = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->project = $extra['project'];
        $this->issue = $extra['issue'];
        if ($this->user->hasPerm('IDF.project-owner', $this->project)
            or $this->user->hasPerm('IDF.project-member', $this->project)) {
            $this->show_full = true;
        }
        $this->relation_types = $this->project->getRelationsFromConfig();
        if ($this->show_full) {
            $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Summary'),
                                            'initial' => $this->issue->summary,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
        }
        $this->fields['content'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Comment'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 9,
                                                                    ),
                                            ));
        $upload_path = Pluf::f('upload_issue_path', false);
        if (false === $upload_path) {
            throw new Pluf_Exception_SettingError(__('The "upload_issue_path" configuration variable was not set.'));
        }
        $md5 = md5(rand().microtime().Pluf_Utils::getRandomString());
        // We add .dummy to try to mitigate security issues in the
        // case of someone allowing the upload path to be accessible
        // to everybody.
        for ($i=1;$i<4;$i++) {
            $filename = substr($md5, 0, 2).'/'.substr($md5, 2, 2).'/'.substr($md5, 4).'/%s.dummy';
            $this->fields['attachment'.$i] = new Pluf_Form_Field_File(
                array('required' => false,
                      'label' => __('Attach a file'),
                      'move_function_params' =>
                      array('upload_path' => $upload_path,
                            'upload_path_create' => true,
                            'file_name' => $filename,
                            )
                      )
                );
        }

        if ($this->show_full) {
            $this->fields['status'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Status'),
                                            'initial' => $this->issue->get_status()->name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 20,
                                                       'size' => 15,
                                                                    ),
                                            ));
            $initial = ($this->issue->get_owner() == null) ? '' : $this->issue->get_owner()->login;
            $this->fields['owner'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Owner'),
                                            'initial' => $initial,
                                            'widget_attrs' => array(
                                                       'maxlength' => 20,
                                                       'size' => 15,
                                                                    ),
                                            ));

            $idx = 0;
            // note: clean_relation_type0 and clean_relation_issue0 already
            //       exist in the base class
            $this->fields['relation_type'.$idx] = new Pluf_Form_Field_Varchar(
                          array('required' => false,
                                'label' => __('This issue'),
                                'initial' => current($this->relation_types),
                                'widget_attrs' => array('size' => 15),
                                ));

            $this->fields['relation_issue'.$idx] = new Pluf_Form_Field_Varchar(
                          array('required' => false,
                                'label' => null,
                                'initial' => '',
                                'widget_attrs' => array('size' => 10),
                                ));

            ++$idx;
            $relatedIssues = $this->issue->getGroupedRelatedIssues(array(), true);
            foreach ($relatedIssues as $verb => $ids) {
                $this->fields['relation_type'.$idx] = new Pluf_Form_Field_Varchar(
                          array('required' => false,
                                'label' => __('This issue'),
                                'initial' => $verb,
                                'widget_attrs' => array('size' => 15),
                                ));
                $m = 'clean_relation_type'.$idx;
                $this->$m = create_function('$form', '
                    return $form->clean_relation_type($form->cleaned_data["relation_type'.$idx.'"]);
                ');

                $this->fields['relation_issue'.$idx] = new Pluf_Form_Field_Varchar(
                              array('required' => false,
                                    'label' => null,
                                    'initial' => implode(', ', $ids),
                                    'widget_attrs' => array('size' => 10),
                                    ));
                $m = 'clean_relation_issue'.$idx;
                $this->$m = create_function('$form', '
                    return $form->clean_relation_issue($form->cleaned_data["relation_issue'.$idx.'"]);
                ');

                ++$idx;
            }

            $tags = $this->issue->get_tags_list();
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
        }
    }

    /**
     * Clean the attachments post failure.
     */
    function failed()
    {
        $upload_path = Pluf::f('upload_issue_path', false);
        if ($upload_path == false) return;
        for ($i=1;$i<4;$i++) {
            if (!empty($this->cleaned_data['attachment'.$i]) and
                file_exists($upload_path.'/'.$this->cleaned_data['attachment'.$i])) {
                @unlink($upload_path.'/'.$this->cleaned_data['attachment'.$i]);
            }
        }
    }

    function clean_content()
    {
        $content = trim($this->cleaned_data['content']);
        if (!$this->show_full and strlen($content) == 0) {
            throw new Pluf_Form_Invalid(__('You need to provide a description of the issue.'));
        }
        return $content;
    }

    /**
     * We check that something is really changed.
     */
    public function clean()
    {
        $this->cleaned_data = parent::clean();

        // normalize the user's input by removing dublettes and by combining
        // ids from identical verbs in different input fields into one array
        $normRelatedIssues = array();
        for ($idx = 0; isset($this->cleaned_data['relation_type'.$idx]); ++$idx) {
            $verb = $this->cleaned_data['relation_type'.$idx];
            if (empty($verb))
                continue;

            $ids = preg_split('/\s*,\s*/', $this->cleaned_data['relation_issue'.$idx],
                              -1, PREG_SPLIT_NO_EMPTY);
            if (count($ids) == 0)
                continue;

            if (!array_key_exists($verb, $normRelatedIssues))
                $normRelatedIssues[$verb] = array();
            foreach ($ids as $id) {
                if (!in_array($id, $normRelatedIssues[$verb]))
                    $normRelatedIssues[$verb][] = $id;
            }
        }

        // now look at any added / removed ids
        $added = $removed = array();
        $relatedIssues = $this->issue->getGroupedRelatedIssues(array(), true);
        $added = array_diff_key($normRelatedIssues, $relatedIssues);
        $removed = array_diff_key($relatedIssues, $normRelatedIssues);

        $keysToLookAt = array_keys(
            array_intersect_key($relatedIssues, $normRelatedIssues)
        );
        foreach ($keysToLookAt as $key) {
            $a = array_diff($normRelatedIssues[$key], $relatedIssues[$key]);
            if (count($a) > 0)
                $added[$key] = $a;
            $r = array_diff($relatedIssues[$key], $normRelatedIssues[$key]);
            if (count($r) > 0)
                $removed[$key] = $r;
        }

        // cache the added / removed data, so we do not have to
        // calculate that again
        $this->cleaned_data['_added_issue_relations'] = $added;
        $this->cleaned_data['_removed_issue_relations'] = $removed;

        // As soon as we know that at least one change was done, we
        // return the cleaned data and do not go further.
        if (strlen(trim($this->cleaned_data['content']))) {
            return $this->cleaned_data;
        }
        if ($this->show_full) {
            $status = $this->issue->get_status();
            if (trim($this->cleaned_data['status']) != $status->name) {
                return $this->cleaned_data;
            }
            if (trim($this->issue->summary) != trim($this->cleaned_data['summary'])) {
                return $this->cleaned_data;
            }
            $owner = self::findUser($this->cleaned_data['owner']);
            if ((is_null($owner) and !is_null($this->issue->get_owner()))
                or (!is_null($owner) and is_null($this->issue->get_owner()))
                or ((!is_null($owner) and !is_null($this->issue->get_owner())) and $owner->id != $this->issue->get_owner()->id)) {
                return $this->cleaned_data;
            }
            $tags = array();
            for ($i=1;$i<7;$i++) {
                if (strlen($this->cleaned_data['label'.$i]) > 0) {
                    if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                        list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                        list($class, $name) = array(trim($class), trim($name));
                    } else {
                        $class = 'Other';
                        $name = trim($this->cleaned_data['label'.$i]);
                    }
                    $tags[] = array($class, $name);
                }
            }
            $oldtags = $this->issue->get_tags_list();
            foreach ($tags as $tag) {
                $found = false;
                foreach ($oldtags as $otag) {
                    if ($otag->class == $tag[0] and $otag->name == $tag[1]) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    // new tag not found in the old tags
                    return $this->cleaned_data;
                }
            }
            foreach ($oldtags as $otag) {
                $found = false;
                foreach ($tags as $tag) {
                    if ($otag->class == $tag[0] and $otag->name == $tag[1]) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    // old tag not found in the new tags
                    return $this->cleaned_data;
                }
            }

            if (count($this->cleaned_data['_added_issue_relations']) != 0 ||
                count($this->cleaned_data['_removed_issue_relations']) != 0) {
                return $this->cleaned_data;
            }
        }
        // no changes!
        throw new Pluf_Form_Invalid(__('No changes were entered.'));
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
        if ($this->show_full) {
            // Add a tag for each label
            $tags = array();
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
                    $tag = IDF_Tag::add($name, $this->project, $class);
                    $tags[] = $tag;
                    $tagids[] = $tag->id;
                }
            }
            // Compare between the old and the new data
            $changes = array();
            $oldtags = $this->issue->get_tags_list();
            foreach ($tags as $tag) {
                if (!Pluf_Model_InArray($tag, $oldtags)) {
                    if (!isset($changes['lb'])) $changes['lb'] = array();
                    if (!isset($changes['lb']['add'])) $changes['lb']['add'] = array();
                    if ($tag->class != 'Other') {
                        $changes['lb']['add'][] = (string) $tag; //new tag
                    } else {
                        $changes['lb']['add'][] = (string) $tag->name;
                    }
                }
            }
            foreach ($oldtags as $tag) {
                if (!Pluf_Model_InArray($tag, $tags)) {
                    if (!isset($changes['lb'])) $changes['lb'] = array();
                    if (!isset($changes['lb']['rem'])) $changes['lb']['rem'] = array();
                    if ($tag->class != 'Other') {
                        $changes['lb']['rem'][] = (string) $tag; //new tag
                    } else {
                        $changes['lb']['rem'][] = (string) $tag->name;
                    }
                }
            }
            // Status, summary and owner
            $status = IDF_Tag::add(trim($this->cleaned_data['status']), $this->project, 'Status');
            if ($status->id != $this->issue->status) {
                $changes['st'] = $status->name;
            }
            if (trim($this->issue->summary) != trim($this->cleaned_data['summary'])) {
                $changes['su'] = trim($this->cleaned_data['summary']);
            }
            $owner = self::findUser($this->cleaned_data['owner']);
            if ((is_null($owner) and !is_null($this->issue->get_owner()))
                or (!is_null($owner) and is_null($this->issue->get_owner()))
                or ((!is_null($owner) and !is_null($this->issue->get_owner())) and $owner->id != $this->issue->get_owner()->id)) {
                $changes['ow'] = (is_null($owner)) ? '---' : $owner->login;
            }
            // Issue relations - additions
            foreach ($this->cleaned_data['_added_issue_relations'] as $verb => $ids) {
                $other_verb = $this->relation_types[$verb];
                foreach ($ids as $id) {
                    $related_issue = new IDF_Issue($id);
                    $rel = new IDF_IssueRelation();
                    $rel->issue = $this->issue;
                    $rel->verb = $verb;
                    $rel->other_issue = $related_issue;
                    $rel->submitter = $this->user;
                    $rel->create();

                    $other_rel = new IDF_IssueRelation();
                    $other_rel->issue = $related_issue;
                    $other_rel->verb = $other_verb;
                    $other_rel->other_issue = $this->issue;
                    $other_rel->submitter = $this->user;
                    $other_rel->create();
                }
                if (!isset($changes['rel'])) $changes['rel'] = array();
                if (!isset($changes['rel']['add'])) $changes['rel']['add'] = array();
                $changes['rel']['add'][] = $verb.' '.implode(', ', $ids);
            }
            // Issue relations - removals
            foreach ($this->cleaned_data['_removed_issue_relations'] as $verb => $ids) {
                foreach ($ids as $id) {
                    $db = &Pluf::db();
                    $table = Pluf::factory('IDF_IssueRelation')->getSqlTable();
                    $sql = new Pluf_SQL('verb=%s AND (
                                        (issue=%s AND other_issue=%s) OR
                                        (other_issue=%s AND issue=%s))',
                                        array($verb,
                                              $this->issue->id, $id,
                                              $this->issue->id, $id));
                    $db->execute('DELETE FROM '.$table.' WHERE '.$sql->gen());
                }

                if (!isset($changes['rel'])) $changes['rel'] = array();
                if (!isset($changes['rel']['rem'])) $changes['rel']['rem'] = array();
                $changes['rel']['rem'][] = $verb.' '.implode(', ', $ids);
            }
            // Update the issue
            $this->issue->batchAssoc('IDF_Tag', $tagids);
            $this->issue->summary = trim($this->cleaned_data['summary']);
            $this->issue->status = $status;
            $this->issue->owner = $owner;
        }
        // Create the comment
        $comment = new IDF_IssueComment();
        $comment->issue = $this->issue;
        $comment->content = $this->cleaned_data['content'];
        $comment->submitter = $this->user;
        if (!$this->show_full) $changes = array();
        $comment->changes = $changes;
        $comment->create();
        $this->issue->update();
        if ($this->issue->owner != $this->user->id and
            $this->issue->submitter != $this->user->id) {
            $this->issue->setAssoc($this->user); // interested user.
        }
        $attached_files = array();
        for ($i=1;$i<4;$i++) {
            if ($this->cleaned_data['attachment'.$i]) {
                $file = new IDF_IssueFile();
                $file->attachment = $this->cleaned_data['attachment'.$i];
                $file->submitter = $this->user;
                $file->comment = $comment;
                $file->create();
                $attached_files[] = $file;
            }
        }
        /**
         * [signal]
         *
         * IDF_Issue::update
         *
         * [sender]
         *
         * IDF_Form_IssueUpdate
         *
         * [description]
         *
         * This signal allows an application to perform a set of tasks
         * just after the update of an issue.
         *
         * [parameters]
         *
         * array('issue' => $issue,
         *       'comment' => $comment,
         *       'files' => $attached_files);
         *
         */
        $params = array('issue' => $this->issue,
                        'comment' => $comment,
                        'files' => $attached_files);
        Pluf_Signal::send('IDF_Issue::update', 'IDF_Form_IssueUpdate',
                          $params);

        return $this->issue;
    }
}
