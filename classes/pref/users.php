<?php
class Pref_Users extends Handler_Protected {
		function before($method) {
			if (parent::before($method)) {
				if ($_SESSION["access_level"] < 10) {
					print __("Your access level is insufficient to open this tab.");
					return false;
				}
				return true;
			}
			return false;
		}

		function csrf_ignore($method) {
			$csrf_ignored = array("index");

			return array_search($method, $csrf_ignored) !== false;
		}

		function userdetails() {

			header("Content-Type: text/xml");
			print "<dlg>";

			$uid = sprintf("%d", $_REQUEST["id"]);

			print "<title>".__('User details')."</title>";

			print "<content><![CDATA[";

			$result = db_query($this->link, "SELECT login,
				".SUBSTRING_FOR_DATE."(last_login,1,16) AS last_login,
				access_level,
				(SELECT COUNT(int_id) FROM ttrss_user_entries
					WHERE owner_uid = id) AS stored_articles,
				".SUBSTRING_FOR_DATE."(created,1,16) AS created
				FROM ttrss_users
				WHERE id = '$uid'");

			if (db_num_rows($result) == 0) {
				print "<h1>".__('User not found')."</h1>";
				return;
			}

			// print "<h1>User Details</h1>";

			$login = db_fetch_result($result, 0, "login");

			print "<table width='100%'>";

			$last_login = make_local_datetime($this->link,
				db_fetch_result($result, 0, "last_login"), true);

			$created = make_local_datetime($this->link,
				db_fetch_result($result, 0, "created"), true);

			$access_level = db_fetch_result($result, 0, "access_level");
			$stored_articles = db_fetch_result($result, 0, "stored_articles");

			print "<tr><td>".__('Registered')."</td><td>$created</td></tr>";
			print "<tr><td>".__('Last logged in')."</td><td>$last_login</td></tr>";

			$result = db_query($this->link, "SELECT COUNT(id) as num_feeds FROM ttrss_feeds
				WHERE owner_uid = '$uid'");

			$num_feeds = db_fetch_result($result, 0, "num_feeds");

			print "<tr><td>".__('Subscribed feeds count')."</td><td>$num_feeds</td></tr>";

			print "</table>";

			print "<h1>".__('Subscribed feeds')."</h1>";

			$result = db_query($this->link, "SELECT id,title,site_url FROM ttrss_feeds
				WHERE owner_uid = '$uid' ORDER BY title");

			print "<ul class=\"userFeedList\">";

			$row_class = "odd";

			while ($line = db_fetch_assoc($result)) {

				$icon_file = ICONS_URL."/".$line["id"].".ico";

				if (file_exists($icon_file) && filesize($icon_file) > 0) {
					$feed_icon = "<img class=\"tinyFeedIcon\" src=\"$icon_file\">";
				} else {
					$feed_icon = "<img class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">";
				}

				print "<li class=\"$row_class\">$feed_icon&nbsp;<a href=\"".$line["site_url"]."\">".$line["title"]."</a></li>";

				$row_class = $row_class == "even" ? "odd" : "even";

			}

			if (db_num_rows($result) < $num_feeds) {
				// FIXME - add link to show ALL subscribed feeds here somewhere
				print "<li><img
					class=\"tinyFeedIcon\" src=\"images/blank_icon.gif\">&nbsp;...</li>";
			}

			print "</ul>";

			print "<div align='center'>
				<button onclick=\"closeInfoBox()\">".__("Close this window").
				"</button></div>";

			print "]]></content></dlg>";

			return;
		}

		function edit() {
			global $access_level_names;

			header("Content-Type: text/xml");

			$id = db_escape_string($this->link, $_REQUEST["id"]);

			print "<dlg id=\"$method\">";
			print "<title>".__('User Editor')."</title>";
			print "<content><![CDATA[";

			print "<form id=\"user_edit_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-users\">";
			print "<input type=\"hidden\" name=\"method\" value=\"editSave\">";

			$result = db_query($this->link, "SELECT * FROM ttrss_users WHERE id = '$id'");

			$login = db_fetch_result($result, 0, "login");
			$access_level = db_fetch_result($result, 0, "access_level");
			$email = db_fetch_result($result, 0, "email");

			$sel_disabled = ($id == $_SESSION["uid"]) ? "disabled" : "";

			print "<div class=\"dlgSec\">".__("User")."</div>";
			print "<div class=\"dlgSecCont\">";

			if ($sel_disabled) {
				print "<input type=\"hidden\" name=\"login\" value=\"$login\">";
				print "<input size=\"30\" style=\"font-size : 16px\"
					onkeypress=\"return filterCR(event, userEditSave)\" $sel_disabled
					value=\"$login\">";
			} else {
				print "<input size=\"30\" style=\"font-size : 16px\"
					onkeypress=\"return filterCR(event, userEditSave)\" $sel_disabled
					name=\"login\" value=\"$login\">";
			}

			print "</div>";

			print "<div class=\"dlgSec\">".__("Authentication")."</div>";
			print "<div class=\"dlgSecCont\">";

			print __('Access level: ') . " ";

			if (!$sel_disabled) {
				print_select_hash("access_level", $access_level, $access_level_names,
					$sel_disabled);
			} else {
				print_select_hash("", $access_level, $access_level_names,
					$sel_disabled);
				print "<input type=\"hidden\" name=\"access_level\" value=\"$access_level\">";
			}

			print "<br/>";

			print __('Change password to') .
				" <input type=\"password\" size=\"20\" onkeypress=\"return filterCR(event, userEditSave)\"
				name=\"password\">";

			print "</div>";

			print "<div class=\"dlgSec\">".__("Options")."</div>";
			print "<div class=\"dlgSecCont\">";

			print __('E-mail: ').
				" <input size=\"30\" name=\"email\" onkeypress=\"return filterCR(event, userEditSave)\"
				value=\"$email\">";

			print "</div>";

			print "</table>";

			print "</form>";

			print "<div class=\"dlgButtons\">
				<button onclick=\"return userEditSave()\">".
					__('Save')."</button>
				<button onclick=\"return userEditCancel()\">".
					__('Cancel')."</button></div>";

			print "]]></content></dlg>";

			return;
		}

		function editSave() {
			$login = db_escape_string($this->link, trim($_REQUEST["login"]));
			$uid = db_escape_string($this->link, $_REQUEST["id"]);
			$access_level = (int) $_REQUEST["access_level"];
			$email = db_escape_string($this->link, trim($_REQUEST["email"]));
			$password = db_escape_string($this->link, trim($_REQUEST["password"]));

			if ($password) {
				$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
				$pwd_hash = encrypt_password($password, $salt, true);
				$pass_query_part = "pwd_hash = '$pwd_hash', salt = '$salt',";
			} else {
				$pass_query_part = "";
			}

			db_query($this->link, "UPDATE ttrss_users SET $pass_query_part login = '$login',
				access_level = '$access_level', email = '$email', otp_enabled = false
				WHERE id = '$uid'");

		}

		function remove() {
			$ids = split(",", db_escape_string($this->link, $_REQUEST["ids"]));

			foreach ($ids as $id) {
				if ($id != $_SESSION["uid"] && $id != 1) {
					db_query($this->link, "DELETE FROM ttrss_tags WHERE owner_uid = '$id'");
					db_query($this->link, "DELETE FROM ttrss_feeds WHERE owner_uid = '$id'");
					db_query($this->link, "DELETE FROM ttrss_users WHERE id = '$id'");
				}
			}
		}

		function add() {

			$login = db_escape_string($this->link, trim($_REQUEST["login"]));
			$tmp_user_pwd = make_password(8);
			$salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$pwd_hash = encrypt_password($tmp_user_pwd, $salt, true);

			$result = db_query($this->link, "SELECT id FROM ttrss_users WHERE
				login = '$login'");

			if (db_num_rows($result) == 0) {

				db_query($this->link, "INSERT INTO ttrss_users
					(login,pwd_hash,access_level,last_login,created, salt)
					VALUES ('$login', '$pwd_hash', 0, null, NOW(), '$salt')");


				$result = db_query($this->link, "SELECT id FROM ttrss_users WHERE
					login = '$login' AND pwd_hash = '$pwd_hash'");

				if (db_num_rows($result) == 1) {

					$new_uid = db_fetch_result($result, 0, "id");

					print format_notice(T_sprintf("Added user <b>%s</b> with password <b>%s</b>",
						$login, $tmp_user_pwd));

					initialize_user($this->link, $new_uid);

				} else {

					print format_warning(T_sprintf("Could not create user <b>%s</b>", $login));

				}
			} else {
				print format_warning(T_sprintf("User <b>%s</b> already exists.", $login));
			}
		}

		static function resetUserPassword($link, $uid, $show_password) {

			$result = db_query($link, "SELECT login,email
				FROM ttrss_users WHERE id = '$uid'");

			$login = db_fetch_result($result, 0, "login");
			$email = db_fetch_result($result, 0, "email");
			$salt = db_fetch_result($result, 0, "salt");

			$new_salt = substr(bin2hex(get_random_bytes(125)), 0, 250);
			$tmp_user_pwd = make_password(8);

			$pwd_hash = encrypt_password($tmp_user_pwd, $new_salt, true);

			db_query($link, "UPDATE ttrss_users SET pwd_hash = '$pwd_hash', salt = '$new_salt'
				WHERE id = '$uid'");

			if ($show_password) {
				print T_sprintf("Changed password of user <b>%s</b>
					to <b>%s</b>", $login, $tmp_user_pwd);
			} else {
				print T_sprintf("Sending new password of user <b>%s</b>
					to <b>%s</b>", $login, $email);
			}

			require_once 'lib/phpmailer/class.phpmailer.php';

			if ($email) {
				require_once "lib/MiniTemplator.class.php";

				$tpl = new MiniTemplator;

				$tpl->readTemplateFromFile("templates/resetpass_template.txt");

				$tpl->setVariable('LOGIN', $login);
				$tpl->setVariable('NEWPASS', $tmp_user_pwd);

				$tpl->addBlock('message');

				$message = "";

				$tpl->generateOutputToString($message);

				$mail = new PHPMailer();

				$mail->PluginDir = "lib/phpmailer/";
				$mail->SetLanguage("en", "lib/phpmailer/language/");

				$mail->CharSet = "UTF-8";

				$mail->From = SMTP_FROM_ADDRESS;
				$mail->FromName = SMTP_FROM_NAME;
				$mail->AddAddress($email, $login);

				if (SMTP_HOST) {
					$mail->Host = SMTP_HOST;
					$mail->Mailer = "smtp";
					$mail->SMTPAuth = SMTP_LOGIN != '';
					$mail->Username = SMTP_LOGIN;
					$mail->Password = SMTP_PASSWORD;
				}

				$mail->IsHTML(false);
				$mail->Subject = __("[tt-rss] Password change notification");
				$mail->Body = $message;

				$rc = $mail->Send();

				if (!$rc) print_error($mail->ErrorInfo);
			}
		}

		function resetPass() {
			$uid = db_escape_string($this->link, $_REQUEST["id"]);
			Pref_Users::resetUserPassword($this->link, $uid, true);
		}

		function index() {

			global $access_level_names;

			print "<div id=\"pref-user-wrap\" dojoType=\"dijit.layout.BorderContainer\" gutters=\"false\">";
			print "<div id=\"pref-user-header\" dojoType=\"dijit.layout.ContentPane\" region=\"top\">";

			print "<div id=\"pref-user-toolbar\" dojoType=\"dijit.Toolbar\">";

			$user_search = db_escape_string($this->link, $_REQUEST["search"]);

			if (array_key_exists("search", $_REQUEST)) {
				$_SESSION["prefs_user_search"] = $user_search;
			} else {
				$user_search = $_SESSION["prefs_user_search"];
			}

			print "<div style='float : right; padding-right : 4px;'>
				<input dojoType=\"dijit.form.TextBox\" id=\"user_search\" size=\"20\" type=\"search\"
					value=\"$user_search\">
				<button dojoType=\"dijit.form.Button\" onclick=\"javascript:updateUsersList()\">".
					__('Search')."</button>
				</div>";

			$sort = db_escape_string($this->link, $_REQUEST["sort"]);

			if (!$sort || $sort == "undefined") {
				$sort = "login";
			}

			print "<div dojoType=\"dijit.form.DropDownButton\">".
					"<span>" . __('Select')."</span>";
			print "<div dojoType=\"dijit.Menu\" style=\"display: none;\">";
			print "<div onclick=\"selectTableRows('prefUserList', 'all')\"
				dojoType=\"dijit.MenuItem\">".__('All')."</div>";
			print "<div onclick=\"selectTableRows('prefUserList', 'none')\"
				dojoType=\"dijit.MenuItem\">".__('None')."</div>";
			print "</div></div>";

			print "<button dojoType=\"dijit.form.Button\" onclick=\"javascript:addUser()\">".__('Create user')."</button>";

			print "
				<button dojoType=\"dijit.form.Button\" onclick=\"javascript:selectedUserDetails()\">".
				__('Details')."</button dojoType=\"dijit.form.Button\">
				<button dojoType=\"dijit.form.Button\" onclick=\"javascript:editSelectedUser()\">".
				__('Edit')."</button dojoType=\"dijit.form.Button\">
				<button dojoType=\"dijit.form.Button\" onclick=\"javascript:removeSelectedUsers()\">".
				__('Remove')."</button dojoType=\"dijit.form.Button\">
				<button dojoType=\"dijit.form.Button\" onclick=\"javascript:resetSelectedUserPass()\">".
				__('Reset password')."</button dojoType=\"dijit.form.Button\">";

			print "</div>"; #toolbar
			print "</div>"; #pane
			print "<div id=\"pref-user-content\" dojoType=\"dijit.layout.ContentPane\" region=\"center\">";

			print "<div id=\"sticky-status-msg\"></div>";

			if ($user_search) {

				$user_search = split(" ", $user_search);
				$tokens = array();

				foreach ($user_search as $token) {
					$token = trim($token);
					array_push($tokens, "(UPPER(login) LIKE UPPER('%$token%'))");
				}

				$user_search_query = "(" . join($tokens, " AND ") . ") AND ";

			} else {
				$user_search_query = "";
			}

			$result = db_query($this->link, "SELECT
					id,login,access_level,email,
					".SUBSTRING_FOR_DATE."(last_login,1,16) as last_login,
					".SUBSTRING_FOR_DATE."(created,1,16) as created
				FROM
					ttrss_users
				WHERE
					$user_search_query
					id > 0
				ORDER BY $sort");

			if (db_num_rows($result) > 0) {

			print "<p><table width=\"100%\" cellspacing=\"0\"
				class=\"prefUserList\" id=\"prefUserList\">";

			print "<tr class=\"title\">
						<td align='center' width=\"5%\">&nbsp;</td>
						<td width='30%'><a href=\"#\" onclick=\"updateUsersList('login')\">".__('Login')."</a></td>
						<td width='30%'><a href=\"#\" onclick=\"updateUsersList('access_level')\">".__('Access Level')."</a></td>
						<td width='20%'><a href=\"#\" onclick=\"updateUsersList('created')\">".__('Registered')."</a></td>
						<td width='20%'><a href=\"#\" onclick=\"updateUsersList('last_login')\">".__('Last login')."</a></td></tr>";

			$lnum = 0;

			while ($line = db_fetch_assoc($result)) {

				$class = ($lnum % 2) ? "even" : "odd";

				$uid = $line["id"];

				print "<tr id=\"UMRR-$uid\">";

				$line["login"] = htmlspecialchars($line["login"]);

				$line["created"] = make_local_datetime($this->link, $line["created"], false);
				$line["last_login"] = make_local_datetime($this->link, $line["last_login"], false);

				print "<td align='center'><input onclick='toggleSelectRow2(this);'
					dojoType=\"dijit.form.CheckBox\" type=\"checkbox\"
					id=\"UMCHK-$uid\"></td>";

				$onclick = "onclick='editUser($uid, event)' title='".__('Click to edit')."'";

				print "<td $onclick>" . $line["login"] . "</td>";

				if (!$line["email"]) $line["email"] = "&nbsp;";

				print "<td $onclick>" .	$access_level_names[$line["access_level"]] . "</td>";
				print "<td $onclick>" . $line["created"] . "</td>";
				print "<td $onclick>" . $line["last_login"] . "</td>";

				print "</tr>";

				++$lnum;
			}

			print "</table>";

			} else {
				print "<p>";
				if (!$user_search) {
					print_warning(__('No users defined.'));
				} else {
					print_warning(__('No matching users found.'));
				}
				print "</p>";

			}

			print "</div>"; #pane

			global $pluginhost;
			$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB,
				"hook_prefs_tab", "prefUsers");

			print "</div>"; #container

		}

	}
?>
