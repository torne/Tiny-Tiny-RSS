# /etc/cron.d/tt-rss-pgsql: crontab fragment for tt-rss-pgsql
#  This update feeds for tiny tiny RSS every 20min 

12,42 *     * * *     www-data  /usr/bin/wget --output-document=/dev/null --quiet http://localhost/tt-rss/public.php?op=globalUpdateFeeds&daemon=1
