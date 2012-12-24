<?php
class Example_Routing extends Plugin implements IHandler {

	// Demonstrates adding a custom handler and method:
	// backend.php?op=test&method=example
	// and masking a system builtin public method:
	// public.php?op=getUnread

	// Plugin class must implelement IHandler interface and has
	// a public method of same name as being registered.
	//
	// Any system method may be masked by plugins. You can mask
	// entire handler by supplying "*" instead of a method name.

	private $link;
	private $host;

	function _about() {
		return array(1.0,
			"Example routing plugin",
			"fox");
	}

	function __construct($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_handler("test", "example", $this);
		$host->add_handler("public", "getunread", $this);
	}

	function getunread() {
		print rand(0,100); # yeah right
	}

	function example() {
		print "example method called";
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
