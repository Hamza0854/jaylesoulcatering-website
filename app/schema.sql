-- Chef Jay LeSoul Catering — testimonials database (MySQL 5.7+ / MariaDB 10.3+)
-- Import in phpMyAdmin (Import tab) or let /install run it. Safe to run twice.
-- No semicolons inside statements, please (the installer splits on them).

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

CREATE TABLE IF NOT EXISTS testimonials (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  author_name     VARCHAR(80)  NOT NULL,
  author_email    VARCHAR(190) NULL,
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
  KEY idx_email (author_email)
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

-- Reviewers the admin has blocked (they can no longer send reviews)
CREATE TABLE IF NOT EXISTS blocked_emails (
  email       VARCHAR(190) NOT NULL PRIMARY KEY,
  reason      VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL
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


-- =========================================================
--  DATA
--  INSERT IGNORE + fixed ids → running this file again never makes duplicates.
-- =========================================================

-- Real video testimonials (published)
INSERT IGNORE INTO testimonials (id, source, author_name, event_type, title, kind, embed_provider, embed_url, status, featured, created_at, reviewed_at) VALUES
  (1, 'admin', 'Private Dining Guest', 'private-dinner', 'A testimony on how a private dining event can be done', 'video', 'facebook', 'https://www.facebook.com/reel/1553656299054690/', 'approved', 1, '2026-11-20 18:00:00', '2026-11-20 18:00:00'),
  (2, 'admin', 'Happy Customer', NULL, NULL, 'video', 'youtube', 'https://www.youtube.com/watch?v=Of20yQSj1rA', 'approved', 0, '2026-11-20 17:59:00', '2026-11-20 17:59:00'),
  (3, 'admin', 'Happy Customer', NULL, NULL, 'video', NULL, NULL, 'approved', 0, '2026-11-20 17:58:00', '2026-11-20 17:58:00'),
  (4, 'admin', 'Happy Customer', NULL, NULL, 'video', NULL, NULL, 'approved', 0, '2026-11-20 17:57:00', '2026-11-20 17:57:00'),
  (5, 'admin', 'Happy Customer', NULL, NULL, 'video', NULL, NULL, 'approved', 0, '2026-11-20 17:56:00', '2026-11-20 17:56:00'),
  (6, 'admin', 'Happy Customer', NULL, NULL, 'video', NULL, NULL, 'approved', 0, '2026-11-20 17:55:00', '2026-11-20 17:55:00'),
  (7, 'admin', 'Happy Customer', NULL, NULL, 'video', NULL, NULL, 'approved', 0, '2026-11-20 17:54:00', '2026-11-20 17:54:00');

INSERT IGNORE INTO testimonial_media (id, testimonial_id, type, path, mime, size, sort) VALUES
  (1, 3, 'video', 'assets/images/testimonial-video-36.mp4', 'video/mp4', 7746034, 0),
  (2, 4, 'video', 'assets/images/testimonial-video-35.mp4', 'video/mp4', 9369010, 0),
  (3, 5, 'video', 'assets/images/testimonial-video-34.mp4', 'video/mp4', 10599794, 0),
  (4, 6, 'video', 'assets/images/testimonial-video-33.mp4', 'video/mp4', 14453360, 0),
  (5, 7, 'video', 'assets/images/testimonial-video-32.mp4', 'video/mp4', 5561996, 0);

-- DEMO reviews for testing the admin panel. They are WAITING FOR APPROVAL, so the public never sees them.
-- Approve / reject them to try the panel, then delete them (Admin → Reviews → Delete).
-- Do not publish them: made-up reviews on a real business site mislead customers.
INSERT IGNORE INTO testimonials (id, source, author_name, author_email, author_location, event_type, rating, title, body, kind, status, created_at) VALUES
  (101, 'customer', '[DEMO] Tasha W.',   'demo1@example.com', 'Cleveland',      'family',         5, '[DEMO] Best oxtails at the party', 'DEMO REVIEW. Chef Jay catered my mother''s 60th birthday for 40 guests. The oxtails and mac and cheese were gone in minutes and the team was friendly and on time.', 'text', 'pending', '2026-11-21 14:10:00'),
  (102, 'customer', '[DEMO] Marcus J.',  'demo2@example.com', 'Shaker Heights', 'corporate',      5, '[DEMO] Office lunch done right', 'DEMO REVIEW. We ordered drop-off trays for a team lunch. Everything arrived hot, labelled and looking great. Already planning the next order.', 'text', 'pending', '2026-11-21 15:25:00'),
  (103, 'customer', '[DEMO] Renee P.',   'demo3@example.com', 'Euclid',         'private-dinner', 4, '[DEMO] Lovely private dinner', 'DEMO REVIEW. The lamb chops were perfect and Chef Jay explained every course. Only wish there had been a little more dessert.', 'text', 'pending', '2026-11-22 19:40:00'),
  (104, 'customer', '[DEMO] Andre L.',   'demo4@example.com', 'Cleveland',      'seasoning',      5, '[DEMO] Black Crack on everything', 'DEMO REVIEW. Bought three jars of Black Crack. It is great on fries, chicken and fish. Garlicky with a little heat.', 'text', 'pending', '2026-11-22 21:05:00'),
  (105, 'customer', '[DEMO] Gloria S.',  'demo5@example.com', 'Lakewood',       'community',      5, '[DEMO] Cooking in the Hood', 'DEMO REVIEW. Chef Jay and the team served our block party and fed everyone. Great food and great energy for the neighborhood.', 'text', 'pending', '2026-11-23 12:30:00'),
  (106, 'customer', '[DEMO] Kevin D.',   'demo6@example.com', 'Parma',          'catering',       3, '[DEMO] Good food, a bit late', 'DEMO REVIEW. The food tasted great but set-up started about 20 minutes late. Staff were apologetic and everything else went well.', 'text', 'pending', '2026-11-23 16:45:00');
