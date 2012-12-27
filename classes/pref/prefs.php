<?php
class Pref_Prefs extends Handler_Protected {

	function csrf_ignore($method) {
		$csrf_ignored = array("index", "updateself");

		return array_search($method, $csrf_ignored) !== false;
	}

	function changepassword() {

		$old_pw = $_POST["old_password"];
		$new_pw = $_POST["new_password"];
		$con_pw = $_POST["confirm_password"];

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

		global $pluginhost;
		$authenticator = $pluginhost->get_plugin($_SESSION["auth_module"]);

		if (method_exists($authenticator, "change_password")) {
			print $authenticator->change_password($_SESSION["uid"], $old_pw, $new_pw);
		} else {
			print "ERROR: ".__("Function not supported by authentication module.");
		}
	}

	function saveconfig() {

		$_SESSION["prefs_cache"] = false;

		$orig_theme = get_pref($this->link, "_THEME_ID");

		foreach (array_keys($_POST) as $pref_name) {

			$pref_name = db_escape_string($pref_name);
			$value = db_escape_string($_POST[$pref_name]);

			if ($pref_name == 'DIGEST_PREFERRED_TIME') {
				if (get_pref($this->link, 'DIGEST_PREFERRED_TIME') != $value) {

					db_query($this->link, "UPDATE ttrss_users SET
						last_digest_sent = NULL WHERE id = " . $_SESSION['uid']);

				}
			}

			set_pref($this->link, $pref_name, $value);

		}

		if ($orig_theme != get_pref($this->link, "_THEME_ID")) {
			print "PREFS_THEME_CHANGED";
		} else {
			print __("The configuration was saved.");
		}
	}

	function getHelp() {

		$pref_name = db_escape_string($_REQUEST["pn"]);

		$result = db_query($this->link, "SELECT help_text FROM ttrss_prefs
			WHERE pref_name = '$pref_name'");

		if (db_num_rows($result) > 0) {
			$help_text = db_fetch_result($result, 0, "help_text");
			print $help_text;
		} else {
			printf(__("Unknown option: %s"), $pref_name);
		}
	}

	function changeemail() {

		$email = db_escape_string($_POST["email"]);
		$full_name = db_escape_string($_POST["full_name"]);

		$active_uid = $_SESSION["uid"];

		db_query($this->link, "UPDATE ttrss_users SET email = '$email',
			full_name = '$full_name' WHERE id = '$active_uid'");

		print __("Your personal data has been saved.");

		return;
	}

	function resetconfig() {

		$_SESSION["prefs_op_result"] = "reset-to-defaults";

		if ($_SESSION["profile"]) {
			$profile_qpart = "profile = '" . $_SESSION["profile"] . "'";
		} else {
			$profile_qpart = "profile IS NULL";
		}

		db_query($this->link, "DELETE FROM ttrss_user_prefs
			WHERE $profile_qpart AND owner_uid = ".$_SESSION["uid"]);

		initialize_user_prefs($this->link, $_SESSION["uid"], $_SESSION["profile"]);

		print "PREFS_THEME_CHANGED";
	}

	function index() {

		global $access_level_names;

		$prefs_blacklist = array("HIDE_READ_FEEDS", "FEEDS_SORT_BY_UNREAD",
					"STRIP_UNSAFE_TAGS");

		$profile_blacklist = array("ALLOW_DUPLICATE_POSTS", "PURGE_OLD_DAYS",
					"PURGE_UNREAD_ARTICLES", "DIGEST_ENABLE", "DIGEST_CATCHUP",
					"BLACKLISTED_TAGS", "ENABLE_API_ACCESS", "UPDATE_POST_ON_CHECKSUM_CHANGE",
					"DEFAULT_UPDATE_INTERVAL", "USER_TIMEZONE", "SORT_HEADLINES_BY_FEED_DATE",
					"SSL_CERT_SERIAL", "DIGEST_PREFERRED_TIME");


		$_SESSION["prefs_op_result"] = "";

		print "<div dojoType=\"dijit.layout.AccordionContainer\" region=\"center\">";
		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Personal data / Authentication')."\">";

		print "<form dojoType=\"dijit.form.Form\" id=\"changeUserdataForm\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
		evt.preventDefault();
		if (this.validate()) {
			notify_progress('Saving data...', true);

			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					notify_callback2(transport);
			} });

		}
		</script>";

		print "<table width=\"100%\" class=\"prefPrefsList\">";

		print "<h2>" . __("Personal data") . "</h2>";

		$result = db_query($this->link, "SELECT email,full_name,otp_enabled,
			access_level FROM ttrss_users
			WHERE id = ".$_SESSION["uid"]);

		$email = htmlspecialchars(db_fetch_result($result, 0, "email"));
		$full_name = htmlspecialchars(db_fetch_result($result, 0, "full_name"));
		$otp_enabled = sql_bool_to_bool(db_fetch_result($result, 0, "otp_enabled"));

		print "<tr><td width=\"40%\">".__('Full name')."</td>";
		print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" name=\"full_name\" required=\"1\"
			value=\"$full_name\"></td></tr>";

		print "<tr><td width=\"40%\">".__('E-mail')."</td>";
		print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" name=\"email\" required=\"1\" value=\"$email\"></td></tr>";

		if (!SINGLE_USER_MODE && !$_SESSION["hide_hello"]) {

			$access_level = db_fetch_result($result, 0, "access_level");
			print "<tr><td width=\"40%\">".__('Access level')."</td>";
			print "<td>" . $access_level_names[$access_level] . "</td></tr>";
		}

		print "</table>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-prefs\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"changeemail\">";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__("Save data")."</button>";

		print "</form>";

		if ($_SESSION["auth_module"]) {
			global $pluginhost;

			$authenticator = $pluginhost->get_plugin($_SESSION["auth_module"]);

		} else {
			$authenticator = false;
		}

		if ($authenticator && method_exists($authenticator, "change_password")) {

			print "<h2>" . __("Password") . "</h2>";

			$result = db_query($this->link, "SELECT id FROM ttrss_users
				WHERE id = ".$_SESSION["uid"]." AND pwd_hash
				= 'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8'");

			if (db_num_rows($result) != 0) {
				print format_warning(__("Your password is at default value, please change it."), "default_pass_warning");
			}

			print "<form dojoType=\"dijit.form.Form\">";

			print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
			if (this.validate()) {
				notify_progress('Changing password...', true);

				new Ajax.Request('backend.php', {
					parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						notify('');
						if (transport.responseText.indexOf('ERROR: ') == 0) {
							notify_error(transport.responseText.replace('ERROR: ', ''));
						} else {
							notify_info(transport.responseText);
							var warn = $('default_pass_warning');
							if (warn) Element.hide(warn);
						}
				}});
				this.reset();
			}
			</script>";

			if ($otp_enabled) {
				print_notice("Changing your current password will disable OTP.");
			}

			print "<table width=\"100%\" class=\"prefPrefsList\">";

			print "<tr><td width=\"40%\">".__("Old password")."</td>";
			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\" name=\"old_password\"></td></tr>";

			print "<tr><td width=\"40%\">".__("New password")."</td>";

			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
				name=\"new_password\"></td></tr>";

			print "<tr><td width=\"40%\">".__("Confirm password")."</td>";

			print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\" name=\"confirm_password\"></td></tr>";

			print "</table>";

			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-prefs\">";
			print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"changepassword\">";

			print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
				__("Change password")."</button>";

			print "</form>";

			if ($_SESSION["auth_module"] == "auth_internal") {

				print "<h2>" . __("One time passwords / Authenticator") . "</h2>";

				if ($otp_enabled) {

					print_notice("One time passwords are currently enabled. Enter your current password below to disable.");

					print "<form dojoType=\"dijit.form.Form\">";

				print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
				evt.preventDefault();
				if (this.validate()) {
					notify_progress('Disabling OTP', true);

					new Ajax.Request('backend.php', {
						parameters: dojo.objectToQuery(this.getValues()),
						onComplete: function(transport) {
							notify('');
							if (transport.responseText.indexOf('ERROR: ') == 0) {
								notify_error(transport.responseText.replace('ERROR: ', ''));
							} else {
								window.location.reload();
							}
					}});
					this.reset();
				}
				</script>";

				print "<table width=\"100%\" class=\"prefPrefsList\">";

				print "<tr><td width=\"40%\">".__("Enter your password")."</td>";

				print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
					name=\"password\"></td></tr>";

				print "</table>";

				print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-prefs\">";
				print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"otpdisable\">";

				print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
					__("Disable OTP")."</button>";

				print "</form>";

				} else {

					print "<p>".__("You will need a compatible Authenticator to use this. Changing your password would automatically disable OTP.") . "</p>";

					print "<p>".__("Scan the following code by the Authenticator application:")."</p>";

					$csrf_token = $_SESSION["csrf_token"];

					print "<img src=\"backend.php?op=pref-prefs&method=otpqrcode&csrf_token=$csrf_token\">";

					print "<form dojoType=\"dijit.form.Form\" id=\"changeOtpForm\">";

					print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-prefs\">";
					print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"otpenable\">";

					print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
					evt.preventDefault();
					if (this.validate()) {
						notify_progress('Saving data...', true);

						new Ajax.Request('backend.php', {
							parameters: dojo.objectToQuery(this.getValues()),
							onComplete: function(transport) {
								notify('');
								if (transport.responseText.indexOf('ERROR: ') == 0) {
									notify_error(transport.responseText.replace('ERROR: ', ''));
								} else {
									window.location.reload();
								}
						} });

					}
					</script>";

					print "<table width=\"100%\" class=\"prefPrefsList\">";

					print "<tr><td width=\"40%\">".__("Enter your password")."</td>";

					print "<td class=\"prefValue\"><input dojoType=\"dijit.form.ValidationTextBox\" type=\"password\" required=\"1\"
						name=\"password\"></td></tr>";

					print "<tr><td colspan=\"2\">";

					print "<input dojoType=\"dijit.form.CheckBox\" required=\"1\"
						type=\"checkbox\" id=\"enable_otp\" name=\"enable_otp\"/> ";
					print "<label for=\"enable_otp\">".__("I have scanned the code and would like to enable OTP")."</label>";

					print "</td></tr><tr><td colspan=\"2\">";

					print "</td></tr>";
					print "</table>";

					print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
						__("Enable OTP")."</button>";

					print "</form>";

				}

			}
		}

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefPrefsAuth");

		print "</div>"; #pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" selected=\"true\" title=\"".__('Preferences')."\">";

		print "<form dojoType=\"dijit.form.Form\" id=\"changeSettingsForm\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
		evt.preventDefault();
		if (this.validate()) {
			console.log(dojo.objectToQuery(this.getValues()));

			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					var msg = transport.responseText;
					if (msg.match('PREFS_THEME_CHANGED')) {
						window.location.reload();
					} else {
						notify_info(msg);
					}
			} });
		}
		</script>";

