<?php
/**
 * Actions: Notice Board / News (admin/sections/notice_board.php)
 * Split out of the old phase3-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_notice'])) {
    $title  = safe_str($_POST['title']           ?? '');
    $cat    = safe_str($_POST['category']        ?? 'Announcement');
    $body   = safe_str($_POST['content']          ?? '');
    $edate  = safe_str($_POST['event_date']       ?? '') ?: null;
    $aud    = safe_str($_POST['target_audience']  ?? 'All');
    $sms    = isset($_POST['sent_sms_alert']) ? 1 : 0;
    if ($title && $body) {
        $uuid = uid('ntc');
        $pdo->prepare("INSERT INTO school_notices_calendar (notice_uuid,school_uuid,title,category,content,event_date,target_audience,sent_sms_alert) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$title,$cat,$body,$edate,$aud,$sms]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'notice.create',$uuid,"Posted notice: $title");
        Notify::role($pdo,$school_uuid, $aud === 'Staff' ? 'Teacher' : ($aud === 'Parents' ? 'Parent' : 'All'), "New Notice: $title", mb_substr($body,0,140), 'info', 'dashboard.php?section=notice_board');
        if ($cat === 'Activity') {
            // Auto-triggered — uses the configurable "activity" template in
            // Broadcast Centre > Automated Templates, sent to all parents
            // with a phone on file. Independent of the manual checkbox below.
            NotificationEngine::fireActivityTrigger($pdo, $school_uuid, $title);
        }
        if ($sms) {
            $sr = SMSGateway::send($school_settings, '', "$title — $body");
            $success_msg = "Notice posted! " . ($sr['success'] ? 'SMS alert sent.' : '⚠ SMS alert not sent: ' . $sr['response']);
        } else {
            $success_msg = 'Notice posted to board!';
        }
    } else { $error_msg = 'Title and content are required.'; }
}
if (isset($_POST['action_delete_notice'])) {
    $pdo->prepare("DELETE FROM school_notices_calendar WHERE notice_uuid=? AND school_uuid=?")->execute([safe_str($_POST['notice_uuid']??''),$school_uuid]);
    $success_msg = 'Notice removed.';
}
