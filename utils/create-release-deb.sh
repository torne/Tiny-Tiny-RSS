#!/bin/sh -ei

if [ -z $1 ]; then
	echo usage: $0 VERSION
	exit 1
fi

git clone . dpkg-tmp/tt-rss
cd dpkg-tmp/tt-rss
git checkout $1

debuild -i -us -uc
#debuild -i -us -uc -b -aamd64

cd ..

if [ ! -z "$DEPLOY_DEBS" ]; then
	reprepro -b /var/www/apt include unstable tt-rss*_i386.changes
	#reprepro -b /var/www/apt include unstable tt-rss*_amd64.changes
fi

#cd ..
#rm -rf dpkg-tmp
