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
 * Base definition of a project.
 *
 * The issue management system can be used to manage several projects
 * at the same time.
 */
class IDF_Project extends Pluf_Model
{
    public $_model = __CLASS__;
    public $_extra_cache = array();
    protected $_pconf = null;
    /**
     * Check if the project as one restricted tab.
     *
     * This is the cached information.
     *
     * @see self::isRestricted
     */
    protected $_isRestricted = null;

    function init()
    {
        $this->_pconf = null;
        $this->_extra_cache = array();
        $this->_a['table'] = 'idf_projects';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true,
                                  ),
                            'name' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('name'),
                                  ),
                            'shortname' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 50,
                                  'verbose' => __('short name'),
                                  'help_text' => __('Used in the URL to access the project, must be short with only letters and numbers.'),
                                  'unique' => true,
                                  ),
                            'shortdesc' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 255,
                                  'verbose' => __('short description'),
                                  'help_text' => __('A one line description of the project.'),
                                  ),
                            'description' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('description'),
                                  'help_text' => __('The description can be extended using the Markdown syntax.'),
                                  ),
                            'tags' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany',
                                  'blank' => true,
                                  'model' => 'IDF_Tag',
                                  'verbose' => __('labels'),
                                  ),
                            'private' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('private'),
                                  'default' => 0,
                                  ),
                            'current_activity' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_ProjectActivity',
                                  'blank' => true,
                                  'is_null' => true,
                                  'default' => null,
                                  'verbose' => __('current project activity'),
                                  ),
			                'enableads' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('enableads'),
                                  'default' => 1,
                                  ),

                            'syntaxtheme' =>
                            array(
                                'type' => 'Pluf_DB_Field_Text',
                                'blank' => false,
                                'verbose' => __('syntaxtheme'),
                                'default' => "Default",
                                "size" => 50
                            )
                            );
        $activityTable = $this->_con->pfx.'idf_projectactivities';
        $tagTable = $this->_con->pfx.'idf_project_idf_tag_assoc';
        $this->_a['views'] = array(
            'join_activities_and_tags' =>
                array(
                    'join' => 'LEFT JOIN '.$activityTable.' ON current_activity='.$activityTable.'.id '
                             .'LEFT JOIN '.$tagTable.' ON idf_project_id='.$this->getSqlTable().'.id',
                    'select' => 'DISTINCT '.$this->getSelect().', date, value',
                    'props' => array(
                        'date' => 'current_activity_date',
                        'value' => 'current_activity_value'
                    ),
                ),
        );
    }


    /**
     * String representation of the abstract.
     */
    function __toString()
    {
        return $this->name;
    }

    /**
     * String ready for indexation.
     */
    function _toIndex()
    {
        return '';
    }


    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
        $this->modif_dtime = gmdate('Y-m-d H:i:s');
    }

    public static function getOr404($shortname)
    {
        $sql = new Pluf_SQL('shortname=%s', array(trim($shortname)));
        $projects = Pluf::factory(__CLASS__)->getList(array('filter' => $sql->gen()));
        if ($projects->count() != 1) {
            throw new Pluf_HTTP_Error404(sprintf(__('Project "%s" not found.'),
                                                 $shortname));
        }
        return $projects[0];
    }

    /**
     * Returns the number of open/closed issues.
     *
     * @param string Status ('open'), 'closed'
     * @param IDF_Tag Subfilter with a label (null)
     * @return int Count
     */
    public function getIssueCountByOwner($status='open')
    {
        switch ($status) {
        case 'open':
            $tags = implode(',', $this->getTagIdsByStatus('open'));
            break;
        case 'closed':
        default:
            $tags = implode(',', $this->getTagIdsByStatus('closed'));
            break;
        }
        $sqlIssueTable = Pluf::factory('IDF_Issue')->getSqlTable();
        $query = "SELECT uid AS id,COUNT(uid) AS nb
FROM (
    SELECT COALESCE(owner, -1) AS uid
    FROM $sqlIssueTable
    WHERE status IN ($tags)
    ) AS ff
GROUP BY uid";

        $db = Pluf::db();
        $dbData = $db->select($query);
        $ownerStatistics = array();
        foreach ($dbData as $k => $v) {
            $key = ($v['id'] === '-1') ? null : $v['id'];
            $ownerStatistics[$key] = (int)$v['nb'];
        }

        arsort($ownerStatistics);

        return $ownerStatistics;
    }

    /**
     * Returns the number of open/closed issues.
     *
     * @param string Status ('open'), 'closed'
     * @param IDF_Tag Subfilter with a label (null)
     * @param array Restrict further to a list of ids
     * @return int Count
     */
    public function getIssueCountByStatus($status='open', $label=null, $ids=array())
    {
        switch ($status) {
        case 'open':
            $key = 'labels_issue_open';
            $default = IDF_Form_IssueTrackingConf::init_open;
            break;
        case 'closed':
        default:
            $key = 'labels_issue_closed';
            $default = IDF_Form_IssueTrackingConf::init_closed;
            break;
        }
        $tags = array();
        foreach ($this->getTagsFromConfig($key, $default, 'Status') as $tag) {
            $tags[] = (int)$tag->id;
        }
        if (count($tags) == 0) return array();
        $sql = new Pluf_SQL(sprintf('project=%%s AND status IN (%s)', implode(', ', $tags)), array($this->id));
        if (!is_null($label)) {
            $sql2 = new Pluf_SQL('idf_tag_id=%s', array($label->id));
            $sql->SAnd($sql2);
        }
        if (count($ids) > 0) {
            $sql2 = new Pluf_SQL(sprintf('id IN (%s)', implode(', ', $ids)));
            $sql->SAnd($sql2);
        }
        $params = array('filter' => $sql->gen());
        if (!is_null($label)) { $params['view'] = 'join_tags'; }
        $gissue = new IDF_Issue();
        return $gissue->getCount($params);
    }

    /**
     * Get the tags for a specific list of issues.
     *
     * @param string Status ('open') or 'closed'
     * @param array A list of issue ids
     * @return array An array of tag objects
     */
    public function getTagsByIssues($issue_ids=array())
    {
        // make the below query always a valid one
        if (count($issue_ids) == 0) $issue_ids[] = 0;

        $assocTable = $this->_con->pfx.'idf_issue_idf_tag_assoc';
        $query = sprintf(
            'SELECT DISTINCT idf_tag_id FROM %s '.
            'WHERE idf_issue_id IN (%s) '.
            'GROUP BY idf_tag_id',
            $assocTable, implode(',', $issue_ids)
        );

        $db = Pluf::db();
        $dbData = $db->select($query);
        $ids = array(0);
        foreach ($dbData as $data) {
            $ids[] = $data['idf_tag_id'];
        }

        $sql = new Pluf_SQL(sprintf('id IN (%s)', implode(', ', $ids)));
        $model = new IDF_Tag();
        return $model->getList(array('filter' => $sql->gen()));
    }

    /**
     * Get the open/closed tag ids as they are often used when doing
     * listings.
     *
     * As this can be often used, the info are cached.
     *
     * @param string Status ('open') or 'closed'
     * @param bool Force cache refresh (false)
     * @return array Ids of the open/closed tags
     */
    public function getTagIdsByStatus($status='open', $cache_refresh=false)
    {
        if (!$cache_refresh
            and isset($this->_extra_cache['getTagIdsByStatus-'.$status])) {
            return $this->_extra_cache['getTagIdsByStatus-'.$status];
        }
        switch ($status) {
        case 'open':
            $key = 'labels_issue_open';
            $default = IDF_Form_IssueTrackingConf::init_open;
            break;
        case 'closed':
        default:
            $key = 'labels_issue_closed';
            $default = IDF_Form_IssueTrackingConf::init_closed;
            break;
        }
        $tags = array();
        foreach ($this->getTagsFromConfig($key, $default, 'Status') as $tag) {
            $tags[] = (int) $tag->id;
        }
        $this->_extra_cache['getTagIdsByStatus-'.$status] = $tags;
        return $tags;
    }

    /**
     * Convert the definition of tags in the configuration into the
     * corresponding list of tags.
     *
     * @param string Configuration key where the tag is.
     * @param string Default config if nothing in the db.
     * @param string Default class.
     * @return array List of tags
     */
    public function getTagsFromConfig($cfg_key, $default, $dclass='Other')
    {
        $conf = $this->getConf();
        $tags = array();
        foreach (preg_split("/\015\012|\015|\012/", $conf->getVal($cfg_key, $default), -1, PREG_SPLIT_NO_EMPTY) as $s) {
            $_s = explode('=', $s, 2);
            $v = trim($_s[0]);
            $_v = explode(':', $v, 2);
            if (count($_v) > 1) {
                $class = trim($_v[0]);
                $name = trim($_v[1]);
            } else {
                $name = trim($_s[0]);
                $class = $dclass;
            }
            $tags[] = IDF_Tag::add($name, $this, $class);
        }
        return $tags;
    }

    /**
     * Returns a list of relations which are available in this project as
     * associative array. Each key-value pair marks a set of orthogonal
     * relations. To ease processing, each of these pairs is included twice
     * in the array, once as key1 => key2 and once as key2 => key1.
     *
     * @return array List of relation names
     */
    public function getRelationsFromConfig()
    {
        $conf = $this->getConf();
        $rel = $conf->getVal('issue_relations', IDF_Form_IssueTrackingConf::init_relations);
        $relations = array();
        foreach (preg_split("/\015\012|\015|\012/", $rel, -1, PREG_SPLIT_NO_EMPTY) as $s) {
            $verbs = preg_split("/\s*,\s*/", $s, 2);
            if (count($verbs) == 1)
                $relations += array($verbs[0] => $verbs[0]);
            else
                $relations += array($verbs[0] => $verbs[1], $verbs[1] => $verbs[0]);
        }
        return $relations;
    }

    /**
     * Return membership data.
     *
     * The array has 3 keys: 'members', 'owners' and 'authorized'.
     *
     * The list of users is only taken using the row level permission
     * table. That is, if you set a user as administrator, he will
     * have the member and owner rights but will not appear in the
     * lists.
     *
     * @param string Format ('objects'), 'string'.
     * @return mixed Array of Pluf_User or newline separated list of logins.
     */
    public function getMembershipData($fmt='objects')
    {
        $mperm = Pluf_Permission::getFromString('IDF.project-member');
        $operm = Pluf_Permission::getFromString('IDF.project-owner');
        $aperm = Pluf_Permission::getFromString('IDF.project-authorized-user');
        $grow = new Pluf_RowPermission();
        $db =& Pluf::db();
        $false = Pluf_DB_BooleanToDb(false, $db);
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s AND owner_class=%s AND permission=%s AND negative='.$false,
                            array('IDF_Project', $this->id, 'Pluf_User', $operm->id));
        $owners = new Pluf_Template_ContextVars(array());
        foreach ($grow->getList(array('filter' => $sql->gen())) as $row) {
            if ($fmt == 'objects') {
                $owners[] = Pluf::factory('Pluf_User', $row->owner_id);
            } else {
                $owners[] = Pluf::factory('Pluf_User', $row->owner_id)->login;
            }
        }
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s AND owner_class=%s AND permission=%s AND negative='.$false,
                            array('IDF_Project', $this->id, 'Pluf_User', $mperm->id));
        $members = new Pluf_Template_ContextVars(array());
        foreach ($grow->getList(array('filter' => $sql->gen())) as $row) {
            if ($fmt == 'objects') {
                $members[] = Pluf::factory('Pluf_User', $row->owner_id);
            } else {
                $members[] = Pluf::factory('Pluf_User', $row->owner_id)->login;
            }
        }
        $authorized = new Pluf_Template_ContextVars(array());
        if ($aperm != false) {
            $sql = new Pluf_SQL('model_class=%s AND model_id=%s AND owner_class=%s AND permission=%s AND negative='.$false,
                                array('IDF_Project', $this->id, 'Pluf_User', $aperm->id));
            foreach ($grow->getList(array('filter' => $sql->gen())) as $row) {
                if ($fmt == 'objects') {
                    $authorized[] = Pluf::factory('Pluf_User', $row->owner_id);
                } else {
                    $authorized[] = Pluf::factory('Pluf_User', $row->owner_id)->login;
                }
            }
        }
        if ($fmt == 'objects') {
            return new Pluf_Template_ContextVars(array('members' => $members, 'owners' => $owners, 'authorized' => $authorized));
        } else {
            return array('members' => implode("\n", (array) $members),
                         'owners' => implode("\n", (array) $owners),
                         'authorized' => implode("\n", (array) $authorized),
                         );
        }
    }

    /**
     * Generate the tag clouds.
     *
     * Return an array of tags sorted by class, then name. Each tag
     * get the extra property 'nb_use' for the number of use in the
     * project.
     *
     * @param string ('issues') 'closed_issues', 'wiki' or 'downloads'
     * @return ArrayObject of IDF_Tag
     */
    public function getTagCloud($what='issues')
    {
        $tag_t = Pluf::factory('IDF_Tag')->getSqlTable();
        if ($what == 'issues' or $what == 'closed_issues') {
            $what_t = Pluf::factory('IDF_Issue')->getSqlTable();
            $asso_t = $this->_con->pfx.'idf_issue_idf_tag_assoc';
            if ($what == 'issues') {
                $ostatus = $this->getTagIdsByStatus('open');
            } else {
                $ostatus = $this->getTagIdsByStatus('closed');
            }
            if (count($ostatus) == 0) $ostatus[] = 0;
            $sql = sprintf('SELECT '.$tag_t.'.id AS id, COUNT(*) AS nb_use FROM '.$tag_t.' '."\n".
                      'LEFT JOIN '.$asso_t.' ON idf_tag_id='.$tag_t.'.id '."\n".
                      'LEFT JOIN '.$what_t.' ON idf_issue_id='.$what_t.'.id '."\n".
                      'WHERE idf_tag_id IS NOT NULL AND '.$what_t.'.status IN (%s) AND '.$what_t.'.project='.$this->id.' GROUP BY '.$tag_t.'.id, '.$tag_t.'.class, '.$tag_t.'.name ORDER BY '.$tag_t.'.class ASC, '.$tag_t.'.name ASC',
                      implode(', ', $ostatus));
        } elseif ($what == 'wiki') {
            $dep_ids = IDF_Views_Wiki::getDeprecatedPagesIds($this);
            $extra = '';
            if (count($dep_ids)) {
                $extra = ' AND idf_wiki_page_id NOT IN ('.implode(', ', $dep_ids).') ';
            }
            $what_t = Pluf::factory('IDF_Wiki_Page')->getSqlTable();
            $asso_t = $this->_con->pfx.'idf_tag_idf_wiki_page_assoc';
            $sql = 'SELECT '.$tag_t.'.id AS id, COUNT(*) AS nb_use FROM '.$tag_t.' '."\n".
                'LEFT JOIN '.$asso_t.' ON idf_tag_id='.$tag_t.'.id '."\n".
                'LEFT JOIN '.$what_t.' ON idf_wiki_page_id='.$what_t.'.id '."\n".
                'WHERE idf_tag_id IS NOT NULL '.$extra.' AND '.$what_t.'.project='.$this->id.' GROUP BY '.$tag_t.'.id, '.$tag_t.'.class, '.$tag_t.'.name ORDER BY '.$tag_t.'.class ASC, '.$tag_t.'.name ASC';
        } elseif ($what == 'downloads') {
            $dep_ids = IDF_Views_Download::getDeprecatedFilesIds($this);
            $extra = '';
            if (count($dep_ids)) {
                $extra = ' AND idf_upload_id NOT IN ('.implode(', ', $dep_ids).') ';
            }
            $what_t = Pluf::factory('IDF_Upload')->getSqlTable();
            $asso_t = $this->_con->pfx.'idf_tag_idf_upload_assoc';
            $sql = 'SELECT '.$tag_t.'.id AS id, COUNT(*) AS nb_use FROM '.$tag_t.' '."\n".
                'LEFT JOIN '.$asso_t.' ON idf_tag_id='.$tag_t.'.id '."\n".
                'LEFT JOIN '.$what_t.' ON idf_upload_id='.$what_t.'.id '."\n".
                'WHERE idf_tag_id IS NOT NULL '.$extra.' AND '.$what_t.'.project='.$this->id.' GROUP BY '.$tag_t.'.id, '.$tag_t.'.class, '.$tag_t.'.name ORDER BY '.$tag_t.'.class ASC, '.$tag_t.'.name ASC';
        }
        $tags = array();
        foreach ($this->_con->select($sql) as $idc) {
            $tag = new IDF_Tag($idc['id']);
            $tag->nb_use = $idc['nb_use'];
            // group by class
            if (!array_key_exists($tag->class, $tags)) {
                $tags[$tag->class] = array();
            }
            $tags[$tag->class][] = $tag;
        }
        return new Pluf_Template_ContextVars($tags);
    }

    /**
     * Get the repository size.
     *
     * @param bool Force to skip the cache (false)
     * @return int Size in byte or -1 if not available
     */
    public function getRepositorySize($force=false)
    {
        $last_eval = $this->getConf()->getVal('repository_size_check_date', 0);
        if (Pluf::f('idf_no_size_check', false) or
            (!$force and $last_eval > time()-172800)) {
            return $this->getConf()->getVal('repository_size', -1);
        }
        $this->getConf()->setVal('repository_size_check_date', time());
        $scm = IDF_Scm::get($this);
        $this->getConf()->setVal('repository_size', $scm->getRepositorySize());
        return $this->getConf()->getVal('repository_size', -1);
    }

    /**
     * Get the access url to the repository.
     *
     * This will return the right url based on the user.
     *
     * @param Pluf_User The user (null)
     * @param string    A specific commit to access
     */
    public function getSourceAccessUrl($user=null, $commit=null)
    {
        $right = $this->getConf()->getVal('source_access_rights', 'all');
        if (($user == null or $user->isAnonymous())
            and  $right == 'all' and !$this->private) {
            return $this->getRemoteAccessUrl($commit);
        }
        return $this->getWriteRemoteAccessUrl($user, $commit);
    }


    /**
     * Get the remote access url to the repository.
     *
     * This will always return the anonymous access url.
     *
     * @param string    A specific commit to access
     */
    public function getRemoteAccessUrl($commit=null)
    {
        $conf = $this->getConf();
        $scm = $conf->getVal('scm', 'git');
        $scms = Pluf::f('allowed_scm');
        Pluf::loadClass($scms[$scm]);
        return call_user_func(array($scms[$scm], 'getAnonymousAccessUrl'),
                              $this, $commit);
    }

    /**
     * Get the remote write access url to the repository.
     *
     * Some SCM have a remote access URL to write which is not the
     * same as the one to read. For example, you do a checkout with
     * git-daemon and push with SSH.
     *
     * @param string    A specific commit to access
     */
    public function getWriteRemoteAccessUrl($user,$commit=null)
    {
        $conf = $this->getConf();
        $scm = $conf->getVal('scm', 'git');
        $scms = Pluf::f('allowed_scm');
        return call_user_func(array($scms[$scm], 'getAuthAccessUrl'),
                              $this, $user, $commit);
    }

    /**
     * Get the web hook key.
     *
     * The goal is to get something predictable but from which one
     * cannot reverse find the secret key.
     */
    public function getWebHookKey()
    {
        return md5($this->id.sha1(Pluf::f('secret_key')).$this->shortname);
    }

    /**
     * Get the root name of the project scm
     *
     * @return string SCM root
     */
    public function getScmRoot()
    {
        $conf = $this->getConf();
        $roots = array(
                       'git' => 'master',
                       'svn' => 'HEAD',
                       'mercurial' => 'tip',
                       'mtn' => 'h:'.$conf->getVal('mtn_master_branch', '*'),
                       );
        $scm = $conf->getVal('scm', 'git');
        return $roots[$scm];
    }

    /**
     * Check that the object belongs to the project or rise a 404
     * error.
     *
     * By convention, all the objects belonging to a project have the
     * 'project' property set, so this is easy to check.
     *
     * @param Pluf_Model
     */
    public function inOr404($obj)
    {
        if ($obj->project != $this->id) {
            throw new Pluf_HTTP_Error404();
        }
    }

    /**
     * Utility function to get a configuration object.
     *
     * @return IDF_Conf
     */
    public function getConf()
    {
        if ($this->_pconf == null) {
            $this->_pconf = new IDF_Conf();
            $this->_pconf->setProject($this);
        }
        return $this->_pconf;
    }

    /**
     * Magic overload that falls back to the values of the internal configuration
     * if no getter / caller matched
     *
     * @param string $key
     */
    public function __get($key)
    {
        try {
            return parent::__get($key);
        }
        catch (Exception $e) {
            return $this->getConf()->getVal($key);
        }
    }

    /**
     * Get simple statistics about the project.
     *
     * This returns an associative array with number of tickets,
     * number of downloads, etc.
     *
     * @return array Stats
     */
    public function getStats()
    {
        $stats = array();
        $stats['total'] = 0;
        $what = array('downloads' => 'IDF_Upload',
                      'reviews'   => 'IDF_Review',
                      'issues'    => 'IDF_Issue',
                      'docpages'  => 'IDF_Wiki_Page',
                      'commits'   => 'IDF_Commit',
                      );
        foreach ($what as $key=>$m) {
            $i = Pluf::factory($m)->getCount(array('filter' => 'project='.(int)$this->id));
            $stats[$key] = $i;
            $stats['total'] += $i;
        }
        /**
         * [signal]
         *
         * IDF_Project::getStats
         *
         * [sender]
         *
         * IDF_Project
         *
         * [description]
         *
         * This signal allows an application to update the statistics
         * array of a project. For example to add the on disk size
         * of the repository if available.
         *
         * [parameters]
         *
         * array('project' => $project,
         *       'stats' => $stats)
         *
         */
        $params = array('project' => $this,
                        'stats' => $stats);
        Pluf_Signal::send('IDF_Project::getStats',
                          'IDF_Project', $params);
        return $stats;
    }

    /**
     * Needs to be called when you update the memberships of a
     * project.
     *
     * This will allow a plugin to, for example, update some access
     * rights to a repository.
     */
    public function membershipsUpdated()
    {
        /**
         * [signal]
         *
         * IDF_Project::membershipsUpdated
         *
         * [sender]
         *
         * IDF_Project
         *
         * [description]
         *
         * This signal allows an application to update the some access
         * rights to a repository when the project memberships is
         * updated.
         *
         * [parameters]
         *
         * array('project' => $project)
         *
         */
        $params = array('project' => $this);
        Pluf_Signal::send('IDF_Project::membershipsUpdated',
                          'IDF_Project', $params);
    }

    /**
     * Needs to be called when you create a project.
     *
     * We cannot put it into the postSave call as the configuration of
     * the project is not defined at that time.
     */
    function created()
    {
        /**
         * [signal]
         *
         * IDF_Project::created
         *
         * [sender]
         *
         * IDF_Project
         *
         * [description]
         *
         * This signal allows an application to perform special
         * operations at the creation of a project.
         *
         * [parameters]
         *
         * array('project' => $project)
         *
         */
        $params = array('project' => $this);
        Pluf_Signal::send('IDF_Project::created',
                          'IDF_Project', $params);
    }

    /**
     * The delete() call do not like circular references and the
     * IDF_Tag is creating some. We predelete to solve these issues.
     */
    public function preDelete()
    {
        /**
         * [signal]
         *
         * IDF_Project::preDelete
         *
         * [sender]
         *
         * IDF_Project
         *
         * [description]
         *
         * This signal allows an application to perform special
         * operations at the deletion of a project.
         *
         * [parameters]
         *
         * array('project' => $project)
         *
         */
        $params = array('project' => $this);
        Pluf_Signal::send('IDF_Project::preDelete',
                          'IDF_Project', $params);
        $what = array('IDF_Upload', 'IDF_Review', 'IDF_Issue',
                      'IDF_Wiki_Page', 'IDF_Wiki_Resource',
                      'IDF_Commit', 'IDF_Tag',
                      );
        foreach ($what as $m) {
            foreach (Pluf::factory($m)->getList(array('filter' => 'project='.(int)$this->id)) as $item) {
                $item->delete();
            }
        }
    }

    /**
     * Check if the project has one restricted tab.
     *
     * @return bool
     */
    public function isRestricted()
    {
        if ($this->_isRestricted !== null) {
            return $this->_isRestricted;
        }
        if ($this->private) {
            $this->_isRestricted = true;
            return true;
        }
        $tabs = array(
                      'source_access_rights',
                      'issues_access_rights',
                      'downloads_access_rights',
                      'wiki_access_rights',
                      'review_access_rights'
                      );
        $conf = $this->getConf();
        foreach ($tabs as $tab) {
            if (!in_array($conf->getVal($tab, 'all'),
                          array('all', 'none'))) {
                $this->_isRestricted = true;
                return true;
            }
        }
        $this->_isRestricted = false;
        return false;
    }

    /**
     * Returns an associative array of email addresses to notify about changes
     * in a certain tab like 'issues', 'source', and so on.
     *
     * @param string $tab
     * @return array Key is the email address, value is the preferred language setting
     */
    public function getNotificationRecipientsForTab($tab)
    {
        if (!in_array($tab, array('source', 'issues', 'downloads', 'wiki', 'review'))) {
            throw new Exception(sprintf('unknown tab %s', $tab));
        }

        $conf = $this->getConf();
        $recipients = array();
        $membership_data = $this->getMembershipData();

        if ($conf->getVal($tab.'_notification_owners_enabled', false)) {
            foreach ($membership_data['owners'] as $owner) {
                $recipients[$owner->email] = $owner->language;
            }
        }

        if ($conf->getVal($tab.'_notification_members_enabled', false)) {
            foreach ($membership_data['members'] as $member) {
                $recipients[$member->email] = $member->language;
            }
        }

        if ($conf->getVal($tab.'_notification_email_enabled', false)) {
            $addresses = preg_split('/\s*,\s*/',
                                $conf->getVal($tab.'_notification_email', ''),
                                -1, PREG_SPLIT_NO_EMPTY);

            // we use a default language setting for this plain list of
            // addresses, but we ensure that we do not overwrite an existing
            // address which might come with a proper setting already
            $languages = Pluf::f('languages', array('en'));
            foreach ($addresses as $address) {
                if (array_key_exists($address, $recipients))
                    continue;
                $recipients[$address] = $languages[0];
            }
        }

        return $recipients;
    }
}
