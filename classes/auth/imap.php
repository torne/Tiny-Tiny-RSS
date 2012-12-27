<?php
/* Requires php-imap
	Put the following options in config.php:

	define('IMAP_AUTH_SERVER', 'your.imap.server:port');
	define('IMAP_AUTH_OPTIONS', '/tls/novalidate-cert/norsh');
	// More about options: http://php.net/manual/ru/function.imap-open.php

 */

class Auth_Imap extends Auth_Base {

	function authenticate($login, $password) {

		if ($login && $password) {
			$imap = imap_open(
				"{".IMAP_AUTH_SERVER.IMAP_AUTH_OPTIONS."}INBOX",
				$login,
				$password);

			if ($imap) {
				imap_close($imap);

				return $this->auto_create_user($login);
			}
		}

		return false;
	}

}
?>
