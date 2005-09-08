<html>
<head>
	<title>Tiny Tiny RSS : Preferences</title>
	<link rel="stylesheet" href="tt-rss.css" type="text/css">
	<script type="text/javascript" src="functions.js"></script>
	<script type="text/javascript" src="prefs.js"></script>
	<!--[if gte IE 5.5000]>
		<script type="text/javascript" src="pngfix.js"></script>
	<![endif]-->
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>

<body onload="init()">

<? require_once "version.php" ?>
<? require_once "config.php" ?>

<table width="100%" height="100%" cellspacing="0" cellpadding="0" class="main">
<tr>
	<td class="header" valign="middle">	
		<img src="images/ttrss_logo.png" alt="logo">	
	</td>
</tr>
<tr>
	<td class="prefsToolbar" valign="middle">

		<table width='100%' cellspacing='0' cellpadding='0'>	
			<td><span id="notify"><span id="notify_body"></span></td>
			<td align='right'>
				<input type="submit" onclick="gotoMain()" 
					class="button" value="Return to main"></td>
		</table>
</tr>
</tr>
	<td id="prefContent" class="prefContent" valign="top">
		<h2>Feed Configuration</h2><div id="piggie">&nbsp;</div>

		<div class="expPane" id="feedConfPane">
			<a class="button" 
				href="javascript:expandPane('feedConfPane')">Expand section &gt;</a>
		</div>

		<h2>OPML Import</h2>

		<div class="expPane">
	
		<form	enctype="multipart/form-data" method="POST" action="opml.php">
			File: <input id="opml_file" name="opml_file" type="file">&nbsp;
			<input class="button" name="op" onclick="return validateOpmlImport();"
				type="submit" value="Import">
			</form>

		</div>

		<h2>Content Filtering</h2>

		<div class="expPane" id="filterConfPane">
			<a class="button" 
				href="javascript:expandPane('filterConfPane')">Expand section &gt;</a>

		</div>

	</td>
</tr>
<tr>
	<td class="footer">
		<a href="http://bah.spb.su/~fox/tt-rss/">Tiny-Tiny RSS</a> v<?= VERSION ?> &copy; 2005 Andrew Dolgov
		<? if (WEB_DEMO_MODE) { ?>
		<br>Running in demo mode, some functionality is disabled.
		<? } ?>
	</td>
</td>
</table>


</body>
</html>
