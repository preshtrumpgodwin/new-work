<?php
/**
 * Actions: Hostel & Dorms (admin/sections/hostels.php)
 * Split out of the old misc-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_hostel']) && $active_role === 'School Admin') {
    $name   = safe_str($_POST['hostel_name']     ?? '');
    $gender = safe_str($_POST['hostel_gender']   ?? 'Mixed');
    $cap    = safe_int($_POST['hostel_capacity'] ?? 0);
    if ($name) {
        try {
            $uuid = uid('hst');
            $pdo->prepare("INSERT INTO hostels (hostel_uuid,school_uuid,name,gender,capacity) VALUES (?,?,?,?,?)")->execute([$uuid,$school_uuid,$name,$gender,$cap]);
            $success_msg = "Hostel '$name' added!";
        } catch (PDOException $e) { $error_msg = 'Hostel tables not found — run migration first.'; }
    }
}
if (isset($_POST['action_allocate_hostel'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('hostel')))) { $error_msg = 'You do not have permission to manage hostels.'; return; }
    $h_uuid = safe_str($_POST['hostel_uuid']  ?? '');
    $s_uuid = safe_str($_POST['student_uuid'] ?? '');
    $room   = safe_str($_POST['room_number']  ?? '');
    $bed    = safe_str($_POST['bed_number']   ?? '');
    if ($h_uuid && $s_uuid) {
        $sn = $pdo->prepare("SELECT name FROM students WHERE student_uuid=? AND school_uuid=?");
        $sn->execute([$s_uuid,$school_uuid]);
        if ($name = $sn->fetchColumn()) {
            $uuid = uid('ha');
            $pdo->prepare("INSERT INTO hostel_allocations (allocation_uuid,school_uuid,hostel_uuid,student_uuid,student_name,room_number,bed_number,status,allocated_date) VALUES (?,?,?,?,?,?,?,'Active',CURDATE())")
                ->execute([$uuid,$school_uuid,$h_uuid,$s_uuid,$name,$room,$bed]);
            $success_msg = "$name allocated to hostel!";
        }
    }
}
if (isset($_POST['action_vacate_hostel']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('hostel')))) {
    $pdo->prepare("UPDATE hostel_allocations SET status='Vacated' WHERE allocation_uuid=? AND school_uuid=?")->execute([safe_str($_POST['allocation_uuid']??''),$school_uuid]);
    $success_msg = 'Allocation vacated.';
}
