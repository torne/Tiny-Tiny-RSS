<?php
class Backend extends Handler {

	function loading() {
		header("Content-type: text/html");
		print __("Loading, please wait...") . " " .
			"<img src='images/indicator_tiny.gif'>";
	}

	function digestTest() {
		header("Content-type: text/html");

		$rv = prepare_headlines_digest($this->link, $_SESSION['uid'], 1, 1000);

		$rv[3] = "<pre>" . $rv[3] . "</pre>";

		print_r($rv);
	}

	function help() {
		$topic = basename($_REQUEST["topic"]);

		if (file_exists("help/$topic.php")) {
			include("help/$topic.php");
		} else {
			print "<p>".__("Help topic not found.")."</p>";
		}
		/* print "<div align='center'>
			<button onclick=\"javascript:window.close()\">".
			__('Close this window')."</button></div>"; */

	}
}
?>
