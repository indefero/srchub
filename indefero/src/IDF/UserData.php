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
 * Thin wrapper around the general purpose Gconf data driver
 * to model a userdata object as key value store
 */
class IDF_UserData extends IDF_Gconf
{
    /** columns for the underlying model for which we do not want to
        override __get and __set */
    private static $protectedVars =
        array('id', 'model_class', 'model_id', 'vkey', 'vdesc');

    function __set($key, $value)
    {
        if (in_array($key, self::$protectedVars))
        {
            parent::__set($key, $value);
            return;
        }
        $this->setVal($key, $value);
    }

    function __get($key)
    {
        if (in_array($key, self::$protectedVars))
            return parent::__get($key);
        return $this->getVal($key, null);
    }

    public static function factory($user)
    {
        $conf = new IDF_UserData();
        $conf->setModel((object) array('_model'=>'IDF_UserData', 'id' => $user->id));
        $conf->initCache();
        return $conf;
    }
}
