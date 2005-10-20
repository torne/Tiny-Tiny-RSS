drop table if exists ttrss_tags;
drop table if exists ttrss_entries;
drop table if exists ttrss_feeds;

create table ttrss_feeds (id integer not null auto_increment primary key,
	title varchar(200) not null unique, 
	feed_url varchar(250) unique not null, 
	icon_url varchar(250) not null default '',
	update_interval integer not null default 0,
	last_updated datetime default '') TYPE=InnoDB;

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

