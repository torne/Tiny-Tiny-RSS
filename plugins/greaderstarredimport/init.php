<?php
class GreaderStarredImport extends Plugin {


	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Import starred items from Google Reader takeout",
			"fox",
			false,
			"");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function import() {

		header("Content-Type: text/html");

		if (is_file($_FILES['starred_file']['tmp_name'])) {
			$doc = json_decode(file_get_contents($_FILES['starred_file']['tmp_name']), true);
		} else {
			print_error(__('No file uploaded.'));
			return;
		}

		if ($doc) {
			if (isset($doc['items'])) {
				$processed = 0;

				foreach ($doc['items'] as $item) {
//					print_r($item);

					$guid = db_escape_string($this->link, $item['id']);
					$title = db_escape_string($this->link, $item['title']);
					$updated = date('Y-m-d h:i:s', $item['updated']);
					$link = '';
					$content = '';
					$author = db_escape_string($this->link, $item['author']);

					if (is_array($item['alternate'])) {
						foreach ($item['alternate'] as $alt) {
							if (isset($alt['type']) && $alt['type'] == 'text/html') {
								$link = db_escape_string($this->link, $alt['href']);
							}
						}
					}

					if (is_array($item['content'])) {
						$content = db_escape_string($this->link,
							$item['content']['content'], false);
					}

					$processed++;

					$imported += (int) $this->create_article($guid, $title,
						$updated, $link, $content, $author);

				}

				print "<p style='text-align : center'>" . T_sprintf("All done. %d out of %d articles imported.", $imported, $processed) . "</p>";

			} else {
				print_error(__('The document has incorrect format.'));
			}

		} else {
			print_error(__('Error while parsing document.'));
		}

		print "<div align='center'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('starredImportDlg').execute()\">".
			__('Close this window')."</button>";
		print "</div>";

	}

	// expects ESCAPED data
	private function create_article($guid, $title, $updated, $link, $content, $author) {

		$owner_uid = $_SESSION["uid"];

		if (!$guid) $guid = sha1($link);

		$guid = "$owner_uid,$guid";

		$content_hash = sha1($content);

		if (filter_var($link, FILTER_VALIDATE_URL) === FALSE) return false;

		db_query($this->link, "BEGIN");

		// only check for our user data here, others might have shared this with different content etc
		$result = db_query($this->link, "SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
			guid = '$guid' AND ref_id = id AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) == 0) {
			$result = db_query($this->link, "INSERT INTO ttrss_entries
				(title, guid, link, updated, content, content_hash, date_entered, date_updated, author)
				VALUES
				('$title', '$guid', '$link', '$updated', '$content', '$content_hash', NOW(), NOW(), '$author')");

			$result = db_query($this->link, "SELECT id FROM ttrss_entries WHERE guid = '$guid'");

			if (db_num_rows($result) != 0) {
				$ref_id = db_fetch_result($result, 0, "id");

				db_query($this->link, "INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, marked, tag_cache, label_cache,
						last_read, note, unread, last_marked)
					VALUES
					('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false, NOW())");

				if (count($labels) != 0) {
					foreach ($labels as $label) {
						label_add_article($link, $ref_id, trim($label), $owner_uid);
					}
				}

				$rc = true;
			}
		}

		db_query($this->link, "COMMIT");

		return $rc;
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__("Import starred items from Google Reader")."\">";

		print_notice("Your imported articles will appear in Starred and Archived feeds.");

		print "<p>".__("Paste your starred.json into the form below."). "</p>";

		print "<iframe id=\"starred_upload_iframe\"
			name=\"starred_upload_iframe\" onload=\"starredImportComplete(this)\"
			style=\"width: 400px; height: 100px; display: none;\"></iframe>";

		print "<form name=\"starred_form\" style='display : block' target=\"starred_upload_iframe\"
			enctype=\"multipart/form-data\" method=\"POST\"
			action=\"backend.php\">
			<input id=\"starred_file\" name=\"starred_file\" type=\"file\">&nbsp;
			<input type=\"hidden\" name=\"op\" value=\"pluginhandler\">
			<input type=\"hidden\" name=\"method\" value=\"import\">
			<input type=\"hidden\" name=\"plugin\" value=\"greaderstarredimport\">
			<button dojoType=\"dijit.form.Button\" onclick=\"return starredImport();\" type=\"submit\">" .
			__('Import my Starred items') . "</button>";


		print "</div>"; #pane
	}
}
?>
