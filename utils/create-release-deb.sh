#!/bin/sh -ei

if [ -z $1 ]; then
	echo usage: $0 VERSION
	exit 1
fi

git clone . dpkg-tmp/tt-rss
cd dpkg-tmp/tt-rss
git co $1

debuild -i -us -uc

cd ..

reprepro -b /var/www/apt include unstable tt-rss*_i386.changes

#cd ..
#rm -rf dpkg-tmp
