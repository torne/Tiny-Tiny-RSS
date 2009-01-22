<?php
	require_once "config.php";
	require_once "lib/simplepie/simplepie.inc";

	SimplePie_Misc::display_cached_file($_GET['i'], SIMPLEPIE_CACHE_DIR, 'spi');
?>
