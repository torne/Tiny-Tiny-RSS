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
drop table ttrss_enclosures;
drop table ttrss_entry_comments;
drop table ttrss_user_entries;
drop table ttrss_entries;
drop table ttrss_scheduled_updates;
drop table ttrss_feeds;
drop table ttrss_feed_categories;
drop table ttrss_users;
drop table ttrss_themes;
drop table ttrss_sessions;
drop function SUBSTRING_FOR_DATE(timestamp, int, int);

begin;

create table ttrss_themes(id serial not null primary key,
	theme_name varchar(200) not null,
	theme_path varchar(200) not null);

insert into ttrss_themes (theme_name, theme_path) values ('Old-skool', 'compat');
insert into ttrss_themes (theme_name, theme_path) values ('Graycube', 'graycube');
insert into ttrss_themes (theme_name, theme_path) values ('Default (Compact)', 'compact');
insert into ttrss_themes (theme_name, theme_path) values ('Three-pane', '3pane');

create table ttrss_users (id serial not null primary key,
	login varchar(120) not null unique,
	pwd_hash varchar(250) not null,
	last_login timestamp default null,
	access_level integer not null default 0,
	email varchar(250) not null default '',
	email_digest boolean not null default false,
	last_digest_sent timestamp default null,
	created timestamp default null,
	theme_id integer references ttrss_themes(id) default null);

insert into ttrss_users (login,pwd_hash,access_level) values ('admin', 
	'SHA1:5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 10);

create table ttrss_feed_categories(id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	collapsed boolean not null default false,
	title varchar(200) not null);

create table ttrss_feeds (id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	title varchar(200) not null, 
	cat_id integer default null references ttrss_feed_categories(id) on delete set null,
	feed_url text not null, 
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	purge_interval integer not null default 0,
	last_updated timestamp default null,
	last_error text not null default '',
	site_url varchar(250) not null default '',
	auth_login varchar(250) not null default '',
	parent_feed integer default null references ttrss_feeds(id) on delete set null,
	private boolean not null default false,
	auth_pass varchar(250) not null default '',
	hidden boolean not null default false,
	include_in_digest boolean not null default true,
	rtl_content boolean not null default false,
	cache_images boolean not null default false,
	last_viewed timestamp default null,
	last_update_started timestamp default null,
	update_method integer not null default 0,
	auth_pass_encrypted boolean not null default false);	

create index ttrss_feeds_owner_uid_index on ttrss_feeds(owner_uid);

insert into ttrss_feeds (owner_uid, title, feed_url) values
	(1, 'Tiny Tiny RSS: New Releases', 'http://tt-rss.spb.ru/releases.rss');

insert into ttrss_feeds (owner_uid, title, feed_url) values 
	(1, 'Tiny Tiny RSS: Forum', 'http://tt-rss.spb.ru/forum/rss.php');

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
	comments varchar(250) not null default '',
	author varchar(250) not null default '');

create index ttrss_entries_guid_index on ttrss_entries(guid);
-- create index ttrss_entries_title_index on ttrss_entries(title);
create index ttrss_entries_date_entered_index on ttrss_entries(date_entered);

create table ttrss_user_entries (
	int_id serial not null primary key,
	ref_id integer not null references ttrss_entries(id) ON DELETE CASCADE,
	feed_id int references ttrss_feeds(id) ON DELETE CASCADE not null, 
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	marked boolean not null default false,
	published boolean not null default false,
	last_read timestamp,
	unread boolean not null default true);

-- create index ttrss_user_entries_feed_id_index on ttrss_user_entries(feed_id);
-- create index ttrss_user_entries_owner_uid_index on ttrss_user_entries(owner_uid);
create index ttrss_user_entries_ref_id_index on ttrss_user_entries(ref_id);
create index ttrss_user_entries_feed_id on ttrss_user_entries(feed_id);

create table ttrss_entry_comments (id serial not null primary key,
	ref_id integer not null references ttrss_entries(id) ON DELETE CASCADE,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	private boolean not null default false,
	date_entered timestamp not null);
	
create index ttrss_entry_comments_ref_id_index on ttrss_entry_comments(ref_id);
-- create index ttrss_entry_comments_owner_uid_index on ttrss_entry_comments(owner_uid);

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

insert into ttrss_filter_actions (id,name,description) values (3, 'mark', 
	'Set starred');

insert into ttrss_filter_actions (id,name,description) values (4, 'tag', 
	'Assign tags');

insert into ttrss_filter_actions (id,name,description) values (5, 'publish', 
	'Publish article');

create table ttrss_filters (id serial not null primary key, 	
	owner_uid integer not null references ttrss_users(id) on delete cascade,
	feed_id integer references ttrss_feeds(id) on delete cascade default null,
	filter_type integer not null references ttrss_filter_types(id), 
	reg_exp varchar(250) not null,
	enabled boolean not null default true,
	inverse boolean not null default false,
	action_id integer not null default 1 references ttrss_filter_actions(id) on delete cascade,
	action_param varchar(250) not null default '');

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

insert into ttrss_version values (35);

