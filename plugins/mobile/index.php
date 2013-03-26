<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	$basedir = dirname(dirname(dirname(__FILE__)));

	set_include_path(
		dirname(__FILE__) . PATH_SEPARATOR .
		$basedir . PATH_SEPARATOR .
		"$basedir/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "config.php";
	require_once "mobile-functions.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	login_sequence($link, true);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
         "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Tiny Tiny RSS</title>
<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;"/>
<link rel="apple-touch-icon" href="iui/iui-logo-touch-icon.png" />
<meta name="apple-touch-fullscreen" content="YES" />
<style type="text/css" media="screen">@import "iui/iui.css";</style>
<script type="application/x-javascript" src="iui/iui.js"></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<script type="text/javascript" src="../../lib/prototype.js"></script>
<script type="text/javascript" src="mobile.js"></script>
<style type="text/css" media="screen">@import "mobile.css";</style>
</head>

<style type="text/css">
	img { max-width : 75%; }

	li.oldItem {
		color : gray;
	}

	#myBackButton {
	    display: none;
	    left: 6px;
	    right: auto;
	    padding: 0;
	    max-width: 55px;
	    border-width: 0 8px 0 14px;
	    -webkit-border-image: url(iui/backButton.png) 0 8 0 14;
	}

	img.tinyIcon {
		max-width : 16px;
		max-height : 16px;
		margin-right : 10px;
		vertical-align : middle;
	}

	a img {
		border-width : 0px;
	}
</style>

<body>
    <div class="toolbar">
        <h1 id="pageTitle"></h1>
		  <a id="myBackButton" class="button" href="#"></a>
        <a class="button" href="prefs.php">Preferences</a>
    </div>

	<?php
	$use_cats = mobile_get_pref($link, 'ENABLE_CATS');
	$offset = (int) db_escape_string($link, $_REQUEST["skip"]);

	if ($use_cats) {
		render_categories_list($link);
	} else {
		render_flat_feed_list($link, $offset);
	}
	?>

</body>
</html>
