<?php
class Handler_Public extends Handler {

	private function generate_syndicated_feed($owner_uid, $feed, $is_cat,
		$limit, $offset, $search, $search_mode,
		$view_mode = false, $format = 'atom') {

		require_once "lib/MiniTemplator.class.php";

		$note_style = 	"background-color : #fff7d5;
			border-width : 1px; ".
			"padding : 5px; border-style : dashed; border-color : #e7d796;".
			"margin-bottom : 1em; color : #9a8c59;";

		if (!$limit) $limit = 100;

		if (get_pref($this->link, "SORT_HEADLINES_BY_FEED_DATE", $owner_uid)) {
			$date_sort_field = "updated";
		} else {
			$date_sort_field = "date_entered";
		}

		if ($feed == -2)
			$date_sort_field = "last_published";
		else if ($feed == -1)
			$date_sort_field = "last_marked";

		$qfh_ret = queryFeedHeadlines($this->link, $feed,
			$limit, $view_mode, $is_cat, $search, $search_mode,
			"$date_sort_field DESC", $offset, $owner_uid,
			false, 0, false, true);

		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];

		$feed_self_url = get_self_url_prefix() .
			"/public.php?op=rss&id=-2&key=" .
			get_feed_access_key($this->link, -2, false, $owner_uid);

		if (!$feed_site_url) $feed_site_url = get_self_url_prefix();

		if ($format == 'atom') {
			$tpl = new MiniTemplator;

			$tpl->readTemplateFromFile("templates/generated_feed.txt");

			$tpl->setVariable('FEED_TITLE', $feed_title, true);
			$tpl->setVariable('VERSION', VERSION, true);
			$tpl->setVariable('FEED_URL', htmlspecialchars($feed_self_url), true);

			if (PUBSUBHUBBUB_HUB && $feed == -2) {
				$tpl->setVariable('HUB_URL', htmlspecialchars(PUBSUBHUBBUB_HUB), true);
				$tpl->addBlock('feed_hub');
			}

			$tpl->setVariable('SELF_URL', htmlspecialchars(get_self_url_prefix()), true);

	 		while ($line = db_fetch_assoc($result)) {
				$tpl->setVariable('ARTICLE_ID', htmlspecialchars($line['link']), true);
				$tpl->setVariable('ARTICLE_LINK', htmlspecialchars($line['link']), true);
				$tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($line['title']), true);
				$tpl->setVariable('ARTICLE_EXCERPT',
					truncate_string(strip_tags($line["content_preview"]), 100, '...'), true);

				$content = sanitize($this->link, $line["content_preview"], false, $owner_uid);

				if ($line['note']) {
					$content = "<div style=\"$note_style\">Article note: " . $line['note'] . "</div>" .
						$content;
}

				$tpl->setVariable('ARTICLE_CONTENT', $content, true);

				$tpl->setVariable('ARTICLE_UPDATED_ATOM',
					date('c', strtotime($line["updated"])), true);
				$tpl->setVariable('ARTICLE_UPDATED_RFC822',
					date(DATE_RFC822, strtotime($line["updated"])), true);

				$tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($line['author']), true);

				$tags = get_article_tags($this->link, $line["id"], $owner_uid);

				foreach ($tags as $tag) {
					$tpl->setVariable('ARTICLE_CATEGORY', htmlspecialchars($tag), true);
					$tpl->addBlock('category');
				}

				$enclosures = get_article_enclosures($this->link, $line["id"]);

				foreach ($enclosures as $e) {
					$type = htmlspecialchars($e['content_type']);
					$url = htmlspecialchars($e['content_url']);
					$length = $e['duration'];

					$tpl->setVariable('ARTICLE_ENCLOSURE_URL', $url, true);
					$tpl->setVariable('ARTICLE_ENCLOSURE_TYPE', $type, true);
					$tpl->setVariable('ARTICLE_ENCLOSURE_LENGTH', $length, true);

					$tpl->addBlock('enclosure');
				}

