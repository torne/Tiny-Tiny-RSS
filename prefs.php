<?php
	if (file_exists("install") && !file_exists("config.php")) {
		header("Location: install/");
	}

	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	require_once "autoload.php";
	require_once "sessions.php";
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";
	require_once "db-prefs.php";

	if (!init_plugins()) return;

	login_sequence();

	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS : <?php echo __("Preferences") ?></title>

	<script type="text/javascript">
		var __ttrss_version = "<?php echo VERSION ?>"
	</script>

	<?php echo stylesheet_tag("lib/dijit/themes/claro/claro.css"); ?>
	<?php echo stylesheet_tag("css/layout.css"); ?>

	<?php if ($_SESSION["uid"]) {
		$theme = get_pref( "USER_CSS_THEME", $_SESSION["uid"], false);
		if ($theme && theme_valid("$theme")) {
			echo stylesheet_tag(get_theme_path($theme));
		} else {
			echo stylesheet_tag("themes/default.css");
		}
	}
	?>

	<?php print_user_stylesheet() ?>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>
	<link rel="icon" type="image/png" sizes="72x72" href="images/favicon-72px.png" />

	<?php
	foreach (array("lib/prototype.js",
				"lib/scriptaculous/scriptaculous.js?load=effects,controls",
				"lib/dojo/dojo.js",
				"lib/dojo/tt-rss-layer.js",
				"errors.php?mode=js") as $jsfile) {

		echo javascript_tag($jsfile);

	} ?>

	<script type="text/javascript">
		require({cache:{}});
	<?php
		require_once 'lib/jshrink/Minifier.php';

		foreach (PluginHost::getInstance()->get_plugins() as $n => $p) {
			if (method_exists($p, "get_prefs_js")) {
				echo JShrink\Minifier::minify($p->get_prefs_js());
			}
		}

		print get_minified_js(array("../lib/CheckBoxTree","functions", "deprecated", "prefs", "PrefFeedTree", "PrefFilterTree", "PrefLabelTree"));

		init_js_translations();
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

<div id="notify" class="notify"></div>
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
	<div id="systemConfigTab" dojoType="dijit.layout.ContentPane"
		href="backend.php?op=pref-system"
		title="<?php echo __('System') ?>"></div>
<?php } ?>
<?php
	PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TABS,
		"hook_prefs_tabs", false);
?>
</div>

<div id="footer" dojoType="dijit.layout.ContentPane" region="bottom">
	<a class="insensitive" target="_blank" href="http://tt-rss.org/">
	Tiny Tiny RSS</a>
	<?php if (!defined('HIDE_VERSION')) { ?>
		 v<?php echo VERSION ?>
	<?php } ?>
	&copy; 2005-<?php echo date('Y') ?>
	<a class="insensitive" target="_blank"
	href="http://fakecake.org/">Andrew Dolgov</a>
</div> <!-- footer -->

</div>

</body>
</html>
