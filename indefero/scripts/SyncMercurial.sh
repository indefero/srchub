#!/bin/sh

private_notify="/home/mercurial/tmp/notify.tmp"
# reload_cmd="/usr/sbin/apachectl -k graceful"
reload_cmd="sudo /etc/init.d/apache2 reload"

if [ -e $private_notify ]; then
    rm -f $private_notify
    $reload_cmd
fi
  
