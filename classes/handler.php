<?php
class Handler implements IHandler {
	protected $link;
	protected $args;

	function __construct($link, $args) {
		$this->link = $link;
		$this->args = $args;
	}

	function csrf_ignore($method) {
		return true;
	}

	function before($method) {
		return true;
	}

	function after() {
		return true;
	}

}
?>
