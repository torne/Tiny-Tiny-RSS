<?

require_once "config.php";

function db_connect($host, $user, $pass, $db) {
	if (DB_TYPE == "pgsql") {	
			  
		return pg_connect("host=$host dbname=$db user=$user password=$pass");

	} else if (DB_TYPE == "mysql") {
		$link = mysql_connect($host, $user, $pass);
		if ($link) {
			mysql_select_db($db, $link);
		}
		return $link;
	}
}

function db_escape_string($s) {
	if (DB_TYPE == "pgsql") {	
		return pg_escape_string($s);
	} else {
		return mysql_escape_string($s);
	}
}

function db_query($link, $query) {
	if (DB_TYPE == "pgsql") {
		return pg_query($link, $query);
	} else if (DB_TYPE == "mysql") {
		return mysql_query($query, $link);
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
		return pg_num_rows($link, $query);
	} else if (DB_TYPE == "mysql") {
		return mysql_num_rows($link, $query);
	}
}

function db_fetch_result($result, $row, $param) {
	if (DB_TYPE == "pgsql") {
		return pg_fetch_result($result, $row, $param);
	} else if (DB_TYPE == "mysql") {
		// FIXME
		$line = mysql_fetch_assoc($result);
		return $line[$param];
	}
}

function db_close($link) {
	if (DB_TYPE == "pgsql") {

		return pg_close($link);

	} else if (DB_TYPE == "mysql") {
		return mysql_close($link);
	}
}
