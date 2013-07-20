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
 * Diff parser.
 *
 */
class IDF_Diff
{
    public $path_strip_level = 0;
    protected $lines = array();

    public $files = array();

    public function __construct($diff, $path_strip_level = 0)
    {
        $this->path_strip_level = $path_strip_level;
        $this->lines = IDF_FileUtil::splitIntoLines($diff, true);
    }

    public function parse()
    {
        $current_file = '';
        $current_chunk = 0;
        $lline = 0;
        $rline = 0;
        $files = array();
        $indiff = false; // Used to skip the headers in the git patches
        $i = 0; // Used to skip the end of a git patch with --\nversion number
        $diffsize = count($this->lines);
        while ($i < $diffsize) {
            // look for the potential beginning of a diff
            if (substr($this->lines[$i], 0, 4) !== '--- ') {
                $i++;
                continue;
            }

            // we're inside a diff candiate
            $oldfileline = $this->lines[$i++];
            $newfileline = $this->lines[$i++];
            if (substr($newfileline, 0, 4) !== '+++ ') {
                // not a valid diff here, move on
                continue;
            }

            // use new file name by default
            preg_match("/^\+\+\+ ([^\t\n\r]+)/", $newfileline, $m);
            $current_file = $m[1];
            if ($current_file === '/dev/null') {
                // except if it's /dev/null, use the old one instead
                // eg. mtn 0.48 and newer
                preg_match("/^--- ([^\t\r\n]+)/", $oldfileline, $m);
                $current_file = $m[1];
            }
            if ($this->path_strip_level > 0) {
                $fileparts = explode('/', $current_file, $this->path_strip_level+1);
                $current_file = array_pop($fileparts);
            }
            $current_chunk = 0;
            $files[$current_file] = array();
            $files[$current_file]['chunks'] = array();
            $files[$current_file]['chunks_def'] = array();

            while ($i < $diffsize && substr($this->lines[$i], 0, 3) === '@@ ') {
                $elems = preg_match('/@@ -(\d+),?(\d*) \+(\d+),?(\d*) @@.*/',
                                    $this->lines[$i++], $results);
                if ($elems != 1) {
                    // hunk is badly formatted
                    break;
                }
                $delstart = $results[1];
                $dellines = $results[2] === '' ? 1 : $results[2];
                $addstart = $results[3];
                $addlines = $results[4] === '' ? 1 : $results[4];

                $files[$current_file]['chunks_def'][] = array(
                    array($delstart, $dellines), array($addstart, $addlines)
                );
                $files[$current_file]['chunks'][] = array();

                while ($i < $diffsize && ($addlines >= 0 || $dellines >= 0)) {
                    $linetype = $this->lines[$i] != '' ? $this->lines[$i][0] : false;
                    $content = substr($this->lines[$i], 1);
                    switch ($linetype) {
                        case ' ':
                            $files[$current_file]['chunks'][$current_chunk][] =
                                array($delstart, $addstart, $content);
                            $dellines--;
                            $addlines--;
                            $delstart++;
                            $addstart++;
                            break;
                        case '+':
                            $files[$current_file]['chunks'][$current_chunk][] =
                                array('', $addstart, $content);
                            $addlines--;
                            $addstart++;
                            break;
                        case '-':
                            $files[$current_file]['chunks'][$current_chunk][] =
                                array($delstart, '', $content);
                            $dellines--;
                            $delstart++;
                            break;
                        case '\\':
                            // no new line at the end of this file; remove pseudo new line from last line
                            $cur = count($files[$current_file]['chunks'][$current_chunk]) - 1;
                            $files[$current_file]['chunks'][$current_chunk][$cur][2] =
                                rtrim($files[$current_file]['chunks'][$current_chunk][$cur][2], "\r\n");
                            continue;
                        default:
                            break 2;
                    }
                    $i++;
                }
                $current_chunk++;
            }
        }
        $this->files = $files;
        return $files;
    }

