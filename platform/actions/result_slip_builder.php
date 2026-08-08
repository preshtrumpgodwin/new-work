<?php
require_once __DIR__ . '/../../config/security.php';
secure_session_start();

if (isset($_POST['action_save_platform_result_slip_template'])) {
    $name = trim($_POST['name'] ?? 'Untitled Template');
    $layout_raw = trim($_POST['layout_json'] ?? '[]');
    $edit_uuid = trim($_POST['template_uuid'] ?? '');
    $remove_bg = ($_POST['remove_background_image'] ?? '0') === '1';

    $decoded = json_decode($layout_raw, true);
    $elements = (is_array($decoded) && isset($decoded['elements'])) ? $decoded['elements'] : [];

    if (json_last_error() !== JSON_ERROR_NONE || empty($elements)) {
        $_SESSION['flash_error'] = 'Please add at least one field to the A4 sheet before saving.';
        header('Location: index.php?page=result_slip_builder' . ($edit_uuid ? '&edit=' . urlencode($edit_uuid) : ''));
        exit;
    }

    // Figure out the existing background (for edits) so a save with no new
    // upload and no "remove" click keeps it, instead of silently wiping it.
    $existing_bg = '';
    if ($edit_uuid !== '') {
        $cq = $pdo->prepare("SELECT layout_json FROM result_slip_templates WHERE template_uuid=? AND school_uuid IS NULL");
        $cq->execute([$edit_uuid]);
        $cur = json_decode((string)$cq->fetchColumn(), true);
        $existing_bg = $cur['page']['background_image'] ?? '';
    }

    $bg_error = null;
    $upload_dir = __DIR__ . '/../../uploads/result_slip_backgrounds/';
    $bg_path = $remove_bg ? '' : handle_image_upload('background_image', $upload_dir, 'rstbg_', $existing_bg, 5_242_880, $bg_error);
    if (!empty($bg_error)) {
        $_SESSION['flash_error'] = $bg_error;
        header('Location: index.php?page=result_slip_builder' . ($edit_uuid ? '&edit=' . urlencode($edit_uuid) : ''));
        exit;
    }

    $final_layout = json_encode([
        'page' => [
            'background_image' => $bg_path ?: null,
            'background_color' => $decoded['page']['background_color'] ?? '#ffffff',
        ],
        'elements' => $elements,
    ]);

    try {
        if ($edit_uuid !== '') {
            $pdo->prepare("UPDATE result_slip_templates SET name=?, layout_json=? WHERE template_uuid=? AND school_uuid IS NULL")
                ->execute([$name, $final_layout, $edit_uuid]);
            $_SESSION['flash_success'] = "Base template \"$name\" updated!";
        } else {
            $tuuid = uid('rst');
            $pdo->prepare("INSERT INTO result_slip_templates (template_uuid, school_uuid, name, layout_json, is_platform_default, created_by)
                VALUES (?, NULL, ?, ?, 0, ?)")
                ->execute([$tuuid, $name, $final_layout, $_SESSION['name'] ?? 'Platform Manager']);
            $_SESSION['flash_success'] = "Base template \"$name\" saved!";
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = safe_error('Failed to save template', $e);
    }
    header('Location: index.php?page=result_slip_builder');
    exit;
}

if (isset($_POST['action_delete_platform_result_slip_template'])) {
    $tuuid = trim($_POST['template_uuid'] ?? '');
    $pdo->prepare("DELETE FROM result_slip_templates WHERE template_uuid=? AND school_uuid IS NULL")->execute([$tuuid]);
    $_SESSION['flash_success'] = 'Base template deleted.';
    header('Location: index.php?page=result_slip_builder');
    exit;
}
