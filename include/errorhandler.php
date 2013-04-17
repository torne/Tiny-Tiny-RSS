<?php
// TODO: make configurable
require_once "classes/logger.php";
require_once "classes/logger/sql.php";

function ttrss_error_handler($errno, $errstr, $file, $line, $context) {
	global $logger;

	if (error_reporting() == 0 || !$errno) return false;

	if (!$logger) $logger = new Logger_SQL();

	$file = substr(str_replace(dirname(dirname(__FILE__)), "", $file), 1);

	if ($logger) {
		return $logger->log_error($errno, $errstr, $file, $line, $context);
	}

	return false;
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

		if (!$logger) $logger = new Logger_SQL();

		if ($logger) {
			if ($logger->log_error($errno, $errstr, $file, $line, $context)) {
				return true;
			}
		}
		return false;
	}

	return false;
}

//register_shutdown_function('ttrss_fatal_handler');
//set_error_handler('ttrss_error_handler');
?>
