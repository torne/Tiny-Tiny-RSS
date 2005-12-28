begin;

alter table ttrss_feeds add column parent_feed integer;
alter table ttrss_feeds add foreign key (parent_feed) references ttrss_feeds(id) on delete set null;

commit;
