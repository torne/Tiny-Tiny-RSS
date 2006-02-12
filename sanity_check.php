<?
	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy 
		<b>config.php-dist</b> to <b>config.php</b> and edit it.";
		exit;
	}

	if (!file_exists("magpierss/rss_fetch.inc")) {
		print "<b>Fatal Error</b>: You forgot to place 
		<a href=\"http://magpierss.sourceforge.net\">MagpieRSS</a>
		distribution in <b>magpierss/</b>
		subdirectory of TT-RSS tree.";
		exit;
	}

	if (file_exists("xml-export.php") || file_exists("xml-import.php")) {
		print "<b>Fatal Error</b>: XML Import/Export tools (<b>xml-export.php</b>
		and <b>xml-import.php</b>) could be used maliciously. Please remove them 
		from your TT-RSS instance.";
		exit;
	}
?>
