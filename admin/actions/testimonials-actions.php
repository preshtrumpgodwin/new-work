<?php
/**
 * Actions: Testimonial Templates (configurable, per school)
 * Split out of the old phase3-actions.php grouping. No dedicated
 * admin/sections/ page name matched this cleanly (closest existing pages are
 * transcripts.php and result_slip_templates.php) — filed here as its own
 * testimonials-actions.php so it's easy to find; still needs a UI page
 * wired to it if one doesn't already exist under a different name.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_save_testimonial_template'])) {
    $name = safe_str($_POST['name'] ?? '');
    $body = trim((string)($_POST['body_html'] ?? ''));
    $is_def = isset($_POST['is_default']) ? 1 : 0;
    if ($name === '' || $body === '') {
        $error_msg = 'Template name and body are required.';
    } else {
        try {
            if ($is_def) {
                $pdo->prepare("UPDATE testimonial_templates SET is_default=0 WHERE school_uuid=?")->execute([$school_uuid]);
            }
            $tuuid = uid('ttp');
            $pdo->prepare("INSERT INTO testimonial_templates (template_uuid,school_uuid,name,body_html,is_default,updated_by) VALUES (?,?,?,?,?,?)")
                ->execute([$tuuid, $school_uuid, $name, $body, $is_def, $_SESSION['name'] ?? 'Admin']);
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'testimonial_template.create', $tuuid, $name);
            $success_msg = "Testimonial template \"$name\" saved!";
        } catch (Exception $e) {
            $error_msg = safe_error('Failed to save testimonial template', $e);
        }
    }
}
if (isset($_POST['action_delete_testimonial_template'])) {
    $tuuid = safe_str($_POST['template_uuid'] ?? '');
    $pdo->prepare("DELETE FROM testimonial_templates WHERE template_uuid=? AND school_uuid=?")->execute([$tuuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'testimonial_template.delete', $tuuid, '');
    $success_msg = 'Testimonial template deleted.';
}
