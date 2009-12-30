<?php
	function handle_rpc_request($link) {

		$subop = $_REQUEST["subop"];

		if ($subop == "setpref") {
			if (WEB_DEMO_MODE) {
				return;
			}

			print "<rpc-reply>";

			$key = db_escape_string($_REQUEST["key"]);
			$value = db_escape_string($_REQUEST["value"]);

			set_pref($link, $key, $value);

			print "<param-set key=\"$key\" value=\"$value\"/>";

			print "</rpc-reply>";

			return;
		}

		if ($subop == "getAllCounters") {
			print "<rpc-reply>";			
			print "<counters>";

			$omode = $_REQUEST["omode"];

			getAllCounters($link, $omode);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "mark") {
			$mark = $_REQUEST["mark"];
			$id = db_escape_string($_REQUEST["id"]);

			if ($mark == "1") {
				$mark = "true";
			} else {
				$mark = "false";
			}

			// FIXME this needs collision testing

			$result = db_query($link, "UPDATE ttrss_user_entries SET marked = $mark
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			print "<rpc-reply><counters>";
			getGlobalCounters($link);
			getVirtCounters($link);
			getLabelCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters></rpc-reply>";

			return;
		}

		if ($subop == "delete") {
			$ids = db_escape_string($_REQUEST["ids"]);

			$result = db_query($link, "DELETE FROM ttrss_user_entries 				
				WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

			print "<rpc-reply><counters>";
			getGlobalCounters($link);
			getVirtCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters></rpc-reply>";

			return;
		}

		if ($subop == "unarchive") {
			$ids = db_escape_string($_REQUEST["ids"]);

			$result = db_query($link, "UPDATE ttrss_user_entries 
				SET feed_id = orig_feed_id, orig_feed_id = NULL
				WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

			print "<rpc-reply><counters>";
			getGlobalCounters($link);
			getVirtCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters></rpc-reply>";

			return;
		}

		if ($subop == "archive") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				archive_article($link, $id, $_SESSION["uid"]);
			}

			print "<rpc-reply><counters>";
			getGlobalCounters($link);
			getVirtCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters></rpc-reply>";

			return;
		}


		if ($subop == "publ") {
			$pub = $_REQUEST["pub"];
			$id = db_escape_string($_REQUEST["id"]);
			$note = trim(strip_tags(db_escape_string($_REQUEST["note"])));

			if ($pub == "1") {
				$pub = "true";
			} else {
				$pub = "false";
			}

			if ($note != 'undefined') {
				$note_qpart = "note = '$note',";
			}

			// FIXME this needs collision testing

			$result = db_query($link, "UPDATE ttrss_user_entries SET 
				$note_qpart
				published = $pub
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);


			print "<rpc-reply>";
			
			print "<counters>";
			getGlobalCounters($link);
			getVirtCounters($link);
			getLabelCounters($link);
			if (get_pref($link, 'ENABLE_FEED_CATS')) {
				getCategoryCounters($link);
			}
			print "</counters>";

			if ($note != 'undefined') {
				$note_size = strlen($note);
				print "<note id=\"$id\" size=\"$note_size\">";
				print "<![CDATA[" . format_article_note($id, $note) . "]]>";
				print "</note>";
			}

			print "</rpc-reply>";

			return;
		}

		if ($subop == "updateFeed") {
			$feed_id = db_escape_string($_REQUEST["feed"]);

			$result = db_query($link, 
				"SELECT feed_url FROM ttrss_feeds WHERE id = '$feed_id'
					AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) > 0) {			
				$feed_url = db_fetch_result($result, 0, "feed_url");
				update_rss_feed($link, $feed_url, $feed_id);
			}

			print "<rpc-reply>";	
			print "<counters>";
			getFeedCounter($link, $feed_id);
			print "</counters>";
			print "</rpc-reply>";
			
			return;
		}

		if ($subop == "forceUpdateAllFeeds" || $subop == "updateAllFeeds") {
	
			$global_unread_caller = sprintf("%d", $_REQUEST["uctr"]);
			$global_unread = getGlobalUnread($link);

			print "<rpc-reply>";

			print "<counters>";

			if ($global_unread_caller != $global_unread) {

	 			$omode = $_REQUEST["omode"];
	 
	 			if (!$omode) $omode = "tflc";

				getVirtCounters($link);

	 			if (strchr($omode, "l")) getLabelCounters($link);

	 			if (strchr($omode, "c")) {			
		 			if (get_pref($link, 'ENABLE_FEED_CATS')) {
		 				getCategoryCounters($link);
		 			}
				}

	 			if (strchr($omode, "f")) getFeedCounters($link);
				if (strchr($omode, "t")) getTagCounters($link);

				getGlobalCounters($link, $global_unread);
			}
 
			print "</counters>";

			print_runtime_info($link);

			print "</rpc-reply>";

			return;
		}

		/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
		if ($subop == "catchupSelected") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			catchupArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link, $_REQUEST["omode"]);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "markSelected") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			markArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link, $_REQUEST["omode"]);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "publishSelected") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			publishArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link, $_REQUEST["omode"]);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "sanityCheck") {
			print "<rpc-reply>";
			if (sanity_check($link)) {
				print "<error error-code=\"0\"/>";
				print_init_params($link);
				print_runtime_info($link);

				# assign client-passed params to session
				$_SESSION["client.userAgent"] = $_REQUEST["ua"];

			}
			print "</rpc-reply>";

			return;
		}		

		if ($subop == "globalPurge") {

			print "<rpc-reply>";
			global_purge_old_posts($link, true);
			print "</rpc-reply>";

			return;
		}

		if ($subop == "getArticleLink") {

			$id = db_escape_string($_REQUEST["id"]);

			$result = db_query($link, "SELECT link FROM ttrss_entries, ttrss_user_entries
				WHERE id = '$id' AND id = ref_id AND owner_uid = '".$_SESSION['uid']."'");

			if (db_num_rows($result) == 1) {
				$link = htmlspecialchars(strip_tags(db_fetch_result($result, 0, "link")));
				print "<rpc-reply><link>$link</link><id>$id</id></rpc-reply>";
			} else {
				print "<rpc-reply><error>Article not found</error></rpc-reply>";
			}

			return;
		}

		if ($subop == "setArticleTags") {

			$id = db_escape_string($_REQUEST["id"]);

			$tags_str = db_escape_string($_REQUEST["tags_str"]);

			$tags = array_unique(trim_array(split(",", $tags_str)));

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1");

			if (db_num_rows($result) == 1) {

				$int_id = db_fetch_result($result, 0, "int_id");

				db_query($link, "DELETE FROM ttrss_tags WHERE 
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
						db_query($link, "INSERT INTO ttrss_tags 
							(post_int_id, owner_uid, tag_name) VALUES ('$int_id', '".$_SESSION["uid"]."', '$tag')");
					}
				}
			}

			db_query($link, "COMMIT");

			$tags_str = format_tags_string(get_article_tags($link, $id), $id);

			print "<rpc-reply>
				<tags-str id=\"$id\"><![CDATA[$tags_str]]></tags-str>
				</rpc-reply>";

			return;
		}

		if ($subop == "regenPubKey") {

			print "<rpc-reply>";

			set_pref($link, "_PREFS_PUBLISH_KEY", generate_publish_key());

			$new_link = article_publish_url($link);		

			print "<link><![CDATA[$new_link]]></link>";

			print "</rpc-reply>";

			return;
		}

		if ($subop == "logout") {
			logout_user();
			print_error_xml(6);
			return;
		}

		if ($subop == "completeTags") {

			$search = db_escape_string($_REQUEST["search"]);

			$result = db_query($link, "SELECT DISTINCT tag_name FROM ttrss_tags 
				WHERE owner_uid = '".$_SESSION["uid"]."' AND
			  	tag_name LIKE '$search%' ORDER BY tag_name
				LIMIT 10");

			print "<ul>";
			while ($line = db_fetch_assoc($result)) {
				print "<li>" . $line["tag_name"] . "</li>";
			}
			print "</ul>";

			return;
		}

		if ($subop == "purge") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$days = sprintf("%d", $_REQUEST["days"]);

			print "<rpc-reply>";

			print "<message><![CDATA[";

			foreach ($ids as $id) {

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
					id = '$id' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 1) {
					purge_feed($link, $id, $days, true);
				}
			}

			print "]]></message>";

			print "</rpc-reply>";

			return;
		}

