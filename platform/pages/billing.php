<?php
$filter_school = $_GET['filter_school'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$search = $_GET['q'] ?? '';
$invoice_page = isset($_GET['invoice_page']) ? max(1, (int)$_GET['invoice_page']) : 1;
$receipt_page = isset($_GET['receipt_page']) ? max(1, (int)$_GET['receipt_page']) : 1;
$per_page = 10;

// Invoices query with pagination
$params = [];
$sql = "SELECT i.*, s.name as school_name FROM school_invoices i JOIN schools s ON i.school_uuid = s.school_uuid";
$where = [];
if ($filter_school) { $where[] = "i.school_uuid = ?"; $params[] = $filter_school; }
if ($filter_status) { $where[] = "i.status = ?"; $params[] = $filter_status; }
if ($search) { $where[] = "(i.invoice_no LIKE ? OR i.plan LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);

// Get total invoice count for pagination
$count_sql = str_replace("SELECT i.*, s.name as school_name", "SELECT COUNT(*) as total", $sql);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_invoices = (int)$count_stmt->fetch()['total'];

// Add pagination to invoice query
$sql .= " ORDER BY i.id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = ($invoice_page - 1) * $per_page;

$invoices = $pdo->prepare($sql);
$invoices->execute($params);
$invoices = $invoices->fetchAll();
$total_invoice_pages = ceil($total_invoices / $per_page);

// Receipts query with pagination
$receipt_params = [];
$receipt_sql = "SELECT r.*, i.plan, i.invoice_no as orig_inv, s.name as school_name 
                FROM school_receipts r 
                LEFT JOIN school_invoices i ON r.invoice_uuid = i.invoice_uuid 
                LEFT JOIN schools s ON i.school_uuid = s.school_uuid";

// Add search to receipts if needed
if ($search) {
    $receipt_sql .= " WHERE r.receipt_no LIKE ? OR s.name LIKE ?";
    $receipt_params[] = "%$search%";
    $receipt_params[] = "%$search%";
}

// Get total receipt count for pagination
$count_receipt_sql = str_replace("SELECT r.*, i.plan, i.invoice_no as orig_inv, s.name as school_name", "SELECT COUNT(*) as total", $receipt_sql);
$count_receipt_stmt = $pdo->prepare($count_receipt_sql);
$count_receipt_stmt->execute($receipt_params);
$total_receipts = (int)$count_receipt_stmt->fetch()['total'];

// Add pagination to receipt query
$receipt_sql .= " ORDER BY r.id DESC LIMIT ? OFFSET ?";
$receipt_params[] = $per_page;
$receipt_params[] = ($receipt_page - 1) * $per_page;

$receipts = $pdo->prepare($receipt_sql);
$receipts->execute($receipt_params);
$receipts = $receipts->fetchAll();
$total_receipt_pages = ceil($total_receipts / $per_page);

$schools_list = $pdo->query("SELECT school_uuid, name FROM schools ORDER BY name ASC")->fetchAll();

// Get credit balances for all schools
$credit_balances = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'school_credit_balances'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        $stmt = $pdo->query("SELECT school_uuid, credit_balance FROM school_credit_balances WHERE credit_balance > 0");
        while ($row = $stmt->fetch()) {
            $credit_balances[$row['school_uuid']] = (float)$row['credit_balance'];
        }
    }
} catch (Exception $e) {
    error_log('Credit balances table not found or error: ' . $e->getMessage());
}

// Calculate total outstanding balance
$total_outstanding = 0;
$total_paid = 0;
foreach ($invoices as $inv) {
    if ($inv['status'] !== 'Paid') {
        $total_outstanding += (float)$inv['amount'];
    } else {
        $total_paid += (float)$inv['amount'];
    }
}

