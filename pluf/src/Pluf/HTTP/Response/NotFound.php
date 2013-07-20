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

class Pluf_HTTP_Response_NotFound extends Pluf_HTTP_Response
{
    function __construct($request)
    {
        $content = '';
        try {
            $context = new Pluf_Template_Context(array('query' => $request->query));
            $tmpl = new Pluf_Template('404.html');
            $content = $tmpl->render($context);
            $mimetype = null;
        } catch (Exception $e) {
            $mimetype = 'text/plain';
            $content = sprintf('The requested URL %s was not found on this server.'."\n"
                               .'Please check the URL and try again.'."\n\n".'404 - Not Found',
                               Pluf_esc($request->query));
        }
        parent::__construct($content, $mimetype);
        $this->status_code = 404;
    }
}
