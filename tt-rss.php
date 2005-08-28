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
		<td valign="middle"> <div id="notify">&nbsp;</div></td>
		<td class="toolbar" valign="middle" align="right">
			<a href="prefs.php" class="button">Preferences</a></td>
		</tr></table>
	</td>
</tr>
<tr>
	<td valign="top" rowspan="2" class="feeds"> 
		
		<div id="feeds">&nbsp;</div>
	
		<p align="center">All feeds:
		
		<a class="button" 
				href="javascript:scheduleFeedUpdate(true)">Update</a>

		<a class="button" 
				href="javascript:catchupAllFeeds()">Mark as read</a></p>

	</td>
	<td id="headlines" class="headlines" valign="top">
		Please select the feed.
	</td>
</tr>
	<td class="content" id="content" valign="top">
		&nbsp;
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
