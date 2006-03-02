alter table ttrss_entries add column author varchar(250);

update ttrss_entries set author = '';

alter table ttrss_entries change author author varchar(250) not null;
alter table ttrss_entries alter column author set default '';

create table ttrss_sessions (int_id integer not null primary key auto_increment,
	id varchar(300) unique not null,
	data text,
	expire integer not null,
	index (id), 
	index (expire)) TYPE=InnoDB;

update ttrss_version set schema_version = 6;

