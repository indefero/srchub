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

class IDF_Form_UploadArchiveHelper
{
    private $file = null;
    private $entries = array();

    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Validates the archive; throws a invalid form exception in case the
     * archive contains invalid data or cannot be read.
     */
    public function validate()
    {
        if (!file_exists($this->file)) {
            throw new Pluf_Form_Invalid(__('The archive does not exist.'));
        }

        $za = new ZipArchive();
        $res = $za->open($this->file);
        if ($res !== true) {
            throw new Pluf_Form_Invalid(
                sprintf(__('The archive could not be read (code %d).'), $res));
        }

        $manifest = $za->getFromName('manifest.xml');
        if ($manifest === false) {
            throw new Pluf_Form_Invalid(__('The archive does not contain a manifest.xml.'));
        }

        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($manifest);
        if ($xml === false) {
            $error = libxml_get_last_error();
            throw new Pluf_Form_Invalid(
                sprintf(__('The archive\'s manifest is invalid: %s'), $error->message));
        }

        foreach (@$xml->file as $idx => $file)
        {
            $entry = array(
                'name' => (string)@$file->name,
                'summary' => (string)@$file->summary,
                'description' => (string)@$file->description,
                'replaces' => (string)@$file->replaces,
                'labels' => array(),
                'stream' => null
            );

            if (empty($entry['name'])) {
                throw new Pluf_Form_Invalid(
                    sprintf(__('The entry %d in the manifest is missing a file name.'), $idx));
            }

            if (empty($entry['summary'])) {
                throw new Pluf_Form_Invalid(
                    sprintf(__('The entry %d in the manifest is missing a summary.'), $idx));
            }

            if ($entry['name'] === 'manifest.xml') {
                throw new Pluf_Form_Invalid(__('The manifest must not reference itself.'));
            }

            if ($za->locateName($entry['name']) === false) {
                throw new Pluf_Form_Invalid(
                    sprintf(__('The entry %s in the manifest does not exist in the archive.'), $entry['name']));
            }

            if (in_array($entry['name'], $this->entries)) {
                throw new Pluf_Form_Invalid(
                    sprintf(__('The entry %s in the manifest is referenced more than once.'), $entry['name']));
            }

            if ($file->labels) {
                foreach (@$file->labels->label as $label) {
                    $entry['labels'][] = (string)$label;
                }
            }

            // FIXME: remove this once we allow more than six labels everywhere
            if (count($entry['labels']) > 6) {
                throw new Pluf_Form_Invalid(
                    sprintf(__('The entry %s in the manifest has more than the six allowed labels set.'), $entry['name']));
            }

            $this->entries[$entry['name']] = $entry;
        }

        $za->close();
    }

    /**
     * Returns all entry names
     *
     * @return array of string
     */
    public function getEntryNames()
    {
        return array_keys($this->entries);
    }

    /**
     * Returns meta data for the given entry
     *
     * @param string $name
     * @throws Exception
     */
    public function getMetaData($name)
    {
        if (!array_key_exists($name, $this->entries)) {
            throw new Exception('unknown file ' . $name);
        }
        return $this->entries[$name];
    }

    /**
     * Extracts the file entry $name at $path
     *
     * @param string $name
     * @param string $path
     * @throws Exception
     */
    public function extract($name, $path)
    {
        if (!array_key_exists($name, $this->entries)) {
            throw new Exception('unknown file ' . $name);
        }
        $za = new ZipArchive();
        $za->open($this->file);
        $za->extractTo($path, $name);
        $za->close();
    }
}
