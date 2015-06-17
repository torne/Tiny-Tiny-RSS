<?php

class Af_Sort_Bayes extends Plugin {

	private $host;
	private $filters = array();
	private $dbh;
	private $score_modifier = 50;
	private $sql_prefix = "ttrss_plugin_af_sort_bayes";

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

		$category = $train_up ? "GOOD" : "NEUTRAL";

		$nbs = new NaiveBayesianStorage($_SESSION["uid"]);
		$nb = new NaiveBayesian($nbs);

		$result = $this->dbh->query("SELECT score, guid, title, content FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id AND id = " .
			$article_id . " AND owner_uid = " . $_SESSION["uid"]);

		if ($this->dbh->num_rows($result) != 0) {
			$guid = $this->dbh->fetch_result($result, 0, "guid");
			$title = $this->dbh->fetch_result($result, 0, "title");
			$content = mb_strtolower($title . " " . strip_tags($this->dbh->fetch_result($result, 0, "content")));
			$score = $this->dbh->fetch_result($result, 0, "score");

			$this->dbh->query("BEGIN");

			if ($nb->untrain($guid, $content)) {
				if ($score >= $this->score_modifier) $score -= $this->score_modifier;
			}

			$nb->train($guid, $nbs->getCategoryByName($category), $content);

			if ($category == "GOOD") $score += $this->score_modifier;

			$this->dbh->query("UPDATE ttrss_user_entries SET score = '$score' WHERE ref_id = $article_id AND owner_uid = " . $_SESSION["uid"]);

			$nb->updateProbabilities();

			$this->dbh->query("COMMIT");

		}

