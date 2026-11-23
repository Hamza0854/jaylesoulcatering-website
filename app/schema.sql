-- Chef Jay LeSoul Catering — testimonials database (MySQL 5.7+ / MariaDB 10.3+)
-- Run automatically by /install. No semicolons inside statements, please.

CREATE TABLE IF NOT EXISTS users (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(80)  NOT NULL,
  email             VARCHAR(190) NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  status            ENUM('active','blocked') NOT NULL DEFAULT 'active',
  session_version   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL,
  last_login_at     DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(80)  NOT NULL,
  email            VARCHAR(190) NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  totp_secret      VARCHAR(64)  NULL,
  totp_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
  totp_last_step   BIGINT       NULL,
  recovery_codes   TEXT         NULL,
  status           ENUM('active','disabled') NOT NULL DEFAULT 'active',
  session_version  INT UNSIGNED NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL,
  last_login_at    DATETIME NULL,
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_tokens (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(16)  NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_token (token_hash),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NULL,
  source          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  author_name     VARCHAR(80)  NOT NULL,
  author_location VARCHAR(80)  NULL,
  event_type      VARCHAR(40)  NULL,
  rating          TINYINT UNSIGNED NULL,
  title           VARCHAR(120) NULL,
  body            TEXT NULL,
  kind            ENUM('text','photo','video') NOT NULL DEFAULT 'text',
  embed_provider  VARCHAR(16)  NULL,
  embed_url       VARCHAR(500) NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  featured        TINYINT(1)   NOT NULL DEFAULT 0,
  admin_note      VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL,
  reviewed_at     DATETIME NULL,
  reviewed_by     INT UNSIGNED NULL,
  KEY idx_public (status, kind, featured, created_at),
  KEY idx_user (user_id),
  CONSTRAINT fk_testimonials_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonial_media (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  testimonial_id  INT UNSIGNED NOT NULL,
  type            ENUM('image','video') NOT NULL,
  path            VARCHAR(255) NOT NULL,
  mime            VARCHAR(60)  NOT NULL,
  size            INT UNSIGNED NOT NULL DEFAULT 0,
  sort            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_media_t (testimonial_id),
  CONSTRAINT fk_media_testimonial FOREIGN KEY (testimonial_id) REFERENCES testimonials(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  rkey          CHAR(64)     NOT NULL PRIMARY KEY,
  hits          INT UNSIGNED NOT NULL,
  window_start  INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

CREATE TABLE IF NOT EXISTS audit_log (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor       VARCHAR(12)  NOT NULL,
  actor_id    INT UNSIGNED NULL,
  action      VARCHAR(60)  NOT NULL,
  detail      VARCHAR(255) NULL,
  ip          VARCHAR(45)  NOT NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
