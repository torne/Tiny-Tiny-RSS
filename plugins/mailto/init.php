<?php
class MailTo extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Share article via email (using mailto: links, invoking your mail client)",
			"fox");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
	}

	function hook_article_button($line) {
		return "<img src=\"".theme_image($link, 'plugins/mailto/mail.png')."\"
					class='tagsPic' style=\"cursor : pointer\"
					onclick=\"mailtoArticle(".$line["id"].")\"
					alt='Zoom' title='".__('Forward by email')."'>";
	}

	function emailArticle() {

		$param = db_escape_string($_REQUEST['param']);

		require_once "lib/MiniTemplator.class.php";

		$tpl = new MiniTemplator;
		$tpl_t = new MiniTemplator;

		$tpl->readTemplateFromFile("templates/email_article_template.txt");

		$tpl->setVariable('USER_NAME', $_SESSION["name"], true);
		$tpl->setVariable('USER_EMAIL', $user_email, true);
		$tpl->setVariable('TTRSS_HOST', $_SERVER["HTTP_HOST"], true);


		$result = db_query($this->link, "SELECT link, content, title
			FROM ttrss_user_entries, ttrss_entries WHERE id = ref_id AND
			id IN ($param) AND owner_uid = " . $_SESSION["uid"]);

		if (db_num_rows($result) > 1) {
			$subject = __("[Forwarded]") . " " . __("Multiple articles");
		}

		while ($line = db_fetch_assoc($result)) {

			if (!$subject)
				$subject = __("[Forwarded]") . " " . htmlspecialchars($line["title"]);

			$tpl->setVariable('ARTICLE_TITLE', strip_tags($line["title"]));
			$tpl->setVariable('ARTICLE_URL', strip_tags($line["link"]));

			$tpl->addBlock('article');
		}

		$tpl->addBlock('email');

		$content = "";
		$tpl->generateOutputToString($content);

		$mailto_link = htmlspecialchars("mailto: ?subject=".urlencode($subject).
			"&body=".urlencode($content));

		print __("Clicking the following link to invoke your mail client:");

		print "<div class=\"tagCloudContainer\">";
		print "<a target=\"_blank\" href=\"$mailto_link\">".
			__("Forward selected article(s) by email.")."</a>";
		print "</div>";

		print __("You should be able to edit the message before sending in your mail client.");

		print "<p>";

		print "<div style='text-align : center'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"dijit.byId('emailArticleDlg').hide()\">".__('Close this dialog')."</button>";
		print "</div>";

		//return;
	}

}
?>
