#!/bin/sh

if [ ! -f localized_js.php ]; then
	echo "please run this script from tt-rss directory"
	exit 1
fi

echo "This script is not used anymore."

exit 0 # disabled

cat >localized_js.php <<HEADER
<?php 
error_reporting(E_ERROR | E_WARNING | E_PARSE);
define('DISABLE_SESSIONS', true);

require "functions.php";
header("Content-Type: text/plain; charset=UTF-8");

function T_js_decl(\$s1) {

	if (!\$s1) return;

//	\$T_s1 = __(\$s1);

//	if (\$T_s1 != \$s1) {
		return "T_messages[\"\$s1\"] = \"".__(\$s1)."\";\n";
//	} else {
//		return "";
//	}
}
?>

var T_messages = new Object();

function __(msg) {
	if (T_messages[msg]) {
		return T_messages[msg];
	} else {
		debug('[gettext] not found: ' + msg);
		return msg;
	}
}

<?php
HEADER

cat *js | ./utils/extract-i18n-js.pl | sort | uniq >> localized_js.php

cat >>localized_js.php <<FOOTER
?>
FOOTER
