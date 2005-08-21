<?
	header("Content-Type: application/xml");

	include "config.php";

	$link = pg_connect(DB_CONN);
	
	$op = $_GET["op"];
	
	if ($op == "feeds") {

		$result = pg_query("SELECT * FROM ttrss_feeds ORDER BY title");			

		print "<ul>";

		$lnum = 0;

		while ($line = pg_fetch_assoc($result)) {

			$feed = $line["title"];
			$feed_id = $line["id"];	  
			
			$class = ($lnum % 2) ? "even" : "odd";
			
//			if ($lnum == 2 || $lnum == 0) $feed = "<b>$feed</b>";
			
			$feed = "<a href=\"javascript:viewfeed($feed_id);\">$feed</a>";
			
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
