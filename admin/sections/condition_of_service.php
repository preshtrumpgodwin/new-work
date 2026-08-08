<?php
/**
 * SECTION: Condition of Service
 * 
 * Displays the school's condition of service text.
 * Full-access users (School Admin / Platform Manager) can open a modal to edit it.
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Condition Of Service' => null]);

// ── Permission check ──────────────────────────────────────────────────────────
$can_configure = in_array($active_role, ['School Admin', 'Platform Manager']) 
    || can_approve($active_role, feature_access('condition_of_service'));

// ── Force a fresh fetch of the condition_of_service_text ────────────────────
$cos_text = '';
try {
    $stmt = $pdo->prepare("SELECT condition_of_service_text FROM schools WHERE school_uuid = ? LIMIT 1");
    $stmt->execute([$school_uuid]);
    $cos_text = trim((string)($stmt->fetchColumn() ?? ''));
} catch (Exception $e) {
    $cos_text = '';
}

// ── For debugging (optional): log what was fetched ──────────────────────────
// error_log("Condition of Service text fetched: " . substr($cos_text, 0, 50) . "...");
?>
<!-- SECTION: CONDITION OF SERVICE -->
<?php if ($section === 'condition_of_service'): ?>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-[var(--border-color)]">
            <div>
                <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                    <i data-lucide="file-text" class="w-6 h-6 text-indigo-400"></i>
                    <span>Staff Condition of Service</span>
                </h1>
                <p class="text-xs text-[var(--text-secondary)] mt-1">The official employment code and conditions for staff members.</p>
            </div>
            <?php if ($can_configure): ?>
            <button onclick="openCosModal()"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md flex items-center gap-2">
                <i data-lucide="pencil" class="w-4 h-4"></i>
                Configure
            </button>
            <?php endif; ?>
        </div>

        <!-- Display Area -->
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-8 space-y-4">
            <h3 class="text-base font-bold text-[var(--text-primary)] border-b border-[var(--border-color)] pb-3">
                <?php echo htmlspecialchars($school['name'] ?? 'School'); ?> — Employment Code
            </h3>
            <div class="text-xs leading-relaxed space-y-3 whitespace-pre-line font-mono bg-[var(--bg-tertiary)] p-6 rounded-xl border border-[var(--border-color)] text-[var(--text-primary)]">
                <?php if ($cos_text !== ''): ?>
                    <?php echo nl2br(htmlspecialchars($cos_text)); ?>
                <?php else: ?>
                    <span class="text-[var(--text-secondary)] italic">No condition of service has been set for this school yet.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 🔧 CONFIGURATION MODAL (only rendered if user has access) -->
    <?php if ($can_configure): ?>
    <div id="cosModal" class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl max-w-2xl w-full p-6 space-y-4 shadow-2xl relative">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-[var(--border-color)]">
                <div class="flex items-center gap-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-indigo-400"></i>
                    <h3 class="text-base font-bold text-[var(--text-primary)]">Configure Condition of Service</h3>
                </div>
                <button onclick="closeCosModal()" 
                        class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form method="POST" action="dashboard.php?section=condition_of_service" class="space-y-4" id="cosForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_save_condition_of_service" value="1">

                <div>
                    <label class="block text-[10px] text-[var(--text-secondary)] font-bold uppercase mb-1.5">
                        Condition of Service Text
                    </label>
                    <textarea id="cosEditor" name="condition_of_service_text" rows="12" 
                              class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl p-4 text-xs text-[var(--text-primary)] font-mono leading-relaxed focus:outline-none focus:border-indigo-500 transition"
                              placeholder="Enter the full staff employment terms and conditions here..."><?php echo htmlspecialchars($cos_text); ?></textarea>
                    <p class="text-[9px] text-[var(--text-secondary)] mt-2">The text will be displayed as-is. Use line breaks to format paragraphs.</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeCosModal()"
                            class="px-4 py-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] text-[var(--text-secondary)] hover:text-[var(--text-primary)] text-xs font-bold rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal JavaScript -->
    <script>
    // ── Open the modal ────────────────────────────────────────────────────────
    function openCosModal() {
        const modal = document.getElementById('cosModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    // ── Close the modal ──────────────────────────────────────────────────────
    function closeCosModal() {
        const modal = document.getElementById('cosModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }

    // ── Close on outside click ──────────────────────────────────────────────
    document.getElementById('cosModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeCosModal();
        }
    });

    // ── Close on Escape key ──────────────────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('cosModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeCosModal();
            }
        }
    });
    </script>
    <?php endif; ?>
<?php endif; ?>