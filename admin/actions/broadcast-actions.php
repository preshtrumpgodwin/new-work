<?php
/**
 * Actions: SMS & Broadcast Centre (admin/sections/broadcast.php)
 * Split out of the old misc-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_send_broadcast']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    $channel  = safe_str($_POST['channel']         ?? 'SMS');
    $group    = safe_str($_POST['recipient_group'] ?? 'All Parents');
    $msg_text = safe_str($_POST['message_text']    ?? '');
    $sent_by  = $_SESSION['name'] ?? 'Admin';
    if (!empty($msg_text)) {
        $phones = match($group) {
            'All Parents'  => (function() use ($pdo, $school_uuid) {
                $st = $pdo->prepare("SELECT DISTINCT phone FROM parents WHERE school_uuid=? AND phone != ''");
                $st->execute([$school_uuid]); return $st->fetchAll(PDO::FETCH_COLUMN);
            })(),
            'All Staff'    => (function() use ($pdo, $school_uuid) {
                $st = $pdo->prepare("SELECT DISTINCT phone FROM staff WHERE school_uuid=? AND phone != ''");
                $st->execute([$school_uuid]); return $st->fetchAll(PDO::FETCH_COLUMN);
            })(),
            'Debtors'      => (function() use ($pdo, $school_uuid) {
                $st = $pdo->prepare("SELECT DISTINCT p.phone FROM parents p JOIN students s ON s.parent_uuid=p.parent_uuid JOIN school_invoices i ON i.student_uuid=s.student_uuid WHERE p.school_uuid=? AND i.status IN ('Unpaid','Partial') AND p.phone != ''");
                $st->execute([$school_uuid]); return $st->fetchAll(PDO::FETCH_COLUMN);
            })(),
            'Non-Debtors'  => (function() use ($pdo, $school_uuid) {
                $st = $pdo->prepare("SELECT DISTINCT p.phone FROM parents p JOIN students s ON s.parent_uuid=p.parent_uuid WHERE p.school_uuid=? AND p.phone != '' AND s.student_uuid NOT IN (SELECT student_uuid FROM school_invoices WHERE school_uuid=? AND status IN ('Unpaid','Partial') AND student_uuid IS NOT NULL)");
                $st->execute([$school_uuid, $school_uuid]); return $st->fetchAll(PDO::FETCH_COLUMN);
            })(),
            default        => [],
        };
        $rcount = count($phones);
        $sent_ok = 0; $last_response = '';
        foreach ($phones as $phone) {
            $r = SMSGateway::send($school_settings, $phone, $msg_text);
            $last_response = $r['response'];
            if ($r['success']) $sent_ok++;
        }
        $status = $rcount === 0 ? 'No Recipients' : ($sent_ok > 0 ? 'Sent' : 'Failed');
        $uuid = uid('bc');
        $pdo->prepare("INSERT INTO broadcast_messages (broadcast_uuid,school_uuid,channel,recipient_group,message_text,recipient_count,status,sent_by,gateway_response) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$channel,$group,$msg_text,$rcount,$status,$sent_by,$last_response]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'broadcast.send',$uuid,"Sent to $sent_ok/$rcount via $channel ($group)");
        if ($rcount === 0) {
            $error_msg = "No phone numbers on file for '$group' — nothing sent.";
        } elseif ($sent_ok === 0) {
            $error_msg = "Broadcast not delivered — $last_response";
        } else {
            $success_msg = "Broadcast sent to $sent_ok of $rcount recipient(s)." . ($sent_ok < $rcount ? " Some failed: $last_response" : '');
        }
    }
}

// ── NOTIFICATION ENGINE TEMPLATES (birthday slots + triggers) ──────────────────
// (moved from phase3-actions.php — these configure the automated
// birthday/activity/exam-result messages Broadcast Centre sends out)
if (isset($_POST['action_save_birthday_template'])) {
    $aud   = safe_str($_POST['audience'] ?? '');
    $slot  = max(1, min(10, safe_int($_POST['slot_index'] ?? 1)));
    $title = safe_str($_POST['title'] ?? "Slot $slot");
    $body  = trim((string)($_POST['body'] ?? ''));
    $active = isset($_POST['is_active']) ? 1 : 0;
    if (!in_array($aud, ['student','staff','parent'], true) || $body === '') {
        $error_msg = 'A valid audience and message body are required.';
    } else {
        try {
            if ($active) {
                // Only one active slot per audience at a time.
                $pdo->prepare("UPDATE notification_templates SET is_active=0 WHERE school_uuid=? AND category='birthday' AND audience=?")->execute([$school_uuid, $aud]);
            }
            $existing = $pdo->prepare("SELECT template_uuid FROM notification_templates WHERE school_uuid=? AND category='birthday' AND audience=? AND slot_index=?");
            $existing->execute([$school_uuid, $aud, $slot]);
            $tuuid = $existing->fetchColumn();
            if ($tuuid) {
                $pdo->prepare("UPDATE notification_templates SET title=?, body=?, is_active=?, updated_by=? WHERE template_uuid=?")
                    ->execute([$title, $body, $active, $_SESSION['name'] ?? 'Admin', $tuuid]);
            } else {
                $tuuid = uid('ntp');
                $pdo->prepare("INSERT INTO notification_templates (template_uuid,school_uuid,category,audience,slot_index,title,body,is_active,updated_by)
                    VALUES (?,?, 'birthday', ?, ?, ?, ?, ?, ?)")
                    ->execute([$tuuid, $school_uuid, $aud, $slot, $title, $body, $active, $_SESSION['name'] ?? 'Admin']);
            }
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'notification_template.birthday.save', $tuuid, "$aud slot $slot" . ($active ? ' (active)' : ''));
            $success_msg = "Birthday template saved (" . ucfirst($aud) . " — Slot $slot)!";
        } catch (Exception $e) {
            $error_msg = safe_error('Failed to save birthday template', $e);
        }
    }
}

if (isset($_POST['action_save_trigger_template'])) {
    $tk    = safe_str($_POST['trigger_key'] ?? '');
    $body  = trim((string)($_POST['body'] ?? ''));
    $active = isset($_POST['is_active']) ? 1 : 0;
    if (!in_array($tk, ['exam_result','activity'], true) || $body === '') {
        $error_msg = 'A valid trigger and message body are required.';
    } else {
        try {
            $existing = $pdo->prepare("SELECT template_uuid FROM notification_templates WHERE school_uuid=? AND category='trigger' AND trigger_key=?");
            $existing->execute([$school_uuid, $tk]);
            $tuuid = $existing->fetchColumn();
            if ($tuuid) {
                $pdo->prepare("UPDATE notification_templates SET body=?, is_active=?, updated_by=? WHERE template_uuid=?")
                    ->execute([$body, $active, $_SESSION['name'] ?? 'Admin', $tuuid]);
            } else {
                $tuuid = uid('ntp');
                $pdo->prepare("INSERT INTO notification_templates (template_uuid,school_uuid,category,trigger_key,title,body,is_active,updated_by)
                    VALUES (?,?, 'trigger', ?, ?, ?, ?, ?)")
                    ->execute([$tuuid, $school_uuid, $tk, ucfirst(str_replace('_',' ',$tk)), $body, $active, $_SESSION['name'] ?? 'Admin']);
            }
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'notification_template.trigger.save', $tuuid, $tk . ($active ? ' (active)' : ' (inactive)'));
            $success_msg = 'Trigger template saved!';
        } catch (Exception $e) {
            $error_msg = safe_error('Failed to save trigger template', $e);
        }
    }
}
