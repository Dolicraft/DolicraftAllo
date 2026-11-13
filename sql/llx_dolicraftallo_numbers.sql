-- Copyright (C) 2026 Dolicraft <contact@dolicraft.com>
-- License: GPL v3+

CREATE TABLE llx_dolicraftallo_numbers (
    rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER DEFAULT 1 NOT NULL,
    allo_number VARCHAR(30) NOT NULL,
    label VARCHAR(100) DEFAULT '',
    sender_id VARCHAR(20) DEFAULT '',
    fk_user INTEGER DEFAULT 0,
    is_default TINYINT DEFAULT 0,
    webhook_registered TINYINT DEFAULT 0,
    active TINYINT DEFAULT 1,
    datec DATETIME NOT NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_numbers_entity (entity),
    UNIQUE KEY uk_allo_number (entity, allo_number)
) ENGINE=InnoDB;
