-- ApexNode Panel v4 — Background job queue
USE apexnode;

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kind VARCHAR(32) NOT NULL,
    target_id INT DEFAULT NULL,
    target_kind VARCHAR(32) DEFAULT NULL,
    status ENUM('queued','running','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
    progress INT NOT NULL DEFAULT 0,
    total INT NOT NULL DEFAULT 0,
    message VARCHAR(255) DEFAULT '',
    error TEXT DEFAULT NULL,
    payload JSON DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    INDEX idx_target (target_kind, target_id),
    INDEX idx_status (status, created_at)
) ENGINE=InnoDB;
