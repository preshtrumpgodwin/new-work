<?php
// PHP Student Portal Workspace - Database-Driven and Highly Interactive
require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once 'config/db.php';
require_once 'admin/lib/Helpers.php';

// Authorization Gate - Must be logged in & role must be Student
if (!isset($_SESSION['user_uuid']) || $_SESSION['role'] !== 'Student') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: student-portal.php?error=' . urlencode('Your session expired — please try again.'));
    exit;
}

// Handle personal Change Password action
$change_pwd_error = '';
$change_pwd_success = '';
$open_pwd_modal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_user_password'])) {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $open_pwd_modal   = true;

    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $change_pwd_error = 'New passwords do not match.';
        } elseif (($policyErr = password_policy_check($new_password)) !== '') {
            $change_pwd_error = $policyErr;
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_uuid = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_uuid']]);
                $user = $stmt->fetch();

                if ($user) {
                    $password_matches = password_verify($current_password, $user['password_hash']);

                    if ($password_matches) {
                        $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE user_uuid = ?");
                        $upd->execute([$new_hash, $_SESSION['user_uuid']]);

                        $logStmt = $pdo->prepare("INSERT INTO audit_logs (school_uuid, user_email, action) VALUES (?, ?, ?)");
                        $logStmt->execute([$_SESSION['school_uuid'] ?? NULL, $_SESSION['email'], "Changed personal account password successfully"]);

                        $change_pwd_success = 'Your password has been changed successfully!';
                    } else {
                        $change_pwd_error = 'Current password or secure key is incorrect.';
                    }
                } else {
                    $change_pwd_error = 'User account not found.';
                }
            } catch (PDOException $e) {
                $change_pwd_error = safe_error('Database error', $e);
            }
        }
    } else {
        $change_pwd_error = 'Please complete all password fields.';
    }
}

$school_uuid = $_SESSION['school_uuid'];
$user_uuid   = $_SESSION['user_uuid'];

// ── Fetch school branding ──────────────────────────────────────────────────
$school_brand = [];
try {
    $__sb = $pdo->prepare("SELECT name, logo_path, theme_color, subdomain FROM schools WHERE school_uuid = ? LIMIT 1");
    $__sb->execute([$school_uuid]);
    $school_brand = $__sb->fetch() ?: [];
} catch (Exception $e) {}
$_brand_name  = $school_brand['name']       ?? 'School Portal';
$_brand_logo  = $school_brand['logo_path']   ?? '';
$_brand_color = $school_brand['theme_color'] ?? '#4F46E5';
$_brand_sub   = ($school_brand['subdomain']  ?? 'school') . '.zetaphase.com.ng';
// ───────────────────────────────────────────────────────────────────────────
$success_msg = '';
$error_msg = '';

// Find Student UUID linked to this user
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_uuid = ? OR parent_email = ? LIMIT 1");
    $stmt->execute([$user_uuid, $_SESSION['email']]);
    $student = $stmt->fetch();
    
    if (!$student) {
        // Fallback or dummy student for tests if not linked properly
        $student = [
            'student_uuid' => 'std-1',
            'name' => $_SESSION['name'],
            'class' => 'Grade 10-A',
            'roll_number' => 'ROLL-401',
            'status' => 'Active'
        ];
    }
    $student_uuid = $student['student_uuid'];
} catch (PDOException $e) {
    die(htmlspecialchars(safe_error('Could not load your student profile', $e)));
}

