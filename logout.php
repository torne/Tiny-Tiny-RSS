<?
	require_once "sessions.php";

	require_once "config.php";
	require_once "functions.php";

	logout_user();

	if (!USE_HTTP_AUTH) {
		$url_path = get_script_urlpath();

		if (ENABLE_LOGIN_SSL) {
			$protocol = "https";
		} else {
			$protocol = "http";
		}		

		$redirect_base = "$protocol://" . $_SERVER["SERVER_NAME"] . $url_path;

		header("Location: $redirect_base/login.php");
	} else { ?>
	
	<html>
		<head>
			<title>Tiny Tiny RSS : Logout</title>
			<link rel="stylesheet" type="text/css" href="tt-rss.css">
	<body class="logoutBody">
		<div class="logoutContent">	
		
			<h1>You have been logged out.</h1>

			<p><span class="logoutWarning">Warning:</span>
			As there is no way to reliably clear HTTP Authentication 
			credentials from your browser, it is recommended for you to close
			this browser window, otherwise your browser could automatically
			authenticate again using previously supplied credentials, which
			is a security risk.</p>
			
		</div>
	</body>
	</html>
<?	} ?>
