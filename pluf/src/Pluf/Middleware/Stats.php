<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume Framework, a simple PHP Application Framework.
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# Plume Framework is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License, or
# (at your option) any later version.
#
# Plume Framework is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Debug middleware.
 *
 * Simply display small debug information at the end of the page. It
 * requires the xdebug extension.
 */
class Pluf_Middleware_Stats
{
    /**
     * Process the response of a view.
     *
     * If the status code and content type are allowed, add the debug
     * information. Debug must be set to true in the config file to
     * active it.
     *
     * @param Pluf_HTTP_Request The request
     * @param Pluf_HTTP_Response The response
     * @return Pluf_HTTP_Response The response
     */
    function process_response($request, $response)
    {
        if (!in_array($response->status_code, 
                     array(200, 201, 202, 203, 204, 205, 206, 404, 501))) {
            return $response;
        }
        $ok = false;
        $cts = array('text/html', 'text/html', 'application/xhtml+xml');
        foreach ($cts as $ct) {
            if (false !== strripos($response->headers['Content-Type'], $ct)) {
                $ok = true;
                break;
            }
        }
        if ($ok == false) {
            return $response;
        }
	if (Pluf::f('db_debug'))
		$text = "Page rendered in " . sprintf('%.5f', (microtime(true) - $GLOBALS['_PX_starttime'])) . "s using " . count($GLOBALS['_PX_debug_data']['sql_queries']) . " queries.";
	else
		$text = "Page rendered in " . sprintf('%.5f', (microtime(true) - $GLOBALS['_PX_starttime'])) . "s.";
        $response->content = str_replace('</body>', $text.'</body>', $response->content);
        return $response;
    }
}
