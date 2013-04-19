<?php
class Import_Export extends Plugin implements IHandler {
	private $host;

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_command("xml-import", "import articles from XML", $this, ":", "FILE");
	}

	function about() {
		return array(1.0,
			"Imports and exports user data using neutral XML format",
			"fox");
	}

	function xml_import($args) {

		$filename = $args['xml_import'];

		if (!is_file($filename)) {
			print "error: input filename ($filename) doesn't exist.\n";
			return;
		}

		_debug("please enter your username:");

		$username = db_escape_string(trim(read_stdin()));

		_debug("importing $filename for user $username...\n");

		$result = db_query("SELECT id FROM ttrss_users WHERE login = '$username'");

		if (db_num_rows($result) == 0) {
			print "error: could not find user $username.\n";
			return;
		}

		$owner_uid = db_fetch_result($result, 0, "id");

		$this->perform_data_import($filename, $owner_uid);
	}

	function save() {
		$example_value = db_escape_string($_POST["example_value"]);

		echo "Value set to $example_value (not really)";
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/import_export.js");
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Import and export')."\">";

		print "<h3>" . __("Article archive") . "</h3>";

		print "<p>" . __("You can export and import your Starred and Archived articles for safekeeping or when migrating between tt-rss instances.") . "</p>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return exportData()\">".
			__('Export my data')."</button> ";

		print "<hr>";

		print "<iframe id=\"data_upload_iframe\"
			name=\"data_upload_iframe\" onload=\"dataImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form name=\"import_form\" style='display : block' target=\"data_upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<input id=\"export_file\" name=\"export_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"pluginhandler\">
			<input type=\"hidden\" name=\"plugin\" value=\"import_export\">
			<input type=\"hidden\" name=\"method\" value=\"dataimport\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return importData();\" type=\"submit\">" .
			__('Import') . "</button>";

		print "</form>";

		print "</div>"; # pane
	}

	function csrf_ignore($method) {
		return in_array($method, array("exportget"));
	}

	function before($method) {
		return $_SESSION["uid"] != false;
	}

	function after() {
		return true;
	}

	function exportget() {
		$exportname = CACHE_DIR . "/export/" .
			sha1($_SESSION['uid'] . $_SESSION['login']) . ".xml";

		if (file_exists($exportname)) {
			header("Content-type: text/xml");

			if (function_exists('gzencode')) {
				header("Content-Disposition: attachment; filename=TinyTinyRSS_exported.xml.gz");
				echo gzencode(file_get_contents($exportname));
			} else {
				header("Content-Disposition: attachment; filename=TinyTinyRSS_exported.xml");
				echo file_get_contents($exportname);
			}
		} else {
			echo "File not found.";
		}
	}

	function exportrun() {
		$offset = (int) db_escape_string($_REQUEST['offset']);
		$exported = 0;
		$limit = 250;

		if ($offset < 10000 && is_writable(CACHE_DIR . "/export")) {
			$result = db_query("SELECT
					ttrss_entries.guid,
					ttrss_entries.title,
					content,
					marked,
					published,
					score,
					note,
					link,
					tag_cache,
					label_cache,
					ttrss_feeds.title AS feed_title,
					ttrss_feeds.feed_url AS feed_url,
					ttrss_entries.updated
				FROM
					ttrss_user_entries LEFT JOIN ttrss_feeds ON (ttrss_feeds.id = feed_id),
					ttrss_entries
				WHERE
					(marked = true OR feed_id IS NULL) AND
					ref_id = ttrss_entries.id AND
					ttrss_user_entries.owner_uid = " . $_SESSION['uid'] . "
				ORDER BY ttrss_entries.id LIMIT $limit OFFSET $offset");

			$exportname = sha1($_SESSION['uid'] . $_SESSION['login']);

			if ($offset == 0) {
				$fp = fopen(CACHE_DIR . "/export/$exportname.xml", "w");
				fputs($fp, "<articles schema-version=\"".SCHEMA_VERSION."\">");
			} else {
				$fp = fopen(CACHE_DIR . "/export/$exportname.xml", "a");
			}

			if ($fp) {

				while ($line = db_fetch_assoc($result)) {
					fputs($fp, "<article>");

					foreach ($line as $k => $v) {
						$v = str_replace("]]>", "]]]]><![CDATA[>", $v);
						fputs($fp, "<$k><![CDATA[$v]]></$k>");
					}

					fputs($fp, "</article>");
				}

				$exported = db_num_rows($result);

				if ($exported < $limit && $exported > 0) {
					fputs($fp, "</articles>");
				}

				fclose($fp);
			}

		}

		print json_encode(array("exported" => $exported));
	}

	function perform_data_import($filename, $owner_uid) {

		$num_imported = 0;
		$num_processed = 0;
		$num_feeds_created = 0;

		$doc = @DOMDocument::load($filename);

		if (!$doc) {
			$contents = file_get_contents($filename);

			if ($contents) {
				$data = @gzuncompress($contents);
			}

			if (!$data) {
				$data = @gzdecode($contents);
			}

			if ($data)
				$doc = DOMDocument::loadXML($data);
		}

		if ($doc) {

			$xpath = new DOMXpath($doc);

			$container = $doc->firstChild;

			if ($container && $container->hasAttribute('schema-version')) {
				$schema_version = $container->getAttribute('schema-version');

				if ($schema_version != SCHEMA_VERSION) {
					print "<p>" .__("Could not import: incorrect schema version.") . "</p>";
					return;
				}

			} else {
				print "<p>" . __("Could not import: unrecognized document format.") . "</p>";
				return;
			}

			$articles = $xpath->query("//article");

			foreach ($articles as $article_node) {
				if ($article_node->childNodes) {

					$ref_id = 0;

					$article = array();

					foreach ($article_node->childNodes as $child) {
						if ($child->nodeName != 'label_cache')
							$article[$child->nodeName] = db_escape_string($child->nodeValue);
						else
							$article[$child->nodeName] = $child->nodeValue;
					}

					//print_r($article);

					if ($article['guid']) {

						++$num_processed;

						//db_query("BEGIN");

						//print 'GUID:' . $article['guid'] . "\n";

						$result = db_query("SELECT id FROM ttrss_entries
							WHERE guid = '".$article['guid']."'");

						if (db_num_rows($result) == 0) {

							$result = db_query(
								"INSERT INTO ttrss_entries
									(title,
									guid,
									link,
									updated,
									content,
									content_hash,
									no_orig_date,
									date_updated,
									date_entered,
									comments,
									num_comments,
									author)
								VALUES
									('".$article['title']."',
									'".$article['guid']."',
									'".$article['link']."',
									'".$article['updated']."',
									'".$article['content']."',
									'".sha1($article['content'])."',
									false,
									NOW(),
									NOW(),
									'',
									'0',
									'')");

							$result = db_query("SELECT id FROM ttrss_entries
								WHERE guid = '".$article['guid']."'");

							if (db_num_rows($result) != 0) {
								$ref_id = db_fetch_result($result, 0, "id");
							}

						} else {
							$ref_id = db_fetch_result($result, 0, "id");
						}

						//print "Got ref ID: $ref_id\n";

						if ($ref_id) {

							$feed_url = $article['feed_url'];
							$feed_title = $article['feed_title'];

							$feed = 'NULL';

							if ($feed_url && $feed_title) {
								$result = db_query("SELECT id FROM ttrss_feeds
									WHERE feed_url = '$feed_url' AND owner_uid = '$owner_uid'");

								if (db_num_rows($result) != 0) {
									$feed = db_fetch_result($result, 0, "id");
								} else {
									// try autocreating feed in Uncategorized...

									$result = db_query("INSERT INTO ttrss_feeds (owner_uid,
										feed_url, title) VALUES ($owner_uid, '$feed_url', '$feed_title')");

									$result = db_query("SELECT id FROM ttrss_feeds
										WHERE feed_url = '$feed_url' AND owner_uid = '$owner_uid'");

									if (db_num_rows($result) != 0) {
										++$num_feeds_created;

										$feed = db_fetch_result($result, 0, "id");
									}
								}
							}

							if ($feed != 'NULL')
								$feed_qpart = "feed_id = $feed";
							else
								$feed_qpart = "feed_id IS NULL";

							//print "$ref_id / $feed / " . $article['title'] . "\n";

							$result = db_query("SELECT int_id FROM ttrss_user_entries
								WHERE ref_id = '$ref_id' AND owner_uid = '$owner_uid' AND $feed_qpart");

							if (db_num_rows($result) == 0) {

								$marked = bool_to_sql_bool(sql_bool_to_bool($article['marked']));
								$published = bool_to_sql_bool(sql_bool_to_bool($article['published']));
								$score = (int) $article['score'];

								$tag_cache = $article['tag_cache'];
								$label_cache = db_escape_string($article['label_cache']);
								$note = $article['note'];

								//print "Importing " . $article['title'] . "<br/>";

								++$num_imported;

								$result = db_query(
									"INSERT INTO ttrss_user_entries
									(ref_id, owner_uid, feed_id, unread, last_read, marked,
										published, score, tag_cache, label_cache, uuid, note)
									VALUES ($ref_id, $owner_uid, $feed, false,
										NULL, $marked, $published, $score, '$tag_cache',
											'$label_cache', '', '$note')");

								$label_cache = json_decode($label_cache, true);

								if (is_array($label_cache) && $label_cache["no-labels"] != 1) {
									foreach ($label_cache as $label) {

										label_create($label[1],
											$label[2], $label[3], $owner_uid);

										label_add_article($ref_id, $label[1], $owner_uid);

									}
								}

								//db_query("COMMIT");
							}
						}
					}
				}
			}

			print "<p>" .
				__("Finished: ").
				vsprintf(ngettext("%d article processed, ", "%d articles processed, ", $num_processed), $num_processed).
				vsprintf(ngettext("%d imported, ", "%d imported, ", $num_imported), $num_imported).
				vsprintf(ngettext("%d feed created.", "%d feeds created.", $num_feeds_created), $num_feeds_created).
					"</p>";

		} else {

			print "<p>" . __("Could not load XML document.") . "</p>";

		}
	}

	function exportData() {

		print "<p style='text-align : center' id='export_status_message'>You need to prepare exported data first by clicking the button below.</p>";

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('dataExportDlg').prepare()\">".
			__('Prepare data')."</button>";

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('dataExportDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";


	}

	function dataImport() {
		header("Content-Type: text/html"); # required for iframe

		print "<div style='text-align : center'>";

		if ($_FILES['export_file']['error'] != 0) {
			print_error(T_sprintf("Upload failed with error code %d",
				$_FILES['export_file']['error']));
			return;
		}

		$tmp_file = false;

		if (is_uploaded_file($_FILES['export_file']['tmp_name'])) {
			$tmp_file = tempnam(CACHE_DIR . '/upload', 'export');

			$result = move_uploaded_file($_FILES['export_file']['tmp_name'],
				$tmp_file);

			if (!$result) {
				print_error(__("Unable to move uploaded file."));
				return;
			}
		} else {
			print_error(__('Error: please upload OPML file.'));
			return;
		}

		if (is_file($tmp_file)) {
			$this->perform_data_import($tmp_file, $_SESSION['uid']);
			unlink($tmp_file);
		} else {
			print_error(__('No file uploaded.'));
			return;
		}

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('dataImportDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";

	}

	function api_version() {
		return 2;
	}

}
?>
