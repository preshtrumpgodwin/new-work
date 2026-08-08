<?php
/**
 * Actions: OMR Sheets & Marking (admin/sections/omr.php)
 * Merged from the old misc-actions.php (create_omr_sheet, save_answer_key,
 * save_omr_evaluation) and omr-scan-actions.php (generate_omr_strips,
 * upload_omr_scan) — every OMR action now lives in one file.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

$omr_upload_dir = __DIR__ . '/../uploads/omr_scans/';
if (!is_dir($omr_upload_dir)) { @mkdir($omr_upload_dir, 0755, true); }

// ── CREATE SHEET ──────────────────────────────────────────────────────────────
if (isset($_POST['action_create_omr_sheet'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('omr')))) { $error_msg = 'You do not have permission to create OMR sheets.'; return; }
    $title = safe_str($_POST['exam_title'] ?? '');
    $class_name = safe_str($_POST['class_name'] ?? '');
    $total_q = max(1, safe_int($_POST['total_questions'] ?? 20));
    if ($title && $class_name) {
        $uuid = uid('sheet');
        $pdo->prepare("INSERT INTO omr_sheets (sheet_uuid,school_uuid,exam_title,class_name,total_questions,generated_by) VALUES (?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$title,$class_name,$total_q,$_SESSION['name']??'Admin']);
        $success_msg = "Sheet '$title' created! Now set the answer key.";
    } else { $error_msg = 'Exam title and class are required.'; }
}

// ── ANSWER KEY ─────────────────────────────────────────────────────────────────
if (isset($_POST['action_save_answer_key'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('omr')))) { $error_msg = 'You do not have permission to set answer keys.'; return; }
    $sheet_uuid = safe_str($_POST['sheet_uuid'] ?? '');
    $keys = $_POST['key'] ?? [];
    if ($sheet_uuid && !empty($keys)) {
        foreach ($keys as $qn => $opt) {
            $qn = (int)$qn; $opt = strtoupper(safe_str($opt));
            if (!$qn || !in_array($opt, ['A','B','C','D'], true)) continue;
            $uuid = uid('key');
            $pdo->prepare("INSERT INTO omr_answer_keys (key_uuid,school_uuid,sheet_uuid,question_number,correct_option)
                VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE correct_option=VALUES(correct_option)")
                ->execute([$uuid,$school_uuid,$sheet_uuid,$qn,$opt]);
        }
        $success_msg = 'Answer key saved!';
    } else { $error_msg = 'No answer key data submitted.'; }
}

// ── GENERATE PRINTABLE STRIPS FOR A CLASS ────────────────────────────────────
if (isset($_POST['action_generate_omr_strips'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('omr')))) {
        $error_msg = 'You do not have permission to generate OMR strips.'; return;
    }
    $sheet_uuid = safe_str($_POST['sheet_uuid'] ?? '');
    $sq = $pdo->prepare("SELECT * FROM omr_sheets WHERE sheet_uuid=? AND school_uuid=?");
    $sq->execute([$sheet_uuid, $school_uuid]);
    $sheet = $sq->fetch();

    if (!$sheet) {
        $error_msg = 'Sheet not found.';
    } else {
        $stq = $pdo->prepare("SELECT student_uuid, name, roll_number FROM students WHERE school_uuid=? AND class=? AND status='Active' ORDER BY name ASC");
        $stq->execute([$school_uuid, $sheet['class_name']]);
        $students = $stq->fetchAll();

        $existq = $pdo->prepare("SELECT student_uuid FROM omr_sheet_students WHERE sheet_uuid=?");
        $existq->execute([$sheet_uuid]);
        $already = array_flip($existq->fetchAll(PDO::FETCH_COLUMN));

        // Serial codes must be unique per sheet; base them on a running
        // counter for this sheet rather than random, so they're short,
        // stable, and easy to sanity-check by eye if ever needed.
        $cntq = $pdo->prepare("SELECT COUNT(*) FROM omr_sheet_students WHERE sheet_uuid=?");
        $cntq->execute([$sheet_uuid]);
        $next_serial = (int)$cntq->fetchColumn();

        $created = 0;
        foreach ($students as $st) {
            if (isset($already[$st['student_uuid']])) continue;
            $next_serial++;
            $serial_code = str_pad((string)$next_serial, 6, '0', STR_PAD_LEFT);
            $ssuuid = uid('sts');
            $pdo->prepare("INSERT INTO omr_sheet_students (sheet_student_uuid, school_uuid, sheet_uuid, student_uuid, student_name, roll_number, serial_code) VALUES (?,?,?,?,?,?,?)")
                ->execute([$ssuuid, $school_uuid, $sheet_uuid, $st['student_uuid'], $st['name'], $st['roll_number'] ?? '', $serial_code]);
            $created++;
        }
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'omr.generate_strips', $sheet_uuid, "$created strip(s) generated for {$sheet['class_name']}");
        $success_msg = $created > 0
            ? "Generated $created printable strip(s) for {$sheet['class_name']}. Go to Print to print them."
            : 'Every active student in this class already has a strip. Go to Print to reprint, or remove students to regenerate.';
    }
}

// ── MANUAL EVALUATION ENTRY ─────────────────────────────────────────────────
if (isset($_POST['action_save_omr_evaluation'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('omr')))) { $error_msg = 'You do not have permission to save OMR evaluations.'; return; }
    $s_uuid   = safe_str($_POST['student_uuid']      ?? '');
    $s_name   = safe_str($_POST['student_name']      ?? '');
    $title    = safe_str($_POST['exam_title']        ?? '');
    $sheet_uuid = safe_str($_POST['sheet_uuid']      ?? '');
    $submitted = $_POST['answer'] ?? []; // ['1'=>'A','2'=>'C', ...] from the bubble grid

    if (!$s_uuid || !$title) {
        $error_msg = 'Select a student and enter an exam title.';
    } else {
        // Pull the real answer key for this sheet (if one was set up)
        $keyRows = [];
        if ($sheet_uuid) {
            $kq = $pdo->prepare("SELECT question_number, correct_option, marks FROM omr_answer_keys WHERE school_uuid=? AND sheet_uuid=? ORDER BY question_number ASC");
            $kq->execute([$school_uuid, $sheet_uuid]);
            $keyRows = $kq->fetchAll();
        }

        if (empty($keyRows)) {
            $error_msg = 'No answer key found for this sheet — set one up under "Answer Keys" first.';
        } else {
            $total_q = count($keyRows);
            $correct = 0; $wrong = 0;
            $detected = [];
            foreach ($keyRows as $k) {
                $qn = (int)$k['question_number'];
                $given = strtoupper(safe_str($submitted[$qn] ?? ''));
                $detected[$qn] = $given;
                if ($given !== '' && $given === strtoupper($k['correct_option'])) $correct++;
                elseif ($given !== '') $wrong++;
            }
            $unanswered = $total_q - $correct - $wrong;
            $pct = $total_q > 0 ? round(($correct / $total_q) * 100, 2) : 0;

            $uuid = uid('omr');
            try {
                $pdo->prepare("INSERT INTO omr_evaluations (evaluation_uuid,school_uuid,student_uuid,student_name,exam_title,total_questions,correct_count,wrong_count,percentage_score,detected_answers_json)
                    VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$uuid,$school_uuid,$s_uuid,$s_name,$title,$total_q,$correct,$wrong,$pct,json_encode($detected)]);
                AuditLog::write($pdo,$school_uuid,$user_uuid,'omr.evaluate',$uuid,"$s_name — $title — $correct/$total_q ($pct%)");
                $success_msg = "OMR evaluation saved! $s_name scored $correct/$total_q ($pct%)" . ($unanswered > 0 ? ", $unanswered unanswered." : ".");
            } catch (PDOException $e) { $error_msg = safe_error('Save failed', $e); }
        }
    }
}

// ── UPLOAD + AUTO-SCAN ────────────────────────────────────────────────────────
if (isset($_POST['action_upload_omr_scan'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('omr')))) {
        $error_msg = 'You do not have permission to upload OMR scans.'; return;
    }
    $sheet_uuid = safe_str($_POST['sheet_uuid'] ?? '');
    $sq = $pdo->prepare("SELECT * FROM omr_sheets WHERE sheet_uuid=? AND school_uuid=?");
    $sq->execute([$sheet_uuid, $school_uuid]);
    $sheet = $sq->fetch();

    if (!$sheet) {
        $error_msg = 'Sheet not found.';
    } elseif (empty($_FILES['scan_files']['name'][0] ?? '')) {
        $error_msg = 'Choose at least one scanned/photographed strip to upload.';
    } else {
        $keyRows = $pdo->prepare("SELECT question_number, correct_option FROM omr_answer_keys WHERE school_uuid=? AND sheet_uuid=? ORDER BY question_number ASC");
        $keyRows->execute([$school_uuid, $sheet_uuid]);
        $key = [];
        foreach ($keyRows->fetchAll() as $r) { $key[(int)$r['question_number']] = strtoupper($r['correct_option']); }

        if (empty($key)) {
            $error_msg = 'No answer key found for this sheet — set one up under "Answer Keys" first.';
        } else {
            $python_bin = getenv('OMR_PYTHON_BIN') ?: 'python3';
            $scanner_path = escapeshellarg(__DIR__ . '/../../scripts/omr_scanner.py');
            $total_q = (int)$sheet['total_questions'];

            $results = [];
            $fileCount = count($_FILES['scan_files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['scan_files']['error'][$i] !== UPLOAD_ERR_OK) {
                    $results[] = ['ok' => false, 'file' => $_FILES['scan_files']['name'][$i], 'error' => 'Upload failed.'];
                    continue;
                }
                $orig_name = $_FILES['scan_files']['name'][$i];
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png'], true)) {
                    $results[] = ['ok' => false, 'file' => $orig_name, 'error' => 'Only JPG/PNG photos or scans are supported.'];
                    continue;
                }
                $dest_name = uid('scan') . '.' . $ext;
                $dest_path = $omr_upload_dir . $dest_name;
                if (!move_uploaded_file($_FILES['scan_files']['tmp_name'][$i], $dest_path)) {
                    $results[] = ['ok' => false, 'file' => $orig_name, 'error' => 'Could not save uploaded file.'];
                    continue;
                }

                $cmd = escapeshellcmd($python_bin) . ' ' . $scanner_path . ' ' . escapeshellarg($dest_path) . ' ' . escapeshellarg((string)$total_q) . ' 2>&1';
                $raw_output = shell_exec($cmd);
                $parsed = json_decode((string)$raw_output, true);

                if (!$parsed || empty($parsed['ok'])) {
                    $results[] = ['ok' => false, 'file' => $orig_name, 'error' => $parsed['error'] ?? 'Scanner failed to process this image.'];
                    continue;
                }

                // Match the detected serial code to a generated strip for this sheet.
                $mq = $pdo->prepare("SELECT * FROM omr_sheet_students WHERE sheet_uuid=? AND serial_code=?");
                $mq->execute([$sheet_uuid, $parsed['serial_code']]);
                $matchedStudent = $mq->fetch();

                if (!$matchedStudent) {
                    $results[] = [
                        'ok' => false, 'file' => $orig_name,
                        'error' => 'Detected ID "' . htmlspecialchars($parsed['serial_code']) . '" does not match any generated strip for this sheet (blurry photo, wrong sheet, or strip not generated yet).',
                    ];
                    continue;
                }

                // Score against the key.
                $detected = $parsed['answers'] ?? [];
                $correct = 0; $wrong = 0;
                foreach ($key as $qn => $correct_opt) {
                    $given = strtoupper((string)($detected[(string)$qn] ?? ''));
                    if ($given !== '' && $given === $correct_opt) $correct++;
                    elseif ($given !== '') $wrong++;
                }
                $unanswered = $total_q - $correct - $wrong;
                $pct = $total_q > 0 ? round(($correct / $total_q) * 100, 2) : 0;

                $confidence = (($parsed['id_confidence'] ?? '') === 'high' && ($parsed['corner_confidence'] ?? '') === 'high') ? 'high' : 'low';

                $euuid = uid('omr');
                try {
                    $pdo->prepare("INSERT INTO omr_evaluations (evaluation_uuid, school_uuid, student_uuid, student_name, exam_title, total_questions, correct_count, wrong_count, percentage_score, detected_answers_json, sheet_student_uuid, scan_confidence, flagged_questions_json)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([
                            $euuid, $school_uuid, $matchedStudent['student_uuid'], $matchedStudent['student_name'], $sheet['exam_title'],
                            $total_q, $correct, $wrong, $pct, json_encode($detected),
                            $matchedStudent['sheet_student_uuid'], $confidence, json_encode($parsed['flagged_questions'] ?? []),
                        ]);
                    $pdo->prepare("UPDATE omr_sheet_students SET scanned_at = NOW() WHERE sheet_student_uuid = ?")->execute([$matchedStudent['sheet_student_uuid']]);
                    AuditLog::write($pdo, $school_uuid, $user_uuid, 'omr.auto_scan', $euuid, "{$matchedStudent['student_name']} — {$sheet['exam_title']} — $correct/$total_q ($pct%) [$confidence confidence]");

                    $results[] = [
                        'ok' => true, 'file' => $orig_name,
                        'student_name' => $matchedStudent['student_name'],
                        'correct' => $correct, 'total' => $total_q, 'pct' => $pct,
                        'confidence' => $confidence,
                        'flagged' => $parsed['flagged_questions'] ?? [],
                        'warnings' => $parsed['warnings'] ?? [],
                    ];
                } catch (PDOException $e) {
                    $results[] = ['ok' => false, 'file' => $orig_name, 'error' => safe_error('Save failed', $e)];
                }
            }

            $omr_scan_results = $results; // read by admin/sections/omr.php to render a review table
            $okCount = count(array_filter($results, fn($r) => $r['ok']));
            $failCount = count($results) - $okCount;
            $success_msg = "Processed " . count($results) . " strip(s): $okCount scored" . ($failCount ? ", $failCount need manual review (see below)." : '.');
        }
    }
}
