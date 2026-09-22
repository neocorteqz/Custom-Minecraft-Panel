-- ApexNode Panel v5 — job cancellation + installer settings
USE apexnode;

-- Add cancel-request flag for in-progress cancellation
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS cancel_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

-- Panel installation profile (for install.sh output + coexistence with cPanel/Plesk/DirectAdmin)
INSERT IGNORE INTO settings (k, v) VALUES ('panel_port', '3000'),
                                          ('panel_url_path', '/'),
                                          ('coexist_mode', 'standalone');
