<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
         "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Tiny Tiny RSS</title>
<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;"/>
<link rel="apple-touch-icon" href="../lib/iui/iui-logo-touch-icon.png" />
<meta name="apple-touch-fullscreen" content="YES" />
<style type="text/css" media="screen">@import "../lib/iui/iui.css";</style>
<script type="application/x-javascript" src="../lib/iui/iui.js"></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>


<script type="text/javascript">
function do_login() {
	var f = document.forms['login'];
	f.submit();
}
</script>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>


<body>

    <div class="toolbar">
        <h1 id="pageTitle"></h1>
		  <a id="backButton" class="button" href="#"></a>
        <a class="button blueButton" onclick='do_login()'><?php echo __('Log in') ?></a>
    </div>

	<form target="_self" title="Login" action="index.php" id="login" class="panel" method="POST" name="login" selected="true">

	<fieldset>

		<input type="hidden" name="login_action" value="do_login">

		<div class="row">
			<label><?php echo __("Login:") ?></label>
			<input type="text" name="login">
		</div>

		<div class="row">
		<label><?php echo __("Password:") ?></label>
		<input type="password" name="password">
		</div>

		</fieldset>
	
	</form>

</body>
</html>

