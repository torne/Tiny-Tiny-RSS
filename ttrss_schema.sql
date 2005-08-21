drop table ttrss_entries;
drop table ttrss_feeds;

create table ttrss_feeds (id serial not null primary key,
	title varchar(200) not null unique, 
	feed_url varchar(250) unique not null, 
	last_updated timestamp default null);

insert into ttrss_feeds (id,title,feed_url) values (0, 'Art.Gnome.Org Releases', 'http://art.gnome.org/backend.php');
insert into ttrss_feeds (id,title,feed_url) values (1, 'Footnotes', 'http://gnomedesktop.org/node/feed');
insert into ttrss_feeds (id,title,feed_url) values (2, 'Freedesktop.org', 'http://planet.freedesktop.org/rss20.xml');
insert into ttrss_feeds (id,title,feed_url) values (3, 'Planet Debian', 'http://planet.debian.org/rss20.xml');
insert into ttrss_feeds (id,title,feed_url) values (4, 'Planet Ubuntu', 'http://planet.ubuntulinux.org/rss20.xml');
insert into ttrss_feeds (id,title,feed_url) values (5, 'Planet GNOME', 'http://planet.gnome.org/rss20.xml');
insert into ttrss_feeds (id,title,feed_url) values (6, 'Monologue', 'http://www.go-mono.com/monologue/index.rss');

create table ttrss_entries (id serial not null primary key, 
	feed_id int references ttrss_feeds(id), 
	entry_time timestamp not null, 
	headline varchar(250) not null, 
	guid varchar(300) not null unique, 
	link varchar(300) not null, 
	content text not null
	unread boolean default true);
	
