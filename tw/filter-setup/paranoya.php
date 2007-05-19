<?
	// WARNING:
	//
	// All tags used in configuration must be defined in tw/tw-tags.php file too.
	//

	$tw_paranoya_setup = array(
		
		"a"		=>	array(
						"href"	=> array( TW_RQ_URL ),		// value is required url 
						"name"	=> array( TW_RQ_LINK ),		// value is link (link+href combination must be fixed in base)
						"title"	=> null,
						),
						
		"hr"	=>	null,	// without attributes
		"br"	=>	null,
		"img"	=>	array(
						"width" => array( TW_NUM, 80, 60, 120 ),	// 80 - default, number must be in range <60,120>
						"height"=> array( TW_NUM, 80, 60, 120 ),
						"src"	=> array( TW_RQ_URL ),
						"title"	=> null,
						"border"=> array( TW_RQ_NUM,  0,  0,  0),
						),

		"p"		=>	array(
						// null - default value (null = remove attr if value not found in case array)
						"class"	=> array( TW_CASE, null, array("par1","par2","par3") ),
						),
						
		"b"		=>	"strong",		// tag substitution <b> -> <strong>
		"strong"	=>	null,			// new tag must be configured too
		"i"		=>	null,
		"u"		=>	null,
		"div"	=>	array(
						"title"	=> null,
						),
		
		"span"	=>	array(
						"class"	=> array( TW_CASE, null, array("my-class1","my-class2","my-class3") ),
						),
						
		"blockquote"	=>	null,
		"h1"	=>	null,
		"h2"	=>	null,
		"h3"	=>	null,
		
		"table"	=>	null,
		"td"	=>	null,
		"tr"	=>	null,
		"th"	=>	null,
		
		"ul"	=>	null,
		"ol"	=>	null,
		"li"	=>	null,
		"dl"	=>	null,
		"dt"	=>	null,
		"dd"	=>	null,
	);
?>