#!/bin/sh
TEMPLATE=messages.pot

./utils/update-schema-translations.sh

xgettext -kT_js_decl -kT_sprintf -kT_ngettext:1,2 -k__ -L PHP -o $TEMPLATE *.php help/*.php mobile/*.php include/*.php `find classes -iname '*.php'` `find plugins -iname '*.php'`

xgettext --from-code utf-8 -k__ -L Java -j -o $TEMPLATE js/*.js `find plugins -iname '*.js'`

update_lang() {
	if [ -f $1.po ]; then
		TMPFILE=/tmp/update-translations.$$
	
		msgmerge -o $TMPFILE $1.po $TEMPLATE
		mv $TMPFILE $1.po
		msgfmt --statistics $1.po
		msgfmt -o $1.mo $1.po
	else
		echo "Usage: $0 [-p|<basename>]"
	fi
}

LANGS=`find locale -name 'messages.po'`

for lang in $LANGS; do
	echo Updating $lang...
	PO_BASENAME=`echo $lang | sed s/.po//`
	update_lang $PO_BASENAME
done

#./utils/update-js-translations.sh
