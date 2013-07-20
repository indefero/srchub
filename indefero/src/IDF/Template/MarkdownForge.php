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

Pluf::loadFunction('Pluf_Text_MarkDown_parse');
Pluf::loadFunction('IDF_Template_safePregReplace');

class IDF_Template_MarkdownForge extends Pluf_Template_Tag
{
    private $request;

    public function start($text, $request)
    {
        $this->request = $request;
        $filter = new IDF_Template_MarkdownPrefilter();
        $text = $filter->go(Pluf_Text_MarkDown_parse($text));

        // replace {}-macros with the corresponding template rendering
        echo IDF_Template_safePregReplace('#\{(\w+)(?:,\s*([^\}]+))?\}#im',
                                          array($this, 'callbackMacros'),
                                          $text);
    }

    public function callbackMacros($matches)
    {
        @list(, $macro, $opts) = $matches;
        $known_macros = array('projectlist');
        if (!in_array($macro, $known_macros)) {
            return $matches[0];
        }
        $callbackName = 'callback'.ucfirst(strtolower($macro)).'Macro';
        return $this->callbackProjectlistMacro($opts);
    }

    public function callbackProjectlistMacro($opts)
    {
        $validOpts = array(
            'label' => '/^\d+|(\w+:)?\w+$/',
            'order' => '/^name|activity$/',
            'limit' => '/^\d+$/',
        );

        $parsedOpts = array();
        // FIXME: no support for escaping yet in place
        $opts = preg_split('/\s*,\s*/', $opts, -1, PREG_SPLIT_NO_EMPTY);
        foreach ((array)@$opts as $opt)
        {
            list($key, $value) = preg_split('/\s*=\s*/', $opt, 2);
            if (!array_key_exists($key, $validOpts)) {
                continue;
            }
            if (!preg_match($validOpts[$key], $value)) {
                continue;
            }
            $parsedOpts[$key] = $value;
        }

        $tag = false;
        if (!empty($parsedOpts['label'])) {
            if (is_numeric($parsedOpts['label'])) {
                $tag = Pluf::factory('IDF_Tag')->get($parsedOpts['label']);
            } else {
                @list($class, $name) = preg_split('/:/', $parsedOpts['label'], 2);
                if (empty($name)) {
                    $name = $class;
                    $class = IDF_TAG_DEFAULT_CLASS;
                }
                $sql = new Pluf_SQL('class=%s AND lcname=%s AND project IS NULL',
                                    array(strtolower($class), mb_strtolower($name)));
                $tag = Pluf::factory('IDF_Tag')->getOne(array('filter' => $sql->gen()));
            }
            // ignore non-global tags
            if ($tag !== false && $tag->project > 0) {
                $tag = false;
            }
        }

        $order = 'name';
        if (!empty($parsedOpts['order'])) {
            $order = $parsedOpts['order'];
        }

        $projects = IDF_Views::getProjects($this->request->user, $tag, $order);
        if (!empty($parsedOpts['limit']) && $parsedOpts['limit'] < count($projects)) {
            // there is no array_slice on ArrayObject, do'h!
            $projectsCopy = array();
            for ($i=0; $i<$parsedOpts['limit']; ++$i)
                $projectsCopy[] = $projects[$i];
            $projects = $projectsCopy;
        }

        $tmpl = new Pluf_Template('idf/project-list.html');
        $context = new Pluf_Template_Context(array(
            'projects' => $projects,
            'order' => 'name',
        ));
        return $tmpl->render($context);
    }
}

