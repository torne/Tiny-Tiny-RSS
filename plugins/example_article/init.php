<?php
class Example_Article extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Example plugin for HOOK_RENDER_ARTICLE",
			"fox",
			true);
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_RENDER_ARTICLE, $this);
	}

	function get_prefs_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function hook_render_article($article) {
		$article["content"] = "Content changed: " . $article["content"];

		return $article;
	}
}
?>
