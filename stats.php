<?
	require_once "sessions.php";

	require_once "sanity_check.php";
	require_once "version.php"; 
	require_once "config.php";
	require_once "db-prefs.php";
	require_once "functions.php"; 

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

	if ($_SESSION["access_level"] < 10) { 
		header("Location: login.php"); die;
	}
?>

<html>
<head>
	<title>Tiny Tiny Statistics</title>
</head>

<body>

<h1>Tiny Tiny Statistics</h1>

<h2>Counters</h2>

<?
	$result = db_query($link, "SELECT count(id) AS cid,
		SUM(LENGTH(content)) AS size
		FROM ttrss_entries");

	$total_articles = db_fetch_result($result, 0, "cid");
	$articles_size = round(db_fetch_result($result, 0, "size") / 1024);

	print "<p>Total articles stored: $total_articles (${articles_size}K)</p>";

/*	$result = db_query($link, "SELECT COUNT(int_id) as cid,owner_uid,login 
		FROM ttrss_user_entries 
			LEFT JOIN ttrss_users ON (owner_uid = ttrss_users.id)
		GROUP BY owner_uid,login ORDER BY cid DESC"); */

	$result = db_query($link, "SELECT count(ttrss_entries.id) AS cid,
		login FROM ttrss_entries 
		LEFT JOIN ttrss_user_entries ON (ref_id = ttrss_entries.id) 
		LEFT JOIN ttrss_users ON (ttrss_users.id = owner_uid) GROUP BY login");

	print "<h2>Per-user storage</h2>";

	print "<table width='100%'>";
	
	print "<tr>
		<td>Articles</td>
		<td>Owner</td>
	</tr>";

	while ($line = db_fetch_assoc($result)) {
		print "<tr>";
		print "<td>" . $line["cid"] . "</td><td>" . $line["login"] . "</td>";
		print "</tr>";
	}

	print "</table>";

	print "<h2>User subscriptions</h2>";

	$result = db_query($link, "SELECT title,feed_url,site_url,login,
		(SELECT count(int_id) FROM ttrss_user_entries 
			WHERE feed_id = ttrss_feeds.id) AS num_articles,
		(SELECT count(int_id) FROM ttrss_user_entries 
			WHERE feed_id = ttrss_feeds.id AND unread = true) AS num_articles_unread
		FROM ttrss_feeds,ttrss_users 
		WHERE owner_uid = ttrss_users.id ORDER BY login");

	print "<table width='100%'>";
	print "<tr>
		<td>Site</td>
		<td>Feed</td>
		<td>Owner</td>
		<td>Stored Articles</td>
		<td>Unread Articles</td>
	</tr>";

	$cur_login = "";

	while ($line = db_fetch_assoc($result)) {
		print "<tr>";
		print "<td><a href=\"".$line["site_url"]."\">".$line["title"]."</a></td>";
		print "<td><a href=\"".$line["feed_url"]."\">".$line["feed_url"]."</a></td>";
		print "<td>" . $line["login"] . "</td>";
		print "<td>" . $line["num_articles"] . "</td>";
		print "<td>" . $line["num_articles_unread"] . "</td>";
		print "</tr>";

		if ($cur_login != $line["login"] && $cur_login != "") {
			print "<tr><td>&nbsp;</td></tr>";
			$cur_login = $line["login"];
		}
	}

	print "</table>";

?>
</pre>

</body>
</html>
