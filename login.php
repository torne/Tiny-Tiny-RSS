<?
//	require_once "sessions.php";

	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "functions.php";

	$url_path = get_script_urlpath();
	$redirect_base = "http://" . $_SERVER["SERVER_NAME"] . $url_path;

	if (SINGLE_USER_MODE) {
		header("Location: $redirect_base/tt-rss.php");
		exit;
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$login = $_POST["login"];
	$password = $_POST["password"];

	if ($login && $password) {

		if ($_POST["remember_me"]) {
			session_set_cookie_params(SESSION_COOKIE_LIFETIME_REMEMBER);
		} else {
			session_set_cookie_params(SESSION_COOKIE_LIFETIME);
		}
			
		require "sessions.php";

		if (authenticate_user($link, $login, $password)) {
			initialize_user_prefs($link, $_SESSION["uid"]); 
			
			if ($_SESSION["login_redirect"]) {
				$redirect_to = $_SESSION["login_redirect"];
			} else {
				$redirect_to = "tt-rss.php";
			}
			header("Location: $redirect_base/$redirect_to");
		}
	}

	if ($_GET["rt"]) {
		$_SESSION["login_redirect"] = $_GET["rt"];
	}

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

	<form action="login.php" method="POST">
	
	<table class="innerLoginForm">

	<tr><td valign="middle" align="center" colspan="2">
		<img src="images/ttrss_logo.png" alt="logo">
	</td></tr>
	
	<tr><td align="right">Login:</td>
		<td><input name="login"></td></tr>
	<tr><td align="right">Password:</td>
		<td><input type="password" name="password"></td></tr>
	<tr><td>&nbsp;</td><td>
			<input type="checkbox" name="remember_me" id="remember_me">
			<label for="remember_me">Remember me</label>
	</td></tr>
	<tr><td colspan="2" align="center">
		<input type="submit" class="button" value="Login">
	</td></tr>
	
	</table>
	
	</form>

	</td></tr>
</table>

<? db_close($link); ?>

</body>
</html>
