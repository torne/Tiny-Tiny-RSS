<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	require_once "functions.php"; 
	require_once "sessions.php";
	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

	$dt_add = get_script_dt_add();

	no_cache_incantation();
	
	header('Content-Type: text/html; charset=utf-8');
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS : Preferences</title>
	<link rel="stylesheet" type="text/css" href="tt-rss.css?<?php echo $dt_add ?>"/>

	<?php	$user_theme = get_user_theme_path($link);
		if ($user_theme) { ?>
		<link rel="stylesheet" type="text/css" href="<?php echo $user_theme ?>/theme.css"/>
	<?php } ?>
	
	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>

	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>

	<script type="text/javascript" charset="utf-8" src="functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="prefs.js?<?php echo $dt_add ?>"></script>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		
	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>

</head>

<body id="ttrssPrefs">

<div id="overlay">
	<div id="overlay_inner">
		<?php echo __("Loading, please wait...") ?>

		<div id="l_progress_o">
			<div id="l_progress_i"></div>
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

<ul id="debug_output" style='display : none'><li>&nbsp;</li></ul>

<div id="prefHeader">
	<div class="topLinks">
		<?php if (!SINGLE_USER_MODE) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
		<?php } ?>
		<a href="#" onclick="gotoMain()"><?php echo __('Exit preferences') ?></a>
		<?php if (!SINGLE_USER_MODE) { ?>
			| <a href="logout.php"><?php echo __('Logout') ?></a>
		<?php } ?>
	</div>
	<img src="<?php echo theme_image($link, 'images/ttrss_logo.png') ?>" alt="Tiny Tiny RSS"/>	
</div>

<div id="prefTabs">
		<div class='prefKbdHelp'>
			<img src="<?php echo theme_image($link, 'images/small_question.png') ?>" alt="?"/> <a href='#' onclick="Effect.Appear('hotkey_help_overlay', {duration: 0.3})"><?php echo __("Keyboard shortcuts") ?></a>
		</div>

		<div class="firstTab">&nbsp;</div>

		<div id="genConfigTab" class="prefsTab" 
			onclick="selectTab('genConfig')"><?php echo __('Preferences') ?></div>
		<div id="feedConfigTab" class="prefsTab" 
			onclick="selectTab('feedConfig')"><?php echo __('Feeds') ?></div>
		<div id="filterConfigTab" class="prefsTab" 
			onclick="selectTab('filterConfig')"><?php echo __('Filters') ?></div>
		<div id="labelConfigTab" class="prefsTab" 
			onclick="selectTab('labelConfig')"><?php echo __('Labels') ?></div>
		<?php if ($_SESSION["access_level"] >= 10) { ?>
		<div id="userConfigTab" class="prefsTab" 
			onclick="selectTab('userConfig')"><?php echo __('Users') ?></div>
		<?php } ?>		
</div>

<div id="prefContentOuter">
<div id="prefContent">
	<p><?php echo __('Loading, please wait...') ?></p>
	<noscript>
		<div class="error">
		<?php echo __("Your browser doesn't support Javascript, which is required
		for this application to function properly. Please check your
		browser settings.") ?></div>
	</noscript>
</div>
</div>

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>
<div id="infoBoxShadow"><div id="infoBox">BAH</div></div>

<div id="cmdline" style="display : none"></div>

<div id="errorBoxShadow" style="display : none">
	<div id="errorBox">
	<div id="xebTitle"><?php echo __('Fatal Exception') ?></div><div id="xebContent">&nbsp;</div>
		<div id="xebBtn" align='center'>
			<button onclick="closeErrorBox()"><?php echo __('Close this window') ?></button>
		</div>
	</div>
</div>

<div id="dialog_overlay" style="display : none"> </div>

<div id="prefFooter">
	<a href="http://tt-rss.org/">Tiny Tiny RSS</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?> 
	<?php } ?>
	&copy; 2005&ndash;2010 <a href="http://bah.org.ru/">Andrew Dolgov</a>
</div>

<?php db_close($link); ?>

</body>
</html>
