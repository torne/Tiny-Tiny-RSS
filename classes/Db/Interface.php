<?php

interface Db_Interface
{
    public function connect($host, $user, $pass, $db);
    public function getLink();
    public function init();
    public function escape_string($s, $strip_tags = true);
    public function query($query, $die_on_error = true);
    public function fetch_assoc($result);
    public function num_rows($result);
    public function fetch_result($result, $row, $param);
    public function unescape_string($str);
    public function close();
    public function affected_rows($result);
    public function last_error();
    public function quote($str);
}