// Build query string for pagination links
function buildQueryString($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Billing & Invoices</h1>
        <div class="flex items-center gap-3 flex-wrap">
            <span class="px-3 py-1 bg-indigo-500/10 text-indigo-400 rounded-full text-xs font-bold">
                <?php echo number_format($total_invoices); ?> Invoices
            </span>
            <span class="px-3 py-1 bg-emerald-500/10 text-emerald-400 rounded-full text-xs font-bold">
                ₦<?php echo number_format($total_paid, 2); ?> Paid
            </span>
            <span class="px-3 py-1 bg-amber-500/10 text-amber-400 rounded-full text-xs font-bold">
                ₦<?php echo number_format($total_outstanding, 2); ?> Outstanding
            </span>
        </div>
    </div>
    
    <!-- Filter Form -->
    <form method="GET" class="flex flex-wrap gap-3 p-4 bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl text-xs">
        <input type="hidden" name="page" value="billing">
        <input type="hidden" name="invoice_page" value="1">
        <input type="hidden" name="receipt_page" value="1">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search invoice or school…" class="flex-1 min-w-40 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
        <select name="filter_school" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="">All Schools</option>
            <?php foreach ($schools_list as $sch): ?>
                <option value="<?php echo htmlspecialchars($sch['school_uuid']); ?>" <?php echo $filter_school === $sch['school_uuid'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sch['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_status" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-[var(--text-primary)]">
            <option value="">All Status</option>
            <option value="Unpaid" <?php echo $filter_status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            <option value="Paid" <?php echo $filter_status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="Overdue" <?php echo $filter_status === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl transition">Filter</button>
        <a href="index.php?page=billing" class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] font-bold rounded-xl transition">Clear</a>
    </form>
    
    <!-- Credit Balance Summary (if any credits exist) -->
    <?php if (!empty($credit_balances)): ?>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-4">
        <h4 class="text-xs font-bold uppercase text-[var(--text-secondary)] mb-3">Credit Balances</h4>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($credit_balances as $school_uuid => $balance): 
                $school_name = 'Unknown School';
                foreach ($schools_list as $sch) {
                    if ($sch['school_uuid'] === $school_uuid) {
                        $school_name = $sch['name'];
                        break;
                    }
                }
            ?>
            <div class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-3">
                <div class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo htmlspecialchars($school_name); ?></div>
                <div class="text-sm font-bold text-emerald-400">₦<?php echo number_format($balance, 2); ?></div>
                <div class="text-[9px] text-[var(--text-secondary)]">Credit Balance</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Invoices Table -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)] flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase text-[var(--text-primary)]">Invoices</h3>
            <span class="text-[10px] text-[var(--text-secondary)]">Showing <?php echo min(($invoice_page-1)*$per_page+1, $total_invoices); ?> – <?php echo min($invoice_page*$per_page, $total_invoices); ?> of <?php echo number_format($total_invoices); ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                    <tr>
                        <th class="px-3.5 py-3">Invoice</th>
                        <th class="px-3.5 py-3">School</th>
                        <th class="px-3.5 py-3">Plan</th>
                        <th class="px-3.5 py-3 text-right">Amount</th>
                        <th class="px-3.5 py-3">Due Date</th>
                        <th class="px-3.5 py-3">Status</th>
                        <th class="px-3.5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" class="px-3.5 py-8 text-center text-[var(--text-secondary)] italic">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-[var(--text-secondary)] opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span>No invoices found</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $safeInvoiceNo = htmlspecialchars($inv['invoice_no'], ENT_QUOTES, 'UTF-8');
                            $safeSchoolName = htmlspecialchars($inv['school_name'], ENT_QUOTES, 'UTF-8');
                            $safePlan = htmlspecialchars($inv['plan'], ENT_QUOTES, 'UTF-8');
                            $safeStatus = htmlspecialchars($inv['status'], ENT_QUOTES, 'UTF-8');
                            $safeDueDate = htmlspecialchars($inv['due_date'], ENT_QUOTES, 'UTF-8');
                            $isOverdue = $inv['due_date'] < date('Y-m-d') && $inv['status'] !== 'Paid';
                            
                            $hasCredit = isset($credit_balances[$inv['school_uuid']]) && $credit_balances[$inv['school_uuid']] > 0;
                            $creditAmount = $hasCredit ? $credit_balances[$inv['school_uuid']] : 0;
                        ?>
                        <tr class="hover:bg-[var(--bg-tertiary)]/50">
                            <td class="px-3.5 py-3 font-mono font-bold text-indigo-400"><?php echo $safeInvoiceNo; ?></td>
                            <td class="px-3.5 py-3 font-semibold text-[var(--text-primary)]">
                                <?php echo $safeSchoolName; ?>
                                <?php if ($hasCredit): ?>
                                    <span class="ml-2 text-[10px] bg-emerald-500/10 text-emerald-400 px-1.5 py-0.5 rounded">Cred: ₦<?php echo number_format($creditAmount, 2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3.5 py-3">
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded font-mono font-bold"><?php echo $safePlan; ?></span>
                            </td>
                            <td class="px-3.5 py-3 font-mono font-bold text-emerald-400 text-right">₦<?php echo number_format((float)$inv['amount'], 2); ?></td>
                            <td class="px-3.5 py-3 font-mono <?php echo $isOverdue ? 'text-rose-400 font-bold' : 'text-[var(--text-secondary)]'; ?>">
                                <?php echo $safeDueDate; ?>
                                <?php if ($isOverdue): ?>
                                    <span class="ml-1 text-[10px] bg-rose-500/10 text-rose-400 px-1.5 py-0.5 rounded">OVERDUE</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3.5 py-3">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold 
                                    <?php echo $inv['status'] === 'Paid' ? 'bg-emerald-500/10 text-emerald-400' : ($isOverdue ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400'); ?>">
                                    <?php echo $safeStatus; ?>
                                </span>
                            </td>
                            <td class="px-3.5 py-3">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <?php if ($inv['status'] !== 'Paid'): ?>
                                    <button onclick="openReceiptModal(this)" 
                                            data-invoice='<?php echo json_encode([
                                                'invoice_uuid' => $inv['invoice_uuid'],
                                                'invoice_no' => $inv['invoice_no'],
                                                'amount' => $inv['amount'],
                                                'has_credit' => $hasCredit,
                                                'credit_amount' => $creditAmount,
                                                'school_uuid' => $inv['school_uuid']
                                            ]); ?>'
                                            class="px-2.5 py-1 bg-emerald-600/10 hover:bg-emerald-600/20 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap transition">
                                        <?php echo $hasCredit ? 'Pay with Credit' : 'Pay'; ?>
                                    </button>
                                    <?php else: ?>
                                    <span class="text-[var(--text-secondary)] text-[10px] font-mono">Paid</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Invoice Pagination -->
        <?php if ($total_invoice_pages > 1): ?>
        <div class="px-3.5 py-3 border-t border-[var(--border-color)] flex items-center justify-between text-xs text-[var(--text-secondary)]">
            <div class="font-mono">
                Page <?php echo $invoice_page; ?> of <?php echo $total_invoice_pages; ?>
            </div>
            <div class="flex items-center gap-1">
                <?php if ($invoice_page > 1): ?>
                    <a href="?<?php echo buildQueryString(['invoice_page']); ?>&invoice_page=<?php echo $invoice_page-1; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">←</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_invoice_pages; $i++): ?>
                    <?php if ($i === $invoice_page): ?>
                        <span class="px-2.5 py-1 bg-indigo-600 text-white rounded-lg font-bold min-w-[28px] text-center"><?php echo $i; ?></span>
                    <?php elseif ($i === 1 || $i === $total_invoice_pages || abs($i - $invoice_page) <= 2): ?>
                        <a href="?<?php echo buildQueryString(['invoice_page']); ?>&invoice_page=<?php echo $i; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition min-w-[28px] text-center"><?php echo $i; ?></a>
                    <?php elseif ($i === 2 || $i === $total_invoice_pages - 1): ?>
                        <span class="px-1 text-[var(--text-secondary)]">…</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($invoice_page < $total_invoice_pages): ?>
                    <a href="?<?php echo buildQueryString(['invoice_page']); ?>&invoice_page=<?php echo $invoice_page+1; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">→</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Receipts Table -->
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden shadow-xl">
        <div class="p-4 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)] flex items-center justify-between">
            <h3 class="text-xs font-bold uppercase text-[var(--text-primary)]">Recent Receipts</h3>
            <span class="text-[10px] text-[var(--text-secondary)]">Showing <?php echo min(($receipt_page-1)*$per_page+1, $total_receipts); ?> – <?php echo min($receipt_page*$per_page, $total_receipts); ?> of <?php echo number_format($total_receipts); ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                    <tr>
                        <th class="px-3.5 py-3">Receipt #</th>
                        <th class="px-3.5 py-3">Invoice</th>
                        <th class="px-3.5 py-3">School</th>
                        <th class="px-3.5 py-3 text-right">Amount</th>
                        <th class="px-3.5 py-3">Method</th>
                        <th class="px-3.5 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php if (empty($receipts)): ?>
                        <tr>
                            <td colspan="6" class="px-3.5 py-8 text-center text-[var(--text-secondary)] italic">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-8 h-8 text-[var(--text-secondary)] opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <span>No receipts found</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($receipts as $r): 
                            $safeReceiptNo = htmlspecialchars($r['receipt_no'], ENT_QUOTES, 'UTF-8');
                            $safeOrigInv = htmlspecialchars($r['orig_inv'] ?? $r['invoice_uuid'], ENT_QUOTES, 'UTF-8');
                            $safeSchoolName = htmlspecialchars($r['school_name'] ?? '—', ENT_QUOTES, 'UTF-8');
                            $safeMethod = htmlspecialchars($r['payment_method'], ENT_QUOTES, 'UTF-8');
                            $safeDate = htmlspecialchars($r['created_at'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr class="hover:bg-[var(--bg-tertiary)]/50">
                            <td class="px-3.5 py-3 font-mono font-bold text-indigo-400"><?php echo $safeReceiptNo; ?></td>
                            <td class="px-3.5 py-3 font-mono text-[var(--text-secondary)] text-[10px]"><?php echo $safeOrigInv; ?></td>
                            <td class="px-3.5 py-3 font-semibold text-[var(--text-primary)]"><?php echo $safeSchoolName; ?></td>
                            <td class="px-3.5 py-3 font-mono font-bold text-emerald-400 text-right">₦<?php echo number_format((float)$r['amount'], 2); ?></td>
                            <td class="px-3.5 py-3">
                                <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded font-mono text-[10px]"><?php echo $safeMethod; ?></span>
                            </td>
                            <td class="px-3.5 py-3 font-mono text-[var(--text-secondary)]"><?php echo $safeDate; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Receipt Pagination -->
        <?php if ($total_receipt_pages > 1): ?>
        <div class="px-3.5 py-3 border-t border-[var(--border-color)] flex items-center justify-between text-xs text-[var(--text-secondary)]">
            <div class="font-mono">
                Page <?php echo $receipt_page; ?> of <?php echo $total_receipt_pages; ?>
            </div>
            <div class="flex items-center gap-1">
                <?php if ($receipt_page > 1): ?>
                    <a href="?<?php echo buildQueryString(['receipt_page']); ?>&receipt_page=<?php echo $receipt_page-1; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">←</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_receipt_pages; $i++): ?>
                    <?php if ($i === $receipt_page): ?>
                        <span class="px-2.5 py-1 bg-indigo-600 text-white rounded-lg font-bold min-w-[28px] text-center"><?php echo $i; ?></span>
                    <?php elseif ($i === 1 || $i === $total_receipt_pages || abs($i - $receipt_page) <= 2): ?>
                        <a href="?<?php echo buildQueryString(['receipt_page']); ?>&receipt_page=<?php echo $i; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition min-w-[28px] text-center"><?php echo $i; ?></a>
                    <?php elseif ($i === 2 || $i === $total_receipt_pages - 1): ?>
                        <span class="px-1 text-[var(--text-secondary)]">…</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($receipt_page < $total_receipt_pages): ?>
                    <a href="?<?php echo buildQueryString(['receipt_page']); ?>&receipt_page=<?php echo $receipt_page+1; ?>" class="px-2.5 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] hover:border-indigo-500 rounded-lg text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">→</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Receipt Modal - Centered -->
<div id="receiptModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-md w-full p-6 space-y-4 relative">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Record Payment</h3>
            <button onclick="closeModal('receiptModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xl transition p-1 rounded hover:bg-[var(--bg-tertiary)]">✕</button>
        </div>
        <form method="POST" class="space-y-3" id="paymentForm"><?php echo csrf_field(); ?>
            <input type="hidden" name="action_record_payment" value="1">
            <input type="hidden" id="rcptInvUuid" name="invoice_uuid">
            <input type="hidden" id="rcptSchoolUuid" name="school_uuid">
            <input type="hidden" id="rcptHasCredit" name="has_credit" value="0">
            
            <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[var(--text-secondary)]">Invoice:</span>
                    <strong id="rcptInvNo" class="font-mono text-indigo-400"></strong>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-[var(--text-secondary)]">Amount:</span>
                    <strong id="rcptAmount" class="text-emerald-400 font-bold"></strong>
                </div>
                <div class="flex items-center justify-between mt-1" id="creditBalanceRow" style="display:none;">
                    <span class="text-[var(--text-secondary)]">Available Credit:</span>
                    <strong id="rcptCreditBalance" class="text-blue-400 font-bold"></strong>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Amount Paid</label>
                    <input type="number" name="amount_paid" id="rcptAmountPaid" required min="0" step="0.01" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] font-mono">
                </div>
                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Method</label>
                    <select name="payment_method" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                        <option>Bank Transfer</option>
                        <option>Cash</option>
                        <option>Paystack</option>
                        <option>Flutterwave</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">Confirm Payment</button>
            
            <button type="button" onclick="closeModal('receiptModal')" class="w-full py-2 bg-[var(--bg-tertiary)] hover:bg-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] font-bold text-xs rounded-xl transition">
                Cancel
            </button>
        </form>
    </div>
