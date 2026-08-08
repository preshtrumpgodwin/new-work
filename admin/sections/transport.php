<?php
/**
 * SECTION: transport — extracted from dashboard.php (Phase 1 refactor)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Transport' => null]);
?>
<!-- SECTION: TRANSPORT -->
                <?php if ($section === 'transport'): ?>
                    <?php
                    $routeList = []; $routeAllocs = []; $routeError = false;
                    try {
                        $routeList = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT * FROM transport_routes WHERE school_uuid = ? ORDER BY route_name ASC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                        $routeAllocs = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT * FROM transport_allocations WHERE school_uuid = ? AND status='Active' ORDER BY id DESC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                    } catch (Exception $e) { $routeError = true; }
                    $transportStudents = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid = ? AND status='Active' ORDER BY name ASC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                    ?>
                    <div class="space-y-6">
                        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                            <i data-lucide="bus" class="w-6 h-6 text-yellow-400"></i>
                            <span>Transport & Logistics</span>
                        </h1>
                        <?php if ($routeError): ?>
                            <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-xs text-amber-400">
                                Transport tables not found. Run <code class="font-mono">migrate_phase7.php</code> once, then reload this page.
                            </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Bus Routes (<?php echo count($routeList); ?>)</h3>
                                <?php if ($active_role === 'School Admin'): ?>
                                <form method="POST" class="grid grid-cols-2 gap-2"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_route" value="1">
                                    <input type="text" name="route_name" required placeholder="Route name" class="col-span-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="driver_name" placeholder="Driver name" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="vehicle_number" placeholder="Vehicle #" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="number" name="route_capacity" min="0" placeholder="Capacity" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="number" name="fee_amount" min="0" step="0.01" placeholder="Fee amount" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <button type="submit" class="col-span-2 py-2 bg-yellow-600 text-white font-bold text-xs rounded-xl">Add Route</button>
                                </form>
                                <?php endif; ?>
                                <div class="space-y-2">
                                    <?php foreach ($routeList as $r): ?>
                                        <?php $occ = 0; foreach ($routeAllocs as $al) { if ($al['route_uuid'] === $r['route_uuid']) $occ++; } ?>
                                        <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                                            <div class="flex justify-between"><span class="font-bold"><?php echo htmlspecialchars($r['route_name']); ?></span><span class="text-[var(--text-secondary)]"><?php echo $occ; ?>/<?php echo (int)$r['capacity']; ?></span></div>
                                            <span class="text-[var(--text-secondary)]"><?php echo htmlspecialchars($r['driver_name'] ?: '—'); ?> · <?php echo htmlspecialchars($r['vehicle_number'] ?: '—'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Assign Student to Route</h3>
                                <form method="POST" class="space-y-2"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_assign_transport" value="1">
                                    <select name="route_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="">-- Select route --</option>
                                        <?php foreach ($routeList as $r): ?><option value="<?php echo htmlspecialchars($r['route_uuid']); ?>"><?php echo htmlspecialchars($r['route_name']); ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="text" id="transportStudentFilter" placeholder="Type to filter students..." class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]" onkeyup="filterDropdown('transportStudentFilter','transportStudentSelect')">
                                    <select name="student_uuid" id="transportStudentSelect" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="">-- Select student --</option>
                                        <?php foreach ($transportStudents as $s): ?><option value="<?php echo htmlspecialchars($s['student_uuid']); ?>"><?php echo htmlspecialchars($s['name'] . ' — ' . $s['class']); ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="text" name="pickup_point" placeholder="Pickup point" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <button type="submit" class="w-full py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl">Assign</button>
                                </form>
                                <h3 class="text-sm font-bold text-[var(--text-primary)] pt-2 border-t border-[var(--border-color)]">Current Assignments (<?php echo count($routeAllocs); ?>)</h3>
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                    <?php foreach ($routeAllocs as $al): ?>
                                        <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs flex items-center justify-between">
                                            <div><span class="font-bold"><?php echo htmlspecialchars($al['student_name']); ?></span><br><span class="text-[var(--text-secondary)]"><?php echo htmlspecialchars($al['pickup_point'] ?: '—'); ?></span></div>
                                            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_remove_transport" value="1"><input type="hidden" name="allocation_uuid" value="<?php echo htmlspecialchars($al['allocation_uuid']); ?>"><button type="submit" class="px-2.5 py-1 bg-rose-600/20 text-rose-400 rounded-lg text-[10px] font-bold">Remove</button></form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
