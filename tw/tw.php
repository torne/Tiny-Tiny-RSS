<?php
/*
 * tag|wall ver 0.1.4                                 | PHP Tag Filter |
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
 * tw.php	
 *
 * main tag|wall parser core
 * ---------------------------------------------------------------------
 *
 */
define ("TW_PARSER_VERSION", "0.1.4");

require_once(TW_PATH."tw-tags.php");
require_once(TW_PATH."tw-errors.php");

class twParser
{
	// class variables
	var $text, $textlen, $textpos;
	var $lang, $options; 
	var $output, $err, $content_off;

	var $out;
	var $_trans, $_flags, $_data, $_delim, $_class, $_keywords;
	var $_ret,$_quit;
	
	var $base, $_names;


	// -----------------------------------------------------------------------
	// USER LEVEL functions
	//

	/* twParser CLASS CONSTRUCTOR
	 * 
	 * input: 
	 *		string 	$language	- TW language class name ( see directory tw/lang/ )
	 *		int		$options	- parser options (not used)
	 */
	function twParser(
						$language = "TW", 
						$options  = P_DEFAULT 
					 )
	{
		$_lang = $language."_lang";
		$_base = $language."_base";
		require_once (TW_LANG."$_base.php");
		require_once (TW_CACHE."$_lang.php");

		$this->lang = new $_lang;
		$this->base = $_base;
		$this->options   = $options;
		$this->_trans    = &$this->lang->trans;
		$this->_flags    = &$this->lang->flags;
		$this->_delim    = &$this->lang->delim;
		$this->_ret      = &$this->lang->ret;
		$this->_quit     = &$this->lang->quit;
		$this->_names    = &$this->lang->names;

		$this->content_off = &$this->lang->content_off;
	}

	/* STRIP TAGS
	 *
	 * input:
	 *		string $text			- input string
	 *		array $configuration	- filter configuration array ( see files in directory tw/filter-setup/ )
	 *		string $output_module	- output module name ( tw/output )
	 *		string $error_module	- error module name ( tw/error )
	 *		int $offset				- offset in $text
	 *
	 * output:
	 *		parsed string
	 */
	function strip_tags (
							$text,
							&$configuration,
							$output_module = "XHTML",
							$error_module  = "FOO",							
							$offset = 0
						)
	{
		// open modules
		$_err  = $error_module."_error";
		require_once (TW_ERRMODULE."$_err.php");
		$this->err = new $_err( $this->options );

		$_out  = $output_module."_output";
		require_once (TW_OUTMODULE."$_out.php");
		$this->output = new $_out;

		// parser init
		$this->text = &$text;
		$this->textlen = strlen($text);
		$this->text .= "IMNOTREALLYOPTIMISTIC";
		$this->textpos = $offset;
		$this->out = null;
		
		// FSHL pointers init
		$this->lang->pt		= &$this->text;
		$this->lang->pti	= &$this->textpos;
		$this->lang->out	= &$this->out;
		$this->lang->err	= &$this->err;
		$this->lang->output = &$this->output;

		// base init
		$base = &$this->base;
		$this->lang->$base();
		$this->lang->config_tags = array_keys($configuration);
		// load initial configuration
		foreach($configuration as $tag => $attributes)
		{
			$this->lang->config_req_attr[$tag] = null;
			if(is_array($attributes))
			{
				$this->lang->config_attr[$tag] = array_keys($configuration[$tag]);
				foreach($attributes as $attr => $command)
				{
					if( $command )
						if( $command[0] & TW_REQ ) $this->lang->config_req_attr[$tag][] = $attr;
				}
			}
			else
			{
				$this->lang->config_attr[$tag] = $attributes;
			}
		}
		$this->lang->config = &$configuration;
		
		// start parser
		$this->parse_string ( $this->lang->initial_state );
		
		$this->out .= $this->lang->base_end();
		$this->out .= $this->output->template_end();

		return $this->out;
	}

