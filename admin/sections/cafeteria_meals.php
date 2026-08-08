<?php
/**
 * SECTION: cafeteria_meals — menu, meal plans, billing (Phase 9)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Cafeteria Meals' => null]);
?>
<!-- SECTION: CAFETERIA & MEALS -->
                <?php if ($section === 'cafeteria_meals'):
                    $can_manage_cafeteria = in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, $current_access ?? 'hide');
                    $menuItems = []; $mealPlans = []; $billing = []; $cafStudents = []; $cafError = false;
                    try {
                        $mi = $pdo->prepare("SELECT * FROM cafeteria_menu_items WHERE school_uuid=? ORDER BY meal_type, item_name");
                        $mi->execute([$school_uuid]); $menuItems = $mi->fetchAll();

                        $mp = $pdo->prepare("SELECT * FROM cafeteria_meal_plans WHERE school_uuid=? ORDER BY start_date DESC");
                        $mp->execute([$school_uuid]); $mealPlans = $mp->fetchAll();

                        $cb = $pdo->prepare("SELECT * FROM cafeteria_billing WHERE school_uuid=? ORDER BY billing_date DESC LIMIT 100");
                        $cb->execute([$school_uuid]); $billing = $cb->fetchAll();

                        $cs = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
                        $cs->execute([$school_uuid]); $cafStudents = $cs->fetchAll();
                    } catch (Exception $e) { $cafError = true; }
                    ?>
                    <div class="space-y-6">
                        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                            <i data-lucide="utensils" class="w-6 h-6 text-orange-400"></i>
                            <span>Cafeteria & Meals</span>
                        </h1>

                        <?php if ($cafError): ?>
                        <div class="bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-2xl p-4 text-xs">
                            Cafeteria tables not found. Run <code class="font-mono">SQL/phase9_schema_migration.sql</code> against your database, then reload this page.
                        </div>
                        <?php else: ?>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Menu -->
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Menu Items</h3>
                                <?php if ($can_manage_cafeteria): ?>
                                <form method="POST" class="space-y-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_menu_item" value="1">
                                    <input type="text" name="item_name" required placeholder="Item name" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="meal_type" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                            <option>Breakfast</option><option selected>Lunch</option><option>Dinner</option><option>Snack</option>
                                        </select>
                                        <input type="number" step="0.01" name="price" placeholder="Price (₦)" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <button type="submit" class="w-full py-1.5 bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs rounded-lg">Add item</button>
                                </form>
                                <?php endif; ?>
                                <div class="divide-y divide-[var(--border-color)] max-h-72 overflow-y-auto">
                                    <?php if (empty($menuItems)): ?>
                                        <p class="text-[10px] text-[var(--text-secondary)] italic py-2">No menu items yet.</p>
                                    <?php else: foreach ($menuItems as $mi): ?>
                                        <div class="py-2 flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-bold text-[var(--text-primary)] <?php echo $mi['is_active'] ? '' : 'opacity-40 line-through'; ?>"><?php echo htmlspecialchars($mi['item_name']); ?></span>
                                                <span class="block text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($mi['meal_type']); ?> — ₦<?php echo number_format($mi['price'],2); ?></span>
                                            </div>
                                            <?php if ($can_manage_cafeteria): ?>
                                            <div class="flex gap-2">
                                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_toggle_menu_item" value="1"><input type="hidden" name="item_uuid" value="<?php echo htmlspecialchars($mi['item_uuid']); ?>"><button class="text-[10px] text-[var(--text-secondary)] hover:text-white"><?php echo $mi['is_active'] ? 'Hide' : 'Show'; ?></button></form>
                                                <form method="POST" onsubmit="return confirm('Delete this item?')"><?php echo csrf_field(); ?><input type="hidden" name="action_delete_menu_item" value="1"><input type="hidden" name="item_uuid" value="<?php echo htmlspecialchars($mi['item_uuid']); ?>"><button class="text-[10px] text-rose-400 hover:text-rose-300">✕</button></form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>

                            <!-- Meal Plans -->
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Meal Plans</h3>
                                <?php if ($can_manage_cafeteria): ?>
                                <form method="POST" class="space-y-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_meal_plan" value="1">
                                    <select name="student_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                        <option value="">Select student…</option>
                                        <?php foreach ($cafStudents as $s): ?><option value="<?php echo htmlspecialchars($s['student_uuid']); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="plan_type" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                            <option>Daily</option><option>Weekly</option><option>Termly</option>
                                        </select>
                                        <input type="number" step="0.01" name="amount" required placeholder="Amount (₦)" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                        <input type="date" name="end_date" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <button type="submit" class="w-full py-1.5 bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs rounded-lg">Create plan</button>
                                </form>
                                <?php endif; ?>
                                <div class="divide-y divide-[var(--border-color)] max-h-72 overflow-y-auto">
                                    <?php if (empty($mealPlans)): ?>
                                        <p class="text-[10px] text-[var(--text-secondary)] italic py-2">No meal plans yet.</p>
                                    <?php else: foreach ($mealPlans as $mp): ?>
                                        <div class="py-2 flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($mp['student_name']); ?></span>
                                                <span class="block text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($mp['plan_type']); ?> — ₦<?php echo number_format($mp['amount'],2); ?> — <span class="<?php echo $mp['status']==='Active'?'text-emerald-400':'text-rose-400'; ?>"><?php echo htmlspecialchars($mp['status']); ?></span></span>
                                            </div>
                                            <?php if ($can_manage_cafeteria && $mp['status']==='Active'): ?>
                                            <form method="POST" onsubmit="return confirm('Cancel this plan?')"><?php echo csrf_field(); ?><input type="hidden" name="action_cancel_meal_plan" value="1"><input type="hidden" name="plan_uuid" value="<?php echo htmlspecialchars($mp['plan_uuid']); ?>"><button class="text-[10px] text-rose-400 hover:text-rose-300">Cancel</button></form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>

                            <!-- Billing -->
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-5 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Billing</h3>
                                <?php if ($can_manage_cafeteria): ?>
                                <form method="POST" class="space-y-2">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_cafeteria_bill" value="1">
                                    <select name="student_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                        <option value="">Select student…</option>
                                        <?php foreach ($cafStudents as $s): ?><option value="<?php echo htmlspecialchars($s['student_uuid']); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="number" step="0.01" name="amount" required placeholder="Amount (₦)" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                        <input type="date" name="billing_date" value="<?php echo date('Y-m-d'); ?>" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                    </div>
                                    <input type="text" name="notes" placeholder="Notes (optional)" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs text-[var(--text-primary)]">
                                    <button type="submit" class="w-full py-1.5 bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs rounded-lg">Add bill</button>
                                </form>
                                <?php endif; ?>
                                <div class="divide-y divide-[var(--border-color)] max-h-72 overflow-y-auto">
                                    <?php if (empty($billing)): ?>
                                        <p class="text-[10px] text-[var(--text-secondary)] italic py-2">No billing records yet.</p>
                                    <?php else: foreach ($billing as $b): ?>
                                        <div class="py-2 flex items-center justify-between text-xs">
                                            <div>
                                                <span class="font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($b['student_name']); ?></span>
                                                <span class="block text-[10px] text-[var(--text-secondary)]">₦<?php echo number_format($b['amount'],2); ?> — <?php echo date('M d', strtotime($b['billing_date'])); ?> — <span class="<?php echo $b['status']==='Paid'?'text-emerald-400':'text-amber-400'; ?>"><?php echo htmlspecialchars($b['status']); ?></span></span>
                                            </div>
                                            <?php if ($can_manage_cafeteria && $b['status']==='Unpaid'): ?>
                                            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_mark_cafeteria_paid" value="1"><input type="hidden" name="bill_uuid" value="<?php echo htmlspecialchars($b['bill_uuid']); ?>"><button class="text-[10px] text-emerald-400 hover:text-emerald-300">Mark paid</button></form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
