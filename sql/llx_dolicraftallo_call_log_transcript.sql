-- Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_dolicraftallo_call_log ADD COLUMN transcript TEXT DEFAULT NULL AFTER recording_url;
