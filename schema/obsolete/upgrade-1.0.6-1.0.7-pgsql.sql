begin;

alter table ttrss_feeds add column last_error text;
alter table ttrss_feeds add column site_url varchar(250);
alter table ttrss_feeds add column update_interval integer;

update ttrss_feeds set last_error = '';
update ttrss_feeds set site_url = '';
update ttrss_feeds set update_interval = 0;

alter table ttrss_feeds alter column last_error set not null;
alter table ttrss_feeds alter column last_error set default '';

alter table ttrss_feeds alter column site_url set not null;
alter table ttrss_feeds alter column site_url set default '';

alter table ttrss_feeds alter column update_interval set not null;
alter table ttrss_feeds alter column update_interval set default 0;

create table ttrss_version (schema_version int not null);

insert into ttrss_version values (1);

commit;
