<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "config.php";

	// backwards compatible wrapper for old-style image caching
	/* if (isset($_GET['url'])) {
		$url = base64_decode($_GET['url']);

		$filename = CACHE_DIR . '/images/' . sha1($url) . '.png';

		if (file_exists($filename)) {
			header("Content-type: image/png");
			echo file_get_contents($filename);
		} else {
			header("Location: $url");
		}

		return;
	} */

	@$hash = basename($_GET['hash']);

	if ($hash) {

		$filename = CACHE_DIR . '/images/' . $hash . '.png';

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
				readfile($filename);
			}
		} else {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			echo "File not found.";
		}
	}

?>
