BEGIN;

ALTER TABLE ttrss_enclosures DROP INDEX post_id;
ALTER TABLE ttrss_entries DROP INDEX ttrss_entries_guid_index;
ALTER TABLE ttrss_feeds DROP INDEX owner_uid;
ALTER TABLE ttrss_feeds DROP INDEX cat_id;
ALTER TABLE ttrss_prefs DROP INDEX ttrss_prefs_pref_name_idx;
ALTER TABLE ttrss_sessions DROP INDEX id_2;
ALTER TABLE ttrss_sessions DROP INDEX id;
ALTER TABLE ttrss_user_entries DROP INDEX ref_id;
ALTER TABLE ttrss_user_entries DROP INDEX owner_uid;
ALTER TABLE ttrss_user_entries DROP INDEX feed_id;
ALTER TABLE ttrss_user_prefs DROP INDEX pref_name;
ALTER TABLE ttrss_user_prefs DROP INDEX owner_uid;

UPDATE ttrss_version SET schema_version = 127;

COMMIT;
