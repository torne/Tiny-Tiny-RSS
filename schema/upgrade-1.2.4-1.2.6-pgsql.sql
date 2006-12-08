begin;

alter table ttrss_filters add column action_param varchar(200);

update ttrss_filters set action_param = '';

alter table ttrss_filters alter column action_param set not null;
alter table ttrss_filters alter column action_param set default '';

update ttrss_version set schema_version = 12;

commit;
