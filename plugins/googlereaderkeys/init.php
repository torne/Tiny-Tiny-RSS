<?php
class GoogleReaderKeys extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Keyboard hotkeys like Google Reader",
			"markwaters");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
	}

	function hook_hotkey_map($hotkeys) {

		$hotkeys["j"] = "next_article_noscroll";
		$hotkeys["N"] = "next_feed";
		$hotkeys["k"] = "prev_article_noscroll";
		$hotkeys["P"] = "prev_feed";
		$hotkeys["v"] = "open_in_new_window";
		$hotkeys["(32)|space"] = "next_article";
		$hotkeys["r"] = "feed_refresh";

		return $hotkeys;

	}
}
?>
