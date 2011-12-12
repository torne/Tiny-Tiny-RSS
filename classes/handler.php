<?php
class Handler {
	protected $link;
	protected $args;

	function __construct($link, $args) {
		$this->link = $link;
		$this->args = $args;
	}

	function before() {
		return true;
	}
}
?>
