<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
         "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Tiny Tiny RSS</title>
<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;"/>
<link rel="apple-touch-icon" href="iui/iui-logo-touch-icon.png" />
<meta name="apple-touch-fullscreen" content="YES" />
<style type="text/css" media="screen">@import "iui/iui.css";</style>
<script type="application/x-javascript" src="iui/iui.js"></script>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>


<script type="text/javascript">
function do_login() {
	var f = document.forms['login'];
	f.submit();
}
</script>

<body>

    <div class="toolbar">
        <h1 id="pageTitle"></h1>
		  <a id="backButton" class="button" href="#"></a>
        <a class="button blueButton" onclick='do_login()'><?php echo __('Log in') ?></a>
    </div>

	<form target="_self" title="Login" id="login" class="panel" name="login" selected="true"
		action="../../public.php?return=<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]) ?>"
		method="post">

	<input type="hidden" name="op" value="login">

	<fieldset>

		<div class="row">
			<label><?php echo __("Login:") ?></label>
			<input type="text" autocapitalize="off" name="login">
		</div>

		<div class="row">
		<label><?php echo __("Password:") ?></label>
		<input type="password" name="password">
		</div>

		</fieldset>

		<div align='center'><a target='_self' href='<?php echo get_self_url_prefix() ?>/index.php?mobile=false'>
			<?php echo __("Open regular version") ?></a>

	</form>

</body>
</html>

