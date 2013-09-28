#!/bin/sh

private_notify="/home/mercurial/tmp/notify.tmp"

# Test for debian systems
if [ -e /etc/debian_version ]; then
	reload_cmd="sudo /etc/init.d/apache2 reload"
else
	reload_cmd="/usr/sbin/apachectl -k graceful"
fi
if [ -e $private_notify ]; then
    rm -f $private_notify
    $reload_cmd
fi
  
