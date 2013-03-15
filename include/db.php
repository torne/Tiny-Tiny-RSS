<?php

require_once "config.php";

$db_class = 'Db_'.ucfirst(DB_TYPE);
$db_class_file = 'classes/' . str_replace('_', '/', $db_class) . '.php';

require_once "classes/Db/Interface.php";
require_once "classes/Db/Abstract.php";
require_once $db_class_file;

$db = $db_class::instance(); //  call_user_func(array($db_class, 'instance'));

function db_connect($host, $user, $pass, $db_name) {
    global $db;
    return $db->connect($host, $user, $pass, $db_name);
}

function db_escape_string($s, $strip_tags = true) {
    global $db;
    return $db->escape_string($s, $strip_tags);
}

function db_query($link, $query, $die_on_error = true) {
    global $db;
    return $db->query($query, $die_on_error);
}

function db_fetch_assoc($result) {
    global $db;
    return $db->fetch_assoc($result);
}

function db_num_rows($result) {
    global $db;
    return $db->num_rows($result);
}

function db_fetch_result($result, $row, $param) {
    global $db;
    return $db->fetch_result($result, $row, $param);
}

function db_unescape_string($str) {
    global $db;
    return $db->unescape_string($str);
}

function db_close($link) {
    global $db;
    return $db->close();
}

function db_affected_rows($link, $result) {
    global $db;
    return $db->affected_rows($result);
}

function db_last_error($link) {
    global $db;
    return $db->last_error();
}

function db_quote($str){
    global $db;
    return $db->quote($str);
}

?>