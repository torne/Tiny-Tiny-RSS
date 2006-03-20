begin;

alter table ttrss_feeds add column rtl_content boolean;

update ttrss_feeds set rtl_content = false;

alter table ttrss_feeds alter column rtl_content set not null;
alter table ttrss_feeds alter column rtl_content set default false;

update ttrss_version set schema_version = 7;

commit;
