<?php
/**
 * SECTION: Primary Academic Operations
 * Sessions · Terms · Classes · Arms · Promotion · Graduation · Withdrawal
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Academic Operations' => null]);

if ($active_role !== 'School Admin') {
    echo '<div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-6 text-xs text-rose-400">School Admin access required.</div>';
    return;
}

$sessions       = [];
$terms          = [];
$academicClasses= [];
$academicArms   = [];
$subjects_catalog = [];
try {
    $sessions        = $pdo->prepare("SELECT * FROM academic_sessions WHERE school_uuid=? ORDER BY id DESC"); 
    $sessions->execute([$school_uuid]); 
    $sessions = $sessions->fetchAll();
    
    $terms = $pdo->prepare("SELECT * FROM academic_terms WHERE school_uuid=? ORDER BY id ASC");    
    $terms->execute([$school_uuid]);    
    $terms = $terms->fetchAll();
    
    $academicClasses = $pdo->prepare("SELECT * FROM academic_classes WHERE school_uuid=? ORDER BY id ASC");  
    $academicClasses->execute([$school_uuid]); 
    $academicClasses = $academicClasses->fetchAll();
    
    $academicArms = $pdo->prepare("SELECT * FROM academic_arms WHERE school_uuid=? ORDER BY id ASC");     
    $academicArms->execute([$school_uuid]);    
    $academicArms = $academicArms->fetchAll();
    
    $sbq = $pdo->prepare("SELECT * FROM academic_subjects WHERE school_uuid=? ORDER BY subject_name ASC");
    $sbq->execute([$school_uuid]);
    $subjects_catalog = $sbq->fetchAll();
} catch (Exception $e) {}

$classNames = array_column($academicClasses, 'class_name');
$cur_session = $school_settings['current_session'] ?? '—';
$cur_term    = $school_settings['current_term']    ?? '—';
?>

<div class="space-y-8">
    <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
            <i data-lucide="settings-2" class="w-5 h-5 text-slate-400"></i> Academic Operations
        </h1>
        <p class="text-xs text-[var(--text-secondary)] mt-1">Manage sessions, terms, classes, arms, and student lifecycle actions.</p>
    </div>

    <!-- ── ROW 1: Sessions + Terms ─────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Sessions -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">Academic Sessions</h3>
                <span class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-lg text-[10px] font-bold font-mono">Current: <?php echo htmlspecialchars($cur_session); ?></span>
            </div>
            <form method="POST" class="flex gap-2"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_add_session" value="1">
                <input type="text" name="session_name" required placeholder="e.g. 2026/2027"
                    class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl">Add</button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <?php if (empty($sessions)): ?>
                    <p class="text-[10px] text-[var(--text-secondary)] italic">No sessions yet.</p>
                <?php endif; ?>
                <?php foreach ($sessions as $s): ?>
                <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl flex items-center justify-between text-xs">
                    <span class="font-bold font-mono"><?php echo htmlspecialchars($s['session_name']); ?></span>
                    <div class="flex items-center gap-2">
                        <?php if ($s['is_current']): ?>
                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded text-[10px] font-bold">CURRENT</span>
                        <?php else: ?>
                            <form method="POST" class="inline"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_set_current_session" value="1">
                                <input type="hidden" name="session_name" value="<?php echo htmlspecialchars($s['session_name']); ?>">
                                <button type="submit" class="px-2.5 py-1 bg-[var(--bg-secondary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-white rounded-lg text-[10px] font-bold">Set Current</button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this session?')"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_delete_session" value="1">
                                <input type="hidden" name="session_id" value="<?php echo (int)$s['id']; ?>">
                                <button type="submit" class="text-rose-400 hover:text-rose-300"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Terms -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-[var(--text-primary)]">Terms</h3>
                <span class="px-2 py-1 bg-purple-500/10 text-purple-400 border border-purple-500/20 rounded-lg text-[10px] font-bold">Current: <?php echo htmlspecialchars($cur_term); ?></span>
            </div>
            <form method="POST" class="flex gap-2"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_add_term" value="1">
                <input type="text" name="term_name" required placeholder="e.g. First Term"
                    class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl">Add</button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <?php if (empty($terms)): ?>
                    <p class="text-[10px] text-[var(--text-secondary)] italic">No terms yet.</p>
                <?php endif; ?>
                <?php foreach ($terms as $t): ?>
                <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold"><?php echo htmlspecialchars($t['term_name']); ?></span>
                        <?php if (!empty($t['is_open'])): ?>
                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded text-[10px] font-bold" title="Attendance can be marked while this term is open">OPEN</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded text-[10px] font-bold" title="Attendance cannot be marked while closed">CLOSED</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($t['is_current']): ?>
                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded text-[10px] font-bold">CURRENT</span>
                        <?php else: ?>
                            <form method="POST" class="inline"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_set_current_term" value="1">
                                <input type="hidden" name="term_name" value="<?php echo htmlspecialchars($t['term_name']); ?>">
                                <button type="submit" class="px-2.5 py-1 bg-[var(--bg-secondary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-white rounded-lg text-[10px] font-bold">Set Current</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($t['is_open'])): ?>
                            <form method="POST" class="inline"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_close_term" value="1">
                                <input type="hidden" name="term_id" value="<?php echo (int)$t['id']; ?>">
                                <button type="submit" class="px-2.5 py-1 bg-rose-600/20 border border-rose-500/20 text-rose-400 hover:bg-rose-600/30 rounded-lg text-[10px] font-bold">Close</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="inline"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_open_term" value="1">
                                <input type="hidden" name="term_id" value="<?php echo (int)$t['id']; ?>">
                                <button type="submit" class="px-2.5 py-1 bg-emerald-600/20 border border-emerald-500/20 text-emerald-400 hover:bg-emerald-600/30 rounded-lg text-[10px] font-bold">Open</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this term?')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_delete_term" value="1">
                            <input type="hidden" name="term_id" value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="text-rose-400 hover:text-rose-300"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-[10px] text-[var(--text-secondary)] italic">Attendance can only be marked while the current term is Open. Closing a term (e.g. at end of session) stops further attendance from being recorded against it.</p>
        </div>
    </div>

    <!-- ── ROW 1B: School Calendar (public holidays & school days) ───────── -->
    <?php
    $calendarDays = [];
    try {
        $cd = $pdo->prepare("SELECT * FROM school_calendar_days WHERE school_uuid=? AND calendar_date >= (CURDATE() - INTERVAL 14 DAY) ORDER BY calendar_date ASC");
        $cd->execute([$school_uuid]);
        $calendarDays = $cd->fetchAll();
    } catch (Exception $e) {}
    ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="calendar-days" class="w-4 h-4 text-sky-400"></i> School Calendar</h3>
            <p class="text-[10px] text-[var(--text-secondary)] mt-1">Mark public holidays and non-school days (attendance is skipped automatically), or mark an otherwise-weekend date as a school day (e.g. a Saturday makeup class). Any date with no entry defaults to: weekdays = school day, weekends = not a school day.</p>
        </div>
        <form method="POST" class="flex flex-wrap items-end gap-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_calendar_day" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Date</label>
                <input type="date" name="calendar_date" required class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            </div>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Type</label>
                <select name="calendar_type" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="holiday">Public Holiday</option>
                    <option value="not_school_day">Not a School Day</option>
                    <option value="school_day">School Day (override weekend)</option>
                </select>
            </div>
            <div class="flex-1 min-w-[160px]">
                <label class="block text-[10px] font-bold uppercase mb-1">Title (optional)</label>
                <input type="text" name="calendar_title" placeholder="e.g. Democracy Day" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            </div>
            <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs rounded-xl">Save</button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                    <tr><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2">Title</th><th class="p-2 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php foreach ($calendarDays as $cd): ?>
                    <tr>
                        <td class="p-2 font-mono"><?php echo htmlspecialchars($cd['calendar_date']); ?></td>
                        <td class="p-2">
                            <?php if ($cd['is_public_holiday']): ?><span class="px-2 py-0.5 bg-rose-500/10 text-rose-400 rounded text-[10px] font-bold">Holiday</span>
                            <?php elseif (!$cd['is_school_day']): ?><span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 rounded text-[10px] font-bold">Not School Day</span>
                            <?php else: ?><span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[10px] font-bold">School Day</span><?php endif; ?>
                        </td>
                        <td class="p-2"><?php echo htmlspecialchars($cd['title'] ?? ''); ?></td>
                        <td class="p-2 text-right">
                            <form method="POST" class="inline" onsubmit="return confirm('Remove this calendar entry?')"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_delete_calendar_day" value="1">
                                <input type="hidden" name="calendar_id" value="<?php echo (int)$cd['id']; ?>">
                                <button type="submit" class="text-rose-400 hover:text-rose-300 text-[10px] font-bold">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($calendarDays)): ?>
                    <tr><td colspan="4" class="p-4 text-center text-[var(--text-secondary)] italic">No calendar entries in the last two weeks or upcoming.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── ROW 2: Classes + Arms ────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Classes -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">
                Classes
                <span class="text-[10px] text-[var(--text-secondary)] font-normal ml-2"><?php echo count($academicClasses); ?> defined</span>
            </h3>
            <form method="POST" class="flex gap-2"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_add_class" value="1">
                <input type="text" name="class_name" required placeholder="e.g. JSS1, SSS2, Primary 4"
                    class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs rounded-xl">Add</button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <?php if (empty($academicClasses)): ?>
                    <p class="text-[10px] text-[var(--text-secondary)] italic">No classes yet.</p>
                <?php endif; ?>
                <?php foreach ($academicClasses as $c):
                    try {
                        $sc = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_uuid=? AND class=? AND status='Active'");
                        $sc->execute([$school_uuid, $c['class_name']]);
                        $cnt = (int)$sc->fetchColumn();
                    } catch(Exception $e){ $cnt = 0; }
                ?>
                <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold"><?php echo htmlspecialchars($c['class_name']); ?></span>
                        <span class="text-[10px] text-[var(--text-secondary)] ml-2"><?php echo $cnt; ?> students</span>
                    </div>
                    <form method="POST" class="inline" onsubmit="return confirm('Remove class <?php echo htmlspecialchars($c['class_name']); ?>? Students will not be deleted.')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_delete_class" value="1">
                        <input type="hidden" name="class_id" value="<?php echo (int)$c['id']; ?>">
                        <button type="submit" class="text-rose-400 hover:text-rose-300"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Arms -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">
                Class Arms / Sections
                <span class="text-[10px] text-[var(--text-secondary)] font-normal ml-2"><?php echo count($academicArms); ?> defined</span>
            </h3>
            <p class="text-[10px] text-[var(--text-secondary)]">An arm belongs to one class — e.g. "A" under JSS1 is a different arm from "A" under JSS2.</p>
            <form method="POST" class="flex gap-2"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_add_arm" value="1">
                <select name="class_name" required
                    class="w-28 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-2 py-2 text-xs text-[var(--text-primary)]">
                    <option value="">Class…</option>
                    <?php foreach ($academicClasses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['class_name']); ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="arm_name" required placeholder="e.g. Gold, Alpha, A"
                    class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl">Add</button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                <?php if (empty($academicArms)): ?>
                    <p class="text-[10px] text-[var(--text-secondary)] italic">No arms yet.</p>
                <?php endif; ?>
                <?php foreach ($academicArms as $a): ?>
                <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl flex items-center justify-between text-xs">
                    <span class="font-bold">
                        <?php echo $a['class_name'] ? htmlspecialchars($a['class_name']) . ' ' : '<span class="text-amber-400 italic">Unassigned</span> '; ?>
                        <?php echo htmlspecialchars($a['arm_name']); ?>
                    </span>
                    <form method="POST" class="inline" onsubmit="return confirm('Remove arm <?php echo htmlspecialchars(($a['class_name'] ? $a['class_name'].' ' : '') . $a['arm_name']); ?>?')"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action_delete_arm" value="1">
                        <input type="hidden" name="arm_id" value="<?php echo (int)$a['id']; ?>">
                        <button type="submit" class="text-rose-400 hover:text-rose-300"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── ROW 3: Promotion · Graduation · Withdrawal ───────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Class Promotion -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="trending-up" class="w-4 h-4 text-emerald-400"></i> Class Promotion
            </h3>
            <p class="text-[10px] text-[var(--text-secondary)]">Move all active students from one class to another in bulk.</p>
            <form method="POST" class="space-y-3" onsubmit="return confirm('Promote ALL active students from the selected class? This cannot be undone.')"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_promote_class" value="1">
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">From Class</label>
                    <select name="from_class" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($classNames as $cn): ?>
                            <option value="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">To Class</label>
                    <select name="to_class" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($classNames as $cn): ?>
                            <option value="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" <?php echo empty($classNames)?'disabled':''; ?>
                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-bold text-xs rounded-xl">
                    Execute Promotion
                </button>
            </form>
        </div>

        <!-- Graduation -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="graduation-cap" class="w-4 h-4 text-amber-400"></i> Class Graduation
            </h3>
            <p class="text-[10px] text-[var(--text-secondary)]">Mark all active students in a class as Graduated. Their records remain in the system.</p>
            <form method="POST" class="space-y-3" onsubmit="return confirm('Graduate ALL active students in this class?')"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_graduate_class" value="1">
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Class to Graduate</label>
                    <select name="graduate_class" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($classNames as $cn): ?>
                            <option value="<?php echo htmlspecialchars($cn); ?>"><?php echo htmlspecialchars($cn); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" <?php echo empty($classNames)?'disabled':''; ?>
                    class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 disabled:opacity-40 text-white font-bold text-xs rounded-xl">
                    Graduate Class
                </button>
            </form>
        </div>

        <!-- Individual Withdrawal / Status Change -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="user-x" class="w-4 h-4 text-rose-400"></i> Student Withdrawal
            </h3>
            <p class="text-[10px] text-[var(--text-secondary)]">Withdraw or change the status of a single student by their roll number.</p>
            <form method="POST" class="space-y-3" onsubmit="return confirm('Change this student\'s status?')"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_change_student_status" value="1">
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Roll Number</label>
                    <input type="text" name="roll_number" required placeholder="e.g. RC2025-001"
                        class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">New Status</label>
                    <select name="new_status" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="Withdrawn">Withdrawn</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Active">Re-activate</option>
                        <option value="Graduated">Mark Graduated</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Reason (optional)</label>
                    <input type="text" name="status_reason" placeholder="e.g. Transferred to another school"
                        class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl">
                    Apply Status Change
                </button>
            </form>
        </div>
    </div>

        <!-- ── ROW 4: Teacher Assignments ─────────────────────────────────────── -->
    <?php
    $all_staff = [];
    try {
        $asq = $pdo->prepare("SELECT staff_uuid, name, role FROM staff WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
        $asq->execute([$school_uuid]);
        $all_staff = $asq->fetchAll();
    } catch (Exception $e) {}

    $current_session = $school_settings['current_session'] ?? ($sessions[0]['session_name'] ?? '');
    $current_term    = $school_settings['current_term']    ?? ($terms[0]['term_name'] ?? '');

    $class_teachers = [];
    try {
        $ctq = $pdo->prepare("
            SELECT cta.*, st.name AS staff_name FROM class_teacher_assignments cta
            JOIN staff st ON st.staff_uuid = cta.staff_uuid
            WHERE cta.school_uuid=? ORDER BY cta.session_name DESC, cta.term_name ASC, cta.class_name ASC
        ");
        $ctq->execute([$school_uuid]);
        $class_teachers = $ctq->fetchAll();
    } catch (Exception $e) {}

    $subject_teachers = [];
    try {
        $stq = $pdo->prepare("
            SELECT ssa.*, st.name AS staff_name FROM staff_subject_assignments ssa
            JOIN staff st ON st.staff_uuid = ssa.staff_uuid
            WHERE ssa.school_uuid=? ORDER BY ssa.session_name DESC, ssa.term_name ASC, ssa.class_name ASC, ssa.subject_name ASC
        ");
        $stq->execute([$school_uuid]);
        $subject_teachers = $stq->fetchAll();
    } catch (Exception $e) {}

    // Get arms grouped by class for dynamic dropdowns
    $arms_by_class = [];
    foreach ($academicArms as $arm) {
        $arms_by_class[$arm['class_name']][] = $arm['arm_name'];
    }
    ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-5">
        <div>
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="user-check" class="w-4 h-4 text-violet-400"></i> Teacher Assignments
            </h3>
            <p class="text-[10px] text-[var(--text-secondary)] mt-1">
                Class teachers auto-get write access to Attendance and Report Cards for their class. 
                Subject teachers auto-get write access to Results and the Broadsheet for their subject/class. 
                Both are scoped to the <strong class="text-emerald-400"><?php echo htmlspecialchars($current_session); ?> — <?php echo htmlspecialchars($current_term); ?></strong> session/term.
            </p>
        </div>

        <!-- Class Teacher Assignment Form -->
        <form method="POST" class="grid grid-cols-2 md:grid-cols-6 gap-2 items-end bg-[var(--bg-tertiary)] p-4 rounded-xl border border-[var(--border-color)]">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_assign_class_teacher" value="1">
            <input type="hidden" name="session_name" value="<?php echo htmlspecialchars($current_session); ?>">
            <input type="hidden" name="term_name" value="<?php echo htmlspecialchars($current_term); ?>">
            <div class="col-span-2">
                <label class="block text-[9px] font-bold uppercase mb-1">Staff</label>
                <select name="staff_uuid" required 
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    <?php foreach ($all_staff as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['staff_uuid']); ?>">
                            <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Class</label>
                <select name="class_name" required id="class_teacher_class" 
                    onchange="updateArmDropdown('class_teacher_class', 'class_teacher_arm')"
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    <?php foreach ($academicClasses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['class_name']); ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Arm</label>
                <select name="arm_name" id="class_teacher_arm" 
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Session</label>
                <div class="w-full bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-2 py-1.5 text-xs text-emerald-400 font-mono">
                    <?php echo htmlspecialchars($current_session); ?>
                </div>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Term</label>
                <div class="w-full bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-2 py-1.5 text-xs text-emerald-400 font-mono">
                    <?php echo htmlspecialchars($current_term); ?>
                </div>
            </div>
            <div class="col-span-2 md:col-span-6">
                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-500 text-white text-[10px] font-bold rounded-lg">
                    Assign Class Teacher
                </button>
            </div>
        </form>

        <!-- Class Teachers List -->
        <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                    <tr>
                        <th class="p-2">Staff</th>
                        <th class="p-2">Class</th>
                        <th class="p-2">Arm</th>
                        <th class="p-2">Session/Term</th>
                        <th class="p-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php foreach ($class_teachers as $ct): ?>
                    <tr>
                        <td class="p-2 font-bold"><?php echo htmlspecialchars($ct['staff_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($ct['class_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($ct['arm_name']); ?></td>
                        <td class="p-2 font-mono text-[10px]"><?php echo htmlspecialchars($ct['term_name'].' — '.$ct['session_name']); ?></td>
                        <td class="p-2 text-right">
                            <form method="POST" class="inline" onsubmit="return confirm('Remove this class teacher assignment?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action_remove_class_teacher" value="1">
                                <input type="hidden" name="assignment_id" value="<?php echo (int)$ct['id']; ?>">
                                <button type="submit" class="text-rose-400 hover:text-rose-300 text-[10px] font-bold">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($class_teachers)): ?>
                        <tr>
                            <td colspan="5" class="p-4 text-center text-[var(--text-secondary)] italic">
                                No class teachers assigned yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Subject Teacher Assignment Form -->
        <form method="POST" class="grid grid-cols-2 md:grid-cols-7 gap-2 items-end bg-[var(--bg-tertiary)] p-4 rounded-xl border border-[var(--border-color)]">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_assign_subject_teacher" value="1">
            <input type="hidden" name="session_name" value="<?php echo htmlspecialchars($current_session); ?>">
            <input type="hidden" name="term_name" value="<?php echo htmlspecialchars($current_term); ?>">
            <div class="col-span-2">
                <label class="block text-[9px] font-bold uppercase mb-1">Staff</label>
                <select name="staff_uuid" required 
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    <?php foreach ($all_staff as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['staff_uuid']); ?>">
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Subject</label>
                <select name="subject_name" required 
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    <option value="">— Select —</option>
                    <?php foreach ($subjects_catalog as $sub): ?>
                        <option value="<?php echo htmlspecialchars($sub['subject_name']); ?>">
                            <?php echo htmlspecialchars($sub['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Class</label>
                <select name="class_name" required id="subject_teacher_class" 
                    onchange="updateArmDropdown('subject_teacher_class', 'subject_teacher_arm')"
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                    <?php foreach ($academicClasses as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['class_name']); ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Arm</label>
                <select name="arm_name" id="subject_teacher_arm" 
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                </select>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Periods/Week</label>
                <input type="number" name="periods_per_week" value="5" min="1" max="20" 
                    class="w-full bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Session</label>
                <div class="w-full bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-2 py-1.5 text-xs text-emerald-400 font-mono">
                    <?php echo htmlspecialchars($current_session); ?>
                </div>
            </div>
            <div>
                <label class="block text-[9px] font-bold uppercase mb-1">Term</label>
                <div class="w-full bg-[var(--bg-primary)] border border-emerald-500/30 rounded-lg px-2 py-1.5 text-xs text-emerald-400 font-mono">
                    <?php echo htmlspecialchars($current_term); ?>
                </div>
            </div>
            <div class="col-span-2 md:col-span-7">
                <button type="submit" class="px-4 py-2 bg-violet-600 hover:bg-violet-500 text-white text-[10px] font-bold rounded-lg">
                    Assign Subject Teacher
                </button>
            </div>
        </form>

        <!-- Subject Teachers List -->
        <div class="overflow-x-auto rounded-xl border border-[var(--border-color)]">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                    <tr>
                        <th class="p-2">Staff</th>
                        <th class="p-2">Subject</th>
                        <th class="p-2">Class</th>
                        <th class="p-2">Arm</th>
                        <th class="p-2">Periods/Week</th>
                        <th class="p-2">Session/Term</th>
                        <th class="p-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php foreach ($subject_teachers as $st2): ?>
                    <tr>
                        <td class="p-2 font-bold"><?php echo htmlspecialchars($st2['staff_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($st2['subject_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($st2['class_name']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($st2['arm_name'] ?: 'All arms'); ?></td>
                        <td class="p-2 font-mono text-cyan-400"><?php echo (int)($st2['periods_per_week'] ?? 5); ?></td>
                        <td class="p-2 font-mono text-[10px]"><?php echo htmlspecialchars($st2['term_name'].' — '.$st2['session_name']); ?></td>
                        <td class="p-2 text-right">
                            <form method="POST" class="inline" onsubmit="return confirm('Remove this subject teacher assignment?')">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action_remove_subject_teacher" value="1">
                                <input type="hidden" name="assignment_id" value="<?php echo (int)$st2['id']; ?>">
                                <button type="submit" class="text-rose-400 hover:text-rose-300 text-[10px] font-bold">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($subject_teachers)): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-[var(--text-secondary)] italic">
                                No subject teachers assigned yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <!-- ── ROW 5: Subjects Catalog ────────────────────────────────────────── -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="book-marked" class="w-4 h-4 text-teal-400"></i> Subjects Catalog</h3>
            <p class="text-[10px] text-[var(--text-secondary)] mt-1">The real subject list for this school — used by Results Entry, the Broadsheet, and Subject Teacher assignments above. Without entries here, Results Entry falls back to a generic default list.</p>
        </div>
        <form method="POST" class="flex flex-wrap gap-2 items-end"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_subject" value="1">
            <div><label class="block text-[9px] font-bold uppercase mb-1">Code</label><input type="text" name="subject_code" placeholder="MTH" class="w-24 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[9px] font-bold uppercase mb-1">Subject Name</label><input type="text" name="subject_name" required placeholder="Mathematics" class="w-48 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]"></div>
            <div><label class="block text-[9px] font-bold uppercase mb-1">Department</label><input type="text" name="department_name" placeholder="Sciences" value="General" class="w-40 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]"></div>
            <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-[10px] font-bold rounded-lg">Add Subject</button>
        </form>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($subjects_catalog as $sub): ?>
            <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-full text-[10px] font-bold text-[var(--text-primary)]">
                <?php echo htmlspecialchars($sub['subject_name']); ?> <span class="text-[var(--text-secondary)] font-normal">(<?php echo htmlspecialchars($sub['department_name']); ?>)</span>
                <form method="POST" class="inline" onsubmit="return confirm('Remove this subject?')"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_delete_subject" value="1">
                    <input type="hidden" name="subject_id" value="<?php echo (int)$sub['id']; ?>">
                    <button type="submit" class="text-rose-400 hover:text-rose-300">✕</button>
                </form>
            </span>
            <?php endforeach; ?>
            <?php if (empty($subjects_catalog)): ?><span class="text-[10px] text-[var(--text-secondary)] italic">No subjects added yet — Results Entry is using its generic default list.</span><?php endif; ?>
        </div>
    </div>
</div>

<script>
// Dynamic arm dropdown based on selected class
const armsByClass = <?php echo json_encode($arms_by_class); ?>;

function updateArmDropdown(classSelectId, armSelectId) {
    const classSelect = document.getElementById(classSelectId);
    const armSelect = document.getElementById(armSelectId);
    const selectedClass = classSelect.value;
    const arms = armsByClass[selectedClass] || [];
    
    armSelect.innerHTML = '';
    
    if (arms.length === 0) {
        armSelect.innerHTML = '<option value="">All</option>';
    } else {
        arms.forEach(function(arm) {
            armSelect.innerHTML += '<option value="' + arm + '">' + arm + '</option>';
        });
    }
}

// Initialize arm dropdowns on page load
document.addEventListener('DOMContentLoaded', function() {
    updateArmDropdown('class_teacher_class', 'class_teacher_arm');
    updateArmDropdown('subject_teacher_class', 'subject_teacher_arm');
});
</script>