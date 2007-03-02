<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="tt-rss.css">
	<link rel="shortcut icon" type="image/png" href="images/favicon.png">
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

<form action="" method="POST" name="loginForm">
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
				<input type="hidden" name="rt" 
					value="<?php if ($return_to != 'none') { echo $return_to; } ?>">
			</td></tr>
		</table>
	</td>
</tr><tr>
	<td align="center" class="loginBottom">
		<a href="http://tt-rss.spb.ru/">Tiny Tiny RSS</a> &copy; 2005-2007 <a href="http://bah.org.ru/">Andrew Dolgov</a>
	</td>
</tr>

</table>

</form>

