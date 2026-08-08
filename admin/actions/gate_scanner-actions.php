<?php
/**
 * Actions: Gate Scanner (QR/Barcode Attendance) (admin/sections/gate_scanner.php)
 * Split out of the old phase4-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// ── QR/BARCODE ATTENDANCE — daily vs static mode toggle ─────────────────────────
if (isset($_POST['action_save_gate_qr_mode']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    $mode = safe_str($_POST['gate_qr_mode'] ?? 'daily');
    if (!in_array($mode, ['daily','static'], true)) $mode = 'daily';
    $pdo->prepare("UPDATE schools SET gate_qr_mode=? WHERE school_uuid=?")->execute([$mode, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'gate.qr_mode.update', '', $mode);
    $success_msg = 'Gate QR mode updated!';
}
