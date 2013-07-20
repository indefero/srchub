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
 * A lightweight model for the singleton forge entity
 */
class IDF_Forge
{
    public $_model = __CLASS__;

    public $id = 1;

    /**
     * @var IDF_Gconf
     */
    private $conf;

    private function __construct() {
        $this->conf = new IDF_Gconf();
        $this->conf->setModel($this);
    }

    public static function instance() {
        return new IDF_Forge();
    }

    public function getProjectLabels($default = '') {
        return $this->conf->getVal('project_labels', $default);
    }

    public function setProjectLabels($labels) {
        $this->conf->setVal('project_labels', $labels);
    }

    public function setCustomForgePageEnabled($enabled) {
        $this->conf->setVal('custom_forge_page_enabled', $enabled);
    }

    public function isCustomForgePageEnabled($default = false) {
        return $this->conf->getVal('custom_forge_page_enabled', $default);
    }

    public function getCustomForgePageContent($default = '') {
        return $this->conf->getVal('custom_forge_page_content', $default);
    }

    public function setCustomForgePageContent($content) {
        $this->conf->setVal('custom_forge_page_content', $content);
    }
}
