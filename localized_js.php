<?php 
require "functions.php";
header("Content-Type: text/plain; charset=UTF-8");

function js_decl($s1, $s2) {
	return "T_messages[\"$s1\"] = \"$s2\";\n";
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

<?php

print js_decl("display feeds", __("display feeds"));
print js_decl("display tags", __("display tags"));

?>

