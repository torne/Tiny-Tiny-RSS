<?php
class Share extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Share article by unique URL",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/share.js");
	}

	function hook_article_button($line) {
		return "<img src=\"plugins/share/share.png\"
			class='tagsPic' style=\"cursor : pointer\"
			onclick=\"shareArticle(".$line['int_id'].")\"
			title='".__('Share by URL')."'>";
	}

	function shareArticle() {
		$param = db_escape_string( $_REQUEST['param']);

		$result = db_query( "SELECT uuid, ref_id FROM ttrss_user_entries WHERE int_id = '$param'
			AND owner_uid = " . $_SESSION['uid']);

		if (db_num_rows($result) == 0) {
			print "Article not found.";
		} else {

			$uuid = db_fetch_result($result, 0, "uuid");
			$ref_id = db_fetch_result($result, 0, "ref_id");

			if (!$uuid) {
				$uuid = db_escape_string( sha1(uniqid(rand(), true)));
				db_query( "UPDATE ttrss_user_entries SET uuid = '$uuid' WHERE int_id = '$param'
					AND owner_uid = " . $_SESSION['uid']);
			}

			print "<h2>". __("You can share this article by the following unique URL:") . "</h2>";

			$url_path = get_self_url_prefix();
			$url_path .= "/public.php?op=share&key=$uuid";

			print "<div class=\"tagCloudContainer\">";
			print "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
			print "</div>";

			/* if (!label_find_id( __('Shared'), $_SESSION["uid"]))
				label_create( __('Shared'), $_SESSION["uid"]);

			label_add_article( $ref_id, __('Shared'), $_SESSION['uid']); */
		}

		print "<div align='center'>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";
	}


}
?>
