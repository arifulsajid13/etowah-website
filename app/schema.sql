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
  seo_title VARCHAR(70) NULL,
  seo_description VARCHAR(170) NULL,
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

-- Optional gallery on each project page: up to 12 photos and 3 videos (enforced in the dashboard and the import).
-- Videos: path = poster image, video_path = MP4, alt = description.
CREATE TABLE IF NOT EXISTS project_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  media_type ENUM('image','video') NOT NULL DEFAULT 'image',
  path VARCHAR(255) NOT NULL,
  video_path VARCHAR(255) NULL,
  title VARCHAR(200) NULL,
  upload_date DATE NULL,
  duration INT UNSIGNED NULL,
  alt VARCHAR(255) NOT NULL DEFAULT '',
  width INT UNSIGNED NOT NULL DEFAULT 0,
  height INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pi_project (project_id, sort_order),
  CONSTRAINT fk_pi_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  location VARCHAR(120) NOT NULL DEFAULT '',
  employment_type VARCHAR(40) NOT NULL DEFAULT '',
  experience_range VARCHAR(10) NULL,
  experience_level VARCHAR(10) NULL,
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

-- Invoices and online payments (v7). Money is stored as integer cents (USD).
-- token: 64 hex characters from random_bytes(32); the only identifier the public pay pages use.
-- status "overdue" is shown, not stored: it is computed from due_date and the balance.
CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number VARCHAR(30) NOT NULL,
  token CHAR(64) NOT NULL,
  customer_name VARCHAR(160) NOT NULL DEFAULT '',
  customer_company VARCHAR(160) NOT NULL DEFAULT '',
  customer_email VARCHAR(190) NOT NULL DEFAULT '',
  customer_phone VARCHAR(40) NOT NULL DEFAULT '',
  billing_address TEXT NULL,
  project_ref VARCHAR(200) NOT NULL DEFAULT '',
  issue_date DATE NOT NULL,
  due_date DATE NOT NULL,
  terms VARCHAR(40) NOT NULL DEFAULT '',
  notes TEXT NULL,
  subtotal_cents BIGINT NOT NULL DEFAULT 0,
  tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  tax_cents BIGINT NOT NULL DEFAULT 0,
  total_cents BIGINT NOT NULL DEFAULT 0,
  amount_paid_cents BIGINT NOT NULL DEFAULT 0,
  status ENUM('draft','sent','viewed','processing','partially_paid','paid','overdue','void','refunded') NOT NULL DEFAULT 'draft',
  sent_at DATETIME NULL,
  viewed_at DATETIME NULL,
  paid_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_number (number),
  UNIQUE KEY uq_invoices_token (token),
  KEY idx_invoices_status (status, due_date),
  KEY idx_invoices_email (customer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  description VARCHAR(500) NOT NULL DEFAULT '',
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit_price_cents BIGINT NOT NULL DEFAULT 0,
  line_total_cents BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_items_invoice (invoice_id, sort),
  CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A refund is its own row with a negative amount and status "refunded".
-- gateway_ref: Checkout Session id (Stripe) or our attempt id (Authorize.net); gateway_txn: PaymentIntent / transaction id.
-- A "pending" row without gateway_txn is a checkout the customer opened but never finished.
-- completed_at: when the money arrived (or the date entered for a manual payment); used for "Paid this month".
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED NOT NULL,
  gateway ENUM('stripe','authorizenet','manual') NOT NULL,
  method ENUM('card','ach','check','cash','wire','other') NOT NULL DEFAULT 'other',
  amount_cents BIGINT NOT NULL DEFAULT 0,
  status ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  gateway_ref VARCHAR(255) NULL,
  gateway_txn VARCHAR(255) NULL,
  reference_note VARCHAR(255) NOT NULL DEFAULT '',
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_payments_invoice (invoice_id, created_at),
  KEY idx_payments_ref (gateway, gateway_ref),
  KEY idx_payments_txn (gateway, gateway_txn),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook idempotency: an event that already has processed_at is ignored.
CREATE TABLE IF NOT EXISTS gateway_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gateway VARCHAR(20) NOT NULL,
  event_id VARCHAR(255) NOT NULL,
  type VARCHAR(100) NOT NULL DEFAULT '',
  invoice_id INT UNSIGNED NULL,
  payload MEDIUMTEXT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gateway_event (event_id),
  KEY idx_gateway_events_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automatic reminders already sent (cron), so each one goes out once: kind = "before-3", "overdue-7", …
CREATE TABLE IF NOT EXISTS invoice_reminders (
  invoice_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (invoice_id, kind),
  CONSTRAINT fk_reminders_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
