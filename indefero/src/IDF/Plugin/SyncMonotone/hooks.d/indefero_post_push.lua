-- ***** BEGIN LICENSE BLOCK *****
-- This file is part of InDefero, an open source project management application.
-- Copyright (C) 2008-2011 Céondo Ltd and contributors.
--
-- InDefero is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- InDefero is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
--
-- ***** END LICENSE BLOCK *****

--
-- let IDF know of new arriving revisions to fill its timeline
--
_idf_revs = {}
push_hook_functions(
   {
      start =
	 function (session_id)
	    _idf_revs[session_id] = {}
	    return "continue",nil
	 end,
      revision_received =
	 function (new_id, revision, certs, session_id)
	    table.insert(_idf_revs[session_id], new_id)
	    return "continue",nil
	 end,
      ["end"] =
	 function (session_id, ...)
	    if table.getn(_idf_revs[session_id]) == 0 then
	       return "continue",nil
	    end

	    local pin,pout,pid = spawn_pipe(IDF_push_script, IDF_project);
	    if pid == -1 then
	       print("could not execute " .. IDF_push_script)
	       return "continue",nil
	    end

	    for _,r in ipairs(_idf_revs[session_id]) do
	       pin:write(r .. "\n")
	    end
	    pin:close()

	    wait(pid)
	    return "continue",nil
	 end
   })

