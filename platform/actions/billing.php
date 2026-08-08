<?php
// Fix: Check if session is already active before starting

    require_once __DIR__ . '/../../config/security.php';
    secure_session_start();

if (isset($_POST['action_record_payment'])) {
    $invoice_uuid = trim($_POST['invoice_uuid'] ?? '');
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Bank Transfer');
    $ref = trim($_POST['transaction_ref'] ?? '');
    
    if (!empty($invoice_uuid) && $amount_paid > 0) {
        try {
            $pdo->beginTransaction();
            
            // Fixed: Properly prepare, execute, and fetch
            $stmt = $pdo->prepare("SELECT * FROM school_invoices WHERE invoice_uuid = ?");
            $stmt->execute([$invoice_uuid]);
            $inv = $stmt->fetch();
            
            if (!$inv) {
                throw new Exception('Invoice not found');
            }
            
            $school_uuid = $inv['school_uuid'];
            $invoice_amount = (float)$inv['amount'];
            $overpay = max(0, $amount_paid - $invoice_amount);
            $pay_to_invoice = min($amount_paid, $invoice_amount);

            $receipt_no = 'RCP-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $receipt_uuid = 'rcp-' . uniqid();
            
            // Insert receipt
            $stmt = $pdo->prepare("INSERT INTO school_receipts (receipt_uuid, school_uuid, receipt_no, invoice_uuid, amount, payment_method, transaction_ref) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $receipt_uuid, 
                $school_uuid, 
                $receipt_no, 
                $invoice_uuid, 
                $pay_to_invoice, 
                $method, 
                $ref
            ]);

            // Update invoice status
            if ($pay_to_invoice >= $invoice_amount) {
                $stmt = $pdo->prepare("UPDATE school_invoices SET status = 'Paid' WHERE invoice_uuid = ?");
                $stmt->execute([$invoice_uuid]);
            } else {
                $stmt = $pdo->prepare("UPDATE school_invoices SET status = 'Partial' WHERE invoice_uuid = ?");
                $stmt->execute([$invoice_uuid]);
            }

            // Handle overpayment as credit
            if ($overpay > 0) {
                $stmt = $pdo->prepare("INSERT INTO school_credit_balances (school_uuid, credit_balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE credit_balance = credit_balance + ?");
                $stmt->execute([$school_uuid, $overpay, $overpay]);
            }

            // Reactivate school if suspended
            $stmt = $pdo->prepare("UPDATE schools SET status = 'Active' WHERE school_uuid = ? AND status = 'Suspended'");
            $stmt->execute([$school_uuid]);

            $pdo->commit();
            $_SESSION['flash_success'] = "Payment of ₦" . number_format($amount_paid, 2) . " recorded. Receipt: $receipt_no";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_error'] = safe_error('Payment failed', $e);
        }
    } else {
        $_SESSION['flash_error'] = 'Invalid payment data. Please try again.';
    }
    
    header('Location: index.php?page=billing');
    exit;
}