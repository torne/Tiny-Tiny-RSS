<?php

require_once "config.php";

function db_connect($host, $user, $pass, $db) {
	if (DB_TYPE == "pgsql") {

		$string = "dbname=$db user=$user";

		if ($pass) {
			$string .= " password=$pass";
		}

		if ($host) {
			$string .= " host=$host";
		}

		if (defined('DB_PORT')) {
			$string = "$string port=" . DB_PORT;
		}

		$link = pg_connect($string);

		if (!$link) {
			die("Unable to connect to database (as $user to $host, database $db):" . pg_last_error());
		}

		return $link;

	} else if (DB_TYPE == "mysql") {
		$link = mysql_connect($host, $user, $pass);
		if ($link) {
			$result = mysql_select_db($db, $link);
			if (!$result) {
				die("Can't select DB: " . mysql_error($link));
			}
			return $link;
		} else {
			die("Unable to connect to database (as $user to $host, database $db): " . mysql_error());
		}
	}
}

function db_escape_string($link, $s, $strip_tags = true) {
	if ($strip_tags) $s = strip_tags($s);

	if (DB_TYPE == "pgsql") {
		return pg_escape_string($link, $s);
	} else {
		return mysql_real_escape_string($s, $link);
	}
}

function db_query($link, $query, $die_on_error = true) {
	if (DB_TYPE == "pgsql") {
		$result = pg_query($link, $query);
		if (!$result) {
			$query = htmlspecialchars($query); // just in case
			if ($die_on_error) {
				die("Query <i>$query</i> failed [$result]: " . ($link ? pg_last_error($link) : "No connection"));
			}
		}
		return $result;
	} else if (DB_TYPE == "mysql") {
		$result = mysql_query($query, $link);
		if (!$result) {
			$query = htmlspecialchars($query);
			if ($die_on_error) {
				die("Query <i>$query</i> failed: " . ($link ? mysql_error($link) : "No connection"));
			}
		}
		return $result;
	}
}

function db_fetch_assoc($result) {
	if (DB_TYPE == "pgsql") {
		return pg_fetch_assoc($result);
	} else if (DB_TYPE == "mysql") {
		return mysql_fetch_assoc($result);
	}
}


function db_num_rows($result) {
	if (DB_TYPE == "pgsql") {
		return pg_num_rows($result);
	} else if (DB_TYPE == "mysql") {
		return mysql_num_rows($result);
	}
}

function db_fetch_result($result, $row, $param) {
	if (DB_TYPE == "pgsql") {
		return pg_fetch_result($result, $row, $param);
	} else if (DB_TYPE == "mysql") {
		// I hate incoherent naming of PHP functions
		return mysql_result($result, $row, $param);
	}
}

function db_unescape_string($str) {
	$tmp = str_replace("\\\"", "\"", $str);
	$tmp = str_replace("\\'", "'", $tmp);
	return $tmp;
}

function db_close($link) {
	if (DB_TYPE == "pgsql") {

		return pg_close($link);

	} else if (DB_TYPE == "mysql") {
		return mysql_close($link);
	}
}

function db_affected_rows($link, $result) {
	if (DB_TYPE == "pgsql") {
		return pg_affected_rows($result);
	} else if (DB_TYPE == "mysql") {
		return mysql_affected_rows($link);
	}
}

function db_last_error($link) {
	if (DB_TYPE == "pgsql") {
		return pg_last_error($link);
	} else if (DB_TYPE == "mysql") {
		return mysql_error($link);
	}
}

function db_quote($str){
	return("'$str'");
}

?>
