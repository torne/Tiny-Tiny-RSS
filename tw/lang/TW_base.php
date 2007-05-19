<?
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
 */

/*
 * $this->pt	text
 * $this->pti	text index
 */
class TW_base		// TW_lang extends this class
{
	var $error;		// error module object
	var $output;	// output module object
	var $out;		// output string
	
	var $content_off;
	
	var $tags;			// relationships between tags
	var $config;		// current configuration	(twParser::strip_tags())
	var $config_tags;	// tags in current configuration
	var $config_attr;	// attributes in current configuration
	var $config_req_attr;	// required attributes
		

	var $TAG;
	var $is_attributes;
	var $ATTRIBUTES;		// index => array("atr","value")
	var $ATTR;				// array(atr,value)
	var $VALUE;
	
/********************************************************************************************
 * BASE CONSTRUCTOR 
 */
	function TW_base()
	{
		global $tw_tag_relations;
		
		$this->stack();
		$this->tags = &$tw_tag_relations;
		$this->content_off = 0;
	}
/*
 * BASE "DESTRUCTOR" (parser call this function if end of string is rached)
 */
	function base_end()
	{
		while( $tag = $this->stack_pop() )
		{
			$this->out .= $this->output->close( $tag );
		}
		return null;
	}


/********************************************************************************************
 * TAG STACK implementation
 */
 	var $Tstack;

	function stack() 				{	$this->Tstack[0] = null;	}
	function stack_push( $tag )		{	array_unshift( $this->Tstack, $tag );	}
	function stack_pop()			{	return array_shift( $this->Tstack );	}
	function stack_search( $tag )	{	return in_array( $tag, $this->Tstack );	}

	function stack_top( $tag = null )
	{
		if( $tag )	return $this->Tstack[0] == $tag;
		return $this->Tstack[0];
	}




/********************************************************************************************
 *	S T A R T _ T A G _ f i l t e r			<tag atr=value>
 *
 */ 	
 	
 	function START_TAG_filter()
 	{
		$tag = $this->TAG;
		if( $tag == null )
		{
			//###### syntax error
			return;
		}
		if( in_array($tag,$this->config_tags) )
		{
			//enabled tag

			if(is_string($this->config_attr[$tag]))
			{
				$this->TAG = $tag = $this->config_attr[$tag];
				//tag substitution warning
			}
			
			// ------- perform attribute check -----------
			
			if($this->is_attributes)
			{
				if(!$this->config_attr[$tag])
				{
					$this->ATTRIBUTES = null;
					// remove attribute warning
				}
				else
				{
					foreach( $this->ATTRIBUTES as $key => $attr )
					{
						if( ! in_array($attr[0], $this->config_attr[$tag] ) )
						{
							$this->ATTRIBUTES[$key][1] = null;
							// remove attribute warning
							continue;
						}
						
						// --------- perform value check -----------
						
						if(($val = $attr[1])==null) continue;
						if(($cmda = &$this->config[$tag][$attr[0]])==null) continue; //null - accept all values
						switch($cmda[0] & 7)
						{
							case TW_URL:
								// V0.1.2: fixed some fatal bugs, big thanx to fczbkk
								//
								// TODO: make better url check with parse_url()
								
								$val = strtolower($val);
								if(strpos($val, "http://") === false)
								{
									if(strpos($val, "ftp://") === false)
									    if(strpos($val, "email:") === false)
										if(strpos($val, "https://") === false)
										    if(strpos($val, "./") === false)	// local relative url
											$val = "http://".$val;
								}
								$this->ATTRIBUTES[$key][1] = $val;
								break;
							
							case TW_LINK:
								//TODO: add link separator check here.
								//      Do not use this attribute in config!
								break;
							
							case TW_NUM:
								if( $val >= $cmda[2] && $val <= $cmda[3] ) break;
								$this->ATTRIBUTES[$key][1] = $cmda[1];
								break;
							
							case TW_CASE:
								if( !in_array($val, $cmda[2]) ) $this->ATTRIBUTES[$key][1] = $cmda[1];
								break;
						}
					}
				}
			}
			// check required attributes
			if($this->config_req_attr[$tag])
			{
				if( $this->is_attributes )
					foreach( $this->config_req_attr[$tag] as $required )
					{
						$req_found = 0;
						foreach( $this->ATTRIBUTES as $val)
							if( $val[0] == $required )
							{
								$req_found = 1;
								break;
							}
						if($req_found) continue;
						
						switch(	$this->config[$tag][$required][0] & 7 )
						{
							case TW_LINK:
							case TW_URL:
								// error
								break;
							
							default:
								$this->ATTRIBUTES[$required] = array($required, $this->config[$tag][$required][1]);
								break;
						}
					}
				else
				{
					foreach( $this->config_req_attr[$tag] as $required )
					{
						switch(	$this->config[$tag][$required][0] & 7 )
						{
							case TW_LINK:
							case TW_URL:
								
								// required tag error
								
								break;
							
							default:
								$this->ATTRIBUTES[$required] = array($required, $this->config[$tag][$required][1]);
								break;
						}						
					}
				}
					
			}			
			// cross tag removal algorithm
			
			$flag = $this->tags[$tag];
			$top = $this->stack_top();
			
			if( $flag[2] != null )		// check if tag before is specified
			{
				// yes, tag before is specified, check relationship
				if(! in_array($top, $flag[2]) )
				{
					if( $flag[0] & TW_OPT ) 
					{
						if( $top == $tag )
						{
							// End Tag is optional and current tag is the same as last tag (on stack).
							// Close previos for XHTML compatibility and open new the same tag.
							// Return, because no manipulation with stack is required.
							$this->out .= $this->output->close( $tag );
							$this->out .= $this->output->pair( $tag, $this->ATTRIBUTES );
							return;
						}
						if( $this->stack_search($tag) )
						{
							// repair stack
							while(($top = $this->stack_pop() ) != $tag )
							{
								/*if( !($this->tags[$top][0] & TW_OPT ) )
								{
									// auto close warning
								}*/
								$this->out .= $this->output->close( $top );
							}
							$this->stack_push($tag);
							$this->out .= $this->output->close( $tag );
							$this->out .= $this->output->pair( $tag, $this->ATTRIBUTES );
							return;
						}
					}
					// <th>...<td>  =>> <th>...</th><td>...	(<th> & <td> they have common parent tag (<tr>) )
					if( $this->tags[$top][0] & TW_OPT )
					{
						if( $this->tags[$top][2] == $flag[2] )
						{
							$this->out .= $this->output->close( $this->stack_pop() );
							$this->out .= $this->output->pair( $tag, $this->ATTRIBUTES );
							$this->stack_push( $tag );
							return;
						}
					}
					// invalid relation between tags #######
					return;
				}
				// valid relationship
			}
			if( $flag[0] & TW_NOP )
			{
				//tag without End Tag <br />
				$this->out .= $this->output->single( $tag, $this->ATTRIBUTES );
			}
			else
			{
				//Tag with End Tag, push to stack
				if( ($top == $tag) && ($flag[0] & TW_OPT) )
				{
					// '<p><p>' => '<p></p><p>'
					$this->out .= $this->output->close( $tag );
					$this->out .= $this->output->pair( $tag, $this->ATTRIBUTES );
					return;
				}
				$this->out .= $this->output->pair( $tag, $this->ATTRIBUTES );
				$this->stack_push( $tag );
			}
			return;
		}
		else
		{
			//disabled tag (or not supported) ######
			return;
		}
 	}
 	
/********************************************************************************************
 *	E N D _ T A G _ f i l t e r				</tag>	
 *
 */
 	function END_TAG_filter()
 	{
		$tag = $this->TAG;
		if( $tag == null ) 
		{
			// </>
			if ($tag = $this->stack_pop() )
			{
				$this->out .= $this->output->close($tag);
				return;
			}
			else
			{
				// kunda underflow :)
				return;
			}
		}
		if(in_array($tag,$this->config_tags))
		{
			// enabled tag
			if(is_string($this->config_attr[$tag]))
			{
				$this->TAG = $tag = $this->config_attr[$tag];
				//tag substitution warning
			}

			$top = $this->stack_top( $tag );
			if( $top )
			{
				$this->out .= $this->output->close( $tag );
				$this->stack_pop();
				return;
			}
			
			if( $this->stack_search($tag) )
			{
				// closing cross tags
				while( ($top = $this->stack_pop()) != $tag )
				{
					/*if( !($this->tags[$top][0] & TW_OPT ) )
					{
						// auto close warning
					}*/
					$this->out .= $this->output->close( $top );
				}
				$this->out .= $this->output->close( $tag );
			}
			else
			{
				// ###### cross tag error
				return;
			}
		}
		// drop out warning 		
 	}


/*
 * THERE ARE STATE IN, OUT & NEW FUNCTIONS
 *
 */
 
/********************************************************************************************
 * STATE T_begin 	'<'
 */
	function T_begin_in()
	{
		$this->tag_position = $this->pti;
		$this->TAG = null;
	}

