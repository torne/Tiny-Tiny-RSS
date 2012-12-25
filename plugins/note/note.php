<?php
class Note extends Plugin {
	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Adds support for setting article notes",
			"fox");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/note.js");
	}


	function hook_article_button($line) {
		return "<img src=\"".theme_image($this->link, "plugins/note/note.png")."\"
			style=\"cursor : pointer\" style=\"cursor : pointer\"
			onclick=\"editArticleNote(".$line["id"].")\"
			class='tagsPic' title='".__('Edit article note')."'>";
	}

	function edit() {
		$param = db_escape_string($_REQUEST['param']);

		$result = db_query($this->link, "SELECT note FROM ttrss_user_entries WHERE
			ref_id = '$param' AND owner_uid = " . $_SESSION['uid']);

		$note = db_fetch_result($result, 0, "note");

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"id\" value=\"$param\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"setNote\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"note\">";

		print "<table width='100%'><tr><td>";
		print "<textarea dojoType=\"dijit.form.SimpleTextarea\"
			style='font-size : 12px; width : 100%; height: 100px;'
			placeHolder='body#ttrssMain { font-size : 14px; };'
			name='note'>$note</textarea>";
		print "</td></tr></table>";

		print "<div class='dlgButtons'>";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editNoteDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\"
			onclick=\"dijit.byId('editNoteDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";

	}

	function setNote() {
		$id = db_escape_string($_REQUEST["id"]);
		$note = trim(strip_tags(db_escape_string($_REQUEST["note"])));

		db_query($this->link, "UPDATE ttrss_user_entries SET note = '$note'
			WHERE ref_id = '$id' AND owner_uid = " . $_SESSION["uid"]);

		$formatted_note = format_article_note($id, $note);

		print json_encode(array("note" => $formatted_note,
				"raw_length" => mb_strlen($note)));
	}

}
?>
