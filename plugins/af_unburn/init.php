<?php
class Af_Unburn extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Resolve feedburner URLs (requires CURL)",
			"fox");
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (!function_exists("curl_init"))
			return $article;

		if (strpos($article["link"], "feedproxy.google.com") !== FALSE &&
			strpos($article["guid"], "unburn,$owner_uid:") === FALSE) {

			$ch = curl_init($article["link"]);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

			$contents = @curl_exec($ch);

			$real_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

			if ($real_url) {
				$article["guid"] = "unburn,$owner_uid:" . $article["guid"];
				$article["link"] = $real_url;
			}
		}

		return $article;
	}
}
?>
