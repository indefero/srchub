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
 * Similar to Pluf_Form_Field_Email, this form field validates one or more
 * email addresses separated by a comma
 */
class IDF_Form_Field_EmailList extends Pluf_Form_Field
{
    public $widget = 'Pluf_Form_Widget_TextInput';

    public function clean($value)
    {
        parent::clean($value);
        if (in_array($value, $this->empty_values)) {
            $value = '';
        }
        if ($value == '') {
            return $value;
        }
        $emails = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($emails as $email) {
            if (!Pluf_Utils::isValidEmail($email)) {
                throw new Pluf_Form_Invalid(__(
                    'Please enter one or more valid email addresses.'
                ));
            }
        }
        return implode(',', $emails);
    }
}
