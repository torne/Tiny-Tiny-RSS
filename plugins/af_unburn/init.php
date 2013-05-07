<?php
class Af_Unburn extends Plugin {
	private $host;

	function about() {
		return array(1.0,
			"Resolves feedburner and similar feed redirector URLs (requires CURL)",
			"fox");
	}

	function init($host) {
		$this->host = $host;

		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	function hook_article_filter($article) {
		$owner_uid = $article["owner_uid"];

		if (!function_exists("curl_init"))
			return $article;

		if ((strpos($article["link"], "feedproxy.google.com") !== FALSE ||
		  		strpos($article["link"], "/~r/") !== FALSE ||
				strpos($article["link"], "feedsportal.com") !== FALSE)) {

			if (strpos($article["plugin_data"], "unburn,$owner_uid:") === FALSE) {

				if (ini_get("safe_mode") || ini_get("open_basedir")) {
					$ch = curl_init(geturl($article["link"]));
				} else {
					$ch = curl_init($article["link"]);
				}

				curl_setopt($ch, CURLOPT_TIMEOUT, 5);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HEADER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !ini_get("safe_mode") && !ini_get("open_basedir"));
				curl_setopt($ch, CURLOPT_USERAGENT, SELF_USER_AGENT);

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

					$article["plugin_data"] = "unburn,$owner_uid:" . $article["plugin_data"];
					$article["link"] = $real_url;
				}
			} else if (isset($article["stored"]["link"])) {
				$article["link"] = $article["stored"]["link"];
			}
		}

		return $article;
	}

		function geturl($url){

		(function_exists('curl_init')) ? '' : die('cURL Must be installed for geturl function to work. Ask your host to enable it or uncomment extension=php_curl.dll in php.ini');

		$curl = curl_init();
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: ";

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:5.0) Gecko/20100101 Firefox/5.0 Firefox/5.0');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_REFERER, $url);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); //CURLOPT_FOLLOWLOCATION Disabled...
		curl_setopt($curl, CURLOPT_TIMEOUT, 60);

		$html = curl_exec($curl);

		$status = curl_getinfo($curl);
		curl_close($curl);

		if($status['http_code']!=200){
			if($status['http_code'] == 301 || $status['http_code'] == 302) {
				list($header) = explode("\r\n\r\n", $html, 2);
				$matches = array();
				preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
				$url = trim(str_replace($matches[1],"",$matches[0]));
				$url_parsed = parse_url($url);
				return (isset($url_parsed))? geturl($url):'';
			}
			$oline='';
			foreach($status as $key=>$eline){$oline.='['.$key.']'.$eline.' ';}
			$line =$oline." \r\n ".$url."\r\n-----------------\r\n";
			$handle = @fopen('./curl.error.log', 'a');
			fwrite($handle, $line);
			return FALSE;
		}
		return $url;
	}

	function api_version() {
		return 2;
	}

}
?>
