<?php 
	header("Content-Type: text/plain");

	$manifest_formatted = array();
	$manifest_files = array(
		"localized_js.php",
      "tt-rss.php",
      "tt-rss.css",
      "viewfeed.js",
      "feedlist.js",
      "functions.js",
      "offline.js",
      "tt-rss.js",
      "images/blank_icon.gif",
      "images/button.png",
      "images/c1.png",
      "images/c2.png",
      "images/c3.png",
		"images/c4.png",
		"images/archive.png",
      "images/cat-collapse.png",
      "images/down_arrow.png",
      "images/footer.png",
      "images/fresh.png",
      "images/indicator_white.gif",
      "images/label.png",
      "images/mark_set.png",
      "images/mark_unset.png",
      "images/online.png",
      "images/overlay.png",
      "images/resize_handle_horiz.png",
      "images/resize_horiz.png",
      "images/resizer.png",
      "images/shadow_dark.png",
      "images/shadow-grid.gif",
      "images/shadow.png",
      "images/shadow_white.png",
      "images/sign_excl.gif",
      "images/sign_info.gif",
      "images/sign_quest.gif",
      "images/small_question.png",
      "images/tag.png",
      "images/toolbar.png",
      "images/ttrss_logo.png",
      "lib/scriptaculous/effects.js",
      "lib/scriptaculous/controls.js",
      "lib/scriptaculous/dragdrop.js",
      "lib/scriptaculous/scriptaculous.js",
      "lib/prototype.js",
		"gears_init.js");

	$mtime_max = 0;

	foreach ($manifest_files as $f) {
		$tmp = filemtime($f);
		if ($tmp > $mtime_max) {
			$mtime_max = $tmp;
		}

		array_push($manifest_formatted, "{ \"url\": \"$f\" }");
	}
?>

{
  "betaManifestVersion": 1,
  "version": "<?php echo date("Y.m.d H:i:s", $mtime_max) ?>",
  "entries": [
     <?php echo join(",\n     ", $manifest_formatted); ?>
    ]
}