</div>

<script>
/**
 * Complete Modal Management System
 */
const ModalManager = {
    // Track all open modals
    openModals: [],
    
    /**
     * Open a modal
     * @param {string} modalId - The ID of the modal to open
     */
    open(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Close all modals first
        this.closeAll();
        
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        this.openModals.push(modalId);
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Recreate icons if lucide is available
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    },
    
    /**
     * Close a specific modal
     * @param {string} modalId - The ID of the modal to close
     */
    close(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.classList.add('hidden');
        modal.style.display = '';
        this.openModals = this.openModals.filter(id => id !== modalId);
        
        // Restore body scroll if no modals are open
        if (this.openModals.length === 0) {
            document.body.style.overflow = '';
        }
    },
    
    /**
     * Close all open modals
     */
    closeAll() {
        this.openModals.forEach(id => {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = '';
            }
        });
        this.openModals = [];
        document.body.style.overflow = '';
    },
    
    /**
     * Setup outside click listeners for multiple modals
     * @param {string|array} modalIds - Single modal ID or array of modal IDs
     */
    setupOutsideClick(modalIds) {
        const ids = Array.isArray(modalIds) ? modalIds : [modalIds];
        
        ids.forEach(id => {
            const modal = document.getElementById(id);
            if (!modal) return;
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.close(id);
                }
            });
        });
    },
    
    /**
     * Setup Escape key listener for all modals
     */
    setupEscapeKey() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (this.openModals.length > 0) {
                    // Close the most recently opened modal first (LIFO)
                    const lastModal = this.openModals[this.openModals.length - 1];
                    this.close(lastModal);
                }
            }
        });
    },
    
    /**
     * Initialize the modal system
     * @param {string|array} modalIds - Modal IDs to manage
     */
    init(modalIds) {
        this.setupOutsideClick(modalIds);
        this.setupEscapeKey();
        console.log('✅ Modal system initialized for:', modalIds);
    }
};

