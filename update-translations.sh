#!/bin/sh
TEMPLATE=messages.pot

xgettext -kT_ngettext:1,2 -kT_ -k_ -L PHP -o $TEMPLATE *.php modules/*.php

if [ "$1" = "-p" ]; then
	msgfmt --statistics $TEMPLATE
else
	if [ -f $1.po ]; then
		TMPFILE=/tmp/update-translations.$$

		msgmerge -o $TMPFILE $1.po $TEMPLATE
		mv $TMPFILE $1.po
		msgfmt --statistics $1.po
		msgfmt -o $1.mo $1.po
	else
		echo "Usage: $0 [-p|<basename>]"
	fi
fi
