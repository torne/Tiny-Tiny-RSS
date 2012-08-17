<?php
class Plugin {
	protected $link;
	protected $handler;

	function __construct($link, $handler) {
		$this->link = $link;
		$this->handler = $handler;
		$this->initialize();
	}

	function initialize() {


	}

	function add_listener($hook) {
		$this->handler->add_listener($hook, $this);
	}
}
?>
