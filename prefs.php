<?php
	require_once "functions.php"; 
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	init_connection($link);

	login_sequence($link);

	$dt_add = time();

	no_cache_incantation();
	
	header('Content-Type: text/html; charset=utf-8');
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS : Preferences</title>
	<link rel="stylesheet" type="text/css" href="tt-rss.css?<?php echo $dt_add ?>"/>
	<link rel="stylesheet" type="text/css" href="lib/dijit/themes/claro/claro.css"/>

	<?php print_theme_includes($link) ?>
	
	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/position.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
	<script type="text/javascript" src="lib/dojo/dojo.js" djConfig="parseOnLoad: true"></script>

	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>

	<script type="text/javascript" charset="utf-8" src="functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="deprecated.js?<?php echo $dt_add ?>"></script>

	<script type="text/javascript" charset="utf-8" src="prefs.js?<?php echo $dt_add ?>"></script>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		
	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>

</head>

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>
<div id="cmdline" style="display : none"></div>

<body id="ttrssPrefs" class="claro">

<div id="overlay">
	<div id="overlay_inner">
		<div class="insensitive"><?php echo __("Loading, please wait...") ?></div>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
	<noscript>
		<p><?php print_error(__("Your browser doesn't support Javascript, which is required
		for this application to function properly. Please check your
		browser settings.")) ?></p>
	</noscript>
	</div>
</div> 

<div id="hotkey_help_overlay" style="display : none" onclick="Element.hide(this)">
	<?php rounded_table_start("hho"); ?>
	<?php include "help/4.php" ?>
	<?php rounded_table_end(); ?>
</div>

<img id="piggie" src="images/piggie.png" style="display : none" alt="piggie"/>

<div id="main" dojoType="dijit.layout.BorderContainer">

<div id="header" dojoType="dijit.layout.ContentPane" region="top">
	<div class="topLinks">
		<?php if (!SINGLE_USER_MODE) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
		<?php } ?>
		<a href="#" onclick="gotoMain()"><?php echo __('Exit preferences') ?></a> | 
		<a href='#' onclick="Effect.Appear('hotkey_help_overlay', {duration: 0.3})"><?php echo __("Keyboard shortcuts") ?></a>
		<?php if (!SINGLE_USER_MODE) { ?>
			| <a href="logout.php"><?php echo __('Logout') ?></a>
		<?php } ?>

	</div>
	<img src="<?php echo theme_image($link, 'images/ttrss_logo.png') ?>" alt="Tiny Tiny RSS"/>	
</div>

<div dojoType="dijit.layout.TabContainer" region="center" id="pref-tabs">
<div id="genConfigTab" dojoType="dijit.layout.ContentPane" 
	href="backend.php?op=pref-prefs"
	title="<?php echo __('Preferences') ?>"></div>
<div id="feedConfigTab" dojoType="dijit.layout.ContentPane" 
	href="backend.php?op=pref-feeds"
	title="<?php echo __('Feeds') ?>"></div>
<div id="filterConfigTab" dojoType="dijit.layout.ContentPane" 
	href="backend.php?op=pref-filters"
	title="<?php echo __('Filters') ?>"></div>
<div id="labelConfigTab" dojoType="dijit.layout.ContentPane" 
	href="backend.php?op=pref-labels"
	title="<?php echo __('Labels') ?>"></div>
<?php if ($_SESSION["access_level"] >= 10) { ?>
	<div id="userConfigTab" dojoType="dijit.layout.ContentPane" 
		href="backend.php?op=pref-users"
		title="<?php echo __('Users') ?>"></div>
<?php } ?>
</div>

<div id="footer" dojoType="dijit.layout.ContentPane" region="bottom">
	<a href="http://tt-rss.org/">Tiny Tiny RSS</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?> 
	<?php } ?>
	&copy; 2005&ndash;<?php echo date('Y') ?> <a href="http://fakecake.org/">Andrew Dolgov</a>
</div> <!-- footer -->

</div>

<?php db_close($link); ?>

</body>
</html>
