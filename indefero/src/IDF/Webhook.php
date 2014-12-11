<?php
require_once PLUF_PATH . '/Pluf/thirdparty/ccurl.php';
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
 * Management of the webhooks.
 *
 * The class provides the tools to perform the POST request with
 * authentication for the webhooks.
 *
 */
class IDF_Webhook
{
    /**
     * Perform the request given the webhook payload.
     *
     * @param array Payload
     * @return bool Success or error
     */
    public static function processNotification($payload)
    {
        $data = json_encode($payload['to_send']);
        $sign_header = 'X-Web-Hook-Hmac';
        // use the old signature header if we're asked for
        if (Pluf::f('webhook_processing', '') === 'compat') {
            // This should really be X-HEADER
            $sign_header = 'X-Post-Commit-Hook-Hmac';
        }
        $sign = hash_hmac('md5', $data, $payload['authkey']);

        $url = $payload['url'];

        $curl = new ccurl($url,true, 15, 0);
        $curl->setPost($data);
        $curl->addHeader("$sign_header: $sign");
        $curl->addHeader("Content-Type: application/json");
        $curl->createCurl();

        return true;
    }


    /**
     * Process the webhook.
     *
     */
    public static function process($sender, &$params)
    {
        $item = $params['item'];
        if (!in_array($item->type, array('new_commit', 'upload'))) {
            // We do nothing.
            return;
        }
        if (isset($params['res']['IDF_Webhook::process']) and
            $params['res']['IDF_Webhook::process'] == true) {
            // Already processed.
            return;
        }
        if ($item->payload['url'] == '') {
            // We do nothing.
            return;
        }
        // We have either to retry or to push for the first time.
        $res = self::processNotification($item->payload);
        if ($res) {
            $params['res']['IDF_Webhook::process'] = true;
        } elseif ($item->trials >= 9) {
            // We are at trial 10, give up
            $params['res']['IDF_Webhook::process'] = true;
        } else {
            // Need to try again
            $params['res']['IDF_Webhook::process'] = false;
        }
    }
}
