<?php
class Query_Headlines extends Plugin {
	// example of the use of the HOOK_QUERY_HEADLINES
	// this example will change the author and tags to be empty string so they don't display
	// the arguements are:
	//	-	 the array of elements that are returned by queryFeedHeadlines
	// 	-	the length that the caller wants to truncate the content preview to
	//	-	a boolean that indicates if the caller is from an API call
	//NOTE:****  You have to make this a system plugin if you want it to also work
	//		on API calls.  If you just make it a user plugin it will work on web page output
	//		but not on API calls
	private $host;

	function about() {
		return array(1.0,
			"Example of use of HOOK_QUERY_HEADLINES",
			"justauser" );
	}

	function init($host) {
		$this->host = $host;
		$host->add_hook($host::HOOK_QUERY_HEADLINES, $this);
	}

	// passes in the array for an item
	// second argument is the length of the preview the caller is using
	// create a key called "modified_preview" if you change the preview and don't want
	//		caller to override with their default

	function hook_query_headlines($line, $preview_length = 100,$api_call=false) {
		//make the author field empty
		$line["author"] = "";
		
		// and toss tags, since I don't use
		$line["tag_cache"] = "";
		return $line;
		
		
	}
	

	function api_version() {
		return 2;
	}

}
?>
