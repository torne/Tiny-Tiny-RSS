<h1>Help for SQL expressions</h1>

<h2>Description</h2>

<p>The &laquo;SQL expression&raquo; is added to WHERE clause of
	view feed query. You can match on ttrss_entries table fields
	and even use subselect to query additional information. This 
	functionality is considered to be advanced and requires basic
	understanding of SQL.</p>
	
<h2>Examples</h2>

<p>Match all unread articles:</p>

<pre>unread = true</pre>

<p>Matches all articles which mention Linux in the title:</p>

<pre>title like '%Linux%'</pre>

<p>See the database schema included in the distribution package for gruesome
details.</p>