/*		if ($subop == "setScore") {
			$id = db_escape_string($_REQUEST["id"]);
			$score = sprintf("%d", $_REQUEST["score"]);

			$result = db_query($link, "UPDATE ttrss_user_entries SET score = '$score'
				WHERE ref_id = '$id' AND owner_uid = ".$_SESSION["uid"]);

			print "<rpc-reply><message>Acknowledged.</message></rpc-reply>";

			return;

		} */

		if ($subop == "getArticles") {
			$ids = split(",", db_escape_string($_REQUEST["ids"]));

			print "<rpc-reply>";

			foreach ($ids as $id) {
				if ($id) {
					outputArticleXML($link, $id, 0, false);
				}
			}
			print "</rpc-reply>";

			return;
		}

		if ($subop == "checkDate") {

			$date = db_escape_string($_REQUEST["date"]);
			$date_parsed = strtotime($date);

			print "<rpc-reply>";

			if ($date_parsed) {
				print "<result>1</result>";
			} else {
				print "<result>0</result>";
			}

			print "</rpc-reply>";

			return;
		}

		if ($subop == "removeFromLabel") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$label_id = db_escape_string($_REQUEST["lid"]);

			$label = db_escape_string(label_find_caption($link, $label_id, 
				$_SESSION["uid"]));

			print "<rpc-reply>";
			print "<info-for-headlines>";

			if ($label) {

				foreach ($ids as $id) {
					label_remove_article($link, $id, $label, $_SESSION["uid"]);

					print "<entry id=\"$id\"><![CDATA[";

					$labels = get_article_labels($link, $id, $_SESSION["uid"]);
					print format_article_labels($labels, $id);

					print "]]></entry>";

				}
			}

			print "</info-for-headlines>";

			print "<counters>";
			getAllCounters($link, $omode);
			print "</counters>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "assignToLabel") {

			$ids = split(",", db_escape_string($_REQUEST["ids"]));
			$label_id = db_escape_string($_REQUEST["lid"]);

			$label = db_escape_string(label_find_caption($link, $label_id, 
				$_SESSION["uid"]));

			print "<rpc-reply>";			

			print "<info-for-headlines>";

			if ($label) {

				foreach ($ids as $id) {
					label_add_article($link, $id, $label, $_SESSION["uid"]);

					print "<entry id=\"$id\"><![CDATA[";

					$labels = get_article_labels($link, $id, $_SESSION["uid"]);
					print format_article_labels($labels, $id);

					print "]]></entry>";

				}
			}

			print "</info-for-headlines>";

			print "<counters>";
			getAllCounters($link, $omode);
			print "</counters>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "feedBrowser") {

			$search = db_escape_string($_REQUEST["search"]);
			$limit = db_escape_string($_REQUEST["limit"]);

			print "<rpc-reply>";
			print "<content>";
			print "<![CDATA[";
			$ctr = print_feed_browser($link, $search, $limit);
			print "]]>";
			print "</content>";
			print "<num-results value=\"$ctr\"/>";
			print "</rpc-reply>";

			return;
		}

		if ($subop == "download") {
			$stage = (int) $_REQUEST["stage"];
			$cidt = (int)db_escape_string($_REQUEST["cidt"]);
			$cidb = (int)db_escape_string($_REQUEST["cidb"]);
			$sync = db_escape_string($_REQUEST["sync"]);
			//$amount = (int) $_REQUEST["amount"];
			//$unread_only = db_escape_string($_REQUEST["unread_only"]);
			//if (!$amount) $amount = 50;

			/* Amount is not used by the frontend offline.js anymore, it goes by
			 * date_qpart below + cidb/cidt IDs */

			$amount = 2000;
			$unread_only = true;

			print "<rpc-reply>";

			$sync = split(";", $sync);

			print "<sync>";

			if (count($sync) > 0) {
				if (strtotime($sync[0])) {
					$last_online = db_escape_string($sync[0]);

					print "<sync-point><![CDATA[$last_online]]></sync-point>";
					
					for ($i = 1; $i < count($sync); $i++) {
						$e = split(",", $sync[$i]);

						if (count($e) == 3) {

							$id = (int) $e[0];
							$unread = bool_to_sql_bool((bool) $e[1]);
							$marked = (bool)$e[2];

							if ($marked) {
								$marked = bool_to_sql_bool($marked);
								$marked_qpart = "marked = $marked,";
							}

							$query = "UPDATE ttrss_user_entries SET 
								$marked_qpart
								unread = $unread, 
								last_read = '$last_online' 
							WHERE ref_id = '$id' AND 
								(last_read IS NULL OR last_read < '$last_online') AND
								owner_uid = ".$_SESSION["uid"];

							$result = db_query($link, $query);

							print "<sync-ok id=\"$id\"/>";

						}
					}

					/* Maybe we need to further update local DB for this client */

					$query = "SELECT ref_id,unread,marked FROM ttrss_user_entries
						WHERE last_read >= '$last_online' AND
								owner_uid = ".$_SESSION["uid"] . " LIMIT 1000";

					$result = db_query($link, $query);

					while ($line = db_fetch_assoc($result)) {
						$unread = (int) sql_bool_to_bool($line["unread"]);
						$marked = (int) sql_bool_to_bool($line["marked"]);

						print "<sync-ok unread=\"$unread\" marked=\"$marked\" 
							id=\"".$line["ref_id"]."\"/>";
					}

				}
			}

			print "</sync>";

			if ($stage == 0) {
				print "<feeds>";

				$result = db_query($link, "SELECT id, title, cat_id FROM
					ttrss_feeds WHERE hidden = false AND owner_uid = ".$_SESSION["uid"]);

				while ($line = db_fetch_assoc($result)) {

					$has_icon = (int) feed_has_icon($line["id"]);

					print "<feed has_icon=\"$has_icon\" 
						cat_id=\"".(int)$line["cat_id"]."\" id=\"".$line["id"]."\"><![CDATA[";
					print $line["title"];
					print "]]></feed>";
				}

				print "</feeds>";

				print "<feed-categories>";

				$result = db_query($link, "SELECT id, title, collapsed FROM
					ttrss_feed_categories WHERE owner_uid = ".$_SESSION["uid"]);

					print "<category id=\"0\" collapsed=\"".
						(int)get_pref($link, "_COLLAPSED_UNCAT")."\"><![CDATA[";
					print __("Uncategorized");
					print "]]></category>";

					print "<category id=\"-1\" collapsed=\"".
						(int)get_pref($link, "_COLLAPSED_SPECIAL")."\"><![CDATA[";
					print __("Special");
					print "]]></category>";

					print "<category id=\"-2\" collapsed=\"".
						(int)get_pref($link, "_COLLAPSED_LABELS")."\"><![CDATA[";
					print __("Labels");
					print "]]></category>";

				while ($line = db_fetch_assoc($result)) {
					print "<category 
						id=\"".$line["id"]."\"
						collapsed=\"".(int)sql_bool_to_bool($line["collapsed"])."\"><![CDATA[";
					print $line["title"];
					print "]]></category>";
				}

				print "</feed-categories>";

				print "<labels>";

				$result = db_query($link, "SELECT * FROM
					ttrss_labels2 WHERE owner_uid = ".$_SESSION["uid"]);

				while ($line = db_fetch_assoc($result)) {
					print "<label
						id=\"".$line["id"]."\"
						fg_color=\"".$line["fg_color"]."\"
						bg_color=\"".$line["bg_color"]."\"
						><![CDATA[";
					print $line["caption"];
					print "]]></label>";
				}


				print "</labels>";

			}

			if ($stage > 0) {
				print "<articles>";

				$limit = 10;
				$skip = $limit*($stage-1);

				print "<limit value=\"$limit\"/>";

				if ($amount > 0) $amount -= $skip;

				if ($amount > 0) {

					$limit = min($limit, $amount);

					if ($unread_only) {
						$unread_qpart = "(unread = true OR marked = true) AND ";
					}

					if ($cidt && $cidb) {
						$cid_qpart =  "(ttrss_entries.id > $cidt OR ttrss_entries.id < $cidb) AND ";
					}

					if (DB_TYPE == "pgsql") {
						$date_qpart = "updated >= NOW() - INTERVAL '1 week' AND";
					} else {
						$date_qpart = "updated >= DATE_SUB(NOW(), INTERVAL 1 WEEK) AND";
					}			

					$result = db_query($link,
						"SELECT DISTINCT ttrss_entries.id,ttrss_entries.title,
							guid,link,comments,
							feed_id,content,updated,unread,marked FROM
							ttrss_user_entries,ttrss_entries,ttrss_feeds
						WHERE $unread_qpart $cid_qpart $date_qpart
							hidden = false AND
							ttrss_feeds.id = feed_id AND
							ref_id = ttrss_entries.id AND 
							ttrss_user_entries.owner_uid = ".$_SESSION["uid"]."
							ORDER BY updated DESC LIMIT $limit OFFSET $skip");

					if (function_exists('json_encode')) {

						while ($line = db_fetch_assoc($result)) {
							print "<article><![CDATA[";
	
							$line["marked"] = (int)sql_bool_to_bool($line["marked"]);
							$line["unread"] = (int)sql_bool_to_bool($line["unread"]);

							$line["labels"] = get_article_labels($link, $line["id"]);

//							too slow :(							
//							$line["tags"] = format_tags_string(
//								get_article_tags($link, $line["id"]), $line["id"]);
	
							print json_encode($line);
							print "]]></article>";
						}	
					}

				}

				print "</articles>";

			}

			print "</rpc-reply>";

			return;
		}

		print "<rpc-reply><error>Unknown method: $subop</error></rpc-reply>";
	}
?>
