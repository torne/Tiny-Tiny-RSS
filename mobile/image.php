<?php
	set_include_path(get_include_path() . PATH_SEPARATOR .
		dirname(__FILE__) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . PATH_SEPARATOR .
		dirname(dirname(__FILE__)) . "/include" );

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
