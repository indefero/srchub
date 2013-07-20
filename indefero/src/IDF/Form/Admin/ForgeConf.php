<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright(C) 2008-2011 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
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
 * Configuration of the forge's start page.
 */
class IDF_Form_Admin_ForgeConf extends Pluf_Form
{
    public function initFields($extra=array())
    {
        $this->fields['enabled'] = new Pluf_Form_Field_Boolean(
                              array('required' => false,
                                    'label' => __('Custom forge page enabled'),
                                    'widget' => 'Pluf_Form_Widget_CheckboxInput',
                                    ));
        $this->fields['content'] = new Pluf_Form_Field_Varchar(
                              array('required' => true,
                                    'label' => __('Content'),
                                    'widget' => 'Pluf_Form_Widget_TextareaInput',
                                    'widget_attrs' => array(
                                        'cols' => 68,
                                        'rows' => 26,
                                    ),
                                    ));
    }
}
