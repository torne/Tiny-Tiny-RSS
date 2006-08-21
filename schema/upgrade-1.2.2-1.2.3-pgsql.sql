insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DIGEST_ENABLE', 1, 'false', 'Enable e-mail digest',1,
'This option enables sending daily digest of new (and unread) headlines on your configured e-mail address');

update ttrss_version set schema_version = 10;

