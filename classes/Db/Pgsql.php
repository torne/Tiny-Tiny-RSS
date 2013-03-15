<?php

class Db_Pgsql extends Db_Abstract
{
    public function connect($host, $user, $pass, $db)
    {
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

        $this->dbconn = $link;
        return $link;
    }

    public function init()
    {
        pg_query($this->dbconn, "set client_encoding = 'UTF-8'");
        pg_set_client_encoding("UNICODE");
        pg_query($this->dbconn, "set datestyle = 'ISO, european'");
        pg_query($this->dbconn, "set TIME ZONE 0");
    }

    public function escape_string($s, $strip_tags = true)
    {
        if ($strip_tags) $s = strip_tags($s);
        return pg_escape_string($s);
    }

    public function query($query, $die_on_error = true)
    {
        $result = pg_query($this->dbconn, $query);
        if (!$result) {
            $query = htmlspecialchars($query); // just in case
            if ($die_on_error) {
                die("Query <i>$query</i> failed [$result]: " . ($this->dbconn ? pg_last_error($this->dbconn) : "No connection"));
            }
        }
        return $result;
    }

    public function fetch_assoc($result) {
        return pg_fetch_assoc($result);
    }

    public function num_rows($result) {
        return pg_num_rows($result);
    }

    public function fetch_result($result, $row, $param) {
        return pg_fetch_result($result, $row, $param);
    }

    public function close() {
        return pg_close($this->dbconn);
    }

    public function affected_rows($result) {
        return pg_affected_rows($result);
    }

    public function last_error() {
        return pg_last_error($this->dbconn);
    }
}
