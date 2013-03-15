<?php
	function __autoload($class) {
		$class_file1 = str_replace("_", "/", basename($class));   // PSR-0
		$class_file2 = str_replace("_", "/", strtolower(basename($class)));

		$file1 = dirname(__FILE__)."/../classes/$class_file1.php";
		$file2 = dirname(__FILE__)."/../classes/$class_file2.php";

		if (file_exists($file1)) {
			require $file1;
		} elseif (file_exists($file2)) {
			require $file2;
		}
	}
?>
