<?php
class Protected_Handler extends Handler {

	function before($method) {
		return parent::before($method) && $_SESSION['uid'];
	}
}
?>
