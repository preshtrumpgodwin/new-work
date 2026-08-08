<?php
/**
 * SECTION: assignments — Phase 5 rebuild.
 * Create assignments, approve/reject them (only full-access staff/admin —
 * or via a confirmed parent meeting), and grade submissions. Only Approved
 * assignments are ever visible to parents/students (student-portal.php /
 * parent-portal.php both filter on approval_status = 'Approved').
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Assignments' => null]);
?>
<!-- SECTION: ASSIGNMENTS -->
                <?php if ($section === 'assignments'): ?>
                    <?php
                    $can_write_assignments   = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, $current_access ?? 'hide');
                    $can_approve_assignments = in_array($active_role, ['School Admin','Platform Manager']) || can_approve($active_role, $current_access ?? 'hide');

                    $asg_filter_class  = $_GET['asg_class']  ?? '';
                    $asg_filter_status = $_GET['asg_status'] ?? '';
                    $asg_view_uuid     = $_GET['asg_view']   ?? '';

                    // Fetch academic subjects for dropdown
                    $academic_subjects = [];
                    try {
                        $subj_stmt = $pdo->prepare("SELECT subject_name, subject_code, department_name FROM academic_subjects WHERE school_uuid = ? ORDER BY subject_name ASC");
                        $subj_stmt->execute([$school_uuid]);
                        $academic_subjects = $subj_stmt->fetchAll();
                    } catch (Exception $e) {}

                    $sql = "SELECT * FROM assignments WHERE school_uuid = ?";
                    $params = [$school_uuid];
                    if ($asg_filter_class !== '')  { $sql .= " AND class_name = ?";     $params[] = $asg_filter_class; }
                    if ($asg_filter_status !== '') { $sql .= " AND approval_status = ?"; $params[] = $asg_filter_status; }
                    // Non-full/non-admin staff only see assignments they created, plus their own drafts' status.
                    if (!$can_approve_assignments) { $sql .= " AND assigned_by_staff_uuid = (SELECT staff_uuid FROM staff WHERE user_uuid=? LIMIT 1)"; $params[] = $user_uuid; }
                    $sql .= " ORDER BY created_at DESC";
                    $assignments_list = [];
                    try { $st = $pdo->prepare($sql); $st->execute($params); $assignments_list = $st->fetchAll(); } catch (Exception $e) {}

                    // Confirmed parent-teacher meetings, for the "approve via meeting" picker.
                    $confirmed_meetings = [];
                    try {
                        $cm = $pdo->prepare("SELECT * FROM parent_teacher_appointments WHERE school_uuid=? AND status='Confirmed' ORDER BY meeting_date DESC LIMIT 50");
                        $cm->execute([$school_uuid]);
                        $confirmed_meetings = $cm->fetchAll();
                    } catch (Exception $e) {}

                    $viewing = null; $submissions = [];
                    if ($asg_view_uuid !== '') {
                        try {
                            $vq = $pdo->prepare("SELECT * FROM assignments WHERE assignment_uuid=? AND school_uuid=?");
                            $vq->execute([$asg_view_uuid, $school_uuid]);
                            $viewing = $vq->fetch();
                            if ($viewing) {
                                $sq = $pdo->prepare("SELECT * FROM assignment_submissions WHERE assignment_uuid=? AND school_uuid=? ORDER BY submitted_at DESC");
                                $sq->execute([$asg_view_uuid, $school_uuid]);
                                $submissions = $sq->fetchAll();
                            }
                        } catch (Exception $e) {}
                    }
                    ?>
                    <div class="space-y-6">
                        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                            <i data-lucide="file-check-2" class="w-6 h-6 text-blue-400"></i>
                            <span>Assignments</span>
                        </h1>
                        <p class="text-xs text-[var(--text-secondary)] -mt-4">Only approved assignments become visible to parents and students. A confirmed parent-teacher meeting can also stand in as the approval event.</p>

                        <?php if ($can_write_assignments): ?>
                        <!-- ── Create ─────────────────────────────────────────── -->
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">New Assignment</h3>
                            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_create_assignment" value="1">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase mb-1">Title</label>
                                    <input type="text" name="title" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase mb-1">Subject</label>
                                    <select name="subject" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="">Select a subject</option>
                                        <?php foreach ($academic_subjects as $subj): ?>
                                        <option value="<?php echo htmlspecialchars($subj['subject_name']); ?>">
                                            <?php echo htmlspecialchars($subj['subject_name']); ?>
                                            <?php if ($subj['subject_code']): ?>
                                                (<?php echo htmlspecialchars($subj['subject_code']); ?>)
                                            <?php endif; ?>
                                            <?php if ($subj['department_name']): ?>
                                                - <?php echo htmlspecialchars($subj['department_name']); ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase mb-1">Class</label>
                                    <select name="class_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase mb-1">Due Date</label>
                                    <input type="date" name="due_date" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase mb-1">Max Score</label>
                                    <input type="number" name="max_score" value="100" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase mb-1">Attachment URL (optional)</label>
                                    <input type="url" name="attachment_url" placeholder="https://..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-bold uppercase mb-1">Description / Instructions</label>
                                    <textarea name="description" rows="3" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl">Submit for Approval</button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- ── Filters ────────────────────────────────────────── -->
                        <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
                            <input type="hidden" name="section" value="assignments">
                            <div>
                                <label class="block text-[10px] font-bold uppercase mb-1">Class</label>
                                <select name="asg_class" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                                    <option value="">All Classes</option>
                                    <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>" <?php echo $asg_filter_class===$cl?'selected':''; ?>><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase mb-1">Status</label>
                                <select name="asg_status" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
                                    <option value="">All</option>
                                    <?php foreach (['Pending','Approved','Rejected'] as $st2): ?><option value="<?php echo $st2; ?>" <?php echo $asg_filter_status===$st2?'selected':''; ?>><?php echo $st2; ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <!-- ── List ───────────────────────────────────────────── -->
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                                    <tr><th class="p-3">Title</th><th class="p-3">Class</th><th class="p-3">Due</th><th class="p-3">Set By</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th></tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--border-color)]">
                                    <?php if (empty($assignments_list)): ?>
                                        <tr><td colspan="6" class="p-6 text-center text-[var(--text-secondary)] italic">No assignments yet.</td></tr>
                                    <?php else: foreach ($assignments_list as $a): ?>
                                    <tr>
                                        <td class="p-3 font-bold"><?php echo htmlspecialchars($a['title']); ?><br><span class="text-[10px] text-[var(--text-secondary)] font-normal"><?php echo htmlspecialchars($a['subject']); ?></span></td>
                                        <td class="p-3"><?php echo htmlspecialchars($a['class_name']); ?></td>
                                        <td class="p-3 font-mono"><?php echo htmlspecialchars($a['due_date']); ?></td>
                                        <td class="p-3"><?php echo htmlspecialchars($a['assigned_by_staff_name'] ?: $a['teacher_name']); ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php
                                                echo $a['approval_status']==='Approved' ? 'bg-emerald-500/10 text-emerald-400' : ($a['approval_status']==='Rejected' ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400');
                                            ?>"><?php echo htmlspecialchars($a['approval_status']); ?></span>
                                            <?php if ($a['approval_status'] === 'Approved' && $a['approval_note']): ?>
                                                <div class="text-[9px] text-[var(--text-secondary)] mt-0.5"><?php echo htmlspecialchars($a['approval_note']); ?></div>
                                            <?php elseif ($a['approval_status'] === 'Rejected' && $a['rejection_reason']): ?>
                                                <div class="text-[9px] text-rose-400 mt-0.5"><?php echo htmlspecialchars($a['rejection_reason']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-right space-x-1 whitespace-nowrap">
                                            <a href="dashboard.php?section=assignments&asg_view=<?php echo urlencode($a['assignment_uuid']); ?>" class="px-2 py-1 bg-blue-500/10 text-blue-400 rounded text-[10px] font-bold inline-block">Submissions</a>
                                            <?php if ($can_approve_assignments && !in_array($a['approval_status'], ['Approved','Rejected'])): ?>
                                                <button type="button" onclick="document.getElementById('approveModal-<?php echo $a['assignment_uuid']; ?>').classList.remove('hidden')" class="px-2 py-1 bg-emerald-500/10 text-emerald-400 rounded text-[10px] font-bold">Approve</button>
                                                <button type="button" onclick="document.getElementById('rejectModal-<?php echo $a['assignment_uuid']; ?>').classList.remove('hidden')" class="px-2 py-1 bg-rose-500/10 text-rose-400 rounded text-[10px] font-bold">Reject</button>
                                            <?php endif; ?>
                                            <?php if ($can_approve_assignments): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete this assignment and all its submissions?')"><?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action_delete_assignment" value="1">
                                                    <input type="hidden" name="assignment_uuid" value="<?php echo htmlspecialchars($a['assignment_uuid']); ?>">
                                                    <button type="submit" class="text-rose-400 hover:text-rose-300"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php if ($can_approve_assignments): ?>
                                    <!-- Approve modal (direct or via confirmed meeting) -->
                                    <tr id="approveModal-<?php echo $a['assignment_uuid']; ?>" class="hidden">
                                        <td colspan="6" class="p-4 bg-[var(--bg-tertiary)]">
                                            <form method="POST" class="space-y-2"><?php echo csrf_field(); ?>
                                                <input type="hidden" name="action_approve_assignment" value="1">
                                                <input type="hidden" name="assignment_uuid" value="<?php echo htmlspecialchars($a['assignment_uuid']); ?>">
                                                <label class="block text-[10px] font-bold uppercase">Approve directly, or cite a confirmed parent meeting instead:</label>
                                                <select name="approval_appointment_uuid" class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                                    <option value="">— Direct approval —</option>
                                                    <?php foreach ($confirmed_meetings as $m): ?>
                                                    <option value="<?php echo htmlspecialchars($m['appointment_uuid']); ?>">Meeting: <?php echo htmlspecialchars($m['parent_name'].' — '.$m['meeting_date'].' (re: '.$m['student_name'].')'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white text-[10px] font-bold rounded-lg">Confirm Approval</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <!-- Reject modal -->
                                    <tr id="rejectModal-<?php echo $a['assignment_uuid']; ?>" class="hidden">
                                        <td colspan="6" class="p-4 bg-[var(--bg-tertiary)]">
                                            <form method="POST" class="space-y-2"><?php echo csrf_field(); ?>
                                                <input type="hidden" name="action_reject_assignment" value="1">
                                                <input type="hidden" name="assignment_uuid" value="<?php echo htmlspecialchars($a['assignment_uuid']); ?>">
                                                <label class="block text-[10px] font-bold uppercase">Reason (optional)</label>
                                                <input type="text" name="rejection_reason" class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                                <button type="submit" class="px-4 py-1.5 bg-rose-600 text-white text-[10px] font-bold rounded-lg">Confirm Rejection</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ── Submissions viewer ─────────────────────────────── -->
                        <?php if ($viewing): ?>
                        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                            <h3 class="text-sm font-bold text-[var(--text-primary)]">Submissions — <?php echo htmlspecialchars($viewing['title']); ?> (<?php echo htmlspecialchars($viewing['class_name']); ?>)</h3>
                            <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                                        <tr><th class="p-2">Student</th><th class="p-2">Submitted</th><th class="p-2">Text / File</th><th class="p-2">Score</th><th class="p-2">Feedback</th><th class="p-2">Status</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-[var(--border-color)]">
                                        <?php if (empty($submissions)): ?>
                                            <tr><td colspan="6" class="p-4 text-center text-[var(--text-secondary)] italic">No submissions yet.</td></tr>
                                        <?php else: foreach ($submissions as $s): ?>
                                        <tr>
                                            <td class="p-2 font-bold"><?php echo htmlspecialchars($s['student_name']); ?></td>
                                            <td class="p-2 font-mono"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($s['submitted_at']))); ?></td>
                                            <td class="p-2 max-w-xs truncate" title="<?php echo htmlspecialchars($s['submission_text'] ?? ''); ?>">
                                                <?php echo htmlspecialchars(mb_substr($s['submission_text'] ?? '', 0, 60)); ?>
                                                <?php if ($s['file_url']): ?><a href="<?php echo htmlspecialchars($s['file_url']); ?>" target="_blank" class="text-blue-400 underline block">File link</a><?php endif; ?>
                                            </td>
                                            <?php if ($can_write_assignments && $s['status'] !== 'Graded'): ?>
                                            <td colspan="3" class="p-2">
                                                <form method="POST" class="flex flex-wrap items-center gap-2"><?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action_grade_submission" value="1">
                                                    <input type="hidden" name="submission_uuid" value="<?php echo htmlspecialchars($s['submission_uuid']); ?>">
                                                    <input type="number" step="0.01" name="grade_score" placeholder="Score" max="<?php echo (int)$viewing['max_score']; ?>" class="w-20 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]">
                                                    <input type="text" name="teacher_feedback" placeholder="Feedback" class="flex-1 min-w-[120px] bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-xs text-[var(--text-primary)]">
                                                    <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-[10px] font-bold rounded-lg">Save Grade</button>
                                                </form>
                                            </td>
                                            <?php else: ?>
                                                <td class="p-2 font-mono"><?php echo $s['grade_score'] !== null ? $s['grade_score'] . '/' . (int)$viewing['max_score'] : '—'; ?></td>
                                                <td class="p-2"><?php echo htmlspecialchars($s['teacher_feedback'] ?? ''); ?></td>
                                                <td class="p-2"><span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $s['status']==='Graded' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-blue-500/10 text-blue-400'; ?>"><?php echo htmlspecialchars($s['status']); ?></span></td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>