begin;

alter table ttrss_feeds add column last_error text;
alter table ttrss_feeds add column site_url varchar(250) not null default '';

update ttrss_feeds set last_error = '';
update ttrss_feeds set site_url = '';

alter table ttrss_feeds alter column last_error set not null;
alter table ttrss_feeds alter column last_error set default '';

alter table ttrss_feeds alter column site_url set not null;
alter table ttrss_feeds alter column site_url set default '';

create table ttrss_version (schema_version int not null);

insert into ttrss_version values (1);

commit;