		print "$article_id :: $category";
	}

	function get_js() {
		return file_get_contents(__DIR__ . "/init.js");
	}

	function get_prefs_js() {
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
		$prefix = $this->sql_prefix;

		// TODO there probably should be a way for plugins to determine their schema version to upgrade tables

		/*$this->dbh->query("DROP TABLE IF EXISTS ${prefix}_wordfreqs", false);
		$this->dbh->query("DROP TABLE IF EXISTS ${prefix}_references", false);
		$this->dbh->query("DROP TABLE IF EXISTS ${prefix}_categories", false);*/

		$this->dbh->query("BEGIN");

		// PG only for the time being

		if (DB_TYPE == "mysql") {

			$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_categories (
				id INTEGER NOT NULL PRIMARY KEY auto_increment,
				category varchar(100) NOT NULL DEFAULT '',
				probability DOUBLE NOT NULL DEFAULT '0',
				owner_uid INTEGER NOT NULL,
				FOREIGN KEY (owner_uid) REFERENCES ttrss_users(id) ON DELETE CASCADE,
				word_count BIGINT NOT NULL DEFAULT '0') ENGINE=InnoDB");

			$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_references (
				id INTEGER NOT NULL PRIMARY KEY auto_increment,
				document_id VARCHAR(255) NOT NULL,
				category_id INTEGER NOT NULL,
				FOREIGN KEY (category_id) REFERENCES ${prefix}_categories(id) ON DELETE CASCADE,
				owner_uid INTEGER NOT NULL,
				FOREIGN KEY (owner_uid) REFERENCES ttrss_users(id) ON DELETE CASCADE,
				content text NOT NULL) ENGINE=InnoDB");

			$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_wordfreqs (
				word varchar(100) NOT NULL DEFAULT '',
				category_id INTEGER NOT NULL,
				FOREIGN KEY (category_id) REFERENCES ${prefix}_categories(id) ON DELETE CASCADE,
				owner_uid INTEGER NOT NULL,
				FOREIGN KEY (owner_uid) REFERENCES ttrss_users(id) ON DELETE CASCADE,
				count BIGINT NOT NULL DEFAULT '0') ENGINE=InnoDB");


		} else {
			$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_categories (
				id SERIAL NOT NULL PRIMARY KEY,
				category varchar(100) NOT NULL DEFAULT '',
				probability DOUBLE NOT NULL DEFAULT '0',
				owner_uid INTEGER NOT NULL REFERENCES ttrss_users(id) ON DELETE CASCADE,
				word_count BIGINT NOT NULL DEFAULT '0')");

			$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_references (
				id SERIAL NOT NULL PRIMARY KEY,
				document_id VARCHAR(255) NOT NULL,
				category_id INTEGER NOT NULL REFERENCES ${prefix}_categories(id) ON DELETE CASCADE,
				owner_uid INTEGER NOT NULL REFERENCES ttrss_users(id) ON DELETE CASCADE,
				content text NOT NULL)");

			$this->dbh->query("CREATE TABLE IF NOT EXISTS ${prefix}_wordfreqs (
				word varchar(100) NOT NULL DEFAULT '',
				category_id INTEGER NOT NULL REFERENCES ${prefix}_categories(id) ON DELETE CASCADE,
				owner_uid INTEGER NOT NULL REFERENCES ttrss_users(id) ON DELETE CASCADE,
				count BIGINT NOT NULL DEFAULT '0')");
		}

		$owner_uid = @$_SESSION["uid"];

		if ($owner_uid) {
			$result = $this->dbh->query("SELECT id FROM ${prefix}_categories WHERE owner_uid = $owner_uid LIMIT 1");

			if ($this->dbh->num_rows($result) == 0) {
				$this->dbh->query("INSERT INTO ${prefix}_categories (category, owner_uid) VALUES ('GOOD', $owner_uid)");
				$this->dbh->query("INSERT INTO ${prefix}_categories (category, owner_uid) VALUES ('NEUTRAL', $owner_uid)");
			}
		}

		$this->dbh->query("COMMIT");
	}

	function hook_prefs_tab($args) {
		if ($args != "prefPrefs") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Bayesian classifier (af_sort_bayes)')."\">";

		$result = $this->dbh->query("SELECT category, probability, word_count,
			(SELECT COUNT(id) FROM {$this->sql_prefix}_references WHERE
				category_id = {$this->sql_prefix}_categories.id) as doc_count
 			FROM {$this->sql_prefix}_categories WHERE owner_uid = " . $_SESSION["uid"]);

		print "<table>";
		print "<tr><th>Category</th><th>Probability</th><th>Word count</th><th>Article count</th></tr>";

		while ($line = $this->dbh->fetch_assoc($result)) {
			print "<tr>";
			foreach ($line as $k => $v) {
				if ($k == "probability") $v = sprintf("%.3f", $v);

				print "<td>$v</td>";
			}
			print "</tr>";
		}

		print "</table>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return bayesClearDatabase()\">".
			__('Clear database')."</button> ";

		//

		print "</div>";
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		$nbs = new NaiveBayesianStorage($owner_uid);
		$nb = new NaiveBayesian($nbs);

		$categories = $nbs->getCategories();

		if (count($categories) > 0) {

			$count_neutral = 0;
			$count_good = 0;
			$id_good = 0;
			$id_neutral = 0;

			foreach ($categories as $id => $cat) {
				if ($cat["category"] == "GOOD") {
					$id_good = $id;
					$count_good += $cat["word_count"];
				} else if ($cat["category"] == "NEUTRAL") {
					$id_neutral = $id;
					$count_neutral += $cat["word_count"];
				}
			}

			$dst_category = $id_neutral;

			$bayes_content = mb_strtolower($article["title"] . " " . strip_tags($article["content"]));

			if ($count_neutral >= 3000 && $count_good >= 1000) {
				// enable automatic categorization

				$result = $nb->categorize($bayes_content);

				if (count($result) == 2) {
					$prob_good = $result[$id_good];
					$prob_neutral = $result[$id_neutral];

					if ($prob_good > 0.90 && $prob_good > $prob_neutral) {
						$dst_category = $id_good; // should we autofile as good or not? idk
						$article["score_modifier"] += $this->score_modifier;
					}
				}
			}

			$nb->train($article["guid_hashed"], $dst_category, $bayes_content);

			$nb->updateProbabilities();
		}

		return $article;

	}

	function clearDatabase() {
		$prefix = $this->sql_prefix;

		$this->dbh->query("BEGIN");
		$this->dbh->query("DELETE FROM ${prefix}_references WHERE owner_uid = " . $_SESSION["uid"]);
		$this->dbh->query("DELETE FROM ${prefix}_wordfreqs WHERE owner_uid = " . $_SESSION["uid"]);
		$this->dbh->query("COMMIT");

		$nbs = new NaiveBayesianStorage($_SESSION["uid"]);
		$nb = new NaiveBayesian($nbs);
		$nb->updateProbabilities();
	}

	function api_version() {
		return 2;
	}

}
?>
