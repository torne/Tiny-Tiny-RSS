<?php
// TODO: make configurable
require_once "classes/logger.php";
require_once "classes/logger/sql.php";

function ttrss_error_handler($errno, $errstr, $file, $line, $context) {
	global $logger;

	if (!$logger) $logger = new Logger_SQL();

	$errfile = str_replace(dirname(dirname(__FILE__)), "", $errfile);

	if ($logger) {
		return $logger->log_error($errno, $errstr, $file, $line, $context);
	}

	return false;
}

function ttrss_fatal_handler() {
	global $logger;

	$file		= "UNKNOWN FILE";
	$errstr  = "UNKNOWN";
	$errno   = E_CORE_ERROR;
	$line		= -1;

	$error = error_get_last();

	if ($error !== NULL) {
		$errno   = $error["type"];
		$file = $error["file"];
		$line = $error["line"];
		$errstr  = $error["message"];

		$context = debug_backtrace();

		$file = str_replace(dirname(dirname(__FILE__)) . "/", "", $file);

		if (!$logger) $logger = new Logger_SQL();

		if ($logger) {
			$logger->log_error($errno, $errstr, $file, $line, $context);
		}
	}
}

register_shutdown_function('ttrss_fatal_handler');
set_error_handler('ttrss_error_handler');
?>
