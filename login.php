<?
	session_start();

	require_once "version.php"; 
	require_once "config.php";

	$_SESSION["uid"] = PLACEHOLDER_UID; // FIXME: placeholder
	$_SESSION["name"] = PLACEHOLDER_NAME;

?>
<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="tt-rss.css">
	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body>

<table width='100%' height='100%' class="loginForm">

	<tr><td align='center' valign='middle'>
	
	<table class="innerLoginForm">

	<tr><td valign="middle" align="center" colspan="2">
		<img src="images/ttrss_logo.png" alt="logo">
	</td></tr>
	
	<tr><td align="right">Login:</td>
		<td><input name="login"></td></tr>
	<tr><td align="right">Password:</td>
		<td><input type="password" name="password"></td></tr>
	
	</table></td></tr>
</table>

</body>
</html>
