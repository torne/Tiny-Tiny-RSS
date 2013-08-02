<?php
class Cache_Starred_Images extends Plugin {

	private $host;
	private $cache_dir;

	function about() {
		return array(1.0,
			"Automatically cache images in Starred articles",
			"fox",
			true);
	}

	function init($host) {
		$this->host = $host;

		$this->cache_dir = CACHE_DIR . "/starred-images/";

		if (!is_dir($this->cache_dir)) {
			mkdir($this->cache_dir);
		}

		if (is_dir($this->cache_dir)) {
			chmod($this->cache_dir, 0777);

			if (is_writable($this->cache_dir)) {
				$host->add_hook($host::HOOK_UPDATE_TASK, $this);
				$host->add_hook($host::HOOK_SANITIZE, $this);
			} else {
				user_error("Starred cache directory is not writable.", E_USER_WARNING);
			}

		} else {
			user_error("Unable to create starred cache directory.", E_USER_WARNING);
		}
	}

	function image() {
		$hash = basename($_REQUEST["hash"]);

		if ($hash) {

			$filename = $this->cache_dir . "/" . $hash . '.png';

			if (file_exists($filename)) {
				/* See if we can use X-Sendfile */
				$xsendfile = false;
				if (function_exists('apache_get_modules') &&
				    array_search('mod_xsendfile', apache_get_modules()))
					$xsendfile = true;

				if ($xsendfile) {
					header("X-Sendfile: $filename");
					header("Content-type: application/octet-stream");
					header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
				} else {
					header("Content-type: image/png");
					$stamp = gmdate("D, d M Y H:i:s", filemtime($filename)). " GMT";
					header("Last-Modified: $stamp", true);
					ob_clean();   // discard any data in the output buffer (if possible)
					flush();      // flush headers (if possible)
					readfile($filename);
				}
			} else {
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				echo "File not found.";
			}
		}
	}

	function hook_sanitize($doc, $site_url, $allowed_elements, $disallowed_attributes, $article_id) {
		$xpath = new DOMXpath($doc);

		if ($article_id) {
			$entries = $xpath->query('(//img[@src])');

			foreach ($entries as $entry) {
				if ($entry->hasAttribute('src')) {
					$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

					$local_filename = $this->cache_dir . $article_id . "-" . sha1($src) . ".png";

					if (file_exists($local_filename)) {
						$entry->setAttribute("src", get_self_url_prefix() .
							"/backend.php?op=pluginhandler&plugin=cache_starred_images&method=image&hash=" .
							$article_id . "-" . sha1($src));
					}

				}
			}
		}

		return $doc;
	}

	function hook_update_task() {
		header("Content-type: text/plain");

		$result = db_query("SELECT content, ttrss_user_entries.owner_uid, link, site_url, ttrss_entries.id, plugin_data
			FROM ttrss_entries, ttrss_user_entries LEFT JOIN ttrss_feeds ON
				(ttrss_user_entries.feed_id = ttrss_feeds.id)
			WHERE ref_id = ttrss_entries.id AND
				marked = true AND
				UPPER(content) LIKE '%<IMG%' AND
				site_url != '' AND
				plugin_data NOT LIKE '%starred_cache_images%'
			ORDER BY ".sql_random_function()." LIMIT 100");


		while ($line = db_fetch_assoc($result)) {
			if ($line["site_url"]) {
				$success = $this->cache_article_images($line["content"], $line["site_url"], $line["owner_uid"], $line["id"]);

				if ($success) {
					$plugin_data = db_escape_string("starred_cache_images,${line['owner_uid']}:" . $line["plugin_data"]);

					db_query("UPDATE ttrss_entries SET plugin_data = '$plugin_data' WHERE id = " . $line["id"]);
				}
			}
		}
	}

	function cache_article_images($content, $site_url, $owner_uid, $article_id) {
		libxml_use_internal_errors(true);

		$charset_hack = '<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		</head>';

		$doc = new DOMDocument();
		$doc->loadHTML($charset_hack . $content);
		$xpath = new DOMXPath($doc);

		$entries = $xpath->query('(//img[@src])');

		$success = false;
		$has_images = false;

		foreach ($entries as $entry) {
			if ($entry->hasAttribute('src')) {
				$has_images = true;
				$src = rewrite_relative_url($site_url, $entry->getAttribute('src'));

				$local_filename = $this->cache_dir . $article_id . "-" . sha1($src) . ".png";

				//_debug("cache_images: downloading: $src to $local_filename");

				if (!file_exists($local_filename)) {
					$file_content = fetch_file_contents($src);

					if ($file_content && strlen($file_content) > 0) {
						file_put_contents($local_filename, $file_content);
						$success = true;
					}
				} else {
					$success = true;
				}
			}
		}

		return $success || !$has_images;
	}

	function api_version() {
		return 2;
	}
}
?>
