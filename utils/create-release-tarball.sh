if [ -z $1 ]; then
	echo usage: $0 VERSION
	exit 1
fi

git archive --format=tar --prefix=tt-rss-$1/ $1 | gzip >tt-rss-$1.tar.gz