	// --- MAIN TAG-FILTER FUNCTION ---

	function T_begin_out($word)
	{
		$this->START_TAG_filter();
	}

/********************************************************************************************
 * STATE TC_begin 	'</'
 */
	function T_Cbegin_in()
	{
		$this->tag_position = $this->pti;
		$this->TAG = null;
	}

	// TAG CLOSE function
	//
	function T_Cbegin_out($word)
	{
		$this->END_TAG_filter();
	}

/********************************************************************************************
 * STATE T_gettag 	'tagname'
 */
	function T_gettag_in() 
	{
		$this->is_attributes = 0;
		$this->ATTRIBUTES = null;
		return;
	}

	function T_gettag_new($word)	{	$this->TAG = strtolower($word);	}	
	function T_gettag_out($word)	{ return; }

/********************************************************************************************
 * STATE A_begin	'__atr...'
 */
	function A_begin_in()	{		$this->ATTR = null;	}
	function A_begin_new($word)	{	$this->ATTR[0] = strtolower($word);	}
	function A_begin_out($word)
	{
		if( $this->is_attributes )
		{
			foreach( $this->ATTRIBUTES as $akey => $aval )
			{
			    if($this->ATTR[0] == $aval[0])
			    {
				// duplicate warning
				$this->ATTRIBUTES[$akey] = $aval;
				return;
			    }
			}
		}
		else
		{
			$this->is_attributes = 1;
		}
		$this->ATTRIBUTES[] = $this->ATTR;
	}

/********************************************************************************************
 * STATE V_begin	'atr...'
 */
	function V_begin_in()	{		$this->VALUE = null;	}
	function V_begin_out($word)	{	$this->ATTR[1] = $this->VALUE;	}

/********************************************************************************************
 * STATE VALUE1
 */
 	function VALUE1_in() { return; }
 	function VALUE1_out($word) { $this->VALUE = substr($word,0,strlen($word)-1); }

/********************************************************************************************
 * STATE VALUE2
 */
 	function VALUE2_in() { return; }
 	function VALUE2_out($word) 
 	{
 		$this->VALUE = str_replace("\"", "&#34", substr($word, 0, strlen($word)-1) );
	}
/********************************************************************************************
 * STATE VALUE3
 */
 	function VALUE3_in() { return; }
 	function VALUE3_out($word) { $this->VALUE = $word; }
 	
 	
} // END class TW_base
?>