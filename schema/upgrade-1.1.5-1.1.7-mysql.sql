insert into ttrss_themes (theme_name, theme_path) values ('Old-skool', 'compat');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ON_CATCHUP_SHOW_NEXT_FEED', 1, 'false', 'On catchup show next feed',2,
	'When "Mark as read" button is clicked in toolbar, automatically open next feed with unread articles.');

update ttrss_version set schema_version = 8;

