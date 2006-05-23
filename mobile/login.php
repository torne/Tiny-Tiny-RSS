<?
	require_once "functions.php";

	require_once "../version.php"; 
	require_once "../config.php";
	require_once "../functions.php";

	$url_path = get_script_urlpath();
	$redirect_base = "http://" . $_SERVER["SERVER_NAME"] . $url_path;

	if (SINGLE_USER_MODE) {
		header("Location: $redirect_base/tt-rss.php");
		exit;
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$login = $_POST["login"];
	$password = $_POST["password"];
	$return_to = $_POST["rt"];

	if ($_COOKIE[get_session_cookie_name()]) {
		require_once "../sessions.php";
		if ($_SESSION["uid"]) {
			initialize_user_prefs($link, $_SESSION["uid"]); 
			header("Location: $redirect_base/tt-rss.php");
			exit;
		}
	}

	if ($login && $password) {

		if ($_POST["remember_me"]) {
			session_set_cookie_params(SESSION_COOKIE_LIFETIME_REMEMBER);
		} else {
			session_set_cookie_params(SESSION_COOKIE_LIFETIME);
		}
			
		require_once "../sessions.php";

		if (authenticate_user($link, $login, $password)) {
			initialize_user_prefs($link, $_SESSION["uid"]); 

			if ($_POST["remember_me"]) {
				$_SESSION["cookie_lifetime"] = time() + SESSION_COOKIE_LIFETIME_REMEMBER;
			} else {
				$_SESSION["cookie_lifetime"] = time() + SESSION_COOKIE_LIFETIME;
			}

			if (!$return_to) {
				$return_to = "tt-rss.php";
			}
			header("Location: $redirect_base/$return_to");
			exit;
		}
	}

?>
<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body>

	<div id="content">
	<div id="heading">Tiny Tiny RSS</div>

	<form action="login.php" method="POST">
	<input type="hidden" name="rt" value="<?= $_GET['rt'] ?>">

	<table>
		<tr><td align='right'>Login:</td><td><input name="login"></td>
		<tr><td align='right'>Password:</td><td><input type="password" name="password"></tr>

		<tr><td colspan='2'>
			<input type="submit" class="button" value="Login">
			<input type="checkbox" name="remember_me" id="remember_me">
			<label for="remember_me">Remember me</label></td></tr>
		</table>

	</form>

	</div>

</body>
</html>

<? db_close($link); ?>

