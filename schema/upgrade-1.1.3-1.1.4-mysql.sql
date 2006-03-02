alter table ttrss_entries add column author varchar(250);

update ttrss_entries set author = '';

alter table ttrss_entries change author author varchar(250) not null;
alter table ttrss_entries alter column author set default '';

create table ttrss_sessions (id varchar(300) unique not null primary key,
	data text,
	expire integer not null,
	ip_address varchar(15) not null default '',
	index (id), 
	index (expire)) TYPE=InnoDB;

update ttrss_version set schema_version = 6;

