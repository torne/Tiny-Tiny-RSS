<?php
	function __autoload($class) {
		$class_file = str_replace("_", "/", strtolower(basename($class)));

		$file = dirname(__FILE__)."/../classes/$class_file.php";

		if (file_exists($file)) {
			require $file;
		}

	}
?>