		print '<div dojoType="dijit.layout.BorderContainer" gutters="false">';

		print '<div dojoType="dijit.layout.ContentPane" region="center" style="overflow-y : auto">';

		if ($_SESSION["profile"]) {
			print_notice("Some preferences are only available in default profile.");
		}

		if ($_SESSION["profile"]) {
			initialize_user_prefs($this->link, $_SESSION["uid"], $_SESSION["profile"]);
			$profile_qpart = "profile = '" . $_SESSION["profile"] . "'";
		} else {
			initialize_user_prefs($this->link, $_SESSION["uid"]);
			$profile_qpart = "profile IS NULL";
		}

		if ($_SESSION["prefs_show_advanced"])
			$access_query = "true";
		else
			$access_query = "(access_level = 0 AND section_id != 3)";

		$result = db_query($this->link, "SELECT DISTINCT
			ttrss_user_prefs.pref_name,short_desc,help_text,value,type_name,
			ttrss_prefs_sections.order_id,
			section_name,def_value,section_id
			FROM ttrss_prefs,ttrss_prefs_types,ttrss_prefs_sections,ttrss_user_prefs
			WHERE type_id = ttrss_prefs_types.id AND
				$profile_qpart AND
				section_id = ttrss_prefs_sections.id AND
				ttrss_user_prefs.pref_name = ttrss_prefs.pref_name AND
				$access_query AND
				short_desc != '' AND
				owner_uid = ".$_SESSION["uid"]."
			ORDER BY ttrss_prefs_sections.order_id,short_desc");

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

				print "<table width=\"100%\" class=\"prefPrefsList\">";

				$active_section = $line["section_name"];

				print "<tr><td colspan=\"3\"><h3>".__($active_section)."</h3></td></tr>";

				if ($line["section_id"] == 2) {
					print "<tr><td width=\"40%\">".__("Select theme")."</td>";

					$user_theme = get_pref($this->link, "_THEME_ID");
					$themes = get_all_themes();

					print "<td><select name=\"_THEME_ID\" dojoType=\"dijit.form.Select\">";
					print "<option value='Default'>".__('Default')."</option>";
					print "<option value='----------------' disabled=\"1\">--------</option>";

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
				$lnum = 0;
			}

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

			if ($pref_name == "USER_TIMEZONE") {

				$timezones = explode("\n", file_get_contents("lib/timezones.txt"));

				print_select($pref_name, $value, $timezones, 'dojoType="dijit.form.FilteringSelect"');
			} else if ($pref_name == "USER_STYLESHEET") {

				print "<button dojoType=\"dijit.form.Button\"
					onclick=\"customizeCSS()\">" . __('Customize') . "</button>";

			} else if ($pref_name == "DEFAULT_ARTICLE_LIMIT") {

				$limits = array(15, 30, 45, 60);

				print_select($pref_name, $value, $limits,
					'dojoType="dijit.form.Select"');

			} else if ($pref_name == "DEFAULT_UPDATE_INTERVAL") {

				global $update_intervals_nodefault;

				print_select_hash($pref_name, $value, $update_intervals_nodefault,
					'dojoType="dijit.form.Select"');

			} else if ($type_name == "bool") {

				if ($value == "true") {
					$value = __("Yes");
				} else {
					$value = __("No");
				}

				if ($pref_name == "PURGE_UNREAD_ARTICLES" && FORCE_ARTICLE_PURGE != 0) {
					$disabled = "disabled=\"1\"";
					$value = __("Yes");
				} else {
					$disabled = "";
				}

				print_radio($pref_name, $value, __("Yes"), array(__("Yes"), __("No")),
					$disabled);

			} else if (array_search($pref_name, array('FRESH_ARTICLE_MAX_AGE', 'DEFAULT_ARTICLE_LIMIT',
					'PURGE_OLD_DAYS', 'LONG_DATE_FORMAT', 'SHORT_DATE_FORMAT')) !== false) {

				$regexp = ($type_name == 'integer') ? 'regexp="^\d*$"' : '';

				if ($pref_name == "PURGE_OLD_DAYS" && FORCE_ARTICLE_PURGE != 0) {
					$disabled = "disabled=\"1\"";
					$value = FORCE_ARTICLE_PURGE;
				} else {
					$disabled = "";
				}

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					required=\"1\" $regexp $disabled
					name=\"$pref_name\" value=\"$value\">";

			} else if ($pref_name == "SSL_CERT_SERIAL") {

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					id=\"SSL_CERT_SERIAL\" readonly=\"1\"
					name=\"$pref_name\" value=\"$value\">";

				$cert_serial = htmlspecialchars(get_ssl_certificate_id());
				$has_serial = ($cert_serial) ? "false" : "true";

				print " <button dojoType=\"dijit.form.Button\" disabled=\"$has_serial\"
					onclick=\"insertSSLserial('$cert_serial')\">" .
					__('Register') . "</button>";

				print " <button dojoType=\"dijit.form.Button\"
					onclick=\"insertSSLserial('')\">" .
					__('Clear') . "</button>";

			} else if ($pref_name == 'DIGEST_PREFERRED_TIME') {
				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					id=\"$pref_name\" regexp=\"[012]?\d:\d\d\" placeHolder=\"12:00\"
					name=\"$pref_name\" value=\"$value\"><div class=\"insensitive\">".
					T_sprintf("Current server time: %s (UTC)", date("H:i")) . "</div>";
			} else {
				$regexp = ($type_name == 'integer') ? 'regexp="^\d*$"' : '';

				print "<input dojoType=\"dijit.form.ValidationTextBox\"
					$regexp
					name=\"$pref_name\" value=\"$value\">";
			}

			print "</td>";

			print "</tr>";

			$lnum++;
		}

