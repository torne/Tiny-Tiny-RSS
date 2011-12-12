<?php
class Feeds extends Handler {
	
	function catchupAll() {
		db_query($this->link, "UPDATE ttrss_user_entries SET
						last_read = NOW(),unread = false WHERE owner_uid = " . $_SESSION["uid"]);
		ccache_zero_all($this->link, $_SESSION["uid"]);
	}	

	function collapse() {
		$cat_id = db_escape_string($_REQUEST["cid"]);
		$mode = (int) db_escape_string($_REQUEST['mode']);
		toggle_collapse_cat($this->link, $cat_id, $mode);
	}

	function index() {
		$root = (bool)$_REQUEST["root"];
	
		if (!$root) {
			print json_encode(outputFeedList($this->link));
		} else {
		
			$feeds = outputFeedList($this->link, false);
		
			$root = array();
			$root['id'] = 'root';
			$root['name'] = __('Feeds');
			$root['items'] = $feeds['items'];
		
			$fl = array();
			$fl['identifier'] = 'id';
			$fl['label'] = 'name';
			$fl['items'] = array($root);
		
			print json_encode($fl);
		}
	}	
	
	function view() {
		$timing_info = getmicrotime();
		
		$reply = array();
		
		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("0", $timing_info);
		
		$omode = db_escape_string($_REQUEST["omode"]);
		
		$feed = db_escape_string($_REQUEST["feed"]);
		$method = db_escape_string($_REQUEST["m"]);
		$view_mode = db_escape_string($_REQUEST["view_mode"]);
		$limit = (int) get_pref($this->link, "DEFAULT_ARTICLE_LIMIT");
		@$cat_view = db_escape_string($_REQUEST["cat"]) == "true";
		@$next_unread_feed = db_escape_string($_REQUEST["nuf"]);
		@$offset = db_escape_string($_REQUEST["skip"]);
		@$vgroup_last_feed = db_escape_string($_REQUEST["vgrlf"]);
		$order_by = db_escape_string($_REQUEST["order_by"]);
		
		if (is_numeric($feed)) $feed = (int) $feed;
		
		/* Feed -5 is a special case: it is used to display auxiliary information
		 * when there's nothing to load - e.g. no stuff in fresh feed */
		
		if ($feed == -5) {
			print json_encode(generate_dashboard_feed($this->link));
			return;
		}
		
		$result = false;
		
		if ($feed < -10) {
			$label_feed = -11-$feed;
			$result = db_query($this->link, "SELECT id FROM ttrss_labels2 WHERE
							id = '$label_feed' AND owner_uid = " . $_SESSION['uid']);
		} else if (!$cat_view && is_numeric($feed) && $feed > 0) {
			$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
							id = '$feed' AND owner_uid = " . $_SESSION['uid']);
		} else if ($cat_view && is_numeric($feed) && $feed > 0) {
			$result = db_query($this->link, "SELECT id FROM ttrss_feed_categories WHERE
							id = '$feed' AND owner_uid = " . $_SESSION['uid']);
		}
		
		if ($result && db_num_rows($result) == 0) {
			print json_encode(generate_error_feed($this->link, __("Feed not found.")));
			return;
		}
		
		/* Updating a label ccache means recalculating all of the caches
		 * so for performance reasons we don't do that here */
		
		if ($feed >= 0) {
			ccache_update($this->link, $feed, $_SESSION["uid"], $cat_view);
		}
		
		set_pref($this->link, "_DEFAULT_VIEW_MODE", $view_mode);
		set_pref($this->link, "_DEFAULT_VIEW_LIMIT", $limit);
		set_pref($this->link, "_DEFAULT_VIEW_ORDER_BY", $order_by);
		
		if (!$cat_view && preg_match("/^[0-9][0-9]*$/", $feed)) {
			db_query($this->link, "UPDATE ttrss_feeds SET last_viewed = NOW()
							WHERE id = '$feed' AND owner_uid = ".$_SESSION["uid"]);
		}
		
		$reply['headlines'] = array();
		
		if (!$next_unread_feed)
			$reply['headlines']['id'] = $feed;
		else
			$reply['headlines']['id'] = $next_unread_feed;
		
		$reply['headlines']['is_cat'] = (bool) $cat_view;
		
		$override_order = false;
		
		if (get_pref($this->link, "SORT_HEADLINES_BY_FEED_DATE", $owner_uid)) {
			$date_sort_field = "updated";
		} else {
			$date_sort_field = "date_entered";
		}
		
		switch ($order_by) {
			case "date":
				if (get_pref($this->link, 'REVERSE_HEADLINES', $owner_uid)) {
					$override_order = "$date_sort_field";
				} else {
					$override_order = "$date_sort_field DESC";
				}
				break;
		
			case "title":
				if (get_pref($this->link, 'REVERSE_HEADLINES', $owner_uid)) {
					$override_order = "title DESC, $date_sort_field";
				} else {
					$override_order = "title, $date_sort_field DESC";
				}
				break;
		
			case "score":
				if (get_pref($this->link, 'REVERSE_HEADLINES', $owner_uid)) {
					$override_order = "score, $date_sort_field";
				} else {
					$override_order = "score DESC, $date_sort_field DESC";
				}
				break;
		}
		
		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("04", $timing_info);
		
		$ret = format_headlines_list($this->link, $feed, $method,
			$view_mode, $limit, $cat_view, $next_unread_feed, $offset,
			$vgroup_last_feed, $override_order);
		
		$topmost_article_ids = $ret[0];
		$headlines_count = $ret[1];
		$returned_feed = $ret[2];
		$disable_cache = $ret[3];
		$vgroup_last_feed = $ret[4];
		
		$reply['headlines']['content'] =& $ret[5]['content'];
		$reply['headlines']['toolbar'] =& $ret[5]['toolbar'];
		
		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("05", $timing_info);
		
		$reply['headlines-info'] = array("count" => (int) $headlines_count,
						"vgroup_last_feed" => $vgroup_last_feed,
						"disable_cache" => (bool) $disable_cache);
		
		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("20", $timing_info);
		
		if (is_array($topmost_article_ids) && !get_pref($this->link, 'COMBINED_DISPLAY_MODE') && !$_SESSION["bw_limit"]) {
			$articles = array();
		
			foreach ($topmost_article_ids as $id) {
				array_push($articles, format_article($this->link, $id, false));
			}
		
			$reply['articles'] = $articles;
		}
		
		if ($_REQUEST["debug"]) $timing_info = print_checkpoint("30", $timing_info);
		
		$reply['runtime-info'] = make_runtime_info($this->link);
		
		print json_encode($reply);
		
	}
}
?>