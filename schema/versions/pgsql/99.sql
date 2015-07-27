begin;

alter table ttrss_feeds add column cache_content boolean;
update ttrss_feeds set cache_content = false;
alter table ttrss_feeds alter column cache_content set not null;
alter table ttrss_feeds alter column cache_content set default false;

alter table ttrss_entries add column cached_content text;

update ttrss_version set schema_version = 99;

commit;
