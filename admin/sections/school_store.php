<?php
/**
 * SECTION: School Store (Inventory + POS)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'School Store' => null]);
$can_write = can_manage($active_role, $current_access);

$items = [];
try {
    $st = $pdo->prepare("SELECT * FROM store_inventory WHERE school_uuid=? ORDER BY category, item_name");
    $st->execute([$school_uuid]);
    $items = $st->fetchAll();
} catch (Exception $e) {}

$sales = [];
try {
    $sl = $pdo->prepare("SELECT * FROM store_pos_sales WHERE school_uuid=? ORDER BY created_at DESC LIMIT 20");
    $sl->execute([$school_uuid]);
    $sales = $sl->fetchAll();
} catch (Exception $e) {}

$students = [];
try {
    $ss = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $ss->execute([$school_uuid]);
    $students = $ss->fetchAll();
} catch (Exception $e) {}

$low_stock = array_filter($items, fn($i) => $i['stock_quantity'] <= $i['reorder_level']);
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)] flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="package" class="w-5 h-5 text-yellow-400"></i> School Store
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1"><?php echo count($items); ?> item(s) in inventory<?php echo count($low_stock) ? ' · ' . count($low_stock) . ' low on stock' : ''; ?></p>
        </div>
        <div class="flex gap-2">
            <button onclick="document.getElementById('posModal').classList.remove('hidden')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i> New Sale
            </button>
            <?php if ($can_write): ?>
            <button onclick="document.getElementById('addItemModal').classList.remove('hidden')" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Item
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($low_stock)): ?>
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 text-xs text-amber-400 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span>Low stock: <?php echo implode(', ', array_map(fn($i)=>htmlspecialchars($i['item_name']).' ('.$i['stock_quantity'].' left)', $low_stock)); ?></span>
    </div>
    <?php endif; ?>

    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]">
                    <tr><th class="p-3">Item</th><th class="p-3">Category</th><th class="p-3">Price</th><th class="p-3">Stock</th><th class="p-3">Reorder Level</th><?php if ($can_write): ?><th class="p-3">Actions</th><?php endif; ?></tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php foreach ($items as $it): $low = $it['stock_quantity'] <= $it['reorder_level']; ?>
                    <tr>
                        <td class="p-3 font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($it['item_name']); ?></td>
                        <td class="p-3 text-[var(--text-secondary)]"><?php echo htmlspecialchars($it['category']); ?></td>
                        <td class="p-3 font-mono text-emerald-400">₦<?php echo number_format($it['unit_price'],2); ?></td>
                        <td class="p-3 font-mono <?php echo $low ? 'text-rose-400 font-bold' : 'text-[var(--text-primary)]'; ?>"><?php echo (int)$it['stock_quantity']; ?></td>
                        <td class="p-3 font-mono text-[var(--text-secondary)]"><?php echo (int)$it['reorder_level']; ?></td>
                        <?php if ($can_write): ?>
                        <td class="p-3">
                            <div class="flex gap-2">
                                <button onclick="document.getElementById('editItem-<?php echo $it['item_uuid']; ?>').classList.remove('hidden')" class="text-indigo-400"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
                                <form method="POST" onsubmit="return confirm('Remove this item?')"><?php echo csrf_field(); ?><input type="hidden" name="action_delete_store_item" value="1"><input type="hidden" name="item_uuid" value="<?php echo htmlspecialchars($it['item_uuid']); ?>"><button type="submit" class="text-rose-400"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button></form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <!-- Edit modal -->
                    <?php if ($can_write): ?>
                    <tr id="editItem-<?php echo $it['item_uuid']; ?>" class="hidden">
                        <td colspan="6" class="p-4 bg-[var(--bg-tertiary)]">
                            <form method="POST" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action_edit_store_item" value="1">
                                <input type="hidden" name="item_uuid" value="<?php echo htmlspecialchars($it['item_uuid']); ?>">
                                <div><label class="block text-[9px] font-bold uppercase mb-1">Name</label><input type="text" name="item_name" value="<?php echo htmlspecialchars($it['item_name']); ?>" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs"></div>
                                <div><label class="block text-[9px] font-bold uppercase mb-1">Category</label><input type="text" name="category" value="<?php echo htmlspecialchars($it['category']); ?>" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs"></div>
                                <div><label class="block text-[9px] font-bold uppercase mb-1">Price</label><input type="number" step="0.01" name="unit_price" value="<?php echo $it['unit_price']; ?>" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs"></div>
                                <div><label class="block text-[9px] font-bold uppercase mb-1">Stock</label><input type="number" name="stock_quantity" value="<?php echo $it['stock_quantity']; ?>" class="w-full bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs"></div>
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-[10px] font-bold">Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]"><h3 class="text-xs font-bold uppercase">Recent Sales</h3></div>
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px]"><tr><th class="p-3">Receipt</th><th class="p-3">Customer</th><th class="p-3">Items</th><th class="p-3">Total</th><th class="p-3">Payment</th><th class="p-3">Date</th></tr></thead>
            <tbody class="divide-y divide-[var(--border-color)]">
                <?php foreach ($sales as $s): $summary = json_decode($s['items_summary_json'], true) ?: []; ?>
                <tr>
                    <td class="p-3 font-mono text-indigo-400"><?php echo htmlspecialchars($s['receipt_number']); ?></td>
                    <td class="p-3 font-bold text-[var(--text-primary)]"><?php echo htmlspecialchars($s['student_name']); ?></td>
                    <td class="p-3 text-[var(--text-secondary)]"><?php echo htmlspecialchars(implode(', ', array_map(fn($x)=>$x['name'].' x'.$x['qty'], $summary))); ?></td>
                    <td class="p-3 font-mono text-emerald-400">₦<?php echo number_format($s['total_amount'],2); ?></td>
                    <td class="p-3 text-[var(--text-secondary)]"><?php echo htmlspecialchars($s['payment_method']); ?></td>
                    <td class="p-3 text-[var(--text-secondary)]"><?php echo $s['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- POS Sale Modal -->
<div id="posModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">New Sale</h3>
            <button onclick="document.getElementById('posModal').classList.add('hidden')" class="text-[var(--text-secondary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_pos_sale" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Student (optional)</label>
                <select name="student_uuid" onchange="const o=this.options[this.selectedIndex]; document.getElementById('posStudentName').value = o.dataset.name || 'Walk-in Customer';" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option value="" data-name="Walk-in Customer">Walk-in Customer</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?php echo htmlspecialchars($s['student_uuid']); ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['class']); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="posStudentName" name="student_name" value="Walk-in Customer">
            </div>
            <div class="space-y-2" id="saleLines">
                <div class="grid grid-cols-5 gap-2 items-end sale-line">
                    <div class="col-span-3">
                        <label class="block text-[9px] font-bold uppercase mb-1">Item</label>
                        <select name="sale_item_uuid[]" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs">
                            <?php foreach ($items as $it): ?><option value="<?php echo htmlspecialchars($it['item_uuid']); ?>">₦<?php echo number_format($it['unit_price'],2); ?> — <?php echo htmlspecialchars($it['item_name']); ?> (<?php echo $it['stock_quantity']; ?> left)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-[9px] font-bold uppercase mb-1">Qty</label>
                        <input type="number" name="sale_item_qty[]" min="1" value="1" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-2 py-1.5 text-xs">
                    </div>
                </div>
            </div>
            <button type="button" onclick="const c=document.getElementById('saleLines'); const l=c.firstElementChild.cloneNode(true); c.appendChild(l);" class="text-[10px] font-bold text-emerald-400 flex items-center gap-1"><i data-lucide="plus" class="w-3 h-3"></i> Add another item</button>
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Payment Method</label>
                <select name="payment_method" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option>Cash / Ledger</option><option>POS Card</option><option>Bank Transfer</option>
                </select>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl">Complete Sale</button>
        </form>
    </div>
</div>

<!-- Add Item Modal -->
<div id="addItemModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Add Inventory Item</h3>
            <button onclick="document.getElementById('addItemModal').classList.add('hidden')" class="text-[var(--text-secondary)]"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_add_store_item" value="1">
            <div>
                <label class="block text-[10px] font-bold uppercase mb-1">Item Name *</label>
                <input type="text" name="item_name" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Category</label>
                    <select name="category" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option>Uniform</option><option>Textbooks</option><option>Stationery</option><option>Cafeteria Vouchers</option><option>General</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Unit Price (₦)</label>
                    <input type="number" step="0.01" name="unit_price" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Initial Stock</label>
                    <input type="number" name="stock_quantity" value="0" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase mb-1">Reorder Level</label>
                    <input type="number" name="reorder_level" value="10" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-yellow-600 hover:bg-yellow-500 text-white font-bold text-xs rounded-xl">Add Item</button>
        </form>
    </div>
</div>
