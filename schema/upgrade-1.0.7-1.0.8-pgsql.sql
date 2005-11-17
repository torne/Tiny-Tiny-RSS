begin;

alter table ttrss_feeds add column purge_interval integer;

update ttrss_feeds set purge_interval = 0;

alter table ttrss_feeds alter column purge_interval set not null;
alter table ttrss_feeds alter column purge_interval set default 0;

update ttrss_version set schema_version = 2;

create table ttrss_prefs_types (id integer primary key, 
	type_name varchar(100) not null);

insert into ttrss_prefs_types (id, type_name) values (1, 'bool');
insert into ttrss_prefs_types (id, type_name) values (2, 'string');
insert into ttrss_prefs_types (id, type_name) values (3, 'integer');

create table ttrss_prefs_sections (id integer primary key, 
	section_name varchar(100) not null);

insert into ttrss_prefs_sections (id, section_name) values (1, 'General');
insert into ttrss_prefs_sections (id, section_name) values (2, 'Interface');
insert into ttrss_prefs_sections (id, section_name) values (3, 'Advanced');

create table ttrss_prefs (pref_name varchar(250) primary key,
	type_id integer not null references ttrss_prefs_types(id),
	section_id integer not null references ttrss_prefs_sections(id) default 1,
	short_desc text not null,
	help_text text not null default '',
	def_value text not null,
	value text not null);

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ENABLE_FEED_ICONS', 1, 'true', 'true', 'Enable icons in feedlist',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ICONS_DIR', 2, 'icons', 'icons', 'Local directory for feed icons',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ICONS_URL', 2, 'icons', 'icons', 'Local URL for icons',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('PURGE_OLD_DAYS', 3, '60', '60', 'Purge old posts after this number of days (0 - disables)',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('UPDATE_POST_ON_CHECKSUM_CHANGE', 1, 'true', 'true', 'Update post on checksum change',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ENABLE_PREFS_CATCHUP_UNCATCHUP', 1, 'false', 'false', 'Enable catchup/uncatchup buttons in feed editor',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('ENABLE_LABELS', 1, 'false', 'false', 'Enable labels',3,
	'Experimental support for virtual feeds based on user crafted SQL queries. This feature is highly experimental and at this point not user friendly. Use with caution.');
	
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('DEFAULT_UPDATE_INTERVAL', 3, '30', '30', 'Default interval between feed updates (in minutes)',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('DISPLAY_HEADER', 1, 'true', 'true', 'Display header',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('DISPLAY_FOOTER', 1, 'true', 'true', 'Display footer',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('USE_COMPACT_STYLESHEET', 1, 'false', 'false', 'Use compact stylesheet by default',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('DEFAULT_ARTICLE_LIMIT', 3, '0', '0', 'Default article limit',2,
	'Default limit for articles to display, any custom number you like (0 - disables).');
	
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('DAEMON_REFRESH_ONLY', 1, 'false', 'false', 'Daemon refresh only', 3,
	'Updates to all feeds will only run when the backend script is invoked with a "daemon" option on the URI stem.');

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('DISPLAY_FEEDLIST_ACTIONS', 1, 'false', 'false', 'Display feedlist actions',2,
	'Display separate dropbox for feedlist actions, if disabled these actions are available in global actions menu.');

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ENABLE_SPLASH', 1, 'false', 'false', 'Enable loading splashscreen',2);

commit;
