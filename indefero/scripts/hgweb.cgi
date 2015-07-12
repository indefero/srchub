#!/usr/bin/env python
#
# An example hgweb CGI script, edit as necessary
# See also http://mercurial.selenic.com/wiki/PublishingRepositories

# Path to repo or hgweb config to serve (see 'hg help hgweb')
# config = "/path/to/repo/or/config"
import os
import sys
config = "/home/www/indefero/scripts/hgweb.config"

# Uncomment and adjust if Mercurial is not installed system-wide
# (consult "installed modules" path from 'hg debuginstall'):
#import sys; sys.path.insert(0, "/path/to/python/lib")

# Uncomment to send python tracebacks to the browser if an error occurs:
#import cgitb; cgitb.enable()

from mercurial import demandimport; demandimport.enable()
from mercurial.hgweb import hgwebdir, wsgicgi
application = hgwebdir(config)
newrepos = []
for repo in application.repos:
    if not os.path.isfile(repo[1] + "/.hide") or repo[0] in os.environ["REQUEST_URI"]:
        newrepos.append(repo)
application.repos = newrepos
wsgicgi.launch(application)
