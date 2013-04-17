<?php
class Article extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("redirect", "editarticletags");

		return array_search($method, $csrf_ignored) !== false;
	}

	function redirect() {
		$id = db_escape_string( $_REQUEST['id']);

		$result = db_query( "SELECT link FROM ttrss_entries, ttrss_user_entries
						WHERE id = '$id' AND id = ref_id AND owner_uid = '".$_SESSION['uid']."'
						LIMIT 1");

		if (db_num_rows($result) == 1) {
			$article_url = db_fetch_result($result, 0, 'link');
			$article_url = str_replace("\n", "", $article_url);

			header("Location: $article_url");
			return;

		} else {
			print_error(__("Article not found."));
		}
	}

	function view() {
		$id = db_escape_string( $_REQUEST["id"]);
		$cids = explode(",", db_escape_string( $_REQUEST["cids"]));
		$mode = db_escape_string( $_REQUEST["mode"]);
		$omode = db_escape_string( $_REQUEST["omode"]);

		// in prefetch mode we only output requested cids, main article
		// just gets marked as read (it already exists in client cache)

		$articles = array();

		if ($mode == "") {
			array_push($articles, format_article( $id, false));
		} else if ($mode == "zoom") {
			array_push($articles, format_article( $id, true, true));
		} else if ($mode == "raw") {
			if ($_REQUEST['html']) {
				header("Content-Type: text/html");
				print '<link rel="stylesheet" type="text/css" href="tt-rss.css"/>';
			}

			$article = format_article( $id, false);
			print $article['content'];
			return;
		}

		$this->catchupArticleById( $id, 0);

		if (!$_SESSION["bw_limit"]) {
			foreach ($cids as $cid) {
				if ($cid) {
					array_push($articles, format_article( $cid, false, false));
				}
			}
		}

		print json_encode($articles);
	}

	private function catchupArticleById( $id, $cmode) {

		if ($cmode == 0) {
			db_query( "UPDATE ttrss_user_entries SET
			unread = false,last_read = NOW()
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		} else if ($cmode == 1) {
			db_query( "UPDATE ttrss_user_entries SET
			unread = true
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		} else {
			db_query( "UPDATE ttrss_user_entries SET
			unread = NOT unread,last_read = NOW()
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}

		$feed_id = getArticleFeed( $id);
		ccache_update( $feed_id, $_SESSION["uid"]);
	}

	static function create_published_article( $title, $url, $content, $labels_str,
			$owner_uid) {

		$guid = 'SHA1:' . sha1("ttshared:" . $url . $owner_uid); // include owner_uid to prevent global GUID clash
		$content_hash = sha1($content);

		if ($labels_str != "") {
			$labels = explode(",", $labels_str);
		} else {
			$labels = array();
		}

		$rc = false;

		if (!$title) $title = $url;
		if (!$title && !$url) return false;

		if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false;

		db_query( "BEGIN");

		// only check for our user data here, others might have shared this with different content etc
		$result = db_query( "SELECT id FROM ttrss_entries, ttrss_user_entries WHERE
			link = '$url' AND ref_id = id AND owner_uid = '$owner_uid' LIMIT 1");

		if (db_num_rows($result) != 0) {
			$ref_id = db_fetch_result($result, 0, "id");

			$result = db_query( "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$ref_id' AND owner_uid = '$owner_uid' LIMIT 1");

			if (db_num_rows($result) != 0) {
				$int_id = db_fetch_result($result, 0, "int_id");

				db_query( "UPDATE ttrss_entries SET
					content = '$content', content_hash = '$content_hash' WHERE id = '$ref_id'");

				db_query( "UPDATE ttrss_user_entries SET published = true,
						last_published = NOW() WHERE
						int_id = '$int_id' AND owner_uid = '$owner_uid'");
			} else {

				db_query( "INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
						last_read, note, unread, last_published)
					VALUES
					('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false, NOW())");
			}

			if (count($labels) != 0) {
				foreach ($labels as $label) {
					label_add_article( $ref_id, trim($label), $owner_uid);
				}
			}

			$rc = true;

		} else {
			$result = db_query( "INSERT INTO ttrss_entries
				(title, guid, link, updated, content, content_hash, date_entered, date_updated)
				VALUES
				('$title', '$guid', '$url', NOW(), '$content', '$content_hash', NOW(), NOW())");

			$result = db_query( "SELECT id FROM ttrss_entries WHERE guid = '$guid'");

			if (db_num_rows($result) != 0) {
				$ref_id = db_fetch_result($result, 0, "id");

				db_query( "INSERT INTO ttrss_user_entries
					(ref_id, uuid, feed_id, orig_feed_id, owner_uid, published, tag_cache, label_cache,
						last_read, note, unread, last_published)
					VALUES
					('$ref_id', '', NULL, NULL, $owner_uid, true, '', '', NOW(), '', false, NOW())");

				if (count($labels) != 0) {
					foreach ($labels as $label) {
						label_add_article( $ref_id, trim($label), $owner_uid);
					}
				}

				$rc = true;
			}
		}

		db_query( "COMMIT");

		return $rc;
	}

	function editArticleTags() {

		print __("Tags for this article (separated by commas):")."<br>";

		$param = db_escape_string( $_REQUEST['param']);

		$tags = get_article_tags( db_escape_string( $param));

		$tags_str = join(", ", $tags);

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$param\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"article\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"setArticleTags\">";

		print "<table width='100%'><tr><td>";

		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" rows='4'
			style='font-size : 12px; width : 100%' id=\"tags_str\"
			name='tags_str'>$tags_str</textarea>
		<div class=\"autocomplete\" id=\"tags_choices\"
				style=\"display:none\"></div>";

		print "</td></tr></table>";

		print "<div class='dlgButtons'>";

		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editTagsDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editTagsDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

	}

	function setScore() {
		$ids = db_escape_string( $_REQUEST['id']);
		$score = (int)db_escape_string( $_REQUEST['score']);

		db_query( "UPDATE ttrss_user_entries SET
			score = '$score' WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

		print json_encode(array("id" => $id,
			"score_pic" => get_score_pic($score)));
	}


	function setArticleTags() {

		$id = db_escape_string( $_REQUEST["id"]);

		$tags_str = db_escape_string( $_REQUEST["tags_str"]);
		$tags = array_unique(trim_array(explode(",", $tags_str)));

		db_query( "BEGIN");

		$result = db_query( "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1");

		if (db_num_rows($result) == 1) {

			$tags_to_cache = array();

			$int_id = db_fetch_result($result, 0, "int_id");

			db_query( "DELETE FROM ttrss_tags WHERE
				post_int_id = $int_id AND owner_uid = '".$_SESSION["uid"]."'");

			foreach ($tags as $tag) {
				$tag = sanitize_tag($tag);

				if (!tag_is_valid($tag)) {
					continue;
				}

				if (preg_match("/^[0-9]*$/", $tag)) {
					continue;
				}

				//					print "<!-- $id : $int_id : $tag -->";

				if ($tag != '') {
					db_query( "INSERT INTO ttrss_tags
								(post_int_id, owner_uid, tag_name) VALUES ('$int_id', '".$_SESSION["uid"]."', '$tag')");
				}

				array_push($tags_to_cache, $tag);
			}

			/* update tag cache */

			sort($tags_to_cache);
			$tags_str = join(",", $tags_to_cache);

			db_query( "UPDATE ttrss_user_entries
				SET tag_cache = '$tags_str' WHERE ref_id = '$id'
						AND owner_uid = " . $_SESSION["uid"]);
		}

		db_query( "COMMIT");

		$tags = get_article_tags( $id);
		$tags_str = format_tags_string($tags, $id);
		$tags_str_full = join(", ", $tags);

		if (!$tags_str_full) $tags_str_full = __("no tags");

		print json_encode(array("id" => (int)$id,
				"content" => $tags_str, "content_full" => $tags_str_full));
	}


	function completeTags() {
		$search = db_escape_string( $_REQUEST["search"]);

		$result = db_query( "SELECT DISTINCT tag_name FROM ttrss_tags
				WHERE owner_uid = '".$_SESSION["uid"]."' AND
				tag_name LIKE '$search%' ORDER BY tag_name
				LIMIT 10");

		print "<ul>";
		while ($line = db_fetch_assoc($result)) {
			print "<li>" . $line["tag_name"] . "</li>";
		}
		print "</ul>";
	}

	function assigntolabel() {
		return $this->labelops(true);
	}

	function removefromlabel() {
		return $this->labelops(false);
	}

	private function labelops($assign) {
		$reply = array();

		$ids = explode(",", db_escape_string( $_REQUEST["ids"]));
		$label_id = db_escape_string( $_REQUEST["lid"]);

		$label = db_escape_string( label_find_caption( $label_id,
		$_SESSION["uid"]));

		$reply["info-for-headlines"] = array();

		if ($label) {

			foreach ($ids as $id) {

				if ($assign)
					label_add_article( $id, $label, $_SESSION["uid"]);
				else
					label_remove_article( $id, $label, $_SESSION["uid"]);

				$labels = get_article_labels( $id, $_SESSION["uid"]);

				array_push($reply["info-for-headlines"],
				array("id" => $id, "labels" => format_article_labels($labels, $id)));

			}
		}

		$reply["message"] = "UPDATE_COUNTERS";

		print json_encode($reply);
	}



}
