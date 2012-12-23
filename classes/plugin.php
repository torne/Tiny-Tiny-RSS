<?php
class Plugin {
	private $link;
	private $host;

	function __construct($host) {
		$this->link = $host->get_link();
		$this->host = $host;
	}
}
?>
