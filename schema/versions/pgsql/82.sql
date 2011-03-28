begin;

insert into ttrss_prefs (pref_name,type_id,def_value,short_desc,section_id,help_text) values('SSL_CERT_SERIAL', 2, '', 'Login with an SSL certificate',3, 'You can login automatically with an active client SSL certificate if you fill in its serial number here.');

update ttrss_version set schema_version = 82;

commit;
