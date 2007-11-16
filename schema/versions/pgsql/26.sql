insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('PURGE_UNREAD_ARTICLES', 1, 'true', 'Purge unread articles',3);

alter table ttrss_users add column created timestamp;
alter table ttrss_users alter column created set default null;

update ttrss_version set schema_version = 26;
