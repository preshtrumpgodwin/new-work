<?php
// Student Admission Application Portal — Subdomain-Aware
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
$school_subdomain = $selected_school['subdomain'] ?? '';

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

// ── Nigerian States ────────────────────────────────────────────────────────
$nigerian_states = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
    'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT (Abuja)', 'Gombe',
    'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos',
    'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto',
    'Taraba', 'Yobe', 'Zamfara'
];

$religions = ['Christianity', 'Islam', 'Traditional', 'Other'];

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
    $target_school  = trim($_POST['school_uuid'] ?? $school_uuid);
    $applicant_name = trim($_POST['applicant_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $date_of_birth  = trim($_POST['date_of_birth'] ?? '');
    $gender         = trim($_POST['gender'] ?? '');
    $place_of_birth = trim($_POST['place_of_birth'] ?? '');
    $nationality    = trim($_POST['nationality'] ?? '');
    $state_of_origin = trim($_POST['state_of_origin'] ?? '');
    $lga            = trim($_POST['lga'] ?? '');
    $religion       = trim($_POST['religion'] ?? '');
    $target_class   = trim($_POST['target_class'] ?? '');
    $parent_name    = trim($_POST['parent_name'] ?? '');
    $parent_phone   = trim($_POST['parent_phone'] ?? '');
    $parent_email   = trim(strtolower($_POST['parent_email'] ?? ''));

    // ── Auto-generate student email ───────────────────────────────────────
    $generated_email = null;
    if (!empty($applicant_name) && !empty($school_subdomain)) {
        $clean_name = preg_replace('/\b(Mr\.?|Mrs\.?|Miss\.?|Ms\.?|Dr\.?|Prof\.?|Chief\.?|Alhaji\.?|Hajiya\.?|Sir\.?|Engr\.?|Barr\.?)\s*/i', '', $applicant_name);
        $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($clean_name));
        $clean_name = preg_replace('/_+/', '', $clean_name);
        $clean_name = substr($clean_name, 0, 30);
        
        $subdomain_part = explode('.', $school_subdomain)[0];
        $generated_email = $clean_name . '@' . $subdomain_part;
    }

    // Healthcare
    $blood_group    = trim($_POST['blood_group'] ?? 'O+');
    $genotype       = trim($_POST['genotype'] ?? 'AA');
    $allergies      = trim($_POST['allergies'] ?? 'None');
    $med_conditions = trim($_POST['medical_conditions'] ?? 'None');
    $emerg_contact  = trim($_POST['emergency_contact'] ?? '');
    $physician      = trim($_POST['physician'] ?? '');

    $applied_class_or_role = $target_class;

    // ── DEBUG: Log submitted data ──────────────────────────────────────────
    $debug_info['submitted'] = [
        'target_school' => $target_school,
        'applicant_name' => $applicant_name,
        'phone' => $phone,
        'date_of_birth' => $date_of_birth,
        'gender' => $gender,
        'place_of_birth' => $place_of_birth,
        'nationality' => $nationality,
        'state_of_origin' => $state_of_origin,
        'lga' => $lga,
        'religion' => $religion,
        'target_class' => $target_class,
        'parent_name' => $parent_name,
        'parent_phone' => $parent_phone,
        'parent_email' => $parent_email,
        'generated_email' => $generated_email,
        'applied_class_or_role' => $applied_class_or_role
    ];

    // ── Validation ──────────────────────────────────────────────────────────
    $errors = [];
    if (empty($target_school))  $errors[] = 'No school selected.';
    if (empty($applicant_name)) $errors[] = 'Student name is required.';
    if (empty($phone))          $errors[] = 'Phone number is required.';
    if (empty($date_of_birth))  $errors[] = 'Date of birth is required.';
    if (empty($gender))         $errors[] = 'Gender is required.';
    if (empty($place_of_birth)) $errors[] = 'Place of birth is required.';
    if (empty($nationality))    $errors[] = 'Nationality is required.';
    if (empty($state_of_origin)) $errors[] = 'State/Region is required.';
    if (empty($lga))            $errors[] = 'LGA/City is required.';
    if (empty($religion))       $errors[] = 'Religion is required.';
    if (empty($target_class))   $errors[] = 'Class applying for is required.';
    if (empty($parent_name))    $errors[] = 'Parent/Guardian name is required.';
    if (empty($parent_phone))   $errors[] = 'Parent phone number is required.';
    if (empty($emerg_contact))  $errors[] = 'Emergency contact is required.';
    if (empty($generated_email)) $errors[] = 'Unable to generate student email. Please contact support.';

    // ── File uploads ────────────────────────────────────────────────────────
    $photo_error = null;
    $photo_path = handle_image_upload('student_photo', $upload_dir, 'student_', '', $max_file_size, $photo_error);
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
            // Check table columns
            $stmt = $pdo->query("SHOW COLUMNS FROM public_applications");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $debug_info['columns'] = $columns;

            $app_uuid = 'app-stu-' . uniqid();
            
            // ── FIX: Match columns to values count ──────────────────────────
            // Column count: 20 columns
            // Values count: 20 values (including app_uuid, target_school, etc.)
            $sql = "INSERT INTO public_applications (
                app_uuid, school_uuid, applicant_type, applicant_name,
                email, phone, date_of_birth, gender,
                place_of_birth, nationality, state_of_origin, lga, religion,
                applied_class_or_role,
                parent_name, parent_phone, parent_email,
                photo_path, documents_json,
                healthcare_json, status
            ) VALUES (
                ?, ?, 'student', ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, 
                ?, ?, ?,
                ?, ?,
                ?, 'Pending'
            )";
            
            $debug_info['sql'] = $sql;
            
            $stmt = $pdo->prepare($sql);
            
            // ── Values must match column count exactly ──────────────────────
            $values = [
                $app_uuid,                                              // app_uuid
                $target_school,                                         // school_uuid
                $applicant_name,                                        // applicant_name
                $generated_email,                                       // email
                $phone,                                                 // phone
                $date_of_birth,                                         // date_of_birth
                $gender,                                                // gender
                $place_of_birth,                                        // place_of_birth
                $nationality,                                           // nationality
                $state_of_origin,                                       // state_of_origin
                $lga,                                                   // lga
                $religion,                                              // religion
                $applied_class_or_role,                                 // applied_class_or_role
                $parent_name,                                           // parent_name
                $parent_phone,                                          // parent_phone
                $parent_email,                                          // parent_email
                $photo_path,                                            // photo_path
                $documents_json,                                        // documents_json
                $healthcare_json,                                       // healthcare_json
                'Pending'                                               // status
            ];
            
            $debug_info['values'] = $values;
            $debug_info['values_count'] = count($values);
            
            $result = $stmt->execute($values);
            $debug_info['result'] = $result;
            $debug_info['row_count'] = $stmt->rowCount();

            if ($result && $stmt->rowCount() > 0) {
                $success_msg = "Student admission application submitted successfully!<br><br>
                               <strong>Student Email:</strong> {$generated_email}<br>
                               The school administration will review your application and contact you via email or phone.";
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
    
    // ── Store debug info in session for display ────────────────────────────
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
    <title><?php echo htmlspecialchars($brand_name); ?> — Student Admission</title>
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

        .email-preview {
            font-size: 10px;
            color: var(--text-secondary);
            padding: 4px 12px;
            background: var(--bg-tertiary);
            border-radius: 8px;
            display: inline-block;
            border: 1px dashed var(--border-color);
            font-family: monospace;
        }
        .email-preview .highlight {
            color: var(--brand-color);
            font-weight: bold;
        }
        
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
                    <span class="text-[10px] brand-accent block font-mono">Student Admission</span>
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
            <span class="text-[10px] font-bold text-indigo-400 bg-indigo-500/10 px-3 py-1 rounded-full uppercase tracking-wider font-mono">
                Student Admission Form
            </span>
            <h1 class="text-3xl font-extrabold text-[var(--text-primary)] tracking-tight">
                Apply to <?php echo htmlspecialchars($selected_school['name'] ?? 'School'); ?>
            </h1>
            <p class="text-xs text-[var(--text-secondary)] max-w-xl mx-auto leading-relaxed">
                Complete all fields below. A school email will be auto-generated for the student.
            </p>
        </div>

        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="school_uuid" value="<?php echo htmlspecialchars($school_uuid); ?>">

                <!-- Section 1: Personal Information -->
                <div class="flex items-center space-x-2 text-indigo-400 text-xs font-bold border-b border-[var(--border-color)] pb-3">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <span>1. Personal Information</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Student Full Name *</label>
                        <input type="text" name="applicant_name" id="studentName" required placeholder="e.g. Emmanuel Joe" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="updateEmailPreview(); titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['applicant_name'] ?? ''))); ?>">
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
                                <input type="radio" name="gender" value="Male" required <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'checked' : ''; ?> class="accent-indigo-500"> <span>Male</span>
                            </label>
                            <label class="flex items-center space-x-2 text-xs text-[var(--text-primary)] cursor-pointer">
                                <input type="radio" name="gender" value="Female" required <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'checked' : ''; ?> class="accent-indigo-500"> <span>Female</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Place of Birth *</label>
                        <input type="text" name="place_of_birth" id="placeOfBirth" required placeholder="e.g. Lagos, Nigeria" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['place_of_birth'] ?? ''))); ?>">
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
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Religion *</label>
                        <select name="religion" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Religion --</option>
                            <?php foreach ($religions as $rel): ?>
                                <option value="<?php echo htmlspecialchars($rel); ?>" <?php echo (($_POST['religion'] ?? '') === $rel) ? 'selected' : ''; ?>><?php echo htmlspecialchars($rel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Section 2: Contact & School Email -->
                <div class="flex items-center space-x-2 text-indigo-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="mail" class="w-4 h-4"></i>
                    <span>2. Contact & School Email</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Phone Number *</label>
                        <input type="tel" name="phone" required placeholder="+234 800 000 0000" pattern="[0-9]+" title="Numbers only" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all font-mono" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Class Applying For *</label>
                        <select name="target_class" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($academic_classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class); ?>" <?php echo (($_POST['target_class'] ?? '') === $class) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($academic_classes)): ?>
                            <p class="text-[9px] text-amber-400 mt-1">⚠️ No classes found. Please contact the school administrator.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Student School Email (Auto-Generated)</label>
                    <div class="email-preview" id="emailPreview">
                        <span class="highlight">[Student Name]</span>@<span class="highlight"><?php echo htmlspecialchars(explode('.', $school_subdomain)[0] ?: 'school'); ?></span>
                    </div>
                    <p class="text-[9px] text-[var(--text-secondary)] mt-1">This email will be automatically generated from the student's name and school subdomain.</p>
                </div>

                <!-- Section 3: Parent/Guardian -->
                <div class="flex items-center space-x-2 text-indigo-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>3. Parent / Guardian Details</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Parent / Guardian Full Name *</label>
                        <input type="text" name="parent_name" id="parentName" required placeholder="e.g. Chief Robert Joe" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['parent_name'] ?? ''))); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Parent Phone Number *</label>
                        <input type="tel" name="parent_phone" required placeholder="+234 802 000 0000" pattern="[0-9]+" title="Numbers only" oninput="this.value=this.value.replace(/[^0-9]/g,'')" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all font-mono" value="<?php echo htmlspecialchars($_POST['parent_phone'] ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Parent Email (Optional)</label>
                    <input type="email" name="parent_email" placeholder="parent@example.com" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all font-mono" value="<?php echo htmlspecialchars(strtolower($_POST['parent_email'] ?? '')); ?>">
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
                        <input type="text" name="allergies" placeholder="e.g. Peanuts, Penicillin, None" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['allergies'] ?? 'None'))); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Medical Conditions</label>
                        <input type="text" name="medical_conditions" placeholder="e.g. Asthma, None" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['medical_conditions'] ?? 'None'))); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Emergency Contact & Phone *</label>
                        <input type="text" name="emergency_contact" id="emergencyContact" required placeholder="e.g. Dr. Benson - 08021110000" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['emergency_contact'] ?? ''))); ?>">
                    </div>
                    <div>
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Primary Clinic / Physician</label>
                        <input type="text" name="physician" id="physician" placeholder="e.g. Reddington Hospital" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)" value="<?php echo htmlspecialchars(ucwords(strtolower($_POST['physician'] ?? ''))); ?>">
                    </div>
                </div>

                <!-- Section 5: Document Uploads -->
                <div class="flex items-center space-x-2 text-amber-400 text-xs font-bold border-b border-[var(--border-color)] pb-3 pt-2">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    <span>5. Document Uploads</span>
                </div>

                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">Passport Photograph</label>
                    <input type="file" name="student_photo" accept="image/jpeg,image/png,image/webp" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-4 py-3 text-xs text-[var(--text-primary)] file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold brand-bg text-white hover:filter:brightness(1.15) transition-all">
                    <p class="text-[9px] text-[var(--text-secondary)] mt-1">JPEG, PNG, or WebP. Max 5MB.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase">Supporting Documents</label>
                        <button type="button" onclick="addDocumentRow()" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300 flex items-center space-x-1 bg-indigo-500/10 px-2.5 py-1 rounded-lg border border-indigo-500/20 transition-all">
                            <i data-lucide="plus" class="w-3 h-3"></i>
                            <span>Add Document</span>
                        </button>
                    </div>
                    <p class="text-[9px] text-[var(--text-secondary)] mb-3">Birth certificate, previous term results, transfer letter, etc.</p>
                    
                    <div id="documentsContainer" class="space-y-3">
                        <div class="document-row flex flex-col sm:flex-row gap-3 p-3 bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)] rounded-xl">
                            <div class="flex-1">
                                <label class="block text-[9px] text-[var(--text-secondary)] font-bold uppercase mb-1">Document Name</label>
                                <input type="text" name="document_labels[]" placeholder="e.g. Birth Certificate" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)">
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
                        <span>Submit Student Admission Application</span>
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

            updateEmailPreview();
            
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

        // ── Email Preview ────────────────────────────────────────────────────
        function updateEmailPreview() {
            const nameInput = document.getElementById('studentName');
            const preview = document.getElementById('emailPreview');
            const subdomain = '<?php echo htmlspecialchars(explode('.', $school_subdomain)[0] ?: 'school'); ?>';
            
            if (nameInput && preview) {
                let cleanName = nameInput.value;
                cleanName = cleanName.replace(/\b(Mr\.?|Mrs\.?|Miss\.?|Ms\.?|Dr\.?|Prof\.?|Chief\.?|Alhaji\.?|Hajiya\.?|Sir\.?|Engr\.?|Barr\.?)\s*/gi, '');
                cleanName = cleanName.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
                cleanName = cleanName.replace(/_+/g, '');
                cleanName = cleanName.substring(0, 30);
                
                if (cleanName) {
                    preview.innerHTML = '<span class="highlight">' + cleanName + '</span>@<span class="highlight">' + subdomain + '</span>';
                } else {
                    preview.innerHTML = '<span class="highlight">[Student Name]</span>@<span class="highlight">' + subdomain + '</span>';
                }
            }
        }

        // ── Title-case on input (first letter of each word) ──────────────────
        function titleCaseInput(el) {
            const start = el.selectionStart;
            const end = el.selectionEnd;
            
            // Only apply if user has typed something
            if (el.value && el.value.length > 0 && el.value !== 'None') {
                el.value = el.value.replace(/\w\S*/g, function(w) {
                    return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
                });
            }
            
            try { el.setSelectionRange(start, end); } catch(e) {}
        }

        // ── Dynamic Document Rows ──────────────────────────────────────────
        function addDocumentRow() {
            const container = document.getElementById('documentsContainer');
            const row = document.createElement('div');
            row.className = 'document-row flex flex-col sm:flex-row gap-3 p-3 bg-[var(--bg-tertiary)]/50 border border-[var(--border-color)] rounded-xl';
            row.innerHTML = `
                <div class="flex-1">
                    <label class="block text-[9px] text-[var(--text-secondary)] font-bold uppercase mb-1">Document Name</label>
                    <input type="text" name="document_labels[]" placeholder="e.g. Birth Certificate" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-none brand-ring transition-all" oninput="titleCaseInput(this)" onblur="titleCaseInput(this)">
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

        // ── Auto-title-case on blur for all name fields ─────────────────────
        document.querySelectorAll(
            'input[name="applicant_name"], input[name="parent_name"], ' +
            'input[name="place_of_birth"], input[name="physician"], ' +
            'input[name="emergency_contact"], input[name="document_labels[]"], ' +
            'input[name="allergies"], input[name="medical_conditions"]'
        ).forEach(function(el) {
            // Apply on blur if not already applied
            el.addEventListener('blur', function() {
                if (el.value && el.value !== 'None') {
                    titleCaseInput(el);
                }
            });
        });
    </script>
</body>
</html>