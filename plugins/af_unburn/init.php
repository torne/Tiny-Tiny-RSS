<?php
class Af_Unburn extends Plugin {

	private $link;
	private $host;

	function about() {
		return array(1.0,
			"Resolves feedburner and similar feed redirector URLs (requires CURL)",
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

		if ((strpos($article["link"], "feedproxy.google.com") !== FALSE ||
		  		strpos($article["link"], "/~r/") !== FALSE ||
		  		strpos($article["link"], "feedsportal.com") !== FALSE)	&&
			strpos($article["guid"], "unburn,$owner_uid:") === FALSE) {

			$ch = curl_init($article["link"]);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

			$contents = @curl_exec($ch);

			$real_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

			curl_close($ch);

			if ($real_url) {
				/* remove the rest of it */

				$query = parse_url($real_url, PHP_URL_QUERY);

				if ($query && strpos($query, "utm_source") !== FALSE) {
					$args = array();
					parse_str($query, $args);

					foreach (array("utm_source", "utm_medium", "utm_campaign") as $param) {
						if (isset($args[$param])) unset($args[$param]);
					}

					$new_query = http_build_query($args);

					if ($new_query != $query) {
						$real_url = str_replace("?$query", "?$new_query", $real_url);
					}
				}

				$real_url = preg_replace("/\?$/", "", $real_url);

				$article["guid"] = "unburn,$owner_uid:" . $article["guid"];
				$article["link"] = $real_url;
			}
		}

		return $article;
	}
}
?>
