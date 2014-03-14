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
 * File utilities.
 *
 */
class IDF_FileUtil
{
    /**
     * Extension supported by the syntax highlighter.
     */
    public static $supportedExtenstions = array(
              'ascx', 'ashx', 'asmx', 'aspx', 'browser', 'bsh', 'c', 'cl', 'cc',
              'config', 'cpp', 'cs', 'csh', 'csproj', 'css', 'cv', 'cyc', 'el', 'fs',
              'h', 'hh', 'hpp', 'hs', 'html', 'html', 'java', 'js', 'lisp', 'master',
              'pas', 'perl', 'php', 'pl', 'pm', 'py', 'rb', 'scm', 'sh', 'sitemap',
              'skin', 'sln', 'svc', 'vala', 'vb', 'vbproj', 'vbs', 'wsdl', 'xhtml',
              'xml', 'xsd', 'xsl', 'xslt');

    public static $map = array("cxx" => "cpp", "h" => "cpp", "hpp" => "cpp", "rc"=>"text", "sh"=>"bash", "cs"=>"csharp");

    public static $syntaxhighlightext = array("as3", "cf", "cpp", "c", "css", "pas", "diff", "patch", "erl", "java", "jfx", "js", "pl", "php", "py", "rb", "sass", "scss", "scala", "sql", "vb", );

    /**
     * Test if an extension is supported by the syntax highlighter.
     *
     * @param string The extension to test
     * @return bool
     */
    public static function isSupportedExtension($extension)
    {
        return in_array($extension, self::$supportedExtenstions);
    }

    /**
     * Returns a HTML snippet with a line-by-line pre-rendered table
     * for the given source content
     *
     * @param array file information as returned by getMimeType or getMimeTypeFromContent
     * @param string the content of the file
     * @return string
     */
    public static function highLight($fileinfo, $content)
    {

        $pretty = '';
        if (self::isSupportedExtension($fileinfo[2])) {
            $pretty = ' prettyprint';
        }
        $table = array();
        $i = 1;
        /*foreach (self::splitIntoLines($content) as $line) {
            $table[] = '<tr class="c-line"><td class="code-lc" id="L'.$i.'"><a href="#L'.$i.'">'.$i.'</a></td>'
                .'<td class="code mono'.$pretty.'">'.self::emphasizeControlCharacters(Pluf_esc($line)).'</td></tr>';
            $i++;
        }
        return Pluf_Template::markSafe(implode("\n", $table));*/
        //var_dump($fileinfo);
        $ext = "";
        if (in_array($fileinfo[2], self::$syntaxhighlightext))
            $ext = $fileinfo[2];
        elseif (array_key_exists($fileinfo[2], self::$map))
            $ext = self::$map[$fileinfo[2]];
        else
            $ext = "text";

        $content = '<div id="highlight"><script type="syntaxhighlighter" class="brush: ' . $ext . '">' . $content . '</script></div>';
        return  Pluf_Template::markSafe($content);
    }

    /**
     * Find the mime type of a file.
     *
     * Use /etc/mime.types to find the type.
     *
     * @param string Filename/Filepath
     * @param array  Mime type found or 'application/octet-stream', basename, extension
     */
    public static function getMimeType($file)
    {
        static $mimes = null;
        if ($mimes == null) {
            $mimes = array();
            $src = Pluf::f('idf_mimetypes_db', '/etc/mime.types');
            $filecontent = @file_get_contents($src);
            if ($filecontent !== false) {
                $mimes = preg_split("/\015\012|\015|\012/", $filecontent);
            }
        }

        $info = pathinfo($file);
        if (isset($info['extension'])) {
            foreach ($mimes as $mime) {
                if ('#' != substr($mime, 0, 1)) {
                    $elts = preg_split('/ |\t/', $mime, -1, PREG_SPLIT_NO_EMPTY);
                    if (in_array($info['extension'], $elts)) {
                        return array($elts[0], $info['basename'], $info['extension']);
                    }
                }
            }
        } else {
            // we consider that if no extension and base name is all
            // uppercase, then we have a text file.
            if ($info['basename'] == strtoupper($info['basename'])) {
                return array('text/plain', $info['basename'], 'txt');
            }
            $info['extension'] = 'bin';
        }
        return array('application/octet-stream', $info['basename'], $info['extension']);
    }

    /**
     * Find the mime type of a file using the fileinfo class.
     *
     * @param string Filename/Filepath
     * @param string File content
     * @return array Mime type found or 'application/octet-stream', basename, extension
     */
    public static function getMimeTypeFromContent($file, $filedata)
    {
        $info = pathinfo($file);
        $res = array('application/octet-stream',
                     $info['basename'],
                     isset($info['extension']) ? $info['extension'] : 'bin');
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mime = finfo_buffer($finfo, $filedata);
            finfo_close($finfo);
            if ($mime) {
                $res[0] = $mime;
            }
            if (!isset($info['extension']) && $mime) {
                $res[2] = (0 === strpos($mime, 'text/')) ? 'txt' : 'bin';
            } elseif (!isset($info['extension'])) {
                $res[2] = 'bin';
            }
        }
        return $res;
    }

    /**
     * Splits a string into separate lines while retaining the individual
     * line ending character for every line.
     *
     * OS 9 line endings are not supported.
     *
     * @param string content
     * @param boolean if true, skip completely empty lines
     * @return string
     */
    public static function splitIntoLines($content, $skipEmpty = false)
    {
        $last_off = 0;
        $lines = array();
        while (preg_match("/\r\n|\n/", $content, $m, PREG_OFFSET_CAPTURE, $last_off)) {
            $next_off = strlen($m[0][0]) + $m[0][1];
            $line = substr($content, $last_off, $next_off - $last_off);
            $last_off = $next_off;
            if ($line !== $m[0][0] || !$skipEmpty) $lines[] = $line;
        }
        $line = substr($content, $last_off);
        if ($line !== false && strlen($line) > 0) $lines[] = $line;
        return $lines;
    }

    /**
     * This translates most of the C0 ASCII control characters into
     * their visual counterparts in the 0x24## unicode plane
     * (http://en.wikipedia.org/wiki/C0_and_C1_control_codes).
     *
     * We could add DEL (0x7F) to this set, but unfortunately this
     * is not nicely mapped to 0x247F in the control plane, but 0x2421
     * and adding an if expression below just for this is a little bit
     * of a hassle. And of course, the more esoteric ones from C1 are
     * missing as well...
     *
     * @param string $content
     * @return string
     */
    public static function emphasizeControlCharacters($content)
    {
        return preg_replace(
            '/([\x00-\x1F])/ue',
            '"<span class=\"ctrl-char\" title=\"0x".bin2hex("\\1")."\">&#x24".bin2hex("\\1")."</span>"',
            $content);
    }

    /**
     * Find if a given mime type is a text file.
     * This uses the output of the self::getMimeType function.
     *
     * @param array (Mime type, file name, extension)
     * @return bool Is text
     */
    public static function isText($fileinfo)
    {
        if (0 === strpos($fileinfo[0], 'text/')) {
            return true;
        }
        $ext = 'mdtext php-dist h gitignore diff patch';
        $extra_ext = trim(Pluf::f('idf_extra_text_ext', ''));
        if (!empty($extra_ext))
            $ext .= ' ' . $extra_ext;
        $ext = array_merge(self::$supportedExtenstions, explode(' ' , $ext));
        return (in_array($fileinfo[2], $ext));
    }
}
