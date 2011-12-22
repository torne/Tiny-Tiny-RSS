<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="lib/dijit/themes/claro/claro.css"/>
	<link rel="stylesheet" type="text/css" href="tt-rss.css">
	<link rel="shortcut icon" type="image/png" href="images/favicon.png">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<script type="text/javascript" src="lib/dojo/dojo.js" djConfig="parseOnLoad: true"></script>
	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
	<script type="text/javascript" src="js/functions.js"></script>
	<script type="text/javascript" charset="utf-8" src="errors.php?mode=js"></script>
</head>

<body id="ttrssLogin" class="claro">

<script type="text/javascript">
function init() {

	dojo.require("dijit.Dialog");

	var test = setCookie("ttrss_test", "TEST");

	if (getCookie("ttrss_test") != "TEST") {
		return fatalError(2);
	}

	var limit_set = getCookie("ttrss_bwlimit");

	if (limit_set == "true") {
		document.forms["loginForm"].bw_limit.checked = true;
	}

	document.forms["loginForm"].login.focus();
}

function fetchProfiles() {
	try {
		var params = Form.serialize('loginForm');
		var query = "?op=getProfiles&" + params;

		if (query) {
			new Ajax.Request("backend.php",	{
				parameters: query,
					onComplete: function(transport) {
						if (transport.responseText.match("select")) {
							$('profile_box').innerHTML = transport.responseText;
						}
				} });
		}

	} catch (e) {
		exception_error("fetchProfiles", e);
	}
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

function gotoRegForm() {
	window.location.href = "register.php";
	return false;
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
	Event.observe(window, 'load', function() {
		init();
	});
</script>

<form action="" method="POST" id="loginForm" name="loginForm" onsubmit="return validateLoginForm(this)">
<input type="hidden" name="login_action" value="do_login">

<table class="loginForm2">
<tr>
	<td class="loginTop" valign="bottom" align="left">
		<img src="images/logo_wide.png">
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
				onchange="fetchProfiles()" onfocus="fetchProfiles()"
				value="<?php echo get_remote_user($link) ?>"></td></tr>
			<tr><td align="right"><?php echo __("Password:") ?></td>
			<td align="right"><input type="password" name="password"
				onchange="fetchProfiles()" onfocus="fetchProfiles()"
				value="<?php echo get_remote_fakepass($link) ?>"></td></tr>
			<tr><td align="right"><?php echo __("Language:") ?></td>
			<td align="right">
			<?php
				print_select_hash("language", $_COOKIE["ttrss_lang"], get_translations(),
					"style='width : 100%' onchange='languageChange(this)'");

			?>
			</td></tr>

			<tr><td align="right"><?php echo __("Profile:") ?></td>
			<td align="right" id="profile_box">
			<select style='width : 100%' disabled='disabled'>
				<option><?php echo __("Default profile") ?></option></select>
			</td></tr>

			<!-- <tr><td colspan="2">
				<input type="checkbox" name="remember_me" id="remember_me">
				<label for="remember_me">Remember me on this computer</label>
			</td></tr> -->

			<tr><td colspan="2" align="right" class="innerLoginCell">

			<button type="submit" name='click'><?php echo __('Log in') ?></button>
			<?php if (defined('ENABLE_REGISTRATION') && ENABLE_REGISTRATION) { ?>
				<button onclick="return gotoRegForm()">
					<?php echo __("Create new account") ?></button>
			<?php } ?>

				<input type="hidden" name="action" value="login">
				<input type="hidden" name="rt"
					value="<?php if ($return_to != 'none') { echo $return_to; } ?>">
			</td></tr>

			<tr><td colspan="2" align="right" class="innerLoginCell">

			<div class="small">
			<input name="bw_limit" id="bw_limit" type="checkbox"
				onchange="bwLimitChange(this)">
			<label for="bw_limit">
			<?php echo __("Use less traffic") ?></label></div>

			</td></tr>


		</table>
	</td>
</tr><tr>
	<td align="center" class="loginBottom">
	<a href="http://tt-rss.org/">Tiny Tiny RSS</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?>
	<?php } ?>
	&copy; 2005&ndash;<?php echo date('Y') ?> <a href="http://fakecake.org/">Andrew Dolgov</a>
	</td>
</tr>

</table>

</form>

</body></html>
