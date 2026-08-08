<?php
/**
 * SECTION: Finance & Invoicing
 * Fee structures, student invoices, receipts, expense tracking, cashbook summary.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Finance & Invoicing' => null]);
$can_write = can_manage($active_role, $current_access);

$tab = safe_str($_GET['tab'] ?? 'invoices');

$fin_students = [];
try {
    $fss = $pdo->prepare("SELECT student_uuid, name, class FROM students WHERE school_uuid=? AND status='Active' ORDER BY name ASC");
    $fss->execute([$school_uuid]);
    $fin_students = $fss->fetchAll();
} catch (Exception $e) {}

// ── Summary stats ─────────────────────────────────────────────────────────
$stats = ['total_billed'=>0,'total_paid'=>0,'total_unpaid'=>0,'invoices'=>0];
try {
    $sq = $pdo->prepare("SELECT
        COUNT(*) as invoices,
        SUM(amount) as total_billed,
        SUM(CASE WHEN status='Paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status!='Paid' THEN amount ELSE 0 END) as total_unpaid
        FROM school_invoices WHERE school_uuid=?");
    $sq->execute([$school_uuid]);
    $stats = $sq->fetch() ?: $stats;
} catch(Exception $e){}

// ── Fee structures (JSON, per session/term/class) ────────────────────────
$fee_structures = [];
try {
    $fs = $pdo->prepare("SELECT * FROM fee_structures WHERE school_uuid=? AND session_name IS NOT NULL ORDER BY session_name DESC, term_name ASC, class_name ASC");
    $fs->execute([$school_uuid]);
    $fee_structures = $fs->fetchAll();
} catch(Exception $e){}

$fin_sessions = []; $fin_terms = [];
try {
    $sq = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE school_uuid=? ORDER BY id DESC");
    $sq->execute([$school_uuid]); $fin_sessions = $sq->fetchAll(PDO::FETCH_COLUMN);
    $tq = $pdo->prepare("SELECT term_name FROM academic_terms WHERE school_uuid=? ORDER BY id ASC");
    $tq->execute([$school_uuid]); $fin_terms = $tq->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
if (empty($fin_sessions)) $fin_sessions = [$school_settings['current_session'] ?? '2025/2026'];
if (empty($fin_terms))    $fin_terms    = ['First Term','Second Term','Third Term'];

// Students currently carrying a credit balance (from an overpayment)
$credit_balances = [];
try {
    $cbq = $pdo->prepare("
        SELECT c.*, s.name AS student_name, s.class AS student_class FROM student_fee_credits c
        JOIN students s ON s.student_uuid = c.student_uuid
        WHERE c.school_uuid=? AND c.balance > 0 ORDER BY c.balance DESC
    ");
    $cbq->execute([$school_uuid]);
    $credit_balances = $cbq->fetchAll();
} catch (Exception $e) {}

// ── Invoices ──────────────────────────────────────────────────────────────
$filter_status = safe_str($_GET['filter_status'] ?? '');
$search        = safe_str($_GET['q'] ?? '');
$total_inv     = 0;
try {
    $cq = "SELECT COUNT(*) FROM school_invoices i LEFT JOIN students s ON s.student_uuid=i.student_uuid WHERE i.school_uuid=?";
    $cb = [$school_uuid];
    if ($filter_status) { $cq .= " AND i.status=?"; $cb[] = $filter_status; }
    if ($search)        { $cq .= " AND (i.invoice_no LIKE ? OR i.plan LIKE ? OR s.name LIKE ?)"; $cb[] = "%$search%"; $cb[] = "%$search%"; $cb[] = "%$search%"; }
    $cs = $pdo->prepare($cq); $cs->execute($cb);
    $total_inv = (int)$cs->fetchColumn();
} catch(Exception $e){}
$pg       = paginate($total_inv, 20, 'finance', array_filter(['tab'=>$tab,'filter_status'=>$filter_status,'q'=>$search]));
$invoices = [];
try {
    $iq = "SELECT i.*, s.name AS student_name, s.class AS student_class FROM school_invoices i LEFT JOIN students s ON s.student_uuid = i.student_uuid WHERE i.school_uuid=?";
    $ib = [$school_uuid];
    if ($filter_status) { $iq .= " AND i.status=?"; $ib[] = $filter_status; }
    if ($search)        { $iq .= " AND (i.invoice_no LIKE ? OR i.plan LIKE ? OR s.name LIKE ?)"; $ib[] = "%$search%"; $ib[] = "%$search%"; $ib[] = "%$search%"; }
    $iq .= " ORDER BY i.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}";
    $is2 = $pdo->prepare($iq); $is2->execute($ib);
    $invoices = $is2->fetchAll();
} catch(Exception $e){}

// ── Receipts ──────────────────────────────────────────────────────────────
$receipts = [];
try {
    $rq = $pdo->prepare("SELECT r.*, i.plan, i.invoice_no as orig_inv FROM school_receipts r LEFT JOIN school_invoices i ON r.invoice_uuid=i.invoice_uuid WHERE r.school_uuid=? ORDER BY r.id DESC LIMIT 50");
    $rq->execute([$school_uuid]);
    $receipts = $rq->fetchAll();
} catch(Exception $e){}

// Payment Requests (parent-initiated) — Phase E
$payment_requests = [];
try {
    $prq = $pdo->prepare("SELECT pr.*, p.name AS parent_name, s.name AS student_name
        FROM payment_requests pr LEFT JOIN parents p ON p.parent_uuid = pr.parent_uuid
        LEFT JOIN students s ON s.student_uuid = pr.student_uuid
        WHERE pr.school_uuid=? ORDER BY (pr.status='Pending') DESC, pr.created_at DESC LIMIT 50");
    $prq->execute([$school_uuid]);
    $payment_requests = $prq->fetchAll();
} catch (Exception $e) {}
?>

<div class="space-y-6">

    <!-- Payment Requests (parent-initiated) -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-[var(--border-color)] flex items-center justify-between">
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Parent Payment Requests</h3>
            <span class="text-[10px] text-[var(--text-secondary)]"><?php echo count(array_filter($payment_requests, fn($r) => $r['status']==='Pending')); ?> pending</span>
        </div>
        <div class="divide-y divide-[var(--border-color)]">
            <?php foreach ($payment_requests as $pr): ?>
            <div class="p-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--text-primary)]"><b><?php echo htmlspecialchars($pr['parent_name'] ?? 'Parent'); ?></b><?php echo $pr['student_name'] ? ' — for ' . htmlspecialchars($pr['student_name']) : ''; ?></p>
                    <p class="text-[10px] text-[var(--text-secondary)]"><?php echo htmlspecialchars($pr['description']); ?><?php echo $pr['amount'] ? ' · ₦' . number_format((float)$pr['amount'],2) : ''; ?> · <?php echo date('M j', strtotime($pr['created_at'])); ?></p>
                </div>
                <?php if ($pr['status'] === 'Pending' && ($can_write ?? false)): ?>
                <div class="flex gap-2">
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_resolve_payment_request" value="1"><input type="hidden" name="request_uuid" value="<?php echo htmlspecialchars($pr['request_uuid']); ?>"><input type="hidden" name="decision" value="Approved"><button class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold rounded-lg">Approve</button></form>
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_resolve_payment_request" value="1"><input type="hidden" name="request_uuid" value="<?php echo htmlspecialchars($pr['request_uuid']); ?>"><input type="hidden" name="decision" value="Declined"><button class="px-3 py-1 bg-rose-600 hover:bg-rose-500 text-white text-[10px] font-bold rounded-lg">Decline</button></form>
                </div>
                <?php else: ?>
                <span class="text-[10px] font-bold px-2 py-1 rounded-lg <?php echo $pr['status']==='Approved' ? 'bg-emerald-500/10 text-emerald-400' : ($pr['status']==='Declined' ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400'); ?>"><?php echo htmlspecialchars($pr['status']); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($payment_requests)): ?><p class="p-6 text-center text-xs italic text-[var(--text-secondary)]">No payment requests yet.</p><?php endif; ?>
        </div>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[var(--border-color)]">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="landmark" class="w-5 h-5 text-emerald-400"></i> Finance & Invoicing
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">School fees, invoices, receipts, and expense tracking.</p>
        </div>
        <?php if ($can_write): ?>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('addFeeStructModal').classList.remove('hidden')"
                class="px-3 py-2 bg-[var(--bg-secondary)] border border-[var(--border-color)] text-xs font-bold rounded-xl hover:text-white flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-indigo-400"></i> Fee Structure
            </button>
            <button onclick="document.getElementById('addInvoiceModal').classList.remove('hidden')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
                <i data-lucide="file-plus" class="w-4 h-4"></i> New Invoice
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <?php
        $kpis = [
            ['label'=>'Total Invoiced',  'value'=>'₦'.number_format((float)$stats['total_billed'],2),  'color'=>'text-indigo-400',  'bg'=>'bg-indigo-500/10'],
            ['label'=>'Collected',       'value'=>'₦'.number_format((float)$stats['total_paid'],2),    'color'=>'text-emerald-400', 'bg'=>'bg-emerald-500/10'],
            ['label'=>'Outstanding',     'value'=>'₦'.number_format((float)$stats['total_unpaid'],2),  'color'=>'text-rose-400',    'bg'=>'bg-rose-500/10'],
            ['label'=>'Total Invoices',  'value'=>number_format((int)$stats['invoices']),              'color'=>'text-amber-400',   'bg'=>'bg-amber-500/10'],
        ];
        foreach ($kpis as $k): ?>
        <div class="<?php echo $k['bg']; ?> border border-[var(--border-color)] rounded-2xl p-5">
            <div class="text-lg font-black <?php echo $k['color']; ?>"><?php echo $k['value']; ?></div>
            <div class="text-[10px] text-[var(--text-secondary)] mt-0.5"><?php echo $k['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 p-1 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl w-fit">
        <?php foreach (['invoices'=>'Invoices','fee_structure'=>'Fee Structure','receipts'=>'Receipts'] as $t=>$label): ?>
        <a href="dashboard.php?section=finance&tab=<?php echo $t; ?>"
           class="px-4 py-2 text-xs font-bold rounded-xl transition-all <?php echo $tab===$t?'bg-indigo-600 text-white shadow':'text-[var(--text-secondary)] hover:text-white'; ?>">
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── TAB: INVOICES ──────────────────────────────────────────────── -->
    <?php if ($tab === 'invoices'): ?>
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="section" value="finance">
        <input type="hidden" name="tab" value="invoices">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search invoice #, plan…"
            class="flex-1 min-w-40 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
        <select name="filter_status" onchange="this.form.submit()" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="">All Status</option>
            <option value="Unpaid"   <?php echo $filter_status==='Unpaid'  ?'selected':''; ?>>Unpaid</option>
            <option value="Paid"     <?php echo $filter_status==='Paid'    ?'selected':''; ?>>Paid</option>
            <option value="Overdue"  <?php echo $filter_status==='Overdue' ?'selected':''; ?>>Overdue</option>
            <option value="Partial"  <?php echo $filter_status==='Partial' ?'selected':''; ?>>Partial</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-bold rounded-xl">Search</button>
    </form>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                <tr>
                    <th class="p-3.5">Invoice #</th><th class="p-3.5">Student</th><th class="p-3.5">Plan / Description</th>
                    <th class="p-3.5 text-right">Amount</th><th class="p-3.5">Due Date</th>
                    <th class="p-3.5">Status</th><th class="p-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php if (empty($invoices)): ?>
                <tr><td colspan="7" class="p-8 text-center text-[var(--text-secondary)] italic">No invoices yet.</td></tr>
            <?php else: foreach ($invoices as $inv):
                $sc = ['Paid'=>'bg-emerald-500/10 text-emerald-400','Unpaid'=>'bg-rose-500/10 text-rose-400','Partial'=>'bg-amber-500/10 text-amber-400','Overdue'=>'bg-red-500/10 text-red-400'];
            ?>
                <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors">
                    <td class="p-3.5 font-mono font-bold text-indigo-400"><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                    <td class="p-3.5"><?php echo htmlspecialchars($inv['student_name'] ?? '—'); ?><?php if(!empty($inv['student_class'])): ?><span class="text-[var(--text-secondary)]"> (<?php echo htmlspecialchars($inv['student_class']); ?>)</span><?php endif; ?></td>
                    <td class="p-3.5 font-semibold"><?php echo htmlspecialchars($inv['plan']); ?></td>
                    <td class="p-3.5 font-mono font-bold text-emerald-400 text-right">₦<?php echo number_format((float)$inv['amount'],2); ?></td>
                    <td class="p-3.5 font-mono <?php echo $inv['due_date']<date('Y-m-d')&&$inv['status']!=='Paid'?'text-rose-400 font-bold':''; ?>">
                        <?php echo htmlspecialchars($inv['due_date']); ?>
                    </td>
                    <td class="p-3.5">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $sc[$inv['status']] ?? 'bg-slate-500/10 text-slate-400'; ?>">
                            <?php echo htmlspecialchars($inv['status']); ?>
                        </span>
                    </td>
                    <td class="p-3.5 text-right space-x-2">
                        <?php if ($can_write && $inv['status'] !== 'Paid'): ?>
                        <button onclick='openReceiptModal(<?php echo json_encode($inv, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG); ?>)'
                            class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-bold">
                            Record Payment
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Apply this student\'s available credit balance to this invoice?')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_apply_credit" value="1">
                            <input type="hidden" name="invoice_uuid" value="<?php echo htmlspecialchars($inv['invoice_uuid']); ?>">
                            <button type="submit" class="px-2.5 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-lg text-[10px] font-bold">Apply Credit</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <div class="px-4 pb-4"><?php render_pagination($pg); ?></div>
    </div>

    <!-- ── TAB: FEE STRUCTURE ─────────────────────────────────────────── -->
    <?php elseif ($tab === 'fee_structure'): ?>
    <?php if ($can_write): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
        <div>
            <h3 class="text-sm font-bold text-[var(--text-primary)]">Set Up Fee Structure</h3>
            <p class="text-[10px] text-[var(--text-secondary)] mt-1">One itemized structure per class, per term, per session — e.g. Tuition, Books, PTA Levy each with their own amount, summed automatically.</p>
        </div>
        <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_save_fee_structure_json" value="1">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="block text-[10px] font-bold uppercase mb-1">Session</label>
                    <select name="fs_session" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($fin_sessions as $sn): ?><option value="<?php echo htmlspecialchars($sn); ?>"><?php echo htmlspecialchars($sn); ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Term</label>
                    <select name="fs_term" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($fin_terms as $tn): ?><option value="<?php echo htmlspecialchars($tn); ?>"><?php echo htmlspecialchars($tn); ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="block text-[10px] font-bold uppercase mb-1">Class</label>
                    <select name="fs_class" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($roster_classes as $cl): ?><option value="<?php echo htmlspecialchars($cl); ?>"><?php echo htmlspecialchars($cl); ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div id="feeItemRows" class="space-y-2">
                <div class="fee-item-row flex gap-2 items-center">
                    <input type="text" name="item_name[]" placeholder="e.g. Tuition" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">
                    <input type="number" step="0.01" name="item_amount[]" placeholder="Amount" class="w-32 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">
                    <button type="button" onclick="this.closest('.fee-item-row').remove()" class="text-rose-400 text-[10px] px-2">✕</button>
                </div>
            </div>
            <button type="button" onclick="addFeeItemRow()" class="px-3 py-1.5 bg-emerald-600/20 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-bold">+ Add Item</button>
            <div><button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl">Save Fee Structure</button></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
        <h3 class="text-sm font-bold text-[var(--text-primary)]">Configured Fee Structures</h3>
        <?php if (empty($fee_structures)): ?>
            <p class="text-xs text-[var(--text-secondary)] italic">No fee structures set up yet.</p>
        <?php else: foreach ($fee_structures as $fs): $items = json_decode($fs['items_json'] ?? '[]', true) ?: []; ?>
            <div class="p-4 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl space-y-2">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-bold text-sm text-[var(--text-primary)]"><?php echo htmlspecialchars($fs['class_name']); ?></span>
                        <span class="text-[10px] text-[var(--text-secondary)] ml-2 font-mono"><?php echo htmlspecialchars($fs['term_name'].' — '.$fs['session_name']); ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-emerald-400">₦<?php echo number_format((float)$fs['total_amount'],2); ?></span>
                        <?php if ($can_write): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this entire fee structure? Invoices already generated from it are not affected.')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action_delete_fee_structure_json" value="1">
                            <input type="hidden" name="fee_uuid" value="<?php echo htmlspecialchars($fs['fee_uuid']); ?>">
                            <button type="submit" class="text-rose-400 hover:text-rose-300 text-[10px]">✕</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($items)): ?>
                <div class="flex flex-wrap gap-2 text-[10px]">
                    <?php foreach ($items as $it): ?>
                        <span class="px-2 py-0.5 bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-full"><?php echo htmlspecialchars($it['name']); ?>: ₦<?php echo number_format((float)$it['amount'],2); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($can_write): ?>
                <form method="POST" class="pt-1" onsubmit="return confirm('Generate an invoice for every active student in this class?')"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action_generate_invoices_from_structure" value="1">
                    <input type="hidden" name="gen_session" value="<?php echo htmlspecialchars($fs['session_name']); ?>">
                    <input type="hidden" name="gen_term" value="<?php echo htmlspecialchars($fs['term_name']); ?>">
                    <input type="hidden" name="gen_class" value="<?php echo htmlspecialchars($fs['class_name']); ?>">
                    <input type="date" name="gen_due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="bg-[var(--bg-primary)] border border-[var(--border-color)] rounded-lg px-2 py-1 text-[10px] text-[var(--text-primary)]">
                    <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-bold rounded-lg">Generate Invoices for Class</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <?php if (!empty($credit_balances)): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-3">
        <h3 class="text-sm font-bold text-[var(--text-primary)]">Student Credit Balances <span class="text-[10px] font-normal text-[var(--text-secondary)]">(from overpayments — auto-applied to their next invoice)</span></h3>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($credit_balances as $cb): ?>
            <span class="px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full text-[10px] font-bold">
                <?php echo htmlspecialchars($cb['student_name']); ?> (<?php echo htmlspecialchars($cb['student_class']); ?>): ₦<?php echo number_format((float)$cb['balance'],2); ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <script>
    function addFeeItemRow() {
        const wrap = document.createElement('div');
        wrap.className = 'fee-item-row flex gap-2 items-center';
        wrap.innerHTML = '<input type="text" name="item_name[]" placeholder="e.g. Books" class="flex-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">' +
            '<input type="number" step="0.01" name="item_amount[]" placeholder="Amount" class="w-32 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-lg px-3 py-1.5 text-xs text-[var(--text-primary)]">' +
            '<button type="button" onclick="this.closest(\'.fee-item-row\').remove()" class="text-rose-400 text-[10px] px-2">✕</button>';
        document.getElementById('feeItemRows').appendChild(wrap);
    }
    </script>

    <!-- ── TAB: RECEIPTS ──────────────────────────────────────────────── -->
    <?php elseif ($tab === 'receipts'): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                <tr>
                    <th class="p-3.5">Receipt #</th><th class="p-3.5">Invoice</th><th class="p-3.5">Plan</th>
                    <th class="p-3.5 text-right">Amount</th><th class="p-3.5">Method</th><th class="p-3.5">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)]">
            <?php if (empty($receipts)): ?>
                <tr><td colspan="6" class="p-8 text-center text-[var(--text-secondary)] italic">No receipts yet.</td></tr>
            <?php else: foreach ($receipts as $r): ?>
                <tr class="hover:bg-[var(--bg-tertiary)]/50 transition-colors">
                    <td class="p-3.5 font-mono font-bold text-indigo-400"><?php echo htmlspecialchars($r['receipt_no']); ?></td>
                    <td class="p-3.5 font-mono text-[var(--text-secondary)] text-[10px]"><?php echo htmlspecialchars($r['orig_inv'] ?? $r['invoice_uuid']); ?></td>
                    <td class="p-3.5 font-semibold"><?php echo htmlspecialchars($r['plan'] ?? '—'); ?></td>
                    <td class="p-3.5 font-mono font-bold text-emerald-400 text-right">₦<?php echo number_format((float)$r['amount'],2); ?></td>
                    <td class="p-3.5 text-[var(--text-secondary)]"><?php echo htmlspecialchars($r['payment_method']); ?></td>
                    <td class="p-3.5 font-mono text-[var(--text-secondary)]"><?php echo htmlspecialchars(substr($r['created_at'],0,10)); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ── MODALS ──────────────────────────────────────────────────────────────── -->

<!-- New Invoice -->
<div id="addInvoiceModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Create Invoice</h3>
            <button onclick="document.getElementById('addInvoiceModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_create_invoice" value="1">
            <div>
                <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Bill To *</label>
                <div class="flex gap-3 mb-2 text-xs">
                    <label class="flex items-center gap-1.5"><input type="radio" name="invoice_target" value="student" checked onchange="document.getElementById('invStudentWrap').classList.remove('hidden');document.getElementById('invClassWrap').classList.add('hidden');"> One Student</label>
                    <label class="flex items-center gap-1.5"><input type="radio" name="invoice_target" value="class" onchange="document.getElementById('invStudentWrap').classList.add('hidden');document.getElementById('invClassWrap').classList.remove('hidden');"> Whole Class</label>
                </div>
                <div id="invStudentWrap">
                    <select name="student_uuid" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option value="">Select student...</option>
                        <?php foreach ($fin_students ?? [] as $fs2): ?>
                        <option value="<?php echo htmlspecialchars($fs2['student_uuid']); ?>"><?php echo htmlspecialchars($fs2['name']); ?> (<?php echo htmlspecialchars($fs2['class']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="invClassWrap" class="hidden">
                    <select name="class_name" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <?php foreach ($roster_classes as $rc2): ?><option value="<?php echo htmlspecialchars($rc2); ?>"><?php echo htmlspecialchars($rc2); ?></option><?php endforeach; ?>
                    </select>
                    <p class="text-[9px] text-[var(--text-secondary)] mt-1">Creates one invoice per active student in this class.</p>
                </div>
            </div>
            <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Plan / Description *</label>
                <input type="text" name="plan" required placeholder="e.g. 2025/2026 First Term School Fees"
                    class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Amount (₦) *</label>
                    <input type="number" name="amount" required min="0" step="0.01"
                        class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Due Date *</label>
                    <input type="date" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                        class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]"></div>
            </div>
            <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Billing Cycle</label>
                <select name="billing_cycle" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                    <option>One-Time</option><option>Monthly</option><option>Termly</option><option>Annual</option>
                </select></div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg">Create Invoice</button>
        </form>
    </div>
</div>

<!-- Fee Structure -->
<div id="addFeeStructModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-sm w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Add Fee Structure</h3>
            <button onclick="document.getElementById('addFeeStructModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <p class="text-xs text-[var(--text-secondary)]">Use the <strong>Fee Structure</strong> tab to manage fee definitions.</p>
        <a href="dashboard.php?section=finance&tab=fee_structure"
           class="block w-full py-3 bg-indigo-600 text-white font-bold text-xs rounded-xl text-center">Go to Fee Structure →</a>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="receiptModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Record Payment</h3>
            <button onclick="document.getElementById('receiptModal').classList.add('hidden')" class="text-[var(--text-secondary)] hover:text-white text-lg">✕</button>
        </div>
        <form method="POST" class="space-y-3"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_record_payment" value="1">
            <input type="hidden" name="invoice_uuid" id="rcptInvUuid">
            <div class="p-3 bg-[var(--bg-tertiary)] rounded-xl text-xs">
                <span class="text-[var(--text-secondary)]">Invoice:</span>
                <strong id="rcptInvNo" class="font-mono text-indigo-400 ml-2"></strong>
                <span class="ml-3 text-[var(--text-secondary)]">Outstanding:</span>
                <strong id="rcptOutstanding" class="text-rose-400 ml-1"></strong>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Amount Paid (₦)</label>
                    <input type="number" name="amount_paid" id="rcptAmountPaid" required min="0" step="0.01"
                        class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
                <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Payment Method</label>
                    <select name="payment_method" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option>Bank Transfer</option><option>Cash</option><option>Paystack</option><option>Flutterwave</option><option>POS</option><option>Cheque</option>
                    </select></div>
            </div>
            <div><label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Transaction Ref (optional)</label>
                <input type="text" name="transaction_ref" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono"></div>
            <p class="text-[9px] text-[var(--text-secondary)] italic">If the amount paid exceeds the outstanding balance, the excess is automatically credited to the student's account and applied to their next invoice.</p>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg">Confirm Payment & Issue Receipt</button>
        </form>
    </div>
</div>

<script>
function openReceiptModal(inv) {
    document.getElementById('rcptInvUuid').value    = inv.invoice_uuid;
    document.getElementById('rcptInvNo').textContent = inv.invoice_no;
    document.getElementById('rcptOutstanding').textContent = '₦' + parseFloat(inv.amount).toLocaleString('en-NG', {minimumFractionDigits:2});
    document.getElementById('rcptAmountPaid').value = inv.amount;
    document.getElementById('receiptModal').classList.remove('hidden');
}
</script>
