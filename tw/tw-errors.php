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
 * tw-errors.php	
 * 
 * This file containing basic error definitions and basic error handling class.
 *
 */
 
// comments
define('TWE_OK',				0x0000);	// null
define('TWE_VERSION',			0x0001);	// param1=parser_version, param2=lang_version
define('TWE_NOTE',				0x0002);	// param2=note / tips / etc...
define('TWE_CREDITS',			0x0003);	// null

// warnings
define('TWE_STACK_UNDERFLOW',	0x0010);	// param1=tag_name
define('TWE_STACK_NOT_EMPTY',	0x0020);	// param1=tags, stack not empty
define('TWE_UNEXPECTED_EOST',	0x0030);	// unexpected end of stream (tag completed automatically)
define('TWE_UNEXPECTED_QUOTE',	0x0040);	// atr="value>...

// errors
define('TWE_SYNTAX', 			0x0100);	// null, HTML syntax error (for future strict bases)
define('TWE_TOO_MANY_ATTRS',	0x0200);	// tag_name, too many attrs in tag ..

// internal errors
define('TWE_FILE_NOT_FOUND',	0x1000);	// param1 = file
define('TWE_BAD_SIGNATURE',		0x2000);	// param1 = language, param2 = signature
define('TWE_LANG_NOT_FOUND',	0x3000);	// param1 = language

// indexes to ErrorArray
define('TWE_ERRNO',		0);		// error value
define('TWE_PARAM1',	1);		// parameter 1
define('TWE_PARAM2',	2);		// parameter 2
define('TWE_POSIT',		3);		// position in source
define('TWE_CODE',		4);		// piece of bad code

class TW_errors
{
	var $IsError;
	var $ErrorArray;
	var $identifier;

	/* class constructor 
	 */
	function TW_errors($options = 0)
	{
		$this->IsError = 0;
		$this->ErrorArray = null;
		$this->identifier = 0;
	}
	
	function is_error()		{ return $this->IsError; }
	
	function get_err_array() { return $this->ErrorArray; }
	
	function get_comments()	{ return $this->get_by_mask(0x000f); }

	function get_warnings()	{ return $this->get_by_mask(0x00f0); }

	function get_errors()	{ return $this->get_by_mask(0x0f00); }

	function get_internal()	{ return $this->get_by_mask(0xf000); }
		
	function get_by_mask($mask)
	{
		$ErrTemp = null;
		foreach($this->ErrorArray as $key => $value)
		{
			if($value[TWE_ERRNO] & $mask) $ErrTemp[$key] = $value;
		}
		return $ErrTemp;
	}
	
	/* Input: 
	 *		id:		error id, 
	 *		lang:	error_language_array
	 *
	 *  Outupt:
	 * 		error text or null
	 */
	function get_error_text ( $id, &$lang )
	{
		if( in_array($id, $this->ErrorArray) )
		{
			$errno = $this->ErrorArray[$id][TWE_ERRNO];
			if( in_array($errno,$lang) )
				return sprintf( $lang[$errno], 
								$this->ErrorArray[$id][TWE_PARAM1], 
								$this->ErrorArray[$id][TWE_PARAM2] );
			else
				return sprintf( "Please translate errno 0x%x.",$errno );
		}
		return null;
	}

}	// END class TW_errors
?>
