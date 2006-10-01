begin;

delete FROM ttrss_user_prefs WHERE pref_name = 'DISPLAY_HEADER';
delete FROM ttrss_user_prefs WHERE pref_name = 'DISPLAY_FOOTER';
delete FROM ttrss_user_prefs WHERE pref_name = 'ENABLE_SEARCH_TOOLBAR';

delete FROM ttrss_prefs WHERE pref_name = 'DISPLAY_HEADER';
delete FROM ttrss_prefs WHERE pref_name = 'DISPLAY_FOOTER';
delete FROM ttrss_prefs WHERE pref_name = 'ENABLE_SEARCH_TOOLBAR';

insert into ttrss_themes (theme_name, theme_path) 
	values ('Graycube', 'graycube');

update ttrss_version set schema_version = 11;

commit;
