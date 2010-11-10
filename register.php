<?php
	// This file uses two additional include files:
	//
	// 1) templates/register_notice.txt - displayed above the registration form
	// 2) register_expire_do.php - contains user expiration queries when necessary

	$action = $_REQUEST["action"];

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "config.php";
	require_once "db.php";
	
	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	init_connection($link);	

	/* Remove users which didn't login after receiving their registration information */

	if (DB_TYPE == "pgsql") {
		db_query($link, "DELETE FROM ttrss_users WHERE last_login IS NULL 
				AND created < NOW() - INTERVAL '1 day' AND access_level = 0");
	} else {
		db_query($link, "DELETE FROM ttrss_users WHERE last_login IS NULL 
				AND created < DATE_SUB(NOW(), INTERVAL 1 DAY) AND access_level = 0");
	}

	if (file_exists("register_expire_do.php")) {
		require_once "register_expire_do.php";
	}

	if ($action == "check") {
		header("Content-Type: application/xml");

		$login = trim(db_escape_string($_REQUEST['login']));

		$result = db_query($link, "SELECT id FROM ttrss_users WHERE
			LOWER(login) = LOWER('$login')");
	
		$is_registered = db_num_rows($result) > 0;

		print "<result>";

		printf("%d", $is_registered);

		print "</result>";

		return;
	}
?>

<html>
<head>
<title>Create new account</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="utility.css">
<script type="text/javascript" src="functions.js"></script>
<script type="text/javascript" src="lib/prototype.js"></script>
<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
</head>

<script type="text/javascript">

	function checkUsername() {

		try {
			var f = document.forms['register_form'];
			var login = f.login.value;

			if (login == "") {
				new Effect.Highlight(f.login);
				f.sub_btn.disabled = true;
				return false;
			}

			var query = "register.php?action=check&login=" + 
					param_escape(login);

			new Ajax.Request(query, {
				onComplete: function(transport) { 

					try {

						var reply = transport.responseXML;

						var result = reply.getElementsByTagName('result')[0];
						var result_code = result.firstChild.nodeValue;

						if (result_code == 0) {
							new Effect.Highlight(f.login, {startcolor : '#00ff00'});
							f.sub_btn.disabled = false;
						} else {
							new Effect.Highlight(f.login, {startcolor : '#ff0000'});
							f.sub_btn.disabled = true;
						}					
					} catch (e) {
						exception_error("checkUsername_callback", e);
					}

				} });

		} catch (e) {
			exception_error("checkUsername", e);
		}

		return false;

	}

	function validateRegForm() {
		try {

			var f = document.forms['register_form'];

			if (f.login.value.length == 0) {
				new Effect.Highlight(f.login);
				return false;
			}

			if (f.email.value.length == 0) {
				new Effect.Highlight(f.email);
				return false;
			}

			if (f.turing_test.value.length == 0) {
				new Effect.Highlight(f.turing_test);
				return false;
			}

			return true;

		} catch (e) {
			exception_error("validateRegForm", e);
			return false;
		}
	}

</script>

<body>

<div class="floatingLogo"><img src="images/ttrss_logo.png"></div>

<h1><?php echo __("Create new account") ?></h1>

<?php
		if (!ENABLE_REGISTRATION) {
			print_error(__("New user registrations are administratively disabled."));

			print "<p><form method=\"GET\" action=\"logout.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
			return;
		}
?>

<?php if (REG_MAX_USERS > 0) {
		$result = db_query($link, "SELECT COUNT(*) AS cu FROM ttrss_users");
		$num_users = db_fetch_result($result, 0, "cu");
} ?>

<?php if (!REG_MAX_USERS || $num_users < REG_MAX_USERS) { ?>

	<!-- If you have any rules or ToS you'd like to display, enter them here -->

	<?php	if (file_exists("templates/register_notice.txt")) {
			require_once "templates/register_notice.txt";
	} ?>

	<?php if (!$action) { ?>
	
	<p><?php echo __('Your temporary password will be sent to the specified email. Accounts, which were not logged in once, are erased automatically 24 hours after temporary password is sent.') ?></p> 
	
	<form action="register.php" method="POST" name="register_form">
	<input type="hidden" name="action" value="do_register">
	<table>
	<tr>
	<td><?php echo __('Desired login:') ?></td><td>
		<input name="login">
	</td><td>
		<input type="submit" value="<?php echo __('Check availability') ?>" onclick='return checkUsername()'>
	</td></tr>
	<td><?php echo __('Email:') ?></td><td>
		<input name="email">
	</td></tr>
	<td><?php echo __('How much is two plus two:') ?></td><td>
		<input name="turing_test"></td></tr>
	<tr><td colspan="2" align="right">
	<input type="submit" name="sub_btn" value="<?php echo __('Submit registration') ?>"
			disabled="true" onclick='return validateRegForm()'>
	</td></tr>
	</table>
	</form>

	<?php print "<p><form method=\"GET\" action=\"tt-rss.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>"; ?>

	<?php } else if ($action == "do_register") { ?>
	
	<?php
		$login = mb_strtolower(trim(db_escape_string($_REQUEST["login"])));
		$email = trim(db_escape_string($_REQUEST["email"]));
		$test = trim(db_escape_string($_REQUEST["turing_test"]));
	
		if (!$login || !$email || !$test) {
			print_error(__("Your registration information is incomplete."));
			print "<p><form method=\"GET\" action=\"tt-rss.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
			return;
		}
	
		if ($test == "four" || $test == "4") {
	
			$result = db_query($link, "SELECT id FROM ttrss_users WHERE
				login = '$login'");
		
			$is_registered = db_num_rows($result) > 0;
		
			if ($is_registered) {
				print_error(__('Sorry, this username is already taken.'));
				print "<p><form method=\"GET\" action=\"tt-rss.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
			} else {
	
				$password = make_password();
	
				$pwd_hash = encrypt_password($password, $login);
	
				db_query($link, "INSERT INTO ttrss_users 
					(login,pwd_hash,access_level,last_login, email, created)
					VALUES ('$login', '$pwd_hash', 0, null, '$email', NOW())");
	
				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					login = '$login' AND pwd_hash = '$pwd_hash'");
		
				if (db_num_rows($result) != 1) {
					print_error(__('Registration failed.'));
					print "<p><form method=\"GET\" action=\"tt-rss.php\">
					<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
					</form>";
				} else {
	
					$new_uid = db_fetch_result($result, 0, "id");
		
					initialize_user($link, $new_uid);
	
					$reg_text = "Hi!\n".
						"\n".
						"You are receiving this message, because you (or somebody else) have opened\n".
						"an account at Tiny Tiny RSS.\n".
						"\n".
						"Your login information is as follows:\n".
						"\n".
						"Login: $login\n".
						"Password: $password\n".
						"\n".
						"Don't forget to login at least once to your new account, otherwise\n".
						"it will be deleted in 24 hours.\n".
						"\n".
						"If that wasn't you, just ignore this message. Thanks.";
			
					$mail = new PHPMailer();
			
					$mail->PluginDir = "lib/phpmailer/";
					$mail->SetLanguage("en", "lib/phpmailer/language/");
			
					$mail->CharSet = "UTF-8";
			
					$mail->From = DIGEST_FROM_ADDRESS;
					$mail->FromName = DIGEST_FROM_NAME;
					$mail->AddAddress($email);
			
					if (DIGEST_SMTP_HOST) {
						$mail->Host = DIGEST_SMTP_HOST;
						$mail->Mailer = "smtp";
						$mail->Username = DIGEST_SMTP_LOGIN;
						$mail->Password = DIGEST_SMTP_PASSWORD;
					}
			
			//		$mail->IsHTML(true);
					$mail->Subject = "Registration information for Tiny Tiny RSS";
					$mail->Body = $reg_text;
			//		$mail->AltBody = $digest_text;
			
					$rc = $mail->Send();
			
					if (!$rc) print_error($mail->ErrorInfo);
		
					$reg_text = "Hi!\n".
						"\n".
						"New user had registered at your Tiny Tiny RSS installation.\n".
						"\n".
						"Login: $login\n".
						"Email: $email\n";
			
					$mail = new PHPMailer();
			
					$mail->PluginDir = "lib/phpmailer/";
					$mail->SetLanguage("en", "lib/phpmailer/language/");
			
					$mail->CharSet = "UTF-8";
			
					$mail->From = DIGEST_FROM_ADDRESS;
					$mail->FromName = DIGEST_FROM_NAME;
					$mail->AddAddress(REG_NOTIFY_ADDRESS);
			
					if (DIGEST_SMTP_HOST) {
						$mail->Host = DIGEST_SMTP_HOST;
						$mail->Mailer = "smtp";
						$mail->Username = DIGEST_SMTP_LOGIN;
						$mail->Password = DIGEST_SMTP_PASSWORD;
					}
			
			//		$mail->IsHTML(true);
					$mail->Subject = "Registration notice for Tiny Tiny RSS";
					$mail->Body = $reg_text;
			//		$mail->AltBody = $digest_text;
			
					$rc = $mail->Send();
	
					print_notice(__("Account created successfully."));
	
					print "<p><form method=\"GET\" action=\"tt-rss.php\">
					<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
					</form>";
	
				}
	
			}
	
			} else {
				print_error('Plese check the form again, you have failed the robot test.');
				print "<p><form method=\"GET\" action=\"tt-rss.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>";
	
			}
		}
	?>

<?php } else { ?>

	<?php print_notice(__('New user registrations are currently closed.')) ?>

	<?php print "<p><form method=\"GET\" action=\"tt-rss.php\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form>"; ?>

<?php } ?>

</body>
</html>

