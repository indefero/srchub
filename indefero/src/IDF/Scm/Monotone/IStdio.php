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
 * Monotone stdio interface
 *
 * @author Thomas Keller <me@thomaskeller.biz>
 */
interface IDF_Scm_Monotone_IStdio
{
    /**
     * Constructor
     */
    public function __construct(IDF_Project $project);

    /**
     * Starts the stdio process and resets the command counter
     */
    public function start();

    /**
     * Stops the stdio process and closes all pipes
     */
    public function stop();

    /**
     * Executes a command over stdio and returns its result
     *
     * @param array Array of arguments
     * @param array Array of options as key-value pairs. Multiple options
     *              can be defined in sub-arrays, like
     *              "r" => array("123...", "456...")
     * @return string
     */
    public function exec(array $args, array $options = array());

    /**
     * Returns the last out-of-band output for a previously executed
     * command as associative array with 'e' (error), 'w' (warning),
     * 'p' (progress) and 't' (ticker, unparsed) as keys
     *
     * @return array
     */
    public function getLastOutOfBandOutput();
}

