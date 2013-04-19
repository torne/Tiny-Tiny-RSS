<?php
function ttrss_error_handler($errno, $errstr, $file, $line, $context) {
	global $logger;

	if (error_reporting() == 0 || !$errno) return false;

	$file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);

	return Logger::get()->log_error($errno, $errstr, $file, $line, $context);
}

function ttrss_fatal_handler() {
	global $logger;

	$error = error_get_last();

	if ($error !== NULL) {
		$errno = $error["type"];
		$file = $error["file"];
		$line = $error["line"];
		$errstr  = $error["message"];

		if (!$errno) return false;

		$context = debug_backtrace();

		$file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);

		return Logger::get()->log_error($errno, $errstr, $file, $line, $context);
	}

	return false;
}

register_shutdown_function('ttrss_fatal_handler');
set_error_handler('ttrss_error_handler');
?>
