<?php

class Db_Mysql extends Db_Abstract
{
    public function connect($host, $user, $pass, $db)
    {
        $link = mysql_connect($host, $user, $pass);
        if ($link) {
            $result = mysql_select_db($db, $link);
            if (!$result) {
                die("Can't select DB: " . mysql_error($link));
            }
            $this->dbconn = $link;
            return $link;
        } else {
            die("Unable to connect to database (as $user to $host, database $db): " . mysql_error());
        }
    }

    public function init()
    {
        db_query($this->dbconn, "SET time_zone = '+0:0'");

        if (defined('MYSQL_CHARSET') && MYSQL_CHARSET) {
            db_query($this->dbconn, "SET NAMES " . MYSQL_CHARSET);
        }
    }

    public function escape_string($s, $strip_tags = true)
    {
        if ($strip_tags) $s = strip_tags($s);
        return mysql_real_escape_string($s);
    }

    public function query($query, $die_on_error = true)
    {
        $result = mysql_query($query, $this->dbconn);
        if (!$result) {
            $query = htmlspecialchars($query);
            if ($die_on_error) {
                die("Query <i>$query</i> failed: " . ($this->dbconn ? mysql_error($this->dbconn) : "No connection"));
            }
        }
        return $result;
    }

    public function fetch_assoc($result) {
        return mysql_fetch_assoc($result);
    }

    public function num_rows($result) {
        return mysql_num_rows($result);
    }

    public function fetch_result($result, $row, $param) {
        // I hate incoherent naming of PHP functions
        return mysql_result($result, $row, $param);
    }

    public function close() {
        return mysql_close($this->dbconn);
    }

    public function affected_rows($result) {
        return mysql_affected_rows($this->dbconn);
    }

    public function last_error() {
        return mysql_error($this->dbconn);
    }
}
