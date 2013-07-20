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
 * PHP sets the backtrack limit quite low, so some (harder to analyze) regexes may fail
 * unexpectedly on large inputs or weird cornercases (see issue 618). Unfortunately this
 * fix does not always work and the execution time gets bigger the bigger we set the limit,
 * so in case PCRE fails to analyze the input string and preg_replace(_callback) returns NULL,
 * we at least return the input string unaltered.
 *
 * @param $pattern  The pattern
 * @param $mixed    Callback or replacement string
 * @param $input    The input
 * @return The output
 */
function IDF_Template_safePregReplace($pattern, $mixed, $input)
{
    $pcre_backtrack_limit = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', 10000000);
   
    if (is_string($mixed) && !function_exists($mixed))
        $output = preg_replace($pattern, $mixed, $input);
    else
        $output = preg_replace_callback($pattern, $mixed, $input);

    if ($output === null)
        $output = $input;

    ini_set('pcre.backtrack_limit', $pcre_backtrack_limit);
    return $output;
}
