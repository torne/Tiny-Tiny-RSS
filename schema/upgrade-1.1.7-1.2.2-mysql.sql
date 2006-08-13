alter table ttrss_feeds add column hidden bool;

update ttrss_feeds set hidden = false;

alter table ttrss_feeds change hidden rtl_content bool not null;
alter table ttrss_feeds alter column hidden set default false;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('MARK_UNREAD_ON_UPDATE', 1, 'false', 'Set articles as unread on update',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('REVERSE_HEADLINES', 1, 'false', 'Reverse headline order (oldest first)',2);

update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_SEARCH_TOOLBAR';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'ENABLE_FEED_ICONS';
update ttrss_prefs SET section_id = 3 WHERE pref_name = 'EXTENDED_FEEDLIST';

update ttrss_version set schema_version = 9;

