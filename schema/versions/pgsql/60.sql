begin;

alter table ttrss_user_entries alter column feed_id drop not null;

alter table ttrss_user_entries add column orig_feed_id integer;
update ttrss_user_entries set orig_feed_id = NULL;

alter table ttrss_user_entries add constraint "$4" FOREIGN KEY (orig_feed_id) REFERENCES ttrss_feeds(id) ON DELETE SET NULL;

update ttrss_version set schema_version = 60;

commit;
