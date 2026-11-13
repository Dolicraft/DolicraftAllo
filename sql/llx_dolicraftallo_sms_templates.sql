-- Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_dolicraftallo_sms_templates (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    label VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    context VARCHAR(50) DEFAULT '',
    active TINYINT DEFAULT 1,
    position INTEGER DEFAULT 0,
    datec DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat INTEGER DEFAULT 0,
    INDEX idx_tpl_entity (entity)
) ENGINE=InnoDB;
