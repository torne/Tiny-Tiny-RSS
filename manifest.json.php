<?php 
	header("Content-Type: text/plain");

	$manifest_formatted = array();
	$manifest_files = array(
      "tt-rss.php",
      "tt-rss.css",
      "viewfeed.js",
      "feedlist.js",
      "functions.js",
      "offline.js",
      "tt-rss.js",
      "images/art-inline.png",
      "images/art-zoom.png",
      "images/blank_icon.gif",
      "images/button.png",
      "images/c1.png",
      "images/c2.png",
      "images/c3.png",
      "images/c4.png",
      "images/cat-collapse.png",
      "images/down_arrow.png",
      "images/favicon.png",
      "images/feed-icon-12x12.png",
      "images/feed-icon-64x64.png",
      "images/footer.png",
      "images/fresh_new.png",
      "images/fresh.png",
      "images/indicator_white.gif",
      "images/label.png",
      "images/mark_set.gif",
      "images/mark_set.png",
      "images/mark_unset.gif",
      "images/mark_unset.png",
      "images/new_version.png",
      "images/offline.png",
      "images/offline-sync.gif",
      "images/online.png",
      "images/overlay.png",
      "images/piggie_icon.png",
      "images/piggie.png",
      "images/prefs-content.png",
      "images/pub_set.gif",
      "images/pub_unset.gif",
      "images/resize_handle_horiz.png",
      "images/resize_horiz.png",
      "images/resizer.png",
      "images/score_half_high.png",
      "images/score_half_low.png",
      "images/score_high.png",
      "images/score_low.png",
      "images/score_neutral.png",
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
      "images/ttrss_logo_big.png",
      "images/ttrss_logo_blackred.png",
      "images/ttrss_logo.png",
      "images/ttrss_logo_small.png",
      "images/updated.png",
      "images/www.png",
      "extras/button/musicplayer_f6.swf",
      "extras/button/musicplayer.swf",
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
