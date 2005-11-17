drop table if exists ttrss_tags;
drop table if exists ttrss_entries;
drop table if exists ttrss_feeds;

create table ttrss_feeds (id integer not null auto_increment primary key,
	title varchar(200) not null unique, 
	feed_url varchar(250) unique not null, 
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	purge_interval integer not null default 0,
	last_updated datetime default '',
	last_error text not null default '',
	site_url varchar(250) not null default '') TYPE=InnoDB;

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

create table ttrss_entries (id integer not null primary key auto_increment, 
	feed_id integer not null,
	updated datetime not null, 
	title text not null, 
	guid varchar(255) not null unique, 
	link text not null, 
	content text not null,
	content_hash varchar(250) not null,
	last_read datetime,
	marked bool not null default 0,
	date_entered datetime not null,
	no_orig_date bool not null default 0,
	comments varchar(250) not null default '',
	unread bool not null default 1,
	index (feed_id),
	foreign key (feed_id) references ttrss_feeds(id) ON DELETE CASCADE) TYPE=InnoDB;

drop table if exists ttrss_filters;
drop table if exists ttrss_filter_types;

create table ttrss_filter_types (id integer primary key, 
	name varchar(120) unique not null, 
	description varchar(250) not null unique) TYPE=InnoDB;


insert into ttrss_filter_types (id,name,description) values (1, 'title', 'Title');
insert into ttrss_filter_types (id,name,description) values (2, 'content', 'Content');
insert into ttrss_filter_types (id,name,description) values (3, 'both', 
	'Title or Content');

create table ttrss_filters (id integer primary key auto_increment, 
	filter_type integer not null references ttrss_filter_types(id), 
	reg_exp varchar(250) not null,
	description varchar(250) not null default '') TYPE=InnoDB;

drop table if exists ttrss_labels;

create table ttrss_labels (id integer primary key auto_increment, 
	sql_exp varchar(250) not null,
	description varchar(250) not null) TYPE=InnoDB;

insert into ttrss_labels (sql_exp,description) values ('unread = true', 
	'Unread articles');

insert into ttrss_labels (sql_exp,description) values (
	'last_read is null and unread = false', 'Updated articles');

create table ttrss_tags (id integer primary key auto_increment, 
	tag_name varchar(250) not null,
	post_id integer not null,
	index (post_id),
	foreign key (post_id) references ttrss_entries(id) ON DELETE CASCADE) TYPE=InnoDB;

drop table ttrss_version;

create table ttrss_version (schema_version int not null) TYPE=InnoDB;

insert into ttrss_version values (2);

drop table ttrss_prefs;
drop table ttrss_prefs_types;
drop table ttrss_prefs_sections;

create table ttrss_prefs_types (id integer primary key, 
	type_name varchar(100) not null) TYPE=InnoDB;

insert into ttrss_prefs_types (id, type_name) values (1, 'bool');
insert into ttrss_prefs_types (id, type_name) values (2, 'string');
insert into ttrss_prefs_types (id, type_name) values (3, 'integer');

create table ttrss_prefs_sections (id integer primary key, 
	section_name varchar(100) not null) TYPE=InnoDB;

insert into ttrss_prefs_sections (id, section_name) values (1, 'General');
insert into ttrss_prefs_sections (id, section_name) values (2, 'Interface');

create table ttrss_prefs (pref_name varchar(250) primary key,
	type_id integer not null,
	section_id integer not null default 1,
	short_desc text not null,
	help_text text not null default '',
	def_value text not null,
	value text not null,
	index(type_id),
	foreign key (type_id) references ttrss_prefs_types(id),
	index(section_id),
	foreign key (section_id) references ttrss_prefs_sections(id)) TYPE=InnoDB;

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ENABLE_FEED_ICONS', 1, 'true', 'true', 'Enable icons in feedlist',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ICONS_DIR', 2, 'icons', 'icons', 'Local directory for feed icons',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ICONS_URL', 2, 'icons', 'icons', 'Local URL for icons',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('PURGE_OLD_DAYS', 3, '60', '60', 'Purge old posts after this number of days (0 - disables)',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('UPDATE_POST_ON_CHECKSUM_CHANGE', 1, 'true', 'true', 'Update post on checksum change',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ENABLE_PREFS_CATCHUP_UNCATCHUP', 1, 'false', 'false', 'Enable catchup/uncatchup buttons in feed editor',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('ENABLE_LABELS', 1, 'false', 'false', 'Enable labels',1,
	'Experimental support for virtual feeds based on user crafted SQL queries. This feature is highly experimental and at this point not user friendly. Use with caution.');
	
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('DEFAULT_UPDATE_INTERVAL', 3, '30', '30', 'Default interval between feed updates (in minutes)',1);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('DISPLAY_HEADER', 1, 'true', 'true', 'Display header',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('DISPLAY_FOOTER', 1, 'true', 'true', 'Display footer',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('USE_COMPACT_STYLESHEET', 1, 'false', 'false', 'Use compact stylesheet by default',2);
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('DEFAULT_ARTICLE_LIMIT', 3, '0', '0', 'Default article limit',2,
	'Default limit for articles to display, any custom number you like (0 - disables).');
	
insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('DAEMON_REFRESH_ONLY', 1, 'false', 'false', 'Daemon refresh only',1,
	'Updates to all feeds will only run when the backend script is invoked with a "daemon" option on the URI stem.');

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id,help_text) values('DISPLAY_FEEDLIST_ACTIONS', 1, 'false', 'false', 'Display feedlist actions',2,
	'Display separate dropbox for feedlist actions, if disabled these actions are available in global actions menu.');

insert into ttrss_prefs (pref_name,type_id,value,def_value,short_desc,section_id) values('ENABLE_SPLASH', 1, 'false', 'false', 'Enable loading splashscreen',2);

