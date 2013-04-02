#!/bin/bash

# This script rebuilds customized layer of Dojo for tt-rss
# Place unpacked Dojo source release in this directory and run this script.
# It will automatically replace previous build of Dojo in ../dojo

# Dojo requires Java runtime to build. Further information on rebuilding Dojo
# is available here: http://dojotoolkit.org/reference-guide/build/index.html

if [ -d util/buildscripts/ ]; then
	rm -rf release/dojo

	pushd util/buildscripts
	./build.sh profile=../../tt-rss action=clean,release optimize=shrinksafe
	popd

	if [ -d release/dojo ]; then
		rm -rf ../dojo ../dijit
		cp -r release/dojo/dojo ..
		cp -r release/dojo/dijit ..

		cd ..

		find dojo -name '*uncompressed*' -exec rm -- {} \;
		find dijit -name '*uncompressed*' -exec rm -- {} \;
	else
		echo $0: ERROR: Dojo build seems to have failed.
	fi
else
	echo $0: ERROR: Please unpack Dojo source release into current directory.
fi
