<?php
/**
 * Helpers — small utility functions shared across all section files.
 * Include once per request via dashboard.php; section files can call freely.
 */

/**
 * Hard ceiling for any saved image upload (photos, logos, etc.) — 50KB.
 * Uploads over this are auto-compressed (resized + re-encoded, trying WebP
 * first since it compresses best) down to this budget rather than rejected
 * outright. Only if compression genuinely can't get under budget (extremely
 * rare — would mean the image is already tiny in dimensions but very
 * detailed) do we reject and ask for a smaller source photo.
 */
const MAX_UPLOAD_IMAGE_BYTES = 50 * 1024;

/**
 * Compress an already-validated image file down to $max_bytes.
 * Tries, in order: resize to a sane max dimension, then WebP at
 * decreasing quality, then (if WebP isn't available on this server) JPEG
 * at decreasing quality, then further dimension reduction if quality
 * alone isn't enough.
 *
 * @return array{data:string,ext:string}|null  null if it couldn't be
 *         brought under budget at all (e.g. GD unavailable).
 */
function compress_image_to_limit(string $tmp_path, string $real_mime, int $max_bytes): ?array {
    if (!extension_loaded('gd')) {
        return null;
    }

    $src = match ($real_mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp_path),
        'image/png'  => @imagecreatefrompng($tmp_path),
        'image/gif'  => @imagecreatefromgif($tmp_path), // static frame only — animation is not preserved
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp_path) : false,
        default      => false,
    };
    if ($src === false) {
        return null;
    }

    // PNGs may carry transparency — preserve alpha through the resize step.
    imagealphablending($src, true);
    imagesavealpha($src, true);

    $max_dim = 640; // generous for an ID-card/profile photo; shrinks further below if needed
    $width   = imagesx($src);
    $height  = imagesy($src);

    $can_webp = function_exists('imagewebp');

    // Try progressively smaller dimensions until quality-only compression
    // can hit the budget, or we hit a floor small enough that shrinking
    // further would make the photo useless.
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $scale = min(1, $max_dim / max($width, $height));
        $w = max(40, (int)round($width  * $scale));
        $h = max(40, (int)round($height * $scale));

        $resized = imagecreatetruecolor($w, $h);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $w, $h, $transparent);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $w, $h, $width, $height);

        foreach ([80, 60, 45, 30, 18] as $quality) {
            ob_start();
            if ($can_webp) {
                imagewebp($resized, null, $quality);
                $ext = 'webp';
            } else {
                // No alpha in JPEG — flatten onto white first.
                $flat = imagecreatetruecolor($w, $h);
                imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                imagecopy($flat, $resized, 0, 0, 0, 0, $w, $h);
                imagejpeg($flat, null, $quality);
                imagedestroy($flat);
                $ext = 'jpg';
            }
            $data = ob_get_clean();

            if ($data !== false && strlen($data) <= $max_bytes) {
                imagedestroy($resized);
                imagedestroy($src);
                return ['data' => $data, 'ext' => $ext];
            }
        }

        imagedestroy($resized);
        $max_dim = (int)round($max_dim * 0.7); // shrink further and try the quality ladder again
    }

    imagedestroy($src);
    return null; // couldn't get under budget even at a small size / lowest quality
}

/**
 * Redirect back to a dashboard section with an optional flash message.
 * Call before any HTML output.
 */
function redirect_section(string $section, string $success = '', string $error = ''): never {
    $qs = http_build_query(array_filter([
        'section' => $section,
        'success' => $success,
        'error'   => $error,
    ]));
    header('Location: dashboard.php?' . $qs);
    exit;
}

/**
 * Generate a UUID-style unique identifier with a given prefix.
 * e.g.  uid('std')  →  "std-67a3f1c2b4d5e"
 */
/**
 * Generate the next sequential admission number for a school: ADM-YYYY-0001
 */
function generate_admission_number(PDO $pdo, string $school_uuid): string {
    $prefix = 'ADM-' . date('Y') . '-';
    try {
        $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(admission_number, 10) AS UNSIGNED)) FROM students WHERE school_uuid=? AND admission_number LIKE ?");
        $mx->execute([$school_uuid, $prefix . '%']);
        $next = (int)$mx->fetchColumn() + 1;
    } catch (Exception $e) { $next = 1; }
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function uid(string $prefix = 'rec'): string {
    return $prefix . '-' . bin2hex(random_bytes(7));
}

/**
 * Safely cast a value to int, clamped to ≥0.
 */
function safe_int(mixed $v, int $default = 0): int {
    $i = (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
    return max(0, $i ?: $default);
}

/**
 * Return a string trimmed and stripped of null bytes; empty-safe.
 */
function safe_str(mixed $v, string $default = ''): string {
    return trim(str_replace("\0", '', (string)($v ?? ''))) ?: $default;
}

/**
 * Validate & sanitise a hex colour code; returns fallback on failure.
 */
function safe_color(string $v, string $fallback = '#4F46E5'): string {
    return preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $v) ? $v : $fallback;
}

/**
 * Resolve a project-root-relative stored path (e.g. "admin/uploads/photos/
 * students/std_xxx.jpg" or "uploads/school_logos/logo_xxx.jpg", as produced
 * by handle_image_upload()) into a src usable from whatever page is
 * currently rendering it — root-level pages (login.php, verify-id.php,
 * parent-portal.php, student-portal.php), admin/*.php, or platform/*.php.
 *
 * This is the fix for photo_path/logo_path rendering inconsistently
 * depending on which page displayed it: previously every render site did
 * `echo htmlspecialchars($row['photo_path'])` directly, which only
 * happened to resolve correctly from admin/sections/*.php (because the old
 * upload code computed paths relative to admin/, not the true root). Any
 * root-level page showing the same value pointed at the wrong location.
 * Always call this instead of echoing photo_path/logo_path raw.
 */
function asset_url(?string $stored_path): string {
    $stored_path = trim((string)$stored_path);
    if ($stored_path === '') return '';
    if (preg_match('#^(https?:)?//#i', $stored_path)) return $stored_path; // already an absolute URL
    $stored_path = ltrim($stored_path, '/');

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    foreach (['/admin/', '/platform/'] as $one_level_down) {
        if (str_contains($script, $one_level_down)) {
            return '../' . $stored_path;
        }
    }
    return $stored_path; // root-level page — no prefix needed
}

