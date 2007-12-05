<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	require_once "../config.php";
	require_once "functions.php";
	require_once "../functions.php"; 

	require_once "../sessions.php";

	require_once "../version.php"; 
	require_once "../config.php";
	require_once "../db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (DB_TYPE == "pgsql") {
		pg_query("set client_encoding = 'UTF-8'");
		pg_set_client_encoding("UNICODE");
	} else {
		if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
			db_query($link, "SET NAMES " . MYSQL_CHARSET);
//			db_query($link, "SET CHARACTER SET " . MYSQL_CHARSET);
		}
	}

	login_sequence($link, true);

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

	$ts_id = db_escape_string($_GET["ts"]);

	if ($go == "vf" && $ts_id) {
		$result = db_query($link, "UPDATE ttrss_user_entries SET marked = NOT marked
			WHERE ref_id = '$ts_id' AND owner_uid = " . $_SESSION["uid"]);
		$query_string = preg_replace("/&ts=[0-9]*/", "", $_SERVER["QUERY_STRING"]);
		header("Location: tt-rss.php?$query_string");
		return;
	}

	$tp_id = db_escape_string($_GET["tp"]);

	if ($go == "vf" && $tp_id) {
		$result = db_query($link, "UPDATE ttrss_user_entries SET published = NOT published
			WHERE ref_id = '$tp_id' AND owner_uid = " . $_SESSION["uid"]);
		$query_string = preg_replace("/&tp=[0-9]*/", "", $_SERVER["QUERY_STRING"]);
		header("Location: tt-rss.php?$query_string");
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
<?php
	if (!$go) {
		render_feeds_list($link);
	} else if ($go == "vf") {
		render_headlines($link);	
	} else if ($go == "view") {
		render_article($link);
	} else if ($go == "sform") {
		render_search_form($link, $_GET["aid"], $_GET["ic"]);
	} else {
		print __("Internal error: Function not implemented");
	}

?>
</div>

<div id="footer">
	<a href="http://tt-rss.spb.ru/">Tiny-Tiny RSS</a> v<?php echo VERSION ?> &copy; 2005-2007 Andrew Dolgov
</div>

</body>
</html>
