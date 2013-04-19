<?php
class Example_VFeed extends Plugin {

	// Demonstrates how to create a dummy special feed and chain
	// headline generation to queryFeedHeadlines();

	// Not implemented yet: stuff for 3 panel mode

	private $host;
	private $dummy_id;

	function about() {
		return array(1.0,
			"Example vfeed plugin",
			"fox",
			false);
	}

	function init($host) {
		$this->host = $host;

		$this->dummy_id = $host->add_feed(-1, 'Dummy feed', 'images/pub_set.svg', $this);
	}

	function get_unread($feed_id) {
		return 1234;
	}

	function get_headlines($feed_id, $options) {
		$qfh_ret = queryFeedHeadlines(-4,
			$options['limit'],
			$options['view_mode'], $options['cat_view'],
			$options['search'],
			$options['search_mode'],
			$options['override_order'],
			$options['offset'],
			$options['owner_uid'],
			$options['filter'],
			$options['since_id'],
			$options['include_children']);

		$qfh_ret[1] = 'Dummy feed';

		return $qfh_ret;
	}

	function api_version() {
		return 2;
	}

}
?>