				$tpl->addBlock('entry');
			}

			$tmp = "";

			$tpl->addBlock('feed');
			$tpl->generateOutputToString($tmp);

			if (@!$_REQUEST["noxml"]) {
				header("Content-Type: text/xml; charset=utf-8");
			} else {
				header("Content-Type: text/plain; charset=utf-8");
			}

			print $tmp;
		} else if ($format == 'json') {

			$feed = array();

			$feed['title'] = $feed_title;
			$feed['version'] = VERSION;
			$feed['feed_url'] = $feed_self_url;

			if (PUBSUBHUBBUB_HUB && $feed == -2) {
				$feed['hub_url'] = PUBSUBHUBBUB_HUB;
			}

			$feed['self_url'] = get_self_url_prefix();

			$feed['articles'] = array();

			while ($line = db_fetch_assoc($result)) {
				$article = array();

				$article['id'] = $line['link'];
				$article['link']	= $line['link'];
				$article['title'] = $line['title'];
				$article['excerpt'] = truncate_string(strip_tags($line["content_preview"]), 100, '...');
				$article['content'] = sanitize($this->link, $line["content_preview"], false, $owner_uid);
				$article['updated'] = date('c', strtotime($line["updated"]));

				if ($line['note']) $article['note'] = $line['note'];
				if ($article['author']) $article['author'] = $line['author'];

				$tags = get_article_tags($this->link, $line["id"], $owner_uid);

				if (count($tags) > 0) {
					$article['tags'] = array();

					foreach ($tags as $tag) {
						array_push($article['tags'], $tag);
					}
				}

				$enclosures = get_article_enclosures($this->link, $line["id"]);

				if (count($enclosures) > 0) {
					$article['enclosures'] = array();

					foreach ($enclosures as $e) {
						$type = $e['content_type'];
						$url = $e['content_url'];
						$length = $e['duration'];

						array_push($article['enclosures'], array("url" => $url, "type" => $type, "length" => $length));
					}
				}

				array_push($feed['articles'], $article);
			}

			header("Content-Type: text/json; charset=utf-8");
			print json_encode($feed);

		} else {
			header("Content-Type: text/plain; charset=utf-8");
			print json_encode(array("error" => array("message" => "Unknown format")));
		}
	}

	function getUnread() {
		$login = db_escape_string($this->link, $_REQUEST["login"]);
		$fresh = $_REQUEST["fresh"] == "1";

		$result = db_query($this->link, "SELECT id FROM ttrss_users WHERE login = '$login'");

		if (db_num_rows($result) == 1) {
			$uid = db_fetch_result($result, 0, "id");

			print getGlobalUnread($this->link, $uid);

			if ($fresh) {
				print ";";
				print getFeedArticles($this->link, -3, false, true, $uid);
			}

		} else {
			print "-1;User not found";
		}

	}

	function getProfiles() {
		$login = db_escape_string($this->link, $_REQUEST["login"]);

		$result = db_query($this->link, "SELECT * FROM ttrss_settings_profiles,ttrss_users
			WHERE ttrss_users.id = ttrss_settings_profiles.owner_uid AND login = '$login' ORDER BY title");

		print "<select dojoType='dijit.form.Select' style='width : 220px; margin : 0px' name='profile'>";

		print "<option value='0'>" . __("Default profile") . "</option>";

		while ($line = db_fetch_assoc($result)) {
			$id = $line["id"];
			$title = $line["title"];

			print "<option value='$id'>$title</option>";
		}

		print "</select>";
	}

	function pubsub() {
		$mode = db_escape_string($this->link, $_REQUEST['hub_mode']);
		$feed_id = (int) db_escape_string($this->link, $_REQUEST['id']);
		$feed_url = db_escape_string($this->link, $_REQUEST['hub_topic']);

		if (!PUBSUBHUBBUB_ENABLED) {
			header('HTTP/1.0 404 Not Found');
			echo "404 Not found";
			return;
		}

		// TODO: implement hub_verifytoken checking

		$result = db_query($this->link, "SELECT feed_url FROM ttrss_feeds
			WHERE id = '$feed_id'");

		if (db_num_rows($result) != 0) {

			$check_feed_url = db_fetch_result($result, 0, "feed_url");

			if ($check_feed_url && ($check_feed_url == $feed_url || !$feed_url)) {
				if ($mode == "subscribe") {

					db_query($this->link, "UPDATE ttrss_feeds SET pubsub_state = 2
						WHERE id = '$feed_id'");

					print $_REQUEST['hub_challenge'];
					return;

				} else if ($mode == "unsubscribe") {

					db_query($this->link, "UPDATE ttrss_feeds SET pubsub_state = 0
						WHERE id = '$feed_id'");

					print $_REQUEST['hub_challenge'];
					return;

				} else if (!$mode) {

					// Received update ping, schedule feed update.
					//update_rss_feed($this->link, $feed_id, true, true);

					db_query($this->link, "UPDATE ttrss_feeds SET
						last_update_started = '1970-01-01',
						last_updated = '1970-01-01' WHERE id = '$feed_id'");

				}
			} else {
				header('HTTP/1.0 404 Not Found');
				echo "404 Not found";
			}
		} else {
			header('HTTP/1.0 404 Not Found');
			echo "404 Not found";
		}

	}

	function logout() {
		logout_user();
		header("Location: index.php");
	}

	function share() {
		$uuid = db_escape_string($this->link, $_REQUEST["key"]);

		$result = db_query($this->link, "SELECT ref_id, owner_uid FROM ttrss_user_entries WHERE
			uuid = '$uuid'");

		if (db_num_rows($result) != 0) {
			header("Content-Type: text/html");

			$id = db_fetch_result($result, 0, "ref_id");
			$owner_uid = db_fetch_result($result, 0, "owner_uid");

			$article = format_article($this->link, $id, false, true, $owner_uid);

			print_r($article['content']);

		} else {
			print "Article not found.";
		}

	}

	function rss() {
		$feed = db_escape_string($this->link, $_REQUEST["id"]);
		$key = db_escape_string($this->link, $_REQUEST["key"]);
		$is_cat = $_REQUEST["is_cat"] != false;
		$limit = (int)db_escape_string($this->link, $_REQUEST["limit"]);
		$offset = (int)db_escape_string($this->link, $_REQUEST["offset"]);

		$search = db_escape_string($this->link, $_REQUEST["q"]);
		$search_mode = db_escape_string($this->link, $_REQUEST["smode"]);
		$view_mode = db_escape_string($this->link, $_REQUEST["view-mode"]);

		$format = db_escape_string($this->link, $_REQUEST['format']);

		if (!$format) $format = 'atom';

		if (SINGLE_USER_MODE) {
			authenticate_user($this->link, "admin", null);
		}

		$owner_id = false;

		if ($key) {
			$result = db_query($this->link, "SELECT owner_uid FROM
				ttrss_access_keys WHERE access_key = '$key' AND feed_id = '$feed'");

			if (db_num_rows($result) == 1)
				$owner_id = db_fetch_result($result, 0, "owner_uid");
		}

		if ($owner_id) {
			$this->generate_syndicated_feed($owner_id, $feed, $is_cat, $limit,
				$offset, $search, $search_mode, $view_mode, $format);
		} else {
			header('HTTP/1.1 403 Forbidden');
		}
	}

	function globalUpdateFeeds() {
		include "rssfuncs.php";
		// Update all feeds needing a update.
		update_daemon_common($this->link, 0, true, false);

		// Update feedbrowser
		update_feedbrowser_cache($this->link);

		// Purge orphans and cleanup tags
		purge_orphans($this->link);

		cleanup_tags($this->link, 14, 50000);

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_UPDATE_TASK, "hook_update_task", $op);

	}

	function sharepopup() {
		if (SINGLE_USER_MODE) {
			login_sequence($this->link);
		}

		header('Content-Type: text/html; charset=utf-8');
		print "<html>
				<head>
					<title>Tiny Tiny RSS</title>
					<link rel=\"stylesheet\" type=\"text/css\" href=\"utility.css\">
					<script type=\"text/javascript\" src=\"lib/prototype.js\"></script>
					<script type=\"text/javascript\" src=\"lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls\"></script>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
				</head>
				<body id='sharepopup'>";

		$action = $_REQUEST["action"];

		if ($_SESSION["uid"]) {

			if ($action == 'share') {

				$title = db_escape_string($this->link, strip_tags($_REQUEST["title"]));
				$url = db_escape_string($this->link, strip_tags($_REQUEST["url"]));
				$content = db_escape_string($this->link, strip_tags($_REQUEST["content"]));
				$labels = db_escape_string($this->link, strip_tags($_REQUEST["labels"]));

				Article::create_published_article($this->link, $title, $url, $content, $labels,
					$_SESSION["uid"]);

				print "<script type='text/javascript'>";
				print "window.close();";
				print "</script>";

			} else {
				$title = htmlspecialchars($_REQUEST["title"]);
				$url = htmlspecialchars($_REQUEST["url"]);

				?>

				<table height='100%' width='100%'><tr><td colspan='2'>
				<h1><?php echo __("Share with Tiny Tiny RSS") ?></h1>
				</td></tr>

				<form id='share_form' name='share_form'>

				<input type="hidden" name="op" value="sharepopup">
				<input type="hidden" name="action" value="share">

				<tr><td align='right'><?php echo __("Title:") ?></td>
				<td width='80%'><input name='title' value="<?php echo $title ?>"></td></tr>
				<tr><td align='right'><?php echo __("URL:") ?></td>
				<td><input name='url' value="<?php echo $url ?>"></td></tr>
				<tr><td align='right'><?php echo __("Content:") ?></td>
				<td><input name='content' value=""></td></tr>
				<tr><td align='right'><?php echo __("Labels:") ?></td>
				<td><input name='labels' id="labels_value"
					placeholder='Alpha, Beta, Gamma' value="">
				</td></tr>

				<tr><td>
					<div class="autocomplete" id="labels_choices"
						style="display : block"></div></td></tr>

				<script type='text/javascript'>document.forms[0].title.focus();</script>

				<script type='text/javascript'>
					new Ajax.Autocompleter('labels_value', 'labels_choices',
				   "backend.php?op=rpc&method=completeLabels",
				   { tokens: ',', paramName: "search" });
				</script>

				<tr><td colspan='2'>
					<div style='float : right' class='insensitive-small'>
					<?php echo __("Shared article will appear in the Published feed.") ?>
					</div>
					<button type="submit"><?php echo __('Share') ?></button>
					<button onclick="return window.close()"><?php echo __('Cancel') ?></button>
					</div>

				</form>
				</td></tr></table>
				</body></html>
				<?php

			}

		} else {

			$return = urlencode($_SERVER["REQUEST_URI"])
			?>

			<form action="public.php?return=<?php echo $return ?>"
				method="POST" id="loginForm" name="loginForm">

			<input type="hidden" name="op" value="login">

			<table height='100%' width='100%'><tr><td colspan='2'>
			<h1><?php echo __("Not logged in") ?></h1></td></tr>

			<tr><td align="right"><?php echo __("Login:") ?></td>
			<td align="right"><input name="login"
				value="<?php echo $_SESSION["fake_login"] ?>"></td></tr>
				<tr><td align="right"><?php echo __("Password:") ?></td>
				<td align="right"><input type="password" name="password"
				value="<?php echo $_SESSION["fake_password"] ?>"></td></tr>
			<tr><td align="right"><?php echo __("Language:") ?></td>
			<td align="right">
			<?php
				print_select_hash("language", $_COOKIE["ttrss_lang"], get_translations(),
					"style='width : 100%''");

			?>
			</td></tr>
			<tr><td colspan='2'>
				<button type="submit">
					<?php echo __('Log in') ?></button>

				<button onclick="return window.close()">
					<?php echo __('Cancel') ?></button>
			</td></tr>
			</table>

			</form>
			<?php
		}
	}

	function login() {
		@session_destroy();
		@session_start();

		$_SESSION["prefs_cache"] = array();

		if (!SINGLE_USER_MODE) {

			$login = db_escape_string($this->link, $_POST["login"]);
			$password = $_POST["password"];
			$remember_me = $_POST["remember_me"];

			if (authenticate_user($this->link, $login, $password)) {
				$_POST["password"] = "";

				$_SESSION["language"] = $_POST["language"];
				$_SESSION["ref_schema_version"] = get_schema_version($this->link, true);
				$_SESSION["bw_limit"] = !!$_POST["bw_limit"];

				if ($_POST["profile"]) {

					$profile = db_escape_string($this->link, $_POST["profile"]);

					$result = db_query($this->link, "SELECT id FROM ttrss_settings_profiles
						WHERE id = '$profile' AND owner_uid = " . $_SESSION["uid"]);

					if (db_num_rows($result) != 0) {
						$_SESSION["profile"] = $profile;
						$_SESSION["prefs_cache"] = array();
					}
				}
			} else {
				$_SESSION["login_error_msg"] = __("Incorrect username or password");
			}

			if ($_REQUEST['return']) {
				header("Location: " . $_REQUEST['return']);
			} else {
				header("Location: " . SELF_URL_PATH);
			}
		}
	}

	function subscribe() {
		if (SINGLE_USER_MODE) {
			login_sequence($this->link);
		}

		if ($_SESSION["uid"]) {

			$feed_url = db_escape_string($this->link, trim($_REQUEST["feed_url"]));

			header('Content-Type: text/html; charset=utf-8');
			print "<html>
				<head>
					<title>Tiny Tiny RSS</title>
					<link rel=\"stylesheet\" type=\"text/css\" href=\"utility.css\">
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
				</head>
				<body>
				<img class=\"floatingLogo\" src=\"images/logo_small.png\"
			  		alt=\"Tiny Tiny RSS\"/>
					<h1>".__("Subscribe to feed...")."</h1><div class='content'>";

			$rc = subscribe_to_feed($this->link, $feed_url);

			switch ($rc['code']) {
			case 0:
				print_warning(T_sprintf("Already subscribed to <b>%s</b>.", $feed_url));
				break;
			case 1:
				print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
				break;
			case 2:
				print_error(T_sprintf("Could not subscribe to <b>%s</b>.", $feed_url));
				break;
			case 3:
				print_error(T_sprintf("No feeds found in <b>%s</b>.", $feed_url));
				break;
			case 4:
				print_notice(__("Multiple feed URLs found."));
				$feed_urls = $rc["feeds"];
				break;
			case 5:
				print_error(T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url));
				break;
			}

			if ($feed_urls) {

				print "<form action=\"public.php\">";
				print "<input type=\"hidden\" name=\"op\" value=\"subscribe\">";

				print "<select name=\"feed_url\">";

				foreach ($feed_urls as $url => $name) {
					$url = htmlspecialchars($url);
					$name = htmlspecialchars($name);

					print "<option value=\"$url\">$name</option>";
				}

				print "<input type=\"submit\" value=\"".__("Subscribe to selected feed").
					"\">";

				print "</form>";
			}

			$tp_uri = get_self_url_prefix() . "/prefs.php";
			$tt_uri = get_self_url_prefix();

			if ($rc['code'] <= 2){
				$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				$feed_id = db_fetch_result($result, 0, "id");
			} else {
				$feed_id = 0;
			}
			print "<p>";

			if ($feed_id) {
				print "<form method=\"GET\" style='display: inline'
					action=\"$tp_uri\">
					<input type=\"hidden\" name=\"tab\" value=\"feedConfig\">
					<input type=\"hidden\" name=\"method\" value=\"editFeed\">
					<input type=\"hidden\" name=\"methodparam\" value=\"$feed_id\">
					<input type=\"submit\" value=\"".__("Edit subscription options")."\">
					</form>";
			}

			print "<form style='display: inline' method=\"GET\" action=\"$tt_uri\">
				<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
				</form></p>";

			print "</div></body></html>";

		} else {
			render_login_form($this->link);
		}
	}

	function subscribe2() {
		$feed_url = db_escape_string($this->link, trim($_REQUEST["feed_url"]));
		$cat_id = db_escape_string($this->link, $_REQUEST["cat_id"]);
		$from = db_escape_string($this->link, $_REQUEST["from"]);

		/* only read authentication information from POST */

		$auth_login = db_escape_string($this->link, trim($_POST["auth_login"]));
		$auth_pass = db_escape_string($this->link, trim($_POST["auth_pass"]));

		$rc = subscribe_to_feed($this->link, $feed_url, $cat_id, $auth_login, $auth_pass);

		switch ($rc) {
		case 1:
			print_notice(T_sprintf("Subscribed to <b>%s</b>.", $feed_url));
			break;
		case 2:
			print_error(T_sprintf("Could not subscribe to <b>%s</b>.", $feed_url));
			break;
		case 3:
			print_error(T_sprintf("No feeds found in <b>%s</b>.", $feed_url));
			break;
		case 0:
			print_warning(T_sprintf("Already subscribed to <b>%s</b>.", $feed_url));
			break;
		case 4:
			print_notice(__("Multiple feed URLs found."));

			$feed_urls = get_feeds_from_html($feed_url);
			break;
		case 5:
			print_error(T_sprintf("Could not subscribe to <b>%s</b>.<br>Can't download the Feed URL.", $feed_url));
			break;
		}

		if ($feed_urls) {
			print "<form action=\"backend.php\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-feeds\">";
			print "<input type=\"hidden\" name=\"quiet\" value=\"1\">";
			print "<input type=\"hidden\" name=\"method\" value=\"add\">";

			print "<select name=\"feed_url\">";

			foreach ($feed_urls as $url => $name) {
				$url = htmlspecialchars($url);
				$name = htmlspecialchars($name);
				print "<option value=\"$url\">$name</option>";
			}

			print "<input type=\"submit\" value=\"".__("Subscribe to selected feed")."\">";
			print "</form>";
		}

		$tp_uri = get_self_url_prefix() . "/prefs.php";
		$tt_uri = get_self_url_prefix();

		if ($rc <= 2){
			$result = db_query($this->link, "SELECT id FROM ttrss_feeds WHERE
				feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

			$feed_id = db_fetch_result($result, 0, "id");
		} else {
			$feed_id = 0;
		}

		print "<p>";

		if ($feed_id) {
			print "<form method=\"GET\" style='display: inline'
				action=\"$tp_uri\">
				<input type=\"hidden\" name=\"tab\" value=\"feedConfig\">
				<input type=\"hidden\" name=\"method\" value=\"editFeed\">
				<input type=\"hidden\" name=\"methodparam\" value=\"$feed_id\">
				<input type=\"submit\" value=\"".__("Edit subscription options")."\">
				</form>";
		}

		print "<form style='display: inline' method=\"GET\" action=\"$tt_uri\">
			<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
			</form></p>";

		print "</body></html>";
	}

	function index() {
		header("Content-Type: text/plain");
		print json_encode(array("error" => array("code" => 7)));
	}

	function forgotpass() {
		header('Content-Type: text/html; charset=utf-8');
		print "<html>
				<head>
					<title>Tiny Tiny RSS</title>
					<link rel=\"stylesheet\" type=\"text/css\" href=\"utility.css\">
					<script type=\"text/javascript\" src=\"lib/prototype.js\"></script>
					<script type=\"text/javascript\" src=\"lib/scriptaculous/scriptaculous.js?load=effects,dragdrop,controls\"></script>
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
				</head>
				<body id='forgotpass'>";

		print '<div class="floatingLogo"><img src="images/logo_small.png"></div>';
		print "<h1>".__("Reset password")."</h1>";
		print "<div class='content'>";

		print "<p>".__("You will need to provide valid account name and email. New password will be sent on your email address.")."</p>";

		@$method = $_POST['method'];

		if (!$method) {
			$secretkey = uniqid();
			$_SESSION["secretkey"] = $secretkey;

			print "<form method='POST' action='public.php'>";
			print "<input type='hidden' name='secretkey' value='$secretkey'>";
			print "<input type='hidden' name='method' value='do'>";
			print "<input type='hidden' name='op' value='forgotpass'>";

			print "<fieldset>";
			print "<label>".__("Login:")."</label>";
			print "<input type='text' name='login' value='' required>";
			print "</fieldset>";

			print "<fieldset>";
			print "<label>".__("Email:")."</label>";
			print "<input type='email' name='email' value='' required>";
			print "</fieldset>";

			print "<fieldset>";
			print "<label>".__("How much is two plus two:")."</label>";
			print "<input type='text' name='test' value='' required>";
			print "</fieldset>";

			print "<p/>";
			print "<button type='submit'>".__("Reset password")."</button>";

			print "</form>";
		} else if ($method == 'do') {

			$secretkey = $_POST["secretkey"];
			$login = db_escape_string($this->link, $_POST["login"]);
			$email = db_escape_string($this->link, $_POST["email"]);
			$test = db_escape_string($this->link, $_POST["test"]);

			if (($test != 4 && $test != 'four') || !$email || !$login) {
				print_error(__('Some of the required form parameters are missing or incorrect.'));

				print "<p><a href=\"public.php?op=forgotpass\">".__("Go back")."</a></p>";

			} else if ($_SESSION["secretkey"] == $secretkey) {

				$result = db_query($this->link, "SELECT id FROM ttrss_users
					WHERE login = '$login' AND email = '$email'");

				if (db_num_rows($result) != 0) {
					$id = db_fetch_result($result, 0, "id");

					Pref_Users::resetUserPassword($this->link, $id, false);

					print "<p>".__("Completed.")."</p>";

				} else {
					print_error(__("Sorry, login and email combination not found."));
					print "<p><a href=\"public.php?op=forgotpass\">".__("Go back")."</a></p>";
				}

			} else {
				print_error(__("Form secret key incorrect. Please enable cookies and try again."));
				print "<p><a href=\"public.php?op=forgotpass\">".__("Go back")."</a></p>";

			}

		}

		print "</div>";
		print "</body>";
		print "</html>";

	}

}
?>
