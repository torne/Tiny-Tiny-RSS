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
		$tid = (int) $_REQUEST["tid"];

		if (file_exists("help/$tid.php")) {
			include("help/$tid.php");
		} else {
			print "<p>".__("Help topic not found.")."</p>";
		}
		print "<div align='center'>
			<button onclick=\"javascript:window.close()\">".
			__('Close this window')."</button></div>";

	}
}
?>
