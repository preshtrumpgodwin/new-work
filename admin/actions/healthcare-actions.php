<?php
/**
 * Actions: Healthcare Records (admin/sections/healthcare.php)
 * Split out of the old misc-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_health_record'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('healthcare')))) { $error_msg = 'You do not have permission to log health records.'; return; }
    $p_type  = safe_str($_POST['person_type']     ?? 'Student');
    $p_uuid  = safe_str($_POST['person_uuid']     ?? '');
    $p_name  = safe_str($_POST['person_name']     ?? '');
    $vdate   = safe_str($_POST['visit_date']      ?? date('Y-m-d'));
    $symp    = safe_str($_POST['symptoms']        ?? '');
    $diag    = safe_str($_POST['diagnosis']       ?? '');
    $treat   = safe_str($_POST['treatment']       ?? '');
    $doctor  = safe_str($_POST['attending_staff'] ?? 'School Nurse');
    if (!empty($p_uuid) && !empty($symp)) {
        try {
            $uuid = uid('hlth');
            $pdo->prepare("INSERT INTO healthcare_records (record_uuid,school_uuid,person_type,person_uuid,person_name,visit_date,symptoms,diagnosis,treatment,attending_staff,status) VALUES (?,?,?,?,?,?,?,?,?,?,'Active')")
                ->execute([$uuid,$school_uuid,$p_type,$p_uuid,$p_name,$vdate,$symp,$diag,$treat,$doctor]);
            AuditLog::write($pdo,$school_uuid,$user_uuid,'health.create',$uuid,"Logged visit for $p_name");
            $success_msg = 'Health record logged!';
        } catch (Exception $e) { $error_msg = safe_error('Error', $e); }
    } else { $error_msg = 'Patient UUID and symptoms are required.'; }
}
