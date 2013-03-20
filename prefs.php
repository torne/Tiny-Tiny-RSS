<?php
	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "sessions.php";
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";
	require_once "db-prefs.php";

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	login_sequence($link);

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title><?php echo PAGE_TITLE ?> : <?php echo __("Preferences") ?></title>

	<?php echo stylesheet_tag("lib/dijit/themes/claro/claro.css"); ?>
	<?php echo stylesheet_tag("tt-rss.css"); ?>

	<?php print_user_stylesheet($link) ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<?php
	foreach (array("lib/prototype.js",
				"lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls",
				"lib/dojo/dojo.js",
				"lib/dijit/dijit.js",
				"lib/dojo/tt-rss-layer.js",
				"localized_js.php",
				"errors.php?mode=js") as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

	<script type="text/javascript">
	<?php
		require 'lib/jshrink/Minifier.php';

		global $pluginhost;

		foreach ($pluginhost->get_plugins() as $n => $p) {
			if (method_exists($p, "get_prefs_js")) {
				echo JShrink\Minifier::minify($p->get_prefs_js());
			}
		}

		print get_minified_js(array("functions", "deprecated", "prefs"));

	?>
	</script>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>

</head>

<body id="ttrssPrefs" class="claro">

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>
<div id="cmdline" style="display : none"></div>

<div id="overlay">
	<div id="overlay_inner">
		<div class="insensitive"><?php echo __("Loading, please wait...") ?></div>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
		<noscript><br/><?php print_error('Javascript is disabled. Please enable it.') ?></noscript>
	</div>
</div>

<img id="piggie" src="images/piggie.png" style="display : none" alt="piggie"/>

<div id="header" dojoType="dijit.layout.ContentPane" region="top">
	<!-- <a href='#' onclick="showHelp()"><?php echo __("Keyboard shortcuts") ?></a> | -->
	<a href="#" onclick="gotoMain()"><?php echo __('Exit preferences') ?></a>
</div>

<div id="main" dojoType="dijit.layout.BorderContainer">

<div dojoType="dijit.layout.TabContainer" region="center" id="pref-tabs">
<div id="genConfigTab" dojoType="dijit.layout.ContentPane"
	href="backend.php?op=pref-prefs"
	title="<?php echo __('Preferences') ?>"></div>
<div id="feedConfigTab" dojoType="dijit.layout.ContentPane"
	href="backend.php?op=pref-feeds"
	title="<?php echo __('Feeds') ?>"></div>
<div id="filterConfigTab" dojoType="dijit.layout.ContentPane"
	href="backend.php?op=pref-filters"
	title="<?php echo __('Filters') ?>"></div>
<div id="labelConfigTab" dojoType="dijit.layout.ContentPane"
	href="backend.php?op=pref-labels"
	title="<?php echo __('Labels') ?>"></div>
<?php if ($_SESSION["access_level"] >= 10) { ?>
	<div id="userConfigTab" dojoType="dijit.layout.ContentPane"
		href="backend.php?op=pref-users"
		title="<?php echo __('Users') ?>"></div>
<?php } ?>
<?php
	$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TABS,
		"hook_prefs_tabs", false);
?>
</div>

<div id="footer" dojoType="dijit.layout.ContentPane" region="bottom">
	<a class="insensitive" target="_blank" href="http://tt-rss.org/">
	Tiny Tiny RSS</a> &copy; 2005-<?php echo date('Y') ?>
	<a class="insensitive" target="_blank"
	href="http://fakecake.org/">Andrew Dolgov</a>
</div> <!-- footer -->

</div>

<?php db_close($link); ?>

</body>
</html>
