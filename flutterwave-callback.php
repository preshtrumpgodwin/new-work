<?php
/**
 * Flutterwave callback — the browser lands here after checkout. We
 * independently verify the transaction with Flutterwave's server (never
 * trust the client-side redirect alone) before crediting anything.
 * Mirrors paystack-callback.php's verify-then-credit pattern.
 */
require_once __DIR__ . '/config/security.php';
secure_session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/Crypto.php';
require_once __DIR__ . '/admin/lib/Helpers.php';

if (!isset($_SESSION['user_uuid']) || $_SESSION['role'] !== 'Parent') {
    header('Location: login.php'); exit;
}

$reference = trim($_GET['tx_ref'] ?? '');
$fw_status = trim($_GET['status'] ?? '');
$message = ''; $ok = false;

if ($reference === '') {
    $message = 'Missing payment reference.';
} else {
    $txnSt = $pdo->prepare("SELECT * FROM payment_transactions WHERE reference = ? LIMIT 1");
    $txnSt->execute([$reference]);
    $txn = $txnSt->fetch();

    if (!$txn) {
        $message = 'Unrecognized payment reference.';
    } elseif ($txn['status'] === 'Success') {
        $ok = true;
        $message = 'Payment already confirmed. Your receipt has been recorded.';
    } elseif ($fw_status === 'cancelled') {
        $pdo->prepare("UPDATE payment_transactions SET status='Failed' WHERE txn_uuid=?")->execute([$txn['txn_uuid']]);
        $message = 'Payment was cancelled.';
    } else {
        $fwSt = $pdo->prepare("SELECT * FROM flutterwave_settings WHERE school_uuid = ? LIMIT 1");
        $fwSt->execute([$txn['school_uuid']]);
        $fwSettings = $fwSt->fetch();
        if ($fwSettings) {
            $fwSettings['secret_key_enc'] = Crypto::decrypt($fwSettings['secret_key_enc'] ?? '');
        }

        if (empty($fwSettings['secret_key_enc'])) {
            $message = 'Payment gateway is not configured for this school.';
        } else {
            $ch = curl_init('https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . rawurlencode($reference));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $fwSettings['secret_key_enc']],
                CURLOPT_TIMEOUT => 15,
            ]);
            $resp = curl_exec($ch);
            $curlErr = curl_error($ch);
            curl_close($ch);
            $json = $resp ? json_decode($resp, true) : null;

            $pdo->prepare("UPDATE payment_transactions SET gateway_response = ? WHERE txn_uuid = ?")
                ->execute([substr((string)$resp, 0, 2000), $txn['txn_uuid']]);

            $verifiedAmount = $json['data']['amount'] ?? null;
            $verifiedStatus = $json['data']['status'] ?? null;
            $expectedAmount = (float)$txn['amount'];

            if ($verifiedStatus === 'successful' && abs((float)$verifiedAmount - $expectedAmount) < 0.01) {
                try {
                    $pdo->beginTransaction();

                    $pdo->prepare("UPDATE payment_transactions SET status='Success', verified_at=NOW() WHERE txn_uuid=?")
                        ->execute([$txn['txn_uuid']]);

                    $rcp_prefix = 'RCP-' . date('Y') . '-';
                    $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(receipt_no, 10) AS UNSIGNED)) FROM school_receipts WHERE school_uuid=? AND receipt_no LIKE ?");
                    $mx->execute([$txn['school_uuid'], $rcp_prefix . '%']);
                    $rcp_no = $rcp_prefix . str_pad((int)$mx->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

                    $pdo->prepare("INSERT INTO school_receipts (receipt_uuid,school_uuid,receipt_no,invoice_uuid,amount,payment_method,payment_date,transaction_ref,received_by) VALUES (?,?,?,?,?,?,CURDATE(),?,?)")
                        ->execute([uid('rcp'), $txn['school_uuid'], $rcp_no, $txn['invoice_uuid'], $txn['amount'], 'Flutterwave', $reference, 'Online Gateway']);

                    $iq = $pdo->prepare("SELECT amount FROM school_invoices WHERE invoice_uuid=? AND school_uuid=?");
                    $iq->execute([$txn['invoice_uuid'], $txn['school_uuid']]);
                    $invAmount = (float)$iq->fetchColumn();

                    $paidSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
                    $paidSt->execute([$txn['invoice_uuid']]);
                    $totalPaid = (float)$paidSt->fetchColumn();

                    $new_status = $totalPaid >= $invAmount ? 'Paid' : 'Partial';
                    $pdo->prepare("UPDATE school_invoices SET status=? WHERE invoice_uuid=? AND school_uuid=?")
                        ->execute([$new_status, $txn['invoice_uuid'], $txn['school_uuid']]);

                    AuditLog::write($pdo, $txn['school_uuid'], $_SESSION['user_uuid'], 'finance.online_payment', $txn['invoice_uuid'], "Flutterwave payment verified: ₦{$txn['amount']} (ref $reference)");

                    $pdo->commit();
                    $ok = true;
                    $message = "Payment of ₦" . number_format($txn['amount'], 2) . " confirmed! Receipt: $rcp_no";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Flutterwave callback crediting error: ' . $e->getMessage());
                    $message = 'Payment was verified but we hit an error recording your receipt. Please contact the school office with reference ' . $reference . '.';
                }
            } else {
                $pdo->prepare("UPDATE payment_transactions SET status='Failed' WHERE txn_uuid=?")->execute([$txn['txn_uuid']]);
                $message = 'Payment could not be verified. If you were charged, please contact the school office with reference ' . htmlspecialchars($reference) . '.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status — Zetaphase EduCloud</title>
</head>
<body style="background:#0E1117;color:#F1F5F9;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;">
    <div style="max-width:420px;width:100%;padding:2rem;background:#11141B;border:1px solid #1E232D;border-radius:1rem;text-align:center;">
        <h1 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem;color:<?php echo $ok ? '#34d399' : '#fb7185'; ?>;"><?php echo $ok ? 'Payment confirmed' : 'Payment not confirmed'; ?></h1>
        <p style="font-size:0.85rem;color:#94A3B8;margin-bottom:1.5rem;"><?php echo htmlspecialchars($message); ?></p>
        <a href="parent-portal.php" style="display:inline-block;background:#4F46E5;color:white;font-weight:700;padding:.6rem 1.2rem;border-radius:.5rem;text-decoration:none;font-size:0.85rem;">Back to Parent Portal</a>
    </div>
</body>
</html>
