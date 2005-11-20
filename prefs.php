<?
	session_start();

	require_once "version.php"; 
	require_once "config.php";
	require_once "db-prefs.php";
	require_once "functions.php"; 

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	if (!SINGLE_USER_MODE) {

		if (!USE_HTTP_AUTH) {
			if (!$_SESSION["uid"]) {
				header("Location: login.php?rt=tt-rss.php");
				exit;
			}
		} else {
			$force_logout = $_POST["ForceLogout"];
			http_authenticate_user($link, $force_logout == "yes");
		}
	} else {
		$_SESSION["uid"] = 1;
		$_SESSION["name"] = "admin";
	}

?>
<html>
<head>
	<title>Tiny Tiny RSS : Preferences</title>
	<link rel="stylesheet" href="tt-rss.css" type="text/css">

	<? if (get_pref($link, 'USE_COMPACT_STYLESHEET')) { ?>

		<link rel="stylesheet" href="tt-rss_compact.css" type="text/css">

	<? } else { ?>

		<link title="Compact Stylesheet" rel="alternate stylesheet" 
			type="text/css" href="tt-rss_compact.css"/> 

	<? } ?>

	
	<script type="text/javascript" src="functions.js"></script>
	<script type="text/javascript" src="prefs.js"></script>

	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body onload="init()">

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<? if (get_pref($link, 'DISPLAY_HEADER')) { ?>
<tr>
	<td colspan="2">
		<table cellspacing="0" cellpadding="0" width="100%"><tr>
			<td rowspan="2" class="header" valign="middle">	
				<img src="images/ttrss_logo.png" alt="logo">	
			</td>
			<td align="right" valign="top">
				<div id="notify"><span id="notify_body"></div>
			</td>
		</tr><tr><td class="welcomePrompt">
			<? if (!SINGLE_USER_MODE) { ?>
			<? if (USE_HTTP_AUTH) { ?>
				<table align="right"><tr>
				<td class="httpWelcomePrompt">Hello, <b> <?= $_SESSION["name"] ?></b></td>
				<td><form action="tt-rss.php" method="POST">
					<input type="hidden" name="ForceLogout" value="yes">
					<input type="submit" class="button" value="Logout">
				</form>
				</td></tr></table>
			<? } else { ?>
				Hello, <b><?= $_SESSION["name"] ?></b>
				(<a href="logout.php">Logout</a>)
			<? } ?>
			</td>			
			<? } ?>
		</tr></table>
	</td>
</tr>
<? } ?>
<tr>
	<td class="prefsTabs" align="left" valign="bottom">
		<input id="genConfigTab" class="prefsTab" type="submit" value="Preferences"
			onclick="selectTab('genConfig')">
		<input id="feedConfigTab" class="prefsTab" type="submit" value="Feed Configuration"
			onclick="selectTab('feedConfig')">
		<input id="filterConfigTab" class="prefsTab" type="submit" value="Content Filtering"
			onclick="selectTab('filterConfig')">
		<? if (get_pref($link, 'ENABLE_LABELS')) { ?>
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
	<td class="footer" colspan="2">
		<a href="http://bah.spb.su/~fox/tt-rss/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
		<? if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<? } ?>
	</td>
</td>
<? } ?>
</table>

<? db_close($link); ?>

</body>
</html>
