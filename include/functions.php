<?php
	define('EXPECTED_CONFIG_VERSION', 26);
	define('SCHEMA_VERSION', 124);

	define('LABEL_BASE_INDEX', -1024);
	define('PLUGIN_FEED_BASE_INDEX', -128);

	define('COOKIE_LIFETIME_LONG', 86400*365);

	$fetch_last_error = false;
	$fetch_last_error_code = false;
	$fetch_last_content_type = false;
	$fetch_curl_used = false;
	$suppress_debugging = false;

	mb_internal_encoding("UTF-8");
	date_default_timezone_set('UTC');
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	} else {
		error_reporting(E_ALL & ~E_NOTICE);
	}

	require_once 'config.php';

	/**
	 * Define a constant if not already defined
	 *
	 * @param string $name The constant name.
	 * @param mixed $value The constant value.
	 * @access public
	 * @return boolean True if defined successfully or not.
	 */
	function define_default($name, $value) {
		defined($name) or define($name, $value);
	}

	///// Some defaults that you can override in config.php //////

	define_default('FEED_FETCH_TIMEOUT', 45);
	// How may seconds to wait for response when requesting feed from a site
	define_default('FEED_FETCH_NO_CACHE_TIMEOUT', 15);
	// How may seconds to wait for response when requesting feed from a
	// site when that feed wasn't cached before
	define_default('FILE_FETCH_TIMEOUT', 45);
	// Default timeout when fetching files from remote sites
	define_default('FILE_FETCH_CONNECT_TIMEOUT', 15);
	// How many seconds to wait for initial response from website when
	// fetching files from remote sites

	if (DB_TYPE == "pgsql") {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING_FOR_DATE');
	} else {
		define('SUBSTRING_FOR_DATE', 'SUBSTRING');
	}

	/**
	 * Return available translations names.
	 *
	 * @access public
	 * @return array A array of available translations.
	 */
	function get_translations() {
		$tr = array(
					"auto"  => "Detect automatically",
					"ca_CA" => "Català",
					"cs_CZ" => "Česky",
					"en_US" => "English",
					"es_ES" => "Español",
					"de_DE" => "Deutsch",
					"fr_FR" => "Français",
					"hu_HU" => "Magyar (Hungarian)",
					"it_IT" => "Italiano",
					"ja_JP" => "日本語 (Japanese)",
					"lv_LV" => "Latviešu",
					"nb_NO" => "Norwegian bokmål",
					"nl_NL" => "Dutch",
					"pl_PL" => "Polski",
					"ru_RU" => "Русский",
					"pt_BR" => "Portuguese/Brazil",
					"pt_PT" => "Portuguese/Portugal",
					"zh_CN" => "Simplified Chinese",
					"zh_TW" => "Traditional Chinese",
					"sv_SE" => "Svenska",
					"fi_FI" => "Suomi",
					"tr_TR" => "Türkçe");

		return $tr;
	}

	require_once "lib/accept-to-gettext.php";
	require_once "lib/gettext/gettext.inc";

	require_once "lib/languagedetect/LanguageDetect.php";

	function startup_gettext() {

		# Get locale from Accept-Language header
		$lang = al2gt(array_keys(get_translations()), "text/html");

		if (defined('_TRANSLATION_OVERRIDE_DEFAULT')) {
			$lang = _TRANSLATION_OVERRIDE_DEFAULT;
		}

		if ($_SESSION["uid"] && get_schema_version() >= 120) {
			$pref_lang = get_pref("USER_LANGUAGE", $_SESSION["uid"]);

			if ($pref_lang && $pref_lang != 'auto') {
				$lang = $pref_lang;
			}
		}

		if ($lang) {
			if (defined('LC_MESSAGES')) {
				_setlocale(LC_MESSAGES, $lang);
			} else if (defined('LC_ALL')) {
				_setlocale(LC_ALL, $lang);
			}

			_bindtextdomain("messages", "locale");

			_textdomain("messages");
			_bind_textdomain_codeset("messages", "UTF-8");
		}
	}

	require_once 'db-prefs.php';
	require_once 'version.php';
	require_once 'ccache.php';
	require_once 'labels.php';

	define('SELF_USER_AGENT', 'Tiny Tiny RSS/' . VERSION . ' (http://tt-rss.org/)');
	ini_set('user_agent', SELF_USER_AGENT);

	require_once 'lib/pubsubhubbub/publisher.php';

	$schema_version = false;

	function _debug_suppress($suppress) {
		global $suppress_debugging;

		$suppress_debugging = $suppress;
	}

	/**
	 * Print a timestamped debug message.
	 *
	 * @param string $msg The debug message.
	 * @return void
	 */
	function _debug($msg, $show = true) {
		global $suppress_debugging;

		//echo "[$suppress_debugging] $msg $show\n";

		if ($suppress_debugging) return false;

		$ts = strftime("%H:%M:%S", time());
		if (function_exists('posix_getpid')) {
			$ts = "$ts/" . posix_getpid();
		}

		if ($show && !(defined('QUIET') && QUIET)) {
			print "[$ts] $msg\n";
		}

		if (defined('LOGFILE'))  {
			$fp = fopen(LOGFILE, 'a+');

			if ($fp) {
				$locked = false;

				if (function_exists("flock")) {
					$tries = 0;

					// try to lock logfile for writing
					while ($tries < 5 && !$locked = flock($fp, LOCK_EX | LOCK_NB)) {
						sleep(1);
						++$tries;
					}

					if (!$locked) {
						fclose($fp);
						return;
					}
				}

				fputs($fp, "[$ts] $msg\n");

				if (function_exists("flock")) {
					flock($fp, LOCK_UN);
				}

				fclose($fp);
			}
		}

	} // function _debug

	/**
	 * Purge a feed old posts.
	 *
	 * @param mixed $link A database connection.
	 * @param mixed $feed_id The id of the purged feed.
	 * @param mixed $purge_interval Olderness of purged posts.
	 * @param boolean $debug Set to True to enable the debug. False by default.
	 * @access public
	 * @return void
	 */
	function purge_feed($feed_id, $purge_interval, $debug = false) {

		if (!$purge_interval) $purge_interval = feed_purge_interval($feed_id);

		$rows = -1;

		$result = db_query(
			"SELECT owner_uid FROM ttrss_feeds WHERE id = '$feed_id'");

		$owner_uid = false;

		if (db_num_rows($result) == 1) {
			$owner_uid = db_fetch_result($result, 0, "owner_uid");
		}

		if ($purge_interval == -1 || !$purge_interval) {
			if ($owner_uid) {
				ccache_update($feed_id, $owner_uid);
			}
			return;
		}

		if (!$owner_uid) return;

		if (FORCE_ARTICLE_PURGE == 0) {
			$purge_unread = get_pref("PURGE_UNREAD_ARTICLES",
				$owner_uid, false);
		} else {
			$purge_unread = true;
			$purge_interval = FORCE_ARTICLE_PURGE;
		}

		if (!$purge_unread) $query_limit = " unread = false AND ";

		if (DB_TYPE == "pgsql") {
			$pg_version = get_pgsql_version();

			if (preg_match("/^7\./", $pg_version) || preg_match("/^8\.0/", $pg_version)) {

				$result = db_query("DELETE FROM ttrss_user_entries WHERE
					ttrss_entries.id = ref_id AND
					marked = false AND
					feed_id = '$feed_id' AND
					$query_limit
					ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'");

			} else {

				$result = db_query("DELETE FROM ttrss_user_entries
					USING ttrss_entries
					WHERE ttrss_entries.id = ref_id AND
					marked = false AND
					feed_id = '$feed_id' AND
					$query_limit
					ttrss_entries.date_updated < NOW() - INTERVAL '$purge_interval days'");
			}

		} else {

/*			$result = db_query("DELETE FROM ttrss_user_entries WHERE
				marked = false AND feed_id = '$feed_id' AND
				(SELECT date_updated FROM ttrss_entries WHERE
					id = ref_id) < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)"); */

			$result = db_query("DELETE FROM ttrss_user_entries
				USING ttrss_user_entries, ttrss_entries
				WHERE ttrss_entries.id = ref_id AND
				marked = false AND
				feed_id = '$feed_id' AND
				$query_limit
				ttrss_entries.date_updated < DATE_SUB(NOW(), INTERVAL $purge_interval DAY)");
		}

		$rows = db_affected_rows($result);

		ccache_update($feed_id, $owner_uid);

		if ($debug) {
			_debug("Purged feed $feed_id ($purge_interval): deleted $rows articles");
		}

		return $rows;
	} // function purge_feed

	function feed_purge_interval($feed_id) {

		$result = db_query("SELECT purge_interval, owner_uid FROM ttrss_feeds
			WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$purge_interval = db_fetch_result($result, 0, "purge_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($purge_interval == 0) $purge_interval = get_pref(
				'PURGE_OLD_DAYS', $owner_uid);

			return $purge_interval;

		} else {
			return -1;
		}
	}

	function purge_orphans($do_output = false) {

		// purge orphaned posts in main content table
		$result = db_query("DELETE FROM ttrss_entries WHERE
			(SELECT COUNT(int_id) FROM ttrss_user_entries WHERE ref_id = id) = 0");

		if ($do_output) {
			$rows = db_affected_rows($result);
			_debug("Purged $rows orphaned posts.");
		}
	}

	function get_feed_update_interval($feed_id) {
		$result = db_query("SELECT owner_uid, update_interval FROM
			ttrss_feeds WHERE id = '$feed_id'");

		if (db_num_rows($result) == 1) {
			$update_interval = db_fetch_result($result, 0, "update_interval");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			if ($update_interval != 0) {
				return $update_interval;
			} else {
				return get_pref('DEFAULT_UPDATE_INTERVAL', $owner_uid, false);
			}

		} else {
			return -1;
		}
	}

	function fetch_file_contents($url, $type = false, $login = false, $pass = false, $post_query = false, $timeout = false, $timestamp = 0, $useragent = false) {

		global $fetch_last_error;
		global $fetch_last_error_code;
		global $fetch_last_content_type;
		global $fetch_curl_used;

		$url = str_replace(' ', '%20', $url);

		if (!defined('NO_CURL') && function_exists('curl_init')) {

			$fetch_curl_used = true;

			if (ini_get("safe_mode") || ini_get("open_basedir")) {
				$new_url = geturl($url);
				if (!$new_url) {
				    // geturl has already populated $fetch_last_error
				    return false;
				}
				$ch = curl_init($new_url);
			} else {
				$ch = curl_init($url);
			}

			if ($timestamp && !$post_query) {
				curl_setopt($ch, CURLOPT_HTTPHEADER,
					array("If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T', $timestamp)));
			}

			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout ? $timeout : FILE_FETCH_CONNECT_TIMEOUT);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ? $timeout : FILE_FETCH_TIMEOUT);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !ini_get("safe_mode") && !ini_get("open_basedir"));
			curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERAGENT, $useragent ? $useragent :
				SELF_USER_AGENT);
			curl_setopt($ch, CURLOPT_ENCODING, "");
			curl_setopt($ch, CURLOPT_REFERER, $url);

			if (!ini_get("safe_mode") && !ini_get("open_basedir")) {
				curl_setopt($ch, CURLOPT_COOKIEJAR, "/dev/null");
			}

			if (defined('_CURL_HTTP_PROXY')) {
				curl_setopt($ch, CURLOPT_PROXY, _CURL_HTTP_PROXY);
			}

			if ($post_query) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_query);
			}

			if ((OPENSSL_VERSION_NUMBER >= 0x0090808f) && (OPENSSL_VERSION_NUMBER < 0x10000000)) {
				curl_setopt($ch, CURLOPT_SSLVERSION, 3);
			}

			if ($login && $pass)
				curl_setopt($ch, CURLOPT_USERPWD, "$login:$pass");

			$contents = @curl_exec($ch);

			if (curl_errno($ch) === 23 || curl_errno($ch) === 61) {
				curl_setopt($ch, CURLOPT_ENCODING, 'none');
				$contents = @curl_exec($ch);
			}

			if ($contents === false) {
				$fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
				curl_close($ch);
				return false;
			}

			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$fetch_last_content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

			$fetch_last_error_code = $http_code;

			if ($http_code != 200 || $type && strpos($fetch_last_content_type, "$type") === false) {
				if (curl_errno($ch) != 0) {
					$fetch_last_error = curl_errno($ch) . " " . curl_error($ch);
				} else {
					$fetch_last_error = "HTTP Code: $http_code";
				}
				curl_close($ch);
				return false;
			}

			curl_close($ch);

			return $contents;
		} else {

			$fetch_curl_used = false;

			if ($login && $pass){
				$url_parts = array();

				preg_match("/(^[^:]*):\/\/(.*)/", $url, $url_parts);

				$pass = urlencode($pass);

				if ($url_parts[1] && $url_parts[2]) {
					$url = $url_parts[1] . "://$login:$pass@" . $url_parts[2];
				}
			}

			if (!$post_query && $timestamp) {
				$context = stream_context_create(array(
					'http' => array(
						'method' => 'GET',
						'header' => "If-Modified-Since: ".gmdate("D, d M Y H:i:s \\G\\M\\T\r\n", $timestamp)
					)));
			} else {
				$context = NULL;
			}

			$old_error = error_get_last();

			$data = @file_get_contents($url, false, $context);

			$fetch_last_content_type = false;  // reset if no type was sent from server
			if (isset($http_response_header) && is_array($http_response_header)) {
				foreach ($http_response_header as $h) {
					if (substr(strtolower($h), 0, 13) == 'content-type:') {
						$fetch_last_content_type = substr($h, 14);
						// don't abort here b/c there might be more than one
						// e.g. if we were being redirected -- last one is the right one
					}

					if (substr(strtolower($h), 0, 7) == 'http/1.') {
						$fetch_last_error_code = (int) substr($h, 9, 3);
					}
				}
			}

			if (!$data) {
				$error = error_get_last();

				if ($error['message'] != $old_error['message']) {
					$fetch_last_error = $error["message"];
				} else {
					$fetch_last_error = "HTTP Code: $fetch_last_error_code";
				}
			}
			return $data;
		}

	}

	/**
	 * Try to determine the favicon URL for a feed.
	 * adapted from wordpress favicon plugin by Jeff Minard (http://thecodepro.com/)
	 * http://dev.wp-plugins.org/file/favatars/trunk/favatars.php
	 *
	 * @param string $url A feed or page URL
	 * @access public
	 * @return mixed The favicon URL, or false if none was found.
	 */
	function get_favicon_url($url) {

		$favicon_url = false;

		if ($html = @fetch_file_contents($url)) {

			libxml_use_internal_errors(true);

			$doc = new DOMDocument();
			$doc->loadHTML($html);
			$xpath = new DOMXPath($doc);

			$base = $xpath->query('/html/head/base');
			foreach ($base as $b) {
				$url = $b->getAttribute("href");
				break;
			}

			$entries = $xpath->query('/html/head/link[@rel="shortcut icon" or @rel="icon"]');
			if (count($entries) > 0) {
				foreach ($entries as $entry) {
					$favicon_url = rewrite_relative_url($url, $entry->getAttribute("href"));
					break;
				}
			}
		}

		if (!$favicon_url)
			$favicon_url = rewrite_relative_url($url, "/favicon.ico");

		return $favicon_url;
	} // function get_favicon_url

	function check_feed_favicon($site_url, $feed) {
#		print "FAVICON [$site_url]: $favicon_url\n";

		$icon_file = ICONS_DIR . "/$feed.ico";

		if (!file_exists($icon_file)) {
			$favicon_url = get_favicon_url($site_url);

			if ($favicon_url) {
				// Limiting to "image" type misses those served with text/plain
				$contents = fetch_file_contents($favicon_url); // , "image");

				if ($contents) {
					// Crude image type matching.
					// Patterns gleaned from the file(1) source code.
					if (preg_match('/^\x00\x00\x01\x00/', $contents)) {
						// 0       string  \000\000\001\000        MS Windows icon resource
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa MS Windows icon resource");
					}
					elseif (preg_match('/^GIF8/', $contents)) {
						// 0       string          GIF8            GIF image data
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa GIF image");
					}
					elseif (preg_match('/^\x89PNG\x0d\x0a\x1a\x0a/', $contents)) {
						// 0       string          \x89PNG\x0d\x0a\x1a\x0a         PNG image data
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa PNG image");
					}
					elseif (preg_match('/^\xff\xd8/', $contents)) {
						// 0       beshort         0xffd8          JPEG image data
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa JPG image");
					}
					else {
						//error_log("check_feed_favicon: favicon_url=$favicon_url isa UNKNOWN type");
						$contents = "";
					}
				}

				if ($contents) {
					$fp = @fopen($icon_file, "w");

					if ($fp) {
						fwrite($fp, $contents);
						fclose($fp);
						chmod($icon_file, 0644);
					}
				}
			}
            return $icon_file;
		}
	}

	function print_select($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id=\"$id\" $attributes>";
		foreach ($values as $v) {
			if ($v == $default)
				$sel = "selected=\"1\"";
			 else
			 	$sel = "";

			$v = trim($v);

			print "<option value=\"$v\" $sel>$v</option>";
		}
		print "</select>";
	}

	function print_select_hash($id, $default, $values, $attributes = "") {
		print "<select name=\"$id\" id='$id' $attributes>";
		foreach (array_keys($values) as $v) {
			if ($v == $default)
				$sel = 'selected="selected"';
			 else
			 	$sel = "";

			$v = trim($v);

			print "<option $sel value=\"$v\">".$values[$v]."</option>";
		}

		print "</select>";
	}

	function print_radio($id, $default, $true_is, $values, $attributes = "") {
		foreach ($values as $v) {

			if ($v == $default)
				$sel = "checked";
			 else
			 	$sel = "";

			if ($v == $true_is) {
				$sel .= " value=\"1\"";
			} else {
				$sel .= " value=\"0\"";
			}

			print "<input class=\"noborder\" dojoType=\"dijit.form.RadioButton\"
				type=\"radio\" $sel $attributes name=\"$id\">&nbsp;$v&nbsp;";

		}
	}

	function initialize_user_prefs($uid, $profile = false) {

		$uid = db_escape_string($uid);

		if (!$profile) {
			$profile = "NULL";
			$profile_qpart = "AND profile IS NULL";
		} else {
			$profile_qpart = "AND profile = '$profile'";
		}

		if (get_schema_version() < 63) $profile_qpart = "";

		db_query("BEGIN");

		$result = db_query("SELECT pref_name,def_value FROM ttrss_prefs");

		$u_result = db_query("SELECT pref_name
			FROM ttrss_user_prefs WHERE owner_uid = '$uid' $profile_qpart");

		$active_prefs = array();

		while ($line = db_fetch_assoc($u_result)) {
			array_push($active_prefs, $line["pref_name"]);
		}

		while ($line = db_fetch_assoc($result)) {
			if (array_search($line["pref_name"], $active_prefs) === FALSE) {
//				print "adding " . $line["pref_name"] . "<br>";

				$line["def_value"] = db_escape_string($line["def_value"]);
				$line["pref_name"] = db_escape_string($line["pref_name"]);

				if (get_schema_version() < 63) {
					db_query("INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value) VALUES
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."')");

				} else {
					db_query("INSERT INTO ttrss_user_prefs
						(owner_uid,pref_name,value, profile) VALUES
						('$uid', '".$line["pref_name"]."','".$line["def_value"]."', $profile)");
				}

			}
		}

		db_query("COMMIT");

	}

	function get_ssl_certificate_id() {
		if ($_SERVER["REDIRECT_SSL_CLIENT_M_SERIAL"]) {
			return sha1($_SERVER["REDIRECT_SSL_CLIENT_M_SERIAL"] .
				$_SERVER["REDIRECT_SSL_CLIENT_V_START"] .
				$_SERVER["REDIRECT_SSL_CLIENT_V_END"] .
				$_SERVER["REDIRECT_SSL_CLIENT_S_DN"]);
		}
		if ($_SERVER["SSL_CLIENT_M_SERIAL"]) {
			return sha1($_SERVER["SSL_CLIENT_M_SERIAL"] .
				$_SERVER["SSL_CLIENT_V_START"] .
				$_SERVER["SSL_CLIENT_V_END"] .
				$_SERVER["SSL_CLIENT_S_DN"]);
		}
		return "";
	}

	function authenticate_user($login, $password, $check_only = false) {

		if (!SINGLE_USER_MODE) {
			$user_id = false;

			foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_AUTH_USER) as $plugin) {

				$user_id = (int) $plugin->authenticate($login, $password);

				if ($user_id) {
					$_SESSION["auth_module"] = strtolower(get_class($plugin));
					break;
				}
			}

			if ($user_id && !$check_only) {
				@session_start();

				$_SESSION["uid"] = $user_id;
				$_SESSION["version"] = VERSION_STATIC;

				$result = db_query("SELECT login,access_level,pwd_hash FROM ttrss_users
					WHERE id = '$user_id'");

				$_SESSION["name"] = db_fetch_result($result, 0, "login");
				$_SESSION["access_level"] = db_fetch_result($result, 0, "access_level");
				$_SESSION["csrf_token"] = uniqid(rand(), true);

				db_query("UPDATE ttrss_users SET last_login = NOW() WHERE id = " .
					$_SESSION["uid"]);

				$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];
				$_SESSION["user_agent"] = sha1($_SERVER['HTTP_USER_AGENT']);
				$_SESSION["pwd_hash"] = db_fetch_result($result, 0, "pwd_hash");

				$_SESSION["last_version_check"] = time();

				initialize_user_prefs($_SESSION["uid"]);

				return true;
			}

			return false;

		} else {

			$_SESSION["uid"] = 1;
			$_SESSION["name"] = "admin";
			$_SESSION["access_level"] = 10;

			$_SESSION["hide_hello"] = true;
			$_SESSION["hide_logout"] = true;

			$_SESSION["auth_module"] = false;

			if (!$_SESSION["csrf_token"]) {
				$_SESSION["csrf_token"] = uniqid(rand(), true);
			}

			$_SESSION["ip_address"] = $_SERVER["REMOTE_ADDR"];

			initialize_user_prefs($_SESSION["uid"]);

			return true;
		}
	}

	function make_password($length = 8) {

		$password = "";
		$possible = "0123456789abcdfghjkmnpqrstvwxyzABCDFGHJKMNPQRSTVWXYZ";

   	$i = 0;

		while ($i < $length) {
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);

			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}

	// this is called after user is created to initialize default feeds, labels
	// or whatever else

	// user preferences are checked on every login, not here

	function initialize_user($uid) {

		db_query("insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: New Releases',
			'http://tt-rss.org/releases.rss')");

		db_query("insert into ttrss_feeds (owner_uid,title,feed_url)
			values ('$uid', 'Tiny Tiny RSS: Forum',
				'http://tt-rss.org/forum/rss.php')");
	}

	function logout_user() {
		session_destroy();
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
	}

	function validate_csrf($csrf_token) {
		return $csrf_token == $_SESSION['csrf_token'];
	}

	function load_user_plugins($owner_uid) {
		if ($owner_uid && SCHEMA_VERSION >= 100) {
			$plugins = get_pref("_ENABLED_PLUGINS", $owner_uid);

			PluginHost::getInstance()->load($plugins, PluginHost::KIND_USER, $owner_uid);

			if (get_schema_version() > 100) {
				PluginHost::getInstance()->load_data();
			}
		}
	}

	function login_sequence() {
		if (SINGLE_USER_MODE) {
			@session_start();
			authenticate_user("admin", null);
			startup_gettext();
			load_user_plugins($_SESSION["uid"]);
		} else {
			if (!validate_session()) $_SESSION["uid"] = false;

			if (!$_SESSION["uid"]) {

				if (AUTH_AUTO_LOGIN && authenticate_user(null, null)) {
				    $_SESSION["ref_schema_version"] = get_schema_version(true);
				} else {
					 authenticate_user(null, null, true);
				}

				if (!$_SESSION["uid"]) {
					@session_destroy();
					setcookie(session_name(), '', time()-42000, '/');

					render_login_form();
					exit;
				}

			} else {
				/* bump login timestamp */
				db_query("UPDATE ttrss_users SET last_login = NOW() WHERE id = " .
					$_SESSION["uid"]);
				$_SESSION["last_login_update"] = time();
			}

			if ($_SESSION["uid"]) {
				startup_gettext();
				load_user_plugins($_SESSION["uid"]);

				/* cleanup ccache */

				db_query("DELETE FROM ttrss_counters_cache WHERE owner_uid = ".
					$_SESSION["uid"] . " AND
						(SELECT COUNT(id) FROM ttrss_feeds WHERE
							ttrss_feeds.id = feed_id) = 0");

				db_query("DELETE FROM ttrss_cat_counters_cache WHERE owner_uid = ".
					$_SESSION["uid"] . " AND
						(SELECT COUNT(id) FROM ttrss_feed_categories WHERE
							ttrss_feed_categories.id = feed_id) = 0");

			}

		}
	}

	function truncate_string($str, $max_len, $suffix = '&hellip;') {
		if (mb_strlen($str, "utf-8") > $max_len) {
			return mb_substr($str, 0, $max_len, "utf-8") . $suffix;
		} else {
			return $str;
		}
	}

	function convert_timestamp($timestamp, $source_tz, $dest_tz) {

		try {
			$source_tz = new DateTimeZone($source_tz);
		} catch (Exception $e) {
			$source_tz = new DateTimeZone('UTC');
		}

		try {
			$dest_tz = new DateTimeZone($dest_tz);
		} catch (Exception $e) {
			$dest_tz = new DateTimeZone('UTC');
		}

		$dt = new DateTime(date('Y-m-d H:i:s', $timestamp), $source_tz);
		return $dt->format('U') + $dest_tz->getOffset($dt);
	}

	function make_local_datetime($timestamp, $long, $owner_uid = false,
					$no_smart_dt = false) {

		if (!$owner_uid) $owner_uid = $_SESSION['uid'];
		if (!$timestamp) $timestamp = '1970-01-01 0:00';

		global $utc_tz;
		global $user_tz;

		if (!$utc_tz) $utc_tz = new DateTimeZone('UTC');

		$timestamp = substr($timestamp, 0, 19);

		# We store date in UTC internally
		$dt = new DateTime($timestamp, $utc_tz);

		$user_tz_string = get_pref('USER_TIMEZONE', $owner_uid);

		if ($user_tz_string != 'Automatic') {

			try {
				if (!$user_tz) $user_tz = new DateTimeZone($user_tz_string);
			} catch (Exception $e) {
				$user_tz = $utc_tz;
			}

			$tz_offset = $user_tz->getOffset($dt);
		} else {
			$tz_offset = (int) -$_SESSION["clientTzOffset"];
		}

		$user_timestamp = $dt->format('U') + $tz_offset;

		if (!$no_smart_dt) {
			return smart_date_time($user_timestamp,
				$tz_offset, $owner_uid);
		} else {
			if ($long)
				$format = get_pref('LONG_DATE_FORMAT', $owner_uid);
			else
				$format = get_pref('SHORT_DATE_FORMAT', $owner_uid);

			return date($format, $user_timestamp);
		}
	}

	function smart_date_time($timestamp, $tz_offset = 0, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION['uid'];

		if (date("Y.m.d", $timestamp) == date("Y.m.d", time() + $tz_offset)) {
			return date("G:i", $timestamp);
		} else if (date("Y", $timestamp) == date("Y", time() + $tz_offset)) {
			$format = get_pref('SHORT_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		} else {
			$format = get_pref('LONG_DATE_FORMAT', $owner_uid);
			return date($format, $timestamp);
		}
	}

	function sql_bool_to_bool($s) {
		if ($s == "t" || $s == "1" || strtolower($s) == "true") {
			return true;
		} else {
			return false;
		}
	}

	function bool_to_sql_bool($s) {
		if ($s) {
			return "true";
		} else {
			return "false";
		}
	}

	// Session caching removed due to causing wrong redirects to upgrade
	// script when get_schema_version() is called on an obsolete session
	// created on a previous schema version.
	function get_schema_version($nocache = false) {
		global $schema_version;

		if (!$schema_version && !$nocache) {
			$result = db_query("SELECT schema_version FROM ttrss_version");
			$version = db_fetch_result($result, 0, "schema_version");
			$schema_version = $version;
			return $version;
		} else {
			return $schema_version;
		}
	}

	function sanity_check() {
		require_once 'errors.php';
		global $ERRORS;

		$error_code = 0;
		$schema_version = get_schema_version(true);

		if ($schema_version != SCHEMA_VERSION) {
			$error_code = 5;
		}

		if (DB_TYPE == "mysql") {
			$result = db_query("SELECT true", false);
			if (db_num_rows($result) != 1) {
				$error_code = 10;
			}
		}

		if (db_escape_string("testTEST") != "testTEST") {
			$error_code = 12;
		}

		return array("code" => $error_code, "message" => $ERRORS[$error_code]);
	}

	function file_is_locked($filename) {
		if (file_exists(LOCK_DIRECTORY . "/$filename")) {
			if (function_exists('flock')) {
				$fp = @fopen(LOCK_DIRECTORY . "/$filename", "r");
				if ($fp) {
					if (flock($fp, LOCK_EX | LOCK_NB)) {
						flock($fp, LOCK_UN);
						fclose($fp);
						return false;
					}
					fclose($fp);
					return true;
				} else {
					return false;
				}
			}
			return true; // consider the file always locked and skip the test
		} else {
			return false;
		}
	}


	function make_lockfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
			$stat_h = fstat($fp);
			$stat_f = stat(LOCK_DIRECTORY . "/$filename");

			if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
				if ($stat_h["ino"] != $stat_f["ino"] ||
						$stat_h["dev"] != $stat_f["dev"]) {

					return false;
				}
			}

			if (function_exists('posix_getpid')) {
				fwrite($fp, posix_getpid() . "\n");
			}
			return $fp;
		} else {
			return false;
		}
	}

	function make_stampfile($filename) {
		$fp = fopen(LOCK_DIRECTORY . "/$filename", "w");

		if (flock($fp, LOCK_EX | LOCK_NB)) {
			fwrite($fp, time() . "\n");
			flock($fp, LOCK_UN);
			fclose($fp);
			return true;
		} else {
			return false;
		}
	}

	function sql_random_function() {
		if (DB_TYPE == "mysql") {
			return "RAND()";
		} else {
			return "RANDOM()";
		}
	}

	function catchup_feed($feed, $cat_view, $owner_uid = false, $max_id = false, $mode = 'all') {

			if (!$owner_uid) $owner_uid = $_SESSION['uid'];

			//if (preg_match("/^-?[0-9][0-9]*$/", $feed) != false) {

			// Todo: all this interval stuff needs some generic generator function

			$date_qpart = "false";

			switch ($mode) {
			case "1day":
				if (DB_TYPE == "pgsql") {
					$date_qpart = "date_entered < NOW() - INTERVAL '1 day' ";
				} else {
					$date_qpart = "date_entered < DATE_SUB(NOW(), INTERVAL 1 DAY) ";
				}
				break;
			case "1week":
				if (DB_TYPE == "pgsql") {
					$date_qpart = "date_entered < NOW() - INTERVAL '1 week' ";
				} else {
					$date_qpart = "date_entered < DATE_SUB(NOW(), INTERVAL 1 WEEK) ";
				}
				break;
			case "2week":
				if (DB_TYPE == "pgsql") {
					$date_qpart = "date_entered < NOW() - INTERVAL '2 week' ";
				} else {
					$date_qpart = "date_entered < DATE_SUB(NOW(), INTERVAL 2 WEEK) ";
				}
				break;
			default:
				$date_qpart = "true";
			}

			if (is_numeric($feed)) {
				if ($cat_view) {

					if ($feed >= 0) {

						if ($feed > 0) {
							$children = getChildCategories($feed, $owner_uid);
							array_push($children, $feed);

							$children = join(",", $children);

							$cat_qpart = "cat_id IN ($children)";
						} else {
							$cat_qpart = "cat_id IS NULL";
						}

						db_query("UPDATE ttrss_user_entries
							SET unread = false, last_read = NOW() WHERE ref_id IN
								(SELECT id FROM
									(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
										AND owner_uid = $owner_uid AND unread = true AND feed_id IN
											(SELECT id FROM ttrss_feeds WHERE $cat_qpart) AND $date_qpart) as tmp)");

					} else if ($feed == -2) {

						db_query("UPDATE ttrss_user_entries
							SET unread = false,last_read = NOW() WHERE (SELECT COUNT(*)
								FROM ttrss_user_labels2, ttrss_entries WHERE article_id = ref_id AND id = ref_id AND $date_qpart) > 0
								AND unread = true AND owner_uid = $owner_uid");
					}

				} else if ($feed > 0) {

					db_query("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
									AND owner_uid = $owner_uid AND unread = true AND feed_id = $feed AND $date_qpart) as tmp)");

				} else if ($feed < 0 && $feed > LABEL_BASE_INDEX) { // special, like starred

					if ($feed == -1) {
						db_query("UPDATE ttrss_user_entries
							SET unread = false, last_read = NOW() WHERE ref_id IN
								(SELECT id FROM
									(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
										AND owner_uid = $owner_uid AND unread = true AND marked = true AND $date_qpart) as tmp)");
					}

					if ($feed == -2) {
						db_query("UPDATE ttrss_user_entries
							SET unread = false, last_read = NOW() WHERE ref_id IN
								(SELECT id FROM
									(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
										AND owner_uid = $owner_uid AND unread = true AND published = true AND $date_qpart) as tmp)");
					}

					if ($feed == -3) {

						$intl = get_pref("FRESH_ARTICLE_MAX_AGE");

						if (DB_TYPE == "pgsql") {
							$match_part = "date_entered > NOW() - INTERVAL '$intl hour' ";
						} else {
							$match_part = "date_entered > DATE_SUB(NOW(),
								INTERVAL $intl HOUR) ";
						}

						db_query("UPDATE ttrss_user_entries
							SET unread = false, last_read = NOW() WHERE ref_id IN
								(SELECT id FROM
									(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
										AND owner_uid = $owner_uid AND unread = true AND $date_qpart AND $match_part) as tmp)");
					}

					if ($feed == -4) {
						db_query("UPDATE ttrss_user_entries
							SET unread = false, last_read = NOW() WHERE ref_id IN
								(SELECT id FROM
									(SELECT id FROM ttrss_entries, ttrss_user_entries WHERE ref_id = id
										AND owner_uid = $owner_uid AND unread = true AND $date_qpart) as tmp)");
					}

				} else if ($feed < LABEL_BASE_INDEX) { // label

					$label_id = feed_to_label_id($feed);

					db_query("UPDATE ttrss_user_entries
						SET unread = false, last_read = NOW() WHERE ref_id IN
							(SELECT id FROM
								(SELECT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_user_labels2 WHERE ref_id = id
									AND label_id = '$label_id' AND ref_id = article_id
									AND owner_uid = $owner_uid AND unread = true AND $date_qpart) as tmp)");

				}

				ccache_update($feed, $owner_uid, $cat_view);

			} else { // tag
				db_query("UPDATE ttrss_user_entries
					SET unread = false, last_read = NOW() WHERE ref_id IN
						(SELECT id FROM
							(SELECT ttrss_entries.id FROM ttrss_entries, ttrss_user_entries, ttrss_tags WHERE ref_id = ttrss_entries.id
								AND post_int_id = int_id AND tag_name = '$feed'
								AND ttrss_user_entries.owner_uid = $owner_uid AND unread = true AND $date_qpart) as tmp)");

			}
	}

	function getAllCounters() {
		$data = getGlobalCounters();

		$data = array_merge($data, getVirtCounters());
		$data = array_merge($data, getLabelCounters());
		$data = array_merge($data, getFeedCounters());
		$data = array_merge($data, getCategoryCounters());

		return $data;
	}

	function getCategoryTitle($cat_id) 
