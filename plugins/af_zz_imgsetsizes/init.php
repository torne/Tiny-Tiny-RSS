<?php
class Af_Zz_ImgSetSizes extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Set width/height attributes for images in articles (requires CURL and GD)",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		if (function_exists("curl_init") && function_exists("getimagesize")) {
			$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
		}
	}

	function hook_article_filter($article) {

		$owner_uid = $article["owner_uid"];

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $article["content"]);

		$found = false;

		if ($doc) {
			$xpath = new DOMXpath($doc);

			$images = $xpath->query('(//img[@src])');

			foreach ($images as $img) {
				$src = $img->getAttribute("src");

				$ch = curl_init($src);
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
				curl_setopt($ch, CURLOPT_RANGE, "0-32768");

				@$result = curl_exec($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				if ($result && ($http_code == 200 || $http_code == 206)) {
					$filename = tempnam(sys_get_temp_dir(), "ttsizecheck");

					if ($filename) {
						$fh = fopen($filename, "w");
						if ($fh) {
							fwrite($fh, $result);
							fclose($fh);

							@$info = getimagesize($filename);

							if ($info && $info[0] > 0 && $info[1] > 0) {
								$img->setAttribute("width", $info[0]);
								$img->setAttribute("height", $info[1]);
								$found = true;
							}

							unlink($filename);
						}
					}
				}
			}

			if ($found) {
				$doc->removeChild($doc->firstChild); //remove doctype
				$article["content"] = $doc->saveHTML();
			}
		}

		return $article;

	}


	function api_version() {
		return 2;
	}

}
?>
