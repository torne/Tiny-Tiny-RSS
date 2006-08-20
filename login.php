<?php
//	require_once "sessions.php";

	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "functions.php";

	$error_msg = "";

	$url_path = get_script_urlpath();

	if (ENABLE_LOGIN_SSL) {		
		$redirect_base = "https://" . $_SERVER["SERVER_NAME"] . $url_path;
	} else {
		$redirect_base = "http://" . $_SERVER["SERVER_NAME"] . $url_path;
	}

	if (SINGLE_USER_MODE) {
		header("Location: $redirect_base/tt-rss.php");
		exit;
	}

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	$login = $_POST["login"];
	$password = $_POST["password"];
	$return_to = $_POST["rt"];
	$action = $_POST["action"];

	if ($_COOKIE[get_session_cookie_name()]) {
		require_once "sessions.php";
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
			
		require_once "sessions.php";

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
		} else {
			$error_msg = "Error: Unable to authenticate user. Please check login and password.";
		}
	} else if ($action) {
		$error_msg = "Error: Either login or password is blank.";
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

<script type="text/javascript">
function init() {

	if (arguments.callee.done) return;
	arguments.callee.done = true;		

	var login = document.forms["loginForm"].login;

	login.focus();

}
</script>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<form action="login.php" method="POST" name="loginForm">

<table width="100%" class="loginForm2">
<tr>
	<td class="loginTop" valign="bottom" align="left">
		<img src="images/ttrss_logo_big.png" alt="Logo">
	</td>
</tr><tr>
	<td align="center" valign="middle" class="loginMiddle" height="100%">
		<?php if ($error_msg) { ?>
			<div class="loginError"><?php echo $error_msg ?></div>
		<?php } ?>
		<table>
			<tr><td align="right">Login:</td>
			<td align="right"><input name="login"></td></tr>
			<tr><td align="right">Password:</td>
			<td align="right"><input type="password" name="password"></td></tr>
			<tr><td colspan="2">
				<input type="checkbox" name="remember_me" id="remember_me">
				<label for="remember_me">Remember me on this computer</label>
			</td></tr>
			<tr><td colspan="2" align="right" class="innerLoginCell">
				<input type="submit" class="button" value="Login">
				<input type="hidden" name="action" value="login">
				<input type="hidden" name="rt" value="<?php echo $_GET['rt'] ?>">
			</td></tr>
		</table>
	</td>
</tr><tr>
	<td align="center" class="loginBottom">
		<a href="http://tt-rss.spb.ru/">Tiny Tiny RSS</a> &copy; 2005-2006 Andrew Dolgov
		<?php if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<?php } ?>
	</td>
</tr>

</table>

</form>

<?php db_close($link); ?>

<script type="text/javascript">
	/* for IE */
	function statechange() {
		if (document.readyState == "interactive") init();
	}

	if (document.readyState) {	
		if (document.readyState == "interactive" || document.readyState == "complete") {
			init();
		} else {
			document.onreadystatechange = statechange;
		}
	}
</script>

</body>
</html>