// ── Receipt Modal ──────────────────────────────────────────────────────────
function openReceiptModal(button) {
    try {
        const invoice = JSON.parse(button.dataset.invoice);
        
        document.getElementById('rcptInvUuid').value = invoice.invoice_uuid;
        document.getElementById('rcptSchoolUuid').value = invoice.school_uuid || '';
        document.getElementById('rcptInvNo').textContent = invoice.invoice_no;
        document.getElementById('rcptAmount').textContent = '₦' + parseFloat(invoice.amount).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('rcptAmountPaid').value = invoice.amount;
        
        // Handle credit balance display
        const hasCredit = invoice.has_credit || false;
        const creditAmount = invoice.credit_amount || 0;
        document.getElementById('rcptHasCredit').value = hasCredit ? '1' : '0';
        
        const creditRow = document.getElementById('creditBalanceRow');
        if (hasCredit && creditAmount > 0) {
            creditRow.style.display = 'flex';
            document.getElementById('rcptCreditBalance').textContent = '₦' + parseFloat(creditAmount).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            if (parseFloat(invoice.amount) <= creditAmount) {
                document.getElementById('rcptAmountPaid').value = invoice.amount;
            }
        } else {
            creditRow.style.display = 'none';
        }
        
        ModalManager.open('receiptModal');
    } catch (error) {
        console.error('Error opening receipt modal:', error);
        alert('Error loading invoice details. Please try again.');
    }
}

