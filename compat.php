<?

if (!function_exists('mb_strlen')) 
{
  // from Typo3
  // author Martin Kutschker <martin.t.kutschker@blackbox.net>
  function mb_strlen($str, $encoding="utf-8") 
    {
      if($encoding != "utf-8")
	{ return -1; }

      $n=0;

      for($i=0; isset($str{$i}); $i++) 
	{	  
	  $c = ord($str{$i});

	  if (!($c & 0x80)) // single-byte (0xxxxxx)
	    $n++;

	  elseif (($c & 0xC0) == 0xC0) // multi-byte starting byte (11xxxxxx)
	    $n++;
	}

      return $n;
    }
}

if (!function_exists('mb_substr'))
{
  // from Typo3
  // author Martin Kutschker <martin.t.kutschker@blackbox.net>
  function mb_substr($str, $start, $len=null, $encoding="utf-8")
    {
      if($encoding != "utf-8")
	{ return -1; }

      $byte_start = utf8_char2byte_pos($str,$start);

      if ($byte_start === false) 
	return false; // $start outside string length 

      $str = substr($str,$byte_start); 

      if ($len != null) 
	{
	  $byte_end = utf8_char2byte_pos($str,$len);

	  if ($byte_end === false) // $len outside actual string length
	    return $str;
	  else	  
	    return substr($str,0,$byte_end);
	}

      else return $str;
    }

  function utf8_char2byte_pos($str,$pos) 
    {
      $n = 0;  // number of characters found     
      $p = abs($pos); // number of characters wanted 

      if ($pos >= 0) 
	{
	  $i = 0;
	  $d = 1;
	} else {       
	  $i = strlen($str)-1;
	  $d = -1;
	} 

      for( ; isset($str{$i}) && $n<$p; $i+=$d) 
	{
	  $c = (int)ord($str{$i});
	  
	  if (!($c & 0x80)) // single-byte (0xxxxxx)	    
	    $n++;
	  elseif (($c & 0xC0) == 0xC0) // multi-byte starting byte (11xxxxxx)
	    $n++;
	}

      if (!isset($str{$i})) 
	return false; // offset beyond string length 

      if ($pos >= 0) 
	{
	  // skip trailing multi-byte data bytes
	  while ((ord($str{$i}) & 0x80) && !(ord($str{$i}) & 0x40)) 
	    { $i++; }
	} else {	  
	  // correct offset
	  $i++;
	} 
          
      return $i;      
    }
}
