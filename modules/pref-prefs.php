<?php
	function prefs_js_redirect() {
		print "<html><body>
			<script type=\"text/javascript\">
				window.location = 'prefs.php';
			</script>
			</body></html>";
	}

	function module_pref_prefs($link) {

		global $access_level_names;

		$subop = $_REQUEST["subop"];

		$prefs_blacklist = array("HIDE_FEEDLIST", "SYNC_COUNTERS", "ENABLE_LABELS",
			"ENABLE_SEARCH_TOOLBAR", "HIDE_READ_FEEDS");

		$profile_blacklist = array("ALLOW_DUPLICATE_POSTS", "PURGE_OLD_DAYS", 
			"PURGE_UNREAD_ARTICLES", "DIGEST_ENABLE", "DIGEST_CATCHUP", 
			"BLACKLISTED_TAGS", "ENABLE_FEED_ICONS", "ENABLE_API_ACCESS",
			"UPDATE_POST_ON_CHECKSUM_CHANGE", "DEFAULT_UPDATE_INTERVAL",
			"MARK_UNREAD_ON_UPDATE");

		if (FORCE_ARTICLE_PURGE != 0) {
			array_push($prefs_blacklist, "PURGE_OLD_DAYS");
			array_push($prefs_blacklist, "PURGE_UNREAD_ARTICLES");
		}

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

			$old_pw_hash1 = encrypt_password($_POST["OLD_PASSWORD"]);
			$old_pw_hash2 = encrypt_password($_POST["OLD_PASSWORD"],
				$_SESSION["name"]);

			$new_pw_hash = encrypt_password($_POST["NEW_PASSWORD"],
				$_SESSION["name"]);

			$active_uid = $_SESSION["uid"];
			
			if ($old_pw && $new_pw) {

				$login = db_escape_string($_SERVER['PHP_AUTH_USER']);

				$result = db_query($link, "SELECT id FROM ttrss_users WHERE 
					id = '$active_uid' AND (pwd_hash = '$old_pw_hash1' OR 
						pwd_hash = '$old_pw_hash2')");

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

			$orig_theme = get_pref($link, "_THEME_ID");

			foreach (array_keys($_POST) as $pref_name) {
			
				$pref_name = db_escape_string($pref_name);
				$value = db_escape_string($_POST[$pref_name]);

				set_pref($link, $pref_name, $value);

			}

			if ($orig_theme != get_pref($link, "_THEME_ID")) {
				print "PREFS_THEME_CHANGED";
			} else {
				print __("The configuration was saved.");
			}

			return;

		} else if ($subop == "getHelp") {

			$pref_name = db_escape_string($_REQUEST["pn"]);

			$result = db_query($link, "SELECT help_text FROM ttrss_prefs
				WHERE pref_name = '$pref_name'");

			if (db_num_rows($result) > 0) {
				$help_text = db_fetch_result($result, 0, "help_text");
				print $help_text;
			} else {
				printf(__("Unknown option: %s"), $pref_name);
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

			if ($_SESSION["profile"]) {
				$profile_qpart = "profile = '" . $_SESSION["profile"] . "'";
			} else {
				$profile_qpart = "profile IS NULL";
			}

			db_query($link, "DELETE FROM ttrss_user_prefs 
				WHERE $profile_qpart AND owner_uid = ".$_SESSION["uid"]);

			initialize_user_prefs($link, $_SESSION["uid"], $_SESSION["profile"]);

			print "PREFS_THEME_CHANGED";

//			print __("The configuration was reset to defaults.");

			return;

		} else {

			set_pref($link, "_PREFS_ACTIVE_TAB", "genConfig");

			if ($_SESSION["profile"]) {
				print_notice("Some preferences are only available in default profile.");
			}

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

/*				if ($_SESSION["prefs_op_result"] == "reset-to-defaults") {
					print format_notice(__("The configuration was reset to defaults."));
} */

#				if ($_SESSION["prefs_op_result"] == "save-config") {
#					print format_notice(__("The configuration was saved."));
#				}

				$_SESSION["prefs_op_result"] = "";

				print "<form onsubmit='return false' id='change_email_form'>";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>".__("Personal data")."</h3></tr></td>";

				$result = db_query($link, "SELECT email,access_level FROM ttrss_users
					WHERE id = ".$_SESSION["uid"]);
					
				$email = db_fetch_result($result, 0, "email");
	
				print "<tr><td width=\"40%\">".__('E-mail')."</td>";
				print "<td class=\"prefValue\"><input class=\"editbox\" name=\"email\" 
					onfocus=\"javascript:disableHotkeys();\" 
					onblur=\"javascript:enableHotkeys();\"
					onkeypress=\"return filterCR(event, changeUserEmail)\"
					value=\"$email\"></td></tr>";

				if (!SINGLE_USER_MODE) {

					$access_level = db_fetch_result($result, 0, "access_level");

					print "<tr><td width=\"40%\">".__('Access level')."</td>";
					print "<td>" . $access_level_names[$access_level] . "</td></tr>";

				}
	
				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<input type=\"hidden\" name=\"subop\" value=\"change-email\">";

				print "</form>";

				print "<p><button onclick=\"return changeUserEmail()\">".
					__("Change e-mail")."</button>";

				print "<form onsubmit=\"return false\" 
					name=\"change_pass_form\" id=\"change_pass_form\">";
	
				print "<table width=\"100%\" class=\"prefPrefsList\">";
	 			print "<tr><td colspan='3'><h3>".__("Authentication")."</h3></tr></td>";
	
				print "<tr><td width=\"40%\">".__("Old password")."</td>";
				print "<td class=\"prefValue\"><input class=\"editbox\" type=\"password\"
					onfocus=\"javascript:disableHotkeys();\" 
					onblur=\"javascript:enableHotkeys();\"
					onkeypress=\"return filterCR(event, changeUserPassword)\"
					name=\"OLD_PASSWORD\"></td></tr>";
	
				print "<tr><td width=\"40%\">".__("New password")."</td>";
				
				print "<td class=\"prefValue\"><input class=\"editbox\" type=\"password\"
					onfocus=\"javascript:disableHotkeys();\" 
					onblur=\"javascript:enableHotkeys();\"
					onkeypress=\"return filterCR(event, changeUserPassword)\"
					name=\"NEW_PASSWORD\"></td></tr>";

				print "<tr><td width=\"40%\">".__("Confirm password")."</td>";

				print "<td class=\"prefValue\"><input class=\"editbox\" type=\"password\"
					onfocus=\"javascript:disableHotkeys();\" 
					onblur=\"javascript:enableHotkeys();\"
					onkeypress=\"return filterCR(event, changeUserPassword)\"
					name=\"CONFIRM_PASSWORD\"></td></tr>";

				print "</table>";
	
				print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";
				print "<input type=\"hidden\" name=\"subop\" value=\"change-password\">";

				print "</form>";

				print "<p><button	onclick=\"return changeUserPassword()\">".
					__("Change password")."</button>";

			}

			if ($_SESSION["profile"]) {
				initialize_user_prefs($link, $_SESSION["uid"], $_SESSION["profile"]);
				$profile_qpart = "profile = '" . $_SESSION["profile"] . "'";
			} else {
				initialize_user_prefs($link, $_SESSION["uid"]);
				$profile_qpart = "profile IS NULL";
			}

			$result = db_query($link, "SELECT 
				ttrss_user_prefs.pref_name,short_desc,help_text,value,type_name,
				section_name,def_value,section_id
				FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
				WHERE type_id = ttrss_prefs_types.id AND 
					$profile_qpart AND
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

				if (in_array($line["pref_name"], $prefs_blacklist)) {
					continue;
				}

				if ($_SESSION["profile"] && in_array($line["pref_name"], 
						$profile_blacklist)) {
					continue;
				}

				if ($active_section != $line["section_name"]) {

					if ($active_section != "") {
						print "</table>";
					}

					print "<p><table width=\"100%\" class=\"prefPrefsList\">";

					$active_section = $line["section_name"];				
					
					print "<tr><td colspan=\"3\"><h3>".__($active_section)."</h3></td></tr>";

					if ($line["section_id"] == 2) {
						print "<tr><td width=\"40%\">".__("Select theme")."</td>";

						$user_theme = get_pref($link, "_THEME_ID");
						$themes = get_all_themes();

						print "<td><select name=\"_THEME_ID\">";
						print "<option value=''>".__('Default')."</option>";
						print "<option disabled>--------</option>";				

						foreach ($themes as $t) {
							$base = $t['base'];
							$name = $t['name'];

							if ($base == $user_theme) {
								$selected = "selected=\"1\"";
							} else {
								$selected = "";
							}

							print "<option $selected value='$base'>$name</option>";

						}
			
						print "</select></td></tr>";
					}

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

				print "<td width=\"40%\" class=\"prefName\" id=\"$pref_name\">" . __($line["short_desc"]);

				if ($help_text) print "<div class=\"prefHelp\">".__($help_text)."</div>";
				
				print "</td>";

				print "<td class=\"prefValue\">";

				if ($type_name == "bool") {
//					print_select($pref_name, $value, array("true", "false"));

					if ($value == "true") {
						$value = __("Yes");
					} else {
						$value = __("No");
					}

					print_radio($pref_name, $value, __("Yes"), array(__("Yes"), __("No")));
			
				} else {
					print "<input class=\"editbox\"
						onfocus=\"javascript:disableHotkeys();\" 
						onblur=\"javascript:enableHotkeys();\"  
						name=\"$pref_name\" value=\"$value\">";
				}

				print "</td>";

				print "</tr>";

				$lnum++;
			}

			print "</table>";

			print "<input type=\"hidden\" name=\"op\" value=\"pref-prefs\">";

			print "<p><button onclick=\"return validatePrefsSave()\">".
				__('Save configuration')."</button> ";

			print "<button onclick=\"return editProfiles()\">".
				__('Manage profiles')."</button> ";

			print "<button onclick=\"return validatePrefsReset()\">".
				__('Reset to defaults')."</button></p>";

			print "</form>";

		}
	}
?>
