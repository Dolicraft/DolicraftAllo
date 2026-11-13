-- Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_dolicraftallo_sms_log (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    fk_soc INTEGER DEFAULT 0,
    fk_socpeople INTEGER DEFAULT 0,
    from_number VARCHAR(30),
    to_number VARCHAR(30),
    sms_type VARCHAR(20) NOT NULL DEFAULT 'OUTBOUND',
    content TEXT,
    status VARCHAR(20) DEFAULT 'sent',
    related_object_type VARCHAR(50),
    related_object_id INTEGER DEFAULT 0,
    datec DATETIME NOT NULL,
    INDEX idx_sms_entity (entity),
    INDEX idx_sms_soc (fk_soc),
    INDEX idx_sms_date (datec)
) ENGINE=InnoDB;
