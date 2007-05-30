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
 * tag|wall error module 
 *
 * description:
 * - this is default error module.
 */
class FOO_error extends TW_errors
{
	function FOO_error($options = 0)
	{
		$this->TW_errors($options);
	}
	
	function add($errval, $position, $show_code, $param1=null, $param2=null )
	{
		$this->ErrorArray["TW_err_".$this->identifier++] = array($errval, $param1, $param2, $position, $show_code);
		if($errval & 0xff00) $this->IsError = 1;
		return null;
	}
}
?>
