-- Etowah Construction Group website schema (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) NOT NULL,
  v TEXT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "key" is a reserved word in MySQL, so the page key column is named page_key.
CREATE TABLE IF NOT EXISTS pages (
  page_key VARCHAR(40) NOT NULL,
  meta_title VARCHAR(200) NOT NULL DEFAULT '',
  meta_description VARCHAR(400) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page_key VARCHAR(40) NOT NULL,
  slot VARCHAR(40) NOT NULL,
  label VARCHAR(120) NOT NULL DEFAULT '',
  path VARCHAR(255) NOT NULL,
  alt VARCHAR(255) NOT NULL DEFAULT '',
  width INT UNSIGNED NOT NULL DEFAULT 0,
  height INT UNSIGNED NOT NULL DEFAULT 0,
  is_placeholder TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_page_slot (page_key, slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  status ENUM('current','completed') NOT NULL DEFAULT 'completed',
  client_name VARCHAR(200) NULL,
  location VARCHAR(120) NULL,
  market ENUM('municipal','commercial','residential','roadway') NOT NULL,
  primary_service VARCHAR(40) NULL,
  summary VARCHAR(300) NOT NULL DEFAULT '',
  description TEXT NULL,
  image_path VARCHAR(255) NOT NULL DEFAULT '',
  image_alt VARCHAR(255) NOT NULL DEFAULT '',
  image_width INT UNSIGNED NOT NULL DEFAULT 0,
  image_height INT UNSIGNED NOT NULL DEFAULT 0,
  is_placeholder_image TINYINT(1) NOT NULL DEFAULT 1,
  home_position ENUM('none','card1','card2','banner') NOT NULL DEFAULT 'none',
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projects_slug (slug),
  KEY idx_projects_list (published, status, sort_order),
  KEY idx_projects_market (market),
  KEY idx_projects_home (home_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- card_title / card_summary are optional per-service-page overrides (NULL = use the project's own title/summary).
CREATE TABLE IF NOT EXISTS project_services (
  project_id INT UNSIGNED NOT NULL,
  service_key VARCHAR(40) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  card_title VARCHAR(200) NULL,
  card_summary VARCHAR(300) NULL,
  PRIMARY KEY (project_id, service_key),
  KEY idx_ps_service (service_key, sort_order),
  CONSTRAINT fk_ps_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  location VARCHAR(120) NOT NULL DEFAULT '',
  employment_type VARCHAR(40) NOT NULL DEFAULT '',
  description TEXT NULL,
  status ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jobs_slug (slug),
  KEY idx_jobs_status (status, posted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  message TEXT NULL,
  resume_file VARCHAR(64) NOT NULL,
  resume_original_name VARCHAR(255) NOT NULL DEFAULT '',
  resume_mime VARCHAR(100) NOT NULL DEFAULT '',
  resume_size INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('new','reviewing','interview','hired','rejected') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  ip_hash CHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_app_job (job_id),
  KEY idx_app_status (status, created_at),
  CONSTRAINT fk_app_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  company VARCHAR(160) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  project_type VARCHAR(60) NOT NULL DEFAULT '',
  project_location VARCHAR(160) NOT NULL DEFAULT '',
  message TEXT NOT NULL,
  status ENUM('new','contacted','quoted','won','lost','spam') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  ip_hash CHAR(64) NOT NULL DEFAULT '',
  contacted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inq_status (status, created_at),
  KEY idx_inq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_lookup (ip_hash, email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  bucket VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rate_lookup (ip_hash, bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NOT NULL DEFAULT '',
  entity_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_created (created_at),
  KEY idx_activity_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
