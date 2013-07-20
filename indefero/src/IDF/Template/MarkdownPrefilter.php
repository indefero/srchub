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
   * Should be renamed MarkdownPostfilter.
   */
class IDF_Template_MarkdownPrefilter extends Pluf_Text_HTML_Filter
{
    public $allowed_entities = array(
                                     'amp',
                                     'gt',
                                     'lt',
                                     'quot',
                                     'nbsp',
                                     'ndash',
                                     'rdquo',
                                     'ldquo',
                                     'Alpha',
                                     'Beta', 
                                     'Gamma', 
                                     'Delta', 
                                     'Epsilon', 
                                     'Zeta', 
                                     'Eta', 
                                     'Theta', 
                                     'Iota', 
                                     'Kappa', 
                                     'Lambda', 
                                     'Mu', 
                                     'Nu', 
                                     'Xi', 
                                     'Omicron', 
                                     'Pi', 
                                     'Rho', 
                                     'Sigma', 
                                     'Tau', 
                                     'Upsilon', 
                                     'Phi', 
                                     'Chi', 
                                     'Psi', 
                                     'Omega', 
                                     'alpha', 
                                     'beta', 
                                     'gamma', 
                                     'delta', 
                                     'epsilon', 
                                     'zeta', 
                                     'eta', 
                                     'theta', 
                                     'iota', 
                                     'kappa', 
                                     'lambda', 
                                     'mu', 
                                     'nu', 
                                     'xi', 
                                     'omicron', 
                                     'pi', 
                                     'rho', 
                                     'sigmaf', 
                                     'sigma', 
                                     'tau', 
                                     'upsilon', 
                                     'phi', 
                                     'chi', 
                                     'psi', 
                                     'omega', 
                                     'thetasym', 
                                     'upsih', 
                                     'piv',
                                     );

    public $allowed = array(
                            'a' => array('class', 'dir', 'id', 'style', 'title',
                                         'href', 'hreflang', 'rel'),
                            'abbr' => array('class', 'dir', 'id', 'style', 'title'),
                            'address' => array('class', 'dir', 'id', 'style', 'title'),
                            'b' => array('class', 'dir', 'id', 'style', 'title'),
                            'blockquote' => array('class', 'dir', 'id', 'style', 'title',
                                                  'cite'),
                            'br' => array('class', 'id', 'style', 'title'),
                            'caption' => array('class', 'dir', 'id', 'style', 'title',
                                               'align'), // deprecated attribute),
                            'code' => array('class', 'dir', 'id', 'style', 'title'),
                            'dd' => array('class', 'dir', 'id', 'style', 'title'),
                            'del' => array('class', 'dir', 'id', 'style', 'title',
                                           'cite', 'datetime'),
                            'div' => array('class', 'dir', 'id', 'style', 'title',
                                           'align'), // deprecated attribute
                            'dl' => array('class', 'dir', 'id', 'style', 'title'),
                            'dt' => array('class', 'dir', 'id', 'style', 'title'),
                            'em' => array('class', 'dir', 'id', 'style', 'title'),
                            'font' => array('class', 'dir', 'id', 'style', 'title', // deprecated element
                                            'color', 'face', 'size'), // deprecated attribute
                            'h1' => array('class', 'dir', 'id', 'style', 'title',
                                          'align'), // deprecated attribute
                            'h2' => array('class', 'dir', 'id', 'style', 'title',
                                          'align'), // deprecated attribute
                            'h3' => array('class', 'dir', 'id', 'style', 'title',
                                          'align'), // deprecated attribute
                            'h4' => array('class', 'dir', 'id', 'style', 'title',
                                          'align'), // deprecated attribute
                            'h5' => array('class', 'dir', 'id', 'style', 'title',
                                          'align'), // deprecated attribute
                            'h6' => array('class', 'dir', 'id', 'style', 'title',
                                          'align'), // deprecated attribute
                            'hr' => array('class', 'dir', 'id', 'style', 'title',
                                          'align', 'noshade', 'size', 'width'), // deprecated attribute
                            'i' => array('class', 'dir', 'id', 'style', 'title'),
                            'img' => array('class', 'dir', 'id', 'style', 'title',
                                           'src', 'alt', 'height', 'width'),
                            'ins' => array('class', 'dir', 'id', 'style', 'title',
                                           'cite', 'datetime'),
                            'li' => array('class', 'dir', 'id', 'style', 'title',
                                          'type'), // deprecated attribute
                            'ol' => array('class', 'dir', 'id', 'style', 'title',
                                          'type'), // deprecated attribute
                            'p' => array('class', 'dir', 'id', 'style', 'title',
                                         'align'), // deprecated attribute
                            'pre' => array('class', 'dir', 'id', 'style', 'title',
                                           'width'), // deprecated attribute
                            'strong' => array('class', 'dir', 'id', 'style', 'title'),
                            'table' => array('class', 'dir', 'id', 'style', 'title',
                                             'border', 'cellpadding', 'cellspacing', 'frame', 'rules', 'summary', 'width',
                                             'align', 'bgcolor'), // deprecated attribute
                            'td' => array('class', 'dir', 'id', 'style', 'title',
                                          'align', 'colspan', 'headers', 'rowspan', 'scope', 'valign',
                                          'bgcolor', 'height', 'nowrap', 'width'), // deprecated attribute
                            'th' => array('class', 'dir', 'id', 'style', 'title',
                                          'align', 'colspan', 'rowspan', 'scope', 'valign',
                                          'bgcolor', 'height', 'nowrap', 'width'), // deprecated attribute
                            'tr' => array('class', 'dir', 'id', 'style', 'title',
                                          'align', 'valign',
                                          'bgcolor'), // deprecated attribute
                            'ul' => array('class', 'dir', 'id', 'style', 'title',
                                          'type'), // deprecated attribute
                            );
    // tags which should always be self-closing (e.g. "<img />")
    public $no_close = array(
                             'img',
                             'br',
                             'hr',
                             );

    // tags which must always have seperate opening and closing tags
    // (e.g. "<b></b>")
    public $always_close = array(
                                 'strong',
                                 'em',
                                 'b',
                                 'code',
                                 'i',
                                 'ul',
                                 'ol',
                                 'li',
                                 'p',
                                 'table',
                                 'caption',
                                 'tr',
                                 'td',
                                 'span',
                                 'a',
                                 'blockquote',
                                 'pre',
                                 'iframe',
                                 'h1', 'h2', 'h3', 'address',
                                 'del',
                                 'ins',
                                 );
    // attributes which should be checked for valid protocols
    public $protocol_attributes = array(
                                        'src',
                                        'href',
                                        );
    // protocols which are allowed
    public $allowed_protocols = array(
                                      'http',
                                      'https',
                                      'ftp',
                                      'mailto',
                                      'irc'
                                      );
    // tags which should be removed if they contain no content
    // (e.g. "<b></b>" or "<b />")
    public $remove_blanks = array(
                                  'p',
                                  'strong',
                                  'em',
                                  'caption',
                                  'li',
                                  'span',
                                  'del',
                                  'ins',
                                  );
}
