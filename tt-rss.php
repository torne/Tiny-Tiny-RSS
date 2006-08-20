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

	<script type="text/javascript" src="prototype.js"></script>

	<script type="text/javascript" src="tt-rss.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="functions.js?<?php echo $dt_add ?>"></script>
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

<div id="overlay"><div id="overlay_inner">Loading, please wait...</div></div>
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

<iframe id="backReqBox"></iframe>

<ul id="debug_output"></ul>

<div id="infoBoxShadow"><div id="infoBox">&nbsp;</div></div>

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<?php if (get_pref($link, 'DISPLAY_HEADER')) { ?>
<tr>
	<td colspan="2" class="headerBox" id="mainHeader">
		<table cellspacing="0" cellpadding="0" width="100%"><tr>
			<td rowspan="2" class="header" valign="middle">	
				<img src="<?php echo $theme_image_path ?>images/ttrss_logo.png" alt="Tiny Tiny RSS">	
			</td>
			<td valign="top" class="notifyBox">
				<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>
			</td>
		</tr><tr><td class="welcomePrompt">
			<?php if (!SINGLE_USER_MODE) { ?>
				Hello, <b><?php echo $_SESSION["name"] ?></b>
				(<a href="logout.php">Logout</a>)
			<?php } ?>
			</td>			
		</tr></table>
	</td>
</tr>
<?php } else { ?>
<tr>
	<td class="small" id="mainHeader">
		<div id="notify" class="notify_sm"><span id="notify_body">&nbsp;</span></div>
		<div id="userDlgShadow"><div id="userDlg">&nbsp;</div></div>
	</td><td class="welcomePrompt">
		<?php if (!SINGLE_USER_MODE) { ?>
			Hello, <b><?php echo $_SESSION["name"] ?></b>
			(<a href="logout.php">Logout</a>)
		<?php } ?>
</td></tr>
<?php } ?>
<tr>
	<?php if (get_pref($link, 'COMBINED_DISPLAY_MODE')) 
			$feeds_rowspan = 2;
		else 
			$feeds_rowspan = 3; ?>
	<td valign="top" rowspan="<?php echo $feeds_rowspan ?>" class="feeds"> 
		<table class="innerFeedTable" 
			cellspacing="0" cellpadding="0" height="100%" width="100%">
		<tr><td>
			<div id="dispSwitch"> 
			<a id="dispSwitchPrompt" href="javascript:toggleTags()">display tags</a>
		</div>
		</td></tr>	
		<tr><td height="100%" width="100%" valign="top">

		<iframe frameborder="0" 
			id="feeds-frame" name="feeds-frame" class="feedsFrame"></iframe>

		</td></tr></table>

	</td>
	<td valign="top" class="headlinesToolbarBox">
		<table width="100%" cellpadding="0" cellspacing="0">

		<tr><td class="headlinesToolbar" id="headlinesToolbar">

		<form id="main_toolbar_form">

		<?php if (get_pref($link, 'ENABLE_SEARCH_TOOLBAR')) { ?>
		<input name="query"
			onKeyPress="return filterCR(event)"
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
			onclick="viewCurrentFeed(0, 'ForceUpdate')" value="Update">

		<input class="button" type="submit"
			onclick="catchupCurrentFeed()" value="Mark as read"> 

		</td>
		<td align="right">
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
		</td>
		</tr>
		</table>
	</td>
</tr>
<?php if (get_pref($link, 'COMBINED_DISPLAY_MODE')) { ?>
<tr>
	<td id="headlines" class="headlines2" valign="top">
		<iframe frameborder="0" name="headlines-frame" 
			id="headlines-frame" class="headlinesFrame"></iframe>
	</td>
</tr>
<?php } else { ?>
<tr>
	<td id="headlines" class="headlines" valign="top">
		<iframe frameborder="0" name="headlines-frame" 
			id="headlines-frame" class="headlinesFrame"></iframe>
	</td>
</tr><tr>
	<td class="content" id="content" valign="top">
		<iframe frameborder="0" name="content-frame" 
			id="content-frame" class="contentFrame"> </iframe>
	</td>
</tr>
<?php } ?>
<?php if (get_pref($link, 'DISPLAY_FOOTER')) { ?>
<tr>
	<td colspan="2" class="footer" id="mainFooter">
		<a href="http://tt-rss.spb.ru/">Tiny Tiny RSS</a> v<?php echo VERSION ?> &copy; 2005-2006 Andrew Dolgov
		<?php if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<?php } ?>
	</td>
</td>
<?php } ?>
</table>

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
