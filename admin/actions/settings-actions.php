<?php
/**
 * Settings Actions — Phase 2 rebuild.
 *
 * Branding/theme/SMTP/SMS/WhatsApp/payment/AI config are platform-manager-
 * only (see platform/settings.php). The school admin manages Assessment
 * Templates & Configurations, Condition of Service, and School Policy for
 * their own school here — each in its own form/action, saved independently.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $active_role !== 'School Admin') return;

// ── Assessment Templates (JSON-based storage) ──────────────────────────────
if (isset($_POST['action_save_assessment_templates'])) {
    $names = $_POST['template_name'] ?? [];
    $descs = $_POST['template_desc'] ?? [];
    
    try {
        // Build the assessments array. No uuid is stored or needed here —
        // every screen that needs a stable per-template id now derives one
        // deterministically from (school_uuid, name) via
        // assessmentTemplateKey() in Helpers.php, so it's always the same
        // value on every page load and every save, with nothing to persist
        // or get out of sync.
        $assessments = [];
        foreach ($names as $i => $name) {
            $name = safe_str(trim($name));
            $desc = safe_str(trim($descs[$i] ?? ''));
            if ($name === '') continue;
            
            $assessments[] = [
                'name' => $name,
                'description' => $desc,
                'is_active' => true
            ];
        }
        
        // Check for duplicate names
        $seen_names = [];
        foreach ($assessments as $assessment) {
            $norm_name = mb_strtolower($assessment['name']);
            if (in_array($norm_name, $seen_names)) {
                $_SESSION['flash_error'] = "Duplicate assessment name: '{$assessment['name']}'. Please use unique names.";
                header('Location: dashboard.php?section=settings');
                exit;
            }
            $seen_names[] = $norm_name;
        }
        
        // Store as JSON in school_settings
        $json = json_encode($assessments, JSON_UNESCAPED_UNICODE);
        
        // Check if record exists first
        $check = $pdo->prepare("SELECT school_uuid FROM school_settings WHERE school_uuid = ?");
        $check->execute([$school_uuid]);
        
        if ($check->fetchColumn()) {
            // Update existing record
            $pdo->prepare("UPDATE school_settings SET assessment_templates_json = ? WHERE school_uuid = ?")
                ->execute([$json, $school_uuid]);
        } else {
            // Insert new record
            $pdo->prepare("INSERT INTO school_settings (school_uuid, assessment_templates_json) VALUES (?, ?)")
                ->execute([$school_uuid, $json]);
        }
        
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'settings.assessment_templates', '', 'Assessment templates updated via JSON');
        $_SESSION['flash_success'] = 'Assessment templates saved successfully! (' . count($assessments) . ' templates)';
        
    } catch (Exception $e) { 
        $_SESSION['flash_error'] = safe_error('Error: ' . $e->getMessage()); 
    }
    
    header('Location: dashboard.php?section=settings');
    exit;
}

// ── Assessment Configuration (JSON-based storage) ──────────────────────────
if (isset($_POST['action_save_assessment_config'])) {
    $session  = safe_str($_POST['config_session']  ?? '');
    $term     = safe_str($_POST['config_term']     ?? '');
    $class    = safe_str($_POST['config_class']    ?? '');
    $template = safe_str($_POST['config_template'] ?? '');
    $order    = safe_int($_POST['config_order']    ?? 0);
    $max      = (float)($_POST['config_max_score'] ?? 100);
    $required = isset($_POST['config_required']) ? 1 : 0;

    if ($session === '' || $term === '' || $template === '') {
        $_SESSION['flash_error'] = 'Session, Term, and Assessment are required.';
    } else {
        try {
            // Get existing configurations
            $check = $pdo->prepare("SELECT assessment_configurations_json FROM school_settings WHERE school_uuid = ?");
            $check->execute([$school_uuid]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            
            $configurations = [];
            if ($row && !empty($row['assessment_configurations_json'])) {
                $configurations = json_decode($row['assessment_configurations_json'], true);
                if (!is_array($configurations)) {
                    $configurations = [];
                }
            }
            
            // Check for duplicate configuration
            $duplicate_index = null;
            foreach ($configurations as $index => $config) {
                if ($config['session_name'] === $session && 
                    $config['term_name'] === $term && 
                    $config['class_name'] === $class && 
                    $config['template_name'] === $template) {
                    $duplicate_index = $index;
                    break;
                }
            }
            
            if ($duplicate_index !== null) {
                // Update existing configuration
                $configurations[$duplicate_index] = [
                    'session_name' => $session,
                    'term_name' => $term,
                    'class_name' => $class,
                    'template_name' => $template,
                    'assessment_order' => $order,
                    'max_score' => $max,
                    'is_required' => $required
                ];
                $_SESSION['flash_success'] = 'Assessment configuration updated successfully!';
            } else {
                // Add new configuration
                $configurations[] = [
                    'session_name' => $session,
                    'term_name' => $term,
                    'class_name' => $class,
                    'template_name' => $template,
                    'assessment_order' => $order,
                    'max_score' => $max,
                    'is_required' => $required
                ];
                $_SESSION['flash_success'] = 'Assessment configuration saved successfully!';
            }
            
            // Save back to database — check-then-insert-or-update, same
            // pattern as the templates save above. The previous code always
            // ran a bare UPDATE, which silently affected 0 rows (no PHP/SQL
            // error at all) whenever this school had no school_settings row
            // yet, making the save look successful while nothing was stored.
            $json = json_encode($configurations, JSON_UNESCAPED_UNICODE);
            $exists = $pdo->prepare("SELECT school_uuid FROM school_settings WHERE school_uuid = ?");
            $exists->execute([$school_uuid]);
            if ($exists->fetchColumn()) {
                $pdo->prepare("UPDATE school_settings SET assessment_configurations_json = ? WHERE school_uuid = ?")
                    ->execute([$json, $school_uuid]);
            } else {
                $pdo->prepare("INSERT INTO school_settings (school_uuid, assessment_configurations_json) VALUES (?, ?)")
                    ->execute([$school_uuid, $json]);
            }
                
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'settings.assessment_config', '', "Assessment config saved/updated: $session/$term/" . ($class ?: 'All'));
            
        } catch (Exception $e) { 
            $_SESSION['flash_error'] = safe_error('Error: ' . $e->getMessage()); 
        }
    }
    
    header('Location: dashboard.php?section=settings');
    exit;
}

// ── Delete a single assessment configuration ────────────────────────────────
if (isset($_POST['action_delete_assessment_config'])) {
    $session  = safe_str($_POST['config_session'] ?? '');
    $term     = safe_str($_POST['config_term'] ?? '');
    $class    = safe_str($_POST['config_class'] ?? '');
    $template = safe_str($_POST['config_template'] ?? '');
    
    if ($session !== '' && $term !== '' && $template !== '') {
        try {
            // Get existing configurations
            $check = $pdo->prepare("SELECT assessment_configurations_json FROM school_settings WHERE school_uuid = ?");
            $check->execute([$school_uuid]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            
            $configurations = [];
            if ($row && !empty($row['assessment_configurations_json'])) {
                $configurations = json_decode($row['assessment_configurations_json'], true);
                if (!is_array($configurations)) {
                    $configurations = [];
                }
            }
            
            // Find and remove the configuration
            $found = false;
            foreach ($configurations as $index => $config) {
                if ($config['session_name'] === $session && 
                    $config['term_name'] === $term && 
                    $config['class_name'] === $class && 
                    $config['template_name'] === $template) {
                    unset($configurations[$index]);
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                // Re-index array
                $configurations = array_values($configurations);
                // Save back to database
                $json = json_encode($configurations, JSON_UNESCAPED_UNICODE);
                $pdo->prepare("UPDATE school_settings SET assessment_configurations_json = ? WHERE school_uuid = ?")
                    ->execute([$json, $school_uuid]);
                AuditLog::write($pdo, $school_uuid, $user_uuid, 'settings.assessment_config', '', 'Assessment config deleted');
                $_SESSION['flash_success'] = 'Configuration deleted successfully.';
            } else {
                $_SESSION['flash_error'] = 'Configuration not found.';
            }
            
        } catch (Exception $e) { 
            $_SESSION['flash_error'] = safe_error('Error: ' . $e->getMessage()); 
        }
    }
    
    header('Location: dashboard.php?section=settings');
    exit;
}

// ── Grading Scale (moved here from the Platform Manager) ───────────────────
if (isset($_POST['action_save_grading_scale'])) {
    $grade_min    = $_POST['grade_min']    ?? [];
    $grade_max    = $_POST['grade_max']    ?? [];
    $grade_letter = $_POST['grade_letter'] ?? [];
    $grade_remark = $_POST['grade_remark'] ?? [];
    $grade_points = $_POST['grade_points'] ?? [];

    $grading_scale = [];
    $count = count($grade_min);
    for ($i = 0; $i < $count; $i++) {
        if (isset($grade_min[$i], $grade_max[$i], $grade_letter[$i], $grade_remark[$i], $grade_points[$i])) {
            $grading_scale[] = [
                'min'    => (float)$grade_min[$i],
                'max'    => (float)$grade_max[$i],
                'grade'  => trim($grade_letter[$i]),
                'remark' => trim($grade_remark[$i]),
                'points' => (float)$grade_points[$i],
            ];
        }
    }
    $grading_json = json_encode($grading_scale);
    try {
        $pdo->prepare("UPDATE school_settings SET grading_json = ? WHERE school_uuid = ?")
            ->execute([$grading_json, $school_uuid]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'settings.grading_scale', $school_uuid, 'Updated grading scale');
        $_SESSION['flash_success'] = 'Grading scale saved successfully.';
    } catch (Exception $e) {
        $_SESSION['flash_error'] = safe_error('Error: ' . $e->getMessage());
    }
    
    header('Location: dashboard.php?section=settings');
    exit;
}

// ── Condition of Service (moved here from the Platform Manager) ────────────
if (isset($_POST['action_save_condition_of_service'])) {
    $text = trim($_POST['condition_of_service_text'] ?? '');
    try {
        $pdo->prepare("UPDATE schools SET condition_of_service_text = ? WHERE school_uuid = ?")
            ->execute([$text, $school_uuid]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'settings.condition_of_service', $school_uuid, 'Updated condition of service');
        $_SESSION['flash_success'] = 'Condition of Service saved successfully.';
    } catch (Exception $e) {
        $_SESSION['flash_error'] = safe_error('Error: ' . $e->getMessage());
    }
    
    header('Location: dashboard.php?section=settings');
    exit;
}

// ── School Policy (moved here from the Platform Manager) ───────────────────
if (isset($_POST['action_save_school_policy'])) {
    $text = trim($_POST['school_policy_text'] ?? '');
    try {
        $pdo->prepare("UPDATE schools SET school_policy_text = ? WHERE school_uuid = ?")
            ->execute([$text, $school_uuid]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'settings.school_policy', $school_uuid, 'Updated school policy');
        $_SESSION['flash_success'] = 'School Policy saved successfully.';
    } catch (Exception $e) {
        $_SESSION['flash_error'] = safe_error('Error: ' . $e->getMessage());
    }
    
    header('Location: dashboard.php?section=settings');
    exit;
}
?>