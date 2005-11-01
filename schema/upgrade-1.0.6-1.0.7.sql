alter table ttrss_feeds add column last_error text;

update ttrss_feeds set not last_error = '';

alter table ttrss_feeds alter column last_error set not null;
alter table ttrss_feeds alter column last_error set default '';

