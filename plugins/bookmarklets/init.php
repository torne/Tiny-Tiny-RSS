<?php
class Bookmarklets extends Plugin {
  private $host;

  function about() {
    return array(1.0,
		 "Easy feed subscription and web page sharing using bookmarklets",
		 "fox");
  }

  function init($host) {
    $this->host = $host;

    $host->add_hook($host::HOOK_PREFS_TAB, $this);
  }

  function hook_prefs_tab($args) {
    if ($args == "prefFeeds") {

		print "<div dojoType=\"dijit.layout.AccordionPane\" title=\"".__('Bookmarklets')."\">";

		print "<p>" . __("Drag the link below to your browser toolbar, open the feed you're interested in in your browser and click on the link to subscribe to it.") . "</p>";

		$bm_subscribe_url = str_replace('%s', '', add_feed_url());

		$confirm_str = str_replace("'", "\'", __('Subscribe to %s in Tiny Tiny RSS?'));

		$bm_url = htmlspecialchars("javascript:{if(confirm('$confirm_str'.replace('%s',window.location.href)))window.location.href='$bm_subscribe_url'+window.location.href}");

		print "<a href=\"$bm_url\" class='bookmarklet'>" . __('Subscribe in Tiny Tiny RSS'). "</a>";

		print "<p>" . __("Use this bookmarklet to publish arbitrary pages using Tiny Tiny RSS") . "</p>";

		$bm_url = htmlspecialchars("javascript:(function(){var d=document,w=window,e=w.getSelection,k=d.getSelection,x=d.selection,s=(e?e():(k)?k():(x?x.createRange().text:0)),f='".SELF_URL_PATH."/public.php?op=sharepopup',l=d.location,e=encodeURIComponent,g=f+'&title='+((e(s))?e(s):e(document.title))+'&url='+e(l.href);function a(){if(!w.open(g,'t','toolbar=0,resizable=0,scrollbars=1,status=1,width=500,height=250')){l.href=g;}}a();})()");

		print "<a href=\"$bm_url\" class='bookmarklet'>" . __('Share with Tiny Tiny RSS'). "</a>";

		print "</div>"; #pane

	 }

  }

}
?>
