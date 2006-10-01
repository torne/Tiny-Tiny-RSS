<?php
	require_once "functions.php"; 

	basic_nosid_redirect_check();

	require_once "sessions.php";

	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

	$dt_add = get_script_dt_add();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" 
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS</title>

	<link rel="stylesheet" type="text/css" href="tt-rss.css">

	<?php	$user_theme = $_SESSION["theme"];
		if ($user_theme) { ?>
		<link rel="stylesheet" type="text/css" href="themes/<?php echo $user_theme ?>/theme.css">
	<?php } ?>

	<?php if ($user_theme) { $theme_image_path = "themes/$user_theme/"; } ?>

	<?php $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<?php if ($user_css_url) { ?>
		<link type="text/css" href="<?php echo $user_css_url ?>"/> 
	<?php } ?>

	<?php if (get_pref($link, 'USE_COMPACT_STYLESHEET')) { ?>

		<link rel="stylesheet" href="tt-rss_compact.css" type="text/css">

	<?php } else { ?>

		<link title="Compact Stylesheet" rel="alternate stylesheet" 
			type="text/css" href="tt-rss_compact.css"/> 

	<?php } ?>

	<!--[if gte IE 5.5000]>		
		<script type="text/javascript" src="pngfix.js"></script>
		<link rel="stylesheet" type="text/css" href="tt-rss-ie.css">
	<![endif]-->

	<link rel="shortcut icon" type="image/png" href="images/favicon.png">

	<script type="text/javascript" src="prototype.js"></script>

	<script type="text/javascript" src="tt-rss.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="feedlist.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="viewfeed.js?<?php echo $dt_add ?>"></script>

	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
		<link rel="stylesheet" type="text/css" href="tt-rss-ie.css">
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

<iframe id="backReqBox"></iframe>

<div id="overlay"><div id="overlay_inner">Loading, please wait...</div></div> 

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>

<div id="fatal_error"><div id="fatal_error_inner">
	<h1>Fatal Error</h1>
	<div id="fatal_error_msg">Unknown Error</div>
</div></div>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<div id="noDaemonWarning">
	<b>Warning:</b> Update daemon is enabled in configuration, but daemon
	process is not running, which prevents all feeds from updating. Please
	start the daemon process or contact instance owner.
</div>

<ul id="debug_output"></ul>

<div id="infoBoxShadow"><div id="infoBox">&nbsp;</div></div>

<div id="header">
	<?php if (!SINGLE_USER_MODE) { ?>
		<div style="float : right">
			Hello, <b><?php echo $_SESSION["name"] ?></b>
			(<a href="logout.php">Logout</a>)
		</div>
	<?php } ?>
	<img src="<?php echo $theme_image_path ?>images/ttrss_logo.png" alt="Tiny Tiny RSS"/>	
</div>

<div id="feeds-holder">
	<div id="dispSwitch"> 
		<a id="dispSwitchPrompt" href="javascript:toggleTags()">display tags</a>
	</div>
	<div id="feeds-frame">&nbsp;</div>
</div>

<div id="toolbar">

		<div style="float : right">
			<select id="quickMenuChooser" onchange="quickMenuChange()">
					<option value="qmcDefault" selected>Actions...</option>
					<option value="qmcPrefs">Preferences</option>
					<option value="qmcSearch">Search</option>
					<option disabled>--------</option>
					<option style="color : #5050aa" disabled>Feed actions:</option>
					<option value="qmcAddFeed">&nbsp;&nbsp;Subscribe to feed</option>
					<option value="qmcRemoveFeed">&nbsp;&nbsp;Unsubscribe</option>
					<!-- <option>Edit this feed</option> -->
					<option disabled>--------</option>
					<option style="color : #5050aa" disabled>All feeds:</option>
					<?php if (!ENABLE_UPDATE_DAEMON && !DAEMON_REFRESH_ONLY) { ?>
					<option value="qmcUpdateFeeds">&nbsp;&nbsp;Update</option>
					<?php } ?>
					<option value="qmcCatchupAll">&nbsp;&nbsp;Mark as read</option>				
					<option value="qmcShowOnlyUnread">&nbsp;&nbsp;Show only unread</option>
					<option disabled>--------</option>
					<option style="color : #5050aa" disabled>Other actions:</option>				
					<option value="qmcAddFilter">&nbsp;&nbsp;Create filter</option>
			</select>
		</div>

		<form id="main_toolbar_form">

		<?php if (get_pref($link, 'ENABLE_SEARCH_TOOLBAR')) { ?>
		<input name="query"
			onKeyPress="return filterCR(event, viewCurrentFeed)"
			onblur="javascript:enableHotkeys();" onfocus="javascript:disableHotkeys();">
		<input class="button" type="submit"
			onclick="return viewCurrentFeed(0)" value="Search">
		&nbsp; 
		<?php } ?>

		View: 		
		<select name="view_mode" onchange="viewCurrentFeed(0, '')">
			<option selected value="adaptive">Adaptive</option>
			<option value="all_articles">All Articles</option>
			<option value="marked">Starred</option>
			<option value="unread">Unread</option>
		</select>
		
		&nbsp;Limit:		
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
			'onchange="viewCurrentFeed(0, \'\')"');
	
		?>		
		</form>

		<!-- &nbsp;<input class="button" type="submit"
			onclick="quickMenuGo('qmcSearch')" value="Search (tmp)"> -->

		&nbsp;<input class="button" type="submit"
			onclick="viewCurrentFeed('ForceUpdate')" value="Update">

		<!-- <input class="button" type="submit"
			onclick="catchupCurrentFeed()" value="Mark as read">  -->

	</div>

<?php if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) { ?>
	<div id="headlines-frame" class="headlines_normal"><div class="whiteBox">No feed selected.</div></div>
	<div id="content-frame"><div class="whiteBox">&nbsp;</div></div>
<?php } else { ?>
	<div id="headlines-frame" class="headlines_cdm"><div class="whiteBox">No feed selected.</div></div>
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
	<? } ?>
	<a href="http://tt-rss.spb.ru/">Tiny Tiny RSS</a> v<?php echo VERSION ?> &copy; 2005-2006 Andrew Dolgov
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
