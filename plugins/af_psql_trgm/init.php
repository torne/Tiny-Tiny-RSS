<?php
class Af_Psql_Trgm extends Plugin {

	private $host;
	private $filters = array();

	function about() {
		return array(1.0,
			"Marks similar articles as read (requires pg_trgm)",
			"fox");
	}

	function save() {
		$similarity = (float) db_escape_string($_POST["similarity"]);
		$min_title_length = (int) db_escape_string($_POST["min_title_length"]);

		if ($similarity < 0) $similarity = 0;
		if ($similarity > 1) $similarity = 1;

		if ($min_title_length < 0) $min_title_length = 0;

		$similarity = sprintf("%.2f", $similarity);

		$this->host->set($this, "similarity", $similarity);
		$this->host->set($this, "min_title_length", $min_title_length);

		echo T_sprintf("Data saved (%s)", $similarity);
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		$host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
		$host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);

	}

	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Mark similar articles as read')."\">";

		if (DB_TYPE != "pgsql") {
			print_error("Database type not supported.");
		}

		$result = db_query("select 'similarity'::regproc");

		if (db_num_rows($result) == 0) {
			print_error("pg_trgm extension not found.");
		}

		$similarity = $this->host->get($this, "similarity");
		$min_title_length = $this->host->get($this, "min_title_length");

		if (!$similarity) $similarity = '0.75';
		if (!$min_title_length) $min_title_length = '32';

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				console.log(dojo.objectToQuery(this.getValues()));
				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						notify_info(transport.responseText);
					}
				});
				//this.reset();
			}
			</script>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"af_psql_trgm\">";

		print_notice("PostgreSQL trigram extension returns string similarity as a floating point number (0-1). Setting it too low might produce false positives, zero disables checking.");

		print "<br/>";
		print_notice("Only data in other feeds is checked, i.e. sequential duplicate posts in one feed will not be detected by this plugin.");

		print "<br/>";
		print_notice("Enable the plugin for specific feeds in the feed editor.");

		print "<h3>" . __("Global settings") . "</h3>";

		print "<table>";

		print "<tr><td width=\"40%\">".__("Minimum similarity:")."</td>";
		print "<td>
			<input dojoType=\"dijit.form.ValidationTextBox\"
			placeholder=\"0.75\"
			required=\"1\" name=\"similarity\" value=\"$similarity\"></td></tr>";
		print "<tr><td width=\"40%\">".__("Minimum title length:")."</td>";
		print "<td>
			<input dojoType=\"dijit.form.ValidationTextBox\"
			placeholder=\"32\"
			required=\"1\" name=\"min_title_length\" value=\"$min_title_length\"></td></tr>";


		print "</table>";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__("Save")."</button>";

		print "</form>";

		print "</div>";
	}

	//PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_EDIT_FEED,
	//		"hook_prefs_edit_feed", $feed_id);
	//	PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_SAVE_FEED,
	//		"hook_prefs_save_feed", $feed_id);

	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("Similarity (pg_trgm)")."</div>";
		print "<div class=\"dlgSecCont\">";

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!array($enabled_feeds)) $enabled_feeds = array();

		$key = array_search($feed_id, $enabled_feeds);
		$checked = $key !== FALSE ? "checked" : "";

		print "<hr/><input dojoType=\"dijit.form.CheckBox\" type=\"checkbox\" id=\"trgm_similarity_enabled\"
			name=\"trgm_similarity_enabled\"
			$checked>&nbsp;<label for=\"trgm_similarity_enabled\">".__('Mark similar articles as read')."</label>";

		print "</div>";
	}

	function hook_prefs_save_feed($feed_id) {
		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		if (!is_array($enabled_feeds)) $enabled_feeds = array();

		$enable = checkbox_to_sql_bool($_POST["trgm_similarity_enabled"]) == 'true';
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

		if (DB_TYPE != "pgsql") return $article;

		$result = db_query("select 'similarity'::regproc");
		if (db_num_rows($result) == 0) return $article;

		$enabled_feeds = $this->host->get($this, "enabled_feeds");
		$key = array_search($article["feed"]["id"], $enabled_feeds);
		if ($key === FALSE) return $article;

		$similarity = (float) $this->host->get($this, "similarity");
		if ($similarity < 0.01) return $article;

		$min_title_length = (int) $this->host->get($this, "min_length");
		if (mb_strlen($article["title"]) < $min_title_length) return $article;

		$owner_uid = $article["owner_uid"];
		$feed_id = $article["feed"]["id"];

		$title_escaped = db_escape_string($article["title"]);

		$result = db_query("SELECT MAX(SIMILARITY(title, '$title_escaped')) AS ms
		  FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id AND
		  date_entered >= NOW() - interval '1 day' AND
		  feed_id != $feed_id AND
		  owner_uid = $owner_uid");

		$similarity_result = db_fetch_result($result, 0, "ms");

		//_debug("similarity result: $similarity_result");

		if ($similarity_result >= $similarity) {
			$article["force_catchup"] = true;
		}

		return $article;

	}

	function api_version() {
		return 2;
	}

}
?>
