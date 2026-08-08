<?php
/**
 * Finance Actions — fee structures, invoices, payment recording, receipts
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

// ── FEE STRUCTURE ─────────────────────────────────────────────────────────────
if (isset($_POST['action_add_fee_structure'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $fee_type   = safe_str($_POST['fee_type']    ?? '');
    $class_name = safe_str($_POST['class_name']  ?? '');
    $term_name  = safe_str($_POST['term_name']   ?? '');
    $amount     = max(0, (float)($_POST['amount']    ?? 0));
    $desc       = safe_str($_POST['description'] ?? '');

    if (empty($fee_type) || $amount <= 0) { $error_msg = 'Fee type and amount are required.'; return; }
    try {
        $uuid = uid('fs');
        $pdo->prepare("INSERT INTO fee_structures (fee_uuid,school_uuid,fee_type,class_name,term_name,amount,description)
            VALUES (?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,$fee_type,$class_name,$term_name,$amount,$desc]);
        AuditLog::write($pdo,$school_uuid,$user_uuid,'finance.fee_structure.add',$uuid,"Added $fee_type ₦$amount");
        $success_msg = "Fee '$fee_type' (₦" . number_format($amount,2) . ") saved!";
    } catch (PDOException $e) {
        // Table may not exist yet — auto-create it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `fee_structures` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `fee_uuid` VARCHAR(50) NOT NULL UNIQUE,
                `school_uuid` VARCHAR(50) NOT NULL,
                `fee_type` VARCHAR(100) NOT NULL,
                `class_name` VARCHAR(50) DEFAULT NULL,
                `term_name` VARCHAR(50) DEFAULT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `description` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_school (`school_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->prepare("INSERT INTO fee_structures (fee_uuid,school_uuid,fee_type,class_name,term_name,amount,description)
                VALUES (?,?,?,?,?,?,?)")
                ->execute([uid('fs'),$school_uuid,$fee_type,$class_name,$term_name,$amount,$desc]);
            $success_msg = "Fee '$fee_type' saved! (Table auto-created)";
        } catch(PDOException $e2) { $error_msg = safe_error('Failed', $e2); }
    }
}

if (isset($_POST['action_delete_fee_structure'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $fee_uuid = safe_str($_POST['fee_uuid'] ?? '');
    try {
        $pdo->prepare("DELETE FROM fee_structures WHERE fee_uuid=? AND school_uuid=?")->execute([$fee_uuid,$school_uuid]);
        $success_msg = 'Fee removed.';
    } catch(Exception $e){ $error_msg = 'Delete failed.'; }
}

// ── CREATE INVOICE ────────────────────────────────────────────────────────────
if (isset($_POST['action_create_invoice'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $plan       = safe_str($_POST['plan']          ?? '');
    $amount     = max(0, (float)($_POST['amount']  ?? 0));
    $due_date   = safe_str($_POST['due_date']      ?? date('Y-m-d', strtotime('+30 days')));
    $cycle      = safe_str($_POST['billing_cycle'] ?? 'One-Time');
    $target     = safe_str($_POST['invoice_target'] ?? 'student'); // 'student' or 'class'
    $student_uuid = safe_str($_POST['student_uuid'] ?? '');
    $class_name   = safe_str($_POST['class_name']   ?? '');
    $session_name = $school_settings['current_session'] ?? '';
    $term_name    = $school_settings['current_term'] ?? '';

    if (empty($plan) || $amount <= 0) { $error_msg = 'Fee description and amount are required.'; return; }
    if ($target === 'student' && empty($student_uuid)) { $error_msg = 'Select a student, or switch to "Whole class".'; return; }
    if ($target === 'class' && empty($class_name)) { $error_msg = 'Select a class to bill.'; return; }

    // Resolve the list of students to invoice
    $targets = [];
    try {
        if ($target === 'student') {
            $s = $pdo->prepare("SELECT student_uuid, class FROM students WHERE student_uuid=? AND school_uuid=?");
            $s->execute([$student_uuid, $school_uuid]);
            if ($row = $s->fetch()) $targets[] = $row;
        } else {
            $s = $pdo->prepare("SELECT student_uuid, class FROM students WHERE class=? AND school_uuid=? AND status='Active'");
            $s->execute([$class_name, $school_uuid]);
            $targets = $s->fetchAll();
        }
    } catch (Exception $e) { $error_msg = safe_error('Could not resolve students', $e); return; }

    if (empty($targets)) { $error_msg = 'No matching student(s) found.'; return; }

    try {
        $prefix = 'INV-' . date('Y') . '-';
        $created = 0;
        foreach ($targets as $t) {
            $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(invoice_no, 10) AS UNSIGNED)) FROM school_invoices WHERE school_uuid=? AND invoice_no LIKE ?");
            $mx->execute([$school_uuid, $prefix . '%']);
            $nextNum = (int)$mx->fetchColumn() + 1;
            $inv_no  = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $uuid = uid('inv');
            $pdo->prepare("INSERT INTO school_invoices (invoice_uuid,school_uuid,student_uuid,invoice_no,plan,amount,due_date,billing_cycle,session_name,term_name,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,'Unpaid')")
                ->execute([$uuid,$school_uuid,$t['student_uuid'],$inv_no,$plan,$amount,$due_date,$cycle,$session_name,$term_name]);
            $created++;
        }
        AuditLog::write($pdo,$school_uuid,$user_uuid,'finance.invoice.create',$uuid ?? '',"$created invoice(s) — $plan — ₦$amount each");
        $success_msg = $created === 1
            ? "Invoice created for ₦" . number_format($amount,2) . "!"
            : "$created invoices created (₦" . number_format($amount,2) . " each) for $class_name!";
    } catch (PDOException $e) { $error_msg = safe_error('Invoice creation failed', $e); }
}

// ── DELETE AN ENTIRE ITEMIZED FEE STRUCTURE ─────────────────────────────────
if (isset($_POST['action_delete_fee_structure_json'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $fee_uuid = safe_str($_POST['fee_uuid'] ?? '');
    $pdo->prepare("DELETE FROM fee_structures WHERE fee_uuid=? AND school_uuid=? AND session_name IS NOT NULL")
        ->execute([$fee_uuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'finance.fee_structure.delete', $fee_uuid, 'Itemized fee structure deleted');
    $success_msg = 'Fee structure deleted. Existing invoices already generated from it are not affected.';
}

// ── FEE STRUCTURE (JSON items per session/term/class) ───────────────────────
if (isset($_POST['action_save_fee_structure_json'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $session = safe_str($_POST['fs_session'] ?? '');
    $term    = safe_str($_POST['fs_term']    ?? '');
    $class   = safe_str($_POST['fs_class']   ?? '');
    $names   = $_POST['item_name']   ?? [];
    $amounts = $_POST['item_amount'] ?? [];

    if (!$session || !$term || !$class) { $error_msg = 'Session, term, and class are required.'; return; }

    $items = [];
    $total = 0;
    foreach ($names as $i => $n) {
        $n = safe_str($n);
        $amt = max(0, (float)($amounts[$i] ?? 0));
        if ($n === '' || $amt <= 0) continue;
        $items[] = ['name' => $n, 'amount' => $amt];
        $total += $amt;
    }
    if (empty($items)) { $error_msg = 'Add at least one fee item with a positive amount.'; return; }

    try {
        $uuid = uid('fs');
        $pdo->prepare("
            INSERT INTO fee_structures (fee_uuid, school_uuid, fee_type, class_name, session_name, term_name, items_json, total_amount, amount)
            VALUES (?, ?, 'Itemized Fee Structure', ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE items_json = VALUES(items_json), total_amount = VALUES(total_amount), amount = VALUES(amount)
        ")->execute([$uuid, $school_uuid, $class, $session, $term, json_encode($items), $total, $total]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'finance.fee_structure.save', $uuid, "Fee structure saved: $class / $term / $session — ₦$total (" . count($items) . " items)");
        $success_msg = "Fee structure saved: ₦" . number_format($total,2) . " total for $class ($term, $session).";
    } catch (Exception $e) { $error_msg = safe_error('Error', $e); }
}

// ── GENERATE INVOICES FROM A SAVED FEE STRUCTURE ────────────────────────────
// Any existing credit balance (from a prior overpayment) is auto-applied
// against the new invoice first, so a family that overpaid last term doesn't
// have to separately ask for a refund or manually offset next term's bill.
if (isset($_POST['action_generate_invoices_from_structure'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $session = safe_str($_POST['gen_session'] ?? '');
    $term    = safe_str($_POST['gen_term']    ?? '');
    $class   = safe_str($_POST['gen_class']   ?? '');
    $due     = safe_str($_POST['gen_due_date'] ?? date('Y-m-d', strtotime('+30 days')));

    $fq = $pdo->prepare("SELECT * FROM fee_structures WHERE school_uuid=? AND session_name=? AND term_name=? AND class_name=? LIMIT 1");
    $fq->execute([$school_uuid, $session, $term, $class]);
    $structure = $fq->fetch();

    if (!$structure || (float)$structure['total_amount'] <= 0) {
        $error_msg = "No fee structure found for $class / $term / $session. Set one up first.";
    } else {
        $items = json_decode($structure['items_json'] ?? '[]', true) ?: [];
        $desc  = implode(', ', array_column($items, 'name')) ?: 'School Fees';
        $amount = (float)$structure['total_amount'];

        $s = $pdo->prepare("SELECT student_uuid FROM students WHERE class=? AND school_uuid=? AND status='Active'");
        $s->execute([$class, $school_uuid]);
        $targets = $s->fetchAll(PDO::FETCH_COLUMN);

        if (empty($targets)) {
            $error_msg = 'No active students found in this class.';
        } else {
            $prefix = 'INV-' . date('Y') . '-';
            $created = 0; $auto_applied_total = 0;
            foreach ($targets as $student_uuid) {
                // Skip if an invoice for this exact fee structure already exists for this student
                $dupe = $pdo->prepare("SELECT id FROM school_invoices WHERE school_uuid=? AND student_uuid=? AND session_name=? AND term_name=? AND plan=? LIMIT 1");
                $dupe->execute([$school_uuid, $student_uuid, $session, $term, $desc]);
                if ($dupe->fetch()) continue;

                $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(invoice_no, 10) AS UNSIGNED)) FROM school_invoices WHERE school_uuid=? AND invoice_no LIKE ?");
                $mx->execute([$school_uuid, $prefix . '%']);
                $inv_no = $prefix . str_pad((int)$mx->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
                $inv_uuid = uid('inv');

                $pdo->prepare("INSERT INTO school_invoices (invoice_uuid,school_uuid,student_uuid,invoice_no,plan,amount,due_date,billing_cycle,session_name,term_name,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,'Unpaid')")
                    ->execute([$inv_uuid,$school_uuid,$student_uuid,$inv_no,$desc,$amount,$due,'Termly',$session,$term]);
                $created++;

                // Auto-apply any existing credit balance
                $cq = $pdo->prepare("SELECT balance FROM student_fee_credits WHERE school_uuid=? AND student_uuid=? LIMIT 1");
                $cq->execute([$school_uuid, $student_uuid]);
                $credit = (float)($cq->fetchColumn() ?: 0);
                if ($credit > 0) {
                    $apply = min($credit, $amount);
                    $rprefix = 'RCP-' . date('Y') . '-';
                    $rmx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(receipt_no, 10) AS UNSIGNED)) FROM school_receipts WHERE school_uuid=? AND receipt_no LIKE ?");
                    $rmx->execute([$school_uuid, $rprefix . '%']);
                    $rcp_no = $rprefix . str_pad((int)$rmx->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
                    $r_uuid = uid('rcp');
                    $pdo->prepare("INSERT INTO school_receipts (receipt_uuid,school_uuid,receipt_no,invoice_uuid,amount,payment_method,payment_date,transaction_ref,received_by)
                        VALUES (?,?,?,?,?,'Credit Balance',CURDATE(),?,?)")
                        ->execute([$r_uuid,$school_uuid,$rcp_no,$inv_uuid,$apply,'Auto-applied credit','System']);
                    $pdo->prepare("UPDATE student_fee_credits SET balance = balance - ? WHERE school_uuid=? AND student_uuid=?")
                        ->execute([$apply, $school_uuid, $student_uuid]);
                    $pdo->prepare("INSERT INTO student_fee_credit_log (school_uuid,student_uuid,change_type,amount,related_invoice_uuid,related_receipt_uuid,note)
                        VALUES (?,?,?,?,?,?,?)")
                        ->execute([$school_uuid, $student_uuid, 'AppliedToInvoice', -$apply, $inv_uuid, $r_uuid, "Auto-applied to $inv_no"]);
                    $new_status = $apply >= $amount ? 'Paid' : 'Partial';
                    $pdo->prepare("UPDATE school_invoices SET status=? WHERE invoice_uuid=?")->execute([$new_status, $inv_uuid]);
                    $auto_applied_total += $apply;
                }
            }
            AuditLog::write($pdo, $school_uuid, $user_uuid, 'finance.invoice.generate_bulk', '', "$created invoice(s) generated for $class — $term $session (₦$amount each)");
            $success_msg = "$created invoice(s) generated for $class (₦" . number_format($amount,2) . " each)"
                . ($auto_applied_total > 0 ? ". ₦" . number_format($auto_applied_total,2) . " in existing credit balances was auto-applied." : '.');
        }
    }
}

// ── APPLY EXISTING CREDIT TO AN INVOICE MANUALLY ────────────────────────────
if (isset($_POST['action_apply_credit'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $inv_uuid = safe_str($_POST['invoice_uuid'] ?? '');
    $iq = $pdo->prepare("SELECT * FROM school_invoices WHERE invoice_uuid=? AND school_uuid=?");
    $iq->execute([$inv_uuid, $school_uuid]);
    $inv = $iq->fetch();
    if (!$inv) { $error_msg = 'Invoice not found.'; return; }

    $paidSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
    $paidSt->execute([$inv_uuid]);
    $remaining = (float)$inv['amount'] - (float)$paidSt->fetchColumn();

    $cq = $pdo->prepare("SELECT balance FROM student_fee_credits WHERE school_uuid=? AND student_uuid=? LIMIT 1");
    $cq->execute([$school_uuid, $inv['student_uuid']]);
    $credit = (float)($cq->fetchColumn() ?: 0);

    if ($remaining <= 0 || $credit <= 0) {
        $error_msg = 'No outstanding balance or no credit available to apply.';
    } else {
        $apply = min($credit, $remaining);
        $rprefix = 'RCP-' . date('Y') . '-';
        $rmx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(receipt_no, 10) AS UNSIGNED)) FROM school_receipts WHERE school_uuid=? AND receipt_no LIKE ?");
        $rmx->execute([$school_uuid, $rprefix . '%']);
        $rcp_no = $rprefix . str_pad((int)$rmx->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
        $r_uuid = uid('rcp');
        $pdo->prepare("INSERT INTO school_receipts (receipt_uuid,school_uuid,receipt_no,invoice_uuid,amount,payment_method,payment_date,transaction_ref,received_by)
            VALUES (?,?,?,?,?,'Credit Balance',CURDATE(),?,?)")
            ->execute([$r_uuid,$school_uuid,$rcp_no,$inv_uuid,$apply,'Manual credit application',$_SESSION['name']??'Staff']);
        $pdo->prepare("UPDATE student_fee_credits SET balance = balance - ? WHERE school_uuid=? AND student_uuid=?")
            ->execute([$apply, $school_uuid, $inv['student_uuid']]);
        $pdo->prepare("INSERT INTO student_fee_credit_log (school_uuid,student_uuid,change_type,amount,related_invoice_uuid,related_receipt_uuid,note)
            VALUES (?,?,?,?,?,?,?)")
            ->execute([$school_uuid, $inv['student_uuid'], 'AppliedToInvoice', -$apply, $inv_uuid, $r_uuid, "Manually applied to {$inv['invoice_no']}"]);
        $new_status = $apply >= $remaining ? 'Paid' : 'Partial';
        $pdo->prepare("UPDATE school_invoices SET status=? WHERE invoice_uuid=?")->execute([$new_status, $inv_uuid]);
        $success_msg = "₦" . number_format($apply,2) . " credit applied to {$inv['invoice_no']}.";
    }
}

// ── PAYSLIP APPROVAL (salary disbursement requires full-access sign-off) ────
if (isset($_POST['action_approve_payslip'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_approve($active_role, feature_access('staff'))) {
        $error_msg = 'Only full-access staff or the school admin can approve payslips.'; return;
    }
    $uuid = safe_str($_POST['payslip_uuid'] ?? '');
    $pdo->prepare("UPDATE staff_payslips SET approval_status='Approved', approved_by=?, approved_at=NOW(), rejection_reason=NULL WHERE payslip_uuid=? AND school_uuid=?")
        ->execute([$_SESSION['name'] ?? $active_role, $uuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'payslip.approve', $uuid, 'Payslip approved for disbursement');
    $success_msg = 'Payslip approved. It can now be marked as disbursed.';
}
if (isset($_POST['action_reject_payslip'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_approve($active_role, feature_access('staff'))) {
        $error_msg = 'Only full-access staff or the school admin can reject payslips.'; return;
    }
    $uuid = safe_str($_POST['payslip_uuid'] ?? '');
    $reason = safe_str($_POST['rejection_reason'] ?? '');
    $pdo->prepare("UPDATE staff_payslips SET approval_status='Rejected', approved_by=?, approved_at=NOW(), rejection_reason=? WHERE payslip_uuid=? AND school_uuid=?")
        ->execute([$_SESSION['name'] ?? $active_role, $reason, $uuid, $school_uuid]);
    AuditLog::write($pdo, $school_uuid, $user_uuid, 'payslip.reject', $uuid, "Payslip rejected" . ($reason ? " — $reason" : ''));
    $success_msg = 'Payslip rejected.';
}
if (isset($_POST['action_mark_payslip_disbursed'])) {
    if (!in_array($active_role, ['School Admin','Platform Manager']) && !can_approve($active_role, feature_access('staff'))) {
        $error_msg = 'Only full-access staff or the school admin can mark salary as disbursed.'; return;
    }
    $uuid = safe_str($_POST['payslip_uuid'] ?? '');
    $chk = $pdo->prepare("SELECT approval_status FROM staff_payslips WHERE payslip_uuid=? AND school_uuid=?");
    $chk->execute([$uuid, $school_uuid]);
    if ($chk->fetchColumn() !== 'Approved') {
        $error_msg = 'This payslip must be Approved before it can be marked as disbursed.';
    } else {
        $pdo->prepare("UPDATE staff_payslips SET disbursed_at=NOW() WHERE payslip_uuid=? AND school_uuid=?")->execute([$uuid, $school_uuid]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'payslip.disburse', $uuid, 'Salary marked as disbursed');
        $success_msg = 'Salary marked as disbursed.';
    }
}

// ── RECORD PAYMENT ────────────────────────────────────────────────────────────
if (isset($_POST['action_record_payment'])) {
    if (!can_manage($active_role, $current_access)) { $error_msg = 'Permission denied.'; return; }
    $inv_uuid  = safe_str($_POST['invoice_uuid']    ?? '');
    $amt_paid  = max(0, (float)($_POST['amount_paid'] ?? 0));
    $method    = safe_str($_POST['payment_method']  ?? 'Cash');
    $ref       = safe_str($_POST['transaction_ref'] ?? '');

    if (!$inv_uuid || $amt_paid <= 0) { $error_msg = 'Invoice and amount are required.'; return; }

    try {
        // Get invoice
        $iq = $pdo->prepare("SELECT * FROM school_invoices WHERE invoice_uuid=? AND school_uuid=? LIMIT 1");
        $iq->execute([$inv_uuid, $school_uuid]);
        $inv = $iq->fetch();
        if (!$inv) { $error_msg = 'Invoice not found.'; return; }

        // Sum of payments already received against this invoice, before this one
        $sumSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
        $sumSt->execute([$inv_uuid]);
        $paidSoFar = (float)$sumSt->fetchColumn();
        $remaining = max(0, (float)$inv['amount'] - $paidSoFar);

        // Overpayment handling: never let more than the outstanding balance
        // count against this invoice. Any excess becomes a credit balance on
        // the student's account, automatically applied to their next invoice.
        $overpay = max(0, $amt_paid - $remaining);
        $amt_applied = $amt_paid - $overpay;

        // Auto receipt number
        $rprefix = 'RCP-' . date('Y') . '-';
        $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(receipt_no, 10) AS UNSIGNED)) FROM school_receipts WHERE school_uuid=? AND receipt_no LIKE ?");
        $mx->execute([$school_uuid, $rprefix . '%']);
        $rNum    = (int)$mx->fetchColumn() + 1;
        $rcp_no  = $rprefix . str_pad($rNum, 4, '0', STR_PAD_LEFT);

        // Insert receipt — records the FULL amount actually received (accounting
        // stays accurate), even though only $amt_applied counts toward this invoice.
        $r_uuid = uid('rcp');
        $pdo->prepare("INSERT INTO school_receipts (receipt_uuid,school_uuid,receipt_no,invoice_uuid,amount,payment_method,payment_date,transaction_ref,received_by)
            VALUES (?,?,?,?,?,?,CURDATE(),?,?)")
            ->execute([$r_uuid,$school_uuid,$rcp_no,$inv_uuid,$amt_paid,$method,$ref,$_SESSION['name']??'Staff']);

        // Update invoice status based on cumulative applied payments, not just this one
        $totalApplied  = $paidSoFar + $amt_applied;
        $new_status = ($totalApplied >= (float)$inv['amount']) ? 'Paid' : 'Partial';
        $pdo->prepare("UPDATE school_invoices SET status=? WHERE invoice_uuid=? AND school_uuid=?")
            ->execute([$new_status,$inv_uuid,$school_uuid]);

        $note = "Receipt $rcp_no — ₦$amt_paid via $method — Invoice {$inv['invoice_no']}";
        if ($overpay > 0) {
            $pdo->prepare("
                INSERT INTO student_fee_credits (school_uuid, student_uuid, balance)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
            ")->execute([$school_uuid, $inv['student_uuid'], $overpay]);
            $pdo->prepare("INSERT INTO student_fee_credit_log (school_uuid,student_uuid,change_type,amount,related_invoice_uuid,related_receipt_uuid,note)
                VALUES (?,?,?,?,?,?,?)")
                ->execute([$school_uuid, $inv['student_uuid'], 'Overpayment', $overpay, $inv_uuid, $r_uuid, "Overpaid {$inv['invoice_no']} by ₦$overpay"]);
            $note .= " (₦$overpay overpaid — credited to student's balance for future invoices)";
        }

        AuditLog::write($pdo,$school_uuid,$user_uuid,'finance.payment',$r_uuid,$note);
        $success_msg = "Payment recorded! Receipt: $rcp_no. Invoice marked $new_status."
            . ($overpay > 0 ? " ₦" . number_format($overpay,2) . " was overpaid and added to this student's credit balance." : '');
    } catch (PDOException $e) { $error_msg = safe_error('Payment failed', $e); }
}

// ── ONE-CLICK: FEE BALANCE REMINDER (moved from misc-actions.php) ─────────────
if (isset($_POST['action_send_fee_reminders']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    // Per-parent outstanding balance = sum of Unpaid/Partial invoices minus receipts paid so far
    $rows = $pdo->prepare("SELECT p.phone, p.name AS parent_name, s.name AS student_name,
            i.invoice_uuid, i.amount, COALESCE((SELECT SUM(r.amount) FROM school_receipts r WHERE r.invoice_uuid=i.invoice_uuid),0) AS paid
        FROM school_invoices i
        JOIN students s ON s.student_uuid = i.student_uuid
        JOIN parents p ON p.parent_uuid = s.parent_uuid
        WHERE i.school_uuid=? AND i.status IN ('Unpaid','Partial') AND p.phone IS NOT NULL AND p.phone != ''");
    $rows->execute([$school_uuid]);
    $debtor_rows = $rows->fetchAll();

    // Group by parent phone, summing each parent's total outstanding balance
    $by_parent = [];
    foreach ($debtor_rows as $r) {
        $balance = (float)$r['amount'] - (float)$r['paid'];
        if ($balance <= 0) continue;
        $key = $r['phone'];
        if (!isset($by_parent[$key])) $by_parent[$key] = ['name' => $r['parent_name'], 'phone' => $r['phone'], 'balance' => 0, 'students' => []];
        $by_parent[$key]['balance'] += $balance;
        if (!in_array($r['student_name'], $by_parent[$key]['students'], true)) $by_parent[$key]['students'][] = $r['student_name'];
    }

    if (empty($by_parent)) {
        $success_msg = 'No outstanding balances found — nothing to send.';
    } else {
        $sent = 0; $last_response = ''; $total_outstanding = 0;
        foreach ($by_parent as $d) {
            $total_outstanding += $d['balance'];
            $students_txt = implode(' & ', $d['students']);
            $msg = "Dear {$d['name']}, kindly note an outstanding balance of ₦" . number_format($d['balance'],2) . " for {$students_txt}. Please settle at your earliest convenience.";
            $r = SMSGateway::send($school_settings, $d['phone'], $msg);
            $last_response = $r['response'];
            if ($r['success']) $sent++;
        }
        $rcount = count($by_parent);
        $uuid = uid('bc');
        $pdo->prepare("INSERT INTO broadcast_messages (broadcast_uuid,school_uuid,channel,recipient_group,message_text,recipient_count,status,sent_by,gateway_response) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uuid,$school_uuid,'SMS','Debtors',"Fee balance reminder (₦" . number_format($total_outstanding,2) . " total outstanding across $rcount parent(s))",$rcount,$sent>0?'Sent':'Failed',$_SESSION['name']??'Admin',$last_response]);
        if ($sent === 0) { $error_msg = "Reminder not delivered — $last_response"; }
        else { $success_msg = "Fee reminder sent to $sent of $rcount debtor parent(s) — ₦" . number_format($total_outstanding,2) . " total outstanding."; }
    }
}

// ── PAYMENT REQUESTS (admin approve/decline, moved from phase5-actions.php) ────
if (isset($_POST['action_resolve_payment_request']) && in_array($active_role, ['School Admin','Platform Manager'])) {
    $req_uuid = safe_str($_POST['request_uuid'] ?? '');
    $decision = safe_str($_POST['decision'] ?? '');
    $note = safe_str($_POST['admin_note'] ?? '');
    if (in_array($decision, ['Approved','Declined'], true)) {
        $pdo->prepare("UPDATE payment_requests SET status=?, admin_note=?, resolved_at=NOW() WHERE request_uuid=? AND school_uuid=?")
            ->execute([$decision, $note, $req_uuid, $school_uuid]);
        AuditLog::write($pdo, $school_uuid, $user_uuid, 'payment_request.resolve', $req_uuid, $decision);
        $success_msg = "Payment request $decision.";
    }
}
