begin;

alter table ttrss_feeds add column parent_feed integer;
alter table ttrss_feeds add foreign key (parent_feed) references ttrss_feeds(id) on delete set null;

alter table ttrss_feeds add column private boolean;

update ttrss_feeds set private = false;

alter table ttrss_feeds alter column private set not null;
alter table ttrss_feeds alter column private set default false;

update ttrss_version set schema_version = 4;

commit;
