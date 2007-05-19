<?
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
 * - this module appending automatic links and colored piece of bad code.
 *
 * - Module using following CSS classes (you must define it in your CSS stylesheet)
 *
 *   .tw-err-int 	- internal error	(in output string)
 *   .tw-err-err 	- standard error
 *   .tw-err-war 	- warning
 *   .tw-err-com 	- comment
 *
 *   .tw-err-int-l 	- internal error	(in error list)
 *   .tw-err-err-l 	- standard error
 *   .tw-err-war-l 	- warning
 *   .tw-err-com-l 	- comment

 */
class LINK_error extends TW_errors
{
	function LINK_error($options = 0)
	{
		$this->TW_errors($options);
	}
	
	function add($errval, $position, $show_code, $param1=null, $param2=null )
	{
		$err_id = "TW_err_".$this->identifier++;
		$this->ErrorArray[$err_id] = array($errval, $param1, $param2, $position, $show_code);
		if($errval & 0xff00) $this->IsError = 1;
		
		// append errors and warnings
		if(($errval & 0x0ff0) && $show_code != null )
			return 	'<a name="'.$err_id.'"><span class="'.$this->get_error_class($errval).'">'.$show_code.'</span></a>';
		
		return null;
	}
	
	// You can call this method from your project and create nice list of errors.
	//
	//   $lang - error string pack
	//   $mask - any combination of following masks:
	// 		0xf000 - internal, 
	// 		0x0f00 - errors, 
	// 		0x00f0 - warnings, 
	// 		0x000f - comments
	//
	function create_list (&$lang, $mask = 0x0ff0)
	{
		$errors = $this->get_by_mask($mask);
		
		$out = "<ul>";
		foreach($errors as $key => $value)
		{
			$errno = $value[TWE_ERRNO];
			$piece = '<span class="'.$this->get_error_class($errno).'">'.$value[TWE_CODE].'</span>';
			$list ='<a href="#'.$key.'">'.$this->get_error_text($key,$lang).$piece.'</a>';
			$out .= '<li class="'.$this->get_error_class($errno).'-l">'.$list.'</li>';
		}		
		return $out."</ul>";
	}
	
	function get_error_class ($error)
	{
		if($error&0xf000) return "tw-err-int";
		if($error&0x0f00) return "tw-err-err";
		if($error&0x00f0) return "tw-err-war";
		return "tw-err-com";
	}
}
?>