#!/bin/bash
if [ -e /shared ]; then \
	if [ ! -e /shared/xml ]; then \
		unzip -n /var/www/html/teitok/shared.zip -d /shared;
	fi;
	apachectl -D FOREGROUND;\
else
	echo please mount your local volume to /shared 1>&2;
	mkdir /shared;
	unzip -n /var/www/html/teitok/shared.zip -d /shared;
	apachectl -D FOREGROUND;\
fi;