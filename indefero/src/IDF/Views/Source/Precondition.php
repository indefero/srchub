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

class IDF_Views_Source_Precondition
{
    /**
     * Ensures that the configured SCM for the project is available
     *
     * @param $request
     * @return true | Pluf_HTTP_Response_Redirect
     */
    static public function scmAvailable($request)
    {
        $scm = IDF_Scm::get($request->project);
        if (!$scm->isAvailable()) {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::help',
                                            array($request->project->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        return true;
    }

    /**
     * Validates the revision given in the URL path and acts accordingly
     *
     * @param $request
     * @return true | Pluf_HTTP_Response_Redirect
     * @throws Exception
     */
    static public function revisionValid($request)
    {
        list($url_info, $url_matches) = $request->view;
        list(, $project, $commit) = $url_matches;

        $scm = IDF_Scm::get($request->project);
        $res = $scm->validateRevision($commit);
        switch ($res) {
            case IDF_Scm::REVISION_VALID:
                return true;
            case IDF_Scm::REVISION_INVALID:
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::invalidRevision',
                                                array($request->project->shortname, $commit));
                return new Pluf_HTTP_Response_Redirect($url);
            case IDF_Scm::REVISION_AMBIGUOUS:
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::disambiguateRevision',
                                                array($request->project->shortname,
                                                      $commit,
                                                      $url_info['model'].'::'.$url_info['method']));
                return new Pluf_HTTP_Response_Redirect($url);
            default:
                throw new Exception('unknown validation result: '. $res);
        }
    }
}