/**
 * THEME-FIX: Generate a Tailwind-style shade ramp (300/400/500/600/700) from
 * a single school-picked accent hex colour, and emit CSS var overrides.
 *
 * Why this works site-wide with zero per-page edits: Tailwind v4 compiles
 * utility classes like `bg-indigo-600` to `background-color: var(--color-indigo-600)`
 * rather than a hardcoded hex. Every button/badge/active-state across all
 * ~90 files already uses the indigo-300..700 scale, so overriding those five
 * CSS variables at :root retints the entire app to the school's chosen
 * accent colour without touching a single Tailwind class anywhere else.
 */
function accent_shade_vars(string $hex): string {
    $hex = ltrim(safe_color($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $mix = function (int $r, int $g, int $b, float $amt, bool $toWhite): string {
        $target = $toWhite ? 255 : 0;
        $nr = (int)round($r + ($target - $r) * $amt);
        $ng = (int)round($g + ($target - $g) * $amt);
        $nb = (int)round($b + ($target - $b) * $amt);
        return sprintf('#%02x%02x%02x', max(0, min(255, $nr)), max(0, min(255, $ng)), max(0, min(255, $nb)));
    };

    $c300 = $mix($r, $g, $b, 0.45, true);
    $c400 = $mix($r, $g, $b, 0.20, true);
    $c500 = $mix($r, $g, $b, 0.06, true);
    $c600 = sprintf('#%02x%02x%02x', $r, $g, $b);
    $c700 = $mix($r, $g, $b, 0.20, false);

    return "--color-indigo-300:{$c300};--color-indigo-400:{$c400};--color-indigo-500:{$c500};"
         . "--color-indigo-600:{$c600};--color-indigo-700:{$c700};--brand-color:{$c600};";
}

/**
 * Handle a single image file upload.
 * Returns the relative path string on success, or $existing_path on failure/skip.
 * On failure, the reason is written into the by-reference $error parameter
 * (instead of the old $GLOBALS['photo_upload_error'] side-channel, which was
 * fragile — two uploads in the same request could overwrite each other's
 * error). Pass a variable by reference to capture it:
 *
 *     $error = null;
 *     $photo = handle_image_upload('student_photo', $dir, 'std_', '', 5_242_880, $error);
 *     if ($error) { ... }
 *
 * @param string      $field          $_FILES key
 * @param string      $dest_dir       Absolute directory (created if missing)
 * @param string      $file_prefix    Prefix for generated filename e.g. "std_"
 * @param string      $existing_path  Current DB value — returned unchanged if no new upload
 * @param int         $max_bytes      Default 5 MB
 * @param string|null $error          Out param: failure reason, or null on success/no-upload
 */
function handle_image_upload(
    string $field,
    string $dest_dir,
    string $file_prefix = 'img_',
    string $existing_path = '',
    int    $max_bytes = 5_242_880,
    ?string &$error = null
): string {
    // Map of real image signatures we accept, keyed by the extension we'll save as.
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    $error = null;

    // No file field posted at all (e.g. plain edit with no new photo chosen) — not an error.
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing_path;
    }

    $file = $_FILES[$field];

    // Translate PHP's upload error codes into something a user can act on.
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'Photo was not saved — the file is larger than this server allows (max ' . round($max_bytes / 1_048_576, 1) . 'MB).',
            UPLOAD_ERR_PARTIAL => 'Photo was not saved — the upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION =>
                'Photo was not saved — a server storage error occurred. Please contact support.',
            default => 'Photo was not saved — the upload failed (error code ' . $file['error'] . ').',
        };
        return $existing_path;
    }

    if ($file['size'] > $max_bytes) {
        $error = 'Photo was not saved — file is too large (max ' . round($max_bytes / 1_048_576, 1) . 'MB).';
        return $existing_path;
    }

    // Don't trust the client-supplied Content-Type header (unreliable across
    // phones/browsers) — inspect the actual file bytes instead.
    $real_mime = @mime_content_type($file['tmp_name']);
    if ($real_mime === false || !isset($allowed_mimes[$real_mime])) {
        // Fall back to getimagesize() in case mime_content_type/fileinfo isn't available
        $gis = @getimagesize($file['tmp_name']);
        $real_mime = $gis['mime'] ?? $real_mime;
    }
    if (!isset($allowed_mimes[$real_mime])) {
        $error = 'Photo was not saved — only JPG, PNG, or WEBP images are accepted.';
        return $existing_path;
    }

    if (!is_dir($dest_dir) && !mkdir($dest_dir, 0755, true) && !is_dir($dest_dir)) {
        $error = 'Photo was not saved — could not create upload folder on the server.';
        return $existing_path;
    }
    if (!is_writable($dest_dir)) {
        $error = 'Photo was not saved — upload folder is not writable on the server.';
        return $existing_path;
    }

    $ext_hint = $allowed_mimes[$real_mime]; // kept for reference; final ext decided by the compressor

    $compressed = compress_image_to_limit($file['tmp_name'], $real_mime, MAX_UPLOAD_IMAGE_BYTES);
    if ($compressed === null) {
        $error = 'Photo was not saved — we could not compress this image under our '
            . round(MAX_UPLOAD_IMAGE_BYTES / 1024) . 'KB limit. Please try a smaller or simpler photo '
            . '(this is rare — usually only happens with very "busy"/high-detail images at small sizes).';
        return $existing_path;
    }

    $filename = $file_prefix . bin2hex(random_bytes(6)) . '.' . $compressed['ext'];

    if (file_put_contents($dest_dir . $filename, $compressed['data']) !== false) {
        // Work out the relative (web-facing) path from the TRUE project
        // root. Helpers.php lives at admin/lib/Helpers.php, so the actual
        // project root is two levels up (dirname(dirname(__DIR__))) — not
        // one level up. The previous dirname(__DIR__) pointed at admin/,
        // which only happened to work for uploads that land under
        // admin/uploads/... (student/staff/parent photos, called from
        // admin/actions/*). It silently broke for the school-logo upload in
        // platform/settings.php, whose dest_dir is project_root/uploads/
        // school_logos/ — outside admin/ entirely — sending it down the
        // wrong fallback branch. Storing true-root-relative paths here and
        // resolving them per-page with asset_url() fixes both cases.
        $project_root = realpath(dirname(dirname(__DIR__)));
        $saved_real   = realpath($dest_dir . $filename);
        if ($project_root !== false && $saved_real !== false && str_starts_with($saved_real, $project_root)) {
            $rel = ltrim(substr($saved_real, strlen($project_root)), '/\\');
        } else {
            // Fallback: last two path segments (e.g. "students/std_xxx.jpg")
            // prefixed with the standard uploads path, in case realpath fails.
            $rel = 'uploads/photos/' . basename(rtrim($dest_dir, '/\\')) . '/' . $filename;
        }
        return $rel;
    }

    $error = 'Photo was not saved — the server could not write the uploaded file.';
    return $existing_path;
}

