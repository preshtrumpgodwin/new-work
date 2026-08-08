<?php
/**
 * Actions: Transport & Logistics (admin/sections/transport.php)
 * Split out of the old misc-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_route']) && $active_role === 'School Admin') {
    $rname  = safe_str($_POST['route_name']     ?? '');
    $driver = safe_str($_POST['driver_name']    ?? '');
    $veh    = safe_str($_POST['vehicle_number'] ?? '');
    $cap    = safe_int($_POST['route_capacity'] ?? 0);
    $fee    = max(0, (float)($_POST['fee_amount'] ?? 0));
    if ($rname) {
        try {
            $uuid = uid('rt');
            $pdo->prepare("INSERT INTO transport_routes (route_uuid,school_uuid,route_name,driver_name,vehicle_number,capacity,fee_amount) VALUES (?,?,?,?,?,?,?)")
                ->execute([$uuid,$school_uuid,$rname,$driver,$veh,$cap,$fee]);
            $success_msg = "Route '$rname' added!";
        } catch (PDOException $e) { $error_msg = 'Transport tables not found — run migration first.'; }
    }
}
if (isset($_POST['action_assign_transport'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('transport')))) { $error_msg = 'You do not have permission to manage transport.'; return; }
    $r_uuid = safe_str($_POST['route_uuid']   ?? '');
    $s_uuid = safe_str($_POST['student_uuid'] ?? '');
    $pickup = safe_str($_POST['pickup_point'] ?? '');
    if ($r_uuid && $s_uuid) {
        $sn = $pdo->prepare("SELECT name FROM students WHERE student_uuid=? AND school_uuid=?");
        $sn->execute([$s_uuid,$school_uuid]);
        if ($name = $sn->fetchColumn()) {
            $uuid = uid('ta');
            $pdo->prepare("INSERT INTO transport_allocations (allocation_uuid,school_uuid,route_uuid,student_uuid,student_name,pickup_point,status) VALUES (?,?,?,?,?,?,'Active')")
                ->execute([$uuid,$school_uuid,$r_uuid,$s_uuid,$name,$pickup]);
            $success_msg = "$name assigned to route!";
        }
    }
}
if (isset($_POST['action_remove_transport']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('transport')))) {
    $pdo->prepare("UPDATE transport_allocations SET status='Inactive' WHERE allocation_uuid=? AND school_uuid=?")->execute([safe_str($_POST['allocation_uuid']??''),$school_uuid]);
    $success_msg = 'Removed from route.';
}
