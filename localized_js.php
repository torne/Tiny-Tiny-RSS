<?php 
error_reporting(E_ERROR | E_WARNING | E_PARSE);
define('DISABLE_SESSIONS', true);

require "functions.php";
header("Content-Type: text/plain; charset=UTF-8");

function T_js_decl($s1) {

	if (!$s1) return;

	$T_s1 = __($s1);

	if ($T_s1 != $s1) {
		return "T_messages[\"$s1\"] = \"".__($s1)."\";\n";
	} else {
		return "";
	}
}
?>

var T_messages = new Object();

function __(msg) {
	if (T_messages[msg]) {
		return T_messages[msg];
	} else {
		debug('[gettext] not found: ' + msg);
		return msg;
	}
}

<?php

print T_js_decl("display feeds");
print T_js_decl("display tags");
print T_js_decl("Loading, please wait...");
print T_js_decl("All feeds updated.");
print T_js_decl("Marking all feeds as read...");
print T_js_decl("Adding feed...");
print T_js_decl("Removing feed...");
print T_js_decl("Saving feed...");
print T_js_decl("Can't add category: no name specified.");
print T_js_decl("Adding feed category...");
print T_js_decl("Can't add user: no login specified.");

print T_js_decl("Adding user...");
print T_js_decl("Can't create label: missing SQL expression.");
print T_js_decl("Can't create label: missing caption.");
print T_js_decl("Remove selected labels?");
print T_js_decl("Removing selected labels...");
print T_js_decl("No labels are selected.");
print T_js_decl("Remove selected users?");
print T_js_decl("Removing selected users...");
print T_js_decl("No users are selected.");
print T_js_decl("Remove selected filters?");
print T_js_decl("Removing selected filters...");
print T_js_decl("No filters are selected.");
print T_js_decl("Unsubscribe from selected feeds?");
print T_js_decl("Unsubscribing from selected feeds...");
print T_js_decl("No feeds are selected.");
print T_js_decl("Remove selected categories?");
print T_js_decl("Removing selected categories...");
print T_js_decl("No categories are selected.");
print T_js_decl("Saving category...");
print T_js_decl("Loading help...");
print T_js_decl("Saving label...");
print T_js_decl("Login field cannot be blank.");
print T_js_decl("Saving user...");
print T_js_decl("Saving filter...");
print T_js_decl("No labels are selected.");
print T_js_decl("Please select only one label.");
print T_js_decl("No users are selected.");
print T_js_decl("Please select only one user.");
print T_js_decl("No users are selected.");
print T_js_decl("Please select only one user.");
print T_js_decl("Reset password of selected user?");
print T_js_decl("Resetting password for selected user...");
print T_js_decl("No feeds are selected.");
print T_js_decl("Please select only one feed.");
print T_js_decl("No filters are selected.");
print T_js_decl("Please select only one filter.");
print T_js_decl("No feeds are selected.");
print T_js_decl("Please select one feed.");
print T_js_decl("No categories are selected.");
print T_js_decl("Please select only one category.");
print T_js_decl("No OPML file to upload.");
print T_js_decl("Changing category of selected feeds...");
print T_js_decl("Reset to defaults?");
print T_js_decl("Trying to change password...");
print T_js_decl("Trying to change e-mail...");
print T_js_decl("No articles are selected.");
print T_js_decl("Could not update headlines (missing XML object)");
print T_js_decl("Could not display article (missing XML object)");
print T_js_decl("No feed selected.");
print T_js_decl("Replace current publishing address with a new one?");
print T_js_decl("Address changed.");
print T_js_decl("Could not change address.");
print T_js_decl("You can't unsubscribe from the category.");
print T_js_decl("Please select some feed first.");
print T_js_decl("You can't edit this kind of feed.");
print T_js_decl("Can't create label: missing SQL expression.");
print T_js_decl("Can't create label: missing caption.");
print T_js_decl("Can't add user: no login specified.");
print T_js_decl("No labels are selected.");
print T_js_decl("No users are selected.");
print T_js_decl("No filters are selected.");
print T_js_decl("No feeds are selected.");
print T_js_decl("No categories are selected.");
print T_js_decl("Login field cannot be blank.");
print T_js_decl("No feeds are selected.");
print T_js_decl("Can't add filter: nothing to match on.");
print T_js_decl("Can't subscribe: no feed URL given.");
print T_js_decl("Unsubscribe from %s?");
print T_js_decl("Mark all visible articles in %s as read?");
print T_js_decl("Mark all articles in %s as read?");
print T_js_decl("Mark %d selected articles in %s as read?");
print T_js_decl("Please enter label title:");
print T_js_decl("Save current configuration?");
print T_js_decl("Old password cannot be blank.");
print T_js_decl("New password cannot be blank.");
print T_js_decl("Entered passwords do not match.");
print T_js_decl("No articles found to mark");
print T_js_decl("Mark %d article(s) as read?");
print T_js_decl("No article is selected.");
print T_js_decl("Star article");
print T_js_decl("Unstar article");
print T_js_decl("Please wait...");
print T_js_decl("Publish article");
print T_js_decl("Unpublish article");
print T_js_decl("You can't clear this type of feed.");
print T_js_decl("Erase all non-starred articles in %s?");
print T_js_decl("Mark all articles as read?");
print T_js_decl("Rescore articles in selected feeds?");
print T_js_decl("Rescoring selected feeds...");
print T_js_decl("Rescore articles in all feeds? This operation may take a lot of time.");
print T_js_decl("Rescoring feeds...");
print T_js_decl("You can't rescore this kind of feed.");
print T_js_decl("Rescore articles in %s?");
print T_js_decl("Rescoring articles...");
print T_js_decl("Reset category order?");
print T_js_decl("Category reordering enabled");
print T_js_decl("Category reordering disabled");
print T_js_decl("Save changes to selected feeds?");
print T_js_decl("Saving feeds...");
#print T_js_decl(
#print T_js_decl(
#print T_js_decl(
#print T_js_decl(
#print T_js_decl(
#print T_js_decl(
#print T_js_decl(
#print T_js_decl(
?>
