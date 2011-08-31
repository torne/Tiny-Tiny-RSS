<?php
	function handle_rpc_request($link) {

		$subop = $_REQUEST["subop"];
		$seq = (int) $_REQUEST["seq"];

		// Silent
		if ($subop == "setprofile") {
			$id = db_escape_string($_REQUEST["id"]);

			$_SESSION["profile"] = $id;
			$_SESSION["prefs_cache"] = array();
			return;
		}

		// Silent
		if ($subop == "remprofiles") {
			$ids = explode(",", db_escape_string(trim($_REQUEST["ids"])));

			foreach ($ids as $id) {
				if ($_SESSION["profile"] != $id) {
					db_query($link, "DELETE FROM ttrss_settings_profiles WHERE id = '$id' AND
						owner_uid = " . $_SESSION["uid"]);
				}
			}
			return;
		}

		// Silent
		if ($subop == "addprofile") {
			$title = db_escape_string(trim($_REQUEST["title"]));
			if ($title) {
				db_query($link, "BEGIN");

				$result = db_query($link, "SELECT id FROM ttrss_settings_profiles
					WHERE title = '$title' AND owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {

					db_query($link, "INSERT INTO ttrss_settings_profiles (title, owner_uid)
						VALUES ('$title', ".$_SESSION["uid"] .")");

					$result = db_query($link, "SELECT id FROM ttrss_settings_profiles WHERE
						title = '$title'");

					if (db_num_rows($result) != 0) {
						$profile_id = db_fetch_result($result, 0, "id");

						if ($profile_id) {
							initialize_user_prefs($link, $_SESSION["uid"], $profile_id);
						}
					}
				}

				db_query($link, "COMMIT");
			}
			return;
		}

		// Silent
		if ($subop == "saveprofile") {
			$id = db_escape_string($_REQUEST["id"]);
			$title = db_escape_string(trim($_REQUEST["value"]));

			if ($id == 0) {
				print __("Default profile");
				return;
			}

			if ($title) {
				db_query($link, "BEGIN");

				$result = db_query($link, "SELECT id FROM ttrss_settings_profiles
					WHERE title = '$title' AND owner_uid =" . $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					db_query($link, "UPDATE ttrss_settings_profiles
						SET title = '$title' WHERE id = '$id' AND
						owner_uid = " . $_SESSION["uid"]);
					print $title;
				} else {
					$result = db_query($link, "SELECT title FROM ttrss_settings_profiles
						WHERE id = '$id' AND owner_uid =" . $_SESSION["uid"]);
					print db_fetch_result($result, 0, "title");
				}

				db_query($link, "COMMIT");
			}
			return;
		}

		// Silent
		if ($subop == "remarchive") {
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				$result = db_query($link, "DELETE FROM ttrss_archived_feeds WHERE
					(SELECT COUNT(*) FROM ttrss_user_entries
						WHERE orig_feed_id = '$id') = 0 AND
						id = '$id' AND owner_uid = ".$_SESSION["uid"]);

				$rc = db_affected_rows($link, $result);
			}
			return;
		}

		if ($subop == "addfeed") {
			$feed = db_escape_string($_REQUEST['feed']);
			$cat = db_escape_string($_REQUEST['cat']);
			$login = db_escape_string($_REQUEST['login']);
			$pass = db_escape_string($_REQUEST['pass']);

			$rc = subscribe_to_feed($link, $feed, $cat, $login, $pass);

			print json_encode(array("result" => $rc));

			return;

		}

		if ($subop == "extractfeedurls") {

			$urls = get_feeds_from_html($_REQUEST['url']);

			print json_encode(array("urls" => $urls));
			return;
		}

		if ($subop == "togglepref") {
			$key = db_escape_string($_REQUEST["key"]);
			set_pref($link, $key, !get_pref($link, $key));
			$value = get_pref($link, $key);

			print json_encode(array("param" =>$key, "value" => $value));
			return;
		}

		if ($subop == "setpref") {
			$value = str_replace("\n", "<br/>", $_REQUEST['value']);

			$key = db_escape_string($_REQUEST["key"]);
			$value = db_escape_string($value);

			set_pref($link, $key, $value);

			print json_encode(array("param" =>$key, "value" => $value));
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

			$result = db_query($link, "UPDATE ttrss_user_entries SET marked = $mark
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "delete") {
			$ids = db_escape_string($_REQUEST["ids"]);

			$result = db_query($link, "DELETE FROM ttrss_user_entries
				WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "unarchive") {
			$ids = db_escape_string($_REQUEST["ids"]);

			$result = db_query($link, "UPDATE ttrss_user_entries
				SET feed_id = orig_feed_id, orig_feed_id = NULL
				WHERE ref_id IN ($ids) AND owner_uid = " . $_SESSION["uid"]);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "archive") {
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));

			foreach ($ids as $id) {
				archive_article($link, $id, $_SESSION["uid"]);
			}

			print json_encode(array("message" => "UPDATE_COUNTERS"));
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

			$result = db_query($link, "UPDATE ttrss_user_entries SET
				published = $pub
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			$pubsub_result = false;

			if (PUBSUBHUBBUB_HUB) {
				$rss_link = get_self_url_prefix() .
					"/backend.php?op=rss&id=-2&key=" .
					get_feed_access_key($link, -2, false);

				$p = new Publisher(PUBSUBHUBBUB_HUB);

				$pubsub_result = $p->publish_update($rss_link);
			}

			print json_encode(array("message" => "UPDATE_COUNTERS",
				"pubsub_result" => $pubsub_result));
			return;
		}

		// Silent
		/* if ($subop == "update") {
			$feed_id = db_escape_string($_REQUEST["feed"]);
			update_rss_feed($link, $feed_id);
			return;
		} */

		if ($subop == "updateAllFeeds" || $subop == "getAllCounters") {
			$last_article_id = (int) $_REQUEST["last_article_id"];

			$reply = array();

			if ($seq) $reply['seq'] = $seq;

			if ($last_article_id != getLastArticleId($link)) {
				$omode = $_REQUEST["omode"];

				if ($omode != "T")
					$reply['counters'] = getAllCounters($link, $omode);
				else
					$reply['counters'] = getGlobalCounters($link);
			}

			$reply['runtime-info'] = make_runtime_info($link);


			print json_encode($reply);
			return;
		}

		/* GET["cmode"] = 0 - mark as read, 1 - as unread, 2 - toggle */
		if ($subop == "catchupSelected") {
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			catchupArticlesById($link, $ids, $cmode);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "markSelected") {
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			markArticlesById($link, $ids, $cmode);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "publishSelected") {
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));
			$cmode = sprintf("%d", $_REQUEST["cmode"]);

			publishArticlesById($link, $ids, $cmode);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "sanityCheck") {
			$_SESSION["hasAudio"] = $_REQUEST["hasAudio"] === "true";

			$reply = array();

			$reply['error'] = sanity_check($link);

			if ($reply['error']['code'] == 0) {
				$reply['init-params'] = make_init_params($link);
				$reply['runtime-info'] = make_runtime_info($link);
			}

			print json_encode($reply);
			return;
		}

/*		if ($subop == "globalPurge") {

			print "<rpc-reply>";
			global_purge_old_posts($link, true);
			print "</rpc-reply>";

			return;
		} */

		if ($subop == "setArticleTags") {
			global $memcache;

			$id = db_escape_string($_REQUEST["id"]);

			$tags_str = db_escape_string($_REQUEST["tags_str"]);
			$tags = array_unique(trim_array(explode(",", $tags_str)));

			db_query($link, "BEGIN");

			$result = db_query($link, "SELECT int_id FROM ttrss_user_entries WHERE
				ref_id = '$id' AND owner_uid = '".$_SESSION["uid"]."' LIMIT 1");

			if (db_num_rows($result) == 1) {

				$tags_to_cache = array();

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

					array_push($tags_to_cache, $tag);
				}

				/* update tag cache */

				sort($tags_to_cache);
				$tags_str = join(",", $tags_to_cache);

				db_query($link, "UPDATE ttrss_user_entries
					SET tag_cache = '$tags_str' WHERE ref_id = '$id'
					AND owner_uid = " . $_SESSION["uid"]);
			}

			db_query($link, "COMMIT");

			if ($memcache) {
				$obj_id = md5("TAGS:".$_SESSION["uid"].":$id");
				$memcache->delete($obj_id);
			}

			$tags = get_article_tags($link, $id);
			$tags_str = format_tags_string($tags, $id);
			$tags_str_full = join(", ", $tags);

			if (!$tags_str_full) $tags_str_full = __("no tags");

			print json_encode(array("tags_str" => array("id" => $id,
				"content" => $tags_str, "content_full" => $tags_str_full)));

			return;
		}

		if ($subop == "regenOPMLKey") {
			update_feed_access_key($link, 'OPML:Publish',
				false, $_SESSION["uid"]);

			$new_link = opml_publish_url($link);

			print json_encode(array("link" => $new_link));
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
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));
			$days = sprintf("%d", $_REQUEST["days"]);

			foreach ($ids as $id) {

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
					id = '$id' AND owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) == 1) {
					purge_feed($link, $id, $days);
				}
			}

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
			$ids = explode(",", db_escape_string($_REQUEST["ids"]));
			$articles = array();

			foreach ($ids as $id) {
				if ($id) {
					array_push($articles, format_article($link, $id, 0, false));
				}
			}

			print json_encode($articles);
			return;
		}

		if ($subop == "checkDate") {
			$date = db_escape_string($_REQUEST["date"]);
			$date_parsed = strtotime($date);

			print json_encode(array("result" => (bool)$date_parsed,
				"date" => date("c", $date_parsed)));
			return;
		}

		if ($subop == "assignToLabel" || $subop == "removeFromLabel") {
			$reply = array();

			$ids = explode(",", db_escape_string($_REQUEST["ids"]));
			$label_id = db_escape_string($_REQUEST["lid"]);

			$label = db_escape_string(label_find_caption($link, $label_id,
				$_SESSION["uid"]));

			$reply["info-for-headlines"] = array();

			if ($label) {

				foreach ($ids as $id) {

					if ($subop == "assignToLabel")
						label_add_article($link, $id, $label, $_SESSION["uid"]);
					else
						label_remove_article($link, $id, $label, $_SESSION["uid"]);

					$labels = get_article_labels($link, $id, $_SESSION["uid"]);

					array_push($reply["info-for-headlines"],
					  array("id" => $id, "labels" => format_article_labels($labels, $id)));

				}
			}

			$reply["message"] = "UPDATE_COUNTERS";

			print json_encode($reply);

			return;
		}

		if ($subop == "updateFeedBrowser") {
			$search = db_escape_string($_REQUEST["search"]);
			$limit = db_escape_string($_REQUEST["limit"]);
			$mode = (int) db_escape_string($_REQUEST["mode"]);

			print json_encode(array("content" =>
				make_feed_browser($link, $search, $limit, $mode),
				"mode" => $mode));
			return;
		}

		// Silent
		if ($subop == "massSubscribe") {

			$payload = json_decode($_REQUEST["payload"], false);
			$mode = $_REQUEST["mode"];

			if (!$payload || !is_array($payload)) return;

			if ($mode == 1) {
				foreach ($payload as $feed) {

					$title = db_escape_string($feed[0]);
					$feed_url = db_escape_string($feed[1]);

					$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

					if (db_num_rows($result) == 0) {
						$result = db_query($link, "INSERT INTO ttrss_feeds
								(owner_uid,feed_url,title,cat_id,site_url)
							VALUES ('".$_SESSION["uid"]."',
								'$feed_url', '$title', NULL, '')");
					}
				}
			} else if ($mode == 2) {
				// feed archive
				foreach ($payload as $id) {
					$result = db_query($link, "SELECT * FROM ttrss_archived_feeds
						WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);

					if (db_num_rows($result) != 0) {
						$site_url = db_escape_string(db_fetch_result($result, 0, "site_url"));
						$feed_url = db_escape_string(db_fetch_result($result, 0, "feed_url"));
						$title = db_escape_string(db_fetch_result($result, 0, "title"));

						$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
							feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

						if (db_num_rows($result) == 0) {
							$result = db_query($link, "INSERT INTO ttrss_feeds
									(owner_uid,feed_url,title,cat_id,site_url)
								VALUES ('$id','".$_SESSION["uid"]."',
									'$feed_url', '$title', NULL, '$site_url')");
						}
					}
				}
			}

/*			$ids = explode(",", db_escape_string($_REQUEST["ids"]));

			$subscribed = array();

			foreach ($ids as $id) {

				if ($mode == 1) {
					$result = db_query($link, "SELECT feed_url,title FROM ttrss_feeds
						WHERE id = '$id'");
				} else if ($mode == 2) {
					$result = db_query($link, "SELECT * FROM ttrss_archived_feeds
						WHERE id = '$id' AND owner_uid = " . $_SESSION["uid"]);
					$orig_id = db_escape_string(db_fetch_result($result, 0, "id"));
					$site_url = db_escape_string(db_fetch_result($result, 0, "site_url"));
				}

				$feed_url = db_escape_string(db_fetch_result($result, 0, "feed_url"));
				$title = db_escape_string(db_fetch_result($result, 0, "title"));

				$title_orig = db_fetch_result($result, 0, "title");

				$result = db_query($link, "SELECT id FROM ttrss_feeds WHERE
						feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				if (db_num_rows($result) == 0) {
					if ($mode == 1) {
						$result = db_query($link,
							"INSERT INTO ttrss_feeds (owner_uid,feed_url,title,cat_id)
							VALUES ('".$_SESSION["uid"]."', '$feed_url', '$title', NULL)");
					} else if ($mode == 2) {
						$result = db_query($link,
							"INSERT INTO ttrss_feeds (id,owner_uid,feed_url,title,cat_id,site_url)
							VALUES ('$orig_id','".$_SESSION["uid"]."', '$feed_url', '$title', NULL, '$site_url')");
					}
					array_push($subscribed, $title_orig);
				}
			} */

			return;
		}

		if ($subop == "digest-get-contents") {
			$article_id = db_escape_string($_REQUEST['article_id']);

			$result = db_query($link, "SELECT content
				FROM ttrss_entries, ttrss_user_entries
				WHERE id = '$article_id' AND ref_id = id AND owner_uid = ".$_SESSION['uid']);

			$content = sanitize_rss($link, db_fetch_result($result, 0, "content"));

			print json_encode(array("article" =>
				array("id" => $id, "content" => $content)));
			return;
		}

		if ($subop == "digest-update") {
			$feed_id = db_escape_string($_REQUEST['feed_id']);
			$offset = db_escape_string($_REQUEST['offset']);
			$seq = db_escape_string($_REQUEST['seq']);

			if (!$feed_id) $feed_id = -4;
			if (!$offset) $offset = 0;

			$reply = array();

			$reply['seq'] = $seq;

			$headlines = api_get_headlines($link, $feed_id, 10, $offset,
				'', ($feed_id == -4), true, false, "unread", "updated DESC");

			//function api_get_headlines($link, $feed_id, $limit, $offset,
			//		$filter, $is_cat, $show_excerpt, $show_content, $view_mode) {

			$reply['headlines'] = array();
			$reply['headlines']['title'] = getFeedTitle($link, $feed_id);
			$reply['headlines']['content'] = $headlines;

			print json_encode($reply);
			return;
		}

		if ($subop == "digest-init") {
			$tmp_feeds = api_get_feeds($link, -3, true, false, 0);

			$feeds = array();

			foreach ($tmp_feeds as $f) {
				if ($f['id'] > 0 || $f['id'] == -4) array_push($feeds, $f);
			}

			print json_encode(array("feeds" => $feeds));

			return;
		}

		if ($subop == "catchupFeed") {
			$feed_id = db_escape_string($_REQUEST['feed_id']);
			$is_cat = db_escape_string($_REQUEST['is_cat']) == "true";

			catchup_feed($link, $feed_id, $is_cat);

			print json_encode(array("message" => "UPDATE_COUNTERS"));
			return;
		}

		if ($subop == "sendEmail") {
			$secretkey = $_REQUEST['secretkey'];

			$reply = array();

			if (DIGEST_ENABLE && $_SESSION['email_secretkey'] &&
						$secretkey == $_SESSION['email_secretkey']) {

				$_SESSION['email_secretkey'] = '';

				$destination = $_REQUEST['destination'];
				$subject = $_REQUEST['subject'];
				$content = $_REQUEST['content'];

				$replyto = strip_tags($_SESSION['email_replyto']);
				$fromname = strip_tags($_SESSION['email_fromname']);

				$mail = new PHPMailer();

				$mail->PluginDir = "lib/phpmailer/";
				$mail->SetLanguage("en", "lib/phpmailer/language/");

				$mail->CharSet = "UTF-8";

				$mail->From = $replyto;
				$mail->FromName = $fromname;
				$mail->AddAddress($destination);

				if (DIGEST_SMTP_HOST) {
					$mail->Host = DIGEST_SMTP_HOST;
					$mail->Mailer = "smtp";
					$mail->SMTPAuth = DIGEST_SMTP_LOGIN != '';
					$mail->Username = DIGEST_SMTP_LOGIN;
					$mail->Password = DIGEST_SMTP_PASSWORD;
				}

				$mail->IsHTML(false);
				$mail->Subject = $subject;
				$mail->Body = $content;

				$rc = $mail->Send();

				if (!$rc) {
					$reply['error'] =  $mail->ErrorInfo;
				} else {
					save_email_address($link, db_escape_string($destination));
					$reply['message'] = "UPDATE_COUNTERS";
				}

			} else {
				$reply['error'] = "Not authorized.";
			}

			print json_encode($reply);
			return;
		}

		if ($subop == "completeEmails") {
			$search = db_escape_string($_REQUEST["search"]);

			print "<ul>";

			foreach ($_SESSION['stored_emails'] as $email) {
				if (strpos($email, $search) !== false) {
					print "<li>$email</li>";
				}
			}

			print "</ul>";

			return;
		}

		if ($subop == "quickAddCat") {
			$cat = db_escape_string($_REQUEST["cat"]);

			add_feed_category($link, $cat);

			$result = db_query($link, "SELECT id FROM ttrss_feed_categories WHERE
				title = '$cat' AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) == 1) {
				$id = db_fetch_result($result, 0, "id");
			} else {
				$id = 0;
			}

			print_feed_cat_select($link, "cat_id", $id);

			return;
		}

		if ($subop == "regenFeedKey") {
			$feed_id = db_escape_string($_REQUEST['id']);
			$is_cat = db_escape_string($_REQUEST['is_cat']) == "true";

			$new_key = update_feed_access_key($link, $feed_id, $is_cat);

			print json_encode(array("link" => $new_key));
			return;
		}

		// Silent
		if ($subop == "clearKeys") {
			db_query($link, "DELETE FROM ttrss_access_keys WHERE
				owner_uid = " . $_SESSION["uid"]);

			return;
		}

		if ($subop == "verifyRegexp") {
			$reg_exp = $_REQUEST["reg_exp"];

			$status = @preg_match("/$reg_exp/i", "TEST") !== false;

			print json_encode(array("status" => $status));
			return;
		}

		// TODO: unify with digest-get-contents?
		if ($subop == "cdmGetArticle") {
			$ids = array(db_escape_string($_REQUEST["id"]));
			$cids = explode(",", $_REQUEST["cids"]);

			$ids = array_merge($ids, $cids);

			$rv = array();

			foreach ($ids as $id) {
				$id = (int)$id;

				$result = db_query($link, "SELECT content,
					ttrss_feeds.site_url AS site_url FROM ttrss_user_entries, ttrss_feeds,
					ttrss_entries
					WHERE feed_id = ttrss_feeds.id AND ref_id = '$id' AND
					ttrss_entries.id = ref_id AND
					ttrss_user_entries.owner_uid = ".$_SESSION["uid"]);

				if (db_num_rows($result) != 0) {
					$line = db_fetch_assoc($result);

					$article_content = sanitize_rss($link, $line["content"],
						false, false, $line['site_url']);

					array_push($rv,
						array("id" => $id, "content" => $article_content));
				}
			}

			print json_encode($rv);

			return;
		}

		if ($subop == "scheduleFeedUpdate") {
			$feed_id = db_escape_string($_REQUEST["id"]);
			$is_cat =  db_escape_string($_REQUEST['is_cat']) == 'true';

			$message = __("Your request could not be completed.");

			if ($feed_id >= 0) {
				if (!$is_cat) {
					$message = __("Feed update has been scheduled.");

					db_query($link, "UPDATE ttrss_feeds SET
						last_update_started = '1970-01-01',
						last_updated = '1970-01-01' WHERE id = '$feed_id' AND
						owner_uid = ".$_SESSION["uid"]);

				} else {
					$message = __("Category update has been scheduled.");

					if ($feed_id)
						$cat_query = "cat_id = '$feed_id'";
					else
						$cat_query = "cat_id IS NULL";

					db_query($link, "UPDATE ttrss_feeds SET
						last_update_started = '1970-01-01',
						last_updated = '1970-01-01' WHERE $cat_query AND
						owner_uid = ".$_SESSION["uid"]);
				}
			} else {
				$message = __("Can't update this kind of feed.");
			}

			print json_encode(array("message" => $message));
			return;
		}

		if ($subop == "getTweetInfo") {
			$id = db_escape_string($_REQUEST['id']);

			$result = db_query($link, "SELECT title, link
				FROM ttrss_entries, ttrss_user_entries
				WHERE id = '$id' AND ref_id = id AND owner_uid = " .$_SESSION['uid']);

			if (db_num_rows($result) != 0) {
				$title = truncate_string(strip_tags(db_fetch_result($result, 0, 'title')),
					100, '...');
				$article_link = db_fetch_result($result, 0, 'link');
			}

			print json_encode(array("title" => $title, "link" => $article_link,
				"id" => $id));

			return;
		}

		if ($subop == "setNote") {
			$id = db_escape_string($_REQUEST["id"]);
			$note = trim(strip_tags(db_escape_string($_REQUEST["note"])));

			db_query($link, "UPDATE ttrss_user_entries SET note = '$note'
				WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

			$formatted_note = format_article_note($id, $note);

			print json_encode(array("note" => $formatted_note,
				"raw_length" => mb_strlen($note)));
			return;
		}

		if ($subop == "genHash") {
			$hash = sha1(uniqid(rand(), true));

			print json_encode(array("hash" => $hash));
			return;
		}

		print json_encode(array("error" => array("code" => 7,
			"message" => "Unknown method: $subop")));
	}
?>
