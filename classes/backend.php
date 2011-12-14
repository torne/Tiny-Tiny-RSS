<?php
class Backend extends Handler {

	function loading() {
		header("Content-type: text/html");
		print __("Loading, please wait...") . " " .
			"<img src='images/indicator_tiny.gif'>";
	}

	function digestSend() {
		send_headlines_digests($this->link);
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
