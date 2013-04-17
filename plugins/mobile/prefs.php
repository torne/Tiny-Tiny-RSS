<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	header('Content-Type: text/html; charset=utf-8');

	define('MOBILE_VERSION', true);

	$basedir = dirname(dirname(dirname(__FILE__)));

	set_include_path(
		dirname(__FILE__) . PATH_SEPARATOR .
		$basedir . PATH_SEPARATOR .
		"$basedir/include" . PATH_SEPARATOR .
		get_include_path());

	$basedir = dirname(dirname(__FILE__));

	require_once "config.php";
	require_once "mobile-functions.php";

	login_sequence(true);
?>

<div class="panel" id="prefs" selected="yes" title="Preferences"
	myBackLabel="<?php echo __('Home') ?>" myBackHref="home.php">

<fieldset>

<div class="row">
	<label><?php echo __('Enable categories') ?></label>
	<div class="toggle" id="ENABLE_CATS" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled("ENABLE_CATS") ?>"><span class="thumb"></span><span class="toggleOn"><?php echo __('ON') ?></span><span class="toggleOff"><?php echo __('OFF') ?></span></div>
</div>

<div class="row">
	<label><?php echo __('Browse categories like folders') ?></label>
	<div class="toggle" id="BROWSE_CATS" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled("BROWSE_CATS") ?>"><span class="thumb"></span><span class="toggleOn"><?php echo __('ON') ?></span><span class="toggleOff"><?php echo __('OFF') ?></span></div>
</div>


<div class="row">
	<label><?php echo __('Show images in posts') ?></label>
	<div class="toggle" id="SHOW_IMAGES" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled("SHOW_IMAGES") ?>"><span class="thumb"></span><span class="toggleOn"><?php echo __('ON') ?></span><span class="toggleOff"><?php echo __('OFF') ?></span></div>
</div>

<div class="row">
	<label><?php echo __('Hide read articles and feeds') ?></label>
	<div class="toggle" id="HIDE_READ" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled("HIDE_READ") ?>"><span class="thumb"></span><span class="toggleOn"><?php echo __('ON') ?></span><span class="toggleOff"><?php echo __('OFF') ?></span></div>
</div>

<div class="row">
	<label><?php echo __('Sort feeds by unread count') ?></label>
	<div class="toggle" id="SORT_FEEDS_UNREAD" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled("SORT_FEEDS_UNREAD") ?>"><span class="thumb"></span><span class="toggleOn"><?php echo __('ON') ?></span><span class="toggleOff"><?php echo __('OFF') ?></span></div>
</div>

<div class="row">
	<label><?php echo __('Reverse headline order (oldest first)') ?></label>
	<div class="toggle" id="REVERSE_HEADLINES" onclick="setPref(this)" toggled="<?php echo mobile_pref_toggled("REVERSE_HEADLINES") ?>"><span class="thumb"></span><span class="toggleOn"><?php echo __('ON') ?></span><span class="toggleOff"><?php echo __('OFF') ?></span></div>
</div>

</fieldset>
