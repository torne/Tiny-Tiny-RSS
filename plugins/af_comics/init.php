<?php
class Af_Comics extends Plugin {

	private $host;
	private $filters = array();

	function about() {
		return array(1.0,
			"Fixes RSS feeds of assorted comic strips",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);

		require_once __DIR__ . "/filter_base.php";

		$filters = glob(__DIR__ . "/filters/*.php");

		foreach ($filters as $file) {
			require_once $file;
			$filter_name = preg_replace("/\..*$/", "", basename($file));

			$filter = new $filter_name();

			if (is_subclass_of($filter, "Af_ComicFilter")) {
				array_push($this->filters, $filter);
			}
		}

	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Feeds supported by af_comics')."\">";

		print "<p>" . __("The following comics are currently supported:") . "</p>";

		$comics = array();

		foreach ($this->filters as $f) {
			foreach ($f->supported() as $comic) {
				array_push($comics, $comic);
			}
		}

		asort($comics);

		print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
		foreach ($comics as $comic) {
			print "<li>$comic</li>";
		}
		print "</ul>";

		print "</div>";
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		foreach ($this->filters as $f) {
			if ($f->process($article))
				break;
		}

		return $article;

	}

	function api_version() {
		return 2;
	}

}
?>