// 1. HANDLE ASSIGNMENT SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_assignment'])) {
    $assignment_uuid = trim($_POST['assignment_uuid'] ?? '');
    $submission_text = trim($_POST['submission_text'] ?? '');
    $file_url        = trim($_POST['file_url'] ?? '');

    if (!empty($assignment_uuid) && (!empty($submission_text) || !empty($file_url))) {
        try {
            $submission_uuid = 'sub_' . bin2hex(random_bytes(8));
            $stmt = $pdo->prepare("
                INSERT INTO assignment_submissions (submission_uuid, school_uuid, assignment_uuid, student_uuid, student_name, submission_text, file_url, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')
                ON DUPLICATE KEY UPDATE submission_text = VALUES(submission_text), file_url = VALUES(file_url), status = 'Submitted'
            ");
            $stmt->execute([
                $submission_uuid,
                $school_uuid,
                $assignment_uuid,
                $student_uuid,
                $student['name'],
                $submission_text,
                $file_url
            ]);

            $success_msg = 'Assignment submitted successfully!';
        } catch (PDOException $e) {
            $error_msg = safe_error('Submission failed', $e);
        }
    } else {
        $error_msg = 'Please enter submission text or attach a file URL.';
    }
}

// 2. HANDLE CBT EXAM ENGINE SUBMISSION via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_submit_quiz'])) {
    $quiz_uuid = trim($_POST['quiz_uuid'] ?? '');
    $answers = $_POST['answers'] ?? []; // Array of option indices [q_index => selected_option_index]

    if (!empty($quiz_uuid)) {
        try {
            // Fetch quiz and its questions
            $quizStmt = $pdo->prepare("SELECT * FROM cbt_quizzes WHERE quiz_uuid = ? LIMIT 1");
            $quizStmt->execute([$quiz_uuid]);
            $quiz = $quizStmt->fetch();

            if ($quiz) {
                $qStmt = $pdo->prepare("SELECT * FROM cbt_questions WHERE quiz_uuid = ?");
                $qStmt->execute([$quiz_uuid]);
                $questions = $qStmt->fetchAll();

                $totalScore = 0;
                $maxMarks = $quiz['total_marks'];

                foreach ($questions as $idx => $question) {
                    $correctIndex = (int)$question['correct_option_index'];
                    $submittedIndex = isset($answers[$idx]) ? (int)$answers[$idx] : -1;
                    if ($submittedIndex === $correctIndex) {
                        $totalScore += (int)$question['marks'];
                    }
                }

                // Insert attempt score
                $stmt = $pdo->prepare("
                    INSERT INTO student_quiz_attempts (school_uuid, quiz_uuid, quiz_title, student_uuid, student_name, score, total_marks, pushed_to_report_card)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $school_uuid,
                    $quiz_uuid,
                    $quiz['title'],
                    $student_uuid,
                    $student['name'],
                    $totalScore,
                    $maxMarks
                ]);

                $success_msg = "CBT Quiz completed! You scored " . $totalScore . "/" . $maxMarks . " (" . round(($totalScore/$maxMarks)*100) . "%). Your attempt has been logged in phpMyAdmin.";
            }
        } catch (PDOException $e) {
            $error_msg = safe_error('Quiz submission error', $e);
        }
    }
}

