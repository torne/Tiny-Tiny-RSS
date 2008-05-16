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

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS : Preferences</title>
	<link rel="stylesheet" href="tt-rss.css" type="text/css">

	<?php	$user_theme = $_SESSION["theme"];
		if ($user_theme) { ?>
		<link rel="stylesheet" type="text/css" href="themes/<?php echo $user_theme ?>/theme.css">
	<?php } ?>

	<?php if ($user_theme) { $theme_image_path = "themes/$user_theme/"; } ?>
	
	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png">

	<script type="text/javascript" src="prototype.js"></script>
	<script type="text/javascript" src="scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>

	<script type="text/javascript" src="localized_js.php?<?php echo $dt_add ?>"></script>

	<script type="text/javascript" src="functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="prefs.js?<?php echo $dt_add ?>"></script>

	<!--[if lt IE 7]>
		<script type="text/javascript" src="pngfix.js"></script>
		<link rel="stylesheet" type="text/css" href="ie6.css">
	<![endif]-->

	<!--[if IE 7]>		
		<link rel="stylesheet" type="text/css" href="ie7.css">
	<![endif]-->

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

	<script type="text/javascript">
		if (navigator.userAgent.match("Opera")) {
			document.write('<link rel="stylesheet" type="text/css" href="opera.css">');
		}
		if (navigator.userAgent.match("Gecko") && !navigator.userAgent.match("KHTML")) {
			document.write('<link rel="stylesheet" type="text/css" href="gecko.css">');
		}
	</script>
</head>

<body>

<div id="hotkey_help_overlay" style="display : none" onclick="Element.hide(this)">
	<?php rounded_table_start("hho"); ?>
	<?php include "help/4.php" ?>
	<?php rounded_table_end(); ?>
</div>

<img id="piggie" src="images/piggie.png" style="display : none" alt="piggie">

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<ul id="debug_output"></ul>

<div id="fatal_error"><div id="fatal_error_inner">
	<h1>Fatal Error</h1>
	<div id="fatal_error_msg"><?php echo __('Unknown Error') ?></div>
</div></div>

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
	<img src="<?php echo $theme_image_path ?>images/ttrss_logo.png" alt="Tiny Tiny RSS"/>	
</div>

<div id="prefTabs">
		<!-- <div class="return">
			<a href="#" onclick="gotoMain()"><?php echo __('Exit preferences') ?></a>
		</div> -->

		<div class="firstTab">&nbsp;</div>

		<div id="genConfigTab" class="prefsTab" 
			onclick="selectTab('genConfig')"><?php echo __('Preferences') ?></div>
		<div id="feedConfigTab" class="prefsTab" 
			onclick="selectTab('feedConfig')"><?php echo __('My Feeds') ?></div>
		<?php if (ENABLE_FEED_BROWSER && !SINGLE_USER_MODE) { ?>
		<div id="feedBrowserTab" class="prefsTab" 
			onclick="selectTab('feedBrowser')"><?php echo __('Other Feeds') ?></div>
		<?php } ?>
		<!-- <div id="pubItemsTab" class="prefsTab" 
			onclick="selectTab('pubItems')"><?php echo __('Published Articles') ?></div> -->
		<div id="filterConfigTab" class="prefsTab" 
			onclick="selectTab('filterConfig')"><?php echo __('Content Filtering') ?></div>
		<?php if (get_pref($link, 'ENABLE_LABELS')) { ?>
		<div id="labelConfigTab" class="prefsTab" 
			onclick="selectTab('labelConfig')"><?php echo __('Label Editor') ?></div>
		<?php } ?>
		<?php if ($_SESSION["access_level"] >= 10) { ?>
		<div id="userConfigTab" class="prefsTab" 
			onclick="selectTab('userConfig')"><?php echo __('User Manager') ?></div>
		<?php } ?>		
</div>

<div id="prefContent">
	<p><?php echo __('Loading, please wait...') ?></p>
	<noscript>
		<div class="error">
		<?php echo __("Your browser doesn't support Javascript, which is required
		for this application to function properly. Please check your
		browser settings.") ?></div>
	</noscript>
</div>

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>
<div id="infoBoxShadow"><div id="infoBox">BAH</div></div>

<div id="dialog_overlay"> </div>

<div id="prefFooter">
	<?php if (defined('_DEBUG_USER_SWITCH')) { ?>
		<select id="userSwitch" onchange="userSwitch()">
		<?php 
			foreach (array('admin', 'fox', 'test') as $u) {
				$op_sel = ($u == $_SESSION["name"]) ? "selected" : "";
				print "<option $op_sel>$u</option>";
			}
		?>
		</select>
	<?php } ?>
	<a href="http://tt-rss.org/">Tiny Tiny RSS</a> v<?php echo VERSION ?> &copy; 2005&ndash;2008 <a href="http://bah.org.ru/">Andrew Dolgov</a>
</div>

<?php db_close($link); ?>

<script type="text/javascript">
	/* for IE */
	function statechange() {
		if (document.readyState == "interactive") init();
	}

	if (document.readyState) {	
		if (document.readyState == "interactive" || document.readyState == "complete") {
			init();
		} else {
			document.onreadystatechange = statechange;
		}
	}
</script>

</body>
</html>
