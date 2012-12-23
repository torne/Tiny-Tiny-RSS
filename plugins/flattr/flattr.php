<?php
class Flattr {
	private $link;
	private $host;

	function __construct($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

  function hook_article_button($line) {

	$article_id = $line["id"];

    $result = db_query($this->link, "SELECT link
      FROM ttrss_entries, ttrss_user_entries
      WHERE id = '$article_id' AND ref_id = id AND owner_uid = " .$_SESSION['uid']);

    if (db_num_rows($result) != 0) {
      $article_link = db_fetch_result($result, 0, 'link');
    }

    $response = null;
    if ($article_link) {
      $encoded = urlencode($article_link);
      $r = file_get_contents("https://api.flattr.com/rest/v2/things/lookup/?url=$encoded");
      $response = json_decode($r, true);
    }

    $rv = null;
    if ($response and array_key_exists('link', $response)) {
      $rv = "<a id='flattr' href='" . $response['link'] . "'>
        <img src=\"".theme_image($this->link, 'plugins/flattr/flattr.png')."\"
        class='tagsPic' style=\"cursor : pointer\"
        title='".__('Flattr article')."'>
        </a>";
    } else {
      $rv = "";
    }

    return $rv;
  }
}
?>
