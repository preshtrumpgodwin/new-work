<?php
/**
 * Flutterwave webhook — server-to-server payment reconciliation, mirroring
 * api/paystack-webhook.php. Register this URL in each school's Flutterwave
 * dashboard as: https://<subdomain>/api/flutterwave-webhook.php
 *
 * Security: Flutterwave sends a "verif-hash" header equal to the secret
 * hash you configure in your Flutterwave dashboard (Settings > Webhooks) —
 * NOT an HMAC of the body like Paystack. We compare it against the value
 * stored for the school. We also independently re-verify via Flutterwave's
 * API before crediting anything, same as the callback page.
 */
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/Crypto.php';
require_once __DIR__ . '/../admin/lib/Helpers.php';

http_response_code(200);
header('Content-Type: application/json');

function fw_webhook_fail(string $reason): void {
    error_log('Flutterwave webhook rejected: ' . $reason);
    echo json_encode(['status' => 'ignored', 'reason' => $reason]);
    exit;
}

$raw_body = file_get_contents('php://input');
if ($raw_body === '' || $raw_body === false) fw_webhook_fail('empty body');

$payload = json_decode($raw_body, true);
if (!is_array($payload)) fw_webhook_fail('invalid JSON');

$reference = $payload['data']['tx_ref'] ?? '';
if ($reference === '') fw_webhook_fail('no tx_ref in payload');

$txnSt = $pdo->prepare("SELECT * FROM payment_transactions WHERE reference = ? LIMIT 1");
$txnSt->execute([$reference]);
$txn = $txnSt->fetch();
if (!$txn) fw_webhook_fail('unrecognized reference: ' . $reference);

$fwSt = $pdo->prepare("SELECT * FROM flutterwave_settings WHERE school_uuid = ? LIMIT 1");
$fwSt->execute([$txn['school_uuid']]);
$fwSettings = $fwSt->fetch();
if (empty($fwSettings['secret_key_enc'])) fw_webhook_fail('no flutterwave secret configured for school ' . $txn['school_uuid']);

$secret_key = Crypto::decrypt($fwSettings['secret_key_enc']);
if ($secret_key === '') fw_webhook_fail('could not decrypt flutterwave secret for school ' . $txn['school_uuid']);

// Flutterwave's "secret hash" webhook check — compare against the school's
// own secret key as a shared secret (schools set the same value in their
// Flutterwave dashboard's webhook secret-hash field). Defense-in-depth: we
// re-verify via the API below regardless of this check's outcome.
$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
if ($signature === '' || !hash_equals($secret_key, $signature)) {
    // Don't hard-fail solely on this — some schools may not have set the
    // dashboard secret hash yet. Log and continue to the independent verify.
    error_log('Flutterwave webhook: verif-hash missing or mismatched for ' . $reference . ' — continuing to independent verify.');
}

if ($txn['status'] === 'Success') {
    echo json_encode(['status' => 'already_processed']);
    exit;
}

// Independently verify via Flutterwave's API — never trust the payload alone.
$ch = curl_init('https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . rawurlencode($reference));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret_key],
    CURLOPT_TIMEOUT => 15,
]);
$resp = curl_exec($ch);
curl_close($ch);
$json = $resp ? json_decode($resp, true) : null;

$pdo->prepare("UPDATE payment_transactions SET gateway_response = ? WHERE txn_uuid = ?")
    ->execute([substr((string)$resp, 0, 2000), $txn['txn_uuid']]);

$verifiedAmount = $json['data']['amount'] ?? null;
$verifiedStatus = $json['data']['status'] ?? null;
$expectedAmount = (float)$txn['amount'];

if ($verifiedStatus !== 'successful' || abs((float)$verifiedAmount - $expectedAmount) >= 0.01) {
    $pdo->prepare("UPDATE payment_transactions SET status='Failed' WHERE txn_uuid=?")->execute([$txn['txn_uuid']]);
    fw_webhook_fail('verify API did not confirm a matching successful charge for ' . $reference);
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE payment_transactions SET status='Success', verified_at=NOW() WHERE txn_uuid=?")
        ->execute([$txn['txn_uuid']]);

    $rcp_prefix = 'RCP-' . date('Y') . '-';
    $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(receipt_no, 10) AS UNSIGNED)) FROM school_receipts WHERE school_uuid=? AND receipt_no LIKE ?");
    $mx->execute([$txn['school_uuid'], $rcp_prefix . '%']);
    $rcp_no = $rcp_prefix . str_pad((int)$mx->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO school_receipts (receipt_uuid,school_uuid,receipt_no,invoice_uuid,amount,payment_method,payment_date,transaction_ref,received_by) VALUES (?,?,?,?,?,?,CURDATE(),?,?)")
        ->execute([uid('rcp'), $txn['school_uuid'], $rcp_no, $txn['invoice_uuid'], $txn['amount'], 'Flutterwave', $reference, 'Online Gateway (Webhook)']);

    $iq = $pdo->prepare("SELECT amount FROM school_invoices WHERE invoice_uuid=? AND school_uuid=?");
    $iq->execute([$txn['invoice_uuid'], $txn['school_uuid']]);
    $invAmount = (float)$iq->fetchColumn();

    $paidSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
    $paidSt->execute([$txn['invoice_uuid']]);
    $totalPaid = (float)$paidSt->fetchColumn();

    $new_status = $totalPaid >= $invAmount ? 'Paid' : 'Partial';
    $pdo->prepare("UPDATE school_invoices SET status=? WHERE invoice_uuid=? AND school_uuid=?")
        ->execute([$new_status, $txn['invoice_uuid'], $txn['school_uuid']]);

    AuditLog::write($pdo, $txn['school_uuid'], 'system:flutterwave-webhook', 'finance.online_payment', $txn['invoice_uuid'], "Flutterwave payment verified via webhook: ₦{$txn['amount']} (ref $reference)");

    $pdo->commit();
    echo json_encode(['status' => 'credited', 'reference' => $reference, 'receipt' => $rcp_no]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Flutterwave webhook crediting error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'reference' => $reference]);
}
