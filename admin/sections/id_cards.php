<?php
/**
 * SECTION: ID Card Designer
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'ID Cards' => null]);

$id_type = safe_str($_GET['type'] ?? 'student');
$search  = safe_str($_GET['q'] ?? '');

$people = [];
try {
    if ($id_type === 'staff') {
        $sql = "SELECT staff_uuid AS uid, name, role AS sub, photo_path, email FROM staff WHERE school_uuid=? AND status='Active'";
        $params = [$school_uuid];
        if ($search !== '') {
            $sql .= " AND name LIKE ?";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY name ASC";
        $q = $pdo->prepare($sql);
    } else {
        $sql = "SELECT student_uuid AS uid, name, CONCAT(class,' ',arm) AS sub, photo_path, admission_number, roll_number FROM students WHERE school_uuid=? AND status='Active'";
        $params = [$school_uuid];
        if ($search !== '') {
            $sql .= " AND name LIKE ?";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY name ASC";
        $q = $pdo->prepare($sql);
    }
    $q->execute($params);
    $people = $q->fetchAll();
} catch (Exception $e) {}

$sel_uid = safe_str($_GET['uid'] ?? '');
$sel = null;
foreach ($people as $p) { if ($p['uid'] === $sel_uid) { $sel = $p; break; } }
if (!$sel && !empty($people)) $sel = $people[0];
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)] flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center gap-2">
                <i data-lucide="badge-check" class="w-5 h-5 text-teal-400"></i> ID Card Designer
            </h1>
            <p class="text-xs text-[var(--text-secondary)] mt-1">Printable ID cards with QR verification code.</p>
        </div>
        <div class="flex bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-xl p-1 text-xs">
            <a href="dashboard.php?section=id_cards&type=student" class="px-3 py-1.5 rounded-lg font-bold <?php echo $id_type==='student'?'bg-teal-600 text-white':'text-[var(--text-secondary)]'; ?>">Students</a>
            <a href="dashboard.php?section=id_cards&type=staff" class="px-3 py-1.5 rounded-lg font-bold <?php echo $id_type==='staff'?'bg-teal-600 text-white':'text-[var(--text-secondary)]'; ?>">Staff</a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden max-h-[600px] overflow-y-auto">
            <div class="p-3 bg-[var(--bg-tertiary)] border-b border-[var(--border-color)] text-xs font-bold uppercase">Select Person</div>
            
            <!-- Search Filter -->
            <div class="p-3 border-b border-[var(--border-color)]">
                <input type="text" id="idSearchFilter" placeholder="Search by name..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       oninput="filterIdList(this.value)"
                       class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)] focus:outline-none focus:border-indigo-500 transition-all">
            </div>

            <?php if (empty($people)): ?>
                <p class="text-xs text-[var(--text-secondary)] p-6 text-center">No <?php echo $id_type; ?> records found.</p>
            <?php endif; ?>
            <?php foreach ($people as $p): ?>
            <a href="dashboard.php?section=id_cards&type=<?php echo $id_type; ?>&q=<?php echo urlencode($search); ?>&uid=<?php echo htmlspecialchars($p['uid']); ?>"
               class="person-item flex items-center gap-3 p-3 border-b border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] <?php echo ($sel && $sel['uid']===$p['uid']) ? 'bg-[var(--bg-tertiary)]' : ''; ?>">
                <div class="w-8 h-8 rounded-full bg-teal-600 flex items-center justify-center text-white text-[10px] font-bold shrink-0"><?php echo strtoupper(substr($p['name'],0,2)); ?></div>
                <div class="min-w-0">
                    <span class="text-xs font-bold text-[var(--text-primary)] block truncate"><?php echo htmlspecialchars($p['name']); ?></span>
                    <span class="text-[10px] text-[var(--text-secondary)] block truncate"><?php echo htmlspecialchars($p['sub']); ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="lg:col-span-2 flex flex-col items-center gap-4">
            <?php if (!$sel): ?>
                <p class="text-xs text-[var(--text-secondary)] py-12">Select someone to preview their ID card.</p>
            <?php else:
                $id_code = buildIdCardCode($pdo, $school_uuid, $id_type, $sel['uid']);
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $verify_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/verify-id.php?code=' . urlencode($id_code);
                $qr_payload = urlencode($verify_url);
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={$qr_payload}";
            ?>
            <div id="idCard" class="w-[340px] rounded-2xl overflow-hidden shadow-2xl border-4" style="border-color:<?php echo htmlspecialchars($school['theme_color'] ?? '#0d9488'); ?>">
                <div class="p-4 text-white text-center" style="background-color:<?php echo htmlspecialchars($school['theme_color'] ?? '#0d9488'); ?>">
                    <span class="text-xs font-black uppercase tracking-wide block"><?php echo htmlspecialchars($school['name'] ?? 'School'); ?></span>
                    <span class="text-[9px] opacity-80 block"><?php echo $id_type === 'staff' ? 'STAFF IDENTITY CARD' : 'STUDENT IDENTITY CARD'; ?></span>
                </div>
                <div class="bg-white p-5 flex flex-col items-center gap-3">
                    <div class="w-20 h-20 rounded-full bg-teal-100 border-2 border-teal-600 flex items-center justify-center text-teal-700 text-xl font-black overflow-hidden">
                        <?php if (!empty($sel['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars(asset_url($sel['photo_path'])); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo strtoupper(substr($sel['name'],0,2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-center">
                        <span class="text-sm font-black text-slate-900 block"><?php echo htmlspecialchars($sel['name']); ?></span>
                        <span class="text-[11px] text-slate-500 block"><?php echo htmlspecialchars($sel['sub']); ?></span>
                        <?php if ($id_type === 'student'): ?>
                            <span class="text-[10px] font-mono text-teal-700 block mt-1"><?php echo htmlspecialchars($sel['admission_number'] ?? $sel['roll_number'] ?? ''); ?></span>
                        <?php endif; ?>
                    </div>
                    <img src="<?php echo $qr_url; ?>" class="w-24 h-24" alt="QR Code">
                    <span class="text-[8px] text-slate-400 font-mono">ID: <?php echo htmlspecialchars($sel['uid']); ?></span>
                    <span class="text-[8px] text-slate-400">Scan to verify, or visit <?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/verify-id.php</span>
                </div>
            </div>
            <button onclick="window.print()" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs rounded-xl print:hidden"><i data-lucide="printer" class="w-4 h-4 inline mr-1"></i> Print This Card</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>@media print { body * { visibility: hidden; } #idCard, #idCard * { visibility: visible; } #idCard { position: fixed; top: 20px; left: 20px; } }</style>

<script>
function filterIdList(query) {
    const items = document.querySelectorAll('.person-item');
    const q = query.toLowerCase().trim();
    items.forEach(item => {
        const name = item.querySelector('.text-xs.font-bold')?.textContent?.toLowerCase() || '';
        // Show if name contains the search term, hide otherwise
        item.style.display = name.includes(q) ? '' : 'none';
    });
}
</script>