<?
	// Original from http://www.daniweb.com/code/snippet43.html

	require_once "config.php";
	require_once "db.php";

	$session_expire = SESSION_EXPIRE_TIME; //seconds
	$session_name = (!defined('TTRSS_SESSION_NAME')) ? "ttrss_sid" : TTRSS_SESSION_NAME;

	ini_set("session.gc_probability", 50);
	ini_set("session.name", $session_name);
	ini_set("session.use_only_cookies", true);
	ini_set("session.gc_maxlifetime", SESSION_EXPIRE_TIME);

	function open ($s, $n) {
	
		global $session_connection;
		
		$session_connection = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
		
		return true;
	}

	function read ($id){
	
		global $session_connection,$session_read;					 

		$query = "SELECT data FROM ttrss_sessions WHERE id='$id' $address_check_qpart";

		$res = db_query($session_connection, $query);
		
		if (db_num_rows($res) != 1) {
		 	return "";
		} else {
			$session_read = db_fetch_assoc($res);
			$session_read["data"] = base64_decode($session_read["data"]);
			return $session_read["data"];
		}
	}

	function write ($id, $data) {
 
		if (! $data) { 
			return false; 
		}
		
		global $session_connection, $session_read, $session_expire;
		
		$expire = time() + $session_expire;
		
		$data = db_escape_string(base64_encode($data), $session_connection);
		
		if ($session_read) {
		 	$query = "UPDATE ttrss_sessions SET data='$data', 
					expire='$expire' WHERE id='$id' $address_check_qpart"; 
		} else {
		 	$query = "INSERT INTO ttrss_sessions (id, data, expire)
					VALUES ('$id', '$data', '$expire')";
		}
		
		db_query($session_connection, $query);
		return true;
	}

	function close () {
	
		global $session_connection;
		
		db_close($session_connection);
		
		return true;
	}

	function destroy ($id) {
	
		global $session_connection;

		$query = "DELETE FROM ttrss_sessions WHERE id = '$id' $address_check_qpart";
		
		db_query($session_connection, $query);
		
		return true;
	}

	function gc ($expire) {
	
		global $session_connection;
		
		$query = "DELETE FROM ttrss_sessions WHERE expire < " . time();
		
		db_query($session_connection, $query);
	}

//	session_set_cookie_params(SESSION_COOKIE_LIFETIME);

	if (DATABASE_BACKED_SESSIONS) {
		session_set_save_handler("open", "close", "read", "write", "destroy", "gc");
	}
	
	session_start();
?>
