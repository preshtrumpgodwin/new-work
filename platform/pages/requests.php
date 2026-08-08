<?php
$requests = $pdo->query("SELECT * FROM onboarding_requests ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Onboarding Requests</h1>
        <span class="px-3 py-1 bg-indigo-500/10 text-indigo-400 rounded-full text-xs font-bold">
            <?php echo count($requests); ?> Total
        </span>
    </div>
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[var(--bg-tertiary)] text-[var(--text-secondary)] uppercase font-mono text-[10px] border-b border-[var(--border-color)]">
                    <tr>
                        <th class="px-3.5 py-3">School</th>
                        <th class="px-3.5 py-3">Subdomain</th>
                        <th class="px-3.5 py-3">Contact</th>
                        <th class="px-3.5 py-3">Plan</th>
                        <th class="px-3.5 py-3">Status</th>
                        <th class="px-3.5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    <?php foreach ($requests as $req): 
                        $safeSchoolName = htmlspecialchars($req['school_name'], ENT_QUOTES, 'UTF-8');
                        $safeSubdomain = htmlspecialchars($req['subdomain'], ENT_QUOTES, 'UTF-8');
                        $safeContactName = htmlspecialchars($req['contact_name'], ENT_QUOTES, 'UTF-8');
                        $safeEmail = htmlspecialchars($req['email'], ENT_QUOTES, 'UTF-8');
                        $safePlan = htmlspecialchars($req['plan'], ENT_QUOTES, 'UTF-8');
                        $safeStatus = htmlspecialchars($req['status'], ENT_QUOTES, 'UTF-8');
                        $safePhone = htmlspecialchars($req['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                        $safeRole = htmlspecialchars($req['applicant_role'] ?? 'School Admin', ENT_QUOTES, 'UTF-8');
                        $safeCycle = htmlspecialchars($req['billing_cycle'] ?? 'Monthly', ENT_QUOTES, 'UTF-8');
                        $safeStudents = htmlspecialchars($req['student_count'] ?? '150', ENT_QUOTES, 'UTF-8');
                        $safeDate = htmlspecialchars($req['request_date'] ?? '—', ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="hover:bg-[var(--bg-tertiary)]/50">
                        <td class="px-3.5 py-3 font-bold text-[var(--text-primary)]"><?php echo $safeSchoolName; ?></td>
                        <td class="px-3.5 py-3 font-mono text-indigo-400"><?php echo $safeSubdomain; ?>.zetaphase.com.ng</td>
                        <td class="px-3.5 py-3">
                            <div class="font-medium text-[var(--text-primary)]"><?php echo $safeContactName; ?></div>
                            <div class="text-[10px] text-[var(--text-secondary)] font-mono"><?php echo $safeEmail; ?></div>
                        </td>
                        <td class="px-3.5 py-3">
                            <span class="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 rounded font-mono font-bold"><?php echo $safePlan; ?></span>
                        </td>
                        <td class="px-3.5 py-3">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold 
                                <?php echo $req['status']==='Approved'?'bg-emerald-500/10 text-emerald-400':($req['status']==='Pending'?'bg-amber-500/10 text-amber-400':'bg-rose-500/10 text-rose-400'); ?>">
                                <?php echo $safeStatus; ?>
                            </span>
                        </td>
                        <td class="px-3.5 py-3">
                            <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                <?php if ($req['status'] === 'Pending'): ?>
                                <button onclick="openPreviewModal(this)" 
                                        data-request='<?php echo json_encode([
                                            'id' => $req['id'],
                                            'school_name' => $req['school_name'],
                                            'subdomain' => $req['subdomain'],
                                            'contact_name' => $req['contact_name'],
                                            'email' => $req['email'],
                                            'phone' => $req['phone'] ?? '',
                                            'applicant_role' => $req['applicant_role'] ?? 'School Admin',
                                            'plan' => $req['plan'],
                                            'billing_cycle' => $req['billing_cycle'] ?? 'Monthly',
                                            'student_count' => $req['student_count'] ?? '150',
                                            'request_date' => $req['request_date'] ?? '—'
                                        ]); ?>'
                                        class="px-2.5 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 hover:bg-indigo-500/20 rounded-lg text-[10px] font-bold whitespace-nowrap transition">Preview</button>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Approve this request? This will create the school and send login credentials.')"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_approve" value="<?php echo $req['id']; ?>">
                                    <button class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[10px] font-bold whitespace-nowrap transition">Approve</button>
                                </form>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Reject this request?')"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_reject" value="<?php echo $req['id']; ?>">
                                    <button class="px-2.5 py-1 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-[10px] font-bold whitespace-nowrap transition">Reject</button>
                                </form>
                                <?php else: ?>
                                <span class="text-[var(--text-secondary)] text-[10px] font-mono">Processed</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="6" class="px-3.5 py-8 text-center text-[var(--text-secondary)] text-xs">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-[var(--text-secondary)] opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span>No onboarding requests found</span>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Preview Modal - Centered -->
<div id="previewModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-lg w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto relative">
        <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
            <h3 class="text-base font-bold text-[var(--text-primary)]">Request Details</h3>
            <button onclick="closeModal('previewModal')" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xl transition p-1 rounded hover:bg-[var(--bg-tertiary)]">✕</button>
        </div>
        <div id="previewContent" class="grid grid-cols-2 gap-4 text-xs">
            <!-- filled by JS -->
        </div>
        <div class="flex gap-3 pt-3 border-t border-[var(--border-color)]">
            <form method="POST" class="flex-1"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_approve" id="previewApproveId">
                <button class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition">Approve</button>
            </form>
            <form method="POST" class="flex-1"><?php echo csrf_field(); ?>
                <input type="hidden" name="action_reject" id="previewRejectId">
                <button class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl transition">Reject</button>
            </form>
        </div>
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

// ── Preview Modal ──────────────────────────────────────────────────────────
function openPreviewModal(button) {
    try {
        const request = JSON.parse(button.dataset.request);
        
        document.getElementById('previewApproveId').value = request.id;
        document.getElementById('previewRejectId').value = request.id;
        
        document.getElementById('previewContent').innerHTML = `
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">School</div>
                <div class="font-bold text-[var(--text-primary)]">${request.school_name || '—'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Subdomain</div>
                <div class="font-mono text-indigo-400">${request.subdomain || '—'}.zetaphase.com.ng</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Contact</div>
                <div class="font-bold text-[var(--text-primary)]">${request.contact_name || '—'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Email</div>
                <div class="font-mono text-[var(--text-primary)]">${request.email || '—'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Phone</div>
                <div class="font-mono text-[var(--text-primary)]">${request.phone || '—'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Role</div>
                <div class="text-[var(--text-primary)]">${request.applicant_role || 'School Admin'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Plan</div>
                <div class="text-[var(--text-primary)] font-bold">${request.plan || 'Standard'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Billing Cycle</div>
                <div class="text-[var(--text-primary)]">${request.billing_cycle || 'Monthly'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Students</div>
                <div class="text-[var(--text-primary)] font-bold">${request.student_count || '150'}</div>
            </div>
            <div class="bg-[var(--bg-tertiary)] p-3 rounded-xl">
                <div class="text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1">Requested</div>
                <div class="text-[var(--text-primary)] font-mono">${request.request_date || '—'}</div>
            </div>
        `;
        
        ModalManager.open('previewModal');
    } catch (error) {
        console.error('Error opening preview modal:', error);
        alert('Error loading request details. Please try again.');
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
    // Initialize preview modal
    ModalManager.init(['previewModal']);
    
    // Debug
    console.log('✅ Onboarding page loaded successfully');
    console.log('📊 Total requests:', <?php echo count($requests); ?>);
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

// ── Additional utility for approval/rejection ────────────────────────────
function confirmAction(message) {
    return confirm(message);
}

// ── Auto-close modals on form submission ──────────────────────────────────
document.querySelectorAll('#previewModal form').forEach(form => {
    form.addEventListener('submit', function() {
        // Close modal after form submission
        setTimeout(() => {
            ModalManager.close('previewModal');
        }, 500);
    });
});
</script>