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
 * Storage of Email addresses
 *
 */
class IDF_EmailAddress extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_emailaddresses';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true,
                                  ),
                            'user' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('user'),
                                  ),
                            'address' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Email',
                                  'blank' => false,
                                  'verbose' => __('email'),
                                  'unique' => true,
                                  ),
                            );
        // WARNING: Not using getSqlTable on the Pluf_User object to
        // avoid recursion.
        $t_users = $this->_con->pfx.'users';
        $this->_a['views'] = array(
                              'join_user' =>
                              array(
                                    'join' => 'LEFT JOIN '.$t_users
                                    .' ON '.$t_users.'.id='.$this->_con->qn('user'),
                                    'select' => $this->getSelect().', '
                                    .$t_users.'.login AS login',
                                    'props' => array('login' => 'login'),
                                    )
                                   );
    }

    function get_email_addresses_for_user($user)
    {
        $addr = $user->get_idf_emailaddress_list();
        $addr[] = (object)array("address" => $user->email, "id" => -1, "user" => $user);
        return $addr;
    }

    function get_user_for_email_address($email)
    {
        $sql = new Pluf_SQL('email=%s', array($email));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if ($users->count() > 0) {
            return $users[0];
        }
        $sql = new Pluf_SQL('address=%s', array($email));
        $matches = Pluf::factory('IDF_EmailAddress')->getList(array('filter'=>$sql->gen()));
        if ($matches->count() > 0) {
            return new Pluf_User($matches[0]->user);
        }
        return null;
    }
}

