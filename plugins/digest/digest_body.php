<?php global $link; ?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html>
<head>
	<title>Tiny Tiny RSS</title>

	<?php echo stylesheet_tag("plugins/digest/digest.css") ?>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<?php print_user_stylesheet($link) ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<?php
	foreach (array("lib/prototype.js",
				"lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls",
				"js/functions.js",
				"plugins/digest/digest.js",
				"errors.php?mode=js") as $jsfile) {

		echo javascript_tag($jsfile);
	} ?>

	<script type="text/javascript">
	<?php init_js_translations(); ?>
	</script>

	<script type="text/javascript" src="plugins/digest/digest.js"></script>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>
</head>
<body id="ttrssDigest">
	<div id="overlay" style="display : block">
		<div id="overlay_inner">
		<noscript>
			<p>
			<?php print_error(__("Your browser doesn't support Javascript, which is required for this application to function properly. Please check your browser settings.")) ?></p>
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
