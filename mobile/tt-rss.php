<?
	require_once "../functions.php"; 
	require_once "functions.php";

	basic_nosid_redirect_check();

	require_once "../sessions.php";

	require_once "../version.php"; 
	require_once "../config.php";
	require_once "../db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

	/* perform various redirect-needing subops */

	$subop = db_escape_string($_GET["subop"]);
	$go = $_GET["go"];

	if ($subop == "tc" && !$go) {
		
		$cat_id = db_escape_string($_GET["id"]);
			
		if ($cat_id != 0) {			
			db_query($link, "UPDATE ttrss_feed_categories SET
				collapsed = NOT collapsed WHERE id = '$cat_id' AND owner_uid = " . 
				$_SESSION["uid"]);
		} else {
			if ($_COOKIE["ttrss_vf_uclps"] != 1) {
				setcookie("ttrss_vf_uclps", 1);
			} else {
				setcookie("ttrss_vf_uclps", 0);
			}
		}
		
		header("Location: tt-rss.php");
		return;
	}

?>
<html>
<head>
	<title>Tiny Tiny RSS - Mobile</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>

<div id="content">
<?
	if (!$go) {
		render_feeds_list($link);
	} else if ($go == "vf") {
		render_headlines($link);	
	} else if ($go == "view") {
		render_article($link);
	} else {
		print "Function not implemented";
	}

?>
</div>

<div id="footer">
	<a href="http://tt-rss.spb.ru/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005-2006 Andrew Dolgov
</div>

</body>
</html>
