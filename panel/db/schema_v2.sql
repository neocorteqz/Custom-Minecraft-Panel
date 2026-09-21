-- ApexNode Panel v2 schema additions
USE apexnode;

-- Eggs (game templates)
CREATE TABLE IF NOT EXISTS eggs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game ENUM('minecraft-java','minecraft-bedrock','cs2','rust') NOT NULL,
    name VARCHAR(80) NOT NULL,
    tagline VARCHAR(140) NOT NULL,
    description TEXT NOT NULL,
    start_command VARCHAR(255) NOT NULL,
    docker_image VARCHAR(140) DEFAULT NULL,
    default_files JSON DEFAULT NULL,
    default_env JSON DEFAULT NULL,
    author VARCHAR(80) DEFAULT 'ApexNode Team',
    downloads INT DEFAULT 0,
    featured TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add egg_id to servers if missing
ALTER TABLE servers ADD COLUMN IF NOT EXISTS egg_id INT DEFAULT NULL AFTER game;
ALTER TABLE servers ADD COLUMN IF NOT EXISTS work_dir VARCHAR(255) DEFAULT NULL AFTER egg_id;

-- Backups
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    path VARCHAR(500) NOT NULL,
    size_bytes BIGINT DEFAULT 0,
    storage ENUM('local','s3') DEFAULT 'local',
    remote_url VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','running','completed','failed','restored') DEFAULT 'pending',
    error TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    interval_minutes INT NOT NULL DEFAULT 1440,
    retention INT NOT NULL DEFAULT 7,
    storage ENUM('local','s3') DEFAULT 'local',
    s3_bucket VARCHAR(140) DEFAULT NULL,
    s3_endpoint VARCHAR(255) DEFAULT NULL,
    s3_access_key VARCHAR(140) DEFAULT NULL,
    s3_secret_key VARCHAR(255) DEFAULT NULL,
    enabled TINYINT(1) DEFAULT 1,
    last_run DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_server (server_id),
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
