<?php
	if (!file_exists("config.php")) {
		print "<b>Fatal Error</b>: You forgot to copy
		<b>config.php-dist</b> to <b>config.php</b> and edit it.\n";
		exit;
	}

	set_include_path(dirname(__FILE__) ."/include" . PATH_SEPARATOR .
		get_include_path());

	require_once "sessions.php";
	require_once "functions.php";
	require_once "sanity_check.php";
	require_once "version.php";
	require_once "config.php";
	require_once "db-prefs.php";
	require_once "lib/Mobile_Detect.php";

	$mobile = new Mobile_Detect();

	$link = db_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

	if (!init_connection($link)) return;

	global $pluginhost;

	if (!$_REQUEST['mobile']) {
		if ($mobile->isTablet() && $pluginhost->get_plugin("digest")) {
			header('Location: backend.php?op=digest');
			exit;
		} else if ($mobile->isMobile()) {
			header('Location: mobile/index.php');
			exit;
		}
	}


	login_sequence($link);

	$dt_add = time();

	no_cache_incantation();

	header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>Tiny Tiny RSS</title>
	<link rel="stylesheet" type="text/css" href="lib/dijit/themes/claro/claro.css"/>
	<link rel="stylesheet" type="text/css" href="tt-rss.css?<?php echo $dt_add ?>"/>
	<link rel="stylesheet" type="text/css" href="cdm.css?<?php echo $dt_add ?>"/>

	<?php print_theme_includes($link) ?>
	<?php print_user_stylesheet($link) ?>

	<script type="text/javascript">
	</script>

	<link rel="shortcut icon" type="image/png" href="images/favicon.png"/>

	<script type="text/javascript" src="lib/prototype.js"></script>
	<script type="text/javascript" src="lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls"></script>
	<script type="text/javascript" src="lib/dojo/dojo.js"></script>
	<script type="text/javascript" src="lib/dijit/dijit.js"></script>
	<script type="text/javascript" src="lib/dojo/tt-rss-layer.js"></script>

	<script type="text/javascript" charset="utf-8" src="localized_js.php?<?php echo $dt_add ?>"></script>
	<script type="text/javascript" charset="utf-8" src="errors.php?mode=js"></script>

	<script type="text/javascript">
	<?php
		require 'lib/jsmin.php';

		global $pluginhost;

		foreach ($pluginhost->get_plugins() as $n => $p) {
			if (method_exists($p, "get_js")) {
				echo JSMin::minify($p->get_js());
			}
		}

		foreach (array("tt-rss", "functions", "feedlist", "viewfeed", "FeedTree") as $js) {
			if (!isset($_GET['debug'])) {
				echo JSMin::minify(file_get_contents("js/$js.js"));
			} else {
				echo file_get_contents("js/$js.js");
			}
		}
	?>
	</script>

	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

	<script type="text/javascript">
		Event.observe(window, 'load', function() {
			init();
		});
	</script>
</head>

<body id="ttrssMain" class="claro">

<div id="overlay" style="display : block">
	<div id="overlay_inner">
		<div class="insensitive"><?php echo __("Loading, please wait...") ?></div>
		<div dojoType="dijit.ProgressBar" places="0" style="width : 300px" id="loading_bar"
	     progress="0" maximum="100">
		</div>
		<noscript><br/><?php print_error('Javascript is disabled. Please enable it.') ?></noscript>
	</div>
</div>

<div id="header">
	<?php if (!$_SESSION["hide_hello"]) { ?>
			<?php echo __('Hello,') ?> <b><?php echo $_SESSION["name"] ?></b> |
	<?php } ?>
	<a href="prefs.php"><?php echo __('Preferences') ?></a>

	<?php if (defined('FEEDBACK_URL') && FEEDBACK_URL) { ?>
		| <a target="_blank" class="feedback" href="<?php echo FEEDBACK_URL ?>">
				<?php echo __('Comments?') ?></a>
	<?php } ?>

	<?php if (!$_SESSION["hide_logout"]) { ?>
			| <a href="backend.php?op=logout"><?php echo __('Logout') ?></a>
	<?php } ?>

	<img id="newVersionIcon" style="display:none" onclick="newVersionDlg()"
		width="13" height="13"
		src="<?php echo theme_image($link, 'images/new_version.png') ?>"
		title="<?php echo __('New version of Tiny Tiny RSS is available!') ?>"
		alt="new_version_icon"/>
</div>

<div id="notify" class="notify"><span id="notify_body">&nbsp;</span></div>
<div id="cmdline" style="display : none"></div>
<div id="auxDlg" style="display : none"></div>
<div id="headlines-tmp" style="display : none"></div>

<div id="main" dojoType="dijit.layout.BorderContainer">

<div id="feeds-holder" dojoType="dijit.layout.ContentPane" region="leading" style="width : 20%" splitter="true">
	<div id="feedlistLoading">
		<img src='images/indicator_tiny.gif'/>
		<?php echo  __("Loading, please wait..."); ?></div>
	<div id="feedTree"></div>
