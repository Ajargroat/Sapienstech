-- =============================================================
-- SAPIENSTECH — Phase One database schema
-- Multi-tenant redesign of elearning_db
-- Target: MySQL 8.0.16+ (needed for CHECK constraint enforcement)
--
-- NOTE: your current dump's header shows "Server version: 10.4.32-MariaDB".
-- The roadmap recommends MySQL 8 specifically -- confirm the new VPS/host
-- will run real MySQL 8, since JSON columns and CHECK constraints behave
-- a little differently on MariaDB (MariaDB is fine too, just needs 10.2+
-- for CHECK support -- worth a conscious choice either way).
-- =============================================================

SET NAMES utf8mb4;

-- =============================================================
-- SECTION 1: PLATFORM / TENANT CORE  (new -- from roadmap Phase 3)
-- =============================================================

CREATE TABLE tenants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    status ENUM('trial','active','suspended') NOT NULL DEFAULT 'trial',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tenants_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The router's lookup table: incoming Host header -> tenant_id.
-- domain is globally unique on purpose -- two tenants can never claim the same host.
CREATE TABLE domains (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY domains_domain_unique (domain),
    KEY domains_tenant_id_index (tenant_id),
    CONSTRAINT domains_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branding / theme config -- what the router hands to the frontend once it knows the tenant.
CREATE TABLE website_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    theme VARCHAR(100) NOT NULL DEFAULT 'default',
    logo_path VARCHAR(255) NULL DEFAULT NULL,
    favicon_path VARCHAR(255) NULL DEFAULT NULL,
    primary_color VARCHAR(7) NULL DEFAULT NULL,
    secondary_color VARCHAR(7) NULL DEFAULT NULL,
    font VARCHAR(100) NULL DEFAULT NULL,
    layout_config JSON NULL DEFAULT NULL COMMENT 'header/footer/nav structure',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY website_configs_tenant_id_unique (tenant_id),
    CONSTRAINT website_configs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which modules/features are turned on for this tenant. The router/frontend
-- checks this instead of hard-coding which pages exist per consultant.
CREATE TABLE features (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    `key` VARCHAR(100) NOT NULL COMMENT 'e.g. blog, student_evaluation, chat, ai_assistant',
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY features_tenant_id_key_unique (tenant_id, `key`),
    CONSTRAINT features_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable marketing-site copy (homepage, about, contact) -- distinct from
-- the education content in Section 3 below.
CREATE TABLE contents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    `key` VARCHAR(100) NOT NULL COMMENT 'e.g. homepage, about, contact',
    title VARCHAR(255) NULL DEFAULT NULL,
    body LONGTEXT NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY contents_tenant_id_key_unique (tenant_id, `key`),
    CONSTRAINT contents_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 2: IDENTITY
-- Old elearning_db had one flat `users` table with role='student' mixed
-- in among consultants, plus grade/gender/major columns that only ever
-- applied to students. Split per the roadmap's own model.
-- =============================================================

-- Staff/consultant logins only. tenant_id is nullable ONLY for
-- platform_admin, who isn't scoped to a single tenant (manages all of them).
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL only for platform_admin',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('platform_admin','tenant_admin','consultant_staff') NOT NULL DEFAULT 'consultant_staff',
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    remember_token VARCHAR(100) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_tenant_id_email_unique (tenant_id, email),
    CONSTRAINT users_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT users_role_tenant_check CHECK (
        (role = 'platform_admin' AND tenant_id IS NULL) OR
        (role <> 'platform_admin' AND tenant_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Learners. Split out of the old `users` table -- they already had their
-- own auth (password column existed for role='student'), so this keeps
-- that, plus the student-only fields that don't belong on a staff account.
CREATE TABLE students (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    grade VARCHAR(50) NULL DEFAULT NULL,
    gender VARCHAR(50) NULL DEFAULT NULL,
    major VARCHAR(50) NULL DEFAULT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    remember_token VARCHAR(100) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY students_tenant_id_email_unique (tenant_id, email),
    CONSTRAINT students_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renamed from consultant_user_id/student_user_id -> user_id/student_id
-- now that each unambiguously points at the correct table.
CREATE TABLE consultant_student_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'the consultant/staff member',
    student_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY csa_user_id_student_id_unique (user_id, student_id),
    KEY csa_tenant_id_index (tenant_id),
    KEY csa_student_id_index (student_id),
    CONSTRAINT csa_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT csa_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT csa_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 3: CONTENT LIBRARY (books / chapters / topics / questions / answers / tests)
-- Shape preserved from elearning_db. tenant_id added throughout on the
-- assumption each consultant has a private question bank -- flagged as
-- an open question in the design doc; easy to relax later if wrong.
-- =============================================================

CREATE TABLE books (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    book_title VARCHAR(255) NOT NULL,
    pdf_file_path VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY books_tenant_id_index (tenant_id),
    CONSTRAINT books_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chapters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    book_id BIGINT UNSIGNED NOT NULL,
    chapter_number INT NOT NULL,
    chapter_title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY chapters_tenant_id_index (tenant_id),
    KEY chapters_book_id_index (book_id),
    CONSTRAINT chapters_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT chapters_book_id_foreign FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE topics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    chapter_id BIGINT UNSIGNED NOT NULL,
    topic_title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY topics_tenant_id_index (tenant_id),
    KEY topics_chapter_id_index (chapter_id),
    CONSTRAINT topics_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT topics_chapter_id_foreign FOREIGN KEY (chapter_id) REFERENCES chapters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    chapter_id BIGINT UNSIGNED NOT NULL,
    topic_id BIGINT UNSIGNED NULL DEFAULT NULL,
    question_text TEXT NOT NULL,
    question_image_path VARCHAR(255) NULL DEFAULT NULL,
    solution_text TEXT NULL DEFAULT NULL,
    solution_image_path VARCHAR(255) NULL DEFAULT NULL,
    question_number_in_book VARCHAR(50) NULL DEFAULT NULL,
    difficulty ENUM('Easy','Medium','Hard') NULL DEFAULT NULL,
    question_type VARCHAR(50) NOT NULL DEFAULT 'multiple_choice',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY questions_tenant_id_index (tenant_id),
    KEY questions_chapter_id_index (chapter_id),
    KEY questions_topic_id_index (topic_id),
    CONSTRAINT questions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT questions_chapter_id_foreign FOREIGN KEY (chapter_id) REFERENCES chapters (id) ON DELETE CASCADE,
    CONSTRAINT questions_topic_id_foreign FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    answer_text TEXT NOT NULL,
    answer_image_path VARCHAR(255) NULL DEFAULT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY answers_tenant_id_index (tenant_id),
    KEY answers_question_id_index (question_id),
    CONSTRAINT answers_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT answers_question_id_foreign FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    test_title VARCHAR(255) NOT NULL,
    time_limit_minutes INT NULL DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY tests_tenant_id_index (tenant_id),
    KEY tests_created_by_user_id_index (created_by_user_id),
    CONSTRAINT tests_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT tests_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NEW -- replaces the old tests.question_ids_json column. A JSON array of
-- IDs can't be joined, indexed, or protected by a foreign key; this can.
CREATE TABLE test_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    test_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    position INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY test_questions_test_id_question_id_unique (test_id, question_id),
    KEY test_questions_tenant_id_index (tenant_id),
    KEY test_questions_question_id_index (question_id),
    CONSTRAINT test_questions_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT test_questions_test_id_foreign FOREIGN KEY (test_id) REFERENCES tests (id) ON DELETE CASCADE,
    CONSTRAINT test_questions_question_id_foreign FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 4: STUDENT ACTIVITY
-- =============================================================

CREATE TABLE student_assigned_quizzes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    test_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    assigned_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY saq_tenant_id_index (tenant_id),
    KEY saq_test_id_index (test_id),
    KEY saq_student_id_index (student_id),
    KEY saq_assigned_by_user_id_index (assigned_by_user_id),
    CONSTRAINT saq_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT saq_test_id_foreign FOREIGN KEY (test_id) REFERENCES tests (id) ON DELETE CASCADE,
    CONSTRAINT saq_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT saq_assigned_by_user_id_foreign FOREIGN KEY (assigned_by_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_test_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    assignment_id BIGINT UNSIGNED NULL DEFAULT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    test_id BIGINT UNSIGNED NULL DEFAULT NULL,
    score_simple_percent DECIMAL(5,2) NOT NULL,
    score_negative_percent DECIMAL(5,2) NOT NULL,
    time_taken_seconds INT NOT NULL,
    completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY sta_tenant_id_index (tenant_id),
    KEY sta_assignment_id_index (assignment_id),
    KEY sta_student_id_index (student_id),
    KEY sta_test_id_index (test_id),
    CONSTRAINT sta_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT sta_assignment_id_foreign FOREIGN KEY (assignment_id) REFERENCES student_assigned_quizzes (id) ON DELETE SET NULL,
    CONSTRAINT sta_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT sta_test_id_foreign FOREIGN KEY (test_id) REFERENCES tests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NEW -- merges the old student_attempt_answers + student_answer_history,
-- which both recorded "which answer for which question in which attempt"
-- with inconsistent foreign keys (the old table had none on question_id
-- or chosen_answer_id). One table, full referential integrity.
CREATE TABLE attempt_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    attempt_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    chosen_answer_id BIGINT UNSIGNED NULL DEFAULT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    answered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attempt_answers_attempt_id_question_id_unique (attempt_id, question_id),
    KEY attempt_answers_tenant_id_index (tenant_id),
    KEY attempt_answers_student_id_index (student_id),
    KEY attempt_answers_question_id_index (question_id),
    KEY attempt_answers_chosen_answer_id_index (chosen_answer_id),
    CONSTRAINT attempt_answers_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT attempt_answers_attempt_id_foreign FOREIGN KEY (attempt_id) REFERENCES student_test_attempts (id) ON DELETE CASCADE,
    CONSTRAINT attempt_answers_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT attempt_answers_question_id_foreign FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
    CONSTRAINT attempt_answers_chosen_answer_id_foreign FOREIGN KEY (chosen_answer_id) REFERENCES answers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_flagged_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY sfq_student_id_question_id_unique (student_id, question_id),
    KEY sfq_tenant_id_index (tenant_id),
    KEY sfq_question_id_index (question_id),
    CONSTRAINT sfq_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT sfq_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT sfq_question_id_foreign FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_book_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    book_id BIGINT UNSIGNED NOT NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY sbp_student_id_book_id_unique (student_id, book_id),
    KEY sbp_tenant_id_index (tenant_id),
    KEY sbp_book_id_index (book_id),
    CONSTRAINT sbp_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT sbp_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT sbp_book_id_foreign FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- last_modified_by_user_id is now nullable/SET NULL (was NOT NULL/CASCADE
-- in the old schema) so a departing consultant doesn't silently wipe a
-- student's saved weekly display settings.
CREATE TABLE student_weekly_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    week_start_date DATE NOT NULL,
    display_config JSON NULL DEFAULT NULL,
    last_modified_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY sws_student_id_week_start_date_unique (student_id, week_start_date),
    KEY sws_tenant_id_index (tenant_id),
    KEY sws_last_modified_by_user_id_index (last_modified_by_user_id),
    CONSTRAINT sws_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT sws_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT sws_last_modified_by_user_id_foreign FOREIGN KEY (last_modified_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 5: SCHEDULING
-- schedule_items/event_comments/item_comments can be authored by either a
-- staff user or a student, so each gets a type discriminator plus TWO
-- nullable foreign keys (mutually exclusive, enforced by a CHECK
-- constraint) instead of one generic, type-unsafe user_id.
-- =============================================================

CREATE TABLE schedule_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_description TEXT NULL DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '1=Monday, 7=Sunday',
    event_color VARCHAR(7) NOT NULL DEFAULT '#007bff',
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY schedule_events_tenant_id_index (tenant_id),
    KEY schedule_events_user_id_index (user_id),
    CONSTRAINT schedule_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT schedule_events_user_id_foreign FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    commenter_type ENUM('user','student') NOT NULL,
    commenter_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
    commenter_student_id BIGINT UNSIGNED NULL DEFAULT NULL,
    comment_text TEXT NOT NULL,
    commented_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_comments_tenant_id_index (tenant_id),
    KEY event_comments_event_id_index (event_id),
    KEY event_comments_commenter_user_id_index (commenter_user_id),
    KEY event_comments_commenter_student_id_index (commenter_student_id),
    CONSTRAINT event_comments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT event_comments_event_id_foreign FOREIGN KEY (event_id) REFERENCES schedule_events (id) ON DELETE CASCADE,
    CONSTRAINT event_comments_commenter_user_id_foreign FOREIGN KEY (commenter_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT event_comments_commenter_student_id_foreign FOREIGN KEY (commenter_student_id) REFERENCES students (id) ON DELETE SET NULL,
    CONSTRAINT event_comments_commenter_check CHECK (
        (commenter_type = 'user' AND commenter_user_id IS NOT NULL AND commenter_student_id IS NULL) OR
        (commenter_type = 'student' AND commenter_student_id IS NOT NULL AND commenter_user_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    week_start_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL DEFAULT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    color VARCHAR(50) NOT NULL,
    item_type ENUM('consultant_event','student_personal_block') NOT NULL,
    created_by_type ENUM('user','student') NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
    created_by_student_id BIGINT UNSIGNED NULL DEFAULT NULL,
    link_url VARCHAR(2083) NULL DEFAULT NULL,
    book_name VARCHAR(255) NULL DEFAULT NULL,
    test_count INT NULL DEFAULT NULL,
    page_count INT NULL DEFAULT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completion_timestamp DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY schedule_items_tenant_id_index (tenant_id),
    KEY schedule_items_student_id_index (student_id),
    KEY schedule_items_created_by_user_id_index (created_by_user_id),
    KEY schedule_items_created_by_student_id_index (created_by_student_id),
    CONSTRAINT schedule_items_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT schedule_items_student_id_foreign FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT schedule_items_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT schedule_items_created_by_student_id_foreign FOREIGN KEY (created_by_student_id) REFERENCES students (id) ON DELETE SET NULL,
    CONSTRAINT schedule_items_created_by_check CHECK (
        (created_by_type = 'user' AND created_by_user_id IS NOT NULL AND created_by_student_id IS NULL) OR
        (created_by_type = 'student' AND created_by_student_id IS NOT NULL AND created_by_user_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE item_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    commenter_type ENUM('user','student') NOT NULL,
    commenter_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
    commenter_student_id BIGINT UNSIGNED NULL DEFAULT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY item_comments_tenant_id_index (tenant_id),
    KEY item_comments_item_id_index (item_id),
    KEY item_comments_commenter_user_id_index (commenter_user_id),
    KEY item_comments_commenter_student_id_index (commenter_student_id),
    CONSTRAINT item_comments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT item_comments_item_id_foreign FOREIGN KEY (item_id) REFERENCES schedule_items (id) ON DELETE CASCADE,
    CONSTRAINT item_comments_commenter_user_id_foreign FOREIGN KEY (commenter_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT item_comments_commenter_student_id_foreign FOREIGN KEY (commenter_student_id) REFERENCES students (id) ON DELETE SET NULL,
    CONSTRAINT item_comments_commenter_check CHECK (
        (commenter_type = 'user' AND commenter_user_id IS NOT NULL AND commenter_student_id IS NULL) OR
        (commenter_type = 'student' AND commenter_student_id IS NOT NULL AND commenter_user_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- SECTION 6: PUBLISHING
-- =============================================================

CREATE TABLE blog_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    consultant_id BIGINT UNSIGNED NOT NULL COMMENT 'references users.id',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    visibility ENUM('public','students_only') NOT NULL DEFAULT 'public',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY blog_posts_tenant_id_index (tenant_id),
    KEY blog_posts_consultant_id_index (consultant_id),
    CONSTRAINT blog_posts_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT blog_posts_consultant_id_foreign FOREIGN KEY (consultant_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY blog_post_images_tenant_id_index (tenant_id),
    KEY blog_post_images_post_id_index (post_id),
    CONSTRAINT blog_post_images_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT blog_post_images_post_id_foreign FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- End of schema. 27 tables: 5 new platform-core tables, 22 adapted
-- from elearning_db (2 of which -- test_questions, attempt_answers --
-- replace old tables rather than just adding a column).
-- =============================================================
