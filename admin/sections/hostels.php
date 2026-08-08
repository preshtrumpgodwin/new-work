<?php
/**
 * SECTION: hostels — extracted from dashboard.php (Phase 1 refactor)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Hostels' => null]);
?>
<!-- SECTION: HOSTEL -->
                <?php if ($section === 'hostels'): ?>
                    <?php
                    $hostelList = []; $hostelAllocs = []; $hostelError = false;
                    try {
                        $hostelList = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT * FROM hostels WHERE school_uuid = ? ORDER BY name ASC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                        $hostelAllocs = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT * FROM hostel_allocations WHERE school_uuid = ? AND status='Active' ORDER BY id DESC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                    } catch (Exception $e) { $hostelError = true; }
                    $hostelStudents = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid = ? AND status='Active' ORDER BY name ASC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                    ?>
                    <div class="space-y-6">
                        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                            <i data-lucide="home" class="w-6 h-6 text-orange-400"></i>
                            <span>Hostel & Dorms</span>
                        </h1>
                        <?php if ($hostelError): ?>
                            <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-xs text-amber-400">
                                Hostel tables not found. Run <code class="font-mono">migrate_phase7.php</code> once, then reload this page.
                            </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Dormitories (<?php echo count($hostelList); ?>)</h3>
                                <?php if ($active_role === 'School Admin'): ?>
                                <form method="POST" class="grid grid-cols-2 gap-2"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_hostel" value="1">
                                    <input type="text" name="hostel_name" required placeholder="Hostel name" class="col-span-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <select name="hostel_gender" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option>Male</option><option>Female</option><option>Mixed</option>
                                    </select>
                                    <input type="number" name="hostel_capacity" min="0" placeholder="Capacity" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <button type="submit" class="col-span-2 py-2 bg-orange-600 text-white font-bold text-xs rounded-xl">Add Hostel</button>
                                </form>
                                <?php endif; ?>
                                <div class="space-y-2">
                                    <?php foreach ($hostelList as $h): ?>
                                        <?php $occ = 0; foreach ($hostelAllocs as $al) { if ($al['hostel_uuid'] === $h['hostel_uuid']) $occ++; } ?>
                                        <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs flex justify-between">
                                            <span class="font-bold"><?php echo htmlspecialchars($h['name']); ?> <span class="text-[var(--text-secondary)] font-normal">(<?php echo htmlspecialchars($h['gender']); ?>)</span></span>
                                            <span class="text-[var(--text-secondary)]"><?php echo $occ; ?>/<?php echo (int)$h['capacity']; ?> occupied</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Allocate Student</h3>
                                <form method="POST" class="space-y-2"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_allocate_hostel" value="1">
                                    <select name="hostel_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="">-- Select hostel --</option>
                                        <?php foreach ($hostelList as $h): ?><option value="<?php echo htmlspecialchars($h['hostel_uuid']); ?>"><?php echo htmlspecialchars($h['name']); ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="text" id="hostelStudentFilter" placeholder="Type to filter students..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]" onkeyup="filterDropdown('hostelStudentFilter','hostelStudentSelect')">
                                    <select name="student_uuid" id="hostelStudentSelect" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="">-- Select student --</option>
                                        <?php foreach ($hostelStudents as $s): ?><option value="<?php echo htmlspecialchars($s['student_uuid']); ?>"><?php echo htmlspecialchars($s['name'] . ' — ' . $s['class']); ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" name="room_number" placeholder="Room #" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <input type="text" name="bed_number" placeholder="Bed #" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <button type="submit" class="w-full py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl">Allocate</button>
                                </form>
                                <h3 class="text-sm font-bold text-[var(--text-primary)] pt-2 border-t border-[var(--border-color)]">Current Allocations (<?php echo count($hostelAllocs); ?>)</h3>
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                    <?php foreach ($hostelAllocs as $al): ?>
                                        <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs flex items-center justify-between">
                                            <div><span class="font-bold"><?php echo htmlspecialchars($al['student_name']); ?></span><br><span class="text-[var(--text-secondary)]">Room <?php echo htmlspecialchars($al['room_number'] ?: '—'); ?><?php echo $al['bed_number'] ? ' / Bed ' . htmlspecialchars($al['bed_number']) : ''; ?></span></div>
                                            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_vacate_hostel" value="1"><input type="hidden" name="allocation_uuid" value="<?php echo htmlspecialchars($al['allocation_uuid']); ?>"><button type="submit" class="px-2.5 py-1 bg-rose-600/20 text-rose-400 rounded-lg text-[10px] font-bold">Vacate</button></form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
