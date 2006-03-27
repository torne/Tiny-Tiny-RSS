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

<table width='640' height='100%'>
<tr><td class="heading">
	Your Feeds
</td>
<td align='right'>
	<form method="GET">
		<select name="go">
			<option>Feeds</option>
			<option>Preferences</option>
			<option disabled>--------------</option>
			<option disabled>--------------</option>
			<option>Logout</option>
		</select>
		<input type="submit" value="Go">
	</form>
</td>
</tr>
<td class="content" height='100%' colspan='2' valign='top'>
<?
	$go = $_GET["go"];

	if (!$go || $go == "Feeds") {
		render_feeds_list($link);
	}

?>
</td></tr>
</table>

</body>
</html>
