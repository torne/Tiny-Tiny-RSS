<h1><?php echo __("Labels and SQL Expressions") ?></h1>

<p><?php echo __("Label content is generated using SQL expressions. The &laquo;SQL expression&raquo; is added to WHERE clause of view feed query. You can match on ttrss_entries table fields and even use subselect to query additional information. This 	functionality is considered to be advanced and requires some understanding of SQL.") ?></p>
	
<h2><?php echo __("Examples") ?></h2>

<p><?php echo __("Match all unread articles:") ?></p>

<code>unread = true</code>

<p><?php echo __("Matches all articles which mention Linux in the title:") ?></p>

<code>ttrss_entries.title like '%Linux%'</code>

<p><?php echo __("Matches all articles for the last week (PostgreSQL):") ?></p>

<code>updated &gt; NOW() - INTERVAL '7 days'</code>

<p><?php echo __("Matches all articles with scores between 100 and 500:") ?></p>

<code>score &gt; 100 and score &lt; 500</code>

<p>
