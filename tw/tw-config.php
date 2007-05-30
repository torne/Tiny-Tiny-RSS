<?php
/*
 * tag|wall                                            | PHP Tag Filter|
 * ---------------------------------------------------------------------

   Copyright (C) 2002  designia.sk

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
 * tw-config.php
 *
 */

// paths
define ('TW_PATH', 'tw/');
define ('TW_LANG',			TW_PATH.'lang/');
define ('TW_CACHE',			TW_PATH.'tw_cache/');
define ('TW_SETUP',			TW_PATH.'filter-setup/');
define ('TW_ERRMODULE',		TW_PATH.'error/');
define ('TW_OUTMODULE',		TW_PATH.'output/');

// tag flags
define ('TW_NOP',			0x0001);
define ('TW_OPT',			0x0002);
define ('TW_OVR',			0x0004);
define ('TW_DUP',			0x0008);

// attr filter commands
define ('TW_ALL',		0);
define ('TW_URL',		1);		// value is url
define ('TW_LINK',		2);		// value is link
define ('TW_NUM',		3);		// value must be between
define ('TW_CASE',		4);		// value must be in case of..
define ('TW_REQ',		8);		// required attribute

define ('TW_RQ_ALL',	TW_REQ);
define ('TW_RQ_URL',	1 | TW_REQ);
define ('TW_RQ_LINK',	2 | TW_REQ);
define ('TW_RQ_NUM',	3 | TW_REQ);
define ('TW_RQ_CASE',	4 | TW_REQ);

if(!defined('FSHL_WITH_TW_DEFINED'))
{
	define ('FSHL_WITH_TW_DEFINED', 1);

	// debug modes (on - 1, off - 0)
	// only shlParser supports DEBUG modes
	define ('DEBUG_STATE', 	0);	// enable debug states
	define ('DEBUG_REPORT',	0);	// enable parser error reports and infos
	 
	// fshlParser() 'option' flags (not used at this time)
	define ('P_DISABLE_RECURSION',	0x0001);
	define ('P_DISABLE_NEWLANG', 	0x0002);
	define ('P_DISABLE_EXECUTE', 	0x0004);
	define ('P_DISABLE_EXIT',		0x0008);
	define ('P_DEFAULT',			0x0000);
	
	// F/SHL state flags
	define ('PF_VOID',			0x0000);
	define ('PF_KEYWORD',		0x0001);
	define ('PF_RECURSION',		0x0004);
	define ('PF_NEWLANG',		0x0008);
	define ('PF_EXECUTE',		0x0010);	// not used
	
	// TW state flags
	define ('PF_CLEAN',			0x0100);
	define ('PF_XIO',			0x0200);
	define ('PF_XDONE',			0x0400);
	define ('PF_XNEW',			0x0800);
	
	// state field indexes
	define ('XL_DIAGR',		0);
	define ('XL_FLAGS',		1);
	define ('XL_CLASS',		2);
	define ('XL_DATA',		3);
	
	define ('XL_DSTATE',	0);
	define ('XL_DTYPE',		1);
	
	// internal and special states
	define ('P_RET_STATE',	'_RET');
	define ('P_QUIT_STATE',	'_QUIT');
	
	// group delimiters
	$group_delimiters=array(
	
		"SPACE",	"!SPACE",
		"NUMBER",	"!NUMBER",
		"ALPHA",	"!ALPHA",
		"ALNUM",	"!ALNUM",
		"HEXNUM",	"!HEXNUM",
		"_ALL",
	
		// TODO: Add special language depended groups here.
		//       See function shlParser::isdelimiter()
		//       and fshlGenerator::make_isdx(). You must
		//       implement your new delimiters...
		"PHP_DELIM",
	);
	
	$fshl_signatures=array("SHL","TW");

} //end if(!defined())
?>
