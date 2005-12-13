begin;

alter table ttrss_entries add column num_comments integer;

update ttrss_entries set num_comments = 0;

alter table ttrss_entries change num_comments num_comments integer not null;
alter table ttrss_entries alter column num_comments set default 0;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('COMBINED_DISPLAY_MODE', 1, 'false', 'Combined feed display',2,
	'Display expanded list of feed articles, instead of separate displays for headlines and article content');

alter table ttrss_feed_categories add column collapsed bool;

update ttrss_feed_categories set collapsed = false;

alter table ttrss_feed_categories change collapsed collapsed bool not null;
alter table ttrss_feed_categories alter column collapsed set default 0;

commit;
