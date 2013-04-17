<?php
class SessionHandler implements SessionHandlerInterface {
	private static $instance;
	private $db;

	public static function get() {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	private function __construct() {
		$this->db = Db::get();

		session_set_save_handler("SessionHandler::open", "SessionHandler::close",
			"SessionHandler::read", "SessionHandler::write", "SessionHandler::destroy",
			"SessionHandler::gc");
	}

	public static function open($save_path, $name) { }


	public static function read ($id){

		$query = "SELECT data FROM ttrss_sessions WHERE id='$id'";

		$res = $this->db->query("SELECT data FROM ttrss_sessions WHERE id='$id'");

		if ($this->db->num_rows($res) != 1) {

			"INSERT INTO ttrss_sessions (id, data, expire)
					VALUES ('$id', '$data', '$expire')";



		} else {
			$data = $this->db->fetch_result($res, 0, "data");
			return base64_decode($data);
		}

	}

	public static function write($id, $data) {
		if (! $data) {
			return false;
		}

		$data = $this->db->escape_string( base64_encode($data), false);

		$expire = time() + max(SESSION_COOKIE_LIFETIME, 86400);

	 	$query = "UPDATE ttrss_sessions SET data='$data',
				expire = '$expire' WHERE id='$id'";

		$this->db->query( $query);
		return true;
	}

	public static function close () { }

	public static function destroy($session_id) {
		$this->db->query("DELETE FROM ttrss_sessions WHERE id = '$session_id'");
		return true;
	}

	public static function gc($maxLifeTime) {
		$this->db->query("DELETE FROM ttrss_sessions WHERE expire < " time() - $maxLifeTime);
		return true;
	}

}
?>
