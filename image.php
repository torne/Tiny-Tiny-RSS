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
			header("Content-type: image/png");
			echo file_get_contents($filename);
		} else {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			echo "File not found.";
		}
	}

?>
