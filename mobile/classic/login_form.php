<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<script type="text/javascript" charset="utf-8" src="mobile.js"></script>
</head>

<script type="text/javascript">
function init() {

	if (arguments.callee.done) return;
	arguments.callee.done = true;		

	var login = document.forms["loginForm"].login;
	var click = document.forms["loginForm"].click;

	login.focus();
	click.disabled = false;

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

</script>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>


<body>

	<div id="content">
	<div id="heading">Tiny Tiny RSS</div>

	<form action="index.php" method="POST" name="loginForm">
	<input type="hidden" name="rt" value="<?php echo $_GET['rt'] ?>">
	<input type="hidden" name="login_action" value="do_login">

	<?php if ($_SESSION['login_error_msg']) { ?>
		<div class="loginError"><?php echo $_SESSION['login_error_msg'] ?></div>
		<?php $_SESSION['login_error_msg'] = ""; ?>
	<?php } ?>

	<table>
		<tr><td align='right'><?php echo __("Login:") ?></td><td><input type="text" name="login"></td>
		<tr><td align='right'><?php echo __("Password:") ?></td><td><input type="password" name="password"></tr>

		<tr><td align="right"><?php echo __("Language:") ?></td>
		<td>
			<?php
				print_select_hash("language", $_COOKIE["ttrss_lang"], get_translations(),
					"style='width : 100%' onchange='languageChange(this)'");

			?>
		</td></tr>
		<tr><td colspan='2'>
		<input type="submit" class="button" value="<?php echo __('Log in') ?>" name="click">
		</td></tr>
		</table>
	</form>
	</div>

</body>
</html>

