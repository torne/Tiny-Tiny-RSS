<?
	require_once "config.php";
	require_once "functions.php";
	require_once "db.php";

	define('MAX_SOURCE_SCHEMA_VERSION', 2);
	define('TARGET_SCHEMA_VERSION', 2);

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);	

	login_sequence($link);

	if (!$link) {
		if (DB_TYPE == "mysql") {
			print mysql_error();
		}
		// PG seems to display its own errors just fine by default.		
		return;
	}

	if (DB_TYPE == "pgsql") {
		pg_query("set client_encoding = 'utf-8'");
	}

	$result = db_query($link, "SELECT schema_version FROM ttrss_version");

	$schema_version = db_fetch_result($result, 0, "schema_version");

	if ($schema_version != TARGET_SCHEMA_VERSION) {
		print "Error: database schema is invalid
			(got version $schema_version; expected ".TARGET_SCHEMA_VERSION.")";
		return;
	}

	function import_article($link, $data) {

		print "Processing article <b>".$data["title"].
		"</b> (".$data["feed_title"].")<br>";

		$owner_uid = $_SESSION["uid"];

		db_query($link, "BEGIN");

		$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE feed_url = '".
			db_escape_string($data["feed_url"]) . "' AND owner_uid = '$owner_uid'");

		if (db_num_rows($result) == 0) {
			return false;
		}

		$feed_id = db_fetch_result($result, 0, "id");		

		$result = db_query($link, "SELECT id FROM ttrss_entries WHERE
			guid = '".$data["guid"]."'");

		if (db_num_rows($result) == 0) {

			print "Not found, adding base entry...<br>";

			$entry_title = db_escape_string($data["title"]);
			$entry_guid = db_escape_string($data["guid"]);
			$entry_link = db_escape_string($data["link"]);
			$updated = db_escape_string($data["updated"]);
			$date_entered = db_escape_string($data["date_entered"]);
			$entry_content = db_escape_string($data["content"]);
			$content_hash = "SHA1:" . sha1(strip_tags($entry_content));
			$entry_comments = db_escape_string($data["comments"]);

			$result = db_query($link,
				"INSERT INTO ttrss_entries 
					(title,
					guid,
					link,
					updated,
					content,
					content_hash,
					no_orig_date,
					date_entered,
					comments)
				VALUES
					('$entry_title', 
					'$entry_guid', 
					'$entry_link',
					'$updated', 
					'$entry_content', 
					'$content_hash',
					false, 
					'$date_entered', 
					'$entry_comments')");
		}

		$result = db_query($link, "SELECT id FROM ttrss_entries WHERE
			guid = '".$data["guid"]."'");

		if (db_num_rows($result) == 0) { return false; }

		$entry_id = db_fetch_result($result, 0, "id");

		print "Found base ID: $entry_id<br>";

		$result = db_query($link, "SELECT int_id FROM ttrss_user_entries WHERE
			ref_id = '$entry_id' AND owner_uid = '$owner_uid'");

		if (db_num_rows($result) == 0) {
			print "User table entry not found, creating...<br>";

			$unread = sql_bool_to_string(db_escape_string($data["unread"]));
			$marked = sql_bool_to_string(db_escape_string($data["marked"]));
			$last_read = db_escape_string($data["last_read"]);

			if (!$last_read) {
				$last_read_qpart = 'NULL';
			} else {
				$last_read_qpart = "'$last_read'";
			}

			$result = db_query($link,
				"INSERT INTO ttrss_user_entries 
					(ref_id, owner_uid, feed_id, unread, marked, last_read) 
				VALUES ('$entry_id', '$owner_uid', '$feed_id', $unread, $marked,
					$last_read_qpart)");

		} else {
			print "User table entry already exists, nothing to do.<br>";
		}

		db_query($link, "COMMIT");

	}

?>
<html>
<head>
	<title>XML Import</title>
	<link rel="stylesheet" href="opml.css" type="text/css">
</head>
<body>

	<h1><img src="images/ttrss_logo.png"></h1>

	<? if ($_REQUEST["op"] != "Import") { ?>

	<div class="opmlBody">
	
	<h2>Import XMLDB</h2>

	<form	enctype="multipart/form-data" method="POST" action="xml-import.php">
	File: <input name="xmldb" type="file">&nbsp;
	<input class="button" name="op" type="submit" value="Import">
	</form>

	<? } else {

		print "<h2>Importing data</h2>";

		if (is_file($_FILES['xmldb']['tmp_name'])) {
			$dom = domxml_open_file($_FILES['xmldb']['tmp_name']);
//			$dom = domxml_open_file('xmldb.xml');

			if ($dom) {
				$root = $dom->document_element();

				$schema_version = $root->get_elements_by_tagname('schema_version');
				$schema_version = $schema_version[0]->get_content();

				if ($schema_version != MAX_SOURCE_SCHEMA_VERSION) {
					die("Incorrect source schema version");
				}

				$articles = $root->get_elements_by_tagname("article");

				foreach ($articles as $article) {
					$child_nodes = $article->child_nodes();

					$article_data = array();

					foreach ($child_nodes as $child) {
						$article_data[$child->tagname()] = $child->get_content();
					}

					import_article($link, $article_data);
				}
			} else {
				print "Error: could not parse document.";
			}
		} else {
			print "<p>Error: please upload XMLDB.</p>";
		}

	} ?>
</div>
</body>
</html>

