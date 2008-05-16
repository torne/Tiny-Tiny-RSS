<h1><?php echo __("Content filtering") ?></h1>

<p><?php echo __("Tiny Tiny RSS has support for filtering (or processing) articles. Filtering is done once, when new article is imported to the database from the newsfeed, specified field is matched against regular expression and some action is taken. Regular expression matching is case-insensitive.") ?></p>

<p><?php echo __("Supported actions are: filter (do not import) article, mark article as read, set starred, assign tag(s), and set score. Filters can be defined globally and for some specific feed.") ?></p>

<p><?php echo __("Multiple and inverse matching are supported. All matching filters are considered when article is being imported and all actions executed in sequence. Inverse matching reverts matching result, e.g. filter matching XYZZY in title with inverse flag will match all articles, except those containing string XYZZY in title.") ?></p>

<p><?php echo __("See also:")?> <a target="_new" href="http://tt-rss.spb.ru/trac/wiki/ContentFilters">ContentFilters (wiki)</a>

