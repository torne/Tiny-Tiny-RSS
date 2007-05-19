<?php
/*
 * tag|wall                                           | PHP Tag Filter |
 * ---------------------------------------------------------------------

   Copyright (C) 2002  Juraj 'HVGE' Durech
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
 * HL_DEBUG_output.php		xhtml debug output module (highlight tags)
 */

class HL_DEBUG_output
{
	function HL_DEBUG_output() { return; 	}
	
	// input: string, array()
	
	function pair ($tag, &$attributes)
	{
		if($attributes == null)
			return $this->highlight( "<$tag>" );
		$attr = null;
		foreach ($attributes as $value)
		{
			if($value[1])	$attr .= ' '.$value[0].'="'.$value[1].'"';
		}
		return $this->highlight( "<$tag$attr>" );
	}
	
	function single ($tag, &$attributes)
	{
		if($attributes == null)
			return $this->highlight( "<$tag />" );
		$attr = null;
		foreach ($attributes as $value)
		{
			if($value[1])	$attr .= $value[0].'="'.$value[1].'" ';
		}
		return $this->highlight( "<$tag $attr/>" );
	}
	
	// template for end tags
	function close ($tag) 
	{
		return $this->highlight( "</$tag>" );
	}

	function template_end() { return null; 	}

	
	function highlight($string)
	{
		$string = str_replace("&","&amp",$string);
		return '<span style="background-color:yellow">'.str_replace("<","&lt;",$string).'</span>';
	}
	
} //END class HTML_output

?>