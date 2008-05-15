	<h1>Keyboard Shortcuts</h1>

	<table width='100%'><tr><td width='50%'>

	<h2>Navigation</h2>

	<table>
		<tr><td class='n'>j/k</td><td>Move between feeds</td></tr>
		<tr><td class='n'>n/p</td><td>Move between articles</td></tr>
		<tr><td class='n'>/</td><td>Show search dialog</td></tr>
	</table>

	<h2>Active article actions</h2>

	<table>
		<tr><td class='n'>s</td><td>Toggle starred</td></tr>
		<tr><td class='n'>shift-S</td><td>Toggle published</td></tr>
		<tr><td class='n'>u</td><td>Toggle unread</td></tr>
		<tr><td class='n'>T</td><td>Edit tags</td></tr>

		<? if (get_pref($link, "COMBINED_DISPLAY_MODE")) { ?>
			<tr><td class='n'>t</td><td>Select article under mouse pointer</td></tr>
		<? } else { ?>
			<tr><td class='n'>t</td><td class="insensitive">Select article under mouse pointer <span class="small"></span></td></tr>
		<? } ?>

		<!-- <tr><td class='n'>S</td><td>Edit score</td></tr> -->
	</table>

	<h2>Other actions</h2>

	<table>
		<tr><td class='n'>c f</td><td>Create filter</td></tr>
		<tr><td class='n'>c s</td><td>Collapse sidebar</td></tr>
		<tr><td class='n'>?</td><td>Display this help dialog</td></tr>
	</table>

	</td><td>

	<h2>Feed actions</h2>

	<table>
		<tr><td class='n'>f a</td><td>(Un)hide read feeds</td></tr>
		<tr><td class='n'>f s</td><td>Subscribe to feed</td></tr>
		<tr><td class='n'>f u</td><td>Update feed</td></tr>
		<tr><td class='n'>f U</td><td>Update all feeds</td></tr>
		<tr><td class='n'>f e</td><td>Edit feed</td></tr>
		<tr><td class='n'>f c</td><td>Mark feed as read</td></tr>
		<tr><td class='n'>f C</td><td>Mark all feeds as read</td></tr>
	</table>

	<h2>Go to...</h2>

	<table>
		<tr><td class='n'>g s</td><td>Starred articles</td></tr>
		<tr><td class='n'>g f</td><td>Fresh articles</td></tr>
		<tr><td class='n'>g p</td><td>Published articles</td></tr>
		<tr><td class='n'>g P</td><td>Preferences</td></tr>
	</table>


	</td></tr></table>

	<p class="small">Press any key to close this window.</p>
