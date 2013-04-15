begin;

ALTER TABLE `ttrss_feeds` ADD favicon_avg_color VARCHAR(11)

update ttrss_version set schema_version = 117;

commit;
