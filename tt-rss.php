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
	<title>Tiny Tiny RSS</title>

	<link rel="stylesheet" type="text/css" href="tt-rss.css?<?php echo $dt_add ?>">

	<?php	$user_theme = $_SESSION["theme"];
		if ($user_theme) { ?>
			<link rel="stylesheet" type="text/css" href="themes/<?php echo $user_theme ?>/theme.css?<?php echo $dt_add ?>">
	<?php } ?>

	<?php if ($user_theme) { $theme_image_path = "themes/$user_theme/"; } ?>

	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link rel="stylesheet" type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>

	<!--[if lt IE 7]>		
		<script type="text/javascript" src="pngfix.js"></script>
		<link rel="stylesheet" type="text/css" href="ie6.css">
	<![endif]-->

	<!--[if IE 7]>		
		<link rel="stylesheet" type="text/css" href="ie7.css">
	<![endif]-->

	<link rel="shortcut icon" type="image/png" href="images/favicon.png">

	<script type="text/javascript" src="prototype.js"></script>
	<script type="text/javascript" src="scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
	<script type="text/javascript" src="localized_js.php?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="tt-rss.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="feedlist.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="viewfeed.js?<?php echo $dt_add ?>"></script>

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

<body onresize="resize_headlines()">

<div id="overlay">
	<div id="overlay_inner">
	<p><?php echo __("Loading, please wait...") ?></p>
	<noscript>
		<div class="error"><?php echo
		__("Your browser doesn't support Javascript, which is required
		for this application to function properly. Please check your
		browser settings.") ?></div>
	</noscript>
	</div>
</div> 

<div id="hotkey_help_overlay" style="display : none" onclick="Element.hide(this)">
	<?php include "help/3.php" ?>
</div>

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>

<div id="fatal_error"><div id="fatal_error_inner">
	<h1>Fatal Error</h1>
	<div id="fatal_error_msg">Unknown Error</div>
</div></div>

<div id="dialog_overlay"> </div>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<ul id="debug_output"></ul>

<div id="infoBoxShadow"><div id="infoBox">&nbsp;</div></div>

<div id="header">
	<div class="topLinks">
	<?php if (!SINGLE_USER_MODE) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
	<?php } ?>
	<a href="prefs.php"><?php echo __('Preferences') ?></a>

	<?php if (defined('FEEDBACK_URL')) { ?>
		| <a target="_blank" class="feedback" href="<?php echo FEEDBACK_URL ?>">
				<?php echo __('Comments?') ?></a>
	<?php } ?>

	<?php if (!SINGLE_USER_MODE) { ?>
			| <a href="logout.php"><?php echo __('Logout') ?></a>
	<?php } ?>

	<img id="newVersionIcon" style="display:none;" onclick="javascript:explainError(2)" 
		src="images/new_version.png" title="New version is available!" 
		alt="new_version_icon">
	</div>
	<img src="<?php echo $theme_image_path ?>images/ttrss_logo.png" alt="Tiny Tiny RSS"/>	
</div>

<div id="feeds-holder">
	<div id="dispSwitch"> 
		<a id="dispSwitchPrompt" 
			href="javascript:toggleTags()"><?php echo __("tag cloud") ?></a>
	</div>
	<div id="feeds-frame">&nbsp;</div>
</div>