create table ttrss_enclosures (id serial not null primary key,
	content_url text not null,
	content_type varchar(250) not null,
	title text not null,
	duration text not null,
	post_id integer references ttrss_entries(id) ON DELETE cascade NOT NULL);

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
	access_level integer not null default 0,
	def_value text not null);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_FEED_ICONS', 1, 'true', 'Enable icons in feedlist',3);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('PURGE_OLD_DAYS', 3, '60', 'Purge old posts after this number of days (0 - disables)',1);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('UPDATE_POST_ON_CHECKSUM_CHANGE', 1, 'true', 'Update post on checksum change',1);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ENABLE_LABELS', 1, 'false', 'Enable labels',3,
	'Experimental support for virtual feeds based on user crafted SQL queries. This feature is highly experimental and at this point not user friendly. Use with caution.');
	
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DEFAULT_UPDATE_INTERVAL', 3, '30', 'Default interval between feed updates (in minutes)',1);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DEFAULT_ARTICLE_LIMIT', 3, '0', 'Default article limit',2,
	'Default limit for articles to display, any custom number you like (0 - disables).');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ALLOW_DUPLICATE_POSTS', 1, 'true', 'Allow duplicate posts',1,
	'This option is useful when you are reading several planet-type aggregators with partially colliding userbase. When disabled, it forces same posts from different feeds to appear only once.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('USER_STYLESHEET_URL', 2, '', 'User stylesheet URL',2,
	'Link to user stylesheet to override default style, disabled if empty.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_FEED_CATS', 1, 'false', 'Enable feed categories',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('SHOW_CONTENT_PREVIEW', 1, 'true', 'Show content preview in headlines list',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('SHORT_DATE_FORMAT', 2, 'M d, G:i', 'Short date format',3);
insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('LONG_DATE_FORMAT', 2, 'D, M d Y - G:i', 'Long date format',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('HEADLINES_SMART_DATE', 1, 'true', 'Use more accessible date/time format for headlines',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('COMBINED_DISPLAY_MODE', 1, 'false', 'Combined feed display',2,
	'Display expanded list of feed articles, instead of separate displays for headlines and article content');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('HIDE_READ_FEEDS', 1, 'false', 'Hide feeds with no unread messages',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('OPEN_LINKS_IN_NEW_WINDOW', 1, 'true', 'Open article links in new browser window',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('ON_CATCHUP_SHOW_NEXT_FEED', 1, 'false', 'On catchup show next feed',2,
	'When "Mark as read" button is clicked in toolbar, automatically open next feed with unread articles.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('FEEDS_SORT_BY_UNREAD', 1, 'false', 'Sort feeds by unread articles count',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('EXTENDED_FEEDLIST', 1, 'false', 'Show additional information in feedlist',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('MARK_UNREAD_ON_UPDATE', 1, 'false', 'Set articles as unread on update',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('REVERSE_HEADLINES', 1, 'false', 'Reverse headline order (oldest first)',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('DIGEST_ENABLE', 1, 'false', 'Enable e-mail digest',1,
'This option enables sending daily digest of new (and unread) headlines on your configured e-mail address');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('CONFIRM_FEED_CATCHUP', 1, 'true', 'Confirm marking feed as read',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('CDM_AUTO_CATCHUP', 1, 'false', 'Mark articles as read automatically',2,
'This option enables marking articles as read automatically in combined mode while you scroll article list.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_DEFAULT_VIEW_MODE', 2, 'adaptive', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_DEFAULT_VIEW_LIMIT', 3, '30', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_ACTIVE_TAB', 2, '', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_INFOBOX_DISABLE_OVERLAY', 1, 'false', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('STRIP_UNSAFE_TAGS', 1, 'true', 'Strip unsafe tags from articles', 3,
'Strip all but most common HTML tags when reading articles.');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('BLACKLISTED_TAGS', 2, 'main, generic, misc, uncategorized, blog, blogroll, general, news', 'Blacklisted tags', 3,
'When auto-detecting tags in articles these tags will not be applied (comma-separated list).');

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('ENABLE_SEARCH_TOOLBAR', 1, 'false', 'Enable search toolbar',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_ENABLE_PAGINATION', 2, '', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('_PREFS_PUBLISH_KEY', 2, '', '', 1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('FRESH_ARTICLE_MAX_AGE', 3, '24', 'Maximum age of fresh articles (in hours)',2);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('DIGEST_CATCHUP', 1, 'false', 'Mark articles in e-mail digest as read',1);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('CDM_EXPANDED', 1, 'true', 'Automatically expand articles in combined mode',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('PURGE_UNREAD_ARTICLES', 1, 'true', 'Purge unread articles',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('HIDE_READ_SHOWS_SPECIAL', 1, 'true', 'Show special feeds when hiding read feeds',3);

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('HIDE_FEEDLIST', 1, 'false', 'Hide feedlist',2, 'This option hides feedlist and allows it to be toggled on the fly, useful for small screens.');

create table ttrss_user_prefs (
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	pref_name varchar(250) not null references ttrss_prefs(pref_name) ON DELETE CASCADE,
	value text not null);

create index ttrss_user_prefs_owner_uid_index on ttrss_user_prefs(owner_uid);
-- create index ttrss_user_prefs_value_index on ttrss_user_prefs(value);

create table ttrss_scheduled_updates (id serial not null primary key,
	owner_uid integer not null references ttrss_users(id) ON DELETE CASCADE,
	feed_id integer default null references ttrss_feeds(id) ON DELETE CASCADE,
	entered timestamp not null default NOW());

create table ttrss_sessions (id varchar(250) unique not null primary key,
	data text,	
	expire integer not null);

create index ttrss_sessions_expire_index on ttrss_sessions(expire);

create function SUBSTRING_FOR_DATE(timestamp, int, int) RETURNS text AS 'SELECT SUBSTRING(CAST($1 AS text), $2, $3)' LANGUAGE 'sql';

commit;
