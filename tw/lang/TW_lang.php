<?php
/*
 * tag|wall                                           | PHP Tag Filter |
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
 * TW - based on SHL Language File
 * 
 * Spolocne so SHL
 * - kompatibilita s FSHL generatorom 0.4.x
 * - zakladna kostra stavoveho diagramu je spolocna
 *
 * Rozdiely voci SHL
 * - polia maju iny vyznam pretoze spracuva iny stavovy automat
 * - vsetky stavy sa volaju rekurentne
 *
 * function STATE_in()  - vola sa pri vstupe do stavu (Ovplyvnuje flag PF_XIO)
 * function STATE_out() - vola sa pri opusteni stavu (_RET) (Ovplyvnuje flag PF_XIO)
 *                        tiez hned po navrate z rekurzie flag PF_XDONE sposobi zavolanie
 *                        STATE_out() a opustenie aktualneho stavu (simulovany _RET)
 * function STATE_new() - vola sa pri zmene stavu (Ovplyvnuje flag PF_XNEW)
 */ 
class TW_lang
{
	var $states;
	var $initial_state;
	var $keywords;
	var $version;
	var $signature;

	function TW_lang()
	{
		$this->signature = "TW";
		$this->version = "1.0";
		$this->initial_state="OUT";
		$this->states = 
		array
		(
		"OUT" => array (
				array(
						"<"		=> array("T_tagWall",0),
						
						//"&"		=> array("VChar",0),		// validate char (currently not implemented in base)						
						),

				PF_CLEAN,		// PF_CLEAN - znaky sa forwarduju na vystup
				0,0
				),

		"T_tagWall" => array (
				array(
						"ALPHA" => array("T_begin",1),		// normal tag
						"/"		=> array("T_Cbegin",0),		// close tag
						"<"		=> array("T_tagWall",1),	// '<<<<<<<<' fix (faster than _RET)
						"!--"	=> array("HTML_comment",0),
						"_ALL"	=> array("_RET",0),			// '<?' other fixes
						),
			
				PF_XDONE,
				0,0
				),


		"T_Cbegin" => array (
				array(
						"ALPHA"	=> array("T_gettag",1),
						"_ALL"	=> array("_RET",0),
						),
				PF_XIO | PF_XDONE,						0,0
				),


		"T_begin" => array (
				array(
						"ALPHA"	=> array("T_gettag",1),
						"_ALL"	=> array("_RET",0),
						),
				
				PF_XIO | PF_XDONE,						0,0
				),

			
		"T_gettag" => array (
				array(
						"!ALNUM"=> array("T_in",1),
						),
						
				PF_XIO | PF_XDONE | PF_XNEW,			0,0
				),


		"T_in" => array(
				array(
						"ALPHA"	=> array("A_begin",1),	// char back to stream
						">"		=> array("_RET",0),
						"/>"	=> array("_RET",0),		// pozor na spracovanie v T_begin
						),
				
				0,0,0
				),

		"A_begin" => array (
				array(
						"!ALPHA"=> array("V_begin",1),
						">"		=> array("_RET",1),		// vracia string do streamu
						"/>"	=> array("_RET",1),		// pozor na spracovanie v TAGbegin
						),
				
				PF_XIO | PF_XDONE | PF_XNEW ,			0,0
				),

		// this is wide attribute=value implementation
		"V_begin" => array (
				array(
						'"'		=> array("VALUE1",0),
						"'"		=> array("VALUE2",0),
						"ALNUM" => array("VALUE3",1),

						">"		=> array("_RET",1),
						"/>"	=> array("_RET",1),
						),

				PF_XIO | PF_XDONE,						0,0
				),
			
		// "DOUBLEQUOTED VALUE"
		"VALUE1" => array(
				array(
						'"'		=> array("_RET",0),
						),
				PF_XIO,									0,0
				),

		// 'SINGLEQUOTED VALUE'
		"VALUE2" => array(
				array(
						"'"		=> array("_RET",0),
						),
				PF_XIO,									0,0
				),

		// UNQUOTEDVALUE99
		"VALUE3" => array(
				array(
/*						"_" => array ("VALUE3",0),	//Uncomment for better HTML4 compatibility (not recommended)
						"." => array ("VALUE3",0), 
*/
						"-" => array ("VALUE3",0),
						"!ALNUM"=> array("_RET",1),
						),
				PF_XIO,									0,0
				),

		// all comment content will be removed
		"HTML_comment" => array(
				array(
						"-->"	=> array("_RET",0),
						),
				0,0,0
				),
		);
		
		$this->keywords=null;
	}
}
?>
