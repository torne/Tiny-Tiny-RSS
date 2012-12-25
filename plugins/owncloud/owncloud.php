<?php
require_once "config.php";

class OwnCloud extends Plugin {
  private $link;
  private $host;

  function about() {
    return array(1.0,
		 "Adds support for OwnCloud ReadLater",
		 "cy8aer");
  }

  function init($host) {
    $this->link = $host->get_link();
    $this->host = $host;

    $host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
  }

  function get_js() {
    return file_get_contents(dirname(__FILE__) . "/owncloud.js");
  }

  function hook_article_button($line) {
    return "<img src=\"".theme_image($this->link, "plugins/owncloud/owncloud.png")."\"
             style=\"cursor : pointer\" style=\"cursor : pointer\"
             onclick=\"ownArticle(".$line["id"].")\"
             class='tagsPic' title='".__('Bookmark on OwnCloud ')."'>";
  }

  function getOwnCloud() {
    $id = db_escape_string($_REQUEST['id']);
    
    $result = db_query($this->link, "SELECT title, link
		      FROM ttrss_entries, ttrss_user_entries
		      WHERE id = '$id' AND ref_id = id AND owner_uid = " .$_SESSION['uid']);
    
    if (db_num_rows($result) != 0) {
      $title = truncate_string(strip_tags(db_fetch_result($result, 0, 'title')),
			       100, '...');
      $article_link = db_fetch_result($result, 0, 'link');
    }
    
    $own_url = "";
    if (defined('OWNCLOUD_URL')) {
      $own_url = OWNCLOUD_URL;
    }

    print json_encode(array("title" => $title, "link" => $article_link,
			    "id" => $id, "ownurl" => $own_url));
  }
}
?>
