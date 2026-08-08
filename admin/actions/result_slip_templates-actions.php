<?php
/**
 * Actions: Result Slip Templates (admin/sections/result_slip_templates.php)
 * Split out of the old phase5-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_select_result_slip_template']) && $active_role === 'School Admin') {
    $tuuid = safe_str($_POST['template_uuid'] ?? '');
    $pdo->prepare("UPDATE schools SET active_result_slip_template_uuid=? WHERE school_uuid=?")->execute([$tuuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'result_slip_template.select', $tuuid, '');
    $success_msg = 'Result slip template updated!';
}
if (isset($_POST['action_save_school_result_slip_template']) && $active_role === 'School Admin') {
    $name = safe_str($_POST['name'] ?? 'Custom Template');
    $layout_raw = trim((string)($_POST['layout_json'] ?? '[]'));
    $remove_bg = ($_POST['remove_background_image'] ?? '0') === '1';
    $decoded = json_decode($layout_raw, true);
    $elements = (is_array($decoded) && isset($decoded['elements'])) ? $decoded['elements'] : [];

    if (json_last_error() !== JSON_ERROR_NONE || empty($elements)) {
        $error_msg = 'Please add at least one field to the A4 sheet before saving.';
    } else {
        $bg_error = null;
        $upload_dir = __DIR__ . '/../../uploads/result_slip_backgrounds/';
        $bg_path = $remove_bg ? '' : handle_image_upload('background_image', $upload_dir, 'rstbg_' . $school_uuid . '_', '', 5_242_880, $bg_error);
        if (!empty($bg_error)) {
            $error_msg = $bg_error;
        } else {
            $final_layout = json_encode([
                'page' => [
                    'background_image' => $bg_path ?: null,
                    'background_color' => $decoded['page']['background_color'] ?? '#ffffff',
                ],
                'elements' => $elements,
            ]);
            $tuuid = uid('rst');
            $pdo->prepare("INSERT INTO result_slip_templates (template_uuid,school_uuid,name,layout_json,created_by) VALUES (?,?,?,?,?)")
                ->execute([$tuuid, $school_uuid, $name, $final_layout, $_SESSION['name'] ?? 'Admin']);
            $pdo->prepare("UPDATE schools SET active_result_slip_template_uuid=? WHERE school_uuid=?")->execute([$tuuid, $school_uuid]);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'result_slip_template.save', $tuuid, $name);
            $success_msg = "Template \"$name\" saved and set active!";
        }
    }
}
