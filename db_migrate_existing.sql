-- db_migrate_existing.sql
-- Run this on existing installations to add newer CCD features.

ALTER TABLE challenges
  ADD COLUMN IF NOT EXISTS initial_points INT NOT NULL DEFAULT 500 AFTER points,
  ADD COLUMN IF NOT EXISTS floor_points INT NOT NULL DEFAULT 100 AFTER initial_points,
  ADD COLUMN IF NOT EXISTS decay_solves INT NOT NULL DEFAULT 50 AFTER floor_points,
  ADD COLUMN IF NOT EXISTS scoring_type ENUM('static','dynamic') NOT NULL DEFAULT 'static' AFTER decay_solves,
  ADD COLUMN IF NOT EXISTS max_attempts INT NOT NULL DEFAULT 0 AFTER scoring_type,
  ADD COLUMN IF NOT EXISTS flag_type ENUM('static','regex','case_insensitive') NOT NULL DEFAULT 'static' AFTER flag_hash,
  ADD COLUMN IF NOT EXISTS flag_plaintext VARCHAR(600) DEFAULT NULL AFTER flag_type,
  ADD COLUMN IF NOT EXISTS prerequisite_id INT DEFAULT NULL AFTER flag_plaintext;

ALTER TABLE submissions
  ADD COLUMN IF NOT EXISTS flagged TINYINT(1) NOT NULL DEFAULT 0 AFTER is_correct;

ALTER TABLE challenges ADD INDEX idx_challenges_active_points (is_active, points);
ALTER TABLE challenges ADD INDEX idx_ch_prereq (prerequisite_id);
ALTER TABLE submissions ADD INDEX idx_submissions_user_created (user_id, created_at);
ALTER TABLE submissions ADD INDEX idx_submissions_challenge_created (challenge_id, created_at);

CREATE TABLE IF NOT EXISTS challenge_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_size INT NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  uploaded_at DATETIME NOT NULL,
  INDEX idx_challenge_files_challenge (challenge_id),
  CONSTRAINT fk_cf_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hints (
  id INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id INT NOT NULL,
  content TEXT NOT NULL,
  cost INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  INDEX idx_hints_challenge_sort (challenge_id, sort_order, id),
  CONSTRAINT fk_hint_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hint_unlocks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  hint_id INT NOT NULL,
  points_spent INT NOT NULL DEFAULT 0,
  unlocked_at DATETIME NOT NULL,
  UNIQUE KEY uniq_user_hint (user_id, hint_id),
  INDEX idx_hint_unlocks_user_unlocked (user_id, unlocked_at),
  CONSTRAINT fk_hu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_hu_hint FOREIGN KEY (hint_id) REFERENCES hints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS hint_deductions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  hint_id INT NOT NULL,
  points_deducted INT NOT NULL,
  deducted_at DATETIME NOT NULL,
  INDEX idx_hint_deductions_user_deducted (user_id, deducted_at),
  CONSTRAINT fk_hd_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_announcements_pin_date (is_pinned, created_at),
  CONSTRAINT fk_ann_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pr_token (token_hash),
  INDEX idx_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) NOT NULL,
  target_id INT DEFAULT NULL,
  details TEXT DEFAULT NULL,
  ip_addr VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_aal_created (created_at),
  CONSTRAINT fk_aal_admin FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS cheat_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  challenge_id INT NOT NULL,
  reason ENUM(
    'copied_correct_flag',
    'shared_wrong_flag',
    'speed_solve',
    'rapid_solves',
    'same_ip_solve'
  ) NOT NULL,
  detail TEXT DEFAULT NULL,
  severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  reviewed TINYINT(1) NOT NULL DEFAULT 0,
  reviewed_by INT DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_ca_user (user_id),
  INDEX idx_ca_challenge (challenge_id),
  INDEX idx_ca_reviewed_severity (reviewed, severity, created_at),
  CONSTRAINT fk_ca_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
