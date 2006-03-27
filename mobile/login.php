<?
//	require_once "sessions.php";

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

	if ($_COOKIE["ttrss_sid"]) {
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

			setcookie("ttrss_cltime", $_SESSION["cookie_lifetime"], 
				$_SESSION["cookie_lifetime"]);

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

	<div class="main">

	<h1>Tiny Tiny RSS</h1>

	<form action="login.php" method="POST">

		Login: <input name="login"><br>
		Password: <input type="password" name="password"><br>

		<input type="checkbox" name="remember_me" id="remember_me">
		<label for="remember_me">Remember me</label><br>
		
		<input type="submit" class="button" value="Login">
		<input type="hidden" name="rt" value="<?= $_GET['rt'] ?>">

	</form>

	</div>
</body>
</html>

<? db_close($link); ?>

