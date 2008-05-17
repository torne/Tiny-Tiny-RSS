<?php
	function module_pref_feed_browser($link) {

		if (!ENABLE_FEED_BROWSER) {
			print __("Feed browser is administratively disabled.");
			return;
		}

		$subop = $_REQUEST["subop"];

		if ($subop == "details") {
			$id = db_escape_string($_GET["id"]);

			print "<div class=\"browserFeedInfo\">";
			print "<b>".__('Feed information:')."</b>";

			$result = db_query($link, "SELECT 
					feed_url,site_url,
					".SUBSTRING_FOR_DATE."(last_updated,1,19) AS last_updated 
				FROM ttrss_feeds WHERE id = '$id' AND
					auth_login = '' AND auth_pass = '' AND private IS NOT true
					AND feed_url NOT LIKE '%:%@%/%'");

			if (db_num_rows($result) == 1) {

				print "<div class=\"detailsPart\">";

				$feed_url = db_fetch_result($result, 0, "feed_url");
				$site_url = db_fetch_result($result, 0, "site_url");
				$last_updated = db_fetch_result($result, 0, "last_updated");
	
				if (get_pref($link, 'HEADLINES_SMART_DATE')) {
					$last_updated = smart_date_time(strtotime($last_updated));
				} else {
					$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
					$last_updated = date($short_date, strtotime($last_updated));
				}
	
				print __("Site:")." <a target=\"_new\" href='$site_url'>$site_url</a> ".
					"(<a target=\"_new\" href='$feed_url'>feed</a>), ".
					__("Last updated:")." $last_updated";
	
				print "</div>";
	
				$result = db_query($link, "SELECT 
						ttrss_entries.title,
						content,link,
						".SUBSTRING_FOR_DATE."(date_entered,1,19) as date_entered,
						".SUBSTRING_FOR_DATE."(updated,1,19) as updated
					FROM ttrss_entries,ttrss_user_entries
					WHERE	ttrss_entries.id = ref_id AND feed_id = '$id'
					ORDER BY updated DESC LIMIT 5");
	
				if (db_num_rows($result) > 0) {
					
					print "<b>".__('Last headlines:')."</b><br>";
					
					print "<div class=\"detailsPart\">";
					print "<ul class=\"compact\">";
					while ($line = db_fetch_assoc($result)) {
	
						if (get_pref($link, 'HEADLINES_SMART_DATE')) {
							$entry_dt = smart_date_time(strtotime($line["updated"]));
						} else {
							$short_date = get_pref($link, 'SHORT_DATE_FORMAT');
							$entry_dt = date($short_date, strtotime($line["updated"]));
						}				
			
						print "<li><a target=\"_new\" href=\"" . $line["link"] . "\">" . $line["title"] . "</a>" .
							"&nbsp;<span class=\"insensitive\">($entry_dt)</span></li>";	
					}		
					print "</ul></div>";
				}
			} else {
				print "<p>".__("Feed not found.")."</p>";
			}

			print "</div>";
				
			return;
		}

		set_pref($link, "_PREFS_ACTIVE_TAB", "feedBrowser");

		print "<div>".__('This panel shows feeds subscribed by other users of this system, just in case you are interested in them too.')."</div>";

		$limit = db_escape_string($_GET["limit"]);

		if (!$limit) $limit = 25;

		$owner_uid = $_SESSION["uid"];
			
		$result = db_query($link, "SELECT feed_url,COUNT(id) AS subscribers
	  		FROM ttrss_feeds WHERE (SELECT COUNT(id) = 0 FROM ttrss_feeds AS tf 
				WHERE tf.feed_url = ttrss_feeds.feed_url 
					AND (private IS true OR owner_uid = '$owner_uid')) GROUP BY feed_url 
						ORDER BY subscribers DESC LIMIT $limit");

		print "<br/>";
			
		print "<div style=\"float : right\">
			".__('Top')." <select id=\"feedBrowserLimit\">";

		foreach (array(25, 50, 100) as $l) {
			$issel = ($l == $limit) ? "selected" : "";
			print "<option $issel>$l</option>";
		}
			
		print "</select>
			<input type=\"submit\" class=\"button\"
				onclick=\"updateBigFeedBrowser()\" value=\"".__('Show')."\">
		</div>";

		print "<div id=\"fbrOpToolbar\">
			<input type='submit' class='button' onclick=\"feedBrowserSubscribe()\" 
			disabled=\"true\" value=\"".__('Subscribe')."\"></div>";

		print "<ul class='nomarks' id='browseBigFeedList'>";

		$feedctr = 0;
		
		while ($line = db_fetch_assoc($result)) {
			$feed_url = $line["feed_url"];
			$subscribers = $line["subscribers"];

			// mysql returns NULL records first by default
			if (DB_TYPE == "mysql") $order_fix = "DESC";

			$det_result = db_query($link, "SELECT site_url,title,id 
				FROM ttrss_feeds WHERE feed_url = '$feed_url' 
				ORDER BY last_updated $order_fix LIMIT 1");

			$details = db_fetch_assoc($det_result);
		
			$icon_file = ICONS_DIR . "/" . $details["id"] . ".ico";

			if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img class=\"tinyFeedIcon\"	src=\"" . ICONS_URL . 
						"/".$details["id"].".ico\">";
			} else {
				$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
			}

			$check_box = "<input onclick='toggleSelectFBListRow(this)' class='feedBrowseCB' 
				type=\"checkbox\" id=\"FBCHK-" . $details["id"] . "\">";

			$class = ($feedctr % 2) ? "even" : "odd";

			print "<li class='$class' id=\"FBROW-".$details["id"]."\">$check_box".
				"$feed_icon ";
				
			print "<a href=\"javascript:browserToggleExpand('".$details["id"]."')\">" . 
				$details["title"] ."</a>&nbsp;" .
				"<span class='subscribers'>($subscribers)</span>";
			
			print "<div class=\"browserDetails\" style=\"display : none\" id=\"BRDET-" . $details["id"] . "\">";
			print "</div>";
				
			print "</li>";

				++$feedctr;
		}

		if ($feedctr == 0) {
			print "<li>".__('No feeds found to subscribe.')."</li>";
		}

		print "</ul>";

		print "</div>";
	}
?>
