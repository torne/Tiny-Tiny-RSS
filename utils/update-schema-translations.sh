#!/bin/sh

LC_ALL=C
LANG=C
LANGUAGE=C

BASENAME=`basename $0`
TMPFILE="/tmp/$BASENAME-$$.tmp"
OUTFILE="include/localized_schema.php"

cat schema/ttrss_schema_pgsql.sql | grep 'insert.*pref_name' | awk -F\' '{ print $8 }' > $TMPFILE
cat schema/ttrss_schema_pgsql.sql | grep 'insert.*pref_name' | awk -F\' '{ print $6 }' >> $TMPFILE

echo "<?php # This file has been generated at: " `date` > $OUTFILE
echo >> $OUTFILE
cat utils/localized_schema.txt >> $OUTFILE
echo >> $OUTFILE

cat $TMPFILE | grep -v '^$' | sed "s/.*/__('&');/" >> $OUTFILE

echo "?>" >> $OUTFILE

rm $TMPFILE
