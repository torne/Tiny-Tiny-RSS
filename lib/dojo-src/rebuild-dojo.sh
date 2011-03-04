#!/bin/sh

# This script rebuilds customized layer of Dojo for tt-rss
# Place unpacked Dojo source release in this directory and run this script.
# It will automatically replace previous build of Dojo in ../dojo

# Dojo requires Java runtime to build. Further information on rebuilding Dojo
# is available here: http://dojotoolkit.org/reference-guide/build/index.html

if [ -d util/buildscripts/ ]; then
	pushd util/buildscripts
	./build.sh profileFile=../../profile.js action=clean,release version=1.5.0 releaseName=
	popd

	if [ -d release/dojo ]; then
		rm -rf ../dojo
		cp -r release/dojo ..
	else
		echo $0: ERROR: Dojo build seems to have failed.
	fi
else
	echo $0: ERROR: Please unpack Dojo source release into current directory.
fi