    /**
     * Return the html version of a parsed diff.
     */
    public function as_html()
    {
        $out = '';
        foreach ($this->files as $filename => $file) {
            $pretty = '';
            $fileinfo = IDF_FileUtil::getMimeType($filename);
            if (IDF_FileUtil::isSupportedExtension($fileinfo[2])) {
                $pretty = ' prettyprint';
            }

            $cc = 1;
            $offsets = array();
            $contents = array();

            foreach ($file['chunks'] as $chunk) {
                foreach ($chunk as $line) {
                    list($left, $right, $content) = $line;
                    if ($left and $right) {
                        $class = 'context';
                    } elseif ($left) {
                        $class = 'removed';
                    } else {
                        $class = 'added';
                    }

                    $offsets[] = sprintf('<td>%s</td><td>%s</td>', $left, $right);
                    $content = IDF_FileUtil::emphasizeControlCharacters(Pluf_esc($content));
                    $contents[] = sprintf('<td class="%s%s mono">%s</td>', $class, $pretty, $content);
                }
                if (count($file['chunks']) > $cc) {
                    $offsets[]  = '<td class="next">...</td><td class="next">...</td>';
                    $contents[] = '<td class="next"></td>';
                }
                $cc++;
            }

            list($added, $removed) = end($file['chunks_def']);

            $added = $added[0] + $added[1];
            $leftwidth = 0;
            if ($added > 0)
                $leftwidth = ((ceil(log10($added)) + 1) * 8) + 17;

            $removed = $removed[0] + $removed[1];
            $rightwidth = 0;
            if ($removed > 0)
                $rightwidth = ((ceil(log10($removed)) + 1) * 8) + 17;

            // we need to correct the width of a single column a little
            // to take less space and to hide the empty one
            $class = '';
            if ($leftwidth == 0) {
                $class = 'left-hidden';
                $rightwidth -= floor(log10($removed));
            }
            else if ($rightwidth == 0) {
                $class = 'right-hidden';
                $leftwidth -= floor(log10($added));
            }

            $inner_linecounts =
              '<table class="diff-linecounts '.$class.'">' ."\n".
                '<colgroup><col width="'.$leftwidth.'" /><col width="'. $rightwidth.'" /></colgroup>' ."\n".
                '<tr class="line">' .
                  implode('</tr>'."\n".'<tr class="line">', $offsets).
                '</tr>' ."\n".
              '</table>' ."\n";


            $inner_contents =
              '<table class="diff-contents">' ."\n".
                '<tr class="line">' .
                  implode('</tr>'."\n".'<tr class="line">', $contents) .
                '</tr>' ."\n".
              '</table>' ."\n";

            $out .= '<table class="diff unified">' ."\n".
                      '<colgroup><col width="'.($leftwidth + $rightwidth + 1).'" /><col width="*" /></colgroup>' ."\n".
                      '<tr id="diff-'.md5($filename).'">'.
                        '<th colspan="2">'.Pluf_esc($filename).'</th>'.
                      '</tr>' ."\n".
                      '<tr>' .
                        '<td>'. $inner_linecounts .'</td>'. "\n".
                        '<td><div class="scroll">'. $inner_contents .'</div></td>'.
                      '</tr>' ."\n".
                    '</table>' ."\n";
        }

        return Pluf_Template::markSafe($out);
    }

    /**
     * Review patch.
     *
     * Given the original file as a string and the parsed
     * corresponding diff chunks, generate a side by side view of the
     * original file and new file with added/removed lines.
     *
     * Example of use:
     *
     * $diff = new IDF_Diff(file_get_contents($diff_file));
     * $orig = file_get_contents($orig_file);
     * $diff->parse();
     * echo $diff->fileCompare($orig, $diff->files[$orig_file], $diff_file);
     *
     * @param string Original file
     * @param array Chunk description of the diff corresponding to the file
     * @param string Original file name
     * @param int Number of lines before/after the chunk to be displayed (10)
     * @return Pluf_Template_SafeString The table body
     */
    public function fileCompare($orig, $chunks, $filename, $context=10)
    {
        $orig_lines = IDF_FileUtil::splitIntoLines($orig);
        $new_chunks = $this->mergeChunks($orig_lines, $chunks, $context);
        return $this->renderCompared($new_chunks, $filename);
    }

