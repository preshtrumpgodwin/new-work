<?php
/**
 * Zetaphase EduCloud — Dashboard Bootstrap
 *
 * Shared setup used by BOTH admin/dashboard.php and staff/index.php, so the
 * two entry points can never drift out of sync on session guarding, schema
 * migrations, feature-flag enforcement, or shared data hoisting. Each caller
 * must `define('ADMIN_DIR', ...)` pointing at the real admin/ folder before
 * requiring this file, then render its own HTML shell (header/sidebar) and
 * include the section content file for $section itself.
 *
 * Responsibilities:
 *  1. Session guard & school context
 *  2. Schema-version migration gate
 *  3. Feature-flag enforcement (incl. hard admin-only section lock)
 *  4. Shared data hoisting (classes, settings, theme)
 *  5. Auto-attendance
 *  6. POST routing → actions/*.php AND inline handlers
 *  7. Theme CSS variable computation ($tc, $tm)
 *
 * NOT this file's responsibility (left to the caller): HTML shell, header,
 * sidebar, and the final `include .../sections/$section.php`.
 */

require_once ADMIN_DIR . '/../config/security.php';
secure_session_start();
require_once ADMIN_DIR . '/../config/db.php';
require_once ADMIN_DIR . '/../config/Crypto.php';
require_once ADMIN_DIR . '/lib/AuditLog.php';
require_once ADMIN_DIR . '/lib/Helpers.php';
require_once ADMIN_DIR . '/lib/Notify.php';
require_once ADMIN_DIR . '/lib/SMSGateway.php';
require_once ADMIN_DIR . '/lib/Mailer.php';
require_once ADMIN_DIR . '/lib/NotificationEngine.php';
require_once ADMIN_DIR . '/components/breadcrumb.php';

require_once ADMIN_DIR . '/../config/subdomain.php';

// ── Resolve context from subdomain ──────────────────────────────────────────
$ctx        = resolve_subdomain($pdo);
$is_platform = $ctx['is_platform'];
$is_school   = $ctx['is_school'];
$school      = $ctx['school'];     // null unless on a school subdomain

// Branding defaults
$brand_name    = 'Zetaphase EduCloud';
$brand_sub     = 'zetaphase.com.ng';
$brand_logo    = 'logo.jpeg';
$brand_color   = '#4F46E5';
$school_uuid_ctx = '';
$theme_mode    = 'auto'; // auto, light, dark

if ($is_platform) {
    $brand_name  = 'Zetaphase — Platform';
    $brand_sub   = 'platform.zetaphase.com.ng';
    $brand_logo  = 'logo.jpeg';
}

if ($is_school && $school) {
    $brand_name      = $school['name'];
    $brand_sub       = $school['subdomain'] . '.zetaphase.com.ng';
    // Fix: prepend '/../' to logo path for correct resolution from /dashboard/
    $logo_path       = $school['logo_path'] ?? 'logo.jpeg';
    $brand_logo      = (!empty($logo_path) && strpos($logo_path, '/') !== 0)
                        ? '/../' . ltrim($logo_path, '/')
                        : $logo_path;
    $brand_color     = $school['theme_color'] ?? '#4F46E5';
    $theme_mode      = $school['theme_mode'] ?? 'auto';
    $school_uuid_ctx = $school['school_uuid'];
}

// ── 0. Runtime schema migrations (idempotent — safe to run every request) ────
// PERF-1/F7 fix: these ~25 ALTER TABLE statements used to run on EVERY request.
// They're now gated behind a schema_versions table so they run once, then a
// single cheap SELECT confirms "up to date" on every subsequent request.
define('DASHBOARD_SCHEMA_VERSION', 11);
$__schema_current = 0;
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_versions` (
        `version_id` INT PRIMARY KEY,
        `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $__schema_current = (int)($pdo->query("SELECT MAX(version_id) FROM schema_versions")->fetchColumn() ?: 0);
} catch (Exception $e) { /* fall through and run migrations to be safe */ }

