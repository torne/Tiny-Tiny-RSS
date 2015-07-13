<?php
class VF_Shared extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Feed for all articles actively shared by URL",
			"fox",
			false);
	}

	function init($host) {
		$this->host = $host;

		$host->add_feed(-1, __("Shared articles"), 'plugins/vf_shared/share.png', $this);
	}

	function api_version() {
		return 2;
	}

	function get_unread($feed_id) {
		$result = db_query("select count(int_id) AS count from ttrss_user_entries where owner_uid = ".$_SESSION["uid"]." and unread = true and uuid != ''");

		return db_fetch_result($result, 0, "count");
	}

	function get_total($feed_id) {
		$result = db_query("select count(int_id) AS count from ttrss_user_entries where owner_uid = ".$_SESSION["uid"]." and uuid != ''");

		return db_fetch_result($result, 0, "count");
	}

	//function queryFeedHeadlines($feed, $limit, $view_mode, $cat_view, $search, $search_mode, $override_order = false, $offset = 0, $owner_uid = 0, $filter = false, $since_id = 0, $include_children = false, $ignore_vfeed_group = false, $override_strategy = false, $override_vfeed = false) {

	function get_headlines($feed_id, $options) {
		/*$qfh_ret = queryFeedHeadlines(-4,
			$options['limit'],
			$this->get_unread(-1) > 0 ? "adaptive" : "all_articles",
			false,
			$options['search'],
			$options['search_mode'],
			$options['override_order'],
			$options['offset'],
			$options['owner_uid'],
			$options['filter'],
			$options['since_id'],
			$options['include_children'],
			false,
			"uuid != ''",
			"ttrss_feeds.title AS feed_title,"); */

		$params = array(
			"feed" => -4,
			"limit" => $options["limit"],
			"view_mode" => $this->get_unread(-1) > 0 ? "adaptive" : "all_articles",
			"search" => $options['search'],
			"override_order" => $options['override_order'],
			"offset" => $options["offset"],
			"filter" => $options["filter"],
			"since_id" => $options["since_id"],
			"include_children" => $options["include_children"],
			"override_strategy" => "uuid != ''",
			"override_vfeed" => "ttrss_feeds.title AS feed_title,"
		);

		$qfh_ret = queryFeedHeadlines($params);
		$qfh_ret[1] = __("Shared articles");

		return $qfh_ret;
	}

}
?>
