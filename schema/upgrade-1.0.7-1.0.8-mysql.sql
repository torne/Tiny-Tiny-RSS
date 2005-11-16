begin;

alter table ttrss_feeds add column purge_interval integer;

update ttrss_feeds set purge_interval = 0;

alter table ttrss_feeds modify column purge_interval integer not null;
alter table ttrss_feeds alter column purge_interval set default 0;

update ttrss_version set schema_version = 2;

commit;
