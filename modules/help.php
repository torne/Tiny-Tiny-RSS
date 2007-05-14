<?php
	function module_help($link) {

		if (!$_GET["noheaders"]) {
			print "<html><head>
				<title>Tiny Tiny RSS : Help</title>
				<link rel=\"stylesheet\" href=\"utility.css\" type=\"text/css\">
				<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">
				</head><body>";
		}

		$tid = sprintf("%d", $_GET["tid"]);

		if (file_exists("help/$tid.php")) {
			include("help/$tid.php");
		} else {
			print "<p>".__("Help topic not found.")."</p>";
		}
		print "<div align='center'>
			<input type='submit' class='button'			
			onclick=\"javascript:window.close()\" 
			value=\"".__('Close this window')."\"></div>";

		if (!$_GET["noheaders"]) { 
			print "</body></html>";
		}
	}
?>
