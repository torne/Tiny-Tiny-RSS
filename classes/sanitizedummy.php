<?php
class SanitizeDummy extends SimplePie_Sanitize {
	function sanitize($data, $type, $base = '') {
		return $data;
	}
}
?>
