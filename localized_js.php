<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "sessions.php";
	require_once "functions.php";
	header("Content-Type: text/plain; charset=UTF-8");

	function T_js_decl($s1, $s2) {
		if ($s1 && $s2) {
			$s1 = preg_replace("/\n/", "", $s1);
			$s2 = preg_replace("/\n/", "", $s2);

			$s1 = preg_replace("/\"/", "\\\"", $s1);
			$s2 = preg_replace("/\"/", "\\\"", $s2);

			return "T_messages[\"$s1\"] = \"$s2\";\n";
		}
	}
?>

var T_messages = new Object();

function __(msg) {
	if (T_messages[msg]) {
		return T_messages[msg];
	} else {
		return msg;
	}
}

function ngettext(msg1, msg2, n) {
	return (parseInt(n) > 1) ? msg2 : msg1;
}

<?php
	$l10n = _get_reader();

	for ($i = 0; $i < $l10n->total; $i++) {
		$orig = $l10n->get_original_string($i);
		$translation = __($orig);

		print T_js_decl($orig, $translation);
	}
?>
