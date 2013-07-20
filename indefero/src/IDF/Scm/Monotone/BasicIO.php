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

require_once 'IDF/Scm/Exception.php';

/**
 * Utility class to parse and compile basic_io stanzas
 *
 * @author Thomas Keller <me@thomaskeller.biz>
 */
class IDF_Scm_Monotone_BasicIO
{
    /**
     * Parses monotone's basic_io format
     *
     * Known quirks:
     * - does not handle multi-values starting with a hash '[]' (no known output)
     * - does not validate hashes (should be /[0-9a-f]{40}/i)
     * - does not handle forbidden \0
     *
     * @param string $in
     * @return array of arrays
     */
    public static function parse($in)
    {
        $pos = 0;
        $stanzas = array();
        $length = strlen($in);

        while ($pos < $length) {
            $stanza = array();
            while ($pos < $length) {
                if ($in[$pos] == "\n") break;

                $stanzaLine = array('key' => '', 'values' => array(), 'hash' => null);
                while ($pos < $length) {
                    $ch = $in[$pos];
                    if ($ch == '"' || $ch == '[') break;
                    ++$pos;
                    if ($ch == ' ') continue;
                    $stanzaLine['key'] .= $ch;
                }

                // ensure we don't look at a symbol w/o a value list
                if ($pos >= $length || $in[$pos] == "\n") {
                    unset($stanzaLine['values']);
                    unset($stanzaLine['hash']);
                }
                else {
                    if ($in[$pos] == '[') {
                        unset($stanzaLine['values']);
                        ++$pos; // opening square bracket
                        while ($pos < $length && $in[$pos] != ']') {
                            $stanzaLine['hash'] .= $in[$pos];
                            ++$pos;
                        }
                        ++$pos; // closing square bracket
                    }
                    else
                    {
                        unset($stanzaLine['hash']);
                        $valCount = 0;
                        // if hashs and plain values are encountered in the same
                        // value list, we add the hash values as simple values as well
                        while ($in[$pos] == '"' || $in[$pos] == '[') {
                            $isHashValue = $in[$pos] == '[';
                            ++$pos; // opening quote / bracket
                            $stanzaLine['values'][$valCount] = '';
                            while ($pos < $length) {
                                $ch = $in[$pos]; $pr = $in[$pos-1];
                                if (($isHashValue && $ch == ']')
                                    ||(!$isHashValue && $ch == '"' && $pr != '\\'))
                                     break;
                                ++$pos;
                                $stanzaLine['values'][$valCount] .= $ch;
                            }
                            ++$pos; // closing quote

                            if (!$isHashValue) {
                                $stanzaLine['values'][$valCount] = str_replace(
                                    array("\\\\", "\\\""),
                                    array("\\", "\""),
                                    $stanzaLine['values'][$valCount]
                                );
                            }

                            if ($pos >= $length)
                                break;

                            if ($in[$pos] == ' ') {
                                ++$pos; // space
                                ++$valCount;
                            }
                        }
                    }
                }

                $stanza[] = $stanzaLine;
                ++$pos; // newline
            }
            $stanzas[] = $stanza;
            ++$pos; // newline
        }
        return $stanzas;
    }

    /**
     * Compiles monotone's basicio format
     *
     * Known quirks:
     * - does not validate keys for /[a-z_]+/
     * - does not validate hashes (should be /[0-9a-f]{40}/i)
     * - does not support intermixed value / hash formats
     * - does not handle forbidden \0
     *
     * @param array $in Array of arrays
     * @return string
     */
    public static function compile($in)
    {
        $out = "";
        $first = true;
        foreach ((array)$in as $sx => $stanza) {
            if ($first)
                $first = false;
            else
                $out .= "\n";

            $maxkeylength = 0;
            foreach ((array)$stanza as $lx => $line) {
                if (!array_key_exists('key', $line) || empty($line['key'])) {
                    throw new IDF_Scm_Exception(
                        '"key" not found in basicio stanza '.$sx.', line '.$lx
                    );
                }
                $maxkeylength = max($maxkeylength, strlen($line['key']));
            }

            foreach ((array)$stanza as $lx => $line) {
                $out .= str_pad($line['key'], $maxkeylength, ' ', STR_PAD_LEFT);

                if (array_key_exists('hash', $line)) {
                    $out .= ' ['.$line['hash'].']';
                } else
                if (array_key_exists('values', $line)) {
                    if (!is_array($line['values']) || count($line['values']) == 0) {
                        throw new IDF_Scm_Exception(
                            '"values" must be an array of a size >= 1 '.
                            'in basicio stanza '.$sx.', line '.$lx
                        );
                    }
                    foreach ($line['values'] as $value) {
                        $out .= ' "'.str_replace(
                             array("\\", "\""),
                             array("\\\\", "\\\""),
                             $value).'"';
                    }
                }

                $out .= "\n";
            }
        }
        return $out;
    }
}

