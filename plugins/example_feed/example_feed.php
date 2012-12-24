<?php
class Example_Feed extends Plugin {

	// Demonstrates how to query data from the parsed feed object (SimplePie)
	// don't enable unless debugging feed through f D hotkey or manually.

	private $link;
	private $host;

	function __construct($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_FEED_PARSED, $this);
	}

	function hook_feed_parsed($feed) {
		_debug("I'm a little feed short and stout, here's my title: " . $feed->get_title());
		_debug("... here's my link element: " . $feed->get_link());
	}
}
?>
