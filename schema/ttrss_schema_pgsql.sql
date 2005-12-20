drop table ttrss_version;
drop table ttrss_labels;
drop table ttrss_filters;
drop table ttrss_filter_types;
drop table ttrss_filter_actions;
drop table ttrss_user_prefs;
drop table ttrss_prefs;
drop table ttrss_prefs_types;
drop table ttrss_prefs_sections; 
drop table ttrss_tags;
drop table ttrss_entry_comments;
drop table ttrss_user_entries;
drop table ttrss_entries;
drop table ttrss_feeds;
drop table ttrss_feed_categories;
drop table ttrss_users;
drop table ttrss_themes;

begin;

create table ttrss_themes(id serial not null primary key,
	theme_name varchar(200) not null,
	theme_path varchar(200) not null);

create table ttrss_users (id serial not null primary key,
	login varchar(120) not null unique,
	pwd_hash varchar(250) not null,
	last_login timestamp default null,
	access_level integer not null default 0,
	email varchar(250) not null default '',
	theme_id integer references ttrss_themes(id) default null);

insert into ttrss_users (login,pwd_hash,access_level) values ('admin', 'password', 10);

create table ttrss_feed_categories(id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	collapsed boolean not null default false,
	title varchar(200) not null);

create table ttrss_feeds (id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	title varchar(200) not null, 
	cat_id integer references ttrss_feed_categories(id) default null,
	feed_url varchar(250) not null, 
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	purge_interval integer not null default 0,
	last_updated timestamp default null,
	last_error text not null default '',
	site_url varchar(250) not null default '',
	auth_login varchar(250) not null default '',
	auth_pass varchar(250) not null default '');

create index ttrss_feeds_owner_uid_index on ttrss_feeds(owner_uid);

insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Footnotes', 'http://gnomedesktop.org/node/feed');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Latest Linux Kernel Versions','http://kernel.org/kdist/rss.xml');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'RPGDot Newsfeed',
   'http://www.rpgdot.com/team/rss/rss0.xml');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Digg.com News',
   'http://digg.com/rss/index.xml');
insert into ttrss_feeds (owner_uid,title,feed_url) values (1,'Technocrat.net',
   'http://syndication.technocrat.net/rss');

create table ttrss_entries (id serial not null primary key, 
	title text not null, 
	guid text not null unique, 
	link text not null, 
	updated timestamp not null, 
	content text not null,
	content_hash varchar(250) not null,
	no_orig_date boolean not null default false,
	date_entered timestamp not null default NOW(),
	num_comments integer not null default 0,
	comments varchar(250) not null default '');

create index ttrss_entries_guid_index on ttrss_entries(guid);
create index ttrss_entries_title_index on ttrss_entries(title);

create table ttrss_user_entries (
	int_id serial not null primary key,
	ref_id integer not null references ttrss_entries(id) ON DELETE CASCADE,
	feed_id int references ttrss_feeds(id) ON DELETE CASCADE not null, 
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	marked boolean not null default false,
	last_read timestamp,
	unread boolean not null default true);

create index ttrss_user_entries_feed_id_index on ttrss_user_entries(feed_id);
create index ttrss_user_entries_owner_uid_index on ttrss_user_entries(owner_uid);
create index ttrss_user_entries_ref_id_index on ttrss_user_entries(ref_id);

create table ttrss_entry_comments (id serial not null primary key,
	ref_id integer not null references ttrss_entries(id) ON DELETE CASCADE,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	private boolean not null default false,
	date_entered timestamp not null);
	
create index ttrss_entry_comments_ref_id_index on ttrss_entry_comments(ref_id);
create index ttrss_entry_comments_owner_uid_index on ttrss_entry_comments(owner_uid);

create table ttrss_filter_types (id integer not null primary key, 
	name varchar(120) unique not null, 
	description varchar(250) not null unique);

insert into ttrss_filter_types (id,name,description) values (1, 'title', 'Title');
insert into ttrss_filter_types (id,name,description) values (2, 'content', 'Content');
insert into ttrss_filter_types (id,name,description) values (3, 'both', 
	'Title or Content');
insert into ttrss_filter_types (id,name,description) values (4, 'link', 
	'Link');

create table ttrss_filter_actions (id integer not null primary key, 
	name varchar(120) unique not null, 
	description varchar(250) not null unique);

insert into ttrss_filter_actions (id,name,description) values (1, 'filter', 
	'Filter article');

insert into ttrss_filter_actions (id,name,description) values (2, 'catchup', 
	'Mark as read');

create table ttrss_filters (id serial not null primary key, 	
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	feed_id integer references ttrss_feeds(id) on delete cascade default null,
	filter_type integer not null references ttrss_filter_types(id), 
	reg_exp varchar(250) not null,
	description varchar(250) not null default '',
	action_id integer not null default 1 references ttrss_filter_actions(id) on delete cascade);

create table ttrss_labels (id serial not null primary key, 
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	sql_exp varchar(250) not null,
	description varchar(250) not null);

create index ttrss_labels_owner_uid_index on ttrss_labels(owner_uid);

insert into ttrss_labels (owner_uid,sql_exp,description) values (1,'unread = true', 
	'Unread articles');

insert into ttrss_labels (owner_uid,sql_exp,description) values (1,
	'last_read is null and unread = false', 'Updated articles');

create table ttrss_tags (id serial not null primary key, 
	tag_name varchar(250) not null,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	post_int_id integer references ttrss_user_entries(int_id) ON DELETE CASCADE not null);

create index ttrss_tags_owner_uid_index on ttrss_tags(owner_uid);

create table ttrss_version (schema_version int not null);

insert into ttrss_version values (2);

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
	
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DISPLAY_FEEDLIST_ACTIONS', 1, 'false', 'Display feedlist actions',2,
	'Display separate dropbox for feedlist actions, if disabled these actions are available in global actions menu.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ALLOW_DUPLICATE_POSTS', 1, 'true', 'Allow duplicate posts',1,
	'This option is useful when you are reading several planet-type aggregators with partially colliding userbase. 
	When disabled, it forces same posts from different feeds to appear only once.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('USER_STYLESHEET_URL', 2, '', 'User stylesheet URL',2,
	'Link to user stylesheet to override default style, disabled if empty.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_FEED_CATS', 1, 'false', 'Enable feed categories',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('SHOW_CONTENT_PREVIEW', 1, 'true', 'Show content preview in headlines list',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('SHORT_DATE_FORMAT', 2, 'M d, G:i', 'Short date format',3);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('LONG_DATE_FORMAT', 2, 'D, M d Y - G:i', 'Long date format',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('HEADLINES_SMART_DATE', 1, 'true', 'Use more accessible date/time format for headlines',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('COMBINED_DISPLAY_MODE', 1, 'false', 'Combined feed display',2,
	'Display expanded list of feed articles, instead of separate displays for headlines and article content');

create table ttrss_user_prefs (
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	pref_name varchar(250) not null references ttrss_prefs(pref_name) ON DELETE CASCADE,
	value text not null);

create index ttrss_user_prefs_owner_uid_index on ttrss_user_prefs(owner_uid);
create index ttrss_user_prefs_value_index on ttrss_user_prefs(value);

commit;