</div>

<div dojoType="dijit.layout.BorderContainer" region="center" id="header-wrap" gutters="false">
<div dojoType="dijit.layout.TabContainer" region="center" id="content-tabs">
<div dojoType="dijit.layout.BorderContainer" region="center" id="content-wrap"
	title="<?php echo __("News") ?>">

<div id="toolbar" dojoType="dijit.layout.ContentPane" region="top">
	<div id="main-toolbar" dojoType="dijit.Toolbar">

		<form id="main_toolbar_form" action="" onsubmit='return false'>

		<button dojoType="dijit.form.Button" id="collapse_feeds_btn"
			onclick="collapse_feedlist()"
			title="<?php echo __('Collapse feedlist') ?>" style="display : inline">
			&lt;&lt;</button>

		<select name="view_mode" title="<?php echo __('Show articles') ?>"
			onchange="viewModeChanged()"
			dojoType="dijit.form.Select">
			<option selected="selected" value="adaptive"><?php echo __('Adaptive') ?></option>
			<option value="all_articles"><?php echo __('All Articles') ?></option>
			<option value="marked"><?php echo __('Starred') ?></option>
			<option value="published"><?php echo __('Published') ?></option>
			<option value="unread"><?php echo __('Unread') ?></option>
			<!-- <option value="noscores"><?php echo __('Ignore Scoring') ?></option> -->
			<option value="updated"><?php echo __('Updated') ?></option>
		</select>

		<select title="<?php echo __('Sort articles') ?>"
			onchange="viewModeChanged()"
			dojoType="dijit.form.Select" name="order_by">
			<option selected="selected" value="default"><?php echo __('Default') ?></option>
			<option value="date"><?php echo __('Date') ?></option>
			<option value="title"><?php echo __('Title') ?></option>
			<option value="score"><?php echo __('Score') ?></option>
		</select>

		<button dojoType="dijit.form.Button" name="update"
			onclick="viewCurrentFeed()">
			<?php echo __('Update') ?></button>

		<button dojoType="dijit.form.Button"
			onclick="catchupCurrentFeed()">
			<?php echo __('Mark as read') ?></button>

		</form>

		<div class="actionChooser">
			<div dojoType="dijit.form.DropDownButton">
				<span><?php echo __('Actions...') ?></span>
				<div dojoType="dijit.Menu" style="display: none">
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcSearch')"><?php echo __('Search...') ?></div>
					<div dojoType="dijit.MenuItem" disabled="1"><?php echo __('Feed actions:') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcAddFeed')"><?php echo __('Subscribe to feed...') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcEditFeed')"><?php echo __('Edit this feed...') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcRescoreFeed')"><?php echo __('Rescore feed') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcRemoveFeed')"><?php echo __('Unsubscribe') ?></div>
					<div dojoType="dijit.MenuItem" disabled="1"><?php echo __('All feeds:') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcCatchupAll')"><?php echo __('Mark as read') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcShowOnlyUnread')"><?php echo __('(Un)hide read feeds') ?></div>
					<div dojoType="dijit.MenuItem" disabled="1"><?php echo __('Other actions:') ?></div>
					<?php if ($pluginhost->get_plugin("digest")) { ?>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcDigest')"><?php echo __('Switch to digest...') ?></div>
					<?php } ?>
						<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcTagCloud')"><?php echo __('Show tag cloud...') ?></div>
						<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcToggleWidescreen')"><?php echo __('Toggle widescreen mode') ?></div>

					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcTagSelect')"><?php echo __('Select by tags...') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcAddLabel')"><?php echo __('Create label...') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcAddFilter')"><?php echo __('Create filter...') ?></div>
					<div dojoType="dijit.MenuItem" onclick="quickMenuGo('qmcHKhelp')"><?php echo __('Keyboard shortcuts help') ?></div>
				</div>
			</div>
		</div>
	</div> <!-- toolbar -->
</div> <!-- toolbar pane -->

	<div id="headlines-wrap-inner" dojoType="dijit.layout.BorderContainer" region="center">

		<div id="headlines-toolbar" dojoType="dijit.layout.ContentPane" region="top">
		</div>

		<div id="headlines-frame" dojoType="dijit.layout.ContentPane"
				onscroll="headlines_scroll_handler(this)" region="center">
			<div id="headlinesInnerContainer">
				<div class="whiteBox"><?php echo __('Loading, please wait...') ?></div>
			</div>
		</div>

		<?php if (!get_pref($link, 'COMBINED_DISPLAY_MODE')) { ?>
		<div id="content-insert" dojoType="dijit.layout.ContentPane" region="bottom"
			style="height : 50%" splitter="true"></div>
		<?php } ?>

	</div>
</div>
</div>
</div>
</div>

<?php db_close($link); ?>

</body>
</html>
