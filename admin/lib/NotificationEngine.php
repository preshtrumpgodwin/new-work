<?php
/**
 * NotificationEngine — shared trigger + template + send engine.
 *
 * Backs three Phase B features off one plumbing layer:
 *   - Birthday alerts (10 editable templates per audience: student/staff/parent)
 *   - Exam-result-published SMS trigger
 *   - Activity-posted SMS trigger
 *
 * All sends go through the existing SMSGateway and are logged to
 * `notification_log` (separate from the manual Broadcast Centre's
 * `broadcast_messages` table, since these are system-triggered rather than
 * admin-composed).
 */
class NotificationEngine
{
    const SLOTS_PER_AUDIENCE = 10;

    /** Replace {{token}} placeholders in a template body. */
    public static function render(string $body, array $tokens): string
    {
        $out = $body;
        foreach ($tokens as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string)$v, $out);
        }
        return $out;
    }

    private static function log(PDO $pdo, string $school_uuid, string $category, ?string $trigger_key, string $name, string $phone, string $message, array $result): void
    {
        try {
            $pdo->prepare("INSERT INTO notification_log (log_uuid,school_uuid,category,trigger_key,recipient_name,recipient_phone,message,status,gateway_response)
                VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
                    uid('nlog'), $school_uuid, $category, $trigger_key, $name, $phone, $message,
                    $result['success'] ? 'Sent' : 'Failed', $result['response'] ?? null,
                ]);
        } catch (Exception $e) { error_log('[NotificationEngine] ' . $e->getMessage()); }
    }

    /**
     * Send one message via SMSGateway, using the school's configured
     * SMS settings, and log the attempt.
     */
    private static function sendOne(PDO $pdo, array $school_settings, string $school_uuid, string $category, ?string $trigger_key, string $name, string $phone, string $message): void
    {
        if (trim($phone) === '') return;
        $result = SMSGateway::send($school_settings, $phone, $message);
        self::log($pdo, $school_uuid, $category, $trigger_key, $name, $phone, $message, $result);
    }

    /**
     * Fetch the currently-active template for a birthday audience, or null.
     */
    public static function activeBirthdayTemplate(PDO $pdo, string $school_uuid, string $audience): ?array
    {
        $st = $pdo->prepare("SELECT * FROM notification_templates WHERE school_uuid=? AND category='birthday' AND audience=? AND is_active=1 LIMIT 1");
        $st->execute([$school_uuid, $audience]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /** Fetch the currently-active trigger template, or null. */
    public static function activeTriggerTemplate(PDO $pdo, string $school_uuid, string $trigger_key): ?array
    {
        $st = $pdo->prepare("SELECT * FROM notification_templates WHERE school_uuid=? AND category='trigger' AND trigger_key=? AND is_active=1 LIMIT 1");
        $st->execute([$school_uuid, $trigger_key]);
        $r = $st->fetch();
        return $r ?: null;
    }

    /**
     * Run birthday alerts for one school for "today". Intended to be called
     * once per school per day (see scripts/send_birthday_alerts.php cron).
     * Safe to call more than once a day — it doesn't dedupe by design (kept
     * simple); wire dedupe via notification_log if double-sends become an
     * issue in practice.
     */
    public static function runBirthdaysForSchool(PDO $pdo, string $school_uuid): int
    {
        $sent = 0;
        $ss = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid=? LIMIT 1");
        $ss->execute([$school_uuid]);
        $school_settings = $ss->fetch() ?: [];

        $sc = $pdo->prepare("SELECT name FROM schools WHERE school_uuid=? LIMIT 1");
        $sc->execute([$school_uuid]);
        $school_name = $sc->fetchColumn() ?: 'the school';

        $today = date('m-d');

        // Students — message sent to the linked parent's phone (students
        // typically have no personal phone on file).
        $tplStudent = self::activeBirthdayTemplate($pdo, $school_uuid, 'student');
        if ($tplStudent) {
            $q = $pdo->prepare("SELECT s.*, p.phone AS parent_phone, p.name AS parent_name
                FROM students s LEFT JOIN parents p ON p.parent_uuid = s.parent_uuid
                WHERE s.school_uuid=? AND s.status='Active' AND s.date_of_birth IS NOT NULL
                AND DATE_FORMAT(s.date_of_birth, '%m-%d') = ?");
            $q->execute([$school_uuid, $today]);
            foreach ($q->fetchAll() as $row) {
                if (empty($row['parent_phone'])) continue;
                $msg = self::render($tplStudent['body'], [
                    'student_name' => $row['name'], 'class' => $row['class'] ?? '',
                    'parent_name' => $row['parent_name'] ?? 'Parent/Guardian', 'school_name' => $school_name,
                ]);
                self::sendOne($pdo, $school_settings, $school_uuid, 'birthday', null, $row['name'], $row['parent_phone'], $msg);
                $sent++;
            }
        }

        // Staff — own phone.
        $tplStaff = self::activeBirthdayTemplate($pdo, $school_uuid, 'staff');
        if ($tplStaff) {
            $q = $pdo->prepare("SELECT * FROM staff WHERE school_uuid=? AND status='Active' AND date_of_birth IS NOT NULL
                AND DATE_FORMAT(date_of_birth, '%m-%d') = ?");
            $q->execute([$school_uuid, $today]);
            foreach ($q->fetchAll() as $row) {
                if (empty($row['phone'])) continue;
                $msg = self::render($tplStaff['body'], ['staff_name' => $row['name'], 'role' => $row['role'] ?? '', 'school_name' => $school_name]);
                self::sendOne($pdo, $school_settings, $school_uuid, 'birthday', null, $row['name'], $row['phone'], $msg);
                $sent++;
            }
        }

        // Parents — own phone.
        $tplParent = self::activeBirthdayTemplate($pdo, $school_uuid, 'parent');
        if ($tplParent) {
            $q = $pdo->prepare("SELECT * FROM parents WHERE school_uuid=? AND date_of_birth IS NOT NULL
                AND DATE_FORMAT(date_of_birth, '%m-%d') = ?");
            $q->execute([$school_uuid, $today]);
            foreach ($q->fetchAll() as $row) {
                if (empty($row['phone'])) continue;
                $msg = self::render($tplParent['body'], ['parent_name' => $row['name'], 'school_name' => $school_name]);
                self::sendOne($pdo, $school_settings, $school_uuid, 'birthday', null, $row['name'], $row['phone'], $msg);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Fire the exam-result-published trigger for one student.
     * Called from report-card-actions.php on approval.
     */
    public static function fireExamResultTrigger(PDO $pdo, string $school_uuid, string $student_uuid, string $session, string $term): void
    {
        $tpl = self::activeTriggerTemplate($pdo, $school_uuid, 'exam_result');
        if (!$tpl) return;

        $q = $pdo->prepare("SELECT s.name, s.class, p.phone AS parent_phone, p.name AS parent_name
            FROM students s LEFT JOIN parents p ON p.parent_uuid = s.parent_uuid
            WHERE s.student_uuid=? AND s.school_uuid=? LIMIT 1");
        $q->execute([$student_uuid, $school_uuid]);
        $row = $q->fetch();
        if (!$row || empty($row['parent_phone'])) return;

        $ss = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid=? LIMIT 1");
        $ss->execute([$school_uuid]);
        $school_settings = $ss->fetch() ?: [];
        $sc = $pdo->prepare("SELECT name FROM schools WHERE school_uuid=? LIMIT 1");
        $sc->execute([$school_uuid]);
        $school_name = $sc->fetchColumn() ?: 'the school';

        $msg = self::render($tpl['body'], [
            'student_name' => $row['name'], 'class' => $row['class'] ?? '',
            'parent_name' => $row['parent_name'] ?? 'Parent/Guardian',
            'session' => $session, 'term' => $term, 'school_name' => $school_name,
        ]);
        self::sendOne($pdo, $school_settings, $school_uuid, 'trigger', 'exam_result', $row['name'], $row['parent_phone'], $msg);
    }

    /**
     * Fire the activity-posted trigger (e.g. a Notice Board post tagged
     * "Activity"). Sends to all parents with a phone on file.
     */
    public static function fireActivityTrigger(PDO $pdo, string $school_uuid, string $activity_title): void
    {
        $tpl = self::activeTriggerTemplate($pdo, $school_uuid, 'activity');
        if (!$tpl) return;

        $ss = $pdo->prepare("SELECT * FROM school_settings WHERE school_uuid=? LIMIT 1");
        $ss->execute([$school_uuid]);
        $school_settings = $ss->fetch() ?: [];
        $sc = $pdo->prepare("SELECT name FROM schools WHERE school_uuid=? LIMIT 1");
        $sc->execute([$school_uuid]);
        $school_name = $sc->fetchColumn() ?: 'the school';

        $msg = self::render($tpl['body'], ['activity_title' => $activity_title, 'school_name' => $school_name]);

        $q = $pdo->prepare("SELECT name, phone FROM parents WHERE school_uuid=? AND phone IS NOT NULL AND phone != ''");
        $q->execute([$school_uuid]);
        foreach ($q->fetchAll() as $row) {
            self::sendOne($pdo, $school_settings, $school_uuid, 'trigger', 'activity', $row['name'], $row['phone'], $msg);
        }
    }
}
