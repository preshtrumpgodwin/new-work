<?php
/**
 * Actions: Library Management (admin/sections/library.php)
 * Split out of the old misc-actions.php grouping.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

if (isset($_POST['action_add_book'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('library')))) { $error_msg = 'You do not have permission to manage the library.'; return; }
    $title  = safe_str($_POST['book_title']    ?? '');
    $author = safe_str($_POST['book_author']   ?? '');
    $isbn   = safe_str($_POST['book_isbn']     ?? '');
    $cat    = safe_str($_POST['book_category'] ?? 'General');
    $copies = max(1, safe_int($_POST['book_copies'] ?? 1));
    if ($title) {
        try {
            $uuid = uid('bk');
            $pdo->prepare("INSERT INTO library_books (book_uuid,school_uuid,title,author,isbn,category,total_copies,available_copies) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$uuid,$school_uuid,$title,$author,$isbn,$cat,$copies,$copies]);
            $success_msg = "Book '$title' added!";
        } catch (PDOException $e) { $error_msg = 'Library tables not found — run migration first.'; }
    }
}
if (isset($_POST['action_checkout_book'])) {
    if (!(in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('library')))) { $error_msg = 'You do not have permission to manage the library.'; return; }
    $bk_uuid  = safe_str($_POST['book_uuid']     ?? '');
    $borrower = safe_str($_POST['borrower_name'] ?? '');
    $btype    = safe_str($_POST['borrower_type'] ?? 'Student');
    $due      = safe_str($_POST['due_date']       ?? date('Y-m-d', strtotime('+14 days')));
    if ($bk_uuid && $borrower) {
        $av = $pdo->prepare("SELECT available_copies FROM library_books WHERE book_uuid=? AND school_uuid=?");
        $av->execute([$bk_uuid,$school_uuid]);
        if ((int)$av->fetchColumn() > 0) {
            $uuid = uid('co');
            $pdo->prepare("INSERT INTO library_checkouts (checkout_uuid,school_uuid,book_uuid,borrower_type,borrower_name,checkout_date,due_date,status) VALUES (?,?,?,?,?,CURDATE(),?,'Borrowed')")
                ->execute([$uuid,$school_uuid,$bk_uuid,$btype,$borrower,$due]);
            $pdo->prepare("UPDATE library_books SET available_copies=available_copies-1 WHERE book_uuid=? AND school_uuid=?")->execute([$bk_uuid,$school_uuid]);
            $success_msg = "Checked out to $borrower!";
        } else { $error_msg = 'No copies available.'; }
    }
}
if (isset($_POST['action_return_book']) && (in_array($active_role, ['School Admin','Platform Manager']) || can_manage($active_role, feature_access('library')))) {
    $co_uuid = safe_str($_POST['checkout_uuid'] ?? '');
    $st = $pdo->prepare("SELECT book_uuid FROM library_checkouts WHERE checkout_uuid=? AND school_uuid=? AND status='Borrowed'");
    $st->execute([$co_uuid,$school_uuid]);
    $bk = $st->fetchColumn();
    if ($bk) {
        $pdo->prepare("UPDATE library_checkouts SET status='Returned',return_date=CURDATE() WHERE checkout_uuid=? AND school_uuid=?")->execute([$co_uuid,$school_uuid]);
        $pdo->prepare("UPDATE library_books SET available_copies=available_copies+1 WHERE book_uuid=? AND school_uuid=?")->execute([$bk,$school_uuid]);
        $success_msg = 'Book returned!';
    }
}
