<?php
class Handler_Public extends Handler {

	private function generate_syndicated_feed($owner_uid, $feed, $is_cat,
		$limit, $offset, $search, $search_mode,
		$view_mode = false, $format = 'atom', $order = false, $orig_guid = false) {

		require_once "lib/MiniTemplator.class.php";

		$note_style = 	"background-color : #fff7d5;
			border-width : 1px; ".
			"padding : 5px; border-style : dashed; border-color : #e7d796;".
			"margin-bottom : 1em; color : #9a8c59;";

		if (!$limit) $limit = 60;

		$date_sort_field = "date_entered DESC, updated DESC";
		$date_check_field = "date_entered";

		if ($feed == -2 && !$is_cat) {
			$date_sort_field = "last_published DESC";
			$date_check_field = "last_published";
		} else if ($feed == -1 && !$is_cat) {
			$date_sort_field = "last_marked DESC";
			$date_check_field = "last_marked";
		}

		switch ($order) {
		case "title":
			$date_sort_field = "ttrss_entries.title";
			break;
		case "date_reverse":
			$date_sort_field = "date_entered, updated";
			break;
		case "feed_dates":
			$date_sort_field = "updated DESC";
			break;
		}

		$qfh_ret = queryFeedHeadlines($feed,
			1, $view_mode, $is_cat, $search, $search_mode,
			$date_sort_field, $offset, $owner_uid,
			false, 0, false, true);

		$result = $qfh_ret[0];

		if ($this->dbh->num_rows($result) != 0) {

			$ts = strtotime($this->dbh->fetch_result($result, 0, $date_check_field));

			if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
					strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $ts) {
		      header('HTTP/1.0 304 Not Modified');
		      return;
			}

			$last_modified = gmdate("D, d M Y H:i:s", $ts) . " GMT";
			header("Last-Modified: $last_modified", true);
		}

		$qfh_ret = queryFeedHeadlines($feed,
			$limit, $view_mode, $is_cat, $search, $search_mode,
			$date_sort_field, $offset, $owner_uid,
			false, 0, false, true);


		$result = $qfh_ret[0];
		$feed_title = htmlspecialchars($qfh_ret[1]);
		$feed_site_url = $qfh_ret[2];
		$last_error = $qfh_ret[3];

		$feed_self_url = get_self_url_prefix() .
			"/public.php?op=rss&id=$feed&key=" .
			get_feed_access_key($feed, false, $owner_uid);

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
			while ($line = $this->dbh->fetch_assoc($result)) {
				$line["content_preview"] = truncate_string(strip_tags($line["content"]), 100, '...');

				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
					$line = $p->hook_query_headlines($line);
				}

