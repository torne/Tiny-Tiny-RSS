<?php
class Share_Button extends Plugin_Button {
	function render($article_id, $line) {
		return "<img src=\"".theme_image($this->link, 'images/art-share.png')."\"
			class='tagsPic' style=\"cursor : pointer\"
			onclick=\"shareArticle(".$line['int_id'].")\"
			title='".__('Share by URL')."'>";
	}

	function shareArticle() {
		$param = db_escape_string($_REQUEST['param']);

		$result = db_query($this->link, "SELECT uuid, ref_id FROM ttrss_user_entries WHERE int_id = '$param'
			AND owner_uid = " . $_SESSION['uid']);

		if (db_num_rows($result) == 0) {
			print "Article not found.";
		} else {

			$uuid = db_fetch_result($result, 0, "uuid");
			$ref_id = db_fetch_result($result, 0, "ref_id");

			if (!$uuid) {
				$uuid = db_escape_string(sha1(uniqid(rand(), true)));
				db_query($this->link, "UPDATE ttrss_user_entries SET uuid = '$uuid' WHERE int_id = '$param'
					AND owner_uid = " . $_SESSION['uid']);
			}

			print __("You can share this article by the following unique URL:");

			$url_path = get_self_url_prefix();
			$url_path .= "/public.php?op=share&key=$uuid";

			print "<div class=\"tagCloudContainer\">";
			print "<a id='pub_opml_url' href='$url_path' target='_blank'>$url_path</a>";
			print "</div>";

			/* if (!label_find_id($this->link, __('Shared'), $_SESSION["uid"]))
				label_create($this->link, __('Shared'), $_SESSION["uid"]);

			label_add_article($this->link, $ref_id, __('Shared'), $_SESSION['uid']); */
		}

		print "<div align='center'>";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('shareArticleDlg').hide()\">".
			__('Close this window')."</button>";

		print "</div>";
	}


}
?>
