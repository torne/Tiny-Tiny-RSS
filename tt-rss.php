<? require_once "version.php" ?>
<? require_once "config.php" ?>

<html>
<head>
	<title>Tiny Tiny RSS</title>

	<? if (USE_COMPACT_STYLESHEET) { ?>

	<link title="Compact Stylesheet" 
		rel="stylesheet" href="tt-rss_compact.css" type="text/css">
	<link title="Normal Stylesheet" rel="alternate stylesheet" 
		type="text/css" href="tt-rss.css"/>

	<? } else { ?>

	<link title="Normal Stylesheet" 
		rel="stylesheet" href="tt-rss.css" type="text/css">
	<link title="Compact Stylesheet" rel="alternate stylesheet" 
		type="text/css" href="tt-rss_compact.css"/>

	<? } ?>
	
	<script type="text/javascript" src="functions.js"></script>
	<script type="text/javascript" src="tt-rss.js"></script>
	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body onload="init()">

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<? if (DISPLAY_HEADER) { ?>
<tr>
	<td colspan="2" class="headerBox">
		<table cellspacing="0" cellpadding="0" width="100%"><tr>
			<td class="header" valign="middle">	
				<img src="images/ttrss_logo.png" alt="logo">	
			</td>
			<td align="right" valign="top">
				<div id="notify"><span id="notify_body"></div>
			</td>
		</tr></table>

		<div id="userDlg">&nbsp;</div>

	</td>
</tr>
<? } ?>
<tr>
	<td valign="top" rowspan="3" class="feeds"> 
		
		<!-- <div id="feeds">&nbsp;</div> -->

		<div id="dispSwitch"> 
			<a id="dispSwitchPrompt" href="javascript:toggleTags()">display tags</a>
		</div>

		<iframe frameborder="0" 
			src="backend.php?op=error&msg=Loading,%20please wait..."
			id="feeds-frame" name="feeds-frame" class="feedsFrame"> </iframe>
	
		<p align="center">All feeds:
		
		<input class="button" type="submit"	
			onclick="javascript:scheduleFeedUpdate(true)" value="Update">
				
		<input class="button" type="submit"	
			onclick="javascript:catchupAllFeeds()" value="Mark as read">

		</p>

	</td>
	<td valign="top" class="headlinesToolbarBox">
		<table width="100%" cellpadding="0" cellspacing="0">
		
		<!-- <tr><td id="headlinesTitle" class="headlinesTitle">
			&nbsp;
		</td></tr> -->
		<tr><td class="headlinesToolbar" id="headlinesToolbar">
			<input id="searchbox"
			onblur="javascript:enableHotkeys()" onfocus="javascript:disableHotkeys()"
			onchange="javascript:search()">
		<select id="searchmodebox">
			<option>This feed</option>
			<option>All feeds</option>
		</select>
		
		<input type="submit" 
			class="button" onclick="javascript:search()" value="Search">
		<!-- <input type="submit" 
			class="button" onclick="javascript:resetSearch()" value="Reset"> -->

		&nbsp;View: 
		
		<select id="viewbox" onchange="javascript:viewCurrentFeed(0, '')">
			<option>All Articles</option>
			<option>Starred</option>
			<option selected>Unread</option>
			<option>Unread or Starred</option>
			<option>Unread or Updated</option>
		</select>

		&nbsp;Limit:

		<select id="limitbox" onchange="javascript:viewCurrentFeed(0, '')">
		
		<?
			$limits = array(15 => 15, 30 => 30, 60 => 60);
			
			if (DEFAULT_ARTILE_LIMIT >= 0) {
				$limits[DEFAULT_ARTICLE_LIMIT] = DEFAULT_ARTICLE_LIMIT; 
			}
			
			asort($limits);

			array_push($limits, 0);

			foreach ($limits as $key) {
				print "<option";
				if ($key == DEFAULT_ARTICLE_LIMIT) { print " selected"; }
				print ">";
				
				if ($limits[$key] == 0) { print "All"; } else { print $limits[$key]; }
				
				print "</option>";
			} ?>
			
		</select>

		&nbsp;Feed: <input class="button" type="submit"
			onclick="javascript:viewCurrentFeed(0, 'ForceUpdate')" value="Update">

		<input class="button" type="submit" id="btnMarkFeedAsRead"
			onclick="javascript:viewCurrentFeed(0, 'MarkAllRead')" value="Mark as read">

		</td>
		<td align="right">
			Actions: <select id="quickMenuChooser">
				<option selected>Preferences</option>
				<option disabled>-----</option>
				<option disabled>Feed actions:</option>
				<option>&nbsp;&nbsp;Add new feed</option>
				<option>&nbsp;&nbsp;Remove this feed</option>
				<!-- <option>Edit this feed</option> -->
			</select>
			<input type="submit" class="button" onclick="quickMenuGo()" value="Go">
		</td>
		</tr>
		</table>
	</td> 
</tr><tr>
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
<? if (DISPLAY_FOOTER) { ?>
<tr>
	<td colspan="2" class="footer">
		<a href="http://bah.spb.su/~fox/tt-rss/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
		<? if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<? } ?>
	</td>
</td>
<? } ?>
</table>


</body>
</html>
