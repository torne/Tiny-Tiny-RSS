<?php
class Protected_Handler extends Handler {

	function before() {
		return parent::before() && $_SESSION['uid'];
	}
}
?>
