<html>
<head>
	<title>Tiny Tiny RSS : Login</title>
	<link rel="stylesheet" type="text/css" href="mobile.css">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body>

	<div id="content">
	<div id="heading">Tiny Tiny RSS</div>

	<form action="tt-rss.php" method="POST">
	<input type="hidden" name="rt" value="<?php echo $_GET['rt'] ?>">
	<input type="hidden" name="login_action" value="do_login">

	<?php if ($_SESSION['login_error_msg']) { ?>
		<div class="loginError"><?php echo $_SESSION['login_error_msg'] ?></div>
		<?php $_SESSION['login_error_msg'] = ""; ?>
	<?php } ?>

	<table>
		<tr><td align='right'><?php echo __("Login:") ?></td><td><input name="login"></td>
		<tr><td align='right'><?php echo __("Password:") ?></td><td><input type="password" name="password"></tr>

		<tr><td colspan='2'>
			<input type="submit" class="button" value="Login">
		</td></tr>
		</table>
	</form>
	</div>

</body>
</html>

