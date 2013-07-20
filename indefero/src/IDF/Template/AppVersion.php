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
 * AppVersion tag.
 *
 * Renders two meta tags that include the application's version and revision
 */
class IDF_Template_AppVersion extends Pluf_Template_Tag
{
    function start($file = 'IDF/version.php')
    {
        if (!Pluf::fileExists($file)) {
            return;
        }

        $info = include_once($file);
        if (!is_array($info)) {
            return;
        }

        if (array_key_exists('version', $info)) {
            echo '<meta name="indefero-version" content="'.$info['version'].'" />'."\n";
        }

        if (array_key_exists('revision', $info)) {
            if (strpos($info['revision'], '$') !== false) {
                $info['revision'] = 'unknown';
                $cmd = Pluf::f('idf_exec_cmd_prefix', '').
                       Pluf::f('git_path', 'git').
                       ' log -1 --format=%H';

                if (IDF_Scm::exec('IDF_Template_AppVersion::start', $cmd, $output)) {
                    $info['revision'] = trim(@$output[0]);
                }
            }
            echo '<meta name="indefero-revision" content="'.$info['revision'].'" />'."\n";
        }
    }
}

