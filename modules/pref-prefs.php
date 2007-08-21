<?php
	function prefs_js_redirect() {
		print "<html><body>
			<script type=\"text/javascript\">
				window.location = 'prefs.php';
			</script>
			</body></html>";
	}

	function module_pref_prefs($link) {
		$subop = $_REQUEST["subop"];

		if ($subop == "change-password") {

			$old_pw = $_POST["OLD_PASSWORD"];
			$new_pw = $_POST["NEW_PASSWORD"];
			$con_pw = $_POST["CONFIRM_PASSWORD"];

			if ($old_pw == "") {
				print "ERROR: ".__("Old password cannot be blank.");
				return;
			}

			if ($new_pw == "") {
				print "ERROR: ".__("New password cannot be blank.");
				return;
			}

			if ($new_pw != $con_pw) {
				print "ERROR: ".__("Entered passwords do not match.");
				return;
			}

			$old_pw_hash = 'SHA1:' . sha1($_POST["OLD_PASSWORD"]);
			$new_pw_hash = 'SHA1:' . sha1($_POST["NEW_PASSWORD"]);

			$active_uid = $_SESSION["uid"];
			
			if ($old_pw && $new_pw) {

				$login = db_escape_string($_SERVER['PHP_AUTH_USER']);

				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					id = '$active_uid' AND (pwd_hash = '$old_pw' OR 
						pwd_hash = '$old_pw_hash')");

				if (db_num_rows($result) == 1) {
					db_query($link, "UPDATE ttrss_users SET pwd_hash = '$new_pw_hash' 
						WHERE id = '$active_uid'");				

					print __("Password has been changed.");
				} else {
					print "ERROR: ".__('Old password is incorrect.');
				}
			}

			return;

		} else if ($subop == "save-config") {

#			$_SESSION["prefs_op_result"] = "save-config";

			$_SESSION["prefs_cache"] = false;

//			print_r($_POST);

			foreach (array_keys($_POST) as $pref_name) {
			
				$pref_name = db_escape_string($pref_name);
				$value = db_escape_string($_POST[$pref_name]);

				$result = db_query($link, "SELECT type_name 
					FROM ttrss_prefs,ttrss_prefs_types 
					WHERE pref_name = '$pref_name' AND type_id = ttrss_prefs_types.id");

				if (db_num_rows($result) > 0) {

					$type_name = db_fetch_result($result, 0, "type_name");

//					print "$pref_name : $type_name : $value<br>";

					if ($type_name == "bool") {
						if ($value == "1") {
							$value = "true";
						} else {
							$value = "false";
						}
					} else if ($type_name == "integer") {
						$value = sprintf("%d", $value);
					}

//					print "$pref_name : $type_name : $value<br>";

					db_query($link, "UPDATE ttrss_user_prefs SET value = '$value' 
						WHERE pref_name = '$pref_name' AND owner_uid = ".$_SESSION["uid"]);

				}

			}

			#return prefs_js_redirect();

			print __("The configuration was saved.");

			return;

		} else if ($subop == "getHelp") {

			$pref_name = db_escape_string($_GET["pn"]);

			$result = db_query($link, "SELECT help_text FROM ttrss_prefs
				WHERE pref_name = '$pref_name'");

			if (db_num_rows($result) > 0) {
				$help_text = db_fetch_result($result, 0, "help_text");
				print $help_text;
			} else {
				print "Unknown option: $pref_name";
			}

		} else if ($subop == "change-email") {

			$email = db_escape_string($_POST["email"]);
			$active_uid = $_SESSION["uid"];

			db_query($link, "UPDATE ttrss_users SET email = '$email' 
				WHERE id = '$active_uid'");				
		
			print __("E-mail has been changed.");
		
			return;

		} else if ($subop == "reset-config") {

			$_SESSION["prefs_op_result"] = "reset-to-defaults";

			db_query($link, "DELETE FROM ttrss_user_prefs 
				WHERE owner_uid = ".$_SESSION["uid"]);
			initialize_user_prefs($link, $_SESSION["uid"]);

			print __("The configuration was reset to defaults.");

			return;

		} else if ($subop == __("Change theme")) {

			$theme = db_escape_string($_POST["theme"]);

			if ($theme == "Default") {
				$theme_qpart = 'NULL';
			} else {
				$theme_qpart = "'$theme'";
			}

			$result = db_query($link, "SELECT id,theme_path FROM ttrss_themes
				WHERE theme_name = '$theme'");

			if (db_num_rows($result) == 1) {
				$theme_id = db_fetch_result($result, 0, "id");
				$theme_path = db_fetch_result($result, 0, "theme_path");
			} else {
				$theme_id = "NULL";
				$theme_path = "";
			}

			db_query($link, "UPDATE ttrss_users SET
				theme_id = $theme_id WHERE id = " . $_SESSION["uid"]);

			$_SESSION["theme"] = $theme_path;

			return prefs_js_redirect();

		} else {

//			print check_for_update($link);

			set_pref($link, "_PREFS_ACTIVE_TAB", "genConfig");

			if (!SINGLE_USER_MODE) {

				$result = db_query($link, "SELECT id FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]." AND pwd_hash 
					= 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8'");

				if (db_num_rows($result) != 0) {
					print format_warning(__("Your password is at default value, 
						please change it."), "default_pass_warning");
				}

/*				if ($_SESSION["pwd_change_result"] == "failed") {
					print format_warning("Could not change the password.");
				}

				if ($_SESSION["pwd_change_result"] == "ok") {
					print format_notice("Password was changed.");
				}

				$_SESSION["pwd_change_result"] = ""; */

				if ($_SESSION["prefs_op_result"] == "reset-to-defaults") {
					print format_notice(__("The configuration was reset to defaults."));
				}

#				if ($_SESSION["prefs_op_result"] == "save-config") {
#					print format_notice(__("The configuration was saved."));
#				}

				$_SESSION["prefs_op_result"] = "";

				print "<form onsubmit='return false' id='change_email_form'>";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>".__("Personal data")."</h3></tr></td>";

				$result = db_query($link, "SELECT email FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]);
					
				$email = db_fetch_result($result, 0, "email");
	
				print "<tr><td width=\"40%\">".__('E-mail')."</td>";
				print "<td><input class=\"editbox\" name=\"email\" 
					onkeypress=\"return filterCR(event, changeUserEmail)\"
					value=\"$email\"></td></tr>";
	
				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<input type=\"hidden\" name=\"subop\" value=\"change-email\">";

				print "</form>";

				print "<p><input class=\"button\" type=\"submit\"
					onclick=\"return changeUserEmail()\" value=\"".__("Change e-mail")."\">";

				print "<form onsubmit=\"return false\" 
					name=\"change_pass_form\" id=\"change_pass_form\">";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>".__("Authentication")."</h3></tr></td>";
	
				print "<tr><td width=\"40%\">".__("Old password")."</td>";
				print "<td><input class=\"editbox\" type=\"password\"
					onkeypress=\"return filterCR(event, changeUserPassword)\"
					name=\"OLD_PASSWORD\"></td></tr>";
	
				print "<tr><td width=\"40%\">".__("New password")."</td>";
				
				print "<td><input class=\"editbox\" type=\"password\"
					onkeypress=\"return filterCR(event, changeUserPassword)\"
					name=\"NEW_PASSWORD\"></td></tr>";

				print "<tr><td width=\"40%\">".__("Confirm password")."</td>";

				print "<td><input class=\"editbox\" type=\"password\"
					onkeypress=\"return filterCR(event, changeUserPassword)\"
					name=\"CONFIRM_PASSWORD\"></td></tr>";

				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<input type=\"hidden\" name=\"subop\" value=\"change-password\">";

				print "</form>";

				print "<p><input class=\"button\" type=\"submit\" 
					onclick=\"return changeUserPassword()\"
					value=\"".__("Change password")."\">";

			}

			$result = db_query($link, "SELECT
				theme_id FROM ttrss_users WHERE id = " . $_SESSION["uid"]);

			$user_theme_id = db_fetch_result($result, 0, "theme_id");

			$result = db_query($link, "SELECT
				id,theme_name FROM ttrss_themes ORDER BY theme_name");

			if (db_num_rows($result) > 0) {

				print "<form action=\"backend.php\" method=\"POST\">";
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>".__("Themes")."</h3></tr></td>";
				print "<tr><td width=\"40%\">".__("Select theme")."</td>";
				print "<td><select name=\"theme\">";
				print "<option value='Default'>".__('Default')."</option>";
				print "<option disabled>--------</option>";				
				
				while ($line = db_fetch_assoc($result)) {	
					if ($line["id"] == $user_theme_id) {
						$selected = "selected";
					} else {
						$selected = "";
					}
					print "<option $selected>" . $line["theme_name"] . "</option>";
				}
				print "</select></td></tr>";
				print "</table>";
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<input type=\"hidden\" name=\"subop\" value=\"Change theme\">";
				print "<p><input class=\"button\" type=\"submit\" 
					value=\"".__('Change theme')."\">";
				print "</form>";
			}

			initialize_user_prefs($link, $_SESSION["uid"]);

			$result = db_query($link, "SELECT 
				ttrss_user_prefs.pref_name,short_desc,help_text,value,type_name,
				section_name,def_value
				FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
				WHERE type_id = ttrss_prefs_types.id AND 
					section_id = ttrss_prefs_sections.id AND
					ttrss_user_prefs.pref_name = ttrss_prefs.pref_name AND
					short_desc != '' AND
					owner_uid = ".$_SESSION["uid"]."
				ORDER BY section_id,short_desc");

			print "<form onsubmit='return false' action=\"backend.php\" 
				method=\"POST\" id=\"pref_prefs_form\">";

			$lnum = 0;

			$active_section = "";
	
			while ($line = db_fetch_assoc($result)) {

				if ($active_section != $line["section_name"]) {

					if ($active_section != "") {
						print "</table>";
					}

					print "<p><table width=\"100%\" class=\"prefPrefsList\">";
				
					$active_section = $line["section_name"];				
					
					print "<tr><td colspan=\"3\"><h3>".__($active_section)."</h3></td></tr>";
//					print "<tr class=\"title\">
//						<td width=\"25%\">Option</td><td>Value</td></tr>";

					$lnum = 0;
				}

//				$class = ($lnum % 2) ? "even" : "odd";

				print "<tr>";

				$type_name = $line["type_name"];
				$pref_name = $line["pref_name"];
				$value = $line["value"];
				$def_value = $line["def_value"];
				$help_text = $line["help_text"];

				print "<td width=\"40%\" id=\"$pref_name\">" . __($line["short_desc"]);

				if ($help_text) print "<div class=\"prefHelp\">".__($help_text)."</div>";
				
				print "</td>";

				print "<td>";

				if ($type_name == "bool") {
//					print_select($pref_name, $value, array("true", "false"));

					if ($value == "true") {
						$value = __("Yes");
					} else {
						$value = __("No");
					}

					print_radio($pref_name, $value, __("Yes"), array(__("Yes"), __("No")));
			
				} else {
					print "<input class=\"editbox\" name=\"$pref_name\" value=\"$value\">";
				}

				print "</td>";

				print "</tr>";

				$lnum++;
			}

			print "</table>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";

			print "<p><input class=\"button\" type=\"submit\" 
				onclick=\"return validatePrefsSave()\"
				value=\"".__('Save configuration')."\">";
				
			print "&nbsp;<input class=\"button\" type=\"submit\" 
				onclick=\"return validatePrefsReset()\" 
				value=\"".__('Reset to defaults')."\"></p>";

			print "</form>";

		}
	}
?>
