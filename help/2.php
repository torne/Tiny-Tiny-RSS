<h1>Content filters</h1>

<p>TT-RSS has support for filtering (or processing) articles. Filtering is done once, when new article is imported to the database from the newsfeed, specified field is matched against regular expression and some action is taken. Regular expression matching is case-insensitive.</p>

<p>Supported actions: filter (do not import) article, mark article as read, set starred, assign tag(s). Filters can be defined globally and for some specific feed.</p>

<p>Multiple and inverse matching are supported. All matching filters are considered when article is being imported and all actions executed in sequence. Inverse matching reverts matching result, e.g. filter matching XYZZY in title with inverse flag will match all articles, except those containing string XYZZY in title.</p>

<p>See <a target="_new" href="http://tt-rss.spb.ru/trac/wiki/ContentFilters">this page</a> for additional information on filtering.</p>

