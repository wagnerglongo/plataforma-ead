-- =============================================================
-- Plataforma EAD · schema MySQL 5.7+/8 · MariaDB 10.4+
-- Charset utf8mb4 (acentos e emojis). Todas as datas em horário local
-- da aplicação (APP_TIMEZONE), geradas pelo PHP.
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- Controle de acesso ----------
CREATE TABLE IF NOT EXISTS roles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(60)  NOT NULL,
  slug         VARCHAR(60)  NOT NULL UNIQUE,
  description  VARCHAR(255) NULL,
  is_system    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(60)  NOT NULL UNIQUE,
  name        VARCHAR(120) NOT NULL,
  group_name  VARCHAR(60)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id        INT UNSIGNED NOT NULL,
  permission_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id            INT UNSIGNED NOT NULL,
  name               VARCHAR(120) NOT NULL,
  email              VARCHAR(160) NOT NULL UNIQUE,
  password_hash      VARCHAR(255) NOT NULL,
  cpf                VARCHAR(14)  NULL,
  phone              VARCHAR(20)  NULL,
  city               VARCHAR(80)  NULL,
  bio                VARCHAR(500) NULL,
  avatar             VARCHAR(255) NULL,
  status             ENUM('active','inactive') NOT NULL DEFAULT 'active',
  marketing_opt_in   TINYINT(1)   NOT NULL DEFAULT 0,
  terms_accepted_at  DATETIME     NULL,
  last_login_at      DATETIME     NULL,
  anonymized_at      DATETIME     NULL,
  created_at         DATETIME     NOT NULL,
  updated_at         DATETIME     NOT NULL,
  KEY idx_users_role (role_id),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_pr_token (token_hash),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rl_key      VARCHAR(190) NOT NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_la_key (rl_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Catálogo ----------
CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(90)  NOT NULL UNIQUE,
  created_at  DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id          INT UNSIGNED NULL,
  instructor_id        INT UNSIGNED NULL,
  title                VARCHAR(160) NOT NULL,
  slug                 VARCHAR(180) NOT NULL UNIQUE,
  summary              VARCHAR(300) NOT NULL,
  description          TEXT         NULL,
  learning_outcomes    TEXT         NULL,
  requirements         TEXT         NULL,
  target_audience      TEXT         NULL,
  workload_hours       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  level                ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
  thumbnail            VARCHAR(255) NULL,
  cover_color          VARCHAR(7)   NOT NULL DEFAULT '#1F3A93',
  status               ENUM('draft','published') NOT NULL DEFAULT 'draft',
  featured             TINYINT(1)   NOT NULL DEFAULT 0,
  pass_score           TINYINT UNSIGNED NOT NULL DEFAULT 70,
  certificate_enabled  TINYINT(1)   NOT NULL DEFAULT 1,
  published_at         DATETIME     NULL,
  created_at           DATETIME     NOT NULL,
  updated_at           DATETIME     NOT NULL,
  KEY idx_courses_status (status, featured),
  FULLTEXT KEY ft_courses (title, summary, description),
  CONSTRAINT fk_courses_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_courses_instr FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(160) NOT NULL,
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL,
  KEY idx_modules_course (course_id, position),
  CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lessons (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module_id         INT UNSIGNED NOT NULL,
  title             VARCHAR(160) NOT NULL,
  type              ENUM('video','text') NOT NULL DEFAULT 'video',
  video_provider    ENUM('local','youtube','vimeo','embed') NULL,
  video_ref         VARCHAR(500) NULL,
  duration_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
  content           MEDIUMTEXT   NULL,
  is_preview        TINYINT(1)   NOT NULL DEFAULT 0,
  position          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  created_at        DATETIME     NOT NULL,
  updated_at        DATETIME     NOT NULL,
  KEY idx_lessons_module (module_id, position),
  CONSTRAINT fk_lessons_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id      INT UNSIGNED NOT NULL,
  title          VARCHAR(160) NOT NULL,
  file_path      VARCHAR(255) NOT NULL,
  original_name  VARCHAR(255) NOT NULL,
  mime           VARCHAR(120) NOT NULL,
  size_bytes     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL,
  KEY idx_materials_lesson (lesson_id),
  CONSTRAINT fk_materials_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Avaliações ----------
CREATE TABLE IF NOT EXISTS quizzes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  module_id     INT UNSIGNED NULL COMMENT 'NULL = avaliação final do curso',
  title         VARCHAR(160) NOT NULL,
  description   VARCHAR(500) NULL,
  pass_score    TINYINT UNSIGNED NOT NULL DEFAULT 70,
  max_attempts  TINYINT UNSIGNED NULL COMMENT 'NULL = ilimitado',
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  KEY idx_quizzes_course (course_id),
  CONSTRAINT fk_quizzes_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_quizzes_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_id     INT UNSIGNED NOT NULL,
  statement   TEXT         NOT NULL,
  explanation VARCHAR(500) NULL,
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_questions_quiz (quiz_id, position),
  CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_options (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id  INT UNSIGNED NOT NULL,
  text         VARCHAR(500) NOT NULL,
  is_correct   TINYINT(1)   NOT NULL DEFAULT 0,
  position     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_options_question (question_id),
  CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Jornada do aluno ----------
CREATE TABLE IF NOT EXISTS enrollments (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  course_id         INT UNSIGNED NOT NULL,
  status            ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  progress_percent  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_lesson_id    INT UNSIGNED NULL,
  last_access_at    DATETIME     NULL,
  enrolled_at       DATETIME     NOT NULL,
  completed_at      DATETIME     NULL,
  UNIQUE KEY uq_enrollment (user_id, course_id),
  KEY idx_enr_course (course_id, status),
  CONSTRAINT fk_enr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_enr_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_enr_lesson FOREIGN KEY (last_lesson_id) REFERENCES lessons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lesson_progress (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL,
  lesson_id         INT UNSIGNED NOT NULL,
  position_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
  watched_seconds   INT UNSIGNED NOT NULL DEFAULT 0,
  completed_at      DATETIME     NULL,
  updated_at        DATETIME     NOT NULL,
  UNIQUE KEY uq_progress (user_id, lesson_id),
  CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quiz_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  quiz_id      INT UNSIGNED NOT NULL,
  score        TINYINT UNSIGNED NOT NULL,
  passed       TINYINT(1)   NOT NULL,
  answers      JSON         NULL,
  finished_at  DATETIME     NOT NULL,
  KEY idx_qa_user_quiz (user_id, quiz_id),
  CONSTRAINT fk_qa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_qa_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificados guardam um "retrato" dos dados no momento da emissão:
-- se o curso mudar de nome depois, o documento emitido continua igual.
CREATE TABLE IF NOT EXISTS certificates (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(20)  NOT NULL UNIQUE,
  user_id         INT UNSIGNED NULL,
  course_id       INT UNSIGNED NULL,
  student_name    VARCHAR(120) NOT NULL,
  student_doc     VARCHAR(20)  NULL COMMENT 'CPF mascarado',
  course_title    VARCHAR(160) NOT NULL,
  workload_hours  SMALLINT UNSIGNED NOT NULL,
  final_score     TINYINT UNSIGNED NULL,
  issued_at       DATETIME     NOT NULL,
  status          ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
  revoked_at      DATETIME     NULL,
  revoke_reason   VARCHAR(255) NULL,
  checksum        CHAR(64)     NOT NULL,
  KEY idx_cert_user (user_id),
  KEY idx_cert_course (course_id),
  CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_cert_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Sistema, LGPD e auditoria ----------
CREATE TABLE IF NOT EXISTS settings (
  setting_key    VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value  TEXT        NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consents (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  type        VARCHAR(40)  NOT NULL COMMENT 'terms, privacy, marketing, cookies',
  version     VARCHAR(20)  NOT NULL,
  granted     TINYINT(1)   NOT NULL,
  ip          VARCHAR(45)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_consents_user (user_id),
  CONSTRAINT fk_consents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS data_requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NULL,
  requester     VARCHAR(160) NOT NULL,
  type          ENUM('export','deletion') NOT NULL,
  status        ENUM('pending','done','rejected') NOT NULL DEFAULT 'pending',
  notes         VARCHAR(500) NULL,
  created_at    DATETIME     NOT NULL,
  processed_at  DATETIME     NULL,
  CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(80)  NOT NULL,
  entity      VARCHAR(60)  NULL,
  entity_id   INT UNSIGNED NULL,
  details     JSON         NULL,
  ip          VARCHAR(45)  NULL,
  created_at  DATETIME     NOT NULL,
  KEY idx_audit_created (created_at),
  KEY idx_audit_entity (entity, entity_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version     VARCHAR(60) NOT NULL PRIMARY KEY,
  applied_at  DATETIME    NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
