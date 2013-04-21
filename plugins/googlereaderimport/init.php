<?php
class GoogleReaderImport extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Import starred/shared items from Google Reader takeout",
			"fox",
			false,
			"");
	}

	function init($host) {
		$this->host = $host;

		$host->add_command("greader-import",
			"import data in Google Reader JSON format",
			$this, ":", "FILE");

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function greader_import($args) {
		$file = $args['greader_import'];

		if (!file_exists($file)) {
			_debug("file not found: $file");
			return;
		}

		_debug("please enter your username:");

		$username = db_escape_string(trim(read_stdin()));

		_debug("looking up user: $username...");

		$result = db_query("SELECT id FROM ttrss_users
			WHERE login = '$username'");

		if (db_num_rows($result) == 0) {
			_debug("user not found.");
			return;
		}

		$owner_uid = db_fetch_result($result, 0, "id");

		_debug("processing: $file (owner_uid: $owner_uid)");

		$this->import($file, $owner_uid);
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function import($file = false, $owner_uid = 0) {

		purge_orphans();

		if (!$file) {
			header("Content-Type: text/html");

			$owner_uid = $_SESSION["uid"];

			if ($_FILES['starred_file']['error'] != 0) {
				print_error(T_sprintf("Upload failed with error code %d",
					$_FILES['starred_file']['error']));
				return;
			}

			$tmp_file = false;

			if (is_uploaded_file($_FILES['starred_file']['tmp_name'])) {
				$tmp_file = tempnam(CACHE_DIR . '/upload', 'starred');

				$result = move_uploaded_file($_FILES['starred_file']['tmp_name'],
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
				$doc = json_decode(file_get_contents($tmp_file), true);
				unlink($tmp_file);
			} else {
				print_error(__('No file uploaded.'));
				return;
			}
		} else {
			$doc = json_decode(file_get_contents($file), true);
		}

		if ($file) {
			$sql_set_marked = strtolower(basename($file)) == 'starred.json' ? 'true' : 'false';
			_debug("will set articles as starred: $sql_set_marked");

		} else {
			$sql_set_marked = strtolower($_FILES['starred_file']['name']) == 'starred.json' ? 'true' : 'false';
		}

		if ($doc) {
			if (isset($doc['items'])) {
				$processed = 0;

				foreach ($doc['items'] as $item) {
//					print_r($item);

					$guid = db_escape_string(mb_substr($item['id'], 0, 250));
					$title = db_escape_string($item['title']);
					$updated = date('Y-m-d h:i:s', $item['updated']);
					$link = '';
					$content = '';
					$author = db_escape_string($item['author']);
					$tags = array();
					$orig_feed_data = array();

					if (is_array($item['alternate'])) {
						foreach ($item['alternate'] as $alt) {
							if (isset($alt['type']) && $alt['type'] == 'text/html') {
								$link = db_escape_string($alt['href']);
							}
						}
					}

					if (is_array($item['summary'])) {
						$content = db_escape_string(
							$item['summary']['content'], false);
					}

					if (is_array($item['content'])) {
						$content = db_escape_string(
							$item['content']['content'], false);
					}

					if (is_array($item['categories'])) {
						foreach ($item['categories'] as $cat) {
							if (strstr($cat, "com.google/") === FALSE) {
								array_push($tags, sanitize_tag($cat));
							}
						}
					}

					if (is_array($item['origin'])) {
						if (strpos($item['origin']['streamId'], 'feed/') === 0) {

							$orig_feed_data['feed_url'] = db_escape_string(
								mb_substr(preg_replace("/^feed\//",
									"", $item['origin']['streamId']), 0, 200));

							$orig_feed_data['title'] = db_escape_string(
								mb_substr($item['origin']['title'], 0, 200));

							$orig_feed_data['site_url'] = db_escape_string(
								mb_substr($item['origin']['htmlUrl'], 0, 200));
						}
					}

					$processed++;

					$imported += (int) $this->create_article($owner_uid, $guid, $title,
						$link, $updated,  $content, $author, $sql_set_marked, $tags,
						$orig_feed_data);

					if ($file && $processed % 25 == 0) {
						_debug("processed $processed articles...");
					}
				}

				if ($file) {
					_debug(sprintf("All done. %d of %d articles imported.", $imported, $processed));
				} else {
					print "<p style='text-align : center'>" . T_sprintf("All done. %d out of %d articles imported.", $imported, $processed) . "</p>";
				}

			} else {
				print_error(__('The document has incorrect format.'));
			}

		} else {
			print_error(__('Error while parsing document.'));
		}

		if (!$file) {
			print "<div align='center'>";
			print "<button dojoType=\"dijit.form.Button\"
				onclick=\"dijit.byId('starredImportDlg').execute()\">".
				__('Close this window')."</button>";
			print "</div>";
		}
	}

	// expects ESCAPED data
	private function create_article($owner_uid, $guid, $title, $link, $updated,  $content, $author, $marked, $tags, $orig_feed_data) {

		if (!$guid) $guid = sha1($link);

		$create_archived_feeds = true;

		$guid = "$owner_uid,$guid";

		$content_hash = sha1($content);

		if (filter_var(FILTER_VALIDATE_URL) === FALSE) return false;

		db_query("BEGIN");

		$feed_id = 'NULL';

		// let's check for archived feed entry

		$feed_inserted = false;

		// before dealing with archived feeds we must check ttrss_feeds to maintain id consistency

		if ($orig_feed_data['feed_url'] && $create_archived_feeds) {
				$result = db_query(
					"SELECT id FROM ttrss_feeds WHERE feed_url = '".$orig_feed_data['feed_url']."'
						AND owner_uid = $owner_uid");

			if (db_num_rows($result) != 0) {
				$feed_id = db_fetch_result($result, 0, "id");
			} else {
				// let's insert it

				if (!$orig_feed_data['title']) $orig_feed_data['title'] = '[Unknown]';

				$result = db_query(
					"INSERT INTO ttrss_feeds
						(owner_uid,feed_url,site_url,title,cat_id,auth_login,auth_pass,update_method)
						VALUES ($owner_uid,
						'".$orig_feed_data['feed_url']."',
						'".$orig_feed_data['site_url']."',
						'".$orig_feed_data['title']."',
						NULL, '', '', 0)");

				$result = db_query(
					"SELECT id FROM ttrss_feeds WHERE feed_url = '".$orig_feed_data['feed_url']."'
						AND owner_uid = $owner_uid");

				if (db_num_rows($result) != 0) {
					$feed_id = db_fetch_result($result, 0, "id");
					$feed_inserted = true;
				}
			}
		}

		if ($feed_id && $feed_id != 'NULL') {
			// locate archived entry to file entries in, we don't want to file them in actual feeds because of purging
			// maybe file marked in real feeds because eh

			$result = db_query("SELECT id FROM ttrss_archived_feeds WHERE
				feed_url = '".$orig_feed_data['feed_url']."' AND owner_uid = $owner_uid");

			if (db_num_rows($result) != 0) {
				$orig_feed_id = db_fetch_result($result, 0, "id");
			} else {
				db_query("INSERT INTO ttrss_archived_feeds
						(id, owner_uid, title, feed_url, site_url)
						SELECT id, owner_uid, title, feed_url, site_url from ttrss_feeds
							WHERE id = '$feed_id'");

				$result = db_query("SELECT id FROM ttrss_archived_feeds WHERE
					feed_url = '".$orig_feed_data['feed_url']."' AND owner_uid = $owner_uid");

				if (db_num_rows($result) != 0) {
					$orig_feed_id = db_fetch_result($result, 0, "id");
				}
			}
		}

		// delete temporarily inserted feed
		if ($feed_id && $feed_inserted) {
				db_query("DELETE FROM ttrss_feeds WHERE id = $feed_id");
		}

		if (!$orig_feed_id) $orig_feed_id = 'NULL';

		$result = db_query("SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
			guid = '$guid' AND ref_id = id AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 0) {
			$result = db_query("INSERT INTO ttrss_entries
				(title, guid, link, updated, content, content_hash, date_entered, date_updated, author)
				VALUES
				('$title', '$guid', '$link', '$updated', '$content', '$content_hash', NOW(), NOW(), '$author')");

			$result = db_query("SELECT id FROM ttrss_entries WHERE guid = '$guid'");

			if (db_num_rows($result) != 0) {
				$ref_id = db_fetch_result($result, 0, "id");

				db_query("INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, marked, tag_cache, label_cache,
						last_read, note, unread, last_marked)
					VALUES
					('$ref_id', '', NULL, $orig_feed_id, $owner_uid, $marked, '', '', NOW(), '', false, NOW())");

				$result = db_query("SELECT int_id FROM ttrss_user_entries, ttrss_entries
					WHERE owner_uid = $owner_uid AND ref_id = id AND ref_id = $ref_id");

				if (db_num_rows($result) != 0 && is_array($tags)) {

					$entry_int_id = db_fetch_result($result, 0, "int_id");
					$tags_to_cache = array();

					foreach ($tags as $tag) {

						$tag = db_escape_string(sanitize_tag($tag));

						if (!tag_is_valid($tag)) continue;

						$result = db_query("SELECT id FROM ttrss_tags
							WHERE tag_name = '$tag' AND post_int_id = '$entry_int_id' AND
							owner_uid = '$owner_uid' LIMIT 1");

							if ($result && db_num_rows($result) == 0) {
								db_query("INSERT INTO ttrss_tags
									(owner_uid,tag_name,post_int_id)
									VALUES ('$owner_uid','$tag', '$entry_int_id')");
							}

						array_push($tags_to_cache, $tag);
					}

					/* update the cache */

					$tags_to_cache = array_unique($tags_to_cache);
					$tags_str = db_escape_string(join(",", $tags_to_cache));

					db_query("UPDATE ttrss_user_entries
						SET tag_cache = '$tags_str' WHERE ref_id = '$ref_id'
						AND owner_uid = $owner_uid");
				}

				$rc = true;
			}
		}

		db_query("COMMIT");

		return $rc;
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__("Import starred or shared items from Google Reader")."\">";

		print_notice("Your imported articles will appear in Starred (in file is named starred.json) and Archived feeds.");

		print "<p>".__("Paste your starred.json or shared.json into the form below."). "</p>";

		print "<iframe id=\"starred_upload_iframe\"
			name=\"starred_upload_iframe\" onload=\"starredImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form name=\"starred_form\" style='display : block' target=\"starred_upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<input id=\"starred_file\" name=\"starred_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"pluginhandler\">
			<input type=\"hidden\" name=\"method\" value=\"import\">
			<input type=\"hidden\" name=\"plugin\" value=\"googlereaderimport\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return starredImport();\" type=\"submit\">" .
			__('Import my Starred items') . "</button>";

		print "</form>";

		print "</div>"; #pane
	}

	function api_version() {
		return 2;
	}

}
?>
