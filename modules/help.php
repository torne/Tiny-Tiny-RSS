<?php
	function module_help($link) {

		if (!$_GET["noheaders"]) {
			print "<html><head>
				<title>Tiny Tiny RSS : Help</title>
				<link rel=\"stylesheet\" href=\"tt-rss.css\" type=\"text/css\">
				<script type=\"text/javascript\" src=\"prototype.js\"></script>
				<script type=\"text/javascript\" src=\"functions.js?$script_dt_add\"></script>
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
				</head><body>";
		}

		$tid = sprintf("%d", $_GET["tid"]);

		print "<div id=\"infoBoxTitle\">Help</div>";

		print "<div class='infoBoxContents'>";

		if (file_exists("help/$tid.php")) {
			include("help/$tid.php");
		} else {
			print "<p>Help topic not found.</p>";
		}

		print "</div>";

		print "<div align='center'>
			<input type='submit' class='button'			
			onclick=\"closeInfoBox()\" value=\"Close this window\"></div>";

		if (!$_GET["noheaders"]) { 
			print "</body></html>";
		}
	}
?>
