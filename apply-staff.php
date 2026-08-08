<?php
// Staff Employment Application Portal — Subdomain-Aware
session_start();
require_once 'config/db.php';
require_once 'config/subdomain.php';
require_once 'admin/lib/Helpers.php';

$success_msg = '';
$error_msg   = '';
$debug_info  = [];

// ── Resolve school from subdomain ──────────────────────────────────────────
$ctx = resolve_subdomain($pdo);

if ($ctx['is_platform']) {
    header('Location: login.php');
    exit;
}

$selected_school = null;

if ($ctx['is_school'] && $ctx['school']) {
    $selected_school = $ctx['school'];
}

if (!$selected_school) {
    $subdomain_param   = trim($_GET['school'] ?? '');
    $school_uuid_param = trim($_GET['school_uuid'] ?? '');

    if (!empty($subdomain_param)) {
        $stmt = $pdo->prepare("SELECT * FROM schools WHERE subdomain = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([strtolower($subdomain_param)]);
        $selected_school = $stmt->fetch() ?: null;
    } elseif (!empty($school_uuid_param)) {
        $stmt = $pdo->prepare("SELECT * FROM schools WHERE school_uuid = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$school_uuid_param]);
        $selected_school = $stmt->fetch() ?: null;
    }
}

$on_subdomain = $ctx['is_school'];
$all_schools  = [];
if (!$on_subdomain) {
    try {
        $all_schools = $pdo->query("SELECT school_uuid, name, subdomain, logo_path FROM schools WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
    if (!$selected_school && !empty($all_schools)) {
        $selected_school = $all_schools[0];
    }
}

$brand_name  = $selected_school['name']       ?? 'Zetaphase EduCloud';
$brand_logo  = $selected_school['logo_path']   ?? '';
$brand_color = $selected_school['theme_color'] ?? '#4F46E5';
$school_uuid = $selected_school['school_uuid'] ?? '';

// ── Theme detection ─────────────────────────────────────────────────────────
$theme_mode = $selected_school['theme_mode'] ?? 'auto';
$hour = (int)date('H');
if ($theme_mode === 'auto') {
    $theme_mode = ($hour >= 6 && $hour < 18) ? 'light' : 'dark';
}
$html_class = $theme_mode === 'dark' ? 'dark' : '';

$bg_primary   = $theme_mode === 'light' ? '#FFFFFF' : '#0E1117';
$bg_secondary = $theme_mode === 'light' ? '#F8FAFC' : '#11141B';
$bg_tertiary  = $theme_mode === 'light' ? '#F1F5F9' : '#0A0D12';
$border_color = $theme_mode === 'light' ? '#E2E8F0' : '#1E232D';
$text_primary = $theme_mode === 'light' ? '#0F172A' : '#F1F5F9';
$text_secondary = $theme_mode === 'light' ? '#475569' : '#94A3B8';

// ── Generate accent shade variables ────────────────────────────────────────
$hex = ltrim($brand_color, '#');
if (strlen($hex) === 3) {
    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
}
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));

$mix = function($r, $g, $b, $amt, $toWhite) {
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

$accent_vars = "--color-indigo-300:{$c300};--color-indigo-400:{$c400};--color-indigo-500:{$c500};--color-indigo-600:{$c600};--color-indigo-700:{$c700};--brand-color:{$c600};";

// ── Fetch school-specific data from database ONLY ──────────────────────────
$academic_subjects = [];
$non_teaching_roles = [
    'Administrative Officer',
    'Accountant / Bursar',
    'Secretary / Front Desk Officer',
    'IT Support / System Administrator',
    'Librarian',
    'Laboratory Attendant',
    'Cleaner / Janitor',
    'Security Guard',
    'Driver',
    'Nurse / Healthcare Attendant',
    'Hostel Master / Mistress',
    'Cafeteria Staff',
    'Groundskeeper',
    'Guidance Counsellor'
];

// ── Fetch subjects from database ONLY ──────────────────────────────────────
if (!empty($school_uuid)) {
    try {
        $stmt = $pdo->prepare("SELECT subject_name FROM academic_subjects WHERE school_uuid = ? ORDER BY subject_name ASC");
        $stmt->execute([$school_uuid]);
        $academic_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Silently fail — subjects will remain empty array
    }
}

// ── Fetch classes from database ONLY ──────────────────────────────────────
$academic_classes = [];
if (!empty($school_uuid)) {
    try {
        $stmt = $pdo->prepare("SELECT class_name FROM academic_classes WHERE school_uuid = ? ORDER BY class_name ASC");
        $stmt->execute([$school_uuid]);
        $academic_classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Silently fail
    }
}

// ── Qualification Levels ────────────────────────────────────────────────────
$qualification_levels = [
    'SSCE / WASSCE / NECO',
    'OND / NCE',
    'HND / B.Sc / B.Ed / B.A',
    'M.Sc / M.Ed / M.A',
    'PhD / Doctorate',
    'Professional Certification (e.g., ACA, ACCA, CIPM)',
    'Other'
];

// ── Nigerian States ────────────────────────────────────────────────────────
$nigerian_states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT (Abuja)', 'Gombe',
    'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
    'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto',
    'Taraba', 'Yobe', 'Zamfara'
];

$marital_statuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];

// ── File Upload Configuration ──────────────────────────────────────────────
$allowed_image_types = ['image/jpeg', 'image/png', 'image/webp'];
$allowed_doc_types   = ['application/pdf', 'image/jpeg', 'image/png'];
$max_file_size       = 5 * 1024 * 1024;
$upload_dir          = __DIR__ . '/uploads/applications/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ── Handle Form Submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_school    = trim($_POST['school_uuid'] ?? $school_uuid);
    $applicant_name   = trim($_POST['applicant_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $date_of_birth    = trim($_POST['date_of_birth'] ?? '');
    $gender           = trim($_POST['gender'] ?? '');
    $place_of_birth   = trim($_POST['place_of_birth'] ?? '');
    $nationality      = trim($_POST['nationality'] ?? '');
    $state_of_origin  = trim($_POST['state_of_origin'] ?? '');
    $lga              = trim($_POST['lga'] ?? '');
    $marital_status   = trim($_POST['marital_status'] ?? '');
    
    $staff_category   = trim($_POST['staff_category'] ?? 'Teaching Staff');
    $target_subject   = trim($_POST['target_subject'] ?? '');
    $target_role_other = trim($_POST['target_role_other'] ?? '');
    $qualification    = trim($_POST['qualification'] ?? '');

    // Healthcare
    $blood_group    = trim($_POST['blood_group'] ?? 'O+');
    $genotype       = trim($_POST['genotype'] ?? 'AA');
    $allergies      = trim($_POST['allergies'] ?? 'None');
    $med_conditions = trim($_POST['medical_conditions'] ?? 'None');
    $emerg_contact  = trim($_POST['emergency_contact'] ?? '');
    $physician      = trim($_POST['physician'] ?? '');

    // Build applied_class_or_role
    if ($staff_category === 'Teaching Staff') {
        $applied_class_or_role = 'Teaching Staff - ' . $target_subject;
    } else {
        $applied_class_or_role = 'Non-Teaching Staff - ' . (!empty($target_role_other) ? $target_role_other : $target_subject);
    }

    // ── DEBUG: Log submitted data ──────────────────────────────────────────
    $debug_info['submitted'] = [
        'target_school' => $target_school,
        'applicant_name' => $applicant_name,
        'email' => $email,
        'phone' => $phone,
        'date_of_birth' => $date_of_birth,
        'gender' => $gender,
        'place_of_birth' => $place_of_birth,
        'nationality' => $nationality,
        'state_of_origin' => $state_of_origin,
        'lga' => $lga,
        'marital_status' => $marital_status,
        'staff_category' => $staff_category,
        'target_subject' => $target_subject,
        'qualification' => $qualification,
        'applied_class_or_role' => $applied_class_or_role
    ];

    // ── Validation ──────────────────────────────────────────────────────────
    $errors = [];
    if (empty($target_school))  $errors[] = 'No school selected.';
    if (empty($applicant_name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($phone))          $errors[] = 'Phone number is required.';
    if (empty($date_of_birth))  $errors[] = 'Date of birth is required.';
    if (empty($gender))         $errors[] = 'Gender is required.';
    if (empty($place_of_birth)) $errors[] = 'Place of birth is required.';
    if (empty($nationality))    $errors[] = 'Nationality is required.';
    if (empty($state_of_origin)) $errors[] = 'State/Region is required.';
    if (empty($lga))            $errors[] = 'LGA/City is required.';
    if (empty($marital_status)) $errors[] = 'Marital status is required.';
    if (empty($qualification))  $errors[] = 'Qualification is required.';
    if (empty($emerg_contact))  $errors[] = 'Emergency contact is required.';
    
    // ── Staff Category Validation ──────────────────────────────────────────
    if ($staff_category === 'Teaching Staff') {
        if (empty($target_subject) || $target_subject === '-- Select Subject --') {
            $errors[] = 'Please select the subject you are applying to teach.';
        }
    } else {
        if (empty($target_subject) && empty($target_role_other)) {
            $errors[] = 'Please select or enter the non-teaching role you are applying for.';
        }
        if ($target_subject === '__other__' && empty($target_role_other)) {
            $errors[] = 'Please specify the other role you are applying for.';
        }
    }

    // ── File uploads ────────────────────────────────────────────────────────
    $photo_error = null;
    $photo_path = handle_image_upload('staff_photo', $upload_dir, 'staff_', '', $max_file_size, $photo_error);
    if (!empty($photo_error)) {
        $errors[] = $photo_error;
    }

    $uploaded_docs = [];
    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        $doc_labels = $_POST['document_labels'] ?? [];
        
        foreach ($_FILES['documents']['name'] as $index => $doc_name) {
            if (empty($doc_name) || $_FILES['documents']['error'][$index] !== UPLOAD_ERR_OK) continue;
            
            $doc_file = [
                'name'     => $_FILES['documents']['name'][$index],
                'type'     => $_FILES['documents']['type'][$index],
                'tmp_name' => $_FILES['documents']['tmp_name'][$index],
                'size'     => $_FILES['documents']['size'][$index],
            ];

            $user_label = trim($doc_labels[$index] ?? '');
            $doc_label  = !empty($user_label) ? $user_label : pathinfo($doc_file['name'], PATHINFO_FILENAME);

            if ($doc_file['size'] > $max_file_size) {
                $errors[] = "'{$doc_label}' exceeds 5MB limit.";
                continue;
            }
            if (!in_array($doc_file['type'], $allowed_doc_types)) {
                $errors[] = "'{$doc_label}' must be PDF, JPEG, or PNG.";
                continue;
            }

            $ext = pathinfo($doc_file['name'], PATHINFO_EXTENSION);
            $filename = 'doc_' . uniqid() . '.' . $ext;
            $dest = $upload_dir . $filename;
            
            if (move_uploaded_file($doc_file['tmp_name'], $dest)) {
                $uploaded_docs[] = [
                    'label' => $doc_label,
                    'path'  => 'uploads/applications/' . $filename,
                    'type'  => $doc_file['type']
                ];
            }
        }
    }

    $documents_json = !empty($uploaded_docs) ? json_encode($uploaded_docs) : null;

    $healthcare_json = json_encode([
        'blood_group'       => $blood_group,
        'genotype'          => $genotype,
        'allergies'         => $allergies,
        'medical_conditions'=> $med_conditions,
        'emergency_contact' => $emerg_contact,
        'physician'         => $physician
    ]);

    // ── If no errors, insert ──────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM public_applications");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $debug_info['columns'] = $columns;

            $app_uuid = 'app-stf-' . uniqid();
            
            $sql = "INSERT INTO public_applications (
                app_uuid, school_uuid, applicant_type, applicant_name,
                email, phone, date_of_birth, gender,
                place_of_birth, nationality, state_of_origin, lga, marital_status,
                applied_class_or_role, qualification,
                photo_path, documents_json,
                healthcare_json, status
            ) VALUES (?, ?, 'staff', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            
            $debug_info['sql'] = $sql;
            
            $stmt = $pdo->prepare($sql);
            
            $values = [
                $app_uuid, $target_school, $applicant_name,
                $email, $phone, $date_of_birth, $gender,
                $place_of_birth, $nationality, $state_of_origin, $lga, $marital_status,
                $applied_class_or_role, $qualification,
                $photo_path, $documents_json,
                $healthcare_json
            ];
            
            $debug_info['values'] = $values;
            
            $result = $stmt->execute($values);
            $debug_info['result'] = $result;
            $debug_info['row_count'] = $stmt->rowCount();

            if ($result && $stmt->rowCount() > 0) {
                $success_msg = "Staff employment application submitted successfully! The school administration will review your qualifications and contact you for an interview.";
            } else {
                $error_info = $stmt->errorInfo();
                $debug_info['pdo_error'] = $error_info;
                $error_msg = "Database error: " . ($error_info[2] ?? 'Unknown error occurred. Please try again.');
            }
            
        } catch (PDOException $e) {
            $debug_info['exception'] = $e->getMessage();
            $error_msg = "Database error: " . $e->getMessage();
        }
    } else {
        $error_msg = implode('<br>', $errors);
        $debug_info['validation_errors'] = $errors;
    }
    
    if (!empty($debug_info) && !empty($error_msg)) {
        $_SESSION['debug_info'] = $debug_info;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $html_class; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($brand_name); ?> — Staff Application</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/ui-components.css">
    <script src="assets/js/ui.js"></script>
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo htmlspecialchars($brand_logo ?: 'logo.png'); ?>">
    <script src="assets/js/lucide.min.js"></script>
    <script src="assets/js/location-fields.js"></script>
    <style>
        :root {
            <?php echo $accent_vars; ?>
            --bg-primary: <?php echo $bg_primary; ?>;
            --bg-secondary: <?php echo $bg_secondary; ?>;
            --bg-tertiary: <?php echo $bg_tertiary; ?>;
            --border-color: <?php echo $border_color; ?>;
            --text-primary: <?php echo $text_primary; ?>;
            --text-secondary: <?php echo $text_secondary; ?>;
        }

        .dark {
            --color-white: #0E1117;
            --color-slate-50: #0A0D12;
            --color-slate-100: #0E1117;
            --color-slate-200: #1E232D;
            --color-slate-300: #2D333B;
            --color-slate-400: #94A3B8;
            --color-slate-500: #64748B;
            --color-slate-600: #475569;
            --color-slate-700: #334155;
            --color-slate-800: #1E293B;
            --color-slate-900: #0F172A;
            --color-slate-950: #020617;
            --color-black: #0E1117;
        }

        .brand-accent { color: var(--brand-color); }
        .brand-bg     { background-color: var(--brand-color); }
        .brand-ring:focus { outline-color: var(--brand-color); border-color: var(--brand-color); }

        .bg-primary   { background-color: var(--bg-primary); }
        .bg-secondary { background-color: var(--bg-secondary); }
        .bg-tertiary  { background-color: var(--bg-tertiary); }
        .border-theme { border-color: var(--border-color); }
        .text-primary { color: var(--text-primary); }
        .text-secondary { color: var(--text-secondary); }
        
        .debug-box {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            font-size: 10px;
            font-family: monospace;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
            color: var(--text-secondary);
        }
    </style>
</head>
<body class="bg-[var(--bg-primary)] text-[var(--text-primary)] min-h-screen flex flex-col selection:bg-indigo-500 selection:text-white">

    <header class="border-b border-[var(--border-color)] bg-[var(--bg-secondary)]/75 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="login.php" class="flex items-center space-x-3">
                <?php if (!empty($brand_logo)): ?>
                    <img src="<?php echo htmlspecialchars($brand_logo); ?>" alt="Logo" class="w-9 h-9 rounded-xl object-cover border border-[var(--border-color)] shadow-lg">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-xl brand-bg flex items-center justify-center shadow-lg">
                        <i data-lucide="graduation-cap" class="w-5 h-5 text-white"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <span class="font-extrabold text-[var(--text-primary)] text-sm tracking-tight"><?php echo htmlspecialchars($brand_name); ?></span>
                    <span class="text-[10px] brand-accent block font-mono">Staff Recruitment</span>
                </div>
            </a>

            <?php if (!$on_subdomain && !empty($all_schools)): ?>
            <form method="GET" class="flex items-center space-x-2">
                <select name="school" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)] focus:outline-none brand-ring">
                    <?php foreach ($all_schools as $sch): ?>
                        <option value="<?php echo htmlspecialchars($sch['subdomain']); ?>" <?php echo ($selected_school && $selected_school['subdomain'] === $sch['subdomain']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-3xl mx-auto w-full px-6 py-10 space-y-8 flex-1">
        
        <?php if (!empty($success_msg)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 p-5 rounded-2xl text-xs text-emerald-400 flex items-start space-x-3 shadow-lg">
                <i data-lucide="check-circle" class="w-6 h-6 shrink-0 mt-0.5"></i>
                <div>
                    <h4 class="font-bold text-sm mb-1">Application Submitted Successfully</h4>
                    <p class="leading-relaxed"><?php echo $success_msg; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 p-5 rounded-2xl text-xs text-rose-400 flex items-start space-x-3 shadow-lg">
                <i data-lucide="alert-circle" class="w-6 h-6 shrink-0 mt-0.5"></i>
                <div>
                    <h4 class="font-bold text-sm mb-1">Submission Error</h4>
                    <p class="leading-relaxed"><?php echo $error_msg; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['debug_info']) && !empty($error_msg)): ?>
            <div class="bg-amber-500/10 border border-amber-500/20 p-4 rounded-2xl">
                <h4 class="text-xs font-bold text-amber-400 mb-2">🔍 Debug Information</h4>
                <div class="debug-box">
                    <?php 
                    $debug = $_SESSION['debug_info'];
                    unset($_SESSION['debug_info']);
                    echo '<pre>';
                    print_r($debug);
                    echo '</pre>';
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center space-y-3">
            <span class="text-[10px] font-bold text-emerald-400 bg-emerald-500/10 px-3 py-1 rounded-full uppercase tracking-wider font-mono">
                Staff Employment Form
            </span>
            <h1 class="text-3xl font-extrabold text-[var(--text-primary)] tracking-tight">
                Join <?php echo htmlspecialchars($selected_school['name'] ?? 'School'); ?>
            </h1>
            <p class="text-xs text-[var(--text-secondary)] max-w-xl mx-auto leading-relaxed">
                Complete all fields below. Upload your CV, certificates, and a passport photo for your application.
            </p>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
            <form method="POST" enctype="multipart/form-data" class="space-y-6" id="staffForm">
                <input type="hidden" name="school_uuid" value="<?php echo htmlspecialchars($school_uuid); ?>">

                <!-- Section 1: Personal Information -->
                <div class="flex items-center space-x-2 text-indigo-400 text-xs font-bold border-b border-[var(--border-color)] pb-3">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <span>1. Personal Information</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Full Name *</label>
                        <input type="text" name="applicant_name" id="applicantName" required placeholder="e.g. Alexander Okon" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['applicant_name'] ?? ''))); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Date of Birth *</label>
                        <input type="date" name="date_of_birth" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Gender *</label>
                        <div class="flex space-x-4 pt-2">
                            <label class="flex items-center space-x-2 text-xs text-[var(--text-primary)] cursor-pointer">
                                <input type="radio" name="gender" value="Male" required <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'checked' : ''; ?> class="accent-emerald-500"> <span>Male</span>
                            </label>
                            <label class="flex items-center space-x-2 text-xs text-[var(--text-primary)] cursor-pointer">
                                <input type="radio" name="gender" value="Female" required <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'checked' : ''; ?> class="accent-emerald-500"> <span>Female</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Place of Birth *</label>
                        <input type="text" name="place_of_birth" id="placeOfBirth" required placeholder="e.g. Lagos, Nigeria" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['place_of_birth'] ?? ''))); ?>">
                    </div>
                </div>

                <!-- Location Fields -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Nationality *</label>
                        <select name="nationality" id="nationality" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Nationality --</option>
                            <option value="Nigerian" <?php echo (($_POST['nationality'] ?? '') === 'Nigerian') ? 'selected' : ''; ?>>Nigerian</option>
                            <option value="Ghanaian" <?php echo (($_POST['nationality'] ?? '') === 'Ghanaian') ? 'selected' : ''; ?>>Ghanaian</option>
                            <option value="Cameroonian" <?php echo (($_POST['nationality'] ?? '') === 'Cameroonian') ? 'selected' : ''; ?>>Cameroonian</option>
                            <option value="Beninese" <?php echo (($_POST['nationality'] ?? '') === 'Beninese') ? 'selected' : ''; ?>>Beninese</option>
                            <option value="Togolese" <?php echo (($_POST['nationality'] ?? '') === 'Togolese') ? 'selected' : ''; ?>>Togolese</option>
                            <option value="Other" <?php echo (($_POST['nationality'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">State / Region *</label>
                        <div id="stateContainer">
                            <select name="state_of_origin" id="stateOfOrigin" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                                <option value="">-- Select State --</option>
                                <?php foreach ($nigerian_states as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>" <?php echo (($_POST['state_of_origin'] ?? '') === $state) ? 'selected' : ''; ?>><?php echo htmlspecialchars($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">LGA / City *</label>
                        <div id="lgaContainer">
                            <select name="lga" id="lgaSelect" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                                <option value="">-- Select State First --</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Marital Status *</label>
                        <select name="marital_status" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Status --</option>
                            <?php foreach ($marital_statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo (($_POST['marital_status'] ?? '') === $status) ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Section 2: Contact Details -->
                <div class="flex items-center space-x-2 text-indigo-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="phone" class="w-4 h-4"></i>
                    <span>2. Contact Details</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Email Address *</label>
                        <input type="email" name="email" required placeholder="teacher@example.com" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all font-mono" value="<?php echo htmlspecialchars(strtolower($_POST['email'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Phone Number *</label>
                        <input type="tel" name="phone" required placeholder="+234 805 000 0000" pattern="[0-9]+" title="Numbers only" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all font-mono" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Section 3: Professional Information -->
                <div class="flex items-center space-x-2 text-indigo-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="briefcase" class="w-4 h-4"></i>
                    <span>3. Professional Information</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Staff Category *</label>
                        <select name="staff_category" id="staffCategory" required onchange="toggleStaffFields()" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="Teaching Staff" <?php echo (($_POST['staff_category'] ?? 'Teaching Staff') === 'Teaching Staff') ? 'selected' : ''; ?>>Teaching Staff</option>
                            <option value="Non-Teaching Staff" <?php echo (($_POST['staff_category'] ?? '') === 'Non-Teaching Staff') ? 'selected' : ''; ?>>Non-Teaching Staff</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Highest Qualification *</label>
                        <select name="qualification" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Qualification --</option>
                            <?php foreach ($qualification_levels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level); ?>" <?php echo (($_POST['qualification'] ?? '') === $level) ? 'selected' : ''; ?>><?php echo htmlspecialchars($level); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Teaching Staff: Subject Dropdown -->
                <div id="teachingField" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Subject Applying To Teach *</label>
                        <select name="target_subject" id="targetSubject" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($academic_subjects as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject); ?>" <?php echo (($_POST['target_subject'] ?? '') === $subject) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($academic_subjects)): ?>
                            <p class="text-[9px] text-amber-400 mt-1">⚠️ No subjects found. Please contact the school administrator.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Non-Teaching Staff: Role Dropdown + Custom Input -->
                <div id="nonTeachingField" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Non-Teaching Role *</label>
                        <select name="target_subject" id="targetRoleSelect" onchange="toggleCustomRoleInput()" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Role --</option>
                            <?php foreach ($non_teaching_roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo (($_POST['target_subject'] ?? '') === $role) ? 'selected' : ''; ?>><?php echo htmlspecialchars($role); ?></option>
                            <?php endforeach; ?>
                            <option value="__other__" <?php echo (($_POST['target_subject'] ?? '') === '__other__') ? 'selected' : ''; ?>>Other (specify below)</option>
                        </select>
                    </div>
                    <div id="customRoleField" class="<?php echo (($_POST['target_subject'] ?? '') === '__other__') ? '' : 'hidden'; ?>">
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Specify Other Role *</label>
                        <input type="text" name="target_role_other" id="targetRoleOther" placeholder="e.g. School Counsellor, Transport Coordinator" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['target_role_other'] ?? ''))); ?>">
                    </div>
                </div>

                <!-- Section 4: Healthcare -->
                <div class="flex items-center space-x-2 text-rose-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="heart-pulse" class="w-4 h-4"></i>
                    <span>4. Healthcare & Medical Records</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Blood Group *</label>
                        <select name="blood_group" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="O+" <?php echo (($_POST['blood_group'] ?? 'O+') === 'O+') ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo (($_POST['blood_group'] ?? '') === 'O-') ? 'selected' : ''; ?>>O-</option>
                            <option value="A+" <?php echo (($_POST['blood_group'] ?? '') === 'A+') ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo (($_POST['blood_group'] ?? '') === 'A-') ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo (($_POST['blood_group'] ?? '') === 'B+') ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo (($_POST['blood_group'] ?? '') === 'B-') ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo (($_POST['blood_group'] ?? '') === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo (($_POST['blood_group'] ?? '') === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Genotype *</label>
                        <select name="genotype" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="AA" <?php echo (($_POST['genotype'] ?? 'AA') === 'AA') ? 'selected' : ''; ?>>AA</option>
                            <option value="AS" <?php echo (($_POST['genotype'] ?? '') === 'AS') ? 'selected' : ''; ?>>AS</option>
                            <option value="SS" <?php echo (($_POST['genotype'] ?? '') === 'SS') ? 'selected' : ''; ?>>SS</option>
                            <option value="AC" <?php echo (($_POST['genotype'] ?? '') === 'AC') ? 'selected' : ''; ?>>AC</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Known Allergies</label>
                        <input type="text" name="allergies" placeholder="e.g. Dust, Latex, None" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['allergies'] ?? 'None'))); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Medical Conditions</label>
                        <input type="text" name="medical_conditions" placeholder="e.g. None" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['medical_conditions'] ?? 'None'))); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Emergency Contact & Phone *</label>
                        <input type="text" name="emergency_contact" id="emergencyContact" required placeholder="e.g. Mrs. Grace Okon - 08055554433" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['emergency_contact'] ?? ''))); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Primary Clinic / Hospital</label>
                        <input type="text" name="physician" id="physician" placeholder="e.g. St. Nicholas Clinic" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['physician'] ?? ''))); ?>">
                    </div>
                </div>

                <!-- Section 5: Document Uploads -->
                <div class="flex items-center space-x-2 text-amber-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    <span>5. Document Uploads</span>
                </div>

                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Passport Photograph</label>
                    <input type="file" name="staff_photo" accept="image/jpeg,image/png,image/webp" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold brand-bg text-white hover:filter:brightness(1.15) transition-all">
                    <p class="text-[9px] text-[var(--text-secondary)] mt-1">JPEG, PNG, or WebP. Max 5MB.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase">CV, Certificates & Credentials</label>
                        <button type="button" onclick="addDocumentRow()" class="text-[10px] font-bold text-emerald-400 hover:text-emerald-300 flex items-center space-x-1 bg-emerald-500/10 px-2.5 py-1 rounded-lg border border-emerald-500/20 transition-all">
                            <i data-lucide="plus" class="w-3 h-3"></i>
                            <span>Add Document</span>
                        </button>
                    </div>
                    <p class="text-[9px] text-[var(--text-secondary)] mb-3">Curriculum Vitae, degree certificates, professional certifications, NYSC discharge certificate, etc.</p>
                    
                    <div id="documentsContainer" class="space-y-3">
                        <div class="document-row flex flex-col sm:flex-row gap-3 p-3 bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)] rounded-xl">
                            <div class="flex-1">
                                <label class="block text-[9px] text-[var(--text-secondary)] font-bold uppercase mb-1">Document Name</label>
                                <input type="text" name="document_labels[]" placeholder="e.g. Curriculum Vitae" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            </div>
                            <div class="flex-1">
                                <label class="block text-[9px] text-[var(--text-secondary)] font-bold uppercase mb-1">File</label>
                                <input type="file" name="documents[]" accept="application/pdf,image/jpeg,image/png" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] file:mr-2 file:py-0.5 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold brand-bg text-white hover:filter:brightness(1.15) transition-all">
                            </div>
                            <div class="flex items-end">
                                <button type="button" onclick="removeDocumentRow(this)" class="p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg transition-all" title="Remove">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-[var(--border-color)]">
                    <button type="submit" class="w-full py-4 brand-bg hover:filter:brightness(1.15) text-white text-xs font-bold rounded-xl transition-all shadow-lg flex items-center justify-center space-x-2">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Submit Staff Employment Application</span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="border-t border-[var(--border-color)] bg-[var(--bg-secondary)]/40 py-6 px-6 text-center text-xs text-[var(--text-secondary)]">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($selected_school['name'] ?? 'Zetaphase EduCloud'); ?>. Powered by Zetaphase EduCloud.</p>
    </footer>

    <script>
        lucide.createIcons();

        // ── Initialize Location Fields ──────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            const nationalitySelect = document.getElementById('nationality');
            const stateContainer = document.getElementById('stateContainer');
            const lgaContainer = document.getElementById('lgaContainer');

            if (nationalitySelect && stateContainer && lgaContainer) {
                initLocationFields('nationality', 'stateContainer', 'lgaContainer');
            }

            toggleStaffFields();
            
            // Preserve LGA selection
            const stateSelect = document.getElementById('stateOfOrigin');
            const lgaSelect = document.getElementById('lgaSelect');
            if (stateSelect && stateSelect.value) {
                const event = new Event('change');
                stateSelect.dispatchEvent(event);
                const submittedLga = '<?php echo htmlspecialchars($_POST['lga'] ?? ''); ?>';
                if (submittedLga && lgaSelect) {
                    setTimeout(function() {
                        lgaSelect.value = submittedLga;
                    }, 100);
                }
            }
        });

        // ── Toggle Teaching / Non-Teaching ──────────────────────────────────
        function toggleStaffFields() {
            const category = document.getElementById('staffCategory').value;
            const teachingField = document.getElementById('teachingField');
            const nonTeachingField = document.getElementById('nonTeachingField');
            const customRoleField = document.getElementById('customRoleField');
            const targetSubject = document.getElementById('targetSubject');
            const targetRoleSelect = document.getElementById('targetRoleSelect');
            const targetRoleOther = document.getElementById('targetRoleOther');

            if (category === 'Teaching Staff') {
                teachingField.classList.remove('hidden');
                nonTeachingField.classList.add('hidden');
                customRoleField.classList.add('hidden');
                targetSubject.required = true;
                targetRoleSelect.required = false;
                targetRoleOther.required = false;
            } else {
                teachingField.classList.add('hidden');
                nonTeachingField.classList.remove('hidden');
                targetSubject.required = false;
                targetRoleSelect.required = true;
                toggleCustomRoleInput();
            }
        }

        function toggleCustomRoleInput() {
            const targetRoleSelect = document.getElementById('targetRoleSelect');
            const customRoleField = document.getElementById('customRoleField');
            const targetRoleOther = document.getElementById('targetRoleOther');

            if (targetRoleSelect.value === '__other__') {
                customRoleField.classList.remove('hidden');
                targetRoleOther.required = true;
                targetRoleSelect.required = false;
            } else {
                customRoleField.classList.add('hidden');
                targetRoleOther.required = false;
                if (targetRoleSelect.value !== '') {
                    targetRoleSelect.required = true;
                }
            }
        }

        // ── Dynamic Document Rows ──────────────────────────────────────────
        function addDocumentRow() {
            const container = document.getElementById('documentsContainer');
            const row = document.createElement('div');
            row.className = 'document-row flex flex-col sm:flex-row gap-3 p-3 bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)] rounded-xl';
            row.innerHTML = `
                <div class="flex-1">
                    <label class="block text-[9px] text-[var(--text-secondary)] font-bold uppercase mb-1">Document Name</label>
                    <input type="text" name="document_labels[]" placeholder="e.g. Degree Certificate" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                </div>
                <div class="flex-1">
                    <label class="block text-[9px] text-[var(--text-secondary)] font-bold uppercase mb-1">File</label>
                    <input type="file" name="documents[]" accept="application/pdf,image/jpeg,image/png" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] file:mr-2 file:py-0.5 file:px-2 file:rounded-md file:border-0 file:text-[10px] file:font-bold brand-bg text-white hover:filter:brightness(1.15) transition-all">
                </div>
                <div class="flex items-end">
                    <button type="button" onclick="removeDocumentRow(this)" class="p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg transition-all" title="Remove">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
            lucide.createIcons();
        }

        function removeDocumentRow(btn) {
            const rows = document.querySelectorAll('.document-row');
            if (rows.length > 1) {
                btn.closest('.document-row').remove();
            }
        }

        // ── Title-case on input (first letter of each word) ──────────────────
        function titleCaseInput(el) {
            const start = el.selectionStart;
            const end = el.selectionEnd;
            
            // Only apply if user has typed something
            if (el.value.length > 0) {
                el.value = el.value.replace(/\w\S*/g, function(w) {
                    return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
                });
            }
            
            try { el.setSelectionRange(start, end); } catch(e) {}
        }

        // Apply to all name/text fields
        document.querySelectorAll(
            'input[name="applicant_name"], input[name="place_of_birth"], ' +
            'input[name="physician"], input[name="emergency_contact"], ' +
            'input[name="target_role_other"], input[name="document_labels[]"], ' +
            'input[name="allergies"], input[name="medical_conditions"]'
        ).forEach(function(el) {
            el.addEventListener('input', function() { 
                // Don't auto-capitalize if it's a placeholder-like value
                if (el.value && el.value !== 'None') {
                    titleCaseInput(el); 
                }
            });
            // Also apply on blur to clean up
            el.addEventListener('blur', function() {
                if (el.value && el.value !== 'None') {
                    titleCaseInput(el);
                }
            });
        });
    </script>
</body>
</html>