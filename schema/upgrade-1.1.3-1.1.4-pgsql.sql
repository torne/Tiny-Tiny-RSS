begin;

alter table ttrss_entries add column author varchar(250);

update ttrss_entries set author = '';

alter table ttrss_entries alter column author set not null;
alter table ttrss_entries alter column author set default '';

update ttrss_version set schema_version = 6;

commit;
