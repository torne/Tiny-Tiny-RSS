<?
/*
 * tag|wall ver 0.1.0                                  | PHP Tag Filter|
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
 * tag|wall error module 
 *
 * description:
 * - TODO: add module description
 *
 * output modifications:
 * - TODO: add your output modifications here
 */
class ???_error extends TW_errors
{
	// TODO: add module depended variables here
	
	// Class constructor
	// NOTE: parser calling this with his options. See twParser constructor.
	//
	function ???_error($options = 0)
	{
		// call parent class constructor
		$this->TW_errors($options);
		
		// TODO: add your own module initializations here
	}
	
	// You must implement your own add method.
	
	function add($errval, $position, $show_code, $param1=null, $param2=null )
	{
		$err_id = "TW_err_".$this->identifier++;
		//
		// This is basic error implementation. Modify this code only if you really need it.
		// 
		$this->ErrorArray[$err_id] = array($errval, $param1, $param2, $position, $show_code);
		if($errval & 0xff00) $this->IsError = 1;

		// TODO: create your output additions here, and return it as string

		return null;
	}
	
	// TODO:
	// You can implement ohther methods here. 
	// For example: special error filters, post-parsing functions for generating 
	// error lists, etc...

}
?>