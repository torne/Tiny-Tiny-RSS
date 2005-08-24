<html>
<head>
	<title>Tiny Tiny RSS</title>
	<link rel="stylesheet" href="tt-rss.css" type="text/css">
	<script type="text/javascript" src="functions.js"></script>
	<script type="text/javascript" src="prefs.js"></script>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body onload="init()">

<? require_once "config.php" ?>

<table width="100%" height="100%" cellspacing=0 cellpadding=0 class="main">
<tr>
	<td class="header" valign="middle" colspan="2">	
			Preferences
	</td>
</tr>
<tr>
	<td class="toolbar" valign="middle">
		 <div id="notify">&nbsp;</div>
	</td>
	<td class="toolbar" valign="middle" colspan="2" align="right">	
		<a href="tt-rss.php" class="button">Return to main</a>
	</td>
</tr>
</tr>
	<td class="prefContent" valign="top" colspan="2">
		<h2>Feed Configuration</h2>

<!--
		<table class="prefAddFeed">
			<td><input id="fadd_link"></td>
			<td colspan="4" align="right">
				<a class="button" href="javascript:addFeed()">Add feed</a></td></tr>
		</table> 
-->
		<div id="piggie">&nbsp;</div>

		<div id="feeds">&nbsp;</div>

		<p>Selection:&nbsp;
			<a class="button" 
				href="javascript:editSelectedFeed()">Edit</a>&nbsp;
			<a class="buttonWarn" 
				href="javascript:removeSelectedFeeds()">Remove</a>&nbsp;
			<a class="button" 
				href="javascript:readSelectedFeeds()">Mark as read</a>&nbsp;
			<a class="button" 
				href="javascript:unreadSelectedFeeds()">Mark as unread</a>&nbsp;
			<a class="button" 
				href="javascript:updateFeedList()">Refresh</a>&nbsp;

		<hr>

	</td>
</tr>
<tr>
	<td colspan="2" class="notify">
		<a href="">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
	</td>
</td>
</table>


</body>
</html>
