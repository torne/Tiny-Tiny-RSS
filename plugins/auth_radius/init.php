<?php
/*
Tiny Tiny RSS plugin for RADIUS authentication
@author alsvartr (me@taughtbycats.ru)
@copyright GPL2

Requires php radius class (comes with plugin)
Put the following options in config.php:

	define('RADIUS_AUTH_SERVER',	'radius_server_address');
	define('RADIUS_AUTH_SECRET',	'radius_shared_secret');

Optional:

	//Default: 1812
	define('RADIUS_AUTH_PORT',	radius_auth_port);
*/

class Auth_Radius extends Plugin implements IAuthModule {

	private $link;
	private $host;
	private $base;
	private $debug;

	function about() {
		return array(0.1,
			"Authenticates against an RADIUS server (configured in config.php)",
			"alsvartr",
			true);
	}

	function init($host) {
		$this->link = $host->get_link();
		$this->host = $host;
		$this->base = new Auth_Base($this->link);
		$this->debug = FALSE;

		$host->add_hook($host::HOOK_AUTH_USER, $this);
	}

	private function _log($msg) {
		if ($this->debug) trigger_error($msg, E_USER_WARNING);
	}

	function authenticate($login, $password) {
		if (!require_once('php-radius/radius.php')) {
			$this->_log('Cannot require radius class files!');
			return FALSE;
		}

		if ($login && $password) {
			if ( (!defined('RADIUS_AUTH_SERVER')) OR (!defined('RADIUS_AUTH_SECRET')) ) {
				$this->_log('Could not parse RADIUS_AUTH_ options from config.php!');
				return FALSE;
			} elseif (!defined('RADIUS_AUTH_PORT'))
				define('RADIUS_AUTH_PORT', 1812);

			$radius = new Radius(RADIUS_AUTH_SERVER, RADIUS_AUTH_SECRET, '', 5, RADIUS_AUTH_PORT);
			$radius->SetNasIpAddress('1.2.3.4');
			$auth = $radius->AccessRequest($login, $password);

			if ($auth)
				return $this->base->auto_create_user($login);
			else {
				$this->_log('Radius authentication rejected!');
				return FALSE;
			}
		}

		return FALSE;
	}

}

?>
