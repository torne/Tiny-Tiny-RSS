<?php

class Af_Sort_Bayes extends Plugin {

	private $host;
	private $filters = array();
	private $dbh;

	function about() {
		return array(1.0,
			"Bayesian classifier for tt-rss (WIP)",
			"fox");
	}

	function init($host) {
		require_once __DIR__ . "/lib/class.naivebayesian.php";
		require_once __DIR__ . "/lib/class.naivebayesianstorage.php";

		$this->host = $host;
		$this->dbh = Db::get();

		$this->init_database();

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);

	}

	function trainArticle() {
		$article_id = (int) $_REQUEST["article_id"];
		$train_up = sql_bool_to_bool($_REQUEST["train_up"]);

		print "FIXME: $article_id :: $train_up";

	}

	function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	function hook_article_button($line) {
		return "<img src=\"plugins/af_sort_bayes/thumb_up.png\"
			style=\"cursor : pointer\" style=\"cursor : pointer\"
			onclick=\"bayesTrain(".$line["id"].", true)\"
			class='tagsPic' title='".__('+1')."'>" .
		"<img src=\"plugins/af_sort_bayes/thumb_down.png\"
			style=\"cursor : pointer\" style=\"cursor : pointer\"
			onclick=\"bayesTrain(".$line["id"].", false)\"
			class='tagsPic' title='".__('-1')."'>";

	}

	function init_database() {
		$prefix = "ttrss_plugin_af_sort_bayes";

		/*$this->dbh->query("DROP TABLE IF EXISTS ${prefix}_references", false);
		$this->dbh->query("DROP TABLE IF EXISTS ${prefix}_categories", false);
		$this->dbh->query("DROP TABLE IF EXISTS ${prefix}_wordfreqs", false);*/

		$this->dbh->query("BEGIN");

		// PG only for the time being

		$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_categories (
			id SERIAL NOT NULL PRIMARY KEY,
			category varchar(100) NOT NULL DEFAULT '',
  			probability DOUBLE PRECISION NOT NULL DEFAULT '0',
  			owner_uid INTEGER NOT NULL REFERENCES ttrss_users(id) ON DELETE CASCADE,
  			word_count BIGINT NOT NULL DEFAULT '0')");

		$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_documents (
			id SERIAL NOT NULL PRIMARY KEY,
			document varchar(250) NOT NULL DEFAULT '',
  			category_id INTEGER NOT NULL REFERENCES ${prefix}_categories(id) ON DELETE CASCADE,
  			owner_uid INTEGER NOT NULL REFERENCES ttrss_users(id) ON DELETE CASCADE,
  			content text NOT NULL)");

		$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_wordfreqs (
			word varchar(100) NOT NULL DEFAULT '',
  			category_id INTEGER NOT NULL REFERENCES ${prefix}_categories(id) ON DELETE CASCADE,
  			owner_uid INTEGER NOT NULL REFERENCES ttrss_users(id) ON DELETE CASCADE,
  			count BIGINT NOT NULL DEFAULT '0')");

		$this->dbh->query("COMMIT");
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('af_sort_bayes')."\">";

		//

		print "</div>";
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];


		return $article;

	}

	function api_version() {
		return 2;
	}

}
?>
