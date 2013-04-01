<?php 
	// this file contains default values for various setting that can be 
	// overridden in config.php
	// It shoule always be "require()"-d after config.php to allow 
	// overriding, so please do not "require()" this file directly, it is 
	// alrady properly included from functions.php

	// **************************************
	// *** Update proces tuning settings  ***
	// **************************************

	define_default('FEED_FETCH_TIMEOUT', 45);
	// How may seconds to wait for response when requesting feed from a site
	// You may need to decease this if you see errors like "MySQL server
	// has gone away" pop up in your feed update logs after fetching feeds
	// from slow websites

	define_default('FEED_FETCH_NO_CACHE_TIMEOUT', 15);
	// How may seconds to wait for response when requesting feed from a
	// site when that feed wasn't cached before

	define_default('FILE_FETCH_TIMEOUT', 45);
	// Default timeout when fetching files from remote sites

	define_default('FILE_FETCH_CONNECT_TIMEOUT', 15);
	// How many seconds to wait for initial response from website when
	// fetching files from remote sites
?>
