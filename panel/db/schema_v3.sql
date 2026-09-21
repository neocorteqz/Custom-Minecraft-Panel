-- ApexNode Panel v3 — Mod loader / modpack chooser
USE apexnode;

CREATE TABLE IF NOT EXISTS mod_loaders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game ENUM('minecraft-java','minecraft-bedrock','cs2','rust') NOT NULL,
    slug VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    category ENUM('vanilla','loader','modpack_source','mod_framework') NOT NULL,
    tagline VARCHAR(140) NOT NULL,
    description TEXT NOT NULL,
    install_cmd VARCHAR(255) DEFAULT NULL,
    logo_char VARCHAR(6) DEFAULT '◈',
    accent_color VARCHAR(9) DEFAULT '#00F0FF',
    requires_pack_id TINYINT(1) DEFAULT 0,
    popular TINYINT(1) DEFAULT 0,
    UNIQUE KEY uniq_game_slug (game, slug)
) ENGINE=InnoDB;

ALTER TABLE servers ADD COLUMN IF NOT EXISTS loader_id INT DEFAULT NULL AFTER egg_id;
ALTER TABLE servers ADD COLUMN IF NOT EXISTS modpack_ref VARCHAR(120) DEFAULT NULL AFTER loader_id;
