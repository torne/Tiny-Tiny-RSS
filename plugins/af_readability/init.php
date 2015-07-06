<?php
class Af_Readability extends Plugin {

	private $host;

	function about() {
		return array(1.0,
			"Try to inline article content using Readability",
			"fox");
	}

	function save() {
		//
	}

	function init($host)
	{
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('af_readability settings')."\">";

		print_notice("Enable the plugin for specific feeds in the feed editor.");

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();

		$enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
		$this->host->set($this, "enabled_feeds", $enabled_feeds);

		if (count($enabled_feeds) > 0) {
			print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";

			print "<ul class=\"browseFeedList\" style=\"border-width : 1px\">";
			foreach ($enabled_feeds as $f) {
				print "<li>" .
					"<img src='images/pub_set.png'
						style='vertical-align : middle'> <a href='#'
						onclick='editFeed($f)'>".
					getFeedTitle($f) . "</a></li>";
			}
			print "</ul>";
		}

		print "</div>";
	}

	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("Readability")."</div>";
		print "<div class=\"dlgSecCont\">";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();

		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"af_readability_enabled\"
			name=\"af_readability_enabled\"
			$checked>&nbsp;<label for=\"af_readability_enabled\">".__('Inline article content')."</label>";

		print "</div>";
	}

	function hook_prefs_save_feed($feed_id) {
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enable = checkbox_to_sql_bool($_POST["af_readability_enabled"]) == 'true';
		$key = array_search($feed_id, $enabled_feeds);

		if ($enable) {
			if ($key === FALSE) {
				array_push($enabled_feeds, $feed_id);
			}
		} else {
			if ($key !== FALSE) {
				unset($enabled_feeds[$key]);
			}
		}

		$this->host->set($this, "enabled_feeds", $enabled_feeds);
	}

	function hook_article_filter($article) {

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		$key = array_search($article["feed"]["id"], $enabled_feeds);
		if ($key === FALSE) return $article;

		if (!class_exists("Readability")) require_once(__DIR__ . "/classes/Readability.php");

		$tmp = fetch_file_contents($article["link"]);

		if ($tmp) {
			$r = new Readability($tmp, $article["link"]);

			if ($r->init()) {
				$article["content"] = $r->articleContent->innerHTML;
			}
		}

		return $article;

	}

	function api_version() {
		return 2;
	}

	private function filter_unknown_feeds($enabled_feeds) {
		$tmp = array();

		foreach ($enabled_feeds as $feed) {

			$result = db_query("SELECT id FROM ttrss_feeds WHERE id = '$feed' AND owner_uid = " . $_SESSION["uid"]);

			if (db_num_rows($result) != 0) {
				array_push($tmp, $feed);
			}
		}

		return $tmp;
	}

}
?>