/**
 * Render a simple pagination bar and return the LIMIT/OFFSET values.
 * Returns ['limit' => int, 'offset' => int, 'page' => int, 'total_pages' => int].
 *
 * @param int    $total_rows   Total row count from COUNT(*)
 * @param int    $per_page     Rows per page (default 25)
 * @param string $section      Current section name (used to build URLs)
 * @param array  $extra_params Extra GET params to include in page links
 */
function paginate(int $total_rows, int $per_page = 25, string $section = '', array $extra_params = []): array {
    $page        = max(1, (int)($_GET['page'] ?? 1));
    $total_pages = max(1, (int)ceil($total_rows / $per_page));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $per_page;

    return [
        'limit'       => $per_page,
        'offset'      => $offset,
        'page'        => $page,
        'total_pages' => $total_pages,
        'total_rows'  => $total_rows,
        'section'     => $section,
        'extra'       => $extra_params,
    ];
}

/**
 * Echo a rendered pagination control.
 * $pg is the array returned by paginate().
 */
function render_pagination(array $pg): void {
    if ($pg['total_pages'] <= 1) return;

    $base = array_merge(['section' => $pg['section']], $pg['extra']);
    echo '<div class="flex items-center justify-between text-xs text-[var(--text-secondary)] pt-4 border-t border-[var(--border-color)]">';
    echo '<span>Page ' . $pg['page'] . ' of ' . $pg['total_pages'] . ' &nbsp;·&nbsp; ' . number_format($pg['total_rows']) . ' records</span>';
    echo '<div class="flex items-center gap-1">';

    for ($i = 1; $i <= $pg['total_pages']; $i++) {
        if (abs($i - $pg['page']) > 2 && $i !== 1 && $i !== $pg['total_pages']) {
            if ($i === $pg['page'] - 3 || $i === $pg['page'] + 3) echo '<span class="px-1">…</span>';
            continue;
        }
        $url    = 'dashboard.php?' . http_build_query(array_merge($base, ['page' => $i]));
        $active = ($i === $pg['page']) ? 'bg-indigo-600 text-white' : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-white';
        echo '<a href="' . htmlspecialchars($url) . '" class="px-2.5 py-1 rounded-lg font-bold ' . $active . '">' . $i . '</a>';
    }

    echo '</div></div>';
}

/**
 * Determine whether attendance may be marked (auto or manual) for a given
 * school/date, per Phase 3 rules:
 *   - a term must be explicitly opened by the school admin (academic_terms.is_open),
 *   - the date must not be a public holiday,
 *   - the date must be a school day — either explicitly marked so in
 *     school_calendar_days, or (when no override exists) a weekday.
 * Returns ['allowed' => bool, 'reason' => string].
 */
function attendanceMarkable(PDO $pdo, string $school_uuid, string $date): array {
    try {
        $t = $pdo->prepare("SELECT COUNT(*) FROM academic_terms WHERE school_uuid=? AND is_current=1 AND is_open=1");
        $t->execute([$school_uuid]);
        if ((int)$t->fetchColumn() === 0) {
            return ['allowed' => false, 'reason' => 'No term is currently open.'];
        }

        $c = $pdo->prepare("SELECT is_school_day, is_public_holiday, title FROM school_calendar_days WHERE school_uuid=? AND calendar_date=? LIMIT 1");
        $c->execute([$school_uuid, $date]);
        $cal = $c->fetch();

        if ($cal) {
            if ((int)$cal['is_public_holiday'] === 1) {
                return ['allowed' => false, 'reason' => 'Public holiday' . ($cal['title'] ? (': ' . $cal['title']) : '.')];
            }
            if ((int)$cal['is_school_day'] === 0) {
                return ['allowed' => false, 'reason' => 'Not a school day.'];
            }
            return ['allowed' => true, 'reason' => ''];
        }

        // No override on record — default to weekdays only.
        $is_weekday = (int)date('N', strtotime($date)) <= 5;
        return $is_weekday
            ? ['allowed' => true, 'reason' => '']
            : ['allowed' => false, 'reason' => 'Weekend.'];
    } catch (Exception $e) {
        // Fail safe: don't silently mark attendance if the rule check itself breaks.
        return ['allowed' => false, 'reason' => 'Could not verify attendance rules.'];
    }
}

/**
 * Return true when a given user has "Class Teacher" scope for a school —
 * i.e. an active class_teacher_assignments row for the current session/term.
 * Used to auto-grant write access to Attendance, Affective/Psychomotor
 * domains, and teacher comments (Phase 6 also draws on this).
 */
function isClassTeacherOf(PDO $pdo, string $staff_uuid, string $school_uuid, ?string $class_name = null, ?string $arm_name = null): bool {
    try {
        $sql = "SELECT COUNT(*) FROM class_teacher_assignments cta
                JOIN academic_sessions s ON s.school_uuid = cta.school_uuid AND s.session_name = cta.session_name AND s.is_current = 1
                JOIN academic_terms t ON t.school_uuid = cta.school_uuid AND t.term_name = cta.term_name AND t.is_current = 1
                WHERE cta.school_uuid = ? AND cta.staff_uuid = ?";
        $params = [$school_uuid, $staff_uuid];
        if ($class_name !== null) { $sql .= " AND cta.class_name = ?"; $params[] = $class_name; }
        if ($arm_name   !== null) { $sql .= " AND cta.arm_name = ?";   $params[] = $arm_name; }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn() > 0;
    } catch (Exception $e) { return false; }
}

/**
 * Platform-set ceiling for a feature at this school: hide/read/write/full.
 * Falls back to 'full' (i.e. no restriction) if the school has no row yet,
 * so existing schools aren't locked out before the platform manager configures them.
 * (Moved here from dashboard.php in Phase 4 so standalone API endpoints can reuse it.)
 */
