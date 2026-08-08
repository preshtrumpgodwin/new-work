<?php
/**
 * SECTION: attendance — Phase 3 rebuild.
 * Adds the term-open/holiday/school-day gating banner and a manual
 * mark/override form, in addition to the existing log view.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Attendance' => null]);
?>
<!-- SECTION: ATTENDANCE LOGS -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <?php if ($section === 'attendance'): ?>
                    <?php
                    $att_date = $_GET['att_date'] ?? date('Y-m-d');
                    $att_class = $_GET['att_class'] ?? '';
                    $att_arm   = $_GET['att_arm']   ?? '';
                    $attendance = [];
                    try {
                        $sql = "SELECT a.*, s.name as student_name, s.class, s.arm, s.roll_number FROM attendance_records a JOIN students s ON a.student_uuid = s.student_uuid WHERE a.school_uuid = ? AND a.date = ?";
                        $params = [$school_uuid, $att_date];
                        if (!empty($att_class)) {
                            $sql .= " AND s.class = ?";
                            $params[] = $att_class;
                        }
                        if (!empty($att_arm)) {
                            $sql .= " AND s.arm = ?";
                            $params[] = $att_arm;
                        }
                        $sql .= " ORDER BY s.name ASC";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $attendance = $stmt->fetchAll();
                    } catch (Exception $e) {}

                    $att_rule = attendanceMarkable($pdo, $school_uuid, $att_date);
                    $can_mark_attendance = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, $current_access ?? 'hide');

                    // Arms — scoped to the selected class only (arms belong to a class).
                    $att_arms = [];
                    if (!empty($att_class)) {
                        try {
                            $aq = $pdo->prepare("SELECT arm_name FROM academic_arms WHERE school_uuid=? AND class_name=? ORDER BY id ASC");
                            $aq->execute([$school_uuid, $att_class]);
                            $att_arms = $aq->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        } catch (Exception $e) {}
                    }

                    // Students in the selected class (needed to render the marking form)
                    $att_students = [];
                    if (!empty($att_class)) {
                        try {
                            $sq_sql = "SELECT student_uuid, name, roll_number FROM students WHERE school_uuid=? AND class=? AND status='Active'";
                            $sq_params = [$school_uuid, $att_class];
                            if (!empty($att_arm)) { $sq_sql .= " AND arm=?"; $sq_params[] = $att_arm; }
                            $sq_sql .= " ORDER BY name ASC";
                            $sq = $pdo->prepare($sq_sql);
                            $sq->execute($sq_params);
                            $att_students = $sq->fetchAll();
                        } catch (Exception $e) {}
                    }
                    $attendance_by_student = [];
                    foreach ($attendance as $a) { $attendance_by_student[$a['student_uuid']] = $a['status']; }
                    ?>
                    <div class="space-y-6">
                        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
                            <div>
                                <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                                    <i data-lucide="calendar-check" class="w-6 h-6 text-blue-400"></i>
                                    <span>Attendance Log</span>
                                </h1>
                                <p class="text-xs text-[var(--text-secondary)]">Auto-marked on open school days. Class teachers can override or mark manually below.</p>
                            </div>
                        </div>

                        <?php if (!$att_rule['allowed']): ?>
                            <div class="flex items-start gap-3 p-4 bg-amber-500/5 border border-amber-500/20 rounded-2xl text-xs">
                                <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 text-amber-400"></i>
                                <div>
                                    <p class="font-bold text-amber-400">Attendance is not being marked for <?php echo htmlspecialchars($att_date); ?></p>
                                    <p class="text-[var(--text-secondary)] mt-0.5"><?php echo htmlspecialchars($att_rule['reason']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
                            <input type="hidden" name="section" value="attendance">
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--text-secondary)] uppercase mb-1">Date</label>
                                <input type="date" name="att_date" value="<?php echo htmlspecialchars($att_date); ?>" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--text-secondary)] uppercase mb-1">Class Filter</label>
                                <select name="att_class" id="attClassSel" onchange="document.getElementById('attArmSel').value=''; this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                                    <option value="">All Classes</option>
                                    <?php foreach ($roster_classes as $cl): ?>
                                        <option value="<?php echo htmlspecialchars($cl); ?>" <?php echo ($att_class === $cl) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--text-secondary)] uppercase mb-1">Arm Filter</label>
                                <select name="att_arm" id="attArmSel" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]" <?php echo empty($att_class) ? 'disabled' : ''; ?>>
                                    <option value="">All Arms</option>
                                    <?php foreach ($att_arms as $ar): ?>
                                        <option value="<?php echo htmlspecialchars($ar); ?>" <?php echo ($att_arm === $ar) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ar); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <?php if ($can_mark_attendance && $att_rule['allowed'] && !empty($att_class)): ?>
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Mark / Override Attendance — <?php echo htmlspecialchars($att_class); ?> on <?php echo htmlspecialchars($att_date); ?></h3>
                            <?php if (empty($att_students)): ?>
                                <p class="text-xs text-[var(--text-secondary)] italic">No active students in this class.</p>
                            <?php else: ?>
                            <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_mark_attendance" value="1">
                                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($att_date); ?>">
                                <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                                            <tr><th class="p-2">Student</th><th class="p-2">Roll No</th><th class="p-2">Status</th></tr>
                                        </thead>
                                        <tbody class="divide-y divide-[var(--border-color)]">
                                            <?php foreach ($att_students as $st): ?>
                                            <tr>
                                                <td class="p-2 font-bold"><?php echo htmlspecialchars($st['name']); ?></td>
                                                <td class="p-2 font-mono text-indigo-400"><?php echo htmlspecialchars($st['roll_number']); ?></td>
                                                <td class="p-2">
                                                    <input type="hidden" name="student_uuid[]" value="<?php echo htmlspecialchars($st['student_uuid']); ?>">
                                                    <?php $cur_status = $attendance_by_student[$st['student_uuid']] ?? 'Present'; ?>
                                                    <select name="status[]" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]">
                                                        <?php foreach (['Present','Absent','Late','Excused'] as $opt): ?>
                                                        <option value="<?php echo $opt; ?>" <?php echo $cur_status === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl">Save Attendance</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($can_mark_attendance && $att_rule['allowed'] && empty($att_class)): ?>
                            <p class="text-xs text-[var(--text-secondary)] italic">Select a class above to mark or override attendance for that date.</p>
                        <?php endif; ?>

                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                                    <tr>
                                        <th class="p-3">Student</th><th class="p-3">Class</th><th class="p-3">Roll No</th>
                                        <th class="p-3">Status</th><th class="p-3">Marked</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--border-color)]">
                                    <?php if (empty($attendance)): ?>
                                        <tr><td colspan="5" class="p-6 text-center text-[var(--text-secondary)] italic">No records for this date.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($attendance as $att): ?>
                                            <tr>
                                                <td class="p-3 font-bold"><?php echo htmlspecialchars($att['student_name']); ?></td>
                                                <td class="p-3"><?php echo htmlspecialchars($att['class']); ?></td>
                                                <td class="p-3 font-mono text-indigo-400"><?php echo htmlspecialchars($att['roll_number']); ?></td>
                                                <td class="p-3">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $att['status'] === 'Present' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>">
                                                        <?php echo htmlspecialchars($att['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="p-3 text-[10px] text-[var(--text-secondary)]"><?php echo $att['auto_marked'] ? 'Auto' : 'Manual'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════ -->
