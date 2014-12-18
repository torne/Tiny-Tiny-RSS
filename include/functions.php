<?php
	define('EXPECTED_CONFIG_VERSION', 26);
	define('SCHEMA_VERSION', 127);

	define('LABEL_BASE_INDEX', -1024);
	define('PLUGIN_FEED_BASE_INDEX', -128);

	define('COOKIE_LIFETIME_LONG', 86400*365);

	$fetch_last_error = false;
	$fetch_last_error_code = false;
	$fetch_last_content_type = false;
	$fetch_last_error_content = false; // curl only for the time being
	$fetch_curl_used = false;
	$suppress_debugging = false;

	libxml_disable_entity_loader(true);

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
					"ar_SA" => "العربيّة (Arabic)",
					"da_DA" => "Dansk",
					"ca_CA" => "Català",
					"cs_CZ" => "Česky",
					"en_US" => "English",
					"el_GR" => "Ελληνικά",
					"es_ES" => "Español (España)",
					"es_LA" => "Español",
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
		global $fetch_last_error_content;
		global $fetch_last_content_type;
		global $fetch_curl_used;

		$url = ltrim($url, ' ');
		$url = str_replace(' ', '%20', $url);

		if (strpos($url, "//") === 0)
			$url = 'http:' . $url;

		if (!defined('NO_CURL') && function_exists('curl_init')) {

			$fetch_curl_used = true;

			if (ini_get("safe_mode") || ini_get("open_basedir") || defined("FORCE_GETURL")) {
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
			//curl_setopt($ch, CURLOPT_REFERER, $url);

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
				$fetch_last_error_content = $contents;
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
										AND owner_uid = $owner_uid AND score >= 0 AND unread = true AND $date_qpart AND $match_part) as tmp)");
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

	function getCategoryTitle($cat_id) {

		if ($cat_id == -1) {
			return __("Special");
		} else if ($cat_id == -2) {
			return __("Labels");
		} else {

			$result = db_query("SELECT title FROM ttrss_feed_categories WHERE
				id = '$cat_id'");

			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return __("Uncategorized");
			}
		}
	}


	function getCategoryCounters() {
		$ret_arr = array();

		/* Labels category */

		$cv = array("id" => -2, "kind" => "cat",
			"counter" => getCategoryUnread(-2));

		array_push($ret_arr, $cv);

		$result = db_query("SELECT id AS cat_id, value AS unread,
			(SELECT COUNT(id) FROM ttrss_feed_categories AS c2
				WHERE c2.parent_cat = ttrss_feed_categories.id) AS num_children
			FROM ttrss_feed_categories, ttrss_cat_counters_cache
			WHERE ttrss_cat_counters_cache.feed_id = id AND
			ttrss_cat_counters_cache.owner_uid = ttrss_feed_categories.owner_uid AND
			ttrss_feed_categories.owner_uid = " . $_SESSION["uid"]);

		while ($line = db_fetch_assoc($result)) {
			$line["cat_id"] = (int) $line["cat_id"];

			if ($line["num_children"] > 0) {
				$child_counter = getCategoryChildrenUnread($line["cat_id"], $_SESSION["uid"]);
			} else {
				$child_counter = 0;
			}

			$cv = array("id" => $line["cat_id"], "kind" => "cat",
				"counter" => $line["unread"] + $child_counter);

			array_push($ret_arr, $cv);
		}

		/* Special case: NULL category doesn't actually exist in the DB */

		$cv = array("id" => 0, "kind" => "cat",
			"counter" => (int) ccache_find(0, $_SESSION["uid"], true));

		array_push($ret_arr, $cv);

		return $ret_arr;
	}

	// only accepts real cats (>= 0)
	function getCategoryChildrenUnread($cat, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$result = db_query("SELECT id FROM ttrss_feed_categories WHERE parent_cat = '$cat'
				AND owner_uid = $owner_uid");

		$unread = 0;

		while ($line = db_fetch_assoc($result)) {
			$unread += getCategoryUnread($line["id"], $owner_uid);
			$unread += getCategoryChildrenUnread($line["id"], $owner_uid);
		}

		return $unread;
	}

	function getCategoryUnread($cat, $owner_uid = false) {

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		if ($cat >= 0) {

			if ($cat != 0) {
				$cat_query = "cat_id = '$cat'";
			} else {
				$cat_query = "cat_id IS NULL";
			}

			$result = db_query("SELECT id FROM ttrss_feeds WHERE $cat_query
					AND owner_uid = " . $owner_uid);

			$cat_feeds = array();
			while ($line = db_fetch_assoc($result)) {
				array_push($cat_feeds, "feed_id = " . $line["id"]);
			}

			if (count($cat_feeds) == 0) return 0;

			$match_part = implode(" OR ", $cat_feeds);

			$result = db_query("SELECT COUNT(int_id) AS unread
				FROM ttrss_user_entries
				WHERE	unread = true AND ($match_part)
				AND owner_uid = " . $owner_uid);

			$unread = 0;

			# this needs to be rewritten
			while ($line = db_fetch_assoc($result)) {
				$unread += $line["unread"];
			}

			return $unread;
		} else if ($cat == -1) {
			return getFeedUnread(-1) + getFeedUnread(-2) + getFeedUnread(-3) + getFeedUnread(0);
		} else if ($cat == -2) {

			$result = db_query("
				SELECT COUNT(unread) AS unread FROM
					ttrss_user_entries, ttrss_user_labels2
				WHERE article_id = ref_id AND unread = true
					AND ttrss_user_entries.owner_uid = '$owner_uid'");

			$unread = db_fetch_result($result, 0, "unread");

			return $unread;

		}
	}

	function getFeedUnread($feed, $is_cat = false) {
		return getFeedArticles($feed, $is_cat, true, $_SESSION["uid"]);
	}

	function getLabelUnread($label_id, $owner_uid = false) {
		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		$result = db_query("SELECT COUNT(ref_id) AS unread FROM ttrss_user_entries, ttrss_user_labels2
			WHERE owner_uid = '$owner_uid' AND unread = true AND label_id = '$label_id' AND article_id = ref_id");

		if (db_num_rows($result) != 0) {
			return db_fetch_result($result, 0, "unread");
		} else {
			return 0;
		}
	}

	function getFeedArticles($feed, $is_cat = false, $unread_only = false,
		$owner_uid = false) {

		$n_feed = (int) $feed;
		$need_entries = false;

		if (!$owner_uid) $owner_uid = $_SESSION["uid"];

		if ($unread_only) {
			$unread_qpart = "unread = true";
		} else {
			$unread_qpart = "true";
		}

		if ($is_cat) {
			return getCategoryUnread($n_feed, $owner_uid);
		} else if ($n_feed == -6) {
			return 0;
		} else if ($feed != "0" && $n_feed == 0) {

			$feed = db_escape_string($feed);

			$result = db_query("SELECT SUM((SELECT COUNT(int_id)
				FROM ttrss_user_entries,ttrss_entries WHERE int_id = post_int_id
					AND ref_id = id AND $unread_qpart)) AS count FROM ttrss_tags
				WHERE owner_uid = $owner_uid AND tag_name = '$feed'");
			return db_fetch_result($result, 0, "count");

		} else if ($n_feed == -1) {
			$match_part = "marked = true";
		} else if ($n_feed == -2) {
			$match_part = "published = true";
		} else if ($n_feed == -3) {
			$match_part = "unread = true AND score >= 0";

			$intl = get_pref("FRESH_ARTICLE_MAX_AGE", $owner_uid);

			if (DB_TYPE == "pgsql") {
				$match_part .= " AND date_entered > NOW() - INTERVAL '$intl hour' ";
			} else {
				$match_part .= " AND date_entered > DATE_SUB(NOW(), INTERVAL $intl HOUR) ";
			}

			$need_entries = true;

		} else if ($n_feed == -4) {
			$match_part = "true";
		} else if ($n_feed >= 0) {

			if ($n_feed != 0) {
				$match_part = "feed_id = '$n_feed'";
			} else {
				$match_part = "feed_id IS NULL";
			}

		} else if ($feed < LABEL_BASE_INDEX) {

			$label_id = feed_to_label_id($feed);

			return getLabelUnread($label_id, $owner_uid);

		}

		if ($match_part) {

			if ($need_entries) {
				$from_qpart = "ttrss_user_entries,ttrss_entries";
				$from_where = "ttrss_entries.id = ttrss_user_entries.ref_id AND";
			} else {
				$from_qpart = "ttrss_user_entries";
				$from_where = "";
			}

			$query = "SELECT count(int_id) AS unread
				FROM $from_qpart WHERE
				$unread_qpart AND $from_where ($match_part) AND ttrss_user_entries.owner_uid = $owner_uid";

			//echo "[$feed/$query]\n";

			$result = db_query($query);

		} else {

			$result = db_query("SELECT COUNT(post_int_id) AS unread
				FROM ttrss_tags,ttrss_user_entries,ttrss_entries
				WHERE tag_name = '$feed' AND post_int_id = int_id AND ref_id = ttrss_entries.id
				AND $unread_qpart AND ttrss_tags.owner_uid = " . $owner_uid);
		}

		$unread = db_fetch_result($result, 0, "unread");

		return $unread;
	}

	function getGlobalUnread($user_id = false) {

		if (!$user_id) {
			$user_id = $_SESSION["uid"];
		}

		$result = db_query("SELECT SUM(value) AS c_id FROM ttrss_counters_cache
			WHERE owner_uid = '$user_id' AND feed_id > 0");

		$c_id = db_fetch_result($result, 0, "c_id");

		return $c_id;
	}

	function getGlobalCounters($global_unread = -1) {
		$ret_arr = array();

		if ($global_unread == -1) {
			$global_unread = getGlobalUnread();
		}

		$cv = array("id" => "global-unread",
			"counter" => (int) $global_unread);

		array_push($ret_arr, $cv);

		$result = db_query("SELECT COUNT(id) AS fn FROM
			ttrss_feeds WHERE owner_uid = " . $_SESSION["uid"]);

		$subscribed_feeds = db_fetch_result($result, 0, "fn");

		$cv = array("id" => "subscribed-feeds",
			"counter" => (int) $subscribed_feeds);

		array_push($ret_arr, $cv);

		return $ret_arr;
	}

	function getVirtCounters() {

		$ret_arr = array();

		for ($i = 0; $i >= -4; $i--) {

			$count = getFeedUnread($i);

			if ($i == 0 || $i == -1 || $i == -2)
				$auxctr = getFeedArticles($i, false);
			else
				$auxctr = 0;

			$cv = array("id" => $i,
				"counter" => (int) $count,
				"auxcounter" => $auxctr);

//			if (get_pref('EXTENDED_FEEDLIST'))
//				$cv["xmsg"] = getFeedArticles($i)." ".__("total");

			array_push($ret_arr, $cv);
		}

		$feeds = PluginHost::getInstance()->get_feeds(-1);

		if (is_array($feeds)) {
			foreach ($feeds as $feed) {
				$cv = array("id" => PluginHost::pfeed_to_feed_id($feed['id']),
					"counter" => $feed['sender']->get_unread($feed['id']));

				if (method_exists($feed['sender'], 'get_total'))
					$cv["auxcounter"] = $feed['sender']->get_total($feed['id']);

				array_push($ret_arr, $cv);
			}
		}

		return $ret_arr;
	}

	function getLabelCounters($descriptions = false) {

		$ret_arr = array();

		$owner_uid = $_SESSION["uid"];

		$result = db_query("SELECT id,caption,SUM(CASE WHEN u1.unread = true THEN 1 ELSE 0 END) AS unread, COUNT(u1.unread) AS total
			FROM ttrss_labels2 LEFT JOIN ttrss_user_labels2 ON
				(ttrss_labels2.id = label_id)
				LEFT JOIN ttrss_user_entries AS u1 ON u1.ref_id = article_id
				WHERE ttrss_labels2.owner_uid = $owner_uid GROUP BY ttrss_labels2.id,
					ttrss_labels2.caption");

		while ($line = db_fetch_assoc($result)) {

			$id = label_to_feed_id($line["id"]);

			$cv = array("id" => $id,
				"counter" => (int) $line["unread"],
				"auxcounter" => (int) $line["total"]);

			if ($descriptions)
				$cv["description"] = $line["caption"];

			array_push($ret_arr, $cv);
		}

		return $ret_arr;
	}

	function getFeedCounters($active_feed = false) {

		$ret_arr = array();

		$query = "SELECT ttrss_feeds.id,
				ttrss_feeds.title,
				".SUBSTRING_FOR_DATE."(ttrss_feeds.last_updated,1,19) AS last_updated,
				last_error, value AS count
			FROM ttrss_feeds, ttrss_counters_cache
			WHERE ttrss_feeds.owner_uid = ".$_SESSION["uid"]."
				AND ttrss_counters_cache.owner_uid = ttrss_feeds.owner_uid
				AND ttrss_counters_cache.feed_id = id";

		$result = db_query($query);

		while ($line = db_fetch_assoc($result)) {

			$id = $line["id"];
			$count = $line["count"];
			$last_error = htmlspecialchars($line["last_error"]);

			$last_updated = make_local_datetime($line['last_updated'], false);

			$has_img = feed_has_icon($id);

			if (date('Y') - date('Y', strtotime($line['last_updated'])) > 2)
				$last_updated = '';

			$cv = array("id" => $id,
				"updated" => $last_updated,
				"counter" => (int) $count,
				"has_img" => (int) $has_img);

			if ($last_error)
				$cv["error"] = $last_error;

//			if (get_pref('EXTENDED_FEEDLIST'))
//				$cv["xmsg"] = getFeedArticles($id)." ".__("total");

			if ($active_feed && $id == $active_feed)
				$cv["title"] = truncate_string($line["title"], 30);

			array_push($ret_arr, $cv);

		}

		return $ret_arr;
	}

	function get_pgsql_version() {
		$result = db_query("SELECT version() AS version");
		$version = explode(" ", db_fetch_result($result, 0, "version"));
		return $version[1];
	}

	/**
	 * @return array (code => Status code, message => error message if available)
	 *
	 *                 0 - OK, Feed already exists
	 *                 1 - OK, Feed added
	 *                 2 - Invalid URL
	 *                 3 - URL content is HTML, no feeds available
	 *                 4 - URL content is HTML which contains multiple feeds.
	 *                     Here you should call extractfeedurls in rpc-backend
	 *                     to get all possible feeds.
	 *                 5 - Couldn't download the URL content.
	 *                 6 - Content is an invalid XML.
	 */
	function subscribe_to_feed($url, $cat_id = 0,
			$auth_login = '', $auth_pass = '') {

		global $fetch_last_error;

		require_once "include/rssfuncs.php";

		$url = fix_url($url);

		if (!$url || !validate_feed_url($url)) return array("code" => 2);

		$contents = @fetch_file_contents($url, false, $auth_login, $auth_pass);

		if (!$contents) {
			return array("code" => 5, "message" => $fetch_last_error);
		}

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SUBSCRIBE_FEED) as $plugin) {
			$contents = $plugin->hook_subscribe_feed($contents, $url, $auth_login, $auth_pass);
		}

		if (is_html($contents)) {
			$feedUrls = get_feeds_from_html($url, $contents);

			if (count($feedUrls) == 0) {
				return array("code" => 3);
			} else if (count($feedUrls) > 1) {
				return array("code" => 4, "feeds" => $feedUrls);
			}
			//use feed url as new URL
			$url = key($feedUrls);
		}

		if ($cat_id == "0" || !$cat_id) {
			$cat_qpart = "NULL";
		} else {
			$cat_qpart = "'$cat_id'";
		}

		$result = db_query(
			"SELECT id FROM ttrss_feeds
			WHERE feed_url = '$url' AND owner_uid = ".$_SESSION["uid"]);

		if (strlen(FEED_CRYPT_KEY) > 0) {
			require_once "crypt.php";
			$auth_pass = substr(encrypt_string($auth_pass), 0, 250);
			$auth_pass_encrypted = 'true';
		} else {
			$auth_pass_encrypted = 'false';
		}

		$auth_pass = db_escape_string($auth_pass);

		if (db_num_rows($result) == 0) {
			$result = db_query(
				"INSERT INTO ttrss_feeds
					(owner_uid,feed_url,title,cat_id, auth_login,auth_pass,update_method,auth_pass_encrypted)
				VALUES ('".$_SESSION["uid"]."', '$url',
				'[Unknown]', $cat_qpart, '$auth_login', '$auth_pass', 0, $auth_pass_encrypted)");

			$result = db_query(
				"SELECT id FROM ttrss_feeds WHERE feed_url = '$url'
					AND owner_uid = " . $_SESSION["uid"]);

			$feed_id = db_fetch_result($result, 0, "id");

			if ($feed_id) {
				update_rss_feed($feed_id, true);
			}

			return array("code" => 1);
		} else {
			return array("code" => 0);
		}
	}

	function print_feed_select($id, $default_id = "",
		$attributes = "", $include_all_feeds = true,
	  	$root_id = false, $nest_level = 0) {

		if (!$root_id) {
			print "<select id=\"$id\" name=\"$id\" $attributes>";
			if ($include_all_feeds) {
				$is_selected = ("0" == $default_id) ? "selected=\"1\"" : "";
				print "<option $is_selected value=\"0\">".__('All feeds')."</option>";
			}
		}

		if (get_pref('ENABLE_FEED_CATS')) {

			if ($root_id)
				$parent_qpart = "parent_cat = '$root_id'";
			else
				$parent_qpart = "parent_cat IS NULL";

			$result = db_query("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]." AND $parent_qpart ORDER BY title");

			while ($line = db_fetch_assoc($result)) {

				for ($i = 0; $i < $nest_level; $i++)
					$line["title"] = " - " . $line["title"];

				$is_selected = ("CAT:".$line["id"] == $default_id) ? "selected=\"1\"" : "";

				printf("<option $is_selected value='CAT:%d'>%s</option>",
					$line["id"], htmlspecialchars($line["title"]));

				if ($line["num_children"] > 0)
					print_feed_select($id, $default_id, $attributes,
						$include_all_feeds, $line["id"], $nest_level+1);

				$feed_result = db_query("SELECT id,title FROM ttrss_feeds
					WHERE cat_id = '".$line["id"]."' AND owner_uid = ".$_SESSION["uid"] . " ORDER BY title");

				while ($fline = db_fetch_assoc($feed_result)) {
					$is_selected = ($fline["id"] == $default_id) ? "selected=\"1\"" : "";

					$fline["title"] = " + " . $fline["title"];

					for ($i = 0; $i < $nest_level; $i++)
						$fline["title"] = " - " . $fline["title"];

					printf("<option $is_selected value='%d'>%s</option>",
						$fline["id"], htmlspecialchars($fline["title"]));
				}
			}

			if (!$root_id) {
				$default_is_cat = ($default_id == "CAT:0");
				$is_selected = $default_is_cat ? "selected=\"1\"" : "";

				printf("<option $is_selected value='CAT:0'>%s</option>",
					__("Uncategorized"));

				$feed_result = db_query("SELECT id,title FROM ttrss_feeds
					WHERE cat_id IS NULL AND owner_uid = ".$_SESSION["uid"] . " ORDER BY title");

				while ($fline = db_fetch_assoc($feed_result)) {
					$is_selected = ($fline["id"] == $default_id && !$default_is_cat) ? "selected=\"1\"" : "";

					$fline["title"] = " + " . $fline["title"];

					for ($i = 0; $i < $nest_level; $i++)
						$fline["title"] = " - " . $fline["title"];

					printf("<option $is_selected value='%d'>%s</option>",
						$fline["id"], htmlspecialchars($fline["title"]));
				}
			}

		} else {
			$result = db_query("SELECT id,title FROM ttrss_feeds
				WHERE owner_uid = ".$_SESSION["uid"]." ORDER BY title");

			while ($line = db_fetch_assoc($result)) {

				$is_selected = ($line["id"] == $default_id) ? "selected=\"1\"" : "";

				printf("<option $is_selected value='%d'>%s</option>",
					$line["id"], htmlspecialchars($line["title"]));
			}
		}

		if (!$root_id) {
			print "</select>";
		}
	}

	function print_feed_cat_select($id, $default_id,
		$attributes, $include_all_cats = true, $root_id = false, $nest_level = 0) {

			if (!$root_id) {
					print "<select id=\"$id\" name=\"$id\" default=\"$default_id\" onchange=\"catSelectOnChange(this)\" $attributes>";
			}

			if ($root_id)
				$parent_qpart = "parent_cat = '$root_id'";
			else
				$parent_qpart = "parent_cat IS NULL";

			$result = db_query("SELECT id,title,
				(SELECT COUNT(id) FROM ttrss_feed_categories AS c2 WHERE
					c2.parent_cat = ttrss_feed_categories.id) AS num_children
				FROM ttrss_feed_categories
				WHERE owner_uid = ".$_SESSION["uid"]." AND $parent_qpart ORDER BY title");

			while ($line = db_fetch_assoc($result)) {
				if ($line["id"] == $default_id) {
					$is_selected = "selected=\"1\"";
				} else {
					$is_selected = "";
				}

				for ($i = 0; $i < $nest_level; $i++)
					$line["title"] = " - " . $line["title"];

				if ($line["title"])
					printf("<option $is_selected value='%d'>%s</option>",
						$line["id"], htmlspecialchars($line["title"]));

				if ($line["num_children"] > 0)
					print_feed_cat_select($id, $default_id, $attributes,
						$include_all_cats, $line["id"], $nest_level+1);
			}

			if (!$root_id) {
				if ($include_all_cats) {
					if (db_num_rows($result) > 0) {
						print "<option disabled=\"1\">--------</option>";
					}

					if ($default_id == 0) {
						$is_selected = "selected=\"1\"";
					} else {
						$is_selected = "";
					}

					print "<option $is_selected value=\"0\">".__('Uncategorized')."</option>";
				}
				print "</select>";
			}
		}

	function checkbox_to_sql_bool($val) {
		return ($val == "on") ? "true" : "false";
	}

	function getFeedCatTitle($id) {
		if ($id == -1) {
			return __("Special");
		} else if ($id < LABEL_BASE_INDEX) {
			return __("Labels");
		} else if ($id > 0) {
			$result = db_query("SELECT ttrss_feed_categories.title
				FROM ttrss_feeds, ttrss_feed_categories WHERE ttrss_feeds.id = '$id' AND
					cat_id = ttrss_feed_categories.id");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return __("Uncategorized");
			}
		} else {
			return "getFeedCatTitle($id) failed";
		}

	}

	function getFeedIcon($id) {
		switch ($id) {
		case 0:
			return "images/archive.png";
			break;
		case -1:
			return "images/star.png";
			break;
		case -2:
			return "images/feed.png";
			break;
		case -3:
			return "images/fresh.png";
			break;
		case -4:
			return "images/folder.png";
			break;
		case -6:
			return "images/time.png";
			break;
		default:
			if ($id < LABEL_BASE_INDEX) {
				return "images/label.png";
			} else {
				if (file_exists(ICONS_DIR . "/$id.ico"))
					return ICONS_URL . "/$id.ico";
			}
			break;
		}

		return false;
	}

	function getFeedTitle($id, $cat = false) {
		if ($cat) {
			return getCategoryTitle($id);
		} else if ($id == -1) {
			return __("Starred articles");
		} else if ($id == -2) {
			return __("Published articles");
		} else if ($id == -3) {
			return __("Fresh articles");
		} else if ($id == -4) {
			return __("All articles");
		} else if ($id === 0 || $id === "0") {
			return __("Archived articles");
		} else if ($id == -6) {
			return __("Recently read");
		} else if ($id < LABEL_BASE_INDEX) {
			$label_id = feed_to_label_id($id);
			$result = db_query("SELECT caption FROM ttrss_labels2 WHERE id = '$label_id'");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "caption");
			} else {
				return "Unknown label ($label_id)";
			}

		} else if (is_numeric($id) && $id > 0) {
			$result = db_query("SELECT title FROM ttrss_feeds WHERE id = '$id'");
			if (db_num_rows($result) == 1) {
				return db_fetch_result($result, 0, "title");
			} else {
				return "Unknown feed ($id)";
			}
		} else {
			return $id;
		}
	}

	// TODO: less dumb splitting
	require_once "functions2.php";

?>
