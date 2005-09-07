<html>
<head>
	<title>Tiny Tiny RSS</title>
	<link rel="stylesheet" href="tt-rss.css" type="text/css">
	<script type="text/javascript" src="functions.js"></script>
	<script type="text/javascript" src="tt-rss.js"></script>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<? require_once "version.php" ?>
<? require_once "config.php" ?>

<body onload="init()">

<table width="100%" height="100%" cellspacing=0 cellpadding=0 class="main">
<tr>
	<td class="header" valign="middle" colspan="2">	
			Tiny Tiny RSS
	</td>
</tr>
<tr>
	<td class="toolbar" colspan="2">
		<table width="100%" cellspacing="0" cellpadding="0">
		<td valign="middle">
			<table id="notify"><tr><td width="100%" id="notify_body">&nbsp;</td>
			<td><img onclick="javascript:notify('')" alt="Close" 
				src="images/close.png"></td></table>
		</td>
		<td class="toolbar" valign="middle" align="right">
			<a href="prefs.php" class="button">Preferences</a></td>
		</tr></table>
	</td>
</tr>
<tr>
	<td valign="top" rowspan="3" class="feeds"> 
		
		<!-- <div id="feeds">&nbsp;</div> -->

		<iframe id="feeds-frame" name="feeds-frame" class="feedsFrame"> </iframe>
	
		<p align="center">All feeds:
		
		<input class="button" type="submit"	
			onclick="javascript:scheduleFeedUpdate(true)" value="Update">
				
		<input class="button" type="submit"	
			onclick="javascript:catchupAllFeeds()" value="Mark as read">

		</p>

	</td>
	<td valign="top" class="headlinesToolbarBox">
		<table width="100%">
		
		<!-- <tr><td id="headlinesTitle" class="headlinesTitle">
			&nbsp;
		</td></tr> -->
		<tr><td class="headlinesToolbar" id="headlinesToolbar">
			Search: <input id="searchbox"
			onblur="javascript:enableHotkeys()" onfocus="javascript:disableHotkeys()"
			onchange="javascript:search()">
		<input type="submit" 
			class="button" onclick="javascript:resetSearch()" value="Reset">

		&nbsp;View: 
		
		<select id="viewbox" onchange="javascript:viewCurrentFeed(0, '')">
			<option>All Posts</option>
			<option>Starred</option>
			<option selected>Unread</option>
		</select>

		&nbsp;Limit:

		<select id="limitbox" onchange="javascript:viewCurrentFeed(0, '')">
			<option>15</option>
			<option selected>30</option>
			<option>60</option>
			<option>All</option>
		</select>

		&nbsp;Feed: <input class="button" type="submit"
			onclick="javascript:viewCurrentFeed(0, 'ForceUpdate')" value="Update">

		<input class="button" type="submit"
			onclick="javascript:viewCurrentFeed(0, 'MarkAllRead')" value="Mark as read">

		</td></tr>
		</table>
	</td> 
</tr><tr>
	<td id="headlines" class="headlines" valign="top">
		<iframe name="headlines-frame" 
			id="headlines-frame" class="headlinesFrame"> </iframe>
	</td>
</tr><tr>
	<td class="content" id="content" valign="top">
		<iframe name="content-frame" id="content-frame" class="contentFrame"> </iframe>
	</td>
</tr>
<tr>
	<td colspan="2" class="notify">
		<a href="http://bah.spb.su/~fox/tt-rss/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
		<? if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<? } ?>
	</td>
</td>
</table>


</body>
</html>
