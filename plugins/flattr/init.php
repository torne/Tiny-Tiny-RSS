<?php
class Flattr extends Plugin {
	private $link;
	private $host;

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
	}

	function about() {
		return array(1.1,
			"Share articles on Flattr (if they exist in their catalogue)",
			"F. Eitel, N. Honing");
	}

  function hook_article_button($line) {

    $rv = "";
    $article_link = $line['link'];

    if ($article_link) {
        $encoded = urlencode($article_link);
        $r = file_get_contents("https://api.flattr.com/rest/v2/things/lookup/?url=$encoded");
        $response = json_decode($r, true);
        $image = "<img src=\"".theme_image($this->link, 'plugins/flattr/flattr.png')."\"
                       class='tagsPic' style=\"cursor : pointer\"
                       title='".__('Flattr this article.')."'>";
        // if Flattr has it in the catalogue, we display the button
        if ($response and array_key_exists('link', $response)) {
            $rv = "<a id='flattr' target='_blank' href='" . $response['link'] . "'> . $image . </a>";
        } else {
            // We can't submit a thing to the catalogue without giving a Flattr user id (who would be the owner)
            // see http://developers.flattr.net/auto-submit
            //$rv = "<a id='flattr' href='https://flattr.com/submit/auto?url=" . $encoded  . "'>" . $image . "</a>";
            $rv = '';
            // Another useful thing would be any rel=payment link (which would have the user id as well),
            // but tt-rss is not checking that (yet), I believe. See http://developers.flattr.net/feed
        }
    }
    return $rv;
  }
}
?>
