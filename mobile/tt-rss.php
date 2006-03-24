<?
	require_once "../functions.php"; 

	basic_nosid_redirect_check();

	require_once "../sessions.php";

	require_once "../version.php"; 
	require_once "../config.php";
	require_once "../db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	
?>
<html>
<head>
	<title>Tiny Tiny RSS - Mobile</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
</head>
<body>

</body>
</html>
