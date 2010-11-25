<?php
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

	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link rel="stylesheet" type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
	<script type="text/javascript" src="lib/dojo/dojo.js" djConfig="parseOnLoad: true"></script>
	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="functions.js?<?php echo $dt_add ?>"></script>

	<script type="text/javascript" src="digest.js"></script>

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

	<div class="links">

	<?php if (!SINGLE_USER_MODE) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
	<?php } ?>

	<?php if (!SINGLE_USER_MODE) { ?>
			<a href="logout.php"><?php echo __('Logout') ?></a>
	<?php } ?>

	</div>

	Tiny Tiny RSS

	</div>
	<div id="content">
		<!-- <div id="title">
			<div id="search">
				<input name="search" type="search"></input>
				<button>Search</button>
			</div>

		</div>

		<div id="latest">
			<h1>latest articles</h1>

			<em>TODO</em>

			<div id="latest-content"> </div>
		</div> -->

		<div id="feeds">
			<h1><?php echo __('feeds') ?></h1>

			<ul id="feeds-content"> </ul>
		</div>

		<div id="headlines">
			<h1><a href="#" onclick="viewfeed(-4)"><?php echo __('headlines') ?></a>: 
				<span id="headlines-title"></span></h1>

			<ul id="headlines-content"> </ul>
		</div>

		<br clear="both">

	</div>

	<div id="footer">

	<a href="http://tt-rss.org/">Tiny Tiny RSS</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?> 
	<?php } ?>
	&copy; 2005&ndash;<?php echo date('Y') ?> 
	<a href="http://fakecake.org/">Andrew Dolgov</a>
	
	<br/>

	<a href="tt-rss.php">
		<?php echo __("You are viewing the digest page. Click to open full version.") ?></a>

</div>

</body>
