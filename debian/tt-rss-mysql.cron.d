# /etc/cron.d/tt-rss-mysql: crontab fragment for tt-rss-mysql
#  This update feeds for tiny tiny RSS every 20min 

12,42 *     * * *     www-data	 /usr/bin/wget --output-document=/dev/null --quiet http://localhost/tt-rss/backend.php?op=globalUpdateFeeds&daemon=1
