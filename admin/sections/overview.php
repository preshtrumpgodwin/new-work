<?php
/**
 * SECTION: Dashboard Overview
 * Always visible — no feature flag required.
 */

render_breadcrumb(['Dashboard Overview' => null]);

// ── Staff Self Check-In widget (Phase B — QR/Barcode Attendance) ───────────
$is_staff_role = !in_array($active_role, ['School Admin','Platform Manager'], true);
?>
<?php if ($is_staff_role): ?>
<div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4 mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-2">
        <i data-lucide="scan-line" class="w-5 h-5 text-cyan-400"></i>
        <span class="text-xs font-bold text-[var(--text-primary)]">Mark your gate attendance by scanning the poster at the entrance.</span>
    </div>
    <button type="button" onclick="document.getElementById('selfCheckinModal').classList.remove('hidden')" class="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl">Scan Gate QR</button>
</div>
<div id="selfCheckinModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 max-w-sm w-full space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Scan Gate QR</h3>
            <button type="button" onclick="document.getElementById('selfCheckinModal').classList.add('hidden'); scStopCam();" class="text-[var(--text-secondary)]">✕</button>
        </div>
        <video id="scVideo" class="w-full rounded-xl border border-[var(--border-color)] bg-black aspect-video" muted playsinline></video>
        <canvas id="scCanvas" class="hidden"></canvas>
        <div id="scResult" class="text-xs"></div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
(function(){
    let scStream = null, scLoopId = null;
    window.scStopCam = function() {
        if (scStream) { scStream.getTracks().forEach(t => t.stop()); scStream = null; }
        if (scLoopId) { cancelAnimationFrame(scLoopId); scLoopId = null; }
    };
    document.getElementById('selfCheckinModal')?.addEventListener('transitionend', function(){});
    const btn = document.querySelector('[onclick*="selfCheckinModal"][onclick*="remove"]');
    if (btn) btn.addEventListener('click', async function() {
        const video = document.getElementById('scVideo');
        const canvas = document.getElementById('scCanvas');
        const resultEl = document.getElementById('scResult');
        resultEl.textContent = '';
        try {
            scStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            video.srcObject = scStream;
            await video.play();
            const ctx = canvas.getContext('2d');
            const tick = () => {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = window.jsQR ? jsQR(imageData.data, imageData.width, imageData.height) : null;
                    if (code && code.data) {
                        scStopCam();
                        fetch('api/self-checkin.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'code=' + encodeURIComponent(code.data) })
                            .then(r => r.json())
                            .then(d => {
                                resultEl.innerHTML = d.success
                                    ? '<span class="text-emerald-400 font-bold">✓ ' + d.check_type + ' logged at ' + d.time + '</span>'
                                    : '<span class="text-rose-400 font-bold">' + (d.error || 'Failed') + '</span>';
                            });
                        return;
                    }
                }
                scLoopId = requestAnimationFrame(tick);
            };
            tick();
        } catch (e) { resultEl.innerHTML = '<span class="text-rose-400">Camera access denied or unavailable.</span>'; }
    });
})();
</script>
<?php endif; ?>

