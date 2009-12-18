<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	require_once "../config.php";
	require_once "functions.php";
	require_once "../functions.php"; 

	require_once "../sessions.php";

	require_once "../version.php"; 
	require_once "../db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	init_connection($link);

	login_sequence($link, true);
?>

<div class="panel" id="prefs" selected="yes" title="Preferences"
	myBackLabel="<?php echo __('Home') ?>" myBackHref="home.php">

<fieldset>

<div class="row">
	<label>Enable categories</label>
	<div class="toggle" id="ENABLE_CATS" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled($link, "ENABLE_CATS") ?>"><span class="thumb"></span><span class="toggleOn">ON</span><span class="toggleOff">OFF</span></div>
</div>

<div class="row">
	<label>Display images</label>
	<div class="toggle" id="SHOW_IMAGES" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled($link, "SHOW_IMAGES") ?>"><span class="thumb"></span><span class="toggleOn">ON</span><span class="toggleOff">OFF</span></div>
</div>

<div class="row">
	<label>Hide read items</label>
	<div class="toggle" id="HIDE_READ" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled($link, "HIDE_READ") ?>"><span class="thumb"></span><span class="toggleOn">ON</span><span class="toggleOff">OFF</span></div>
</div>

<div class="row">
	<label>Sort feeds by unread</label>
	<div class="toggle" id="SORT_FEEDS_UNREAD" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled($link, "SORT_FEEDS_UNREAD") ?>"><span class="thumb"></span><span class="toggleOn">ON</span><span class="toggleOff">OFF</span></div>
</div>

</fieldset>
