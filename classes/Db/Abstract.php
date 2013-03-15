<?php

abstract class Db_Abstract implements Db_Interface
{
    private $dbconn;
    protected static $instance;

    private function __construct() { }

    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function connect($host, $user, $pass, $db) { }

    public function getLink()
    {
        return $this->dbconn;
    }

    public function init() { }

    public function escape_string($s, $strip_tags = true) { }

    public function query($query, $die_on_error = true) { }

    public function fetch_assoc($result) { }

    public function num_rows($result) { }

    public function fetch_result($result, $row, $param) { }

    public function unescape_string($str)
    {
        $tmp = str_replace("\\\"", "\"", $str);
        $tmp = str_replace("\\'", "'", $tmp);
        return $tmp;
    }

    public function close() { }

    public function affected_rows($result) { }

    public function last_error() { }

    public function quote($str)
    {
        return("'$str'");
    }

}