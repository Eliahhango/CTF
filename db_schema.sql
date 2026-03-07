-- db_schema.sql
-- Reference schema for Cyber Club DIT CTF platform.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  status ENUM('pending','active','banned') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  INDEX idx_users_status_role (status, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  category VARCHAR(60) NOT NULL,
  points INT NOT NULL,
  initial_points INT NOT NULL DEFAULT 500,
  floor_points INT NOT NULL DEFAULT 100,
  decay_solves INT NOT NULL DEFAULT 50,
  scoring_type ENUM('static','dynamic') NOT NULL DEFAULT 'static',
  description TEXT NOT NULL,
  flag_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  INDEX idx_challenges_active_points (is_active, points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS solves (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  challenge_id INT NOT NULL,
  points_awarded INT NOT NULL,
  solved_at DATETIME NOT NULL,
  UNIQUE KEY uniq_user_challenge (user_id, challenge_id),
  INDEX idx_solves_solved_at (solved_at),
  CONSTRAINT fk_solves_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_solves_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  challenge_id INT NOT NULL,
  submitted_flag VARCHAR(255) NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  ip_addr VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_submissions_user_created (user_id, created_at),
  INDEX idx_submissions_challenge_created (challenge_id, created_at),
  CONSTRAINT fk_submissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_challenge FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(120) NOT NULL,
  ip_addr VARCHAR(45) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_attempt DATETIME NOT NULL,
  locked_until DATETIME DEFAULT NULL,
  UNIQUE KEY uniq_ident_ip (identifier, ip_addr),
  INDEX idx_login_attempts_locked_until (locked_until),
  INDEX idx_login_attempts_last_attempt (last_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- Migration for existing deployments:
-- ALTER TABLE challenges
--   ADD COLUMN initial_points INT NOT NULL DEFAULT 500 AFTER points,
--   ADD COLUMN floor_points INT NOT NULL DEFAULT 100 AFTER initial_points,
--   ADD COLUMN decay_solves INT NOT NULL DEFAULT 50 AFTER floor_points,
--   ADD COLUMN scoring_type ENUM('static','dynamic') NOT NULL DEFAULT 'static' AFTER decay_solves;
--
-- If the old single-hint challenge column still exists:
-- ALTER TABLE challenges DROP COLUMN hint;
