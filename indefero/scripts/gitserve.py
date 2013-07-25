#!/usr/bin/env python
# -*- coding: utf-8 -*-
#
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008-2011 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

import os
import sys
import subprocess

SCRIPTDIR = os.path.abspath(__file__).rsplit(os.path.sep, 1)[0]
GITSERVEPHP = '%s/gitserve.php' % SCRIPTDIR
process = subprocess.Popen(['php', GITSERVEPHP, sys.argv[1]],
                stdout=subprocess.PIPE, stderr=subprocess.PIPE)
output = str.encode("\n").join(process.communicate()).strip()
status = process.wait()

if status == 0:
    os.execvp('git', ['git', 'shell', '-c', output.strip()])
else:
    sys.stderr.write("%s\n" % output.strip())
sys.exit(1)
