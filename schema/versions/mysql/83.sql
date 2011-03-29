begin;

alter table ttrss_feeds add column mark_unread_on_update boolean;
update ttrss_feeds set mark_unread_on_update = false;
alter table ttrss_feeds change mark_unread_on_update mark_unread_on_update boolean not null;
alter table ttrss_feeds alter column mark_unread_on_update set default false;

alter table ttrss_feeds add column strip_images boolean;
update ttrss_feeds set strip_images = false;
alter table ttrss_feeds change strip_images strip_images boolean not null;
alter table ttrss_feeds alter column strip_images set default false;

alter table ttrss_feeds add column update_on_checksum_change boolean;
update ttrss_feeds set update_on_checksum_change = false;
alter table ttrss_feeds change update_on_checksum_change update_on_checksum_change boolean not null;
alter table ttrss_feeds alter column update_on_checksum_change set default false;

DELETE FROM ttrss_user_prefs WHERE pref_name IN ('HIDE_FEEDLIST', 'SYNC_COUNTERS', 'ENABLE_LABELS', 'ENABLE_SEARCH_TOOLBAR', 'ENABLE_FEED_ICONS', 'ENABLE_OFFLINE_READING', 'EXTENDED_FEEDLIST', 'OPEN_LINKS_IN_NEW_WINDOW', 'ENABLE_FLASH_PLAYER', 'HEADLINES_SMART_DATE', 'MARK_UNREAD_ON_UPDATE', 'UPDATE_POST_ON_CHECKSUM_CHANGE');

DELETE FROM ttrss_prefs WHERE pref_name IN ('HIDE_FEEDLIST', 'SYNC_COUNTERS', 'ENABLE_LABELS', 'ENABLE_SEARCH_TOOLBAR', 'ENABLE_FEED_ICONS', 'ENABLE_OFFLINE_READING', 'EXTENDED_FEEDLIST', 'OPEN_LINKS_IN_NEW_WINDOW', 'ENABLE_FLASH_PLAYER', 'HEADLINES_SMART_DATE', 'MARK_UNREAD_ON_UPDATE', 'UPDATE_POST_ON_CHECKSUM_CHANGE');

update ttrss_version set schema_version = 83;

commit;
