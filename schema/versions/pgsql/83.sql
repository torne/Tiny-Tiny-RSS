begin;

alter table ttrss_feeds add column mark_unread_on_update boolean;
update ttrss_feeds set mark_unread_on_update = false;
alter table ttrss_feeds alter column mark_unread_on_update set not null;
alter table ttrss_feeds alter column mark_unread_on_update set default false;

alter table ttrss_feeds add column strip_images boolean;
update ttrss_feeds set strip_images = false;
alter table ttrss_feeds alter column strip_images set not null;
alter table ttrss_feeds alter column strip_images set default false;

DELETE FROM ttrss_user_prefs WHERE pref_name IN ('HIDE_FEEDLIST', 'SYNC_COUNTERS', 'ENABLE_LABELS', 'ENABLE_SEARCH_TOOLBAR', 'ENABLE_FEED_ICONS', 'ENABLE_OFFLINE_READING', 'EXTENDED_FEEDLIST', 'OPEN_LINKS_IN_NEW_WINDOW', 'ENABLE_FLASH_PLAYER', 'HEADLINES_SMART_DATE', 'MARK_UNREAD_ON_UPDATE');

DELETE FROM ttrss_prefs WHERE pref_name IN ('HIDE_FEEDLIST', 'SYNC_COUNTERS', 'ENABLE_LABELS', 'ENABLE_SEARCH_TOOLBAR', 'ENABLE_FEED_ICONS', 'ENABLE_OFFLINE_READING', 'EXTENDED_FEEDLIST', 'OPEN_LINKS_IN_NEW_WINDOW', 'ENABLE_FLASH_PLAYER', 'HEADLINES_SMART_DATE', 'MARK_UNREAD_ON_UPDATE');

update ttrss_version set schema_version = 83;

commit;
