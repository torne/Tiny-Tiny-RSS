<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . "/include");

	require_once "functions.php";
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	login_sequence($link);

	$dt_add = time();

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html>
<head>
	<title>Tiny Tiny RSS</title>

	<link rel="stylesheet" type="text/css" href="lib/dijit/themes/claro/claro.css"/>
	<link rel="stylesheet" type="text/css" href="digest.css?<?php echo $dt_add ?>"/>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<?php print_user_stylesheet($link) ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<script type="text/javascript" src="lib/dojo/dojo.js" djConfig="parseOnLoad: true"></script>
	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>

	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="errors.php?mode=js"></script>
	<script type="text/javascript" charset="utf-8" src="js/functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="js/digest.js"></script>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>
</head>
<body id="ttrssDigest" class="claro">
	<div id="overlay" style="display : block">
		<div id="overlay_inner">
		<noscript>
			<p>
			<?php print_error(__("Your browser doesn't support Javascript, which is required
			for this application to function properly. Please check your
			browser settings.")) ?></p>
		</noscript>

		<img src="images/indicator_white.gif"/>
			<?php echo __("Loading, please wait...") ?>
		</div>
	</div>

	<div id="header">
	<a style="float : left" href="#" onclick="close_article()">
		<?php echo __("Back to feeds") ?></a>

	<div class="links">

	<?php if (!$_SESSION["hide_hello"]) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
	<?php } ?>
	<?php if (!$_SESSION["hide_logout"]) { ?>
			<a href="backend.php?op=logout"><?php echo __('Logout') ?></a> |
	<?php } ?>
			<a href='<?php echo get_self_url_prefix() ?>/index.php?mobile=false'>
			<?php echo __("Regular version") ?></a>

	</div>
	</div>

	<div id="article"><div id="article-content">&nbsp;</div></div>

	<div id="content">

		<div id="feeds">
			<ul id="feeds-content"> </ul>
		</div>

		<div id="headlines">
			<ul id="headlines-content"> </ul>
		</div>
	</div>

</body>
</html>
