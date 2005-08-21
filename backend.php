<?
	header("Content-Type: application/xml");

	$op = $_GET["op"];

	if ($op == "feeds") {

		$feeds = array("Art.Gnome.Org Releases", "Footnotes", "Freedesktop.org",	
				"Planet Debian", "Planet Gnome");


		print "<ul>";

		$lnum = 0;

		foreach ($feeds as $feed) {
			$class = ($lnum % 2) ? "even" : "odd";
			
//			if ($lnum == 2 || $lnum == 0) $feed = "<b>$feed</b>";
			
			$feed = "<a href=\"javascript:viewfeed('$feed')\">$feed</a>";
			
			print "<li class=\"$class\">$feed</li>";
			++$lnum;
		}

		print "</ul>";

	}

	if ($op == "view") {

		$post = $_GET["post"];
		$feed = $_GET["feed"];

		print "<h1>$post</h1>";
		print "<h2>$feed</h2>";

		print "<p>Blah blah blah blah blah</p>";

	}

	if ($op == "viewfeed") {

		$feed = $_GET["feed"];

		$headlines = array("Linus Torvalds flies to the Moon",
			"SCO bankrupt at last",
			"OMG WTF ANOTHER HEADLINE",
			"Another clever headline from $feed",
			"I'm really not feeling creative today",
			"No, seriously");

		$headlines = array_merge($headlines, $headlines);

		print "<ul>";

		$lnum = 0;

		foreach ($headlines as $hl) {
			$class = ($lnum % 2) ? "even" : "odd";

//			if ($lnum == 2 || $lnum == 0) $feed = "<b>$feed</b>";
			
			$hl = "<a href=\"javascript:view('$feed','$hl')\">$hl</a>";
			
			print "<li class=\"$class\">$hl</li>";
			++$lnum;
		}

		print "</ul>";

	}


?>
