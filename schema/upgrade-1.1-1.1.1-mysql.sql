begin;

alter table ttrss_entries add column num_comments integer;

update ttrss_entries set num_comments = 0;

alter table ttrss_entries change num_comments num_comments integer not null;
alter table ttrss_entries alter column num_comments set default 0;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id) values('COMBINED_DISPLAY_MODE', 1, 'false', 'Combined feed display, no headline/article separation',2);

commit;
