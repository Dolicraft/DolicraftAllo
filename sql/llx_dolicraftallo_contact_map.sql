-- Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_dolicraftallo_contact_map (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    allo_contact_id VARCHAR(100) NOT NULL,
    fk_soc INTEGER DEFAULT 0,
    fk_socpeople INTEGER DEFAULT 0,
    phone_number VARCHAR(30),
    allo_name VARCHAR(200) DEFAULT '',
    last_sync DATETIME,
    datec DATETIME NOT NULL,
    INDEX idx_map_entity (entity),
    INDEX idx_map_allo (allo_contact_id),
    UNIQUE KEY uk_allo_contact (entity, allo_contact_id)
) ENGINE=InnoDB;