    private function mergeChunks($orig_lines, $chunks, $context=10)
    {
        $spans = array();
        $new_chunks = array();
        $min_line = 0;
        $max_line = 0;
        //if (count($chunks['chunks_def']) == 0) return '';
        foreach ($chunks['chunks_def'] as $chunk) {
            $start = ($chunk[0][0] > $context) ? $chunk[0][0]-$context : 0;
            $end = (($chunk[0][0]+$chunk[0][1]+$context-1) < count($orig_lines)) ? $chunk[0][0]+$chunk[0][1]+$context-1 : count($orig_lines);
            $spans[] = array($start, $end);
        }
        // merge chunks/get the chunk lines
        // these are reference lines
        $chunk_lines = array();
        foreach ($chunks['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                $chunk_lines[] = $line;
            }
        }
        $i = 0;
        foreach ($chunks['chunks'] as $chunk) {
            $n_chunk = array();
            // add lines before
            if ($chunk[0][0] > $spans[$i][0]) {
                for ($lc=$spans[$i][0];$lc<$chunk[0][0];$lc++) {
                    $exists = false;
                    foreach ($chunk_lines as $line) {
                        if ($lc == $line[0]
                            or ($chunk[0][1]-$chunk[0][0]+$lc) == $line[1]) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $orig = isset($orig_lines[$lc-1]) ? $orig_lines[$lc-1] : '';
                        $n_chunk[] = array(
                                           $lc,
                                           $chunk[0][1]-$chunk[0][0]+$lc,
                                           $orig
                                           );
                    }
                }
            }
            // add chunk lines
            foreach ($chunk as $line) {
                $n_chunk[] = $line;
            }
            // add lines after
            $lline = $line;
            if (!empty($lline[0]) and $lline[0] < $spans[$i][1]) {
                for ($lc=$lline[0];$lc<=$spans[$i][1];$lc++) {
                    $exists = false;
                    foreach ($chunk_lines as $line) {
                        if ($lc == $line[0] or ($lline[1]-$lline[0]+$lc) == $line[1]) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $n_chunk[] = array(
                                           $lc,
                                           $lline[1]-$lline[0]+$lc,
                                           $orig_lines[$lc-1]
                                           );
                    }
                }
            }
            $new_chunks[] = $n_chunk;
            $i++;
        }
        // Now, each chunk has the right length, we need to merge them
        // when needed
        $nnew_chunks = array();
        $i = 0;
        foreach ($new_chunks as $chunk) {
            if ($i>0) {
                $lline = end($nnew_chunks[$i-1]);
                if ($chunk[0][0] <= $lline[0]+1) {
                    // need merging
                    foreach ($chunk as $line) {
                        if ($line[0] > $lline[0] or empty($line[0])) {
                            $nnew_chunks[$i-1][] = $line;
                        }
                    }
                } else {
                    $nnew_chunks[] = $chunk;
                    $i++;
                }
            } else {
                $nnew_chunks[] = $chunk;
                $i++;
            }
        }
        return $nnew_chunks;
    }

