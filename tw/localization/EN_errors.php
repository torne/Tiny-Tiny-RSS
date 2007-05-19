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
 * tag|wall 	error strings
 *
 * NOTE:
 * TW not using this file directly. You can include it to your project
 * at your opinion. Then you can use method TW_errors::get_error_text()
 * for getting error texts. 
 *
 * Please correct my stupid english :)
 */
$tw_error_strings = array(
	
	// comments	& tips
	TWE_OK 					=> null,
	TWE_VERSION				=> "Versions: parser V %s, language V%s",
	TWE_CREDITS				=> "tag|wall: code Juraj Durech (hvge@cauldron.sk).",
	TWE_NOTE				=> "%s",
	
	// warnings
	TWE_STACK_UNDERFLOW		=> "Stack underflow, tag '%s' was dropped out.",
	TWE_STACK_NOT_EMPTY		=> "There are some unclosed tags on stack.",
	TWE_UNEXPECTED_EOST		=> "Unexpected end of stream.",
	TWE_UNEXPECTED_QUOTE	=> "Unexpected quote.",
	
	// errors
	TWE_SYNTAX				=> "HTML syntax error.",
	TWE_TOO_MANY_ATTRS		=> "Too many attributes in tag '%s'.",
	
	// internal errors
	TWE_FILE_NOT_FOUND		=> "File '%s' not found.",
	TWE_BAD_SIGNATURE		=> "Language '%s' have bad signature '%s'.",
	TWE_LANG_NOT_FOUND		=> "Language '%s' not found.",
	
	);
?>