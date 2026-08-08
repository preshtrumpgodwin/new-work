<?php
/**
 * Actions: Email Centre (admin/sections/email_centre.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_send_email'])) {
    $group   = safe_str($_POST['recipient_group'] ?? 'All Parents');
    $subject = safe_str($_POST['subject']         ?? '');
    $body    = safe_str($_POST['body_html']       ?? '');
    if ($subject && $body) {
        $recipients = [];
        try {
            if ($group === 'All Parents') {
                $recipients = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT DISTINCT email, name FROM parents WHERE school_uuid=? AND email != ''"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
            } elseif ($group === 'All Staff') {
                $recipients = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT DISTINCT email, name FROM staff WHERE school_uuid=? AND email != ''"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
            } elseif ($group === 'Custom' && !empty($_POST['custom_email'])) {
                $recipients = [['email' => safe_str($_POST['custom_email']), 'name' => 'Recipient']];
            }
        } catch (Exception $e) {}

        $sent_count = 0; $last_response = '';
        foreach ($recipients as $r) {
            $res = Mailer::send($school_settings, $r['email'], $r['name'], $subject, nl2br(htmlspecialchars($body)));
            $last_response = $res['response'];
            if ($res['success']) $sent_count++;
        }
        $status = $sent_count > 0 ? 'Sent' : (empty($recipients) ? 'No Recipients' : 'Failed');
        $uuid = uid('eml');
        $pdo->prepare("INSERT INTO email_log (email_uuid,school_uuid,recipient_group,subject,body_html,recipient_count,status,gateway_response,sent_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$group,$subject,$body,count($recipients),$status,$last_response,$_SESSION['name']??'Admin']);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'email.send',$uuid,"Sent to $sent_count/" . count($recipients) . " ($group)");
        if (empty($recipients)) {
            $error_msg = "No recipients found for '$group' — nothing sent.";
        } elseif ($sent_count === 0) {
            $error_msg = "Email not delivered — $last_response";
        } else {
            $success_msg = "Email sent to $sent_count of " . count($recipients) . " recipient(s)." . ($sent_count < count($recipients) ? " Some failed: $last_response" : '');
        }
    } else { $error_msg = 'Subject and message body are required.'; }
}