    private function renderCompared($chunks, $filename)
    {
        $fileinfo = IDF_FileUtil::getMimeType($filename);
        $pretty = '';
        if (IDF_FileUtil::isSupportedExtension($fileinfo[2])) {
            $pretty = ' prettyprint';
        }

        $cc = 1;
        $left_offsets   = array();
        $left_contents  = array();
        $right_offsets  = array();
        $right_contents = array();

        $max_lineno_left = $max_lineno_right = 0;

        foreach ($chunks as $chunk) {
            foreach ($chunk as $line) {
                $left    = '';
                $right   = '';
                $content = IDF_FileUtil::emphasizeControlCharacters(Pluf_esc($line[2]));

                if ($line[0] and $line[1]) {
                    $class = 'context';
                    $left = $right = $content;
                } elseif ($line[0]) {
                    $class = 'removed';
                    $left = $content;
                } else {
                    $class = 'added';
                    $right = $content;
                }

                $left_offsets[]   = sprintf('<td>%s</td>', $line[0]);
                $right_offsets[]  = sprintf('<td>%s</td>', $line[1]);
                $left_contents[]  = sprintf('<td class="%s%s mono">%s</td>', $class, $pretty, $left);
                $right_contents[] = sprintf('<td class="%s%s mono">%s</td>', $class, $pretty, $right);

                $max_lineno_left  = max($max_lineno_left, $line[0]);
                $max_lineno_right = max($max_lineno_right, $line[1]);
            }

            if (count($chunks) > $cc) {
                $left_offsets[]   = '<td class="next">...</td>';
                $right_offsets[]  = '<td class="next">...</td>';
                $left_contents[]  = '<td></td>';
                $right_contents[] = '<td></td>';
            }
            $cc++;
        }

        $leftwidth = 1;
        if ($max_lineno_left > 0)
            $leftwidth = ((ceil(log10($max_lineno_left)) + 1) * 8) + 17;

        $rightwidth = 1;
        if ($max_lineno_right > 0)
            $rightwidth = ((ceil(log10($max_lineno_right)) + 1) * 8) + 17;

        $inner_linecounts_left =
          '<table class="diff-linecounts">' ."\n".
            '<colgroup><col width="'.$leftwidth.'" /></colgroup>' ."\n".
            '<tr class="line">' .
              implode('</tr>'."\n".'<tr class="line">', $left_offsets).
            '</tr>' ."\n".
          '</table>' ."\n";

        $inner_linecounts_right =
          '<table class="diff-linecounts">' ."\n".
            '<colgroup><col width="'.$rightwidth.'" /></colgroup>' ."\n".
            '<tr class="line">' .
              implode('</tr>'."\n".'<tr class="line">', $right_offsets).
            '</tr>' ."\n".
          '</table>' ."\n";

        $inner_contents_left =
          '<table class="diff-contents">' ."\n".
            '<tr class="line">' .
              implode('</tr>'."\n".'<tr class="line">', $left_contents) .
            '</tr>' ."\n".
          '</table>' ."\n";

        $inner_contents_right =
          '<table class="diff-contents">' ."\n".
            '<tr class="line">' .
              implode('</tr>'."\n".'<tr class="line">', $right_contents) .
            '</tr>' ."\n".
          '</table>' ."\n";

        $out =
          '<table class="diff context">' ."\n".
            '<colgroup>' .
              '<col width="'.($leftwidth + 1).'" /><col width="*" />' .
              '<col width="'.($rightwidth + 1).'" /><col width="*" />' .
            '</colgroup>' ."\n".
            '<tr id="diff-'.md5($filename).'">'.
              '<th colspan="4">'.Pluf_esc($filename).'</th>'.
            '</tr>' ."\n".
            '<tr>' .
              '<th colspan="2">'.__('Old').'</th><th colspan="2">'.__('New').'</th>' .
            '</tr>'.
            '<tr>' .
              '<td>'. $inner_linecounts_left .'</td>'. "\n".
              '<td><div class="scroll">'. $inner_contents_left .'</div></td>'. "\n".
              '<td>'. $inner_linecounts_right .'</td>'. "\n".
              '<td><div class="scroll">'. $inner_contents_right .'</div></td>'. "\n".
            '</tr>' ."\n".
            '</table>' ."\n";

        return Pluf_Template::markSafe($out);
    }
}