// ── Close Modal (for inline onclick) ──────────────────────────────────────
function closeModal(modalId) {
    ModalManager.close(modalId);
}

// ── Open Modal (for inline onclick) ──────────────────────────────────────
function openModal(modalId) {
    ModalManager.open(modalId);
}

// ── Close all modals ──────────────────────────────────────────────────────
function closeAllModals() {
    ModalManager.closeAll();
}

// ── Toggle modal visibility ──────────────────────────────────────────────
function toggleModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    if (modal.classList.contains('hidden')) {
        ModalManager.open(modalId);
    } else {
        ModalManager.close(modalId);
    }
}

// ── Check if modal is open ───────────────────────────────────────────────
function isModalOpen(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return false;
    return !modal.classList.contains('hidden');
}

// ── Get all open modals ──────────────────────────────────────────────────
function getOpenModals() {
    return ModalManager.openModals;
}

// ── Initialize Modal System ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Initialize receipt modal
    ModalManager.init(['receiptModal']);
    
    // Debug
    console.log('✅ Billing page loaded successfully');
    console.log('📊 Total invoices:', <?php echo $total_invoices ?? 0; ?>);
    console.log('📊 Total receipts:', <?php echo $total_receipts ?? 0; ?>);
    console.log('📌 Available modal functions:');
    console.log('  - ModalManager.open("modalId")');
    console.log('  - ModalManager.close("modalId")');
    console.log('  - ModalManager.closeAll()');
    console.log('  - closeModal("modalId")');
    console.log('  - openModal("modalId")');
    console.log('  - toggleModal("modalId")');
    console.log('  - isModalOpen("modalId")');
});

// ── Keyboard shortcuts ────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    // Ctrl + M to close all modals
    if (e.ctrlKey && e.key === 'm') {
        e.preventDefault();
        ModalManager.closeAll();
    }
});

// ── Close modal on outside click (legacy support) ───────────────────────
document.querySelectorAll('.modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            if (this.style.display) {
                this.style.display = '';
            }
        }
    });
});
</script>