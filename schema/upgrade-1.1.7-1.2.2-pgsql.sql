begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('MARK_UNREAD_ON_UPDATE', 1, 'false', 'Set articles as unread on update',3);

update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_SEARCH_TOOLBAR';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_FEED_ICONS';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'EXTENDED_FEEDLIST';

update ttrss_version set schema_version = 9;

commit;