<div id="toolbar">

		<div style="float : right">
			<select id="quickMenuChooser" onchange="quickMenuChange()">
					<option value="qmcDefault" selected><?php echo __('Actions...') ?></option>
					<option value="qmcSearch"><?php echo __('Search') ?></option>
					<!-- <option value="qmcPrefs"><?php echo __('Preferences') ?></option> -->
					<option disabled>--------</option>
					<option style="color : #5050aa" disabled><?php echo __('Feed actions:') ?></option>
					<option value="qmcAddFeed"><?php echo __('&nbsp;&nbsp;Subscribe to feed') ?></option>
					<option value="qmcEditFeed"><?php echo __('&nbsp;&nbsp;Edit this feed') ?></option>
					<!-- <option value="qmcClearFeed"><?php echo __('&nbsp;&nbsp;Clear articles') ?></option> -->
					<option value="qmcRescoreFeed"><?php echo __('&nbsp;&nbsp;Rescore feed') ?></option>
					<option value="qmcRemoveFeed"><?php echo __('&nbsp;&nbsp;Unsubscribe') ?></option>
					<option disabled>--------</option>
					<option style="color : #5050aa" disabled><?php echo __('All feeds:') ?></option>
					<option value="qmcCatchupAll"><?php echo __('&nbsp;&nbsp;Mark as read') ?></option>
					<option value="qmcShowOnlyUnread"><?php echo __('&nbsp;&nbsp;(Un)hide read feeds') ?></option>
					<option disabled>--------</option>
					<option style="color : #5050aa" disabled><?php echo __('Other actions:') ?></option>				
					<option value="qmcAddFilter"><?php echo __('&nbsp;&nbsp;Create filter') ?></option>
					<option value="qmcHKhelp"><?php echo __('&nbsp;&nbsp;Keyboard shortcuts') ?></option>
			</select>
		</div>

		<form id="main_toolbar_form" onsubmit='return false'>

		<input type="submit" value="&lt;&lt;" 
			id="collapse_feeds_btn" onclick="collapse_feedlist()" class="button"
			title="<?php echo __('Collapse feedlist') ?>" style="display : none">

		<input type="submit" value="<?php echo __("Toggle Feedlist") ?>" 
			id="toggle_feeds_btn" class="button"
			onclick="toggle_feedlist()" style="display : none">

		&nbsp;

		<?php if (get_pref($link, 'ENABLE_SEARCH_TOOLBAR')) { ?>

		<?php echo __('Search:') ?>
		<input name="query" type="search"
			onKeyPress="return filterCR(event, viewCurrentFeed)"
			onblur="javascript:enableHotkeys();" onfocus="javascript:disableHotkeys();">

		<?php } ?>

		<?php echo __('View:') ?>
		<select name="view_mode" onchange="viewModeChanged()">
			<option selected value="adaptive"><?php echo __('Adaptive') ?></option>
			<option value="all_articles"><?php echo __('All Articles') ?></option>
			<option value="marked"><?php echo __('Starred') ?></option>
			<option value="unread"><?php echo __('Unread') ?></option>
		</select>
		
		<?php echo __('Limit:') ?>
		<?php
		$limits = array(15 => 15, 30 => 30, 60 => 60, 0 => "All");
			
		$def_art_limit = get_pref($link, 'DEFAULT_ARTICLE_LIMIT');

		if ($def_art_limit >= 0 && !array_key_exists($def_art_limit, $limits)) {
			$limits[$def_art_limit] = $def_art_limit; 
		}

		asort($limits);

		if (!$def_art_limit) {
			$def_art_limit = 30;
		}

		print_select_hash("limit", $def_art_limit, $limits, 
			'onchange="viewLimitChanged()"');
	
		?>		

		&nbsp;

		<input class="button" type="submit"
			onclick="return viewCurrentFeed('ForceUpdate')" 
			value="<?php echo __('Update') ?>">

		</form>

		<!-- &nbsp;<input class="button" type="submit"
			onclick="quickMenuGo('qmcSearch')" value="Search (tmp)"> -->

		<!-- <input class="button" type="submit"
			onclick="catchupCurrentFeed()" value="Mark as read">  -->

	</div>

<?php if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) { ?>
	<div id="headlines-frame" class="headlines_normal">
		<div class="whiteBox"><?php echo __('No feed selected.') ?></div></div>
	<div id="content-frame"><div class="whiteBox">&nbsp;</div></div>
<?php } else { ?>
	<div id="headlines-frame" class="headlines_cdm">
		<div class="whiteBox"><?php echo __('No feed selected.') ?></div></div>
<?php } ?>

<div id="footer">
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
