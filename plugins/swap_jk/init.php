<?php
class Swap_JK extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Swap j and k hotkeys (for vi brethren)",
			"fox");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	function hook_hotkey_map($hotkeys) {

		$hotkeys["j"] = "next_feed";
		$hotkeys["k"] = "prev_feed";

		return $hotkeys;

	}
}
?>
