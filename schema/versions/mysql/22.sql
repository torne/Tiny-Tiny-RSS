create index ttrss_entries_date_entered_index on ttrss_entries(date_entered);

update ttrss_version set schema_version = 22;