	function get_position()	{ return $this->textpos; }

	function get_out() { return $this->out;	}
	
	// error wrapper
	
	function is_error() { return $this->err->is_error(); }
	function get_err_array() { return $this->err->get_err_array(); }
	function get_comments()	{ return $this->err->get_by_mask(0x000f); }
	function get_warnings()	{ return $this->err->get_by_mask(0x00f0); }
	function get_errors()	{ return $this->err->get_by_mask(0x0f00); }
	function get_internal()	{ return $this->err->get_by_mask(0xf000); }
	function get_by_mask($mask) { return $this->err->get_by_mask($mask); }
	function get_error_text ( $id, &$lang ) { return $this->err->get_error_text ( $id, $lang ); }
	
// ---------------------------------------------------------------------------------
// LOW LEVEL functions
//

// main parser function
//
function parse_string ($state)
{
	$flags = $this->_flags[$state];
	$statename_n = $this->_names[$state]."_new";
	// perform IN function if required
	if( $flags & PF_XIO )
	{ 
		$statename_i = $this->_names[$state]."_in";
		$statename_o = $this->_names[$state]."_out";
		$this->lang->$statename_i();
	}
	$stateword = null;
	
	while( ($word = $this->getword("isd$state")) != null )
	{
		if(is_array($word))
		{
			// word is delimiter
			$newstate = $this->_trans[$state][$word[0]][XL_DSTATE];

			// char back to stream (CB2S) if required
			if( $this->_trans[$state][$word[0]][XL_DTYPE] )
			{
				if( $newstate == $state )
				{
					// If it is the same state, CB2S flag have different significance
					// re-initialize state (call IN function)
					$stateword = null;
					if( $flags & PF_XIO ) $this->lang->$statename_i();
					continue;
				}
				$this->textpos -= strlen($word[1]);
			}
			else
			{
				$stateword .= $word[1];		// add new parsed word to stateword
			}
			if( $newstate == $this->_ret ) 	// newstate is _RET from recursion
			{
				// perform NEW function if required
				if( $flags & PF_XNEW ) $this->lang->$statename_n($stateword);
				// perform OUT function if required
				if( $flags & PF_XIO ) $this->lang->$statename_o($stateword);
				// return from recursion
				return;
			}
			
			if( $state != $newstate )		// recursion - only if it is really new state
			{
				// perform NEW function if required
				if( $flags & PF_XNEW ) $this->lang->$statename_n($stateword);
				// recursion
				$this->parse_string($newstate);
				// perform OUT function if required and return.
				if( $flags & PF_XDONE )
				{
					if( $flags & PF_XIO ) $this->lang->$statename_o(null);
					return;
				}
				continue;
			}			
		}
		else
		{
			// word is not delimiter
			if( $flags & PF_CLEAN )
			{
				if(!$this->content_off)
					$this->out .= str_replace("<","&gt",$word);
			}
			else
			{
				$stateword .= $word;
			}
		}
	} //END while()

	// TODO: check this OUT
	
	// perform NEW function if required
	if( $flags & PF_XNEW ) $this->lang->$statename_n($stateword);
	// perform OUT function if required and return.
	if( $flags & PF_XIO ) $this->lang->$statename_o($stateword);
}

// get word from stream
//
function getword ($state)
{
	$result = null;
	if($this->textpos < $this->textlen)
	{
		$del = $this->lang->$state();	// call "is delimiter" isdX function
		if($del != false)
		{
			// actual char (or sub-string) is delimiter
			$this->textpos += strlen($del[1]);
			return $del;
		}
		else
		{
			// Actual char/string is not delimiter.
			// Result word is between current position and first delimiter in stream
			$result = $this->text[$this->textpos++];
			while(($this->textpos < $this->textlen) && !$this->lang->$state())
				$result .= $this->text[$this->textpos++];
		}
	}
	return $result;
}

} // END class twParser
?>