		print "</table>";

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefPrefsPrefsInside");

		print '</div>'; # inside pane
		print '<div dojoType="dijit.layout.ContentPane" region="bottom">';

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-prefs\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"saveconfig\">";

		print "<button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__('Save configuration')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return editProfiles()\">".
			__('Manage profiles')."</button> ";

		print "<button dojoType=\"dijit.form.Button\" onclick=\"return validatePrefsReset()\">".
			__('Reset to defaults')."</button>";

		print "&nbsp;";

		$checked = $_SESSION["prefs_show_advanced"] ? "checked='1'" : "";

		print "<input onclick='toggleAdvancedPrefs()'
				id='prefs_show_advanced'
				dojoType=\"dijit.form.CheckBox\"
				$checked
				type=\"checkbox\"></input>
				<label for='prefs_show_advanced'>" .
				__("Show additional preferences") . "</label>";

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB_SECTION,
			"hook_prefs_tab_section", "prefPrefsPrefsOutside");

		print "</form>";
		print '</div>'; # inner pane
		print '</div>'; # border container

		print "</div>"; #pane

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Plugins')."\">";

		print "<h2>".__("Plugins")."</h2>";

		print_notice("You will need to reload Tiny Tiny RSS for plugin changes to take effect.");

		print "<form dojoType=\"dijit.form.Form\" id=\"changePluginsForm\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
		evt.preventDefault();
		if (this.validate()) {
			notify_progress('Saving data...', true);

			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					notify('');
					if (confirm(__('Selected plugins have been enabled. Reload?'))) {
						window.location.reload();
					}
			} });

		}
		</script>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pref-prefs\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"setplugins\">";

		print "<table width='100%' class='prefPluginsList'>";

		print "<tr><td colspan='4'><h3>".__("System plugins")."</h3></td></tr>";

		print "<tr class=\"title\">
				<td width=\"5%\">&nbsp;</td>
				<td width='10%'>".__('Plugin')."</td>
				<td width=''>".__('Description')."</td>
				<td width='5%'>".__('Version')."</td>
				<td width='10%'>".__('Author')."</td></tr>";

		$system_enabled = array_map("trim", explode(",", PLUGINS));
		$user_enabled = array_map("trim", explode(",", get_pref($this->link, "_ENABLED_PLUGINS")));

		$tmppluginhost = new PluginHost($link);
		$tmppluginhost->load_all($tmppluginhost::KIND_ALL);

		foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
			$about = $plugin->about();

			if ($about[3] && strpos($name, "example") === FALSE) {
				if (in_array($name, $system_enabled)) {
					$checked = "checked='1'";
				} else {
					$checked = "";
				}

				print "<tr>";

				print "<td align='center'><input disabled='1'
						dojoType=\"dijit.form.CheckBox\" $checked
						type=\"checkbox\"></td>";

				print "<td>$name</td>";
				print "<td>" . htmlspecialchars($about[1]) . "</td>";
				print "<td>" . htmlspecialchars(sprintf("%.2f", $about[0])) . "</td>";
				print "<td>" . htmlspecialchars($about[2]) . "</td>";

				print "</tr>";

			}
		}

		print "<tr><td colspan='4'><h3>".__("User plugins")."</h3></td></tr>";

		print "<tr class=\"title\">
				<td width=\"5%\">&nbsp;</td>
				<td width='10%'>".__('Plugin')."</td>
				<td width=''>".__('Description')."</td>
				<td width='5%'>".__('Version')."</td>
				<td width='10%'>".__('Author')."</td></tr>";


		foreach ($tmppluginhost->get_plugins() as $name => $plugin) {
			$about = $plugin->about();

			if (!$about[3] && strpos($name, "example") === FALSE) {

				if (in_array($name, $system_enabled)) {
					$checked = "checked='1'";
					$disabled = "disabled='1'";
					$rowclass = '';
				} else if (in_array($name, $user_enabled)) {
					$checked = "checked='1'";
					$disabled = "";
					$rowclass = "Selected";
				} else {
					$checked = "";
					$disabled = "";
					$rowclass = '';
				}

				print "<tr class='$rowclass'>";

				print "<td align='center'><input id='FPCHK-$name' name='plugins[]' value='$name' onclick='toggleSelectRow2(this);'
					dojoType=\"dijit.form.CheckBox\" $checked $disabled
					type=\"checkbox\"></td>";

				print "<td><label for='FPCHK-$name'>$name</label></td>";
				print "<td><label for='FPCHK-$name'>" . htmlspecialchars($about[1]) . "</label></td>";
				print "<td>" . htmlspecialchars(sprintf("%.2f", $about[0])) . "</td>";
				print "<td>" . htmlspecialchars($about[2]) . "</td>";

				print "</tr>";



			}

		}

		print "</table>";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".
			__("Enable selected plugins")."</button></p>";

		print "</form>";

		print "</div>"; #pane

		global $pluginhost;
		$pluginhost->run_hooks($pluginhost::HOOK_PREFS_TAB,
			"hook_prefs_tab", "prefPrefs");

		print "</div>"; #container
	}

	function toggleAdvanced() {
		$_SESSION["prefs_show_advanced"] = !$_SESSION["prefs_show_advanced"];
	}

	function otpqrcode() {
		require_once "lib/otphp/vendor/base32.php";
		require_once "lib/otphp/lib/otp.php";
		require_once "lib/otphp/lib/totp.php";
		require_once "lib/phpqrcode/phpqrcode.php";

		$result = db_query($this->link, "SELECT login,salt,otp_enabled
			FROM ttrss_users
			WHERE id = ".$_SESSION["uid"]);

		$base32 = new Base32();

		$login = db_fetch_result($result, 0, "login");
		$otp_enabled = sql_bool_to_bool(db_fetch_result($result, 0, "otp_enabled"));

		if (!$otp_enabled) {
			$secret = $base32->encode(sha1(db_fetch_result($result, 0, "salt")));
			$topt = new \OTPHP\TOTP($secret);
			print QRcode::png($topt->provisioning_uri($login));
		}
	}

	function otpenable() {
		$password = db_escape_string($_REQUEST["password"]);
		$enable_otp = $_REQUEST["enable_otp"] == "on";

		global $pluginhost;
		$authenticator = $pluginhost->get_plugin($_SESSION["auth_module"]);

		if ($authenticator->check_password($_SESSION["uid"], $password)) {

			if ($enable_otp) {
				db_query($this->link, "UPDATE ttrss_users SET otp_enabled = true WHERE
					id = " . $_SESSION["uid"]);

				print "OK";
			}
		} else {
			print "ERROR: ".__("Incorrect password");
		}

	}

	function otpdisable() {
		$password = db_escape_string($_REQUEST["password"]);

		global $pluginhost;
		$authenticator = $pluginhost->get_plugin($_SESSION["auth_module"]);

		if ($authenticator->check_password($_SESSION["uid"], $password)) {

			db_query($this->link, "UPDATE ttrss_users SET otp_enabled = false WHERE
				id = " . $_SESSION["uid"]);

			print "OK";
		} else {
			print "ERROR: ".__("Incorrect password");
		}

	}

	function setplugins() {
		$plugins = join(",", $_REQUEST["plugins"]);

		set_pref($this->link, "_ENABLED_PLUGINS", $plugins);
	}
}
?>
