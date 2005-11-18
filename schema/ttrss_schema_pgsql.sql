drop table ttrss_tags;
drop table ttrss_entries;
drop table ttrss_feeds;
drop table ttrss_labels;
drop table ttrss_filters;

drop table ttrss_user_prefs;
drop table ttrss_users;

create table ttrss_users (id serial not null primary key,
	login varchar(120) not null unique,
	pwd_hash varchar(250) not null,
	last_login timestamp default null,
	access_level integer not null default 0);

insert into ttrss_users (login,pwd_hash,access_level) values ('admin', 'password', 10);

create table ttrss_feeds (id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	title varchar(200) not null, 
	feed_url varchar(250) not null, 
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	purge_interval integer not null default 0,
	last_updated timestamp default null,
	last_error text not null default '',
	site_url varchar(250) not null default '');

insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Footnotes', 'http://gnomedesktop.org/node/feed');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Latest Linux Kernel Versions','http://kernel.org/kdist/rss.xml');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'RPGDot Newsfeed',
   'http://www.rpgdot.com/team/rss/rss0.xml');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Digg.com News',
   'http://digg.com/rss/index.xml');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Technocrat.net',
   'http://syndication.technocrat.net/rss');

create table ttrss_entries (id serial not null primary key, 
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	feed_id int references ttrss_feeds(id) ON DELETE CASCADE not null, 
	updated timestamp not null, 
	title text not null, 
	guid text not null, 
	link text not null, 
	content text not null,
	content_hash varchar(250) not null,
	last_read timestamp,
	marked boolean not null default false,
	date_entered timestamp not null default NOW(),
	no_orig_date boolean not null default false,
	comments varchar(250) not null default '',
	unread boolean not null default true);

drop table ttrss_filters;
drop table ttrss_filter_types;

create table ttrss_filter_types (id integer not null primary key, 
	name varchar(120) unique not null, 
	description varchar(250) not null unique);

insert into ttrss_filter_types (id,name,description) values (1, 'title', 'Title');
insert into ttrss_filter_types (id,name,description) values (2, 'content', 'Content');
insert into ttrss_filter_types (id,name,description) values (3, 'both', 
	'Title or Content');

create table ttrss_filters (id serial not null primary key, 
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	filter_type integer not null references ttrss_filter_types(id), 
	reg_exp varchar(250) not null,
	description varchar(250) not null default '');

drop table ttrss_labels;

create table ttrss_labels (id serial not null primary key, 
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	sql_exp varchar(250) not null,
	description varchar(250) not null);

insert into ttrss_labels (owner_uid,sql_exp,description) values (1,'unread = true', 
	'Unread articles');

insert into ttrss_labels (owner_uid,sql_exp,description) values (1,
	'last_read is null and unread = false', 'Updated articles');

create table ttrss_tags (id serial not null primary key, 
	tag_name varchar(250) not null,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	post_id integer references ttrss_entries(id) ON DELETE CASCADE not null);

drop table ttrss_version;

create table ttrss_version (schema_version int not null);

insert into ttrss_version values (2);

drop table ttrss_prefs;
drop table ttrss_prefs_types;
drop table ttrss_prefs_sections;

create table ttrss_prefs_types (id integer not null primary key, 
	type_name varchar(100) not null);

insert into ttrss_prefs_types (id, type_name) values (1, 'bool');
insert into ttrss_prefs_types (id, type_name) values (2, 'string');
insert into ttrss_prefs_types (id, type_name) values (3, 'integer');

create table ttrss_prefs_sections (id integer not null primary key, 
	section_name varchar(100) not null);

insert into ttrss_prefs_sections (id, section_name) values (1, 'General');
insert into ttrss_prefs_sections (id, section_name) values (2, 'Interface');
insert into ttrss_prefs_sections (id, section_name) values (3, 'Advanced');

create table ttrss_prefs (pref_name varchar(250) not null primary key,
	type_id integer not null references ttrss_prefs_types(id),
	section_id integer not null references ttrss_prefs_sections(id) default 1,
	short_desc text not null,
	help_text text not null default '',
	def_value text not null);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_FEED_ICONS', 1, 'true', 'Enable icons in feedlist',2);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('PURGE_OLD_DAYS', 3, '60', 'Purge old posts after this number of days (0 - disables)',1);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('UPDATE_POST_ON_CHECKSUM_CHANGE', 1, 'true', 'Update post on checksum change',1);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_PREFS_CATCHUP_UNCATCHUP', 1, 'false', 'Enable catchup/uncatchup buttons in feed editor',2);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ENABLE_LABELS', 1, 'false', 'Enable labels',3,
	'Experimental support for virtual feeds based on user crafted SQL queries. This feature is highly experimental and at this point not user friendly. Use with caution.');
	
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DEFAULT_UPDATE_INTERVAL', 3, '30', 'Default interval between feed updates (in minutes)',1);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DISPLAY_HEADER', 1, 'true', 'Display header',2);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DISPLAY_FOOTER', 1, 'true', 'Display footer',2);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('USE_COMPACT_STYLESHEET', 1, 'false', 'Use compact stylesheet by default',2);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DEFAULT_ARTICLE_LIMIT', 3, '0', 'Default article limit',2,
	'Default limit for articles to display, any custom number you like (0 - disables).');
	
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DAEMON_REFRESH_ONLY', 1, 'false', 'Daemon refresh only', 3,
	'Updates to all feeds will only run when the backend script is invoked with a "daemon" option on the URI stem.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DISPLAY_FEEDLIST_ACTIONS', 1, 'false', 'Display feedlist actions',2,
	'Display separate dropbox for feedlist actions, if disabled these actions are available in global actions menu.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_SPLASH', 1, 'false', 'Enable loading splashscreen',2);

create table ttrss_user_prefs (
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	pref_name varchar(250) not null references ttrss_prefs(pref_name),
	value text not null);


