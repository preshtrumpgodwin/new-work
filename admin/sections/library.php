<?php
/**
 * SECTION: library — extracted from dashboard.php (Phase 1 refactor)
 */
render_breadcrumb(['Dashboard' => 'dashboard.php?section=overview', 'Library' => null]);
?>
<!-- SECTION: LIBRARY -->
                <?php if ($section === 'library'): ?>
                    <?php
                    $libBooks = []; $libCheckouts = []; $libError = false;
                    try {
                        $libBooks = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT * FROM library_books WHERE school_uuid = ? ORDER BY title ASC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                        $libCheckouts = (function() use ($pdo, $school_uuid) { $__st = $pdo->prepare("SELECT * FROM library_checkouts WHERE school_uuid = ? AND status='Borrowed' ORDER BY due_date ASC"); $__st->execute([$school_uuid]); return $__st->fetchAll(); })();
                    } catch (Exception $e) { $libError = true; }
                    ?>
                    <div class="space-y-6">
                        <h1 class="text-xl font-bold text-[var(--text-primary)] flex items-center space-x-2">
                            <i data-lucide="book-open" class="w-6 h-6 text-amber-500"></i>
                            <span>Library Management</span>
                        </h1>

                        <?php if ($libError): ?>
                            <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-xs text-amber-400">
                                Library tables not found. Run <code class="font-mono">migrate_phase7.php</code> once, then reload this page.
                            </div>
                        <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Book Catalog (<?php echo count($libBooks); ?>)</h3>
                                <form method="POST" class="grid grid-cols-2 gap-2"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_add_book" value="1">
                                    <input type="text" name="book_title" required placeholder="Title" class="col-span-2 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="book_author" placeholder="Author" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="book_isbn" placeholder="ISBN (optional)" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="text" name="book_category" placeholder="Category" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="number" name="book_copies" value="1" min="1" placeholder="Copies" class="bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <button type="submit" class="col-span-2 py-2 bg-amber-600 text-white font-bold text-xs rounded-xl">Add Book</button>
                                </form>
                                <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                                    <?php foreach ($libBooks as $bk): ?>
                                        <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs">
                                            <div class="flex justify-between"><span class="font-bold"><?php echo htmlspecialchars($bk['title']); ?></span><span class="text-[var(--text-secondary)]"><?php echo (int)$bk['available_copies']; ?>/<?php echo (int)$bk['total_copies']; ?> available</span></div>
                                            <span class="text-[var(--text-secondary)]"><?php echo htmlspecialchars($bk['author'] ?: '—'); ?> · <?php echo htmlspecialchars($bk['category']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-2xl p-6 space-y-4">
                                <h3 class="text-sm font-bold text-[var(--text-primary)]">Checkout a Book</h3>
                                <form method="POST" class="space-y-2"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action_checkout_book" value="1">
                                    <select name="book_uuid" required class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="">-- Select book --</option>
                                        <?php foreach ($libBooks as $bk): if ($bk['available_copies'] > 0): ?>
                                            <option value="<?php echo htmlspecialchars($bk['book_uuid']); ?>"><?php echo htmlspecialchars($bk['title']); ?> (<?php echo (int)$bk['available_copies']; ?> left)</option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                    <select name="borrower_type" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                        <option value="Student">Student</option><option value="Staff">Staff</option>
                                    </select>
                                    <input type="text" name="borrower_name" required placeholder="Borrower name" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" class="w-full bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl px-3 py-2 text-xs text-[var(--text-primary)]">
                                    <button type="submit" class="w-full py-2 bg-indigo-600 text-white font-bold text-xs rounded-xl">Checkout</button>
                                </form>
                                <h3 class="text-sm font-bold text-[var(--text-primary)] pt-2 border-t border-[var(--border-color)]">Currently Borrowed (<?php echo count($libCheckouts); ?>)</h3>
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                    <?php foreach ($libCheckouts as $co): ?>
                                        <div class="p-3 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-xl text-xs flex items-center justify-between">
                                            <div><span class="font-bold"><?php echo htmlspecialchars($co['borrower_name']); ?></span><br><span class="text-[var(--text-secondary)]">Due <?php echo htmlspecialchars($co['due_date']); ?></span></div>
                                            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action_return_book" value="1"><input type="hidden" name="checkout_uuid" value="<?php echo htmlspecialchars($co['checkout_uuid']); ?>"><button type="submit" class="px-2.5 py-1 bg-emerald-600/20 text-emerald-400 rounded-lg text-[10px] font-bold">Return</button></form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
