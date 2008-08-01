<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="tt-rss.css">
	<link rel="shortcut icon" type="image/png" href="images/favicon.png">
	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<script type="text/javascript" src="prototype.js"></script>
	<script type="text/javascript" src="scriptaculous/scriptaculous.js"></script>
	<script type="text/javascript" src="functions.js"></script>
</head>

<body>

<script type="text/javascript">
function init() {

	if (arguments.callee.done) return;
	arguments.callee.done = true;		

	var login = document.forms["loginForm"].login;

	var limit_set = getCookie("ttrss_bwlimit");

	if (limit_set == "true") {
		document.forms["loginForm"].bw_limit.checked = true;
	}

	login.focus();

}
function languageChange(elem) {
	try {
		document.forms['loginForm']['click'].disabled = true;
	
		var lang = elem[elem.selectedIndex].value;
		setCookie("ttrss_lang", lang, <?php print SESSION_COOKIE_LIFETIME ?>);
		window.location.reload();
	} catch (e) {
		exception_error("languageChange", e);
	}
}

function bwLimitChange(elem) {
	try {
		var limit_set = elem.checked;

		setCookie("ttrss_bwlimit", limit_set, 
			<?php print SESSION_COOKIE_LIFETIME ?>);

	} catch (e) {
		exception_error("bwLimitChange", e);
	}
}

function validateLoginForm(f) {
	try {

		if (f.login.value.length == 0) {
			new Effect.Highlight(f.login);
			return false;
		}

		if (f.password.value.length == 0) {
			new Effect.Highlight(f.password);
			return false;
		}

		document.forms['loginForm']['click'].disabled = true;

		return true;
	} catch (e) {
		exception_error("validateLoginForm", e);
		return true;
	}
}
</script>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<form action="" method="POST" name="loginForm" onsubmit="return validateLoginForm(this)">
<input type="hidden" name="login_action" value="do_login">

<table width="100%" class="loginForm2">
<tr>
	<td class="loginTop" valign="bottom" align="left">
		<img src="images/ttrss_logo_big.png" alt="Logo">
	</td>
</tr><tr>
	<td align="center" valign="middle" class="loginMiddle" height="100%">
		<?php if ($_SESSION['login_error_msg']) { ?>
			<div class="loginError"><?php echo $_SESSION['login_error_msg'] ?></div>
			<?php $_SESSION['login_error_msg'] = ""; ?>
		<?php } ?>
		<table>
			<tr><td align="right"><?php echo __("Login:") ?></td>
			<td align="right"><input name="login" 
				value="<?php echo $_SERVER["REMOTE_USER"] ?>"></td></tr>
			<tr><td align="right"><?php echo __("Password:") ?></td>
			<td align="right"><input type="password" name="password"
				value="<?php echo $_SERVER["REMOTE_USER"] ?>"></td></tr>
			<?php if (ENABLE_TRANSLATIONS) { ?>
			<tr><td align="right"><?php echo __("Language:") ?></td>
			<td align="right">
			<?php
				print_select_hash("language", $_COOKIE["ttrss_lang"], get_translations(),
					"style='width : 100%' onchange='languageChange(this)'");

			?>
			</td></tr>
			<?php } ?>
			<!-- <tr><td colspan="2">
				<input type="checkbox" name="remember_me" id="remember_me">
				<label for="remember_me">Remember me on this computer</label>
			</td></tr> -->

			<tr><td colspan="2" align="right" class="innerLoginCell">

			<?php if (defined('_ENABLE_REGISTRATION')) { ?>
				<a class="newAcctPrompt" href="register.php">Create new account</a>
			<?php } ?>

			<input type="submit" class="button" value="<?php echo __('Log in') ?>" name='click'>
				<input type="hidden" name="action" value="login">
				<input type="hidden" name="rt" 
					value="<?php if ($return_to != 'none') { echo $return_to; } ?>">
			</td></tr>

			<tr><td colspan="2" align="right" class="innerLoginCell">

			<div class="small">
			<input name="bw_limit" id="bw_limit" type="checkbox"
				onchange="bwLimitChange(this)">
			<label for="bw_limit">
			<?php echo __("Limit bandwidth usage") ?></label></div>

			</td></tr>


		</table>
	</td>
</tr><tr>
	<td align="center" class="loginBottom">
		<a href="http://tt-rss.org/">Tiny Tiny RSS</a> &copy; 2005&ndash;2008 <a href="http://bah.org.ru/">Andrew Dolgov</a>
	</td>
</tr>

</table>

</form>

</body></html>
