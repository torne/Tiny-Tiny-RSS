<?php
	set_include_path(get_include_path() . PATH_SEPARATOR . 
		dirname(__FILE__) . "/include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	//require_once "lib/twitteroauth/twitteroauth.php";
	require_once "lib/tmhoauth/tmhOAuth.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;
	login_sequence($link);

	$owner_uid = $_SESSION["uid"];
	$op = $_REQUEST['op'];

	if (!SINGLE_USER_MODE && !$_SESSION['uid']) {
		render_login_form($link);
		exit;
	}

	$callback_url = get_self_url_prefix() . "/twitter.php?op=callback";

	$tmhOAuth = new tmhOAuth(array(
	  'consumer_key'    => CONSUMER_KEY,
	  'consumer_secret' => CONSUMER_SECRET,
	));

	if ($op == 'clear') {
		unset($_SESSION['oauth']);

		header("Location: twitter.php");
		return;
	}

	if (isset($_REQUEST['oauth_verifier'])) {

		$op = 'callback';

		$tmhOAuth->config['user_token']  = $_SESSION['oauth']['oauth_token'];
		$tmhOAuth->config['user_secret'] = $_SESSION['oauth']['oauth_token_secret'];

		$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
			'oauth_verifier' => $_REQUEST['oauth_verifier']));

		if ($code == 200) {

			$access_token = json_encode($tmhOAuth->extract_params($tmhOAuth->response['response']));

			unset($_SESSION['oauth']);

			db_query($link, "UPDATE ttrss_users SET twitter_oauth = '$access_token'
				WHERE id = ".$_SESSION['uid']);

		} else {
			header('Location: twitter.php?op=clear');
			return;
		}

	}

	if ($op == 'register') {

		$code = $tmhOAuth->request('POST',
			$tmhOAuth->url('oauth/request_token', ''), array(
			    'oauth_callback' => $callback));

		if ($code == 200) {
			$_SESSION['oauth'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);

			$method = isset($_REQUEST['signin']) ? 'authenticate' : 'authorize';
			$force  = isset($_REQUEST['force']) ? '&force_login=1' : '';
			$forcewrite  = isset($_REQUEST['force_write']) ? '&oauth_access_type=write' : '';
			$forceread  = isset($_REQUEST['force_read']) ? '&oauth_access_type=read' : '';

			$location = $tmhOAuth->url("oauth/{$method}", '') .
				"?oauth_token={$_SESSION['oauth']['oauth_token']}{$force}{$forcewrite}{$forceread}";

			header("Location: $location");

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

<h1><?php echo __('Register with Twitter') ?></h1>

<?php if ($op == 'register') { ?>

<p><?php print_error(__('Could not connect to Twitter. Refresh the page or try again later.')) ?></p>

<?php } else if ($op == 'callback') { ?>

	<p><?php print_notice(__('Congratulations! You have successfully registered with Twitter.')) ?>
		</p>

	<form method="GET" action="prefs.php">
		<input type="hidden" name="tab" value="feedConfig">
		<button type="submit"><?php echo __('Return to Tiny Tiny RSS') ?></button>
	</form>

<?php } else { ?>

	<form method="GET" action="twitter.php" style='display : inline'>
		<input type="hidden" name="op" value="register">
		<button type="submit"><?php echo __('Register') ?></button>
	</form>

	<form method="GET" action="prefs.php" style='display : inline'>
		<input type="hidden" name="tab" value="feedConfig">
		<button type="submit"><?php echo __('Return to Tiny Tiny RSS') ?></button>
	</form>

<?php } ?>


</body>
</html>