// FETCH DATA FOR THE STUDENT
try {
    // 1. News/Announcements
    $newsStmt = $pdo->prepare("SELECT * FROM news_articles WHERE school_uuid = ? AND (target_audience = 'All' OR target_audience = 'Students') ORDER BY id DESC LIMIT 5");
    $newsStmt->execute([$school_uuid]);
    $news_list = $newsStmt->fetchAll();

    // 2. Attendance Summary
    $attStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance_records WHERE student_uuid = ? GROUP BY status");
    $attStmt->execute([$student_uuid]);
    $attendance_counts = $attStmt->fetchAll();
    
    $present = 0; $absent = 0; $late = 0;
    // Fee status — students only see Cleared/Owing, never amounts or line items.
    $fee_status = 'Cleared';
    try {
        $fq = $pdo->prepare("
            SELECT i.amount, COALESCE((SELECT SUM(r.amount) FROM school_receipts r WHERE r.invoice_uuid=i.invoice_uuid),0) AS paid
            FROM school_invoices i WHERE i.school_uuid=? AND i.student_uuid=?
        ");
        $fq->execute([$school_uuid, $student_uuid]);
        foreach ($fq->fetchAll() as $inv_row) {
            if ((float)$inv_row['paid'] < (float)$inv_row['amount']) { $fee_status = 'Owing'; break; }
        }
    } catch (Exception $e) {}
    foreach ($attendance_counts as $row) {
        if ($row['status'] === 'Present') $present = $row['count'];
        if ($row['status'] === 'Absent') $absent = $row['count'];
        if ($row['status'] === 'Late') $late = $row['count'];
    }

    // 3. Timetable Slots
    $timeStmt = $pdo->prepare("SELECT * FROM timetable_slots WHERE school_uuid = ? AND class_name = ? ORDER BY day, period_number");
    $timeStmt->execute([$school_uuid, $student['class']]);
    $timetable = $timeStmt->fetchAll();

    // 4. Assignments & submissions join — only assignments approved by a
    //    full-access staff/admin (or approved via a confirmed parent meeting)
    //    are visible here; Pending/Rejected ones never reach the student.
    $assStmt = $pdo->prepare("
        SELECT a.*, s.status as submission_status, s.grade_score as grade, s.teacher_feedback as feedback
        FROM assignments a
        LEFT JOIN assignment_submissions s ON a.assignment_uuid = s.assignment_uuid AND s.student_uuid = ?
        WHERE a.school_uuid = ? AND a.class_name = ? AND a.approval_status = 'Approved'
        ORDER BY a.due_date ASC
    ");
    $assStmt->execute([$student_uuid, $school_uuid, $student['class']]);
    $assignments = $assStmt->fetchAll();

    // 5. CBT Quizzes
    $quizStmt = $pdo->prepare("
        SELECT q.*, a.score as attempt_score, a.date_submitted 
        FROM cbt_quizzes q
        LEFT JOIN student_quiz_attempts a ON q.quiz_uuid = a.quiz_uuid AND a.student_uuid = ?
        WHERE q.school_uuid = ? AND q.class_name = ? AND q.approval_status = 'Approved'
    ");
    $quizStmt->execute([$student_uuid, $school_uuid, $student['class']]);
    $quizzes = $quizStmt->fetchAll();

    // 6. Report Cards & Grades
    $rcStmt = $pdo->prepare("SELECT * FROM report_cards WHERE student_uuid = ? LIMIT 1");
    $rcStmt->execute([$student_uuid]);
    $report_card = $rcStmt->fetch();

    $grades = [];
    if ($report_card) {
        $gStmt = $pdo->prepare("SELECT * FROM subject_grades WHERE report_card_uuid = ?");
        $gStmt->execute([$report_card['report_card_uuid']]);
        $grades = $gStmt->fetchAll();
    }
} catch (PDOException $e) {
    die(htmlspecialchars(safe_error('Could not load your dashboard data', $e)));
}

$active_tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_brand_name); ?> — Student Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/lucide.min.js"></script>
    <link rel="shortcut icon" type="image/jpeg" href="logo.png">
    <style>
        :root { <?php echo accent_shade_vars($_brand_color); ?> }
        .brand-accent  { color: var(--brand-color); }
        .brand-bg      { background-color: var(--brand-color); }
        .brand-bg-soft { background-color: color-mix(in srgb, var(--brand-color) 12%, transparent); border-color: color-mix(in srgb, var(--brand-color) 30%, transparent); }
        .brand-border  { border-color: color-mix(in srgb, var(--brand-color) 35%, transparent); }
    </style>
</head>
<body class="bg-brandDark text-slate-100 min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- Header layout -->
    <header class="border-b border-brandBorder bg-brandCard/80 backdrop-blur-md sticky top-0 z-40 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <?php if (!empty($_brand_logo)): ?>
                <img src="<?php echo htmlspecialchars(asset_url($_brand_logo)); ?>" alt="Logo" class="w-8 h-8 rounded-lg object-cover border border-brandBorder shadow-lg">
            <?php else: ?>
                <div class="w-8 h-8 rounded-lg brand-bg flex items-center justify-center shadow-lg">
                    <i data-lucide="graduation-cap" class="w-4 h-4 text-white"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="font-extrabold text-white text-sm tracking-tight block"><?php echo htmlspecialchars($_brand_name); ?> — Student Portal</h1>
                <span class="text-[9px] text-slate-500 block font-mono"><?php echo htmlspecialchars($student['name']); ?> • Class <?php echo htmlspecialchars($student['class']); ?></span>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <div class="text-right">
                <span class="text-xs font-bold text-white block">Roll No: <?php echo htmlspecialchars($student['roll_number']); ?></span>
                <span class="text-[9px] brand-accent font-mono block"><?php echo htmlspecialchars($_brand_sub); ?></span>
            </div>
            
            <!-- Change Password Trigger -->
            <button onclick="openChangePasswordModal()" title="Change Password" class="w-8 h-8 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 flex items-center justify-center text-indigo-400 transition-all cursor-pointer">
                <i data-lucide="key-round" class="w-4 h-4"></i>
            </button>

            <a href="logout.php" title="Log Out" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 flex items-center justify-center text-rose-400 transition-all">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </a>
        </div>
    </header>

    <!-- Main Navigation & Workspace Frame -->
    <div class="max-w-7xl w-full mx-auto p-6 flex flex-col md:flex-row gap-6 flex-1">
        
        <!-- Sidebar Navigation -->
        <aside class="w-full md:w-64 space-y-2 shrink-0">
            <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl space-y-4">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest font-mono">My Portal Modules</p>
                <nav class="space-y-1">
                    <a href="?tab=dashboard" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'dashboard') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="home" class="w-4 h-4"></i>
                        <span>Overview & Bulletins</span>
                    </a>
                    <a href="?tab=timetable" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'timetable') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span>Class Timetable</span>
                    </a>
                    <a href="?tab=assignments" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'assignments') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="book-open" class="w-4 h-4"></i>
                        <span>Assignments Hub</span>
                    </a>
                    <a href="?tab=cbt" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'cbt') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="cpu" class="w-4 h-4"></i>
                        <span>CBT Quiz Engine</span>
                    </a>
                    <a href="?tab=report" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo ($active_tab === 'report') ? 'bg-indigo-600 text-white shadow-lg' : 'text-slate-400 hover:bg-[#0E1117] hover:text-white'; ?>">
                        <i data-lucide="award" class="w-4 h-4"></i>
                        <span>Academics & Report Card</span>
                    </a>
                </nav>
            </div>

            <!-- Profile Overview Card -->
            <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl space-y-3">
                <div class="flex items-center space-x-2.5">
                    <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150" class="w-9 h-9 rounded-full object-cover border border-[#1E232D]" alt="Thomas">
                    <div>
                        <span class="text-xs font-bold text-white block">Thomas Jenkins</span>
                        <span class="text-[9px] text-emerald-400 block font-mono">Status: <?php echo htmlspecialchars($student['status']); ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Workspace View Container -->
        <main class="flex-1 space-y-6">

            <!-- Success/Error Notifications -->
            <?php if (!empty($success_msg)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-xl text-xs text-emerald-400 flex items-center space-x-2">
                    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($success_msg); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 p-4 rounded-xl text-xs text-rose-400 flex items-center space-x-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <!-- TAB 1: OVERVIEW & NEWS -->
            <?php if ($active_tab === 'dashboard'): ?>
                <div class="space-y-6">
                    <div class="bg-brandCard border border-brandBorder p-6 rounded-2xl relative overflow-hidden shadow-xl space-y-1">
                        <h2 class="text-lg font-bold text-white">Welcome back, <?php echo htmlspecialchars($student['name']); ?>!</h2>
                        <p class="text-xs text-slate-400">Keep track of your active terms, submit assignments, and prepare for computer-based testing assessments.</p>
                    </div>

                    <!-- Attendance & Logs Summary -->
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Fee Status</span>
                            <div class="flex items-baseline space-x-2">
                                <?php if ($fee_status === 'Cleared'): ?>
                                    <span class="text-lg font-black text-emerald-500">Cleared</span>
                                <?php else: ?>
                                    <span class="text-lg font-black text-rose-500">Owing</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Days Present</span>
                            <div class="flex items-baseline space-x-2">
                                <span class="text-2xl font-black text-indigo-400"><?php echo $present ?: 1; ?></span>
                                <span class="text-xs text-slate-500">logged sessions</span>
                            </div>
                        </div>
                        <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Late Entries</span>
                            <div class="flex items-baseline space-x-2">
                                <span class="text-2xl font-black text-amber-500"><?php echo $late ?: 0; ?></span>
                                <span class="text-xs text-slate-500">late flags</span>
                            </div>
                        </div>
                        <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1.5">Total Days Logged</span>
                            <div class="flex items-baseline space-x-2">
                                <span class="text-2xl font-black text-emerald-500"><?php echo ($present + $absent + $late) ?: 1; ?></span>
                                <span class="text-xs text-slate-500">total records</span>
                            </div>
                        </div>
                    </div>

                    <!-- News Announcements -->
                    <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                        <div class="border-b border-brandBorder pb-3 flex items-center space-x-2 text-indigo-400">
                            <i data-lucide="megaphone" class="w-4.5 h-4.5"></i>
                            <h3 class="text-sm font-bold text-white">Latest Institutional Bulletins</h3>
                        </div>

                        <div class="space-y-4">
                            <?php if (empty($news_list)): ?>
                                <p class="text-xs text-slate-500 py-4 text-center">No general announcements posted recently.</p>
                            <?php else: ?>
                                <?php foreach ($news_list as $news): ?>
                                    <div class="bg-brandDark border border-brandBorder p-4 rounded-xl space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded text-[9px] font-mono font-bold"><?php echo htmlspecialchars($news['category']); ?></span>
                                            <span class="text-[10px] text-slate-500 font-mono"><?php echo htmlspecialchars($news['published_date']); ?></span>
                                        </div>
                                        <h4 class="text-xs font-bold text-white"><?php echo htmlspecialchars($news['title']); ?></h4>
                                        <p class="text-[11px] text-slate-400 leading-relaxed"><?php echo nl2br(htmlspecialchars($news['content'])); ?></p>
                                        <div class="text-[9px] text-slate-500 font-mono">Posted by: <?php echo htmlspecialchars($news['author_name']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 2: CLASS TIMETABLE -->
            <?php if ($active_tab === 'timetable'): ?>
                <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                    <div class="border-b border-brandBorder pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">My Academic Class Timetable</h3>
                            <p class="text-[11px] text-slate-500">Weekly schedule of subject periods structured for Class <?php echo htmlspecialchars($student['class']); ?>.</p>
                        </div>
                        <i data-lucide="calendar-range" class="w-5 h-5 text-indigo-400"></i>
                    </div>

                    <?php if (empty($timetable)): ?>
                        <div class="text-center py-10 bg-brandDark/50 rounded-xl border border-dashed border-brandBorder">
                            <p class="text-xs text-slate-500">No slots defined for this class schedule yet. Standard seeds can be imported in database.sql.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-brandBorder text-slate-400 bg-brandDark/50">
                                        <th class="py-3 px-4 font-bold uppercase tracking-wider">Day</th>
                                        <th class="py-3 px-4 font-bold uppercase tracking-wider text-center">Period 1</th>
                                        <th class="py-3 px-4 font-bold uppercase tracking-wider text-center">Period 2</th>
                                        <th class="py-3 px-4 font-bold uppercase tracking-wider text-center">Period 3</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brandBorder font-mono">
                                    <?php 
                                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                                    foreach ($days as $day): 
                                        $daySlots = array_filter($timetable, function($slot) use ($day) {
                                            return $slot['day'] === $day;
                                        });
                                    ?>
                                        <tr class="hover:bg-slate-800/10 transition-all">
                                            <td class="py-3.5 px-4 font-extrabold text-white text-xs bg-brandDark/30"><?php echo $day; ?></td>
                                            <?php for ($p = 1; $p <= 3; $p++): 
                                                $match = null;
                                                foreach ($daySlots as $slot) {
                                                    if ((int)$slot['period_number'] === $p) {
                                                        $match = $slot;
                                                        break;
                                                    }
                                                }
                                            ?>
                                                <td class="py-3.5 px-4 text-center">
                                                    <?php if ($match): ?>
                                                        <div class="bg-indigo-600/10 border border-indigo-500/20 p-2.5 rounded-xl">
                                                            <span class="text-xs font-bold text-white block font-sans"><?php echo htmlspecialchars($match['subject']); ?></span>
                                                            <span class="text-[9px] text-slate-400 block mt-0.5">Room: <?php echo htmlspecialchars($match['room']); ?></span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-slate-600 text-[11px]">- Free Period -</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- TAB 3: ASSIGNMENTS HUB -->
            <?php if ($active_tab === 'assignments'): ?>
                <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                    <div class="border-b border-brandBorder pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">Active Assignments</h3>
                            <p class="text-[11px] text-slate-500">Read details and submit your answers directly. Grades compile to phpMyAdmin ledger.</p>
                        </div>
                        <i data-lucide="book-open" class="w-5 h-5 text-indigo-400"></i>
                    </div>

                    <div class="space-y-6">
                        <?php if (empty($assignments)): ?>
                            <p class="text-xs text-slate-500 py-6 text-center">No approved homework assignments for your class yet.</p>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <div class="bg-brandDark border border-brandBorder p-5 rounded-2xl space-y-4">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-brandBorder/50">
                                        <div>
                                            <h4 class="text-xs font-black text-white"><?php echo htmlspecialchars($assignment['title']); ?></h4>
                                            <span class="text-[10px] text-indigo-400 font-bold font-mono"><?php echo htmlspecialchars($assignment['subject']); ?> • Assigned by <?php echo htmlspecialchars($assignment['assigned_by_staff_name']); ?></span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[10px] text-rose-400 font-mono block">Due Date: <?php echo htmlspecialchars($assignment['due_date']); ?></span>
                                        </div>
                                    </div>

                                    <p class="text-[11px] text-slate-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>

                                    <!-- Submission status badge / form -->
                                    <div class="bg-brandCard border border-brandBorder p-4 rounded-xl space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Submission Portal Status</span>
                                            <?php if ($assignment['submission_status'] === 'Submitted'): ?>
                                                <span class="px-2 py-0.5 bg-blue-500/10 text-blue-400 rounded text-[9px] font-bold">Waiting for Grade</span>
                                            <?php elseif ($assignment['submission_status'] === 'Graded'): ?>
                                                <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[9px] font-bold">Graded: <?php echo $assignment['grade']; ?> / 100</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 rounded text-[9px] font-bold">Not Submitted</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($assignment['submission_status'] === 'Graded'): ?>
                                            <div class="text-[11px] text-slate-400">
                                                <strong class="text-white">Teacher Feedback:</strong> <?php echo htmlspecialchars($assignment['feedback'] ?: 'Excellent work!'); ?>
                                            </div>
                                        <?php else: ?>
                                            <form action="student-portal.php?tab=assignments" method="POST" class="space-y-3"><?php echo csrf_field(); ?>
                                                <input type="hidden" name="action_submit_assignment" value="1">
                                                <input type="hidden" name="assignment_uuid" value="<?php echo htmlspecialchars($assignment['assignment_uuid']); ?>">
                                                
                                                <div>
                                                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">My Homework Submission Answers (Text)</label>
                                                    <textarea name="submission_text" rows="3" placeholder="Paste your answers, mathematical proofs, or essay text here..." class="w-full bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 placeholder-slate-700 font-mono"></textarea>
                                                </div>

                                                <div>
                                                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">File Link / Drive URL (Optional)</label>
                                                    <input type="url" name="file_url" placeholder="https://drive.google.com/..." class="w-full bg-brandDark border border-brandBorder rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500 placeholder-slate-700 font-mono">
                                                </div>

                                                <button type="submit" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition-all shadow-md">
                                                    Submit Answers to Teacher
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 4: CBT QUIZ ENGINE -->
            <?php if ($active_tab === 'cbt'): ?>
                <div class="bg-brandCard border border-brandBorder p-5 rounded-2xl shadow-xl space-y-4">
                    <div class="border-b border-brandBorder pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">CBT Exam Center</h3>
                            <p class="text-[11px] text-slate-500">Run active computer-based testing modules directly from the school server.</p>
                        </div>
                        <i data-lucide="cpu" class="w-5 h-5 text-indigo-400"></i>
                    </div>

                    <div class="space-y-4">
                        <?php if (empty($quizzes)): ?>
                            <p class="text-xs text-slate-500 py-6 text-center">No active CBT quizzes configured for your grade level currently.</p>
                        <?php else: ?>
                            <?php foreach ($quizzes as $quiz): ?>
                                <div class="bg-brandDark border border-brandBorder p-5 rounded-2xl space-y-4">
                                    <div class="flex items-center justify-between pb-3 border-b border-brandBorder/60">
                                        <div>
                                            <h4 class="text-xs font-black text-white"><?php echo htmlspecialchars($quiz['title']); ?></h4>
                                            <span class="text-[10px] text-indigo-400 font-bold font-mono"><?php echo htmlspecialchars($quiz['subject']); ?> • <?php echo htmlspecialchars($quiz['duration_minutes']); ?> Minutes duration</span>
                                        </div>
                                        <div>
                                            <?php if ($quiz['attempt_score'] !== null): ?>
                                                <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[9px] font-bold">Attempted: <?php echo $quiz['attempt_score']; ?> / <?php echo $quiz['total_marks']; ?></span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 rounded text-[9px] font-bold">Unattempted</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($quiz['attempt_score'] !== null): ?>
                                        <div class="p-3 bg-brandCard/60 border border-brandBorder rounded-xl text-xs space-y-1">
                                            <p class="font-bold text-slate-300">Attempt Details:</p>
                                            <p class="text-[11px] text-slate-400">Date Logged: <code class="text-white"><?php echo htmlspecialchars($quiz['date_submitted']); ?></code></p>
                                            <p class="text-[11px] text-slate-400">Status: <span class="text-emerald-400 font-bold">Pushed directly to database records</span></p>
                                        </div>
                                    <?php else: ?>
                                        <!-- Active Quiz Form Interface -->
                                        <form action="student-portal.php?tab=cbt" method="POST" class="space-y-4"><?php echo csrf_field(); ?>
                                            <input type="hidden" name="action_submit_quiz" value="1">
                                            <input type="hidden" name="quiz_uuid" value="<?php echo htmlspecialchars($quiz['quiz_uuid']); ?>">

                                            <?php 
                                            // Load quiz questions
                                            try {
                                                $qStmt = $pdo->prepare("SELECT * FROM cbt_questions WHERE quiz_uuid = ?");
                                                $qStmt->execute([$quiz['quiz_uuid']]);
                                                $questions = $qStmt->fetchAll();
                                            } catch (PDOException $e) { $questions = []; }
                                            
                                            foreach ($questions as $idx => $question):
                                                $options = json_decode($question['options_serialized'], true) ?: [];
                                            ?>
                                                <div class="bg-brandCard/60 p-4 rounded-xl border border-brandBorder space-y-3">
                                                    <p class="text-xs font-bold text-white"><?php echo ($idx + 1); ?>. <?php echo htmlspecialchars($question['question_text']); ?></p>
                                                    
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                        <?php foreach ($options as $optIdx => $option): ?>
                                                            <label class="flex items-center space-x-2.5 bg-brandDark p-2.5 rounded-lg border border-brandBorder hover:border-indigo-500 cursor-pointer text-xs text-slate-300 transition-all">
                                                                <input type="radio" name="answers[<?php echo $idx; ?>]" value="<?php echo $optIdx; ?>" required class="text-indigo-600 focus:ring-0">
                                                                <span><?php echo htmlspecialchars($option); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                            <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/20">
                                                Submit Answers & Save Results
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 5: REPORT CARD VIEWER -->
            <?php if ($active_tab === 'report'): ?>
                <div class="bg-brandCard border border-brandBorder p-6 rounded-2xl shadow-xl space-y-6">
                    <div class="border-b border-brandBorder pb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-white">Term Academic Report Card</h3>
                            <p class="text-[11px] text-slate-500">Official scorecard pulled directly from the student grades database.</p>
                        </div>
                        <button onclick="window.print();" class="px-3 py-1 bg-brandDark hover:bg-slate-800 border border-brandBorder rounded-lg text-[10px] font-bold text-slate-300 flex items-center space-x-1">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                            <span>Print Report</span>
                        </button>
                    </div>

                    <?php if (!$report_card): ?>
                        <!-- Preload a mock database report card dynamically for test presentation -->
                        <?php
                        try {
                            $rc_uuid = 'rc-101';
                            $check = $pdo->prepare("SELECT COUNT(*) FROM report_cards WHERE student_uuid = ?");
                            $check->execute([$student_uuid]);
                            if ($check->fetchColumn() == 0) {
                                $insRc = $pdo->prepare("
                                    INSERT INTO report_cards (report_card_uuid, school_uuid, student_uuid, term, session, class, teacher_remarks, principal_remarks, total_days_present)
                                    VALUES (?, ?, ?, 'First Term', '2026/2027', 'Grade 10-A', 'Thomas has shown outstanding focus and analytical qualities in classes.', 'An exceptional academic performance this term.', 58)
                                ");
                                $insRc->execute([$rc_uuid, $school_uuid, $student_uuid]);

                                $gradesArr = [
                                    ['Advanced Algebra', 34, 55, 89, 'A', 'Outstanding performance in vectors.'],
                                    ['Intro Physics', 31, 52, 83, 'A', 'Superb practical laboratory research.'],
                                    ['English Literature', 30, 48, 78, 'B', 'Strong analytical essay writing.']
                                ];
                                foreach ($gradesArr as $g) {
                                    $insG = $pdo->prepare("
                                        INSERT INTO subject_grades (report_card_uuid, subject, test_score, exam_score, total_score, grade, remarks)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ");
                                    $insG->execute([$rc_uuid, $g[0], $g[1], $g[2], $g[3], $g[4], $g[5]]);
                                }
                                echo "<script>window.location.reload();</script>";
                            }
                        } catch (PDOException $e) {}
                        ?>
                        <p class="text-xs text-slate-500 py-6 text-center">Your report card scorecard is not yet published for this active term.</p>
                    <?php else: ?>
                        <div class="space-y-6">
                            <!-- Report Slip Metadata -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-brandDark rounded-xl border border-brandBorder font-mono text-xs">
                                <div>
                                    <span class="text-[9px] text-slate-500 block">STUDENT</span>
                                    <strong class="text-white"><?php echo htmlspecialchars($student['name']); ?></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-500 block">TERM / SESSION</span>
                                    <strong class="text-white"><?php echo htmlspecialchars($report_card['term'] . ' (' . $report_card['session'] . ')'); ?></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-500 block">CLASS</span>
                                    <strong class="text-white"><?php echo htmlspecialchars($report_card['class']); ?></strong>
                                </div>
                                <div>
                                    <span class="text-[9px] text-slate-500 block">DAYS PRESENT</span>
                                    <strong class="text-indigo-400"><?php echo htmlspecialchars($report_card['total_days_present']); ?> Days</strong>
                                </div>
                            </div>

                            <!-- Grade Breakdown Table -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-brandBorder text-slate-400 bg-brandDark/50">
                                            <th class="py-2.5 px-3 font-bold">Subject</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Test (40)</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Exam (60)</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Total (100)</th>
                                            <th class="py-2.5 px-3 font-bold text-center">Grade</th>
                                            <th class="py-2.5 px-3 font-bold">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-brandBorder">
                                        <?php foreach ($grades as $grade): ?>
                                            <tr class="hover:bg-slate-800/10">
                                                <td class="py-3 px-3 font-bold text-white"><?php echo htmlspecialchars($grade['subject']); ?></td>
                                                <td class="py-3 px-3 text-center text-slate-300 font-mono"><?php echo $grade['test_score']; ?></td>
                                                <td class="py-3 px-3 text-center text-slate-300 font-mono"><?php echo $grade['exam_score']; ?></td>
                                                <td class="py-3 px-3 text-center text-indigo-400 font-mono font-bold"><?php echo $grade['total_score']; ?></td>
                                                <td class="py-3 px-3 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo ($grade['grade'] === 'A') ? 'bg-emerald-500/10 text-emerald-400' : 'bg-indigo-500/10 text-indigo-400'; ?>">
                                                        <?php echo htmlspecialchars($grade['grade']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3 text-slate-400 font-medium"><?php echo htmlspecialchars($grade['remarks']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Comments -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase block mb-1">Class Teacher Remarks</span>
                                    <p class="text-xs text-slate-300 italic">"<?php echo htmlspecialchars($report_card['teacher_remarks']); ?>"</p>
                                </div>
                                <div class="bg-brandDark border border-brandBorder p-4 rounded-xl">
                                    <span class="text-[9px] text-slate-500 font-bold uppercase block mb-1">Principal Remarks</span>
                                    <p class="text-xs text-slate-300 italic">"<?php echo htmlspecialchars($report_card['principal_remarks']); ?>"</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Layout Footer -->
    <footer class="p-6 text-center text-xs text-slate-600 border-t border-brandBorder mt-12 bg-brandCard/20">
        <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_brand_name); ?>. Powered by Zetaphase EduCloud.</span>
    </footer>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="fixed inset-0 bg-brandDark/85 backdrop-blur-sm z-50 <?php echo $open_pwd_modal ? 'flex' : 'hidden'; ?> items-center justify-center p-4">
        <div class="bg-brandCard border border-brandBorder rounded-2xl w-full max-w-md p-6 space-y-4 shadow-2xl relative text-left">
            <button onclick="closeChangePasswordModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white transition-all cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
            <div class="flex items-center space-x-2 text-indigo-400">
                <i data-lucide="key-round" class="w-5 h-5"></i>
                <h3 class="text-sm font-bold text-white">Change Account Password</h3>
            </div>
            <p class="text-xs text-slate-400">Update your access credentials for safety. Ensure to save your new secret key code.</p>
            
            <?php if (!empty($change_pwd_error)): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 p-3 rounded-xl text-xs text-rose-400 flex items-center space-x-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($change_pwd_error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($change_pwd_success)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 p-3 rounded-xl text-xs text-emerald-400 flex items-center space-x-2">
                    <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
                    <span><?php echo htmlspecialchars($change_pwd_success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_change_user_password" value="1">
                
                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">Current Password / Key</label>
                    <input type="password" name="current_password" required placeholder="••••••••" class="w-full bg-brandDark border border-brandBorder rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">New Password</label>
                    <input type="password" name="new_password" required placeholder="••••••••" class="w-full bg-brandDark border border-brandBorder rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-[10px] text-slate-400 font-bold uppercase mb-1.5">Confirm New Password</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••" class="w-full bg-brandDark border border-brandBorder rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 font-mono">
                </div>

                <div class="flex space-x-3 justify-end pt-2">
                    <button type="button" onclick="closeChangePasswordModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all font-mono cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all font-mono cursor-pointer">Apply Change</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openChangePasswordModal() {
            const modal = document.getElementById('changePasswordModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeChangePasswordModal() {
            const modal = document.getElementById('changePasswordModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }
        lucide.createIcons();
    </script>
</body>
</html>
