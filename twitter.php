<?php
	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	require_once "lib/twitteroauth/twitteroauth.php";
	
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	init_connection($link);	
	login_sequence($link);
	
	$owner_uid = $_SESSION["uid"];
	$op = $_REQUEST['op'];

	if (!SINGLE_USER_MODE && !$_SESSION['uid']) { 
		render_login_form($link);
		exit;
	}

	$callback_url = get_self_url_prefix() . "/twitter.php?op=callback";

	if ($op == 'clear') {
		/* Remove no longer needed request tokens */
		unset($_SESSION['oauth_token']);
		unset($_SESSION['oauth_token_secret']);
		unset($_SESSION['access_token']);

		header("Location: twitter.php");
		return;
	}

	if ($op == 'callback') {
		/* If the oauth_token is old redirect to the connect page. */
		if (isset($_REQUEST['oauth_token']) && 
				$_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {

		  $_SESSION['oauth_status'] = 'oldtoken';
		  header('Location: twitter.php?op=clear');
		  return;
		}

		/* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

		/* Request access tokens from twitter */
		$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);

		/* If HTTP response is 200 continue otherwise send to connect page to retry */
		if ($connection->http_code == 200) {
			$access_token = db_escape_string(json_encode($access_token));

			db_query($link, "UPDATE ttrss_users SET twitter_oauth = '$access_token'
				WHERE id = ".$_SESSION['uid']);

		} else {
			header('Location: twitter.php?op=clear');
			return;
		}

	}

	if ($op == 'register') {

		/* Build TwitterOAuth object with client credentials. */
		$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);

		/* Get temporary credentials. */
		$request_token = $connection->getRequestToken($callback_url);
		
		/* Save temporary credentials to session. */
		$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
		$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

		if ($connection->http_code == 200) {
		    $url = $connection->getAuthorizeURL($token);
			 header('Location: ' . $url); 
			 return;
		}
	}
?>

<html>
<head>
<title>Register with Twitter</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="utility.css">
</head>

<body>

<h1>Register with Twitter</h1>

<?php if ($op == 'register') { ?>

<p><?php print_error('Could not connect to Twitter. Refresh the page or try again later.') ?></p>

<?php } else if ($op == 'callback') { ?>

	<?php print_notice('Congratulations! You have successfully registered with Twitter.') ?>
		</p>

	<form method="GET" action="prefs.php">
		<input type="hidden" name="tab" value="feedConfig">
		<button type="submit">Return to Tiny Tiny RSS</button>
	</form>

<?php } else { ?>
	<form method="GET" action="twitter.php">
		<input type="hidden" name="op" value="register">
		<button type="submit">Register with Twitter</button>
	</form>

<?php } ?>


</body>
</html>
