<?php
/* --------------------------------------------------------------- *
 *        WARNING: ALL CHANGES IN THIS FILE WILL BE LOST
 *
 *   Source language file: tw/lang/TW_lang.php
 *       Language version: 1.0 (Sign:TW)
 *
 *            Target file: tw/tw_cache/TW_lang.php
 *             Build date: Wed 20.11.2002 00:29:40
 *
 *      Generator version: 0.4.1
 * --------------------------------------------------------------- */
class TW_lang extends TW_base
{
var $trans,$flags,$data,$delim,$class,$keywords;
var $version,$signature,$initial_state,$ret,$quit;
var $pt,$pti,$generator_version;
var $names;

function TW_lang ()
{
	$this->version="1.0";
	$this->signature="TW";
	$this->generator_version="0.4.1";
	$this->initial_state=0;
	$this->trans=array(0=>array("<"=>array(0=>1,1=>0,),),1=>array("ALPHA"=>array(0=>3,1=>1,),"/"=>array(0=>2,1=>0,),"<"=>array(0=>1,1=>1,),"!--"=>array(0=>11,1=>0,),"_ALL"=>array(0=>12,1=>0,),),2=>array("ALPHA"=>array(0=>4,1=>1,),"_ALL"=>array(0=>12,1=>0,),),3=>array("ALPHA"=>array(0=>4,1=>1,),"_ALL"=>array(0=>12,1=>0,),),4=>array("!ALNUM"=>array(0=>5,1=>1,),),5=>array("ALPHA"=>array(0=>6,1=>1,),">"=>array(0=>12,1=>0,),"/>"=>array(0=>12,1=>0,),),6=>array("!ALPHA"=>array(0=>7,1=>1,),">"=>array(0=>12,1=>1,),"/>"=>array(0=>12,1=>1,),),7=>array("\""=>array(0=>8,1=>0,),"'"=>array(0=>9,1=>0,),"ALNUM"=>array(0=>10,1=>1,),">"=>array(0=>12,1=>1,),"/>"=>array(0=>12,1=>1,),),8=>array("\""=>array(0=>12,1=>0,),),9=>array("'"=>array(0=>12,1=>0,),),10=>array("-"=>array(0=>10,1=>0,),"!ALNUM"=>array(0=>12,1=>1,),),11=>array("-->"=>array(0=>12,1=>0,),),);
	$this->flags=array(0=>256,1=>1024,2=>1536,3=>1536,4=>3584,5=>0,6=>3584,7=>1536,8=>512,9=>512,10=>512,11=>0,);
	$this->delim=array(0=>array(0=>"<",),1=>array(0=>"ALPHA",1=>"/",2=>"<",3=>"!--",4=>"_ALL",),2=>array(0=>"ALPHA",1=>"_ALL",),3=>array(0=>"ALPHA",1=>"_ALL",),4=>array(0=>"!ALNUM",),5=>array(0=>"ALPHA",1=>">",2=>"/>",),6=>array(0=>"!ALPHA",1=>">",2=>"/>",),7=>array(0=>"\"",1=>"'",2=>"ALNUM",3=>">",4=>"/>",),8=>array(0=>"\"",),9=>array(0=>"'",),10=>array(0=>"-",1=>"!ALNUM",),11=>array(0=>"-->",),);
	$this->ret=12;
	$this->quit=13;
	$this->names=array(0=>"OUT",1=>"T_tagWall",2=>"T_Cbegin",3=>"T_begin",4=>"T_gettag",5=>"T_in",6=>"A_begin",7=>"V_begin",8=>"VALUE1",9=>"VALUE2",10=>"VALUE3",11=>"HTML_comment",12=>"_RET",13=>"_QUIT",);
}

/* OUT */
function isd0 ()
{
$c1=$this->pt[$this->pti];
if($c1=="<")
	return array("<","<");
return false;
}

/* T_tagWall */
function isd1 ()
{
$p=$this->pti;
$c1=$this->pt[$p++];
$c2=$c1.$this->pt[$p++];
$c3=$c2.$this->pt[$p];
if(stristr("eaoinltsrvdukzmcpyhjbfgxwq",$c1))
	return array("ALPHA",$c1);
if($c1=="/")
	return array("/","/");
if($c1=="<")
	return array("<","<");
if($c3=="!--")
	return array("!--","!--");
return array("_ALL",$c1);
}

/* T_Cbegin */
function isd2 ()
{
$c1=$this->pt[$this->pti];
if(stristr("eaoinltsrvdukzmcpyhjbfgxwq",$c1))
	return array("ALPHA",$c1);
return array("_ALL",$c1);
}

/* T_begin */
function isd3 ()
{
$c1=$this->pt[$this->pti];
if(stristr("eaoinltsrvdukzmcpyhjbfgxwq",$c1))
	return array("ALPHA",$c1);
return array("_ALL",$c1);
}

/* T_gettag */
function isd4 ()
{
$c1=$this->pt[$this->pti];
if(!stristr("eaoinltsrvdukzmcpyhjbfgxwq0123456789",$c1))
	return array("!ALNUM",$c1);
return false;
}

/* T_in */
function isd5 ()
{
$p=$this->pti;
$c1=$this->pt[$p++];
$c2=$c1.$this->pt[$p];
if(stristr("eaoinltsrvdukzmcpyhjbfgxwq",$c1))
	return array("ALPHA",$c1);
if($c1==">")
	return array(">",">");
if($c2=="/>")
	return array("/>","/>");
return false;
}

/* A_begin */
function isd6 ()
{
$p=$this->pti;
$c1=$this->pt[$p++];
$c2=$c1.$this->pt[$p];
if(!stristr("eaoinltsrvdukzmcpyhjbfgxwq",$c1))
	return array("!ALPHA",$c1);
if($c1==">")
	return array(">",">");
if($c2=="/>")
	return array("/>","/>");
return false;
}

/* V_begin */
function isd7 ()
{
$p=$this->pti;
$c1=$this->pt[$p++];
$c2=$c1.$this->pt[$p];
if($c1=="\"")
	return array("\"","\"");
if($c1=="'")
	return array("'","'");
if(stristr("eaoinltsrvdukzmcpyhjbfgxwq0123456789",$c1))
	return array("ALNUM",$c1);
if($c1==">")
	return array(">",">");
if($c2=="/>")
	return array("/>","/>");
return false;
}

/* VALUE1 */
function isd8 ()
{
$c1=$this->pt[$this->pti];
if($c1=="\"")
	return array("\"","\"");
return false;
}

/* VALUE2 */
function isd9 ()
{
$c1=$this->pt[$this->pti];
if($c1=="'")
	return array("'","'");
return false;
}

/* VALUE3 */
function isd10 ()
{
$c1=$this->pt[$this->pti];
if($c1=="-")
	return array("-","-");
if(!stristr("eaoinltsrvdukzmcpyhjbfgxwq0123456789",$c1))
	return array("!ALNUM",$c1);
return false;
}

/* HTML_comment */
function isd11 ()
{
$p=$this->pti;
$c3=$this->pt[$p++].$this->pt[$p++].$this->pt[$p];
if($c3=="-->")
	return array("-->","-->");
return false;
}

}
?>