#!/bin/sh

BASENAME=`basename $0`
TMPFILE="/tmp/$BASENAME-$$.tmp"

cat schema/ttrss_schema_pgsql.sql | grep 'insert.*pref_name' | awk -F\' '{ print $8 }' > $TMPFILE
cat schema/ttrss_schema_pgsql.sql | grep 'insert.*pref_name' | awk -F\' '{ print $6 }' >> $TMPFILE

echo "<?php # This file has been generated at: " `date` > localized_schema.php
echo >> localized_schema.php
cat utils/localized_schema.txt >> localized_schema.php
echo >> localized_schema.php

cat $TMPFILE | grep -v '^$' | sed "s/.*/__('&');/" >> localized_schema.php

echo "?>" >> localized_schema.php

rm $TMPFILE