<?php
// ── Stat counts ────────────────────────────────────────────────────────────
$stats = ['students' => 0, 'staff' => 0, 'parents' => 0, 'present_today' => 0, 'absent_today' => 0];
try {
    $stCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_uuid=? AND status='Active'");
    $stCount->execute([$school_uuid]);
    $stats['students'] = (int)$stCount->fetchColumn();
    $stats['staff']         = (int)(function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT COUNT(*) FROM staff   WHERE school_uuid=? AND status='Active'"); $__st->execute([$school_uuid]); return $__st->fetchColumn(); })();
    $stats['parents']       = (int)(function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE school_uuid=?"); $__st->execute([$school_uuid]); return $__st->fetchColumn(); })();

    $attRow = $pdo->prepare("SELECT
        SUM(status='Present') AS present,
        SUM(status='Absent')  AS absent
        FROM attendance_records
        WHERE school_uuid=? AND date=?");
    $attRow->execute([$school_uuid, date('Y-m-d')]);
    $attData = $attRow->fetch();
    $stats['present_today'] = (int)($attData['present'] ?? 0);
    $stats['absent_today']  = (int)($attData['absent']  ?? 0);
} catch (Exception $e) {}

// ── Chart data: attendance trend (last 7 days) ────────────────────────────
$attendance_trend = ['labels' => [], 'present' => [], 'absent' => []];
try {
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $r = $pdo->prepare("SELECT SUM(status='Present') p, SUM(status='Absent') a FROM attendance_records WHERE school_uuid=? AND date=?");
        $r->execute([$school_uuid, $d]);
        $row = $r->fetch();
        $attendance_trend['labels'][] = date('D', strtotime($d));
        $attendance_trend['present'][] = (int)($row['p'] ?? 0);
        $attendance_trend['absent'][]  = (int)($row['a'] ?? 0);
    }
} catch (Exception $e) {}

// ── Chart data: fee collection (paid vs outstanding) ──────────────────────
$fee_chart = ['paid' => 0, 'outstanding' => 0];
try {
    $fr = $pdo->prepare("SELECT
        SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END) as paid_full,
        SUM(CASE WHEN status IN ('Unpaid','Partial') THEN amount ELSE 0 END) as owed_total
        FROM school_invoices WHERE school_uuid=?");
    $fr->execute([$school_uuid]);
    $frow = $fr->fetch();
    $paid_partial = (float)(function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT COALESCE(SUM(r.amount),0) FROM school_receipts r JOIN school_invoices i ON i.invoice_uuid=r.invoice_uuid WHERE i.school_uuid=? AND i.status='Partial'"); $__st->execute([$school_uuid]); return $__st->fetchColumn(); })();
    $fee_chart['paid'] = (float)($frow['paid_full'] ?? 0) + $paid_partial;
    $fee_chart['outstanding'] = max(0, (float)($frow['owed_total'] ?? 0) - $paid_partial);
} catch (Exception $e) {}

// ── Chart data: results grade distribution ────────────────────────────────
$grade_chart = ['labels' => [], 'counts' => []];
try {
    $gr = $pdo->prepare("SELECT grade, COUNT(*) c FROM results WHERE school_uuid=? AND grade IS NOT NULL GROUP BY grade ORDER BY grade ASC");
    $gr->execute([$school_uuid]);
    foreach ($gr->fetchAll() as $row) { $grade_chart['labels'][] = $row['grade']; $grade_chart['counts'][] = (int)$row['c']; }
} catch (Exception $e) {}

// ── AI / rule-based insights ───────────────────────────────────────────────
$insights = [];
$total_att = array_sum($attendance_trend['present']) + array_sum($attendance_trend['absent']);
if ($total_att > 0) {
    $att_rate = round(array_sum($attendance_trend['present']) / $total_att * 100, 1);
    $insights[] = $att_rate >= 90
        ? "Attendance is strong at {$att_rate}% over the last 7 days."
        : "Attendance dipped to {$att_rate}% over the last 7 days — worth a closer look.";
}
if (($fee_chart['paid'] + $fee_chart['outstanding']) > 0) {
    $collection_rate = round($fee_chart['paid'] / ($fee_chart['paid'] + $fee_chart['outstanding']) * 100, 1);
    $insights[] = "Fee collection stands at {$collection_rate}% (₦" . number_format($fee_chart['paid'],0) . " collected, ₦" . number_format($fee_chart['outstanding'],0) . " outstanding).";
}
if (!empty($grade_chart['counts'])) {
    $top_idx = array_search(max($grade_chart['counts']), $grade_chart['counts']);
    $insights[] = "The most common grade this term is '{$grade_chart['labels'][$top_idx]}' (" . $grade_chart['counts'][$top_idx] . " result(s)).";
}
if (empty($insights)) $insights[] = "Not enough data yet to generate insights — check back once attendance, fees, and results start coming in.";

// ── Recent activity ────────────────────────────────────────────────────────
$recent_students = [];
$recent_staff    = [];
try {
    $s = $pdo->prepare("SELECT name, class, roll_number FROM students WHERE school_uuid=? ORDER BY id DESC LIMIT 5");
    $s->execute([$school_uuid]);
    $recent_students = $s->fetchAll();

    $s2 = $pdo->prepare("SELECT name, role, date_employed FROM staff WHERE school_uuid=? ORDER BY id DESC LIMIT 5");
    $s2->execute([$school_uuid]);
    $recent_staff = $s2->fetchAll();
} catch (Exception $e) {}
?>

<div class="space-y-8">

    <!-- Welcome header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-[var(--text-primary)] tracking-tight">
                Good <?php echo (date('H') < 12) ? 'morning' : ((date('H') < 17) ? 'afternoon' : 'evening'); ?>,
                <?php echo htmlspecialchars(explode(' ', $_SESSION['name'] ?? 'User')[0]); ?> 👋
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">
                <?php echo htmlspecialchars($school['name'] ?? 'School'); ?> &nbsp;·&nbsp;
                <span class="text-indigo-400 font-semibold"><?php echo htmlspecialchars($active_role); ?></span>
                &nbsp;·&nbsp; <?php echo date('l, j F Y'); ?>
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="px-3 py-1.5 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl font-mono font-bold text-[var(--text-secondary)]">
                <?php echo htmlspecialchars($school_settings['current_session'] ?? '—'); ?>
            </span>
            <span class="px-3 py-1.5 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl font-mono font-bold text-[var(--text-secondary)]">
                <?php echo htmlspecialchars($school_settings['current_term'] ?? '—'); ?>
            </span>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <?php
        $kpis = [
            ['label' => 'Active Students', 'value' => number_format($stats['students']),      'icon' => 'users',          'color' => 'text-indigo-400',  'bg' => 'bg-indigo-500/10',  'section' => 'roster'],
            ['label' => 'Staff',           'value' => number_format($stats['staff']),         'icon' => 'briefcase',      'color' => 'text-purple-400',  'bg' => 'bg-purple-500/10',  'section' => 'hr'],
            ['label' => 'Parents',         'value' => number_format($stats['parents']),       'icon' => 'heart-handshake','color' => 'text-pink-400',    'bg' => 'bg-pink-500/10',    'section' => 'parents'],
            ['label' => 'Present Today',   'value' => number_format($stats['present_today']), 'icon' => 'check-circle',   'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10', 'section' => 'attendance'],
            ['label' => 'Absent Today',    'value' => number_format($stats['absent_today']),  'icon' => 'x-circle',       'color' => 'text-rose-400',    'bg' => 'bg-rose-500/10',    'section' => 'attendance'],
        ];
        foreach ($kpis as $k): ?>
        <a href="dashboard.php?section=<?php echo $k['section']; ?>"
           class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 flex flex-col gap-3 hover:border-indigo-500/40 transition-all group shadow-sm">
            <div class="w-9 h-9 rounded-xl <?php echo $k['bg']; ?> flex items-center justify-center">
                <i data-lucide="<?php echo $k['icon']; ?>" class="w-4 h-4 <?php echo $k['color']; ?>"></i>
            </div>
            <div>
                <div class="text-xl font-extrabold text-[var(--text-primary)]"><?php echo $k['value']; ?></div>
                <div class="text-[10px] text-[var(--text-secondary)] font-medium mt-0.5"><?php echo $k['label']; ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Charts + Insights -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
            <h2 class="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-widest mb-4 font-mono">Attendance — Last 7 Days</h2>
            <canvas id="attendanceChart" height="90"></canvas>
        </div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-3">
            <h2 class="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-widest font-mono flex items-center gap-1.5"><i data-lucide="sparkles" class="w-3.5 h-3.5 text-violet-400"></i> Insights</h2>
            <ul class="space-y-2.5">
                <?php foreach ($insights as $ins): ?>
                <li class="text-xs text-[var(--text-primary)] flex gap-2"><span class="text-violet-400">•</span><span><?php echo htmlspecialchars($ins); ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
            <h2 class="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-widest mb-4 font-mono">Fee Collection</h2>
            <canvas id="feeChart" height="140"></canvas>
        </div>
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
            <h2 class="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-widest mb-4 font-mono">Grade Distribution</h2>
            <?php if (empty($grade_chart['counts'])): ?>
                <p class="text-xs text-[var(--text-secondary)] italic py-8 text-center">No graded results yet this term.</p>
            <?php else: ?>
                <canvas id="gradeChart" height="140"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6">
        <h2 class="text-xs font-bold text-[var(--text-secondary)] uppercase tracking-widest mb-4 font-mono">Quick Actions</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <?php
            $actions = [
                ['label' => 'Enroll Student',   'icon' => 'user-plus',      'section' => 'roster',       'color' => 'bg-indigo-600'],
                ['label' => 'Take Attendance',   'icon' => 'calendar-check', 'section' => 'attendance',   'color' => 'bg-blue-600'],
                ['label' => 'Enter Results',     'icon' => 'file-edit',      'section' => 'results',      'color' => 'bg-amber-600'],
                ['label' => 'Broadcast SMS',     'icon' => 'send',           'section' => 'broadcast',    'color' => 'bg-cyan-600'],
            ];
            foreach ($actions as $a): ?>
            <a href="dashboard.php?section=<?php echo $a['section']; ?>"
               class="flex items-center gap-3 p-4 <?php echo $a['color']; ?>/10 border border-[var(--border-color)] hover:border-indigo-400/40 rounded-xl transition-all text-xs font-bold text-[var(--text-primary)]">
                <i data-lucide="<?php echo $a['icon']; ?>" class="w-4 h-4 opacity-70"></i>
                <?php echo $a['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent activity panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Recent admissions -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--text-primary)]">Recent Admissions</h2>
                <a href="dashboard.php?section=roster" class="text-[10px] text-indigo-400 font-bold hover:underline">View all →</a>
            </div>
            <?php if (empty($recent_students)): ?>
                <p class="text-xs text-[var(--text-secondary)] italic">No students enrolled yet.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($recent_students as $rs): ?>
                <div class="flex items-center justify-between p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-[10px]" style="background-color:<?php echo htmlspecialchars($school['theme_color'] ?? '#4F46E5'); ?>">
                            <?php echo strtoupper(substr($rs['name'], 0, 2)); ?>
                        </div>
                        <span class="font-semibold"><?php echo htmlspecialchars($rs['name']); ?></span>
                    </div>
                    <div class="text-right">
                        <span class="block font-mono text-indigo-400"><?php echo htmlspecialchars($rs['class']); ?></span>
                        <span class="block text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($rs['roll_number']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent staff -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-[var(--text-primary)]">Recent Staff</h2>
                <a href="dashboard.php?section=hr" class="text-[10px] text-indigo-400 font-bold hover:underline">View all →</a>
            </div>
            <?php if (empty($recent_staff)): ?>
                <p class="text-xs text-[var(--text-secondary)] italic">No staff records yet.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($recent_staff as $rs): ?>
                <div class="flex items-center justify-between p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-purple-600 flex items-center justify-center text-white font-bold text-[10px]">
                            <?php echo strtoupper(substr($rs['name'], 0, 2)); ?>
                        </div>
                        <span class="font-semibold"><?php echo htmlspecialchars($rs['name']); ?></span>
                    </div>
                    <span class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($rs['role']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){
    const gridColor = 'rgba(148,163,184,0.1)';
    const textColor = '#94a3b8';
    Chart.defaults.color = textColor;
    Chart.defaults.font.size = 10;

    new Chart(document.getElementById('attendanceChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($attendance_trend['labels']); ?>,
            datasets: [
                { label: 'Present', data: <?php echo json_encode($attendance_trend['present']); ?>, borderColor: '#34d399', backgroundColor: 'rgba(52,211,153,0.1)', tension: 0.35, fill: true },
                { label: 'Absent',  data: <?php echo json_encode($attendance_trend['absent']); ?>,  borderColor: '#fb7185', backgroundColor: 'rgba(251,113,133,0.1)', tension: 0.35, fill: true }
            ]
        },
        options: { responsive: true, scales: { x: { grid: { color: gridColor } }, y: { grid: { color: gridColor }, beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('feeChart'), {
        type: 'doughnut',
        data: {
            labels: ['Collected', 'Outstanding'],
            datasets: [{ data: [<?php echo (float)$fee_chart['paid']; ?>, <?php echo (float)$fee_chart['outstanding']; ?>], backgroundColor: ['#34d399', '#fb7185'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    <?php if (!empty($grade_chart['counts'])): ?>
    new Chart(document.getElementById('gradeChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($grade_chart['labels']); ?>,
            datasets: [{ label: 'Results', data: <?php echo json_encode($grade_chart['counts']); ?>, backgroundColor: '#818cf8', borderRadius: 6 }]
        },
        options: { responsive: true, scales: { x: { grid: { display: false } }, y: { grid: { color: gridColor }, beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
    });
    <?php endif; ?>
})();
</script>
