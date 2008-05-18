<?php
	function module_pref_users($link) {

		global $access_level_names;

		if (!SINGLE_USER_MODE && $_SESSION["access_level"] < 10) { 
			print __("Your access level is insufficient to open this tab.");
			return;
		}

		$subop = $_GET["subop"];

		if ($subop == "edit") {

			$id = db_escape_string($_GET["id"]);

			print "<div id=\"infoBoxTitle\">".__('User editor')."</div>";
			
			print "<div class=\"infoBoxContents\">";

			print "<form id=\"user_edit_form\" onsubmit='return false'>";

			print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
			print "<input type=\"hidden\" name=\"op\" value=\"pref-users\">";
			print "<input type=\"hidden\" name=\"subop\" value=\"editSave\">";

			$result = db_query($link, "SELECT * FROM ttrss_users WHERE id = '$id'");

			$login = db_fetch_result($result, 0, "login");
			$access_level = db_fetch_result($result, 0, "access_level");
			$email = db_fetch_result($result, 0, "email");

			print "<table width='100%'>";
			print "<tr><td>".__('Login:')."</td><td>
				<input class=\"iedit\" onkeypress=\"return filterCR(event)\"
				name=\"login\" value=\"$login\"></td></tr>";

			print "<tr><td>".__('Change password:')."</td><td>
				<input class=\"iedit\" onkeypress=\"return filterCR(event)\"
				name=\"password\"></td></tr>";

			print "<tr><td>".__('E-mail:')."</td><td>
				<input class=\"iedit\" name=\"email\" onkeypress=\"return filterCR(event)\"
				value=\"$email\"></td></tr>";

			$sel_disabled = ($id == $_SESSION["uid"]) ? "disabled" : "";
				
			print "<tr><td>".__('Access level:')."</td><td>";
			print_select_hash("access_level", $access_level, $access_level_names, 
				$sel_disabled);
			print "</td></tr>";

			print "</table>";

			print "</form>";
			
			print "<div align='right'>
				<input class=\"button\"
					type=\"submit\" onclick=\"return userEditSave()\" 
					value=\"".__('Save')."\">
				<input class=\"button\"
					type=\"submit\" onclick=\"return userEditCancel()\" 
					value=\"".__('Cancel')."\"></div>";

			print "</div>";

			return;
		}

		if ($subop == "editSave") {
	
			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$login = db_escape_string(trim($_GET["login"]));
				$uid = db_escape_string($_GET["id"]);
				$access_level = (int) $_GET["access_level"];
				$email = db_escape_string(trim($_GET["email"]));
				$password = db_escape_string(trim($_GET["password"]));

				if ($password) {
					$pwd_hash = encrypt_password($password, $login);
					$pass_query_part = "pwd_hash = '$pwd_hash', ";					
					print_notice(T_sprintf('Changed password of user <b>%s</b>.', $login));
				} else {
					$pass_query_part = "";
				}

				db_query($link, "UPDATE ttrss_users SET $pass_query_part login = '$login', 
					access_level = '$access_level', email = '$email' WHERE id = '$uid'");

			}
		} else if ($subop == "remove") {

			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$ids = split(",", db_escape_string($_GET["ids"]));

				foreach ($ids as $id) {
					db_query($link, "BEGIN");
					db_query($link, "DELETE FROM ttrss_feeds WHERE owner_uid = '$id' AND owner_uid != " . $_SESSION["uid"]);
					db_query($link, "DELETE FROM ttrss_users WHERE id = '$id' AND id != " . $_SESSION["uid"]);
					db_query($link, "COMMIT");
					
				}
			}
		} else if ($subop == "add") {
		
			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$login = db_escape_string(trim($_GET["login"]));
				$tmp_user_pwd = make_password(8);
				$pwd_hash = encrypt_password($tmp_user_pwd, $login);

				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					login = '$login'");

				if (db_num_rows($result) == 0) {

					db_query($link, "INSERT INTO ttrss_users 
						(login,pwd_hash,access_level,last_login,created)
						VALUES ('$login', '$pwd_hash', 0, null, NOW())");
	
	
					$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
						login = '$login' AND pwd_hash = '$pwd_hash'");
	
					if (db_num_rows($result) == 1) {
	
						$new_uid = db_fetch_result($result, 0, "id");
	
						print_notice(T_sprintf("Added user <b>%s</b> with password <b>%s</b>", 
							$login, $tmp_user_pwd));
	
						initialize_user($link, $new_uid);
	
					} else {
					
						print_warning(T_sprintf("Could not create user <b>%s</b>", $login));
	
					}
				} else {
					print_warning(T_sprintf("User <b>%s</b> already exists.", $login));
				}
			} 
		} else if ($subop == "resetPass") {

			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$uid = db_escape_string($_GET["id"]);

				$result = db_query($link, "SELECT login,email 
					FROM ttrss_users WHERE id = '$uid'");

				$login = db_fetch_result($result, 0, "login");
				$email = db_fetch_result($result, 0, "email");
				$tmp_user_pwd = make_password(8);
				$pwd_hash = encrypt_password($tmp_user_pwd, $login);

				db_query($link, "UPDATE ttrss_users SET pwd_hash = '$pwd_hash'
					WHERE id = '$uid'");

				print_notice(T_sprintf("Changed password of user <b>%s</b>
					 to <b>%s</b>", $login, $tmp_user_pwd));

				if (MAIL_RESET_PASS && $email) {
					print_notice(T_sprintf("Notifying <b>%s</b>.", $email));

					require_once "MiniTemplator.class.php";

					$tpl = new MiniTemplator;

					$tpl->readTemplateFromFile("templates/resetpass_template.txt");

					$tpl->setVariable('LOGIN', $login);
					$tpl->setVariable('NEWPASS', $tmp_user_pwd);

					$tpl->addBlock('message');

					$message = "";

					$tpl->generateOutputToString($message);

					$mail = new PHPMailer();

					$mail->PluginDir = "phpmailer/";
					$mail->SetLanguage("en", "phpmailer/language/");

					$mail->CharSet = "UTF-8";

					$mail->From = DIGEST_FROM_ADDRESS;
					$mail->FromName = DIGEST_FROM_NAME;
					$mail->AddAddress($email, $login);

					if (DIGEST_SMTP_HOST) {
						$mail->Host = DIGEST_SMTP_HOST;
						$mail->Mailer = "smtp";
						$mail->Username = DIGEST_SMTP_LOGIN;
						$mail->Password = DIGEST_SMTP_PASSWORD;
					}

					$mail->IsHTML(false);
					$mail->Subject = __("Password change notification");
					$mail->Body = $message;

					$rc = $mail->Send();

					if (!$rc) print_error($mail->ErrorInfo);

/*					mail("$login <$email>", "Password reset notification",
						"Hi, $login.\n".
						"\n".
						"Your password for this TT-RSS installation was reset by".
							" an administrator.\n".
						"\n".
						"Your new password is $tmp_user_pwd, please remember".
							" it for later reference.\n".
						"\n".
						"Sincerely, TT-RSS Mail Daemon.", "From: " . MAIL_FROM); */
				}
					
				print "</div>";				

			}
		}

		set_pref($link, "_PREFS_ACTIVE_TAB", "userConfig");

		$user_search = db_escape_string($_GET["search"]);

		if (array_key_exists("search", $_GET)) {
			$_SESSION["prefs_user_search"] = $user_search;
		} else {
			$user_search = $_SESSION["prefs_user_search"];
		}

		print "<div class=\"feedEditSearch\">
			<input id=\"user_search\" size=\"20\" type=\"search\"
				onfocus=\"javascript:disableHotkeys();\" 
				onblur=\"javascript:enableHotkeys();\"
				onchange=\"javascript:updateUsersList()\" value=\"$user_search\">
			<input type=\"submit\" class=\"button\" 
				onclick=\"javascript:updateUsersList()\" value=\"".__('Search')."\">
			</div>";

		$sort = db_escape_string($_GET["sort"]);

		if (!$sort || $sort == "undefined") {
			$sort = "login";
		}

		print "<div class=\"prefGenericAddBox\">
			<input id=\"uadd_box\" 			
				onkeyup=\"toggleSubmitNotEmpty(this, 'user_add_btn')\"
				onchange=\"toggleSubmitNotEmpty(this, 'user_add_btn')\"
				size=\"40\">&nbsp;";
			
		print "<input type=\"submit\" class=\"button\" 
			id=\"user_add_btn\" disabled=\"true\"
			onclick=\"javascript:addUser()\" value=\"".__('Create user')."\"></div>";

		if ($user_search) {
			$user_search_query = "UPPER(login) LIKE UPPER('%$user_search%') AND";
		} else {
			$user_search_query = "";
		}

		$result = db_query($link, "SELECT 
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

//		print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

		print "<p><table width=\"100%\" cellspacing=\"0\" 
			class=\"prefUserList\" id=\"prefUserList\">";

		print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				".__('Select:')." 
					<a href=\"javascript:selectPrefRows('user', true)\">".__('All')."</a>,
					<a href=\"javascript:selectPrefRows('user', false)\">".__('None')."</a>
				</td</tr>";

		print "<tr class=\"title\">
					<td align='center' width=\"5%\">&nbsp;</td>
					<td width=''><a href=\"javascript:updateUsersList('login')\">".__('Login')."</a></td>
					<td width='20%'><a href=\"javascript:updateUsersList('access_level')\">".__('Access Level')."</a></td>
					<td width='20%'><a href=\"javascript:updateUsersList('created')\">".__('Registered')."</a></td>
					<td width='20%'><a href=\"javascript:updateUsersList('last_login')\">".__('Last login')."</a></td></tr>";
		
		$lnum = 0;
		
		while ($line = db_fetch_assoc($result)) {

			$class = ($lnum % 2) ? "even" : "odd";

			$uid = $line["id"];
			$edit_uid = $_GET["id"];

			if ($subop == "edit" && $uid != $edit_uid) {
				$class .= "Grayed";
				$this_row_id = "";
			} else {
				$this_row_id = "id=\"UMRR-$uid\"";
			}		
			
			print "<tr class=\"$class\" $this_row_id>";

			$line["login"] = htmlspecialchars($line["login"]);

#			$line["last_login"] = date(get_pref($link, 'SHORT_DATE_FORMAT'),
#				strtotime($line["last_login"]));

			if (get_pref($link, 'HEADLINES_SMART_DATE')) {
				$line["last_login"] = smart_date_time(strtotime($line["last_login"]));
				$line["created"] = smart_date_time(strtotime($line["created"]));
			} else {
				$line["last_login"] = date(get_pref($link, 'SHORT_DATE_FORMAT'),
					strtotime($line["last_login"]));
				$line["created"] = date(get_pref($link, 'SHORT_DATE_FORMAT'),
					strtotime($line["created"]));
			}				

			print "<td align='center'><input onclick='toggleSelectPrefRow(this, \"user\");' 
				type=\"checkbox\" id=\"UMCHK-$uid\"></td>";

			$onclick = "onclick='editUser($uid)' title='".__('Click to edit')."'";

			print "<td $onclick>" . $line["login"] . "</td>";		

			if (!$line["email"]) $line["email"] = "&nbsp;";

			print "<td $onclick>" .	$access_level_names[$line["access_level"]] . "</td>";	
			print "<td $onclick>" . $line["created"] . "</td>";		
			print "<td $onclick>" . $line["last_login"] . "</td>";		
		
			print "</tr>";

			++$lnum;
		}

		print "</table>";

		print "<p id='userOpToolbar'>";

		print "				
			<input type=\"submit\" class=\"button\" disabled=\"true\"
				onclick=\"javascript:selectedUserDetails()\" value=\"".__('User details')."\">
			<input type=\"submit\" class=\"button\" disabled=\"true\"
				onclick=\"javascript:editSelectedUser()\" value=\"".__('Edit')."\">
			<input type=\"submit\" class=\"button\" disabled=\"true\"
				onclick=\"javascript:removeSelectedUsers()\" value=\"".__('Remove')."\">
			<input type=\"submit\" class=\"button\" disabled=\"true\"
				onclick=\"javascript:resetSelectedUserPass()\" value=\"".__('Reset password')."\">";

		} else {
			print "<p>";
			if (!$user_search) {
				print __('No users defined.');
			} else {
				print __('No matching users found.');
			}
			print "</p>";

		}

	}
?>
