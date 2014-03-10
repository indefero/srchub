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
 * GoogleAnalytics middleware.
 *
 * Add the googleanalytics tracking code to the pages.
 */
class IDF_Middleware_GoogleAds
{
    /**
     * Process the response of a view.
     *
     * If the status code and content type are allowed, add the
     * tracking code.
     *
     * @param Pluf_HTTP_Request The request
     * @param Pluf_HTTP_Response The response
     * @return Pluf_HTTP_Response The response
     */
    function process_response($request, $response)
    {
        if (!Pluf::f('google_ads', false)) {
            return $response;
        }
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
        $replacead1 = $this->makead(Pluf::f("google_ads")["AD1"]);
        $replacead2 = $this->makead(Pluf::f("google_ads")["AD2"]);
        $response->content = str_replace('<!--AD1-->', $replacead1, $response->content);
        $response->content = str_replace('<!--AD2-->', $replacead2, $response->content);
        return $response;
    }

    private function makead($ad)
    {
        $ret = '<script type="text/javascript"><!--' . PHP_EOL;
        $ret .= 'google_ad_client = "' . $ad["google_ad_client"] . '";' . PHP_EOL;
        $ret .= 'google_ad_slot = "' . $ad["google_ad_slot"] . '";' . PHP_EOL;
        $ret .= 'google_ad_width = ' . $ad["google_ad_width"] . ';' . PHP_EOL;
        $ret .= 'google_ad_height = ' . $ad["google_ad_height"] . ';' . PHP_EOL;
        $ret .= "//-->" . PHP_EOL . "</script>";
        $ret .= '<script type="text/javascript" src="https://pagead2.googlesyndication.com/pagead/show_ads.js"></script>';
        return $ret;

    }
}
