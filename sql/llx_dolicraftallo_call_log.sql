-- Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_dolicraftallo_call_log (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    allo_call_id VARCHAR(100) NOT NULL,
    fk_soc INTEGER DEFAULT 0,
    fk_socpeople INTEGER DEFAULT 0,
    from_number VARCHAR(30),
    to_number VARCHAR(30),
    call_type VARCHAR(20) NOT NULL DEFAULT 'INBOUND',
    call_result VARCHAR(20) DEFAULT 'ANSWERED',
    duration_minutes DOUBLE DEFAULT 0,
    summary TEXT,
    recording_url VARCHAR(500),
    tag VARCHAR(100),
    call_date DATETIME NOT NULL,
    datec DATETIME NOT NULL,
    INDEX idx_call_entity (entity),
    INDEX idx_call_soc (fk_soc),
    INDEX idx_call_date (call_date),
    UNIQUE KEY uk_allo_call (entity, allo_call_id)
) ENGINE=InnoDB;