if ($__schema_current < DASHBOARD_SCHEMA_VERSION) {
try {
    // Add columns to staff if missing
    foreach ([
        "ALTER TABLE `staff` ADD COLUMN `department` VARCHAR(100) DEFAULT NULL AFTER `role`",
        "ALTER TABLE `staff` ADD COLUMN `trcn_number` VARCHAR(50) DEFAULT NULL AFTER `qualification`",
        "ALTER TABLE `staff` ADD COLUMN `employer` VARCHAR(100) DEFAULT NULL",
    ] as $col_sql) {
        try { $pdo->exec($col_sql); } catch(PDOException $e){ /* already exists */ }
    }
    // Add columns to parents if missing
    foreach ([
        "ALTER TABLE `parents` ADD COLUMN `employer` VARCHAR(100) DEFAULT NULL AFTER `occupation`",
    ] as $col_sql) {
        try { $pdo->exec($col_sql); } catch(PDOException $e){}
    }
    // Add columns to students if missing
    foreach ([
        "ALTER TABLE `students` ADD COLUMN `admission_date` DATE DEFAULT NULL AFTER `parent_uuid`",
    ] as $col_sql) {
        try { $pdo->exec($col_sql); } catch(PDOException $e){}
    }
    // Add grading_json to school_settings if missing
    try { $pdo->exec("ALTER TABLE `school_settings` ADD COLUMN `grading_json` LONGTEXT DEFAULT NULL"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_settings` ADD COLUMN `ca1_max` INT DEFAULT 20"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_settings` ADD COLUMN `ca2_max` INT DEFAULT 20"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_settings` ADD COLUMN `exam_max` INT DEFAULT 60"); } catch(PDOException $e){}
    // Phase 3 — AI Configuration columns
    foreach ([
        "ALTER TABLE `school_settings` ADD COLUMN `ai_provider` VARCHAR(30) DEFAULT 'openai'",
        "ALTER TABLE `school_settings` ADD COLUMN `ai_api_key` VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `ai_model` VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `ai_essay_prompt` TEXT DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `ai_lesson_prompt` TEXT DEFAULT NULL",
    ] as $col_sql) {
        try { $pdo->exec($col_sql); } catch(PDOException $e){}
    }
    // Phase 3 — broadcast gateway response + read flag on internal messages
    try { $pdo->exec("ALTER TABLE `broadcast_messages` ADD COLUMN `gateway_response` TEXT DEFAULT NULL"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `parent_teacher_messages` ADD COLUMN `is_read` TINYINT DEFAULT 0"); } catch(PDOException $e){}
    // Phase 3.1 — link invoices/receipts to a specific student (was class/plan-level only)
    try { $pdo->exec("ALTER TABLE `school_invoices` ADD COLUMN `student_uuid` VARCHAR(50) DEFAULT NULL AFTER `school_uuid`"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_invoices` ADD COLUMN `session_name` VARCHAR(50) DEFAULT NULL"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_invoices` ADD COLUMN `term_name` VARCHAR(50) DEFAULT NULL"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_invoices` ADD INDEX `idx_student` (`student_uuid`)"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_receipts` ADD COLUMN `transaction_ref` VARCHAR(100) DEFAULT NULL"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_receipts` ADD COLUMN `received_by` VARCHAR(150) DEFAULT NULL"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `school_receipts` MODIFY COLUMN `payment_date` DATE DEFAULT (CURRENT_DATE)"); } catch(PDOException $e){}

    // Phase 4 — Student admission numbers
    try { $pdo->exec("ALTER TABLE `students` ADD COLUMN `admission_number` VARCHAR(50) DEFAULT NULL AFTER `student_uuid`"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE `students` ADD UNIQUE INDEX `uniq_admission_no` (`school_uuid`,`admission_number`)"); } catch(PDOException $e){}

    // Phase 4 — Staff HR: leave management, appraisals, payslips
    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff_leave_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `leave_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `staff_uuid` VARCHAR(50) NOT NULL,
        `staff_name` VARCHAR(150) NOT NULL,
        `leave_type` VARCHAR(50) DEFAULT 'Annual',
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `reason` TEXT DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'Pending',
        `reviewed_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff_appraisals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `appraisal_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `staff_uuid` VARCHAR(50) NOT NULL,
        `staff_name` VARCHAR(150) NOT NULL,
        `period_label` VARCHAR(50) NOT NULL,
        `punctuality_rating` TINYINT DEFAULT 3,
        `subject_mastery_rating` TINYINT DEFAULT 3,
        `classroom_management_rating` TINYINT DEFAULT 3,
        `teamwork_rating` TINYINT DEFAULT 3,
        `overall_comment` TEXT DEFAULT NULL,
        `appraised_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff_payslips` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `payslip_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `staff_uuid` VARCHAR(50) NOT NULL,
        `staff_name` VARCHAR(150) NOT NULL,
        `pay_period` VARCHAR(50) NOT NULL,
        `basic_salary` DECIMAL(10,2) NOT NULL,
        `allowances` DECIMAL(10,2) DEFAULT 0,
        `deductions` DECIMAL(10,2) DEFAULT 0,
        `net_pay` DECIMAL(10,2) NOT NULL,
        `generated_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_payslip (`school_uuid`,`staff_uuid`,`pay_period`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase 4 — NOTE: omr_evaluations insert previously used a nonexistent
    // `eval_uuid` column (the real PK is `evaluation_uuid`) — fixed directly
    // in actions/misc-actions.php rather than adding a redundant column here.

    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `recipient_group` VARCHAR(100) DEFAULT 'All Parents',
        `to_email` VARCHAR(200) DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body_html` TEXT NOT NULL,
        `recipient_count` INT DEFAULT 1,
        `status` VARCHAR(20) DEFAULT 'Sent',
        `gateway_response` TEXT DEFAULT NULL,
        `sent_by` VARCHAR(150) DEFAULT NULL,
        `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Auto-create fee_structures if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fee_structures` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `fee_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `fee_type` VARCHAR(100) NOT NULL,
        `class_name` VARCHAR(50) DEFAULT NULL,
        `term_name` VARCHAR(50) DEFAULT NULL,
        `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `description` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Auto-create school_receipts if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS `school_receipts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `receipt_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `invoice_uuid` VARCHAR(50) DEFAULT NULL,
        `receipt_no` VARCHAR(50) NOT NULL UNIQUE,
        `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
        `payment_method` VARCHAR(50) DEFAULT 'Cash',
        `transaction_ref` VARCHAR(200) DEFAULT NULL,
        `received_by` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Auto-create results table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS `results` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `result_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `student_uuid` VARCHAR(50) NOT NULL,
        `session_name` VARCHAR(50) NOT NULL,
        `term_name` VARCHAR(50) NOT NULL,
        `class_name` VARCHAR(50) NOT NULL,
        `arm_name` VARCHAR(50) DEFAULT 'Gold',
        `subject_name` VARCHAR(100) NOT NULL,
        `ca1_score` DECIMAL(6,2) DEFAULT 0,
        `ca2_score` DECIMAL(6,2) DEFAULT 0,
        `exam_score` DECIMAL(6,2) DEFAULT 0,
        `total_score` DECIMAL(6,2) DEFAULT 0,
        `grade` VARCHAR(5) DEFAULT NULL,
        `subject_teacher_remark` VARCHAR(200) DEFAULT NULL,
        `entered_by` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_result_row` (`school_uuid`,`student_uuid`,`session_name`,`term_name`,`subject_name`),
        INDEX idx_class_term (`school_uuid`,`class_name`,`arm_name`,`session_name`,`term_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // PERF-3 — audit_logs used to be CREATE TABLE IF NOT EXISTS'd on every
    // single AuditLog::write() call. Moved here so it only ever runs once.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `log_uuid`    VARCHAR(50)  NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50)  DEFAULT NULL,
        `user_uuid`   VARCHAR(50)  DEFAULT NULL,
        `action`      VARCHAR(100) NOT NULL,
        `target_uuid` VARCHAR(50)  DEFAULT NULL,
        `description` TEXT         DEFAULT NULL,
        `ip_address`  VARCHAR(45)  DEFAULT NULL,
        `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`),
        INDEX idx_action (`action`),
        INDEX idx_created (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Phase 7 — HR: editable Letter of Employment templates + issued letters
    $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_employment_letter_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `title` VARCHAR(150) NOT NULL DEFAULT 'Letter of Employment',
        `body_html` LONGTEXT NOT NULL,
        `is_default` TINYINT DEFAULT 0,
        `updated_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_employment_letters_issued` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `letter_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `staff_uuid` VARCHAR(50) NOT NULL,
        `template_uuid` VARCHAR(50) DEFAULT NULL,
        `rendered_html` LONGTEXT NOT NULL,
        `issued_by` VARCHAR(150) DEFAULT NULL,
        `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school_staff (`school_uuid`,`staff_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase 7 — configurable Testimonial templates (per school; used when
    // generating an alumni/leaver testimonial from the Alumni module)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `testimonial_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `name` VARCHAR(150) NOT NULL DEFAULT 'Standard Testimonial',
        `body_html` LONGTEXT NOT NULL,
        `is_default` TINYINT DEFAULT 0,
        `updated_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase B — Notification Engine: birthday templates (10 slots per
    // audience) + trigger-based templates (exam result / activity), and a
    // unified send log. Also add date_of_birth to parents (students/staff
    // already have it) so parent-birthday alerts are possible.
    try { $pdo->exec("ALTER TABLE `parents` ADD COLUMN `date_of_birth` DATE DEFAULT NULL"); } catch(PDOException $e){}

    $pdo->exec("CREATE TABLE IF NOT EXISTS `notification_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `category` VARCHAR(20) NOT NULL,        -- 'birthday' or 'trigger'
        `audience` VARCHAR(20) DEFAULT NULL,     -- 'student' | 'staff' | 'parent' (birthday only)
        `trigger_key` VARCHAR(30) DEFAULT NULL,  -- 'exam_result' | 'activity' (trigger only)
        `slot_index` TINYINT DEFAULT 1,          -- 1-10 for birthday templates
        `title` VARCHAR(150) NOT NULL DEFAULT 'Untitled',
        `body` TEXT NOT NULL,
        `is_active` TINYINT DEFAULT 0,
        `updated_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school_cat (`school_uuid`,`category`,`audience`),
        INDEX idx_school_trigger (`school_uuid`,`trigger_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `notification_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `log_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `category` VARCHAR(20) NOT NULL,
        `trigger_key` VARCHAR(30) DEFAULT NULL,
        `recipient_name` VARCHAR(150) DEFAULT NULL,
        `recipient_phone` VARCHAR(30) DEFAULT NULL,
        `message` TEXT DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'Sent',
        `gateway_response` TEXT DEFAULT NULL,
        `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`),
        INDEX idx_sent_at (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // One-time data fix (paired with the handle_image_upload()/asset_url()
    // path bug fix): old code stored student/staff/parent photo paths
    // relative to admin/ instead of the true project root, and stored
    // school logo paths with a wrong extra "photos/" segment entirely.
    // Re-normalize any rows still in the old formats.
    foreach (['students', 'staff', 'parents'] as $__tbl) {
        $pdo->exec("UPDATE `$__tbl` SET photo_path = CONCAT('admin/', photo_path)
                     WHERE photo_path LIKE 'uploads/photos/%' AND photo_path NOT LIKE 'admin/%'");
    }
    $pdo->exec("UPDATE `schools` SET logo_path = REPLACE(logo_path, 'uploads/photos/school_logos/', 'uploads/school_logos/')
                 WHERE logo_path LIKE 'uploads/photos/school_logos/%'");

    // Defense-in-depth for the assessment-template duplicate-name bugfix —
    // legacy duplicate names (created before the app-level dedupe in
    // settings-actions.php existed) permanently blocked the ALTER below from
    // ever applying, silently, on every single deploy. Merge them first:
    // for each (school_uuid, LOWER(name)) group with more than one row, keep
    // the oldest row, repoint every table that references the duplicates'
    // template_uuid to the surviving one, then delete the duplicate rows.
    try {
        $dupGroups = $pdo->query("
            SELECT school_uuid, LOWER(TRIM(name)) AS norm_name, COUNT(*) AS cnt
            FROM assessment_templates
            GROUP BY school_uuid, LOWER(TRIM(name))
            HAVING COUNT(*) > 1
        ")->fetchAll();

        foreach ($dupGroups as $grp) {
            $rows = $pdo->prepare("
                SELECT template_uuid FROM assessment_templates
                WHERE school_uuid = ? AND LOWER(TRIM(name)) = ?
                ORDER BY id ASC
            ");
            $rows->execute([$grp['school_uuid'], $grp['norm_name']]);
            $uuids = $rows->fetchAll(PDO::FETCH_COLUMN);
            if (count($uuids) < 2) continue;

            $keep = array_shift($uuids); // oldest row survives
            $placeholders = implode(',', array_fill(0, count($uuids), '?'));

            $pdo->prepare("UPDATE assessment_configurations SET template_uuid = ? WHERE template_uuid IN ($placeholders)")
                ->execute(array_merge([$keep], $uuids));
            $pdo->prepare("UPDATE result_assessment_scores SET template_uuid = ? WHERE template_uuid IN ($placeholders)")
                ->execute(array_merge([$keep], $uuids));
            $pdo->prepare("DELETE FROM assessment_templates WHERE template_uuid IN ($placeholders)")
                ->execute($uuids);

            // Repointing may have created duplicate configuration rows
            // (same session/term/class now pointing at the same template);
            // keep the newest of each and drop the rest.
            $cfgDupes = $pdo->prepare("
                SELECT session_name, term_name, class_name, COUNT(*) AS cnt
                FROM assessment_configurations
                WHERE school_uuid = ? AND template_uuid = ?
                GROUP BY session_name, term_name, class_name
                HAVING COUNT(*) > 1
            ");
            $cfgDupes->execute([$grp['school_uuid'], $keep]);
            foreach ($cfgDupes->fetchAll() as $cfgGrp) {
                $cfgRows = $pdo->prepare("
                    SELECT config_uuid FROM assessment_configurations
                    WHERE school_uuid = ? AND template_uuid = ? AND session_name = ?
                      AND term_name = ? AND (class_name = ? OR (class_name IS NULL AND ? IS NULL))
                    ORDER BY id DESC
                ");
                $cfgRows->execute([$grp['school_uuid'], $keep, $cfgGrp['session_name'], $cfgGrp['term_name'], $cfgGrp['class_name'], $cfgGrp['class_name']]);
                $cfgUuids = $cfgRows->fetchAll(PDO::FETCH_COLUMN);
                array_shift($cfgUuids); // keep newest
                if ($cfgUuids) {
                    $cfgPh = implode(',', array_fill(0, count($cfgUuids), '?'));
                    $pdo->prepare("DELETE FROM assessment_configurations WHERE config_uuid IN ($cfgPh)")->execute($cfgUuids);
                }
            }
        }
    } catch (PDOException $e) {}

    try { $pdo->exec("ALTER TABLE `assessment_templates` ADD UNIQUE KEY `uniq_school_name` (`school_uuid`,`name`)"); } catch (PDOException $e) {}

    // QR/Barcode Attendance — per-school toggle: 'daily' (rotating, current
    // default behaviour) vs 'static' (permanent, same as printed ID cards).
    try { $pdo->exec("ALTER TABLE `schools` ADD COLUMN `gate_qr_mode` VARCHAR(10) NOT NULL DEFAULT 'daily'"); } catch (PDOException $e) {}

    // Visitor gate check-in — no-login front-end form, per school.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `visitor_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `visitor_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `purpose` VARCHAR(255) DEFAULT NULL,
        `host_name` VARCHAR(150) DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'Checked In',
        `checked_in_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `checked_out_at` TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_school (`school_uuid`),
        INDEX idx_checked_in (`checked_in_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase C — Past Question Bank (CBT-linked + typeable printed exams)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `question_bank` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `question_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `subject_name` VARCHAR(100) NOT NULL,
        `class_name` VARCHAR(50) DEFAULT NULL,
        `question_type` VARCHAR(20) NOT NULL DEFAULT 'objective', -- objective | theory
        `question_text` TEXT NOT NULL,
        `option_a` VARCHAR(500) DEFAULT NULL, `option_b` VARCHAR(500) DEFAULT NULL,
        `option_c` VARCHAR(500) DEFAULT NULL, `option_d` VARCHAR(500) DEFAULT NULL,
        `correct_option` VARCHAR(1) DEFAULT NULL,
        `year` VARCHAR(10) DEFAULT NULL,
        `topic` VARCHAR(150) DEFAULT NULL,
        `for_printed_exam` TINYINT DEFAULT 0, -- also usable as a typed, printable exam question
        `created_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school_subject (`school_uuid`,`subject_name`,`class_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `printed_exam_papers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `paper_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `title` VARCHAR(200) NOT NULL,
        `subject_name` VARCHAR(100) DEFAULT NULL,
        `class_name` VARCHAR(50) DEFAULT NULL,
        `instructions` TEXT DEFAULT NULL,
        `question_uuids` TEXT NOT NULL, -- comma-separated question_bank uuids, in order
        `created_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase D — Virtual Classroom
    $pdo->exec("CREATE TABLE IF NOT EXISTS `virtual_classes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `class_session_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `title` VARCHAR(200) NOT NULL,
        `class_name` VARCHAR(50) DEFAULT NULL,
        `subject_name` VARCHAR(100) DEFAULT NULL,
        `meeting_link` VARCHAR(500) NOT NULL,
        `platform` VARCHAR(20) DEFAULT 'Other', -- Zoom | Google Meet | Other
        `scheduled_at` DATETIME DEFAULT NULL,
        `created_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`),
        INDEX idx_scheduled (`scheduled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase D — Student Career Advisory
    $pdo->exec("CREATE TABLE IF NOT EXISTS `career_advisory_notes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `note_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `student_uuid` VARCHAR(50) NOT NULL,
        `recommended_paths` TEXT DEFAULT NULL,
        `strengths` TEXT DEFAULT NULL,
        `counselor_notes` TEXT DEFAULT NULL,
        `created_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school_student (`school_uuid`,`student_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase E — Staff Attendance
    $pdo->exec("CREATE TABLE IF NOT EXISTS `staff_attendance` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `record_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `staff_uuid` VARCHAR(50) NOT NULL,
        `date` DATE NOT NULL,
        `clock_in` TIME DEFAULT NULL,
        `clock_out` TIME DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'Present',
        UNIQUE KEY uniq_staff_day (`school_uuid`,`staff_uuid`,`date`),
        INDEX idx_school_date (`school_uuid`,`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase E — Parent-initiated payment requests
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `request_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `parent_uuid` VARCHAR(50) NOT NULL,
        `student_uuid` VARCHAR(50) DEFAULT NULL,
        `description` VARCHAR(255) NOT NULL,
        `amount` DECIMAL(12,2) DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'Pending', -- Pending | Approved | Declined
        `admin_note` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `resolved_at` TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_school_status (`school_uuid`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase E — Flutterwave (second payment gateway), keys encrypted like Paystack
    $pdo->exec("CREATE TABLE IF NOT EXISTS `flutterwave_settings` (
        `school_uuid` VARCHAR(50) PRIMARY KEY,
        `public_key_enc` TEXT DEFAULT NULL,
        `secret_key_enc` TEXT DEFAULT NULL,
        `is_active` TINYINT DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase C — Result Slip drag-and-drop templates (platform-level base +
    // per-school selection/override)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `result_slip_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) DEFAULT NULL, -- NULL = platform-manager-built base template
        `name` VARCHAR(150) NOT NULL,
        `layout_json` LONGTEXT NOT NULL, -- ordered array of field blocks (drag-and-drop layout)
        `is_platform_default` TINYINT DEFAULT 0,
        `created_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE `schools` ADD COLUMN `active_result_slip_template_uuid` VARCHAR(50) DEFAULT NULL"); } catch (PDOException $e) {}

    // Phase D — Timetable template builder + auto-generator
    $pdo->exec("CREATE TABLE IF NOT EXISTS `timetable_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `days_json` TEXT NOT NULL, 
        `periods_json` TEXT NOT NULL,
        `created_by` VARCHAR(150) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase F — Persistent subject-teacher assignments (per class), so the
    // timetable auto-generator can read from a saved table instead of the
    // admin retyping "Subject, Teacher, Periods/Week" every time.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `subject_teacher_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `assignment_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `class_name` VARCHAR(100) NOT NULL,
        `subject` VARCHAR(150) NOT NULL,
        `teacher_name` VARCHAR(150) NOT NULL,
        `periods_per_week` INT NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school_class (`school_uuid`,`class_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Phase G — OMR scan-and-mark: printed per-student strips (3 per A4) with
    // a pre-shaded ID bubble grid + fiducial corners, so a photo/scan of the
    // cut strip can be auto-identified and auto-marked.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `omr_sheet_students` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `sheet_student_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `sheet_uuid` VARCHAR(50) NOT NULL,
        `student_uuid` VARCHAR(50) NOT NULL,
        `student_name` VARCHAR(150) NOT NULL,
        `roll_number` VARCHAR(50) DEFAULT NULL,
        `serial_code` VARCHAR(12) NOT NULL,
        `scanned_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sheet_serial (`sheet_uuid`,`serial_code`),
        INDEX idx_sheet (`sheet_uuid`),
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE `omr_evaluations` ADD COLUMN `sheet_student_uuid` VARCHAR(50) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `omr_evaluations` ADD COLUMN `scan_confidence` VARCHAR(20) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `omr_evaluations` ADD COLUMN `flagged_questions_json` TEXT DEFAULT NULL"); } catch (PDOException $e) {}

    // Phase 8 — Class arms must belong to a specific class (e.g. "JSS1 A" is a
    // different arm from "JSS2 A"), not be a school-wide flat list.
    try { $pdo->exec("ALTER TABLE `academic_arms` ADD COLUMN `class_name` VARCHAR(100) DEFAULT NULL AFTER `school_uuid`"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `academic_arms` ADD INDEX `idx_school_class` (`school_uuid`,`class_name`)"); } catch (PDOException $e) {}

    // Phase 9 — Timetable rebuilt as a single whole-school grid (all classes
    // and arms together, per day) instead of one class at a time.
    // `timetables` itself already exists in the live DB from an earlier,
    // undocumented migration — these are additive only.
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS `timetables` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `timetable_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `class_name` VARCHAR(100) NOT NULL,
        `arm_name` VARCHAR(100) DEFAULT NULL,
        `day_of_week` VARCHAR(20) NOT NULL,
        `period_time` VARCHAR(50) NOT NULL,
        `subject` VARCHAR(150) DEFAULT NULL,
        `teacher_name` VARCHAR(150) DEFAULT NULL,
        `room_number` VARCHAR(50) DEFAULT NULL,
        `has_clash` TINYINT(1) DEFAULT 0,
        `clash_overridden` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school_class_arm (`school_uuid`,`class_name`,`arm_name`),
        INDEX idx_school_day (`school_uuid`,`day_of_week`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `timetables` ADD COLUMN `has_clash` TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `timetables` ADD COLUMN `clash_overridden` TINYINT(1) DEFAULT 0"); } catch (PDOException $e) {}
    // Normalize NULL arm_name to '' so the composite unique key below treats
    // "no arm" consistently (MySQL treats NULL <> NULL, which would let
    // duplicate NULL-arm slots slip through the unique constraint).
    try { $pdo->exec("UPDATE `timetables` SET `arm_name`='' WHERE `arm_name` IS NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `timetables` MODIFY `arm_name` VARCHAR(100) NOT NULL DEFAULT ''"); } catch (PDOException $e) {}
    // One slot per class/arm/day/period — lets the grid upsert a cell by
    // this composite key instead of needing a row to already exist.
    try { $pdo->exec("ALTER TABLE `timetables` ADD UNIQUE KEY `uniq_slot` (`school_uuid`,`class_name`,`arm_name`,`day_of_week`,`period_time`)"); } catch (PDOException $e) {}

    // School-wide period/time-slot columns for the grid (the "add new
    // column" button), shared across every class/arm instead of living
    // inside a per-template period list.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `timetable_periods` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `period_uuid` VARCHAR(50) NOT NULL UNIQUE,
        `school_uuid` VARCHAR(50) NOT NULL,
        `label` VARCHAR(50) NOT NULL,
        `sort_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school (`school_uuid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Which days appear on the grid — every school starts with Mon–Fri
    // active and Sat/Sun present-but-inactive; admins can toggle any of
    // the seven on or off.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `timetable_days` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `school_uuid` VARCHAR(50) NOT NULL,
        `day_name` VARCHAR(20) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `sort_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_school_day (`school_uuid`,`day_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Publish record — a timestamped snapshot marker per session/term
    // rather than a per-row flag, so publishing doesn't require touching
    // every slot and a re-publish after edits is just a new row.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `timetable_publications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `school_uuid` VARCHAR(50) NOT NULL,
        `session_name` VARCHAR(50) DEFAULT NULL,
        `term_name` VARCHAR(50) DEFAULT NULL,
        `published_by` VARCHAR(150) DEFAULT NULL,
        `published_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_school_term (`school_uuid`,`session_name`,`term_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Schema v11 -- Paystack/Flutterwave now configured platform-side only
    // (platform/settings.php), stored on school_settings directly instead
    // of the school_payment_settings/flutterwave_settings tables the
    // school-admin portal used to write to.
    foreach ([
        "ALTER TABLE `school_settings` ADD COLUMN `paystack_public_key` VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `paystack_secret_key` TEXT DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `payments_enabled` TINYINT(1) DEFAULT 0",
        "ALTER TABLE `school_settings` ADD COLUMN `flutterwave_public_key` VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `flutterwave_secret_key` TEXT DEFAULT NULL",
        "ALTER TABLE `school_settings` ADD COLUMN `flutterwave_enabled` TINYINT(1) DEFAULT 0",
    ] as $col_sql) {
        try { $pdo->exec($col_sql); } catch(PDOException $e){}
    }
} catch (Exception $_mig_e) { /* migrations never crash the app */ }
    // Record that this migration set has been applied so future requests skip it.
    try {
        $pdo->prepare("INSERT IGNORE INTO schema_versions (version_id) VALUES (?)")->execute([DASHBOARD_SCHEMA_VERSION]);
    } catch (Exception $e) {}
} // end schema-version gate

// ── 1. Auth guard ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_uuid']) || empty($_SESSION['school_uuid'])) {
    header('Location: ../login.php');
    exit;
}

$school_uuid = $_SESSION['school_uuid'];
$user_uuid   = $_SESSION['user_uuid'];
$active_role = $_SESSION['role'] ?? 'Teacher';

// Force a password change before anything else if this account still holds a temp/default password.
try {
    $mrp = $pdo->prepare("SELECT must_reset_password FROM users WHERE user_uuid = ? LIMIT 1");
    $mrp->execute([$user_uuid]);
    if ((int)$mrp->fetchColumn() === 1) {
        header('Location: ../change-password.php');
        exit;
    }
} catch (Exception $e) {}

// CSRF guard: every POST into this router must carry a valid token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: dashboard.php?section=' . urlencode($_GET['section'] ?? 'overview') . '&error=' . urlencode('Your session expired — please try again.'));
    exit;
}

$success_msg = $_GET['success'] ?? '';
$error_msg   = $_GET['error']   ?? '';
$section     = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['section'] ?? 'overview'));

// ── 2. Feature-flag map ───────────────────────────────────────────────────────
$sectionFeatureMap = [
    'roster'               => 'roster',
    'parents'              => 'parents',
    'healthcare'           => 'healthcare',
    'attendance'           => 'attendance',
    'timetable'            => 'timetable',
    'primary_ops'          => 'primary_ops',
    'hr'                   => 'staff',
    'admissions'           => 'admissions',
    'library'              => 'library',
    'hostels'              => 'hostel',
    'transport'            => 'transport',
    'disciplinary'         => 'disciplinary',
    'cbt'                  => 'cbt',
    'omr'                  => 'omr',
    'essay_ocr'            => 'essay_ocr',
    'id_cards'             => 'id_cards',
    'gate_scanner'         => 'gate_scanner',
    'consultations'        => 'consultations',
    'school_store'         => 'inventory',
    'notice_board'         => 'news_notices',
    'cafeteria_meals'      => 'cafeteria_meals',
    'finance'              => 'finance',
    'results'              => 'results',
    'report_cards'         => 'report_cards',
    'broadsheet'           => 'broadsheet',
    'assignments'          => 'assignments',
    'lesson_plans'         => 'lesson_plans',
    'broadcast'            => 'sms_broadcast',
    'email_centre'         => 'email_centre',
    'notifications'        => 'in_app_notifications',
    'alumni'               => 'alumni',
    'condition_of_service' => 'condition_of_service',
    'settings'             => 'settings',
];

// getSchoolFeatureCeiling(), accessLevelRank(), capAccessLevel(), getFeatureAccessLevel(),
// isSectionEnabled() now live in lib/Helpers.php so they're reusable from standalone
// API endpoints (e.g. admin/api/gate-scan.php) without pulling in all of dashboard.php.

if ($section !== 'overview') {
    $rf = $sectionFeatureMap[$section] ?? null;
    if ($rf && $active_role !== 'Platform Manager' && !isSectionEnabled($rf, $school_uuid)) {
        $section = 'overview';
    }
}

// Hard admin-only lock — these are school-configuration/management screens
// (feature-access assignment, staff HR records + salary, school settings &
// theme, class/term/session structure, school policy). Unlike every other
// section, access here is NEVER driven by staff_feature_permissions or
// role_permissions rows — a misconfigured permission grant must not be able
// to expose these to a non-admin. Only School Admin / Platform Manager may
// ever reach them, from either admin/dashboard.php or staff/index.php.
$adminOnlySections = ['settings', 'hr', 'primary_ops', 'condition_of_service'];
if (in_array($section, $adminOnlySections, true) && !in_array($active_role, ['School Admin', 'Platform Manager'], true)) {
    $section = 'overview';
}

$current_access = getFeatureAccessLevel($sectionFeatureMap[$section] ?? $section, $active_role, $user_uuid, $school_uuid);
if ($section !== 'overview' && $current_access === 'hide' && !in_array($active_role, ['School Admin','Platform Manager'])) {
    $section = 'overview';
}

// ── 3. Shared data ────────────────────────────────────────────────────────────
$school = []; $school_settings = []; 
$user_theme = null;
try {
    $s = $pdo->prepare("SELECT * FROM schools WHERE school_uuid=? LIMIT 1");
    $s->execute([$school_uuid]); $school = $s->fetch() ?: [];
    $s2 = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid=? LIMIT 1");
    $s2->execute([$school_uuid]); $school_settings = $s2->fetch() ?: [];
    
    // Fetch user's theme preference
    $s3 = $pdo->prepare("SELECT theme_preference FROM users WHERE user_uuid=? LIMIT 1");
    $s3->execute([$user_uuid]);
    $user_theme = $s3->fetchColumn();
} catch (Exception $e) {}

// Theme determination: User preference > School setting > Auto
$school_theme = $school['theme_mode'] ?? ($school_settings['theme_mode'] ?? 'auto');
if ($user_theme && in_array($user_theme, ['light', 'dark', 'auto'])) {
    $theme_mode = $user_theme;
} else {
    $theme_mode = $school_theme;
}

// Auto theme detection based on Nigeria time
$hour = (int)date('H');
if ($theme_mode === 'auto') {
    $display_theme = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
} else {
    $display_theme = $theme_mode;
}
$html_class = $display_theme === 'dark' ? 'dark' : '';

$roster_classes = [];
try {
    $rc = $pdo->prepare("SELECT class_name FROM academic_classes WHERE school_uuid=? ORDER BY id ASC");
    $rc->execute([$school_uuid]);
    $roster_classes = $rc->fetchAll(PDO::FETCH_COLUMN);
    if (empty($roster_classes)) $roster_classes = ['JSS1','JSS2','JSS3','SSS1','SSS2','SSS3'];
} catch (Exception $e) { $roster_classes = ['JSS1','JSS2','JSS3','SSS1','SSS2','SSS3']; }

$report_card_config = ['show_photo'=>1,'show_logo'=>1,'show_letterhead'=>1,'show_signature'=>1,
    'show_attendance'=>1,'show_position'=>1,'show_class_avg'=>1,'show_grade_scale'=>1,
    'show_teacher_comment'=>1,'show_principal_comment'=>1,'show_healthcare'=>1];
if (!empty($school['report_card_config_json'])) {
    $d = json_decode($school['report_card_config_json'], true);
    if (is_array($d)) $report_card_config = array_merge($report_card_config, $d);
}

$upload_dir_photos_students = ADMIN_DIR . '/uploads/photos/students/';
$upload_dir_photos_staff    = ADMIN_DIR . '/uploads/photos/staff/';
$upload_dir_photos_parents  = ADMIN_DIR . '/uploads/photos/parents/';
foreach ([$upload_dir_photos_students,$upload_dir_photos_staff,$upload_dir_photos_parents] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ── 4. Auto-attendance ────────────────────────────────────────────────────────
$today_date = date('Y-m-d');
$today_attendance_rule = attendanceMarkable($pdo, $school_uuid, $today_date);
if ($today_attendance_rule['allowed']) {
    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE school_uuid=? AND date=?");
        $chk->execute([$school_uuid,$today_date]);
        if ($chk->fetchColumn() == 0) {
            $stds = $pdo->prepare("SELECT student_uuid FROM students WHERE school_uuid=? AND status='Active'");
            $stds->execute([$school_uuid]);
            $ins = $pdo->prepare("INSERT IGNORE INTO attendance_records (school_uuid,student_uuid,date,status,auto_marked) VALUES (?,?,?,'Present',1)");
            foreach ($stds->fetchAll(PDO::FETCH_COLUMN) as $sid) $ins->execute([$school_uuid,$sid,$today_date]);
        }
    } catch (Exception $e) {}
}

// ── 5. POST routing ───────────────────────────────────────────────────────────
// Handle all POST requests here, BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ── Handle theme change ──────────────────────────────────────
    if (isset($_POST['action_change_theme'])) {
        $new_theme = trim($_POST['theme'] ?? 'auto');
        if (in_array($new_theme, ['light', 'dark', 'auto'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE user_uuid = ?");
                $stmt->execute([$new_theme, $user_uuid]);
                $_SESSION['user_theme'] = $new_theme;
                $_SESSION['flash_success'] = "Theme changed to " . ucfirst($new_theme);
            } catch (Exception $e) {
                $_SESSION['flash_error'] = "Failed to update theme preference";
            }
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit;
    }
    
    // ── Handle change password ──────────────────────────────────
    if (isset($_POST['action_change_user_password'])) {
        $current_password = trim($_POST['current_password'] ?? '');
        $new_password     = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $open_pwd_modal   = true;

        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password !== $confirm_password) {
                $_SESSION['flash_error'] = 'New passwords do not match.';
            } elseif (($policyErr = password_policy_check($new_password)) !== '') {
                $_SESSION['flash_error'] = $policyErr;
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_uuid = ? LIMIT 1");
                    $stmt->execute([$user_uuid]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $password_matches = password_verify($current_password, $user['password_hash']);

                        if ($password_matches) {
                            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                            $upd = $pdo->prepare("UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE user_uuid = ?");
                            $upd->execute([$new_hash, $user_uuid]);

                            // Audit log
                            try {
                                $logStmt = $pdo->prepare("INSERT INTO audit_logs (school_uuid, user_email, action) VALUES (?, ?, ?)");
                                $logStmt->execute([$school_uuid ?: null, $_SESSION['email'] ?? '', "Changed personal account password"]);
                            } catch (Exception $e) {}

                            $_SESSION['flash_success'] = 'Your password has been changed successfully!';
                        } else {
                            $_SESSION['flash_error'] = 'Current password is incorrect.';
                        }
                    } else {
                        $_SESSION['flash_error'] = 'User account not found.';
                    }
                } catch (PDOException $e) {
                    $_SESSION['flash_error'] = 'Database error. Please try again.';
                }
            }
        } else {
            $_SESSION['flash_error'] = 'Please complete all password fields.';
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
        exit;
    }

    // ── Other action files ──────────────────────────────────────
    // One action file per section (36 files) — was 17 grouped files.
    require_once ADMIN_DIR . '/actions/admissions-actions.php';
    require_once ADMIN_DIR . '/actions/alumni-actions.php';
    require_once ADMIN_DIR . '/actions/assignments-actions.php';
    require_once ADMIN_DIR . '/actions/attendance-actions.php';
    require_once ADMIN_DIR . '/actions/broadcast-actions.php';
    require_once ADMIN_DIR . '/actions/cafeteria_meals-actions.php';
    require_once ADMIN_DIR . '/actions/career_advisory-actions.php';
    require_once ADMIN_DIR . '/actions/cbt-actions.php';
    require_once ADMIN_DIR . '/actions/consultations-actions.php';
    require_once ADMIN_DIR . '/actions/disciplinary-actions.php';
    require_once ADMIN_DIR . '/actions/email_centre-actions.php';
    require_once ADMIN_DIR . '/actions/essay_ocr-actions.php';
    require_once ADMIN_DIR . '/actions/finance-actions.php';
    require_once ADMIN_DIR . '/actions/gate_scanner-actions.php';
    require_once ADMIN_DIR . '/actions/healthcare-actions.php';
    require_once ADMIN_DIR . '/actions/hostels-actions.php';
    require_once ADMIN_DIR . '/actions/hr-actions.php';
    require_once ADMIN_DIR . '/actions/lesson_plans-actions.php';
    require_once ADMIN_DIR . '/actions/library-actions.php';
    require_once ADMIN_DIR . '/actions/notice_board-actions.php';
    require_once ADMIN_DIR . '/actions/notifications-actions.php';
    require_once ADMIN_DIR . '/actions/omr-actions.php';
    require_once ADMIN_DIR . '/actions/parents-actions.php';
    require_once ADMIN_DIR . '/actions/primary_ops-actions.php';
    require_once ADMIN_DIR . '/actions/question_bank-actions.php';
    require_once ADMIN_DIR . '/actions/report_cards-actions.php';
    require_once ADMIN_DIR . '/actions/result_slip_templates-actions.php';
    require_once ADMIN_DIR . '/actions/results-actions.php';
    require_once ADMIN_DIR . '/actions/roster-actions.php';
    require_once ADMIN_DIR . '/actions/school_store-actions.php';
    require_once ADMIN_DIR . '/actions/settings-actions.php';
    require_once ADMIN_DIR . '/actions/staff_attendance-actions.php';
    require_once ADMIN_DIR . '/actions/testimonials-actions.php';
    require_once ADMIN_DIR . '/actions/timetable-actions.php';
    require_once ADMIN_DIR . '/actions/transport-actions.php';
    require_once ADMIN_DIR . '/actions/virtual_classroom-actions.php';
}

// ── 6. Theme CSS vars ─────────────────────────────────────────────────────────
$tc = htmlspecialchars($school['theme_color'] ?? '#4F46E5');
$tm = $display_theme;