function getSchoolFeatureCeiling(string $fk, string $s_uuid): string {
    global $pdo;
    static $cache = [];
    if (isset($cache[$s_uuid][$fk])) return $cache[$s_uuid][$fk];
    $level = 'full';
    try {
        $st = $pdo->prepare("SELECT is_enabled, access_level FROM school_feature_access WHERE school_uuid=? AND feature_key=? LIMIT 1");
        $st->execute([$s_uuid, $fk]);
        $row = $st->fetch();
        if ($row) {
            $level = ((int)$row['is_enabled'] === 0) ? 'hide' : $row['access_level'];
        }
    } catch (Exception $e) { /* keep default */ }
    return $cache[$s_uuid][$fk] = $level;
}

/** Rank access levels so ceilings/overrides can be compared/capped. */
function accessLevelRank(string $level): int {
    return match ($level) { 'hide' => 0, 'read' => 1, 'write' => 2, 'full' => 3, default => 0 };
}

function capAccessLevel(string $level, string $ceiling): string {
    return accessLevelRank($level) <= accessLevelRank($ceiling) ? $level : $ceiling;
}

/**
 * Subjects (and their class/arm scope) a staff member is assigned to teach
 * for the given session/term, from staff_subject_assignments. Returns an
 * array of subject_name strings (deduped) when class_name is provided, or
 * an array of ['subject_name','class_name','arm_name'] rows when it's not.
 */
function getTeacherSubjects(PDO $pdo, string $staff_uuid, string $school_uuid, ?string $class_name, string $session_name, string $term_name): array {
    try {
        $sql = "SELECT subject_name, class_name, arm_name FROM staff_subject_assignments
                WHERE school_uuid=? AND staff_uuid=? AND session_name=? AND term_name=?";
        $params = [$school_uuid, $staff_uuid, $session_name, $term_name];
        if ($class_name !== null) { $sql .= " AND class_name=?"; $params[] = $class_name; }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        return $class_name !== null ? array_values(array_unique(array_column($rows, 'subject_name'))) : $rows;
    } catch (Exception $e) { return []; }
}

/**
 * Phase 6 auto-grants, layered on top of the ordinary staff/role permission
 * resolution:
 *   - A Class Teacher (class_teacher_assignments, current session/term)
 *     is floored to at least 'write' on Attendance and Report Cards (which
 *     covers affective/psychomotor domain ratings and teacher comments).
 *   - A Subject Teacher (staff_subject_assignments, current session/term)
 *     is floored to at least 'write' on Results — entering their own
 *     subject's scores. This deliberately does NOT extend to the
 *     Broadsheet: that shows every subject and every student's class rank
 *     side by side, which is whole-class-level visibility a subject or
 *     class teacher shouldn't get just for holding either assignment. A
 *     School Admin can still explicitly grant Broadsheet access to a
 *     specific staff member or role via Roles & Permissions if a school
 *     genuinely wants that.
 * Both are still capped by the platform's ceiling for the school.
 */
function applyAutoGrants(PDO $pdo, string $fk, string $level, string $staff_uuid, string $school_uuid, string $ceiling): string {
    if ($staff_uuid === '' || accessLevelRank($level) >= accessLevelRank('write')) return $level;

    try {
        $sq = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid=? AND is_current=1 LIMIT 1");
        $sq->execute([$school_uuid]); $session = $sq->fetchColumn();
        $tq = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid=? AND is_current=1 LIMIT 1");
        $tq->execute([$school_uuid]); $term = $tq->fetchColumn();
        if (!$session || !$term) return $level;

        if (in_array($fk, ['attendance', 'report_cards'], true)) {
            $ct = $pdo->prepare("SELECT COUNT(*) FROM class_teacher_assignments WHERE school_uuid=? AND staff_uuid=? AND session_name=? AND term_name=?");
            $ct->execute([$school_uuid, $staff_uuid, $session, $term]);
            if ((int)$ct->fetchColumn() > 0) return capAccessLevel('write', $ceiling);
        }

        if ($fk === 'results') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM staff_subject_assignments WHERE school_uuid=? AND staff_uuid=? AND session_name=? AND term_name=?");
            $st->execute([$school_uuid, $staff_uuid, $session, $term]);
            if ((int)$st->fetchColumn() > 0) return capAccessLevel('write', $ceiling);
        }
    } catch (Exception $e) { /* leave level as resolved */ }

    return $level;
}

/**
 * Class(es)/arm(s) a staff member is the class teacher of for the given
 * session/term (defaults to the school's current session/term). Used to
 * restrict a class teacher's report card view to their own class/arm —
 * they get the Report Cards auto-grant above, but that should only cover
 * the class they actually teach, not every class in the school.
 * Returns an array of ['class_name' => ..., 'arm_name' => ...] rows.
 */
function getClassTeacherClasses(PDO $pdo, string $staff_uuid, string $school_uuid, ?string $session_name = null, ?string $term_name = null): array {
    if ($staff_uuid === '') return [];
    try {
        if ($session_name === null || $term_name === null) {
            $sq = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid=? AND is_current=1 LIMIT 1");
            $sq->execute([$school_uuid]); $session_name = $session_name ?? $sq->fetchColumn();
            $tq = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid=? AND is_current=1 LIMIT 1");
            $tq->execute([$school_uuid]); $term_name = $term_name ?? $tq->fetchColumn();
        }
        if (!$session_name || !$term_name) return [];
        $st = $pdo->prepare("SELECT class_name, arm_name FROM class_teacher_assignments WHERE school_uuid=? AND staff_uuid=? AND session_name=? AND term_name=?");
        $st->execute([$school_uuid, $staff_uuid, $session_name, $term_name]);
        return $st->fetchAll();
    } catch (Exception $e) { return []; }
}

/**
 * Resolve what a staff member may do with a feature:
 *   staff-specific override → role default → hide,
 *   then Phase 6 auto-grants (class teacher / subject teacher) can raise it,
 *   capped by the platform-set ceiling for this school.
 */
