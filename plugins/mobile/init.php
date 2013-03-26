<?php
class Mobile extends Plugin implements IHandler {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Classic mobile version for tt-rss (unsupported)",
			"fox",
			true);
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_handler("mobile", "index", $this);
	}

	function index() {
		header("Content-type: text/html; charset=utf-8");

		header("Location: plugins/mobile/index.php");
	}

	/* function get_js() {
		return file_get_contents(dirname(__FILE__) . "/digest.js");
	} */

	function csrf_ignore($method) {
		return true; //in_array($method, array("index"));
	}

	function before($method) {
		return true;
	}

	function after() {

	}


}
?>
