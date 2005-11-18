<?
	require_once "version.php";
	require_once "config.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$ERRORS[0] = "Unknown error";

	$ERRORS[1] = "This program requires XmlHttpRequest " .
			"to function properly. Your browser doesn't seem to support it.";

	$ERRORS[2] = "This program requires cookies " .
			"to function properly. Your browser doesn't seem to support them.";

	$ERRORS[3] = "Backend sanity check failed.";

	$ERRORS[4] = "Frontend sanity check failed.";

	$ERRORS[5] = "Incorrect database schema version.";

?>

<html>
<head>
	<title>Tiny Tiny RSS : Error Message</title>

	<link	rel="stylesheet" href="tt-rss.css" type="text/css">
	
	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<? if (get_pref($link, 'DISPLAY_HEADER')) { ?>
<tr>
	<td colspan="2">
		<table cellspacing="0" cellpadding="0" width="100%"><tr>
			<td class="header" valign="middle">	
				<img src="images/ttrss_logo.png" alt="logo">	
			</td>
			<td align="right" valign="top">
				<div id="notify"><span id="notify_body"></div>
			</td>
		</tr></table>
	</td>
</tr>
<? } ?>
<tr>
	<td id="prefContent" class="prefContent" valign="top" colspan="2">
		
		<h1>Fatal Error</h1>

		<div class="bigErrorMsg"><?= $ERRORS[$_GET["c"]] ?></div>

	</td>
</tr>
<? if (get_pref($link, 'DISPLAY_FOOTER')) { ?>
<tr>
	<td class="footer" colspan="2">
		<a href="http://bah.spb.su/~fox/tt-rss/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
		<? if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<? } ?>
	</td>
</td>
<? } ?>
</table>



</body>
</html>

