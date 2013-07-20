<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Plume Framework, a simple PHP Application Framework.
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# Plume Framework is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License, or
# (at your option) any later version.
#
# Plume Framework is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

class Pluf_HTTP_Response_ServerError extends Pluf_HTTP_Response
{
    function __construct($exception, $mimetype=null)
    {
        $content = '';
        $admins = Pluf::f('admins', array());
        if (count($admins) > 0) {
            // Get a nice stack trace and send it by emails.
            $stack = Pluf_HTTP_Response_ServerError_Pretty($exception);
            $subject = $exception->getMessage();
            $subject = substr(strip_tags(nl2br($subject)), 0, 50).'...';
            foreach ($admins as $admin) {
                $email = new Pluf_Mail($admin[1], $admin[1], $subject);
                $email->addTextMessage($stack);
                $email->sendMail();
            }
        }
        try {
            $context = new Pluf_Template_Context(array('message' => $exception->getMessage()));
            $tmpl = new Pluf_Template('500.html');
            $content = $tmpl->render($context);
            $mimetype = null;
        } catch (Exception $e) {
            $mimetype = 'text/plain';
            $content = 'The server encountered an unexpected condition which prevented it from fulfilling your request.'."\n\n"
                .'An email has been sent to the administrators, we will correct this error as soon as possible. Thank you for your comprehension.'
                ."\n\n".'500 - Internal Server Error';
        }
        parent::__construct($content, $mimetype);
        $this->status_code = 500;
    }
}

function Pluf_HTTP_Response_ServerError_Pretty($e) 
{
    $sub = create_function('$f','$loc="";if(isset($f["class"])){
        $loc.=$f["class"].$f["type"];}
        if(isset($f["function"])){$loc.=$f["function"];}
        return $loc;');
    $parms = create_function('$f','$params=array();if(isset($f["function"])){
        try{if(isset($f["class"])){
        $r=new ReflectionMethod($f["class"]."::".$f["function"]);}
        else{$r=new ReflectionFunction($f["function"]);}
        return $r->getParameters();}catch(Exception $e){}}
        return $params;');
    $src2lines = create_function('$file','$src=nl2br(highlight_file($file,TRUE));
        return explode("<br />",$src);');
    $clean = create_function('$line','return html_entity_decode(str_replace("&nbsp;", " ", $line));');
    $desc = get_class($e)." making ".$_SERVER['REQUEST_METHOD']." request to ".$_SERVER['REQUEST_URI'];
    $out = $desc."\n";
    if ($e->getCode()) { 
        $out .= $e->getCode(). ' : '; 
    }
    $out .= $e->getMessage()."\n\n";
    $out .= 'PHP: '.$e->getFile().', line '.$e->getLine()."\n";
    $out .= 'URI: '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']."\n\n";
    $out .= '** Stacktrace **'."\n\n";
    $frames = $e->getTrace(); 
    foreach ($frames as $frame_id=>$frame) { 
        if (!isset($frame['file'])) {
            $frame['file'] = 'No File';
            $frame['line'] = '0';
        }
        $out .= '* '.$sub($frame).'
        ['.$frame['file'].', line '.$frame['line'].'] *'."\n";
        if (is_readable($frame['file']) ) { 
            $out .= '* Src *'."\n";
            $lines = $src2lines($frame['file']);
            $start = $frame['line'] < 5 ?
                0 : $frame['line'] -5; $end = $start + 10;
            $out2 = '';
            $i = 0;
            foreach ( $lines as $k => $line ) {
                if ( $k > $end ) { break; }
                $line = trim(strip_tags($line));
                if ( $k < $start && isset($frames[$frame_id+1]["function"])
                     && preg_match('/function( )*'.preg_quote($frames[$frame_id+1]["function"]).'/',
                                   $line) ) {
                    $start = $k;
                }
                if ( $k >= $start ) {
                    if ( $k != $frame['line'] ) {
                        $out2 .= ($start+$i).': '.$clean($line)."\n"; 
                    } else {
                        $out2 .= '>> '.($start+$i).': '.$clean($line)."\n"; 
                    }
                    $i++;
                }
            }
            $out .= $out2;
        } else { 
            $out .= 'No src available.';
        } 
        $out .= "\n";
    } 
    $out .= "\n\n\n\n";
    $out .= '** Request **'."\n\n";

    if ( function_exists('apache_request_headers') ) {
        $out .= '* Request (raw) *'."\n\n";
        $req_headers = apache_request_headers();
        $out .= 'HEADERS'."\n";
        if ( count($req_headers) > 0 ) {
            foreach ($req_headers as $req_h_name => $req_h_val) {
                $out .= $req_h_name.': '.$req_h_val."\n";
            }
            $out .= "\n";
        } else { 
            $out .= 'No headers.'."\n";
        } 
        $req_body = file_get_contents('php://input');
        if ( strlen( $req_body ) > 0 ) {
            $out .= 'Body'."\n";
            $out .= $req_body."\n";
        } 
    } 
    $out .= "\n".'* Request (parsed) *'."\n\n";
    $superglobals = array('$_GET','$_POST','$_COOKIE','$_SERVER','$_ENV');
    foreach ( $superglobals as $sglobal ) {
        $sfn = create_function('','return '.$sglobal.';');
        $out .= $sglobal."\n";
        if ( count($sfn()) > 0 ) {
            foreach ( $sfn() as $k => $v ) {
                $out .= 'Variable: '.$k."\n";
                $out .= 'Value:    '.print_r($v,TRUE)."\n";
            }
            $out .= "\n";
        } else { 
            $out .= 'No data'."\n\n";
        } 
    } 
    if ( function_exists('headers_list') ) { 
        $out .= "\n\n\n\n";
        $out .= '** Response **'."\n\n";
        $out .= '* Headers *'."\n\n";
        $resp_headers = headers_list();
        if (count($resp_headers) > 0) {
            foreach ( $resp_headers as $resp_h ) {
                $out .= $resp_h."\n";
            }
            $out .= "\n";
        } else {
            $out .= 'No headers.'."\n";
        } 
    } 
    return $out;
}

