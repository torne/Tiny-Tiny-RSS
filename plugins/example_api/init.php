<?php
class Example_Api extends Plugin {

	// Demonstrates adding a method to the API
	// Plugin methods return an array containint
	// 1. status (STATUS_OK or STATUS_ERR)
	// 2. arbitrary payload

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Example plugin adding an API method",
			"fox",
			true,
			"http://tt-rss.org/");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_api_method("example_testmethod", $this);
	}

	function example_testmethod() {
		return array(API::STATUS_OK, array("current_time" => time()));
	}
}
?>
