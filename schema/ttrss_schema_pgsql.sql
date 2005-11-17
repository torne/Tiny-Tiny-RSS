drop table ttrss_tags;
drop table ttrss_entries;
drop table ttrss_feeds;

create table ttrss_feeds (id serial not null primary key,
	title varchar(200) not null unique, 
	feed_url varchar(250) unique not null, 
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	purge_interval integer not null default 0,
	last_updated timestamp default null,
	last_error text not null default '',
	site_url varchar(250) not null default '');

insert into ttrss_feeds (title,feed_url) values ('Footnotes', 'http://gnomedesktop.org/node/feed');
insert into ttrss_feeds (title,feed_url) values ('Freedesktop.org', 'http://planet.freedesktop.org/rss20.xml');
insert into ttrss_feeds (title,feed_url) values ('Planet Debian', 'http://planet.debian.org/rss20.xml');
insert into ttrss_feeds (title,feed_url) values ('Planet GNOME', 'http://planet.gnome.org/rss20.xml');
insert into ttrss_feeds (title,feed_url) values ('Planet Ubuntu', 'http://planet.ubuntulinux.org/rss20.xml');

insert into ttrss_feeds (title,feed_url) values ('Monologue', 'http://www.go-mono.com/monologue/index.rss');

insert into ttrss_feeds (title,feed_url) values ('Latest Linux Kernel Versions', 
	'http://kernel.org/kdist/rss.xml');

insert into ttrss_feeds (title,feed_url) values ('RPGDot Newsfeed', 
	'http://www.rpgdot.com/team/rss/rss0.xml');

insert into ttrss_feeds (title,feed_url) values ('Digg.com News', 
	'http://digg.com/rss/index.xml');

insert into ttrss_feeds (title,feed_url) values ('Technocrat.net', 
	'http://syndication.technocrat.net/rss');

create table ttrss_entries (id serial not null primary key, 
	feed_id int references ttrss_feeds(id) ON DELETE CASCADE not null, 
	updated timestamp not null, 
	title text not null, 
	guid text not null unique, 
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

create table ttrss_filter_types (id integer primary key, 
	name varchar(120) unique not null, 
	description varchar(250) not null unique);

insert into ttrss_filter_types (id,name,description) values (1, 'title', 'Title');
insert into ttrss_filter_types (id,name,description) values (2, 'content', 'Content');
insert into ttrss_filter_types (id,name,description) values (3, 'both', 
	'Title or Content');

create table ttrss_filters (id serial primary key, 
	filter_type integer not null references ttrss_filter_types(id), 
	reg_exp varchar(250) not null,
	description varchar(250) not null default '');

drop table ttrss_labels;

create table ttrss_labels (id serial primary key, 
	sql_exp varchar(250) not null,
	description varchar(250) not null);

insert into ttrss_labels (sql_exp,description) values ('unread = true', 
	'Unread articles');

insert into ttrss_labels (sql_exp,description) values (
	'last_read is null and unread = false', 'Updated articles');

create table ttrss_tags (id serial primary key, 
	tag_name varchar(250) not null,
	post_id integer references ttrss_entries(id) ON DELETE CASCADE not null);

drop table ttrss_version;

create table ttrss_version (schema_version int not null);

insert into ttrss_version values (2);

drop table ttrss_prefs;
drop table ttrss_prefs_types;
drop table ttrss_prefs_sections;

create table ttrss_prefs_types (id integer primary key, 
	type_name varchar(100) not null);

insert into ttrss_prefs_types (id, type_name) values (1, 'bool');
insert into ttrss_prefs_types (id, type_name) values (2, 'string');
insert into ttrss_prefs_types (id, type_name) values (3, 'integer');

create table ttrss_prefs_sections (id integer primary key, 
	section_name varchar(100) not null);

insert into ttrss_prefs_sections (id, section_name) values (1, 'General');
insert into ttrss_prefs_sections (id, section_name) values (2, 'Interface');

create table ttrss_prefs (pref_name varchar(250) primary key,
	type_id integer not null references ttrss_prefs_types(id),
	section_id integer not null references ttrss_prefs_sections(id) default 1,
	short_desc text not null,
	help_text text not null default '',
	def_value text not null,
	value text not null);

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('ENABLE_FEED_ICONS', 1, 'true', 'true', 'Enable icons in feedlist');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('ICONS_DIR', 2, 'icons', 'icons', 'Local directory for feed icons');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('ICONS_URL', 2, 'icons', 'icons', 'Local URL for icons');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('PURGE_OLD_DAYS', 3, '60', '60', 'Purge old posts after this number of days (0 - disables)');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('UPDATE_POST_ON_CHECKSUM_CHANGE', 1, 'true', 'true', 'Update post on checksum change');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('ENABLE_PREFS_CATCHUP_UNCATCHUP', 1, 'false', 'false', 'Enable catchup/uncatchup buttons in feed editor');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('ENABLE_LABELS', 1, 'false', 'false', 'Enable experimental support for labels');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('DEFAULT_UPDATE_INTERVAL', 3, '30', '30', 'Default interval between feed updates (in minutes)');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('DISPLAY_HEADER', 1, 'true', 'true', 'Display header');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('DISPLAY_FOOTER', 1, 'true', 'true', 'Display footer');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('USE_COMPACT_STYLESHEET', 1, 'false', 'false', 'Use compact stylesheet by default');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('DEFAULT_ARTICLE_LIMIT', 3, '0', '0', 'Default article limit');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('DAEMON_REFRESH_ONLY', 1, 'false', 'false', 'Daemon refresh onky');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('DISPLAY_FEEDLIST_ACTIONS', 1, 'false', 'false', 'Display feedlist actions');
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc) values('ENABLE_SPLASH', 1, 'false', 'false', 'Enable loading splashscreen');

