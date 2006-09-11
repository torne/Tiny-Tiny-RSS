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

	<?php if (get_pref($link, 'USE_COMPACT_STYLESHEET')) { ?>

		<link rel="stylesheet" href="tt-rss_compact.css" type="text/css">

	<?php } else { ?>

		<link title="Compact Stylesheet" rel="alternate stylesheet" 
			type="text/css" href="tt-rss_compact.css"/> 

	<?php } ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png">

	<script type="text/javascript" src="prototype.js"></script>

	<script type="text/javascript" src="functions.js?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" src="prefs.js?<?php echo $dt_add ?>"></script>

	<div id="infoBoxShadow"><div id="infoBox">BAH</div></div>

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

<div id="piggie">&nbsp;</div>

<iframe id="backReqBox"></iframe>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<ul id="debug_output"></ul>

<div id="fatal_error"><div id="fatal_error_inner">
	<h1>Fatal Error</h1>
	<div id="fatal_error_msg">Unknown Error</div>
</div></div>

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<?php if (get_pref($link, 'DISPLAY_HEADER')) { ?>
<tr>
	<td colspan="2">
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
	<td class="small">
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
	<td class="prefsTabs" align="left" valign="bottom">
		<input id="genConfigTab" class="prefsTab" type="submit" value="Preferences"
			onclick="selectTab('genConfig')">
		<input id="feedConfigTab" class="prefsTab" type="submit" value="My Feeds"
			onclick="selectTab('feedConfig')">
		<?php if (ENABLE_FEED_BROWSER && !SINGLE_USER_MODE) { ?>
		<input id="feedBrowserTab" class="prefsTab" type="submit" value="Other Feeds"
			onclick="selectTab('feedBrowser')">
		<?php } ?>
		<input id="filterConfigTab" class="prefsTab" type="submit" value="Content Filtering"
			onclick="selectTab('filterConfig')">
		<?php if (get_pref($link, 'ENABLE_LABELS')) { ?>
		<input id="labelConfigTab" class="prefsTab" type="submit" value="Label Editor"
			onclick="selectTab('labelConfig')">
		<?php } ?>
		<?php if ($_SESSION["access_level"] >= 10) { ?>
		<input id="userConfigTab" class="prefsTab" type="submit" value="User Manager"
			onclick="selectTab('userConfig')">
		<?php } ?>		
	</td>
	<td class="prefsToolbar" valign="middle" align="right">	
		<input type="submit" onclick="gotoMain()" class="button" value="Return to main">
	</td>
	</tr>
</tr>
	<td id="prefContent" class="prefContent" valign="top" colspan="2">

		<p>Loading, please wait...</p>

	</td>
</tr>
<?php if (get_pref($link, 'DISPLAY_FOOTER')) { ?>
<tr>
	<td class="footer" colspan="2">
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
