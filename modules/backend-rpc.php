<?php
	function handle_rpc_request($link) {

		$subop = $_GET["subop"];

		if ($subop == "setpref") {
			if (WEB_DEMO_MODE) {
				return;
			}

			print "<rpc-reply>";

			$key = db_escape_string($_GET["key"]);
			$value = db_escape_string($_GET["value"]);

			set_pref($link, $key, $value);

			print "<param-set key=\"$key\" value=\"$value\"/>";

			print "</rpc-reply>";

		}

		if ($subop == "getLabelCounters") {
			$aid = $_GET["aid"];		
			print "<rpc-reply>";
			print "<counters>";
			getLabelCounters($link);
			if ($aid) {
				getFeedCounter($link, $aid);
			}
			print "</counters>";
			print "</rpc-reply>";
		}

		if ($subop == "getFeedCounters") {
			print "<rpc-reply>";
			print "<counters>";
			getFeedCounters($link);
			print "</counters>";
			print "</rpc-reply>";
		}

		if ($subop == "getAllCounters") {
			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";
		}

		if ($subop == "mark") {
			$mark = $_GET["mark"];
			$id = db_escape_string($_GET["id"]);

			if ($mark == "1") {
				$mark = "true";
			} else {
				$mark = "false";
			}

			// FIXME this needs collision testing

			$result = db_query($link, "UPDATE ttrss_user_entries SET marked = $mark
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);
		}

		if ($subop == "updateFeed") {
			$feed_id = db_escape_string($_GET["feed"]);

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
	
			if (ENABLE_UPDATE_DAEMON) {

				if ($subop == "forceUpdateAllFeeds") {

					$result = db_query($link, "SELECT count(id) AS cid FROM
						ttrss_scheduled_updates WHERE feed_id IS NULL AND
							owner_uid = " . $_SESSION["uid"]);
	
					$cid = db_fetch_result($result, 0, "cid");

					if ($cid == 0) {
	
						db_query($link, "INSERT INTO ttrss_scheduled_updates
							(owner_uid, feed_id, entered) VALUES
							(".$_SESSION["uid"].", NULL, NOW())");
					
					}
				}
				
			} else {	
				update_all_feeds($link, $subop == "forceUpdateAllFeeds");
			}

			$global_unread_caller = sprintf("%d", $_GET["uctr"]);
			$global_unread = getGlobalUnread($link);

			print "<rpc-reply>";

			print "<counters>";

			if ($global_unread_caller != $global_unread) {

	 			$omode = $_GET["omode"];
	 
	 			if (!$omode) $omode = "tflc";
	 
	 			if (strchr($omode, "l")) getLabelCounters($link);
	 			if (strchr($omode, "f")) getFeedCounters($link);
	 			if (strchr($omode, "t")) getTagCounters($link);
	 			if (strchr($omode, "c")) {			
		 			if (get_pref($link, 'ENABLE_FEED_CATS')) {
		 				getCategoryCounters($link);
		 			}
				}
			}

 			getGlobalCounters($link, $global_unread);

			print "</counters>";

			print_runtime_info($link);

			print "</rpc-reply>";

		}

		/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
		if ($subop == "catchupSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			catchupArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";
		}

		if ($subop == "markSelected") {

			$ids = split(",", db_escape_string($_GET["ids"]));
			$cmode = sprintf("%d", $_GET["cmode"]);

			markArticlesById($link, $ids, $cmode);

			print "<rpc-reply>";
			print "<counters>";
			getAllCounters($link);
			print "</counters>";
			print_runtime_info($link);
			print "</rpc-reply>";
		}

		if ($subop == "sanityCheck") {
			print "<rpc-reply>";
			if (sanity_check($link)) {
				print "<error error-code=\"0\"/>";
				print_init_params($link);
				print_runtime_info($link);
			}
			print "</rpc-reply>";
		}		

		if ($subop == "globalPurge") {

			print "<rpc-reply>";
			global_purge_old_posts($link, true);
			print "</rpc-reply>";

		}

		if ($subop == "storeParam") {
			$key = $_GET["key"];
			$value = $_GET["value"];
			$_SESSION["stored-params"][$key] = $value;
			print "<rpc-reply>
				<message>$key : $value</message>
				</rpc-reply>";
		}

		if ($subop == "setArticleTags") {
			$id = db_escape_string($_GET["id"]);
			$tags_str = db_escape_string($_GET["tags_str"]);

			$tags = split(",", $tags_str);

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1");

			if (db_num_rows($result) == 1) {

				$int_id = db_fetch_result($result, 0, "int_id");

				db_query($link, "DELETE FROM ttrss_tags WHERE 
					post_int_id = $int_id AND owner_uid = '".$_SESSION["uid"]."'");

				foreach ($tags as $tag) {
					$tag = trim($tag);

					if (preg_match("/^[0-9]*$/", $tag)) {
						continue;
					}
					
					if ($tag != '') {
						db_query($link, "INSERT INTO ttrss_tags 
							(post_int_id, owner_uid, tag_name) VALUES ('$int_id', '".$_SESSION["uid"]."', '$tag')");
					}
				}
			}

			db_query($link, "COMMIT");

			print "<rpc-reply>
				<message>$id</message>
				</rpc-reply>";

		}
	}
?>
