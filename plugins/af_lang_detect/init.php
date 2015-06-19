<?php
class Af_Lang_Detect extends Plugin {
	private $host;
	private $lang;

	function about() {
		return array(1.0,
			"Detect article language",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);

		require_once __DIR__ . "/languagedetect/LanguageDetect.php";

		$this->lang = new Text_LanguageDetect();
		$this->lang->setNameMode(2);
	}

	function hook_article_filter($article) {

		if ($this->lang) {
			$entry_language = $this->lang->detect($article['title'] . " " . $article['content'], 1);

			if (count($entry_language) > 0) {
				$possible = array_keys($entry_language);
				$entry_language = $possible[0];

				_debug("detected language: $entry_language");

				$article["language"] = $entry_language;
			}
		}

		return $article;
	}

	function api_version() {
		return 2;
	}

}
?>
