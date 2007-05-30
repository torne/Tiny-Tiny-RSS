<?php
/*
 * tag|wall                                            | PHP Tag Filter|
 * ---------------------------------------------------------------------

   Copyright (C) 2002  Juraj 'HVGE' Durech
   Copyright (C) 2002  www.designia.sk
   
   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA 
   
 * ---------------------------------------------------------------------
 * tw-tags.php  - this file contains common tag definitions and 
 *                relationships
 *
 * WARNING: This is not filter configuration. Relationships are used
 *          for correct cross tag fixing.
 * ---------------------------------------------------------------------
 * Tag flags:
 *
 * TW_DUP   - remove duplicates ( not implemented )
 *            <i>...<i>:::</i></i> => <i>...:::</i>
 *
 * TW_NOP   - tag without end tag 
 *            <br /><hr /> etc..
 *
 * TW_OPT   - tag have optional end tag ( optional end tag is closed automatically )
 *            <li>line1<li>line2 => <li>line1</li><li>line2</li>
 *
 * CONT.OFF - content off ( not implemented )
 *            <tag>content-off<tag2>content-on</tag1></tag>  =>  <tag><tag2>content-on</tag1></tag>
 *
 * REQ.TAG  - required tag in stack
 *            <ul><li>...  this is correct
 *            <p><li>...   this is not correct, result is <p>...
 */
$tw_tag_relations = array(

//  TAG                  FLAG    CONT.OFF  REQ. TAG

    "a"         => array(0,      0,        null),
    "b"         => array(0,      0,        null),
    "blockquote"=> array(0,      0,        null),
    "big"       => array(0,      0,        null),
    "br"        => array(TW_NOP, 0,        null),
    "code"      => array(0,      0,        null),
    "dl"        => array(0,      0,        null),
    "dt"        => array(TW_OPT, 0,        array("dl")),
    "dd"        => array(TW_OPT, 0,        array("dl")),
    "div"       => array(0,      0,        null),
    "em"        => array(0,      0,        null),
    "h1"        => array(0,      0,        null),
    "h2"        => array(0,      0,        null),
    "h3"        => array(0,      0,        null),
    "h4"        => array(0,      0,        null),
    "h5"        => array(0,      0,        null),
    "h6"        => array(0,      0,        null),
    "hr"        => array(TW_NOP, 0,        null),
    "i"         => array(0,      0,        null),
    "img"       => array(TW_NOP, 0,        null),
    "ul"        => array(0,      1,        null),
    "ol"        => array(0,      1,        null),
    "li"        => array(TW_OPT, 0,        array("ul","ol")),
    "object"    => array(0,      1,        null),
    "p"         => array(TW_OPT, 0,        null),
    "pre"       => array(0,      0,        null),
    "small"     => array(0,      0,        null),
    "span"      => array(0,      0,        null),
    "strong"    => array(0,      0,        null),
    "style"     => array(0,      1,        null),
    "sub"       => array(0,      0,        null),
    "sup"       => array(0,      0,        null),
    "table"     => array(0,      1,        null),
    "caption"   => array(0,      0,        array("table")),
    "tbody"     => array(0,      0,        array("table")),
    "tfoot"     => array(0,      0,        array("table")),
    "thead"     => array(0,      0,        array("table")),
    "tr"        => array(TW_OPT, 1,        array("table","tbody")),
    "td"        => array(TW_OPT, 0,        array("tr")),
    "th"        => array(TW_OPT, 0,        array("tr")),
    "u"         => array(0,      0,        null),

    // TODO: add your specific tags here...

    );
?>
