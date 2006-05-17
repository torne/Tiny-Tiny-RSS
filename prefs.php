<?
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

	<?	$user_theme = $_SESSION["theme"];
		if ($user_theme) { ?>
		<link rel="stylesheet" type="text/css" href="themes/<?= $user_theme ?>/theme.css">
	<? } ?>

	<? $user_css_url = get_pref($link, 'USER_STYLESHEET_URL'); ?>
	<? if ($user_css_url) { ?>
		<link type="text/css" href="<?= $user_css_url ?>"/> 
	<? } ?>

	<? if (get_pref($link, 'USE_COMPACT_STYLESHEET')) { ?>

		<link rel="stylesheet" href="tt-rss_compact.css" type="text/css">

	<? } else { ?>

		<link title="Compact Stylesheet" rel="alternate stylesheet" 
			type="text/css" href="tt-rss_compact.css"/> 

	<? } ?>

	
	<script type="text/javascript" src="functions.js?<?= $dt_add ?>"></script>
	<script type="text/javascript" src="prefs.js?<?= $dt_add ?>"></script>

	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

	<script type="text/javascript">
		if (navigator.userAgent.match("Opera")) {
			document.write('<link rel="stylesheet" type="text/css" href="opera.css">');
		}
	</script>
</head>

<body>

<div id="piggie" class="invisible">&nbsp;</div>

<script type="text/javascript">
if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", init, null);
}
window.onload = init;
</script>

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<? if (get_pref($link, 'DISPLAY_HEADER')) { ?>
<tr>
	<td colspan="2">
		<table cellspacing="0" cellpadding="0" width="100%"><tr>
			<td rowspan="2" class="header" valign="middle">	
				<img src="images/ttrss_logo.png" alt="logo">	
			</td>
			<td valign="top" class="notifyBox">
				<div id="notify"><span id="notify_body">&nbsp;</span></div>
			</td>
		</tr><tr><td class="welcomePrompt">
			<? if (!SINGLE_USER_MODE) { ?>
				Hello, <b><?= $_SESSION["name"] ?></b>
				(<a href="logout.php">Logout</a>)
			<? } ?>
			</td>
		</tr></table>
	</td>
</tr>
<? } else { ?>
<tr>
	<td class="small">
		<div id="notify"><span id="notify_body">&nbsp;</span></div>
		<div id="userDlgShadow"><div id="userDlg">&nbsp;</div></div>
	</td><td class="welcomePrompt">
		<? if (!SINGLE_USER_MODE) { ?>
			Hello, <b><?= $_SESSION["name"] ?></b>
			(<a href="logout.php">Logout</a>)
		<? } ?>
</td></tr>
<? } ?>
<tr>
	<td class="prefsTabs" align="left" valign="bottom">
		<input id="genConfigTab" class="prefsTab" type="submit" value="Preferences"
			onclick="selectTab('genConfig')">
		<input id="feedConfigTab" class="prefsTab" type="submit" value="My Feeds"
			onclick="selectTab('feedConfig')">
		<? if (ENABLE_FEED_BROWSER && !SINGLE_USER_MODE) { ?>
		<input id="feedBrowserTab" class="prefsTab" type="submit" value="Other Feeds"
			onclick="selectTab('feedBrowser')">
		<? } ?>
		<input id="filterConfigTab" class="prefsTab" type="submit" value="Content Filtering"
			onclick="selectTab('filterConfig')">
		<? if (GLOBAL_ENABLE_LABELS && get_pref($link, 'ENABLE_LABELS')) { ?>
		<input id="labelConfigTab" class="prefsTab" type="submit" value="Label Editor"
			onclick="selectTab('labelConfig')">
		<? } ?>
		<? if ($_SESSION["access_level"] >= 10) { ?>
		<input id="userConfigTab" class="prefsTab" type="submit" value="User Manager"
			onclick="selectTab('userConfig')">
		<? } ?>		
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
<? if (get_pref($link, 'DISPLAY_FOOTER')) { ?>
<tr>
	<td class="prefFooter" colspan="2">
		<a href="http://tt-rss.spb.ru/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005-2006 Andrew Dolgov
		<? if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<? } ?>
	</td>
</td>
<? } ?>
</table>

<? db_close($link); ?>

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
