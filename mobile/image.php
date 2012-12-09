<?php
	set_include_path(dirname(__FILE__) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . "/include" . PATH_SEPARATOR .
  		get_include_path());

	require_once "config.php";

	chdir('..');

	$filename = CACHE_DIR . '/images/' . sha1($_GET['url']) . '.png';

	if (file_exists($filename)) {
		header("Content-type: image/png");
		echo file_get_contents($filename);
	} else {
		header("Location: " . $_GET['url']);
	}
?>
