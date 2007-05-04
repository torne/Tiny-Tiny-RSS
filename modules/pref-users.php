<?php
	function module_pref_users($link) {

		global $access_level_names;

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

			print "<tr><td>Change password:</td><td>
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
					$pwd_hash = 'SHA1:' . sha1($password);
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
					db_query($link, "DELETE FROM ttrss_users WHERE id = '$id' AND id != " . $_SESSION["uid"]);
					
				}
			}
		} else if ($subop == "add") {
		
			if (!WEB_DEMO_MODE && $_SESSION["access_level"] >= 10) {

				$login = db_escape_string(trim($_GET["login"]));
				$tmp_user_pwd = make_password(8);
				$pwd_hash = 'SHA1:' . sha1($tmp_user_pwd);

				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					login = '$login'");

				if (db_num_rows($result) == 0) {

					db_query($link, "INSERT INTO ttrss_users 
						(login,pwd_hash,access_level,last_login)
						VALUES ('$login', '$pwd_hash', 0, NOW())");
	
	
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
				$pwd_hash = 'SHA1:' . sha1($tmp_user_pwd);

				db_query($link, "UPDATE ttrss_users SET pwd_hash = '$pwd_hash'
					WHERE id = '$uid'");

				print_notice(T_sprintf("Changed password of user <b>%s</b>
					 to <b>%s</b>", $login, $tmp_user_pwd));

				if (MAIL_RESET_PASS && $email) {
					print " Notifying <b>$email</b>.";

					mail("$login <$email>", "Password reset notification",
						"Hi, $login.\n".
						"\n".
						"Your password for this TT-RSS installation was reset by".
							" an administrator.\n".
						"\n".
						"Your new password is $tmp_user_pwd, please remember".
							" it for later reference.\n".
						"\n".
						"Sincerely, TT-RSS Mail Daemon.", "From: " . MAIL_FROM);
				}
					
				print "</div>";				

			}
		}

		set_pref($link, "_PREFS_ACTIVE_TAB", "userConfig");

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

		$result = db_query($link, "SELECT 
				id,login,access_level,email,
				SUBSTRING(last_login,1,16) as last_login
			FROM 
				ttrss_users
			ORDER BY $sort");

//		print "<div id=\"infoBoxShadow\"><div id=\"infoBox\">PLACEHOLDER</div></div>";

		print "<p><table width=\"100%\" cellspacing=\"0\" 
			class=\"prefUserList\" id=\"prefUserList\">";

		print "<tr><td class=\"selectPrompt\" colspan=\"8\">
				Select: 
					<a href=\"javascript:selectPrefRows('user', true)\">All</a>,
					<a href=\"javascript:selectPrefRows('user', false)\">None</a>
				</td</tr>";

		print "<tr class=\"title\">
					<td align='center' width=\"5%\">&nbsp;</td>
					<td width='40%'><a href=\"javascript:updateUsersList('login')\">".__('Login')."</a></td>
					<td width='40%'><a href=\"javascript:updateUsersList('access_level')\">".__('Access Level')."</a></td>
					<td width='30%'><a href=\"javascript:updateUsersList('last_login')\">".__('Last login')."</a></td></tr>";
		
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
			} else {
				$line["last_login"] = date(get_pref($link, 'SHORT_DATE_FORMAT'),
					strtotime($line["last_login"]));
			}				

			$access_level_names = array(0 => "User", 10 => "Administrator");

//			if (!$edit_uid || $subop != "edit") {

				print "<td align='center'><input onclick='toggleSelectPrefRow(this, \"user\");' 
				type=\"checkbox\" id=\"UMCHK-$uid\"></td>";

				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$line["login"] . "</td>";		

				if (!$line["email"]) $line["email"] = "&nbsp;";

				print "<td><a href=\"javascript:editUser($uid);\">" . 
					$access_level_names[$line["access_level"]] . "</td>";			

/*			} else if ($uid != $edit_uid) {

				if (!$line["email"]) $line["email"] = "&nbsp;";

				print "<td align='center'><input disabled=\"true\" type=\"checkbox\" 
					id=\"UMCHK-".$line["id"]."\"></td>";

				print "<td>".$line["login"]."</td>";		
				print "<td>".$line["email"]."</td>";		
				print "<td>".$access_level_names[$line["access_level"]]."</td>";

			} else {

				print "<td align='center'>
					<input disabled=\"true\" type=\"checkbox\" checked></td>";

				print "<td><input id=\"iedit_ulogin\" value=\"".$line["login"].
					"\"></td>";

				print "<td><input id=\"iedit_email\" value=\"".$line["email"].
					"\"></td>";

				print "<td>";
				print "<select id=\"iedit_ulevel\">";
				foreach (array_keys($access_level_names) as $al) {
					if ($al == $line["access_level"]) {
						$selected = "selected";
					} else {
						$selected = "";
					}					
					print "<option $selected id=\"$al\">" . 
						$access_level_names[$al] . "</option>";
				}
				print "</select>";
				print "</td>";

			} */
				
			print "<td>".$line["last_login"]."</td>";		
		
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

	}
?>