function getFeatureAccessLevel(string $fk, string $role, string $u_uuid, string $s_uuid): string {
    global $pdo;
    $ceiling = getSchoolFeatureCeiling($fk, $s_uuid);
    if ($role === 'Platform Manager') return 'full';
    if ($role === 'School Admin') return $ceiling === 'hide' ? 'hide' : 'full';

    try {
        $st = $pdo->prepare("SELECT sfp.access_level, st.staff_uuid FROM staff_feature_permissions sfp
            JOIN staff st ON st.staff_uuid = sfp.staff_uuid
            WHERE sfp.school_uuid=? AND st.user_uuid=? AND sfp.feature_key=? LIMIT 1");
        $st->execute([$s_uuid, $u_uuid, $fk]);
        $row = $st->fetch();
        $level = $row['access_level'] ?? false;
        if ($level === false) {
            $rp = $pdo->prepare("SELECT access_level FROM role_permissions WHERE school_uuid=? AND role_name=? AND feature_key=? LIMIT 1");
            $rp->execute([$s_uuid, $role, $fk]);
            $level = $rp->fetchColumn() ?: 'hide';
        }

        $staff_uuid_lookup = $pdo->prepare("SELECT staff_uuid FROM staff WHERE user_uuid=? AND school_uuid=? LIMIT 1");
        $staff_uuid_lookup->execute([$u_uuid, $s_uuid]);
        $staff_uuid = $staff_uuid_lookup->fetchColumn() ?: '';
        $level = applyAutoGrants($pdo, $fk, $level, $staff_uuid, $s_uuid, $ceiling);
    } catch (Exception $e) { $level = 'hide'; }

    return capAccessLevel($level, $ceiling);
}

function isSectionEnabled(string $key, string $s_uuid): bool {
    return getSchoolFeatureCeiling($key, $s_uuid) !== 'hide';
}

/**
 * Gate QR passes — Phase 4.
 * Each pass encodes {person type, person uuid, school uuid, date} plus an
 * HMAC signature keyed on a per-school secret, so a photocopied/reused pass
 * from a previous day can be detected as expired rather than silently
 * accepted, and the payload can't be hand-edited to impersonate someone else
 * or backdate/forward-date itself.
 */
function getSchoolGateSecret(PDO $pdo, string $school_uuid): string {
    $st = $pdo->prepare("SELECT gate_qr_secret FROM schools WHERE school_uuid=? LIMIT 1");
    $st->execute([$school_uuid]);
    $secret = $st->fetchColumn();
    if (!$secret) {
        $secret = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE schools SET gate_qr_secret=? WHERE school_uuid=?")->execute([$secret, $school_uuid]);
    }
    return $secret;
}

/** Build a printable gate-pass code for a person, valid for the given date (default: today). */
function buildGatePassCode(PDO $pdo, string $school_uuid, string $person_type, string $person_uuid, ?string $date = null): string {
    $date = $date ?: date('Y-m-d');
    $secret = getSchoolGateSecret($pdo, $school_uuid);
    $payload = base64_encode(json_encode(['t' => $person_type, 'u' => $person_uuid, 's' => $school_uuid, 'd' => $date]));
    $sig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    return $payload . '.' . $sig;
}

/**
 * Decode and validate a scanned gate-pass code.
 * Returns ['ok'=>bool, 'person_type'=>, 'person_uuid'=>, 'qr_date'=>, 'expired'=>bool, 'reason'=>string]
 */
function parseGatePassCode(PDO $pdo, string $school_uuid, string $code): array {
    $parts = explode('.', trim($code));
    if (count($parts) !== 2) return ['ok' => false, 'reason' => 'Unrecognized code format.'];
    [$payload, $sig] = $parts;

    $secret = getSchoolGateSecret($pdo, $school_uuid);
    $expected_sig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    if (!hash_equals($expected_sig, $sig)) {
        return ['ok' => false, 'reason' => 'Invalid or tampered code.'];
    }

    $data = json_decode(base64_decode($payload), true);
    if (!$data || empty($data['u']) || empty($data['t']) || empty($data['d']) || ($data['s'] ?? '') !== $school_uuid) {
        return ['ok' => false, 'reason' => 'Code does not belong to this school.'];
    }

    $today = date('Y-m-d');
    $expired = $data['d'] !== $today;

    return [
        'ok' => true,
        'person_type'  => $data['t'],
        'person_uuid'  => $data['u'],
        'qr_date'      => $data['d'],
        'expired'      => $expired,
        'reason'       => $expired ? "This pass was printed for {$data['d']}, not today ({$today})." : '',
    ];
}

/**
 * Printed ID card QR — permanent (no date bound, unlike the daily gate pass),
 * but HMAC-signed with the same per-school secret so the payload can't be
 * hand-edited to impersonate a different person/school. Verify with
 * parseIdCardCode() before trusting a scanned ID card for anything sensitive
 * (e.g. library checkout by ID card).
 */
function buildIdCardCode(PDO $pdo, string $school_uuid, string $person_type, string $person_uuid): string {
    $secret = getSchoolGateSecret($pdo, $school_uuid);
    $payload = base64_encode(json_encode(['type' => $person_type, 'uid' => $person_uuid, 'school' => $school_uuid]));
    $sig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    return $payload . '.' . $sig;
}

/**
 * A per-school, non-person-specific "gate location" code — printed once and
 * posted at the entrance. Staff scan it from inside their own portal to
 * self-check-in (we already know who they are from their session); the
 * same code (via its embedded URL) also serves as the visitor kiosk entry
 * point. Distinct from buildGatePassCode()/buildIdCardCode(), which are
 * per-person codes carried by the student/staff member instead.
 */
function buildSchoolGateLocationCode(PDO $pdo, string $school_uuid): string {
    $secret = getSchoolGateSecret($pdo, $school_uuid);
    $payload = base64_encode(json_encode(['gate' => $school_uuid]));
    $sig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    return $payload . '.' . $sig;
}

/** Validate a scanned gate-location code and return the school_uuid it belongs to, or null. */
function parseSchoolGateLocationCode(PDO $pdo, string $expected_school_uuid, string $code): ?string {
    $parts = explode('.', trim($code));
    if (count($parts) !== 2) return null;
    [$payload, $sig] = $parts;
    $secret = getSchoolGateSecret($pdo, $expected_school_uuid);
    $expected_sig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    if (!hash_equals($expected_sig, $sig)) return null;
    $data = json_decode(base64_decode($payload), true);
    if (empty($data['gate']) || $data['gate'] !== $expected_school_uuid) return null;
    return $data['gate'];
}

/** Decode and validate a scanned/printed ID card code. */
function parseIdCardCode(PDO $pdo, string $school_uuid, string $code): array {
    $parts = explode('.', trim($code));
    if (count($parts) !== 2) return ['ok' => false, 'reason' => 'Unrecognized code format.'];
    [$payload, $sig] = $parts;

    $secret = getSchoolGateSecret($pdo, $school_uuid);
    $expected_sig = substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    if (!hash_equals($expected_sig, $sig)) {
        return ['ok' => false, 'reason' => 'Invalid or tampered ID card code.'];
    }

    $data = json_decode(base64_decode($payload), true);
    if (!$data || empty($data['uid']) || empty($data['type']) || ($data['school'] ?? '') !== $school_uuid) {
        return ['ok' => false, 'reason' => 'Code does not belong to this school.'];
    }

    return ['ok' => true, 'person_type' => $data['type'], 'person_uuid' => $data['uid']];
}

function can_manage(string $role, string $access_level): bool {
    return $role === 'School Admin' || $role === 'Platform Manager'
        || $access_level === 'write' || $access_level === 'full';
}

/**
 * Return true only for 'full' access — used to gate approval/release actions
 * (approving assignments, releasing results, etc.) that even a write-level
 * staff member should not be able to perform on their own.
 */
function can_approve(string $role, string $access_level): bool {
    return $role === 'School Admin' || $role === 'Platform Manager' || $access_level === 'full';
}

/**
 * Look up the current user's access level for an arbitrary feature key,
 * independent of whichever section is in the URL right now. Action
 * handlers must use this (not the page-level $current_access) so a
 * POST crafted with a mismatched ?section= can't borrow another
 * feature's access level.
 */
function feature_access(string $feature_key): string {
    global $active_role, $user_uuid, $school_uuid;
    return getFeatureAccessLevel($feature_key, $active_role, $user_uuid, $school_uuid);
}

/** Convenience: can the current user write to $feature_key? */
function can_manage_feature(string $feature_key): bool {
    global $active_role;
    return can_manage($active_role, feature_access($feature_key));
}

// ── CSRF protection ─────────────────────────────────────────────────────────
/** Get (or create) this session's CSRF token. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden <input> to drop inside every POST form. */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/** Verify a submitted token against the session's token (constant-time). */
function csrf_verify(?string $submitted): bool {
    return is_string($submitted) && hash_equals(csrf_token(), $submitted);
}

// ── Password policy ─────────────────────────────────────────────────────────
/** Minimum bar for any password we let a human choose. Returns '' if OK, else an error message. */
function password_policy_check(string $password): string {
    if (strlen($password) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must include both letters and numbers.';
    }
    $common = ['password', 'password123', '12345678', 'qwerty123', 'letmein123'];
    if (in_array(strtolower($password), $common, true)) return 'That password is too common — please choose another.';
    return '';
}

/** Generate a random, human-typeable temporary password (e.g. for new staff/parent accounts). */
function generate_temp_password(): string {
    $words = ['Cedar','River','Amber','Pilot','Delta','Maple','Falcon','Coral','Ember','Vista'];
    return $words[array_rand($words)] . random_int(100, 999) . '!' . chr(random_int(97, 122));
}

// ── Login rate limiting ─────────────────────────────────────────────────────
/**
 * Returns true if this email is currently locked out from further login
 * attempts (too many recent failures), false otherwise.
 */
function is_login_locked_out(PDO $pdo, string $email): bool {
    try {
        $st = $pdo->prepare("SELECT failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $row = $st->fetch();
        if (!$row || empty($row['locked_until'])) return false;
        return strtotime($row['locked_until']) > time();
    } catch (Exception $e) { return false; }
}

/** Record a failed login attempt; locks the account for 15 minutes after 5 failures. */
function record_failed_login(PDO $pdo, string $email): void {
    try {
        $st = $pdo->prepare("SELECT failed_login_attempts FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $attempts = (int)($st->fetchColumn() ?: 0) + 1;
        $locked_until = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
        $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE email = ?")
            ->execute([$attempts, $locked_until, $email]);
    } catch (Exception $e) {}
}

/** Clear the failed-attempt counter after a successful login. */
function reset_failed_login(PDO $pdo, string $email): void {
    try {
        $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE email = ?")->execute([$email]);
    } catch (Exception $e) {}
}

/**
 * Turn a caught exception into a safe, generic message for the browser
 * while preserving full detail server-side. Use instead of concatenating
 * $e->getMessage() straight into $error_msg / flash messages — the raw
 * exception can include table/column names or query fragments.
 */
function safe_error(string $context, \Throwable $e): string {
    error_log($context . ': ' . $e->getMessage());
    if (getenv('APP_DEBUG') === '1') {
        return $context . ': ' . $e->getMessage();
    }
    return $context . '. Please try again — if this keeps happening, contact support.';
}

/**
 * Assessment-breakdown columns for print/report views (result slip,
 * transcript, report card preview) — used so those pages render whatever
 * assessments the school actually configured (CA1/CA2/Project/Mock/etc,
 * via Settings → Assessment Configuration) instead of a hardcoded
 * CA1/CA2/Exam split. Falls back to the legacy CA1/CA2/Exam columns for
 * schools that haven't configured dynamic assessments for this class/
 * session/term.
 *
 * Returns:
 *   [
 *     'dynamic' => bool,
 *     'columns' => [ ['key' => config_uuid OR 'ca1'/'ca2'/'exam', 'label' => 'CA1', 'max' => 20], ... ],
 *   ]
 */
/**
 * Stable, deterministic identifier for one school's assessment template
 * (e.g. "CA1"), derived only from the school and the template's name.
 *
 * Previously both admin/sections/results.php (`'tpl-' . md5($name . $school_uuid
 * . time())`) and admin/actions/results-actions.php (`uid('tpl')`) minted a
 * BRAND NEW random id every single page load / save, and never persisted it
 * anywhere. That meant: the score entry page showed one set of field names
 * on load, saved scores under a second, different id, and the next page load
 * generated a third — so previously entered scores appeared to reset to 0,
 * and totals were computed from whatever the current random id happened to
 * match (usually nothing). Assessment names are already unique per school
 * (enforced when templates are saved), so deriving the id from
 * school_uuid + name gives the exact same value every time, with no schema
 * change and no migration needed.
 */
function assessmentTemplateKey(string $school_uuid, string $name): string {
    return 'tpl_' . substr(md5($school_uuid . '|' . mb_strtolower(trim($name))), 0, 20);
}

/**
 * All assessment configurations saved for a school (Settings → Assessment
 * Configuration), decoded once. Single source of truth — the same JSON
 * column that admin/sections/results.php and admin/actions/results-actions.php
 * read and write, so report cards, broadsheets, and print slips always see
 * exactly what results entry sees and saves.
 */
function getSchoolAssessmentConfigs(PDO $pdo, string $school_uuid): array {
    try {
        $q = $pdo->prepare("SELECT assessment_configurations_json FROM school_settings WHERE school_uuid = ?");
        $q->execute([$school_uuid]);
        $raw = $q->fetchColumn();
        $configs = $raw ? json_decode($raw, true) : [];
        return is_array($configs) ? $configs : [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * The assessment columns (CA1, CA2, Exam, or whatever the school configured)
 * that apply to one session/term/class, in the order the school configured
 * them, sourced live from Settings → Assessment Configuration.
 *
 * There is intentionally no legacy CA1/CA2/Exam fallback: if the school
 * hasn't configured any assessments yet for this session/term/class, this
 * returns an empty column list rather than inventing one, so callers can
 * tell the difference and point the admin at Settings instead of silently
 * showing made-up numbers.
 */
function getAssessmentColumns(PDO $pdo, string $school_uuid, string $session_name, string $term_name, string $class_name): array {
    $all_configs = getSchoolAssessmentConfigs($pdo, $school_uuid);

    $matching = [];
    foreach ($all_configs as $cfg) {
        $cfg_class = $cfg['class_name'] ?? '';
        if (($cfg['session_name'] ?? '') === $session_name
            && ($cfg['term_name'] ?? '') === $term_name
            && ($cfg_class === $class_name || $cfg_class === '')) {
            $matching[] = $cfg;
        }
    }
    usort($matching, fn($a, $b) => ($a['assessment_order'] ?? 0) <=> ($b['assessment_order'] ?? 0));

    $columns = [];
    foreach ($matching as $cfg) {
        $name = trim($cfg['template_name'] ?? '');
        if ($name === '') continue;
        $columns[] = [
            'key'   => assessmentTemplateKey($school_uuid, $name),
            'label' => $name,
            'max'   => isset($cfg['max_score']) ? (float)$cfg['max_score'] : null,
        ];
    }

    return ['dynamic' => true, 'configured' => !empty($columns), 'columns' => $columns];
}

/**
 * Per-subject assessment scores for one student, keyed by
 * subject_name => assessmentTemplateKey(...) => score. Pairs with
 * getAssessmentColumns() above — same key derivation, so lookups always
 * line up with whatever's currently configured.
 */
function getStudentDynamicScores(PDO $pdo, string $school_uuid, string $student_uuid, string $session_name, string $term_name): array {
    $out = [];
    try {
        $sq = $pdo->prepare("
            SELECT subject_name, template_uuid, score
            FROM result_assessment_scores
            WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=?
        ");
        $sq->execute([$school_uuid, $student_uuid, $session_name, $term_name]);
        foreach ($sq->fetchAll() as $row) {
            $out[$row['subject_name']][$row['template_uuid']] = (float)$row['score'];
        }
    } catch (Exception $e) {}
    return $out;
}

/**
 * Same as getStudentDynamicScores() but for every student in a class at
 * once (one query instead of N) — used by the broadsheet.
 */
function getClassDynamicScores(PDO $pdo, string $school_uuid, string $session_name, string $term_name, string $class_name): array {
    $out = [];
    try {
        $sq = $pdo->prepare("
            SELECT student_uuid, subject_name, template_uuid, score
            FROM result_assessment_scores
            WHERE school_uuid=? AND session_name=? AND term_name=? AND class_name=?
        ");
        $sq->execute([$school_uuid, $session_name, $term_name, $class_name]);
        foreach ($sq->fetchAll() as $row) {
            $out[$row['student_uuid']][$row['subject_name']][$row['template_uuid']] = (float)$row['score'];
        }
    } catch (Exception $e) {}
    return $out;
}

/**
 * ── Result slip template rendering — shared by print_result_slip.php
 * (a real student's actual slip) and preview_result_slip_template.php
 * (a School Admin previewing a template with sample data before picking
 * it). Both must render a layout_json identically, so the logic lives
 * here once instead of being duplicated and risking drift between what
 * you preview and what actually prints.
 */

/**
 * Turn saved layout_json into a normalized { page, elements[] } shape.
 * Handles the (older) plain array-of-keys format by stacking those fields
 * down the page at default sizes, so pre-existing templates still render —
 * but no built-in layout is ever substituted when no template is selected.
 */
function rst_normalize_layout(?string $raw): ?array {
    if (!$raw) return null;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return null;
    if (isset($decoded['elements'])) {
        return [
            'page' => array_merge(['background_image' => null, 'background_color' => '#ffffff'], $decoded['page'] ?? []),
            'elements' => $decoded['elements'],
        ];
    }
    // Legacy: plain ordered array of block keys (or {key,label} pairs).
    $default_sizes = [
        'school_logo' => [28, 28], 'student_photo' => [26, 32],
        'subjects_table' => [170, 60], 'signature_line' => [45, 14], 'school_name' => [170, 16],
    ];
    $elements = [];
    $y = 10;
    foreach ($decoded as $item) {
        $key = is_array($item) ? ($item['key'] ?? '') : $item;
        if ($key === '') continue;
        [$w, $h] = $default_sizes[$key] ?? [170, 10];
        $elements[] = ['key' => $key, 'x' => 20, 'y' => $y, 'w' => $w, 'h' => $h, 'z' => 1,
            'fontFamily' => 'Georgia, serif', 'fontSize' => 11, 'color' => '#111111',
            'bold' => false, 'italic' => false, 'align' => 'left'];
        $y += $h + 4;
    }
    return ['page' => ['background_image' => null, 'background_color' => '#ffffff'], 'elements' => $elements];
}

/**
 * Render the inner HTML for one field key, given a context array with keys:
 * school, student, results, assess_cols, dynamic_scores, session_name,
 * term_name, total, avg (plus optional position/attendance/remark keys).
 * Returns '' for unknown keys or fields with nothing to show (that element
 * is then skipped entirely on the sheet).
 */
function rst_render_field_html(string $key, array $ctx): string {
    $school = $ctx['school']; $student = $ctx['student']; $results = $ctx['results'];
    $assess_cols = $ctx['assess_cols']; $dynamic_scores = $ctx['dynamic_scores'];
    $session_name = $ctx['session_name']; $term_name = $ctx['term_name'];
    $total = $ctx['total']; $avg = $ctx['avg'];

    switch ($key) {
        case 'school_logo':
            return !empty($school['logo_path']) ? '<img style="max-width:100%;max-height:100%;" src="' . htmlspecialchars(asset_url($school['logo_path'])) . '" alt="Logo">' : '';
        case 'school_name':
            return '<div><div style="font-weight:bold;">' . htmlspecialchars($school['name'] ?? '') . '</div><div>Result Slip</div></div>';
        case 'student_photo':
            return !empty($student['photo_path']) ? '<img style="max-width:100%;max-height:100%;border:1px solid #ccc;border-radius:6px;" src="' . htmlspecialchars(asset_url($student['photo_path'])) . '" alt="Photo">' : '';
        case 'student_name':
            return '<b>Name:</b> ' . htmlspecialchars($student['name']);
        case 'admission_no':
            return '<b>Admission No:</b> ' . htmlspecialchars($student['student_uuid'] ?? $student['roll_number'] ?? '');
        case 'class_arm':
            return '<b>Class:</b> ' . htmlspecialchars(trim(($student['class'] ?? '') . ' ' . ($student['arm'] ?? '')));
        case 'session_term':
            return '<b>Session / Term:</b> ' . htmlspecialchars("$session_name / $term_name");
        case 'subjects_table':
            $html = '<table style="width:100%;height:100%;border-collapse:collapse;font-size:0.9em;"><thead><tr><th style="border:1px solid #ccc;padding:3px 6px;">Subject</th>';
            foreach ($assess_cols['columns'] as $col) {
                $html .= '<th style="border:1px solid #ccc;padding:3px 6px;">' . htmlspecialchars($col['label']) . ($col['max'] !== null ? ' /' . (int)$col['max'] : '') . '</th>';
            }
            $html .= '<th style="border:1px solid #ccc;padding:3px 6px;">Total</th><th style="border:1px solid #ccc;padding:3px 6px;">Grade</th></tr></thead><tbody>';
            foreach ($results as $r) {
                $html .= '<tr><td style="border:1px solid #ccc;padding:3px 6px;">' . htmlspecialchars($r['subject_name']) . '</td>';
                foreach ($assess_cols['columns'] as $col) {
                    $val = $assess_cols['dynamic'] ? ($dynamic_scores[$r['subject_name']][$col['key']] ?? 0) : ($r[$col['key']] ?? 0);
                    $html .= '<td style="border:1px solid #ccc;padding:3px 6px;">' . htmlspecialchars((string)$val) . '</td>';
                }
                $html .= '<td style="border:1px solid #ccc;padding:3px 6px;">' . htmlspecialchars((string)$r['total_score']) . '</td><td style="border:1px solid #ccc;padding:3px 6px;">' . htmlspecialchars($r['grade'] ?? '') . '</td></tr>';
            }
            return $html . '</tbody></table>';
        case 'total_average':
            return '<b>Total / Average:</b> ' . $total . ' / ' . $avg;
        case 'position':
            return '<b>Position:</b> ' . htmlspecialchars((string)($ctx['position'] ?? '—'));
        case 'attendance_summary':
            return '<b>Attendance:</b> ' . htmlspecialchars((string)($ctx['attendance'] ?? '—'));
        case 'affective_domain':
            return '<b>Affective Domain:</b> ' . htmlspecialchars((string)($ctx['affective_note'] ?? '—'));
        case 'psychomotor_domain':
            return '<b>Psychomotor Domain:</b> ' . htmlspecialchars((string)($ctx['psychomotor_note'] ?? '—'));
        case 'class_teacher_remark':
            return '<b>Class Teacher\'s Remark:</b> ' . htmlspecialchars($results[0]['subject_teacher_remark'] ?? ($ctx['teacher_remark'] ?? ''));
        case 'principal_remark':
            return '<b>Principal\'s Remark:</b> ' . htmlspecialchars((string)($ctx['principal_remark'] ?? '—'));
        case 'next_term_begins':
            return '<b>Next Term Begins:</b> ' . htmlspecialchars((string)($ctx['next_term_begins'] ?? '—'));
        case 'signature_line':
            return '<div style="border-top:1px solid #333;padding-top:3px;width:100%;">Authorized Signature</div>';
        default:
            return '';
    }
}

/**
 * Render a full standalone A4 sheet HTML document (doctype through
 * </html>) for a normalized template layout + rendering context. $title
 * appears in the browser tab; $auto_print triggers window.print() on load
 * (used for the real print page, not the preview).
 */
function rst_render_sheet_html(array $template_layout, array $ctx, string $title, bool $auto_print = false): string {
    $page = $template_layout['page'];
    $bg_style = '';
    if (!empty($page['background_image'])) {
        $bg_style .= 'background-image:url(' . htmlspecialchars(asset_url($page['background_image'])) . ');background-size:cover;background-position:center;';
    } else {
        $bg_style .= 'background-color:' . htmlspecialchars($page['background_color'] ?? '#ffffff') . ';';
    }

    $elements_html = '';
    foreach ($template_layout['elements'] as $el) {
        $inner = rst_render_field_html($el['key'], $ctx);
        if ($inner === '') continue;
        $style = sprintf(
            'left:%smm;top:%smm;width:%smm;height:%smm;z-index:%d;font-family:%s;font-size:%dpx;color:%s;font-weight:%s;font-style:%s;text-align:%s;',
            $el['x'], $el['y'], $el['w'], $el['h'], (int)($el['z'] ?? 1),
            htmlspecialchars($el['fontFamily'] ?? 'Georgia, serif'), (int)($el['fontSize'] ?? 11),
            htmlspecialchars($el['color'] ?? '#111111'), !empty($el['bold']) ? 'bold' : 'normal',
            !empty($el['italic']) ? 'italic' : 'normal', htmlspecialchars($el['align'] ?? 'left')
        );
        $elements_html .= '<div class="a4-el" style="' . $style . '">' . $inner . '</div>';
    }

    $print_script = $auto_print ? '<script>window.onload = () => window.print();</script>' : '';

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($title) . '</title>
<style>
 @page { size: A4 portrait; margin: 0; }
 html, body { margin: 0; padding: 0; }
 .a4-sheet { position: relative; width: 210mm; height: 297mm; margin: 0 auto; color: #111; ' . $bg_style . ' }
 .a4-el { position: absolute; box-sizing: border-box; overflow: hidden; padding: 1mm; }
 @media print { body { margin: 0; } }
 @media screen { body { background: #888; padding: 10mm 0; } .a4-sheet { box-shadow: 0 0 0 1px rgba(0,0,0,0.1), 0 8px 24px rgba(0,0,0,0.25); } }
</style></head><body>
<div class="a4-sheet">' . $elements_html . '</div>
' . $print_script . '
</body></html>';
}
