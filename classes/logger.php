<?php
class Logger {

	public static $errornames = array(
		1			=> 'E_ERROR',
		2			=> 'E_WARNING',
		8			=> 'E_NOTICE',
		256		=> 'E_USER_ERROR',
		512		=> 'E_USER_WARNING',
		1024		=> 'E_USER_NOTICE',
		2048		=> 'E_STRICT',
		4096		=> 'E_RECOVERABLE_ERROR',
		8192		=> 'E_DEPRECATED',
		16384		=> 'E_USER_DEPRECATED',
		32767		=> 'E_ALL');

	function log_error($errno, $errstr, $file, $line, $context) {
		return false;
	}

	function log($string) {
		return false;
	}
}
?>
