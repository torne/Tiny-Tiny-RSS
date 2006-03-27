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

?>
<html>
<head>
	<title>Tiny Tiny RSS - Mobile</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
</head>
<body>

<div id="opsel">
	<form method="GET">
		<select name="go">
			<option>Feeds</option>
			<option>Preferences</option>
			<option disabled>--------------</option>
			<option disabled>[user feed list]</option>
			<option disabled>--------------</option>
			<option>Logout</option>
		</select>
		<input type="submit" value="Go">
	</form>
</div>

<div id="content">
<?
	$go = $_GET["go"];

	if (!$go || $go == "Feeds") {
		render_feeds_list($link);
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
