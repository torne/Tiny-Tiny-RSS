<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	define('MOBILE_VERSION', true);

	require_once "../config.php";
	require_once "functions.php";
	require_once "../functions.php"; 

	require_once "../sessions.php";

	require_once "../version.php"; 
	require_once "../db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

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

		toggleMarked($link, $ts_id);

		$query_string = preg_replace("/&ts=[0-9]*/", "", $_SERVER["QUERY_STRING"]);
		header("Location: tt-rss.php?$query_string");
		return;
	}

	$tp_id = db_escape_string($_GET["tp"]);

	if ($go == "vf" && $tp_id) {

		togglePublished($link, $tp_id);

		$query_string = preg_replace("/&tp=[0-9]*/", "", $_SERVER["QUERY_STRING"]);
		header("Location: tt-rss.php?$query_string");
		return;
	}

	$sop = db_escape_string($_GET["sop"]);

	if ($sop && $go == "view") {
		$a_id = db_escape_string($_GET["id"]);

		if ($a_id) {

			if ($sop == "tp") {
				togglePublished($link, $a_id);
			}

			if ($sop == "ts") {
				toggleMarked($link, $a_id);
			}

			$query_string = preg_replace("/&sop=t[sp]/", "", $_SERVER["QUERY_STRING"]);
			header("Location: tt-rss.php?$query_string");
		}
	}

?>
<html>
<head>
	<title>Tiny Tiny RSS - Mobile</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<script type="text/javascript" src="tt-rss.js"></script>

	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link rel="stylesheet" type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>
</head>
<body id="ttrssMobile">

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

<?php if (!$go) { ?>

<div id="footer">
	<a href="http://tt-rss.org/">Tiny-Tiny RSS</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?> 
	<?php } ?>
	&copy; 2005-2008 Andrew Dolgov
</div>

<?php } ?>

</body>
</html>
