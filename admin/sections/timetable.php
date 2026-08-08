<?php
/**
 * SECTION: timetable — whole-school grid (Phase 9 rebuild)
 * All classes/arms shown together, one table per day, with a shared set of
 * period (time-slot) columns that admins define themselves. Replaces the
 * old one-class-at-a-time template/generator flow.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Timetable' => null]);
?>
<!-- SECTION: TIMETABLE GRID -->
<!-- ═══════════════════════════════════════════════════════ -->
<?php if ($section === 'timetable'):
    $tt_can_edit = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('timetable'));
    $tt_session = $school_settings['current_session'] ?? '';
    $tt_term    = $school_settings['current_term'] ?? '';

    $ALL_DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    // Active days — seed defaults (Mon–Fri on, Sat/Sun off) on first visit.
    $dq = $pdo->prepare("SELECT day_name, is_active FROM timetable_days WHERE school_uuid=?");
    $dq->execute([$school_uuid]);
    $day_state = [];
    foreach ($dq->fetchAll() as $r) { $day_state[$r['day_name']] = (int)$r['is_active']; }
    if (empty($day_state)) {
        foreach ($ALL_DAYS as $i => $d) {
            $day_state[$d] = in_array($d, ['Saturday','Sunday']) ? 0 : 1;
        }
    } else {
        foreach ($ALL_DAYS as $d) { if (!isset($day_state[$d])) $day_state[$d] = in_array($d, ['Saturday','Sunday']) ? 0 : 1; }
    }
    $active_days = array_values(array_filter($ALL_DAYS, fn($d) => !empty($day_state[$d])));

    // Period (time-slot) columns — school-wide.
    $pq = $pdo->prepare("SELECT period_uuid, label FROM timetable_periods WHERE school_uuid=? ORDER BY sort_order ASC, id ASC");
    $pq->execute([$school_uuid]);
    $periods = $pq->fetchAll();

    // Class/arm rows — every arm on record, plus any class with no arms as its own row.
    $aq = $pdo->prepare("SELECT class_name, arm_name FROM academic_arms WHERE school_uuid=? ORDER BY class_name, arm_name");
    $aq->execute([$school_uuid]);
    $arm_rows = $aq->fetchAll();
    $classes_with_arms = [];
    foreach ($arm_rows as $ar) { $classes_with_arms[$ar['class_name']] = true; }
    $class_arm_pairs = [];
    foreach ($arm_rows as $ar) { $class_arm_pairs[] = [$ar['class_name'], $ar['arm_name']]; }
    foreach ($roster_classes as $cn) {
        if (empty($classes_with_arms[$cn])) { $class_arm_pairs[] = [$cn, '']; }
    }

    // All existing slots, keyed by class|arm|day|period for O(1) lookup.
    $slots_by_key = [];
    if (!empty($periods)) {
        $sq = $pdo->prepare("SELECT * FROM timetables WHERE school_uuid=?");
        $sq->execute([$school_uuid]);
        foreach ($sq->fetchAll() as $row) {
            $slots_by_key[$row['class_name'] . '|' . $row['arm_name'] . '|' . $row['day_of_week'] . '|' . $row['period_time']] = $row;
        }
    }

    // Subject-teacher assignment options per class, same fetch+fallback
    // logic used by auto-fill: exact arm match, else arm-less, else all.
    $assignments_by_class_arm = [];
    if (!empty($periods)) {
        $saq2 = $pdo->prepare("
            SELECT ssa.class_name, ssa.subject_name AS subject, s.name AS teacher_name, ssa.arm_name
            FROM staff_subject_assignments ssa
            JOIN staff s ON s.staff_uuid = ssa.staff_uuid
            WHERE ssa.school_uuid=? AND ssa.session_name=? AND ssa.term_name=?
            ORDER BY ssa.subject_name ASC
        ");
        $saq2->execute([$school_uuid, $tt_session, $tt_term]);
        $by_class = [];
        foreach ($saq2->fetchAll() as $r) { $by_class[$r['class_name']][] = $r; }

        foreach ($class_arm_pairs as [$cn, $an]) {
            $all_for_class = $by_class[$cn] ?? [];
            $matched = array_values(array_filter($all_for_class, fn($a) => $a['arm_name'] === $an || empty($a['arm_name'])));
            // Only fall back to "every assignment on record for the class"
            // when NONE of them specify an arm at all (the school isn't
            // tracking this class per-arm). If other arms of this class DO
            // have their own arm-specific assignments, an arm with no match
            // of its own must NOT inherit them — otherwise a teacher
            // assigned to "SS3 Science" ends up offered for "SS3 Arts" too.
            $any_arm_specified = false;
            foreach ($all_for_class as $a) { if (!empty($a['arm_name'])) { $any_arm_specified = true; break; } }
            $assignments_by_class_arm[$cn . '|' . $an] = (!empty($matched) || $any_arm_specified) ? $matched : $all_for_class;
        }
    }

    // Last publish record for the current session/term.
    $lp = $pdo->prepare("SELECT * FROM timetable_publications WHERE school_uuid=? AND session_name=? AND term_name=? ORDER BY published_at DESC LIMIT 1");
    $lp->execute([$school_uuid, $tt_session, $tt_term]);
    $last_publish = $lp->fetch();

    $ucq = $pdo->prepare("SELECT COUNT(*) FROM timetables WHERE school_uuid=? AND has_clash=1 AND clash_overridden=0");
    $ucq->execute([$school_uuid]);
    $unresolved_clashes = (int)$ucq->fetchColumn();
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[var(--text-primary)]">School Timetable</h2>
            <p class="text-sm text-[var(--text-secondary)] mt-1">
                Every class and arm, all in one place<?php echo ($tt_session || $tt_term) ? " — $tt_session, $tt_term" : ''; ?>.
                <?php if ($last_publish): ?>
                    Last published <?php echo htmlspecialchars(date('M j, Y g:ia', strtotime($last_publish['published_at']))); ?> by <?php echo htmlspecialchars($last_publish['published_by']); ?>.
                <?php else: ?>
                    Not yet published.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($tt_can_edit): ?>
        <div class="flex flex-wrap gap-2">
            <form method="POST"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_auto_fill_timetable" value="1">
                <button type="submit" class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold"
                    onclick="return confirm('This rebuilds every class/arm\'s timetable from current subject-teacher assignments and overwrites existing slots. Continue?');">
                    ⚡ Auto-Fill All
                </button>
            </form>
            <form method="POST"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_check_timetable_clashes" value="1">
                <button type="submit" class="px-3 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-sm font-semibold">
                    🔍 Check Clashes
                </button>
            </form>
            <form method="POST"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_publish_timetable" value="1">
                <?php if ($unresolved_clashes > 0): ?>
                    <input type="hidden" name="confirm_publish_with_clashes" value="1">
                <?php endif; ?>
                <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold"
                    <?php if ($unresolved_clashes > 0): ?>
                    onclick="return confirm('<?php echo $unresolved_clashes; ?> slot(s) still have unresolved clashes. Publishing will override and accept all of them. Continue?');"
                    <?php endif; ?>>
                    📢 Publish
                </button>
            </form>
            <form method="POST" onsubmit="return confirm('This deletes every timetable slot for the whole school. This cannot be undone. Continue?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_clear_timetable_all" value="1">
                <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600/80 hover:bg-rose-500 text-white text-sm font-semibold">
                    🗑 Clear All
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($unresolved_clashes > 0): ?>
    <div class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
        ⚠️ <?php echo $unresolved_clashes; ?> slot(s) have a teacher double-booked at the same day/period. They're flagged red below — reassign one of them, or click "Override" on the cell to accept it anyway.
    </div>
    <?php endif; ?>

    <?php if ($tt_can_edit): ?>
    <div class="bg-[var(--bg-secondary)] rounded-xl border border-[var(--border-color)] p-4 space-y-4">
        <div>
            <div class="text-xs font-semibold text-[var(--text-secondary)] uppercase tracking-wide mb-2">Days on the timetable</div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($ALL_DAYS as $d): $on = !empty($day_state[$d]); ?>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action_toggle_timetable_day" value="1">
                    <input type="hidden" name="day_name" value="<?php echo $d; ?>">
                    <button type="submit" class="px-3 py-1.5 rounded-full text-xs font-semibold border transition
                        <?php echo $on ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-secondary)]'; ?>">
                        <?php echo $on ? '✓ ' : ''; ?><?php echo $d; ?>
                    </button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <div class="text-xs font-semibold text-[var(--text-secondary)] uppercase tracking-wide mb-2">Time-slot columns</div>
            <div class="flex flex-wrap items-center gap-2">
                <?php foreach ($periods as $p): ?>
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)]">
                    <?php echo htmlspecialchars($p['label']); ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Remove column \'<?php echo htmlspecialchars(addslashes($p['label'])); ?>\'? This also deletes every slot in that column across all classes.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action_delete_timetable_period" value="1">
                        <input type="hidden" name="period_uuid" value="<?php echo $p['period_uuid']; ?>">
                        <button type="submit" class="text-rose-400 hover:text-rose-300 ml-1">✕</button>
                    </form>
                </span>
                <?php endforeach; ?>
                <form method="POST" class="flex items-center gap-1">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action_add_timetable_period" value="1">
                    <input type="text" name="period_label" placeholder="e.g. 8:00 - 8:40" required
                        class="px-2 py-1.5 rounded-lg bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-primary)] text-xs w-40">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-semibold">+ Add Column</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($periods)): ?>
        <div class="bg-[var(--bg-secondary)] rounded-xl border border-dashed border-[var(--border-color)] p-8 text-center text-[var(--text-secondary)]">
            No time-slot columns yet. Add your first one above (e.g. "8:00 - 8:40") to start building the grid.
        </div>
    <?php elseif (empty($active_days)): ?>
        <div class="bg-[var(--bg-secondary)] rounded-xl border border-dashed border-[var(--border-color)] p-8 text-center text-[var(--text-secondary)]">
            No days are active. Turn at least one day back on above.
        </div>
    <?php else: ?>
        <?php foreach ($active_days as $day): ?>
        <div class="bg-[var(--bg-secondary)] rounded-xl border border-[var(--border-color)] overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--border-color)] bg-[var(--bg-tertiary)]">
                <h3 class="font-bold text-[var(--text-primary)]"><?php echo $day; ?></h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[var(--border-color)]">
                            <th class="p-2 text-left text-xs font-semibold text-[var(--text-secondary)] uppercase sticky left-0 bg-[var(--bg-secondary)] whitespace-nowrap">Class / Arm</th>
                            <?php foreach ($periods as $p): ?>
                            <th class="p-2 text-center text-xs font-semibold text-[var(--text-secondary)] uppercase whitespace-nowrap min-w-[140px]"><?php echo htmlspecialchars($p['label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($class_arm_pairs as [$cn, $an]): ?>
                        <tr class="border-b border-[var(--border-color)]">
                            <td class="p-2 font-semibold text-[var(--text-primary)] sticky left-0 bg-[var(--bg-secondary)] whitespace-nowrap">
                                <?php echo htmlspecialchars($cn . ($an !== '' ? " $an" : '')); ?>
                            </td>
                            <?php
                            $opts = $assignments_by_class_arm[$cn . '|' . $an] ?? [];
                            foreach ($periods as $p):
                                $key = $cn . '|' . $an . '|' . $day . '|' . $p['label'];
                                $slot = $slots_by_key[$key] ?? null;
                                $subject = $slot['subject'] ?? '';
                                $teacher = $slot['teacher_name'] ?? '';
                                $isFree = ($subject === '' || $subject === '— Free Period —');
                                $clashed = !empty($slot['has_clash']);
                                $overridden = !empty($slot['clash_overridden']);
                            ?>
                            <td class="p-1.5 text-center <?php echo $clashed && !$overridden ? 'bg-rose-500/10' : ''; ?>">
                                <?php if ($tt_can_edit): ?>
                                <div class="tt-cell rounded-lg p-1.5 <?php echo $clashed && !$overridden ? 'border border-rose-500/60' : 'border border-[var(--border-color)]'; ?>">
                                    <select class="tt-slot-select w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-md px-1 py-1 text-[10px] <?php echo $isFree ? 'text-[var(--text-secondary)] italic' : 'text-[var(--text-primary)] font-semibold'; ?>"
                                        data-class="<?php echo htmlspecialchars($cn); ?>" data-arm="<?php echo htmlspecialchars($an); ?>"
                                        data-day="<?php echo htmlspecialchars($day); ?>" data-period="<?php echo htmlspecialchars($p['label']); ?>">
                                        <option value="" <?php echo $isFree ? 'selected' : ''; ?>>— Free Period —</option>
                                        <?php foreach ($opts as $sr): ?>
                                        <option value="<?php echo htmlspecialchars($sr['subject']); ?>" data-teacher="<?php echo htmlspecialchars($sr['teacher_name']); ?>"
                                            <?php echo (!$isFree && $subject === $sr['subject']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sr['subject']); ?> (<?php echo htmlspecialchars($sr['teacher_name']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                        <?php if (!$isFree && !in_array($subject, array_column($opts, 'subject'))): ?>
                                        <option value="<?php echo htmlspecialchars($subject); ?>" selected data-teacher="<?php echo htmlspecialchars($teacher); ?>">
                                            <?php echo htmlspecialchars($subject); ?> (<?php echo htmlspecialchars($teacher); ?>)
                                        </option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="tt-teacher text-[9px] text-[var(--text-secondary)] mt-0.5 <?php echo $isFree ? 'hidden' : ''; ?>"><?php echo htmlspecialchars($teacher); ?></div>
                                    <?php if ($clashed): ?>
                                    <div class="tt-clash-row mt-0.5 <?php echo $overridden ? 'hidden' : ''; ?>">
                                        <span class="text-[9px] text-rose-400">⚠ clash</span>
                                        <form method="POST" class="inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action_override_timetable_clash" value="1">
                                            <input type="hidden" name="timetable_uuid" value="<?php echo htmlspecialchars($slot['timetable_uuid']); ?>">
                                            <button type="submit" class="text-[9px] text-cyan-400 hover:text-cyan-300 underline ml-1">override</button>
                                        </form>
                                    </div>
                                    <?php elseif ($overridden): ?>
                                    <div class="text-[9px] text-emerald-400 mt-0.5">✓ accepted</div>
                                    <?php endif; ?>
                                    <div class="tt-save-status text-[9px] mt-0.5 h-3"></div>
                                </div>
                                <?php else: ?>
                                    <div class="text-[10px] <?php echo $isFree ? 'text-[var(--text-secondary)] italic' : 'text-[var(--text-primary)] font-semibold'; ?>">
                                        <?php echo $isFree ? '— Free —' : htmlspecialchars($subject); ?>
                                    </div>
                                    <?php if (!$isFree): ?><div class="text-[9px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($teacher); ?></div><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($tt_can_edit): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = <?php echo json_encode(csrf_token()); ?>;
    document.querySelectorAll('.tt-slot-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            const cell = sel.closest('.tt-cell');
            const teacherDiv = cell.querySelector('.tt-teacher');
            const statusDiv = cell.querySelector('.tt-save-status');
            const opt = sel.options[sel.selectedIndex];
            const teacher = opt.getAttribute('data-teacher') || '';
            const subject = sel.value;

            statusDiv.textContent = 'Saving…';
            statusDiv.className = 'tt-save-status text-[9px] mt-0.5 h-3 text-cyan-400';
            sel.disabled = true;

            fetch('api/update-timetable-slot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    class_name: sel.getAttribute('data-class'),
                    arm_name: sel.getAttribute('data-arm'),
                    day_of_week: sel.getAttribute('data-day'),
                    period_time: sel.getAttribute('data-period'),
                    subject: subject,
                    teacher_name: teacher,
                    csrf_token: csrfToken
                })
            })
            .then(r => r.json())
            .then(data => {
                sel.disabled = false;
                if (data.success) {
                    if (data.subject === '— Free Period —') {
                        teacherDiv.classList.add('hidden');
                        sel.classList.add('text-[var(--text-secondary)]', 'italic');
                        sel.classList.remove('text-[var(--text-primary)]', 'font-semibold');
                    } else {
                        teacherDiv.textContent = data.teacher_name;
                        teacherDiv.classList.remove('hidden');
                        sel.classList.remove('text-[var(--text-secondary)]', 'italic');
                        sel.classList.add('text-[var(--text-primary)]', 'font-semibold');
                    }
                    statusDiv.textContent = data.has_clash ? 'Saved — but clashes! Reload to see.' : 'Saved ✓';
                    statusDiv.className = 'tt-save-status text-[9px] mt-0.5 h-3 ' + (data.has_clash ? 'text-rose-400' : 'text-emerald-400');
                    if (!data.has_clash) setTimeout(() => { statusDiv.textContent = ''; }, 1500);
                } else {
                    statusDiv.textContent = data.error || 'Save failed';
                    statusDiv.className = 'tt-save-status text-[9px] mt-0.5 h-3 text-rose-400';
                }
            })
            .catch(() => {
                sel.disabled = false;
                statusDiv.textContent = 'Network error';
                statusDiv.className = 'tt-save-status text-[9px] mt-0.5 h-3 text-rose-400';
            });
        });
    });
});
</script>
<?php endif; ?>

<?php endif; ?>
