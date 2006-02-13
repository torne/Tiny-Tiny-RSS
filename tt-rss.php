<?
	session_start();

	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "db-prefs.php";
	require_once "functions.php"; 

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

?>
<html>
<head>
	<title>Tiny Tiny RSS</title>

	<link rel="stylesheet" type="text/css" href="tt-rss.css">

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

	<script type="text/javascript" src="functions.js"></script>
	<script type="text/javascript" src="tt-rss.js"></script>
	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
		<link rel="stylesheet" type="text/css" href="tt-rss-ie.css">
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body onload="init()">

<? if (ENABLE_UPDATE_DAEMON && !file_is_locked("update_daemon.lock")) { ?>
	<div class="warning">
		<b>Warning:</b> Update daemon is enabled in configuration, but daemon
		process is not running, which prevents all feeds from updating. Please
		start the daemon process or contact instance owner.
	</div>
<? } ?>

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<? if (get_pref($link, 'DISPLAY_HEADER')) { ?>
<tr>
	<td colspan="2" class="headerBox">
		<table cellspacing="0" cellpadding="0" width="100%"><tr>
			<td rowspan="2" class="header" valign="middle">	
				<img src="images/ttrss_logo.png" alt="logo">	
			</td>
			<td valign="top" class="notifyBox">
				<div id="notify"><span id="notify_body">&nbsp;</span></div>
			</td>

			<div id="userDlgShadow"><div id="userDlg">&nbsp;</div></div>

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
	<? if (get_pref($link, 'COMBINED_DISPLAY_MODE')) 
			$feeds_rowspan = 2;
		else 
			$feeds_rowspan = 3; ?>
	<td valign="top" rowspan="<?= $feeds_rowspan ?>" class="feeds"> 
		<table class="innerFeedTable" 
			cellspacing="0" cellpadding="0" height="100%" width="100%">
		<tr><td>
			<div id="dispSwitch"> 
			<a id="dispSwitchPrompt" href="javascript:toggleTags()">display tags</a>
		</div>
		</td></tr>	
		<tr><td height="100%" width="100%" valign="top">
		
		<? if (get_pref($link, 'DISPLAY_FEEDLIST_ACTIONS')) { ?>

		<iframe frameborder="0" 
			src="backend.php?op=error&msg=Loading,%20please wait..."
			id="feeds-frame" name="feeds-frame" class="feedsFrameWithActions"> </iframe>

		<div align="center"> 
		
		<select id="allFeedsChooser" onchange="allFeedsMenuChange()">
			<option selected>All feeds...</option>
			<? if (!ENABLE_UPDATE_DAEMON) { ?>
			<option>Update</option>
			<? } ?>
			<option>Mark as read</option>
			<option>Show only unread</option>
		</select>

		</div>
		
		<? } else { ?>

		<iframe frameborder="0" 
			src="backend.php?op=error&msg=Loading,%20please wait..."
			id="feeds-frame" name="feeds-frame" class="feedsFrame"> </iframe>

		<? } ?>

		</td></tr></table>

	</td>
	<td valign="top" class="headlinesToolbarBox">
		<table width="100%" cellpadding="0" cellspacing="0">

		<tr><td class="headlinesToolbar" id="headlinesToolbar">

		<? if (get_pref($link, 'ENABLE_SEARCH_TOOLBAR')) { ?>

		<input id="searchbox"
			onblur="javascript:enableHotkeys()" onfocus="javascript:disableHotkeys()"
			onchange="javascript:search()">
		<select id="searchmodebox">
			<option>This feed</option>
			<option>All feeds</option>
		</select>
		
		<input type="submit" 
			class="button" onclick="javascript:search()" value="Search">

		&nbsp;
		
		<? } ?>
		
		View: 
		
		<select id="viewbox" onchange="javascript:viewCurrentFeed(0, '')">
			<option selected>Adaptive</option>
			<option>All Articles</option>
			<option>Starred</option>
			<option>Unread</option>
		</select>

		&nbsp;Limit:

		<select id="limitbox" onchange="javascript:viewCurrentFeed(0, '')">
		
		<?
			$limits = array(15 => 15, 30 => 30, 60 => 60);
			
			$def_art_limit = get_pref($link, 'DEFAULT_ARTICLE_LIMIT');

			if ($def_art_limit >= 0 && !array_key_exists($def_art_limit, $limits)) {
				$limits[$def_art_limit] = $def_art_limit; 
			}

			asort($limits);
			array_push($limits, 0);

			if (!$def_art_limit) {
				$def_art_limit = 30;
			}

			foreach ($limits as $key) {
				print "<option";
				if ($key == $def_art_limit) { print " selected"; }
				print ">";
				
				if ($limits[$key] == 0) { print "All"; } else { print $limits[$key]; }
				
				print "</option>";
			} ?>
		
		</select>

<!--		&nbsp;Selection:

		<select id="headopbox">
			<option id="hopToggleRead">Toggle (un)read</option>
		</select>

		<input class="button" type="submit" onclick="headopGo()" value="Go"> -->

		&nbsp;Feed: <input class="button" type="submit"
			onclick="javascript:viewCurrentFeed(0, 'ForceUpdate')" value="Update">

		<input class="button" type="submit" id="btnMarkFeedAsRead"
			onclick="javascript:viewCurrentFeed(0, 'MarkAllRead')" value="Mark as read"> 

		</td>
		<td align="right">
			<select id="quickMenuChooser" onchange="quickMenuChange()">
				<option id="qmcDefault" selected>Actions...</option>
				<option id="qmcPrefs">Preferences</option>
				<option id="qmcSearch">Search</option>
				<option disabled>--------</option>
				<option style="color : #5050aa" disabled>Feed actions:</option>
				<option id="qmcAddFeed">&nbsp;&nbsp;Add new feed</option>
				<option id="qmcRemoveFeed">&nbsp;&nbsp;Remove this feed</option>
				<!-- <option>Edit this feed</option> -->
				<option disabled>--------</option>
				<option style="color : #5050aa" disabled>All feeds:</option>
				<? if (!ENABLE_UPDATE_DAEMON) { ?>
				<option id="qmcUpdateFeeds">&nbsp;&nbsp;Update</option>
				<? } ?>
				<option id="qmcCatchupAll">&nbsp;&nbsp;Mark as read</option>				
				<option id="qmcShowOnlyUnread">&nbsp;&nbsp;Show only unread</option>
				<option disabled>--------</option>
				<option style="color : #5050aa" disabled>Other actions:</option>				
				<option id="qmcAddFilter">&nbsp;&nbsp;Add filter</option>
			</select>
		</td>
		</tr>
		</table>
	</td>
</tr>
<? if (get_pref($link, 'COMBINED_DISPLAY_MODE')) { ?>
<tr>
	<td id="headlines" class="headlines2" valign="top">
		<iframe frameborder="0" name="headlines-frame" 
			id="headlines-frame" class="headlinesFrame" 
				src="backend.php?op=error&msg=No%20feed%20selected."></iframe>
	</td>
</tr>
<? } else { ?>
<tr>
	<td id="headlines" class="headlines" valign="top">
		<iframe frameborder="0" name="headlines-frame" 
			id="headlines-frame" class="headlinesFrame" 
				src="backend.php?op=error&msg=No%20feed%20selected."></iframe>
	</td>
</tr><tr>
	<td class="content" id="content" valign="top">
		<iframe frameborder="0" name="content-frame" 
			id="content-frame" class="contentFrame"> </iframe>
	</td>
</tr>
<? } ?>
<? if (get_pref($link, 'DISPLAY_FOOTER')) { ?>
<tr>
	<td colspan="2" class="footer">
		<a href="http://tt-rss.spb.ru/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
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
