begin;

alter table ttrss_entries add column author varchar(250);

update ttrss_entries set author = '';

alter table ttrss_entries alter column author set not null;
alter table ttrss_entries alter column author set default '';

create table ttrss_sessions (id varchar(300) unique not null primary key,
	data text,
	expire integer not null);

create index ttrss_sessions_id_index on ttrss_sessions(id);
create index ttrss_sessions_expire_index on ttrss_sessions(expire);

update ttrss_version set schema_version = 6;

commit;