				$tpl->setVariable('ARTICLE_ID',
					htmlspecialchars($orig_guid ? $line['link'] :
						get_self_url_prefix() .
							"/public.php?url=" . urlencode($line['link'])), true);
				$tpl->setVariable('ARTICLE_LINK', htmlspecialchars($line['link']), true);
				$tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($line['title']), true);
				$tpl->setVariable('ARTICLE_EXCERPT', $line["content_preview"], true);

				$content = sanitize($line["content"], false, $owner_uid);

				if ($line['note']) {
					$content = "<div style=\"$note_style\">Article note: " . $line['note'] . "</div>" .
						$content;
					$tpl->setVariable('ARTICLE_NOTE', htmlspecialchars($line['note']), true);
				}

				$tpl->setVariable('ARTICLE_CONTENT', $content, true);

				$tpl->setVariable('ARTICLE_UPDATED_ATOM',
					date('c', strtotime($line["updated"])), true);
				$tpl->setVariable('ARTICLE_UPDATED_RFC822',
					date(DATE_RFC822, strtotime($line["updated"])), true);

				$tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($line['author']), true);

				$tpl->setVariable('ARTICLE_SOURCE_LINK', htmlspecialchars($line['site_url']), true);
				$tpl->setVariable('ARTICLE_SOURCE_TITLE', htmlspecialchars($line['feed_title'] ? $line['feed_title'] : $feed_title), true);

				$tags = get_article_tags($line["id"], $owner_uid);

				foreach ($tags as $tag) {
					$tpl->setVariable('ARTICLE_CATEGORY', htmlspecialchars($tag), true);
					$tpl->addBlock('category');
				}

				$enclosures = get_article_enclosures($line["id"]);

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

			while ($line = $this->dbh->fetch_assoc($result)) {
				$line["content_preview"] = truncate_string(strip_tags($line["content_preview"]), 100, '...');
				foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_QUERY_HEADLINES) as $p) {
					$line = $p->hook_query_headlines($line, 100);
				}
				$article = array();

				$article['id'] = $line['link'];
				$article['link']	= $line['link'];
				$article['title'] = $line['title'];
				$article['excerpt'] = $line["content_preview"];
				$article['content'] = sanitize($line["content"], false, $owner_uid);
				$article['updated'] = date('c', strtotime($line["updated"]));

				if ($line['note']) $article['note'] = $line['note'];
				if ($article['author']) $article['author'] = $line['author'];

				$tags = get_article_tags($line["id"], $owner_uid);

				if (count($tags) > 0) {
					$article['tags'] = array();

					foreach ($tags as $tag) {
						array_push($article['tags'], $tag);
					}
				}

				$enclosures = get_article_enclosures($line["id"]);

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
		$login = $this->dbh->escape_string($_REQUEST["login"]);
		$fresh = $_REQUEST["fresh"] == "1";

		$result = $this->dbh->query("SELECT id FROM ttrss_users WHERE login = '$login'");

		if ($this->dbh->num_rows($result) == 1) {
			$uid = $this->dbh->fetch_result($result, 0, "id");

			print getGlobalUnread($uid);

			if ($fresh) {
				print ";";
				print getFeedArticles(-3, false, true, $uid);
			}

		} else {
			print "-1;User not found";
		}

	}

	function getProfiles() {
		$login = $this->dbh->escape_string($_REQUEST["login"]);

		$result = $this->dbh->query("SELECT ttrss_settings_profiles.* FROM ttrss_settings_profiles,ttrss_users
			WHERE ttrss_users.id = ttrss_settings_profiles.owner_uid AND login = '$login' ORDER BY title");

		print "<select dojoType='dijit.form.Select' style='width : 220px; margin : 0px' name='profile'>";

		print "<option value='0'>" . __("Default profile") . "</option>";

		while ($line = $this->dbh->fetch_assoc($result)) {
			$id = $line["id"];
			$title = $line["title"];

			print "<option value='$id'>$title</option>";
		}

		print "</select>";
	}

	function pubsub() {
		$mode = $this->dbh->escape_string($_REQUEST['hub_mode']);
		if (!$mode) $mode = $this->dbh->escape_string($_REQUEST['hub.mode']);

		$feed_id = (int) $this->dbh->escape_string($_REQUEST['id']);
		$feed_url = $this->dbh->escape_string($_REQUEST['hub_topic']);

		if (!$feed_url) $feed_url = $this->dbh->escape_string($_REQUEST['hub.topic']);

		if (!PUBSUBHUBBUB_ENABLED) {
			header('HTTP/1.0 404 Not Found');
			echo "404 Not found (Disabled by server)";
			return;
		}

		// TODO: implement hub_verifytoken checking
		// TODO: store requested rel=self or whatever for verification
		// (may be different from stored feed url) e.g. http://url/ or http://url

		$result = $this->dbh->query("SELECT feed_url FROM ttrss_feeds
			WHERE id = '$feed_id'");

		if ($this->dbh->num_rows($result) != 0) {

			$check_feed_url = $this->dbh->fetch_result($result, 0, "feed_url");

			// ignore url checking for the time being
			if ($check_feed_url && (true || $check_feed_url == $feed_url || !$feed_url)) {
				if ($mode == "subscribe") {

					$this->dbh->query("UPDATE ttrss_feeds SET pubsub_state = 2
						WHERE id = '$feed_id'");

					print $_REQUEST['hub_challenge'];
					return;

				} else if ($mode == "unsubscribe") {

					$this->dbh->query("UPDATE ttrss_feeds SET pubsub_state = 0
						WHERE id = '$feed_id'");

					print $_REQUEST['hub_challenge'];
					return;

				} else if (!$mode) {

					// Received update ping, schedule feed update.
					//update_rss_feed($feed_id, true, true);

					$this->dbh->query("UPDATE ttrss_feeds SET
						last_update_started = '1970-01-01',
						last_updated = '1970-01-01' WHERE id = '$feed_id'");

				}
			} else {
				header('HTTP/1.0 404 Not Found');
				echo "404 Not found (URL check failed)";
			}
		} else {
			header('HTTP/1.0 404 Not Found');
			echo "404 Not found (Feed not found)";
		}

	}

	function logout() {
		logout_user();
		header("Location: index.php");
	}

	function share() {
		$uuid = $this->dbh->escape_string($_REQUEST["key"]);

		$result = $this->dbh->query("SELECT ref_id, owner_uid FROM ttrss_user_entries WHERE
			uuid = '$uuid'");

		if ($this->dbh->num_rows($result) != 0) {
			header("Content-Type: text/html");

			$id = $this->dbh->fetch_result($result, 0, "ref_id");
			$owner_uid = $this->dbh->fetch_result($result, 0, "owner_uid");

			$article = format_article($id, false, true, $owner_uid);

			print_r($article['content']);

		} else {
			print "Article not found.";
		}

	}

	function rss() {
		$feed = $this->dbh->escape_string($_REQUEST["id"]);
		$key = $this->dbh->escape_string($_REQUEST["key"]);
		$is_cat = sql_bool_to_bool($_REQUEST["is_cat"]);
		$limit = (int)$this->dbh->escape_string($_REQUEST["limit"]);
		$offset = (int)$this->dbh->escape_string($_REQUEST["offset"]);

		$search = $this->dbh->escape_string($_REQUEST["q"]);
		$search_mode = $this->dbh->escape_string($_REQUEST["smode"]);
		$view_mode = $this->dbh->escape_string($_REQUEST["view-mode"]);
		$order = $this->dbh->escape_string($_REQUEST["order"]);

		$format = $this->dbh->escape_string($_REQUEST['format']);
		$orig_guid = sql_bool_to_bool($_REQUEST["orig_guid"]);

		if (!$format) $format = 'atom';

		if (SINGLE_USER_MODE) {
			authenticate_user("admin", null);
		}

		$owner_id = false;

		if ($key) {
			$result = $this->dbh->query("SELECT owner_uid FROM
				ttrss_access_keys WHERE access_key = '$key' AND feed_id = '$feed'");

			if ($this->dbh->num_rows($result) == 1)
				$owner_id = $this->dbh->fetch_result($result, 0, "owner_uid");
		}

		if ($owner_id) {
			$this->generate_syndicated_feed($owner_id, $feed, $is_cat, $limit,
				$offset, $search, $search_mode, $view_mode, $format, $order, $orig_guid);
		} else {
			header('HTTP/1.1 403 Forbidden');
		}
	}

	function updateTask() {
		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", $op);
	}

	function housekeepingTask() {
		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_HOUSE_KEEPING, "hook_house_keeping", $op);
	}

	function globalUpdateFeeds() {
		RPC::updaterandomfeed_real($this->dbh);

		PluginHost::getInstance()->run_hooks(PluginHost::HOOK_UPDATE_TASK, "hook_update_task", $op);
	}

	function sharepopup() {
		if (SINGLE_USER_MODE) {
			login_sequence();
		}

		header('Content-Type: text/html; charset=utf-8');
		print "<html><head><title>Tiny Tiny RSS</title>
		<link rel=\"shortcut icon\" type=\"image/png\" href=\"images/favicon.png\">
		<link rel=\"icon\" type=\"image/png\" sizes=\"72x72\" href=\"images/favicon-72px.png\">";

		echo stylesheet_tag("css/utility.css");
		echo stylesheet_tag("css/dijit.css");
		echo javascript_tag("lib/prototype.js");
		echo javascript_tag("lib/scriptaculous/scriptaculous.js?load=effects,controls");
		print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
			</head><body id='sharepopup'>";

		$action = $_REQUEST["action"];

		if ($_SESSION["uid"]) {

			if ($action == 'share') {

				$title = $this->dbh->escape_string(strip_tags($_REQUEST["title"]));
				$url = $this->dbh->escape_string(strip_tags($_REQUEST["url"]));
				$content = $this->dbh->escape_string(strip_tags($_REQUEST["content"]));
				$labels = $this->dbh->escape_string(strip_tags($_REQUEST["labels"]));

				Article::create_published_article($title, $url, $content, $labels,
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
		if (!SINGLE_USER_MODE) {

			$login = $this->dbh->escape_string($_POST["login"]);
			$password = $_POST["password"];
			$remember_me = $_POST["remember_me"];

			if ($remember_me) {
				session_set_cookie_params(SESSION_COOKIE_LIFETIME);
			} else {
				session_set_cookie_params(0);
			}

			@session_start();

			if (authenticate_user($login, $password)) {
				$_POST["password"] = "";

				if (get_schema_version() >= 120) {
					$_SESSION["language"] = get_pref("USER_LANGUAGE", $_SESSION["uid"]);
				}

				$_SESSION["ref_schema_version"] = get_schema_version(true);
				$_SESSION["bw_limit"] = !!$_POST["bw_limit"];

				if ($_POST["profile"]) {

					$profile = $this->dbh->escape_string($_POST["profile"]);

					$result = $this->dbh->query("SELECT id FROM ttrss_settings_profiles
						WHERE id = '$profile' AND owner_uid = " . $_SESSION["uid"]);

					if ($this->dbh->num_rows($result) != 0) {
						$_SESSION["profile"] = $profile;
					}
				}
			} else {
				$_SESSION["login_error_msg"] = __("Incorrect username or password");
				user_error("Failed login attempt for $login from {$_SERVER['REMOTE_ADDR']}", E_USER_WARNING);
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
			login_sequence();
		}

		if ($_SESSION["uid"]) {

			$feed_url = $this->dbh->escape_string(trim($_REQUEST["feed_url"]));

			header('Content-Type: text/html; charset=utf-8');
			print "<html>
				<head>
					<title>Tiny Tiny RSS</title>
					<link rel=\"stylesheet\" type=\"text/css\" href=\"css/utility.css\">
					<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
					<link rel=\"shortcut icon\" type=\"image/png\" href=\"images/favicon.png\">
					<link rel=\"icon\" type=\"image/png\" sizes=\"72x72\" href=\"images/favicon-72px.png\">

				</head>
				<body>
				<img class=\"floatingLogo\" src=\"images/logo_small.png\"
			  		alt=\"Tiny Tiny RSS\"/>
					<h1>".__("Subscribe to feed...")."</h1><div class='content'>";

			$rc = subscribe_to_feed($feed_url);

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
				$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE
					feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

				$feed_id = $this->dbh->fetch_result($result, 0, "id");
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
			render_login_form();
		}
	}

	function subscribe2() {
		$feed_url = $this->dbh->escape_string(trim($_REQUEST["feed_url"]));
		$cat_id = $this->dbh->escape_string($_REQUEST["cat_id"]);
		$from = $this->dbh->escape_string($_REQUEST["from"]);
		$feed_urls = array();

		/* only read authentication information from POST */

		$auth_login = $this->dbh->escape_string(trim($_POST["auth_login"]));
		$auth_pass = $this->dbh->escape_string(trim($_POST["auth_pass"]));

		$rc = subscribe_to_feed($feed_url, $cat_id, $auth_login, $auth_pass);

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
 			$contents = @fetch_file_contents($url, false, $auth_login, $auth_pass);
			if (is_html($contents)) {
				$feed_urls = get_feeds_from_html($url, $contents);
			}
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
			$result = $this->dbh->query("SELECT id FROM ttrss_feeds WHERE
				feed_url = '$feed_url' AND owner_uid = " . $_SESSION["uid"]);

			$feed_id = $this->dbh->fetch_result($result, 0, "id");
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
		startup_gettext();

		header('Content-Type: text/html; charset=utf-8');
		print "<html><head><title>Tiny Tiny RSS</title>
		<link rel=\"shortcut icon\" type=\"image/png\" href=\"images/favicon.png\">
		<link rel=\"icon\" type=\"image/png\" sizes=\"72x72\" href=\"images/favicon-72px.png\">";

		echo stylesheet_tag("css/utility.css");
		echo javascript_tag("lib/prototype.js");

		print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"/>
			</head><body id='forgotpass'>";

		print '<div class="floatingLogo"><img src="images/logo_small.png"></div>';
		print "<h1>".__("Password recovery")."</h1>";
		print "<div class='content'>";

		@$method = $_POST['method'];

		if (!$method) {
			print_notice(__("You will need to provide valid account name and email. New password will be sent on your email address."));

			print "<form method='POST' action='public.php'>";
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

			$login = $this->dbh->escape_string($_POST["login"]);
			$email = $this->dbh->escape_string($_POST["email"]);
			$test = $this->dbh->escape_string($_POST["test"]);

			if (($test != 4 && $test != 'four') || !$email || !$login) {
				print_error(__('Some of the required form parameters are missing or incorrect.'));

				print "<form method=\"GET\" action=\"public.php\">
					<input type=\"hidden\" name=\"op\" value=\"forgotpass\">
					<input type=\"submit\" value=\"".__("Go back")."\">
					</form>";

			} else {

				$result = $this->dbh->query("SELECT id FROM ttrss_users
					WHERE login = '$login' AND email = '$email'");

				if ($this->dbh->num_rows($result) != 0) {
					$id = $this->dbh->fetch_result($result, 0, "id");

					Pref_Users::resetUserPassword($id, false);

					print "<p>";

					print "<p>"."Completed."."</p>";

					print "<form method=\"GET\" action=\"index.php\">
						<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
						</form>";

				} else {
					print_error(__("Sorry, login and email combination not found."));

					print "<form method=\"GET\" action=\"public.php\">
						<input type=\"hidden\" name=\"op\" value=\"forgotpass\">
						<input type=\"submit\" value=\"".__("Go back")."\">
						</form>";

				}
			}

		}

		print "</div>";
		print "</body>";
		print "</html>";

	}

	function dbupdate() {
		startup_gettext();

		if (!SINGLE_USER_MODE && $_SESSION["access_level"] < 10) {
			$_SESSION["login_error_msg"] = __("Your access level is insufficient to run this script.");
			render_login_form();
			exit;
		}

		?><html>
			<head>
			<title>Database Updater</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
			<link rel="stylesheet" type="text/css" href="css/utility.css"/>
			<link rel=\"shortcut icon\" type=\"image/png\" href=\"images/favicon.png\">
			<link rel=\"icon\" type=\"image/png\" sizes=\"72x72\" href=\"images/favicon-72px.png\">
			</head>
			<style type="text/css">
				span.ok { color : #009000; font-weight : bold; }
				span.err { color : #ff0000; font-weight : bold; }
			</style>
		<body>
			<script type='text/javascript'>
			function confirmOP() {
				return confirm("Update the database?");
			}
			</script>

			<div class="floatingLogo"><img src="images/logo_small.png"></div>

			<h1><?php echo __("Database Updater") ?></h1>

			<div class="content">

			<?php
				@$op = $_REQUEST["subop"];
				$updater = new DbUpdater(Db::get(), DB_TYPE, SCHEMA_VERSION);

				if ($op == "performupdate") {
					if ($updater->isUpdateRequired()) {

						print "<h2>Performing updates</h2>";

						print "<h3>Updating to schema version " . SCHEMA_VERSION . "</h3>";

						print "<ul>";

						for ($i = $updater->getSchemaVersion() + 1; $i <= SCHEMA_VERSION; $i++) {
							print "<li>Performing update up to version $i...";

							$result = $updater->performUpdateTo($i);

							if (!$result) {
								print "<span class='err'>FAILED!</span></li></ul>";

								print_warning("One of the updates failed. Either retry the process or perform updates manually.");
								print "<p><form method=\"GET\" action=\"index.php\">
								<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
								</form>";

								break;
							} else {
								print "<span class='ok'>OK!</span></li>";
							}
						}

						print "</ul>";

						print_notice("Your Tiny Tiny RSS database is now updated to the latest version.");

						print "<p><form method=\"GET\" action=\"index.php\">
						<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
						</form>";

					} else {
						print "<h2>Your database is up to date.</h2>";

						print "<p><form method=\"GET\" action=\"index.php\">
						<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
						</form>";
					}
				} else {
					if ($updater->isUpdateRequired()) {

						print "<h2>Database update required</h2>";

						print "<h3>";
						printf("Your Tiny Tiny RSS database needs update to the latest version: %d to %d.",
							$updater->getSchemaVersion(), SCHEMA_VERSION);
						print "</h3>";

						print_warning("Please backup your database before proceeding.");

						print "<form method='POST'>
							<input type='hidden' name='subop' value='performupdate'>
							<input type='submit' onclick='return confirmOP()' value='".__("Perform updates")."'>
						</form>";

					} else {

						print_notice("Tiny Tiny RSS database is up to date.");

						print "<p><form method=\"GET\" action=\"index.php\">
							<input type=\"submit\" value=\"".__("Return to Tiny Tiny RSS")."\">
						</form>";

					}
				}
			?>

			</div>
			</body>
			</html>
		<?php
	}

}
?>
