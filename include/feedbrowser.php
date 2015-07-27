<?php
	function make_feed_browser($search, $limit, $mode = 1) {

		if (defined('_DISABLE_FEED_BROWSER') && _DISABLE_FEED_BROWSER) return;

		$owner_uid = $_SESSION["uid"];
		$rv = '';

		if ($search) {
			$search_qpart = "AND (UPPER(feed_url) LIKE UPPER('%$search%') OR
						UPPER(title) LIKE UPPER('%$search%'))";
		} else {
			$search_qpart = "";
		}

		if ($mode == 1) {
			/* $result = db_query("SELECT feed_url, subscribers FROM
			 ttrss_feedbrowser_cache WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
			WHERE tf.feed_url = ttrss_feedbrowser_cache.feed_url
			AND owner_uid = '$owner_uid') $search_qpart
			ORDER BY subscribers DESC LIMIT $limit"); */

			$result = db_query("SELECT feed_url, site_url, title, SUM(subscribers) AS subscribers FROM
						(SELECT feed_url, site_url, title, subscribers FROM ttrss_feedbrowser_cache UNION ALL
							SELECT feed_url, site_url, title, subscribers FROM ttrss_linked_feeds) AS qqq
						WHERE
							(SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf
								WHERE tf.feed_url = qqq.feed_url
									AND owner_uid = '$owner_uid') $search_qpart
						GROUP BY feed_url, site_url, title ORDER BY subscribers DESC LIMIT $limit");

		} else if ($mode == 2) {
			$result = db_query("SELECT *,
						(SELECT COUNT(*) FROM ttrss_user_entries WHERE
					 		orig_feed_id = ttrss_archived_feeds.id) AS articles_archived
						FROM
							ttrss_archived_feeds
						WHERE
						(SELECT COUNT(*) FROM ttrss_feeds
							WHERE ttrss_feeds.feed_url = ttrss_archived_feeds.feed_url AND
								owner_uid = '$owner_uid') = 0	AND
						owner_uid = '$owner_uid' $search_qpart
						ORDER BY id DESC LIMIT $limit");
		}

		$feedctr = 0;

		while ($line = db_fetch_assoc($result)) {

			if ($mode == 1) {

				$feed_url = htmlspecialchars($line["feed_url"]);
				$site_url = htmlspecialchars($line["site_url"]);
				$subscribers = $line["subscribers"];

				$check_box = "<input onclick='toggleSelectListRow2(this)'
							dojoType=\"dijit.form.CheckBox\"
							type=\"checkbox\" \">";

				$class = ($feedctr % 2) ? "even" : "odd";

				$site_url = "<a target=\"_blank\"
							href=\"$site_url\">
							<span class=\"fb_feedTitle\">".
				htmlspecialchars($line["title"])."</span></a>";

				$feed_url = "<a target=\"_blank\" class=\"fb_feedUrl\"
							href=\"$feed_url\"><img src='images/pub_set.png'
							style='vertical-align : middle'></a>";

				$rv .= "<li>$check_box $feed_url $site_url".
							"&nbsp;<span class='subscribers'>($subscribers)</span></li>";

			} else if ($mode == 2) {
				$feed_url = htmlspecialchars($line["feed_url"]);
				$site_url = htmlspecialchars($line["site_url"]);

				$check_box = "<input onclick='toggleSelectListRow2(this)' dojoType=\"dijit.form.CheckBox\"
							type=\"checkbox\">";

				$class = ($feedctr % 2) ? "even" : "odd";

				if ($line['articles_archived'] > 0) {
					$archived = sprintf(_ngettext("%d archived article", "%d archived articles", $line['articles_archived']), $line['articles_archived']);
					$archived = "&nbsp;<span class='subscribers'>($archived)</span>";
				} else {
					$archived = '';
				}

				$site_url = "<a target=\"_blank\"
							href=\"$site_url\">
							<span class=\"fb_feedTitle\">".
				htmlspecialchars($line["title"])."</span></a>";

				$feed_url = "<a target=\"_blank\" class=\"fb_feedUrl\"
							href=\"$feed_url\"><img src='images/pub_set.png'
							style='vertical-align : middle'></a>";


				$rv .= "<li id=\"FBROW-".$line["id"]."\">".
							"$check_box $feed_url $site_url $archived</li>";
			}

			++$feedctr;
		}

		if ($feedctr == 0) {
			$rv .= "<li style=\"text-align : center\"><p>".__('No feeds found.')."</p></li>";
		}

		return $rv;
	}
?>
