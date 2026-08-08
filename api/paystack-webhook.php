<?php
/**
 * Paystack webhook — server-to-server payment reconciliation.
 *
 * This complements paystack-callback.php (the browser redirect path). The
 * redirect path fails to mark an invoice Paid if the user's browser closes,
 * loses connectivity, or the redirect otherwise never resolves — even though
 * Paystack already collected the money. The webhook is Paystack's
 * recommended production-grade fix: Paystack calls this endpoint directly
 * whenever a transaction event happens, independent of the customer's browser.
 *
 * Register this URL in each school's Paystack dashboard (or platform-level
 * dashboard, if using a shared Paystack account) as:
 *   https://<subdomain>.yourdomain.com/api/paystack-webhook.php
 *
 * Security: Paystack signs every webhook request with an HMAC-SHA512 of the
 * raw request body, keyed with the account's secret key, in the
 * X-Paystack-Signature header. We verify this before trusting anything in
 * the payload — never trust an unsigned or mis-signed webhook call.
 */

require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/Crypto.php';
require_once __DIR__ . '/../admin/lib/Helpers.php';

// This endpoint is called by Paystack's servers, not a logged-in user — do not
// start/require a session here.

http_response_code(200); // Paystack only cares about receiving 200 quickly; we log internally either way.
header('Content-Type: application/json');

function webhook_fail(string $reason): void {
    error_log('Paystack webhook rejected: ' . $reason);
    echo json_encode(['status' => 'ignored', 'reason' => $reason]);
    exit;
}

$raw_body = file_get_contents('php://input');
if ($raw_body === '' || $raw_body === false) {
    webhook_fail('empty body');
}

$payload = json_decode($raw_body, true);
if (!is_array($payload)) {
    webhook_fail('invalid JSON');
}

$event = $payload['event'] ?? '';
$reference = $payload['data']['reference'] ?? '';

if ($reference === '') {
    webhook_fail('no reference in payload');
}

// Look up the transaction to find which school (and therefore which secret
// key) this webhook applies to.
$txnSt = $pdo->prepare("SELECT * FROM payment_transactions WHERE reference = ? LIMIT 1");
$txnSt->execute([$reference]);
$txn = $txnSt->fetch();

if (!$txn) {
    webhook_fail('unrecognized reference: ' . $reference);
}

$psSt = $pdo->prepare("SELECT * FROM school_payment_settings WHERE school_uuid = ? LIMIT 1");
$psSt->execute([$txn['school_uuid']]);
$paySettings = $psSt->fetch();

if (empty($paySettings['paystack_secret_key'])) {
    webhook_fail('no paystack secret configured for school ' . $txn['school_uuid']);
}

$secret_key = Crypto::decrypt($paySettings['paystack_secret_key']);
if ($secret_key === '') {
    webhook_fail('could not decrypt paystack secret for school ' . $txn['school_uuid']);
}

// Verify the signature. Paystack: X-Paystack-Signature = hash_hmac('sha512', raw_body, secret_key)
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$expected_signature = hash_hmac('sha512', $raw_body, $secret_key);

if ($signature === '' || !hash_equals($expected_signature, $signature)) {
    webhook_fail('signature mismatch');
}

// Only act on successful charge events.
if ($event !== 'charge.success') {
    echo json_encode(['status' => 'ignored', 'reason' => 'unhandled event: ' . $event]);
    exit;
}

if ($txn['status'] === 'Success') {
    // Already credited (e.g. via the redirect callback, or a duplicate webhook delivery).
    echo json_encode(['status' => 'already_processed']);
    exit;
}

// Independently verify against Paystack's API too (defense in depth — don't
// trust the webhook payload's amount/status alone, even though it's signed).
$ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
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

$verifiedAmountKobo = $json['data']['amount'] ?? null;
$verifiedStatus = $json['data']['status'] ?? null;
$expectedKobo = (int) round((float)$txn['amount'] * 100);

if ($verifiedStatus !== 'success' || $verifiedAmountKobo !== $expectedKobo) {
    $pdo->prepare("UPDATE payment_transactions SET status='Failed' WHERE txn_uuid=?")->execute([$txn['txn_uuid']]);
    webhook_fail('verify API did not confirm a matching successful charge for ' . $reference);
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
        ->execute([uid('rcp'), $txn['school_uuid'], $rcp_no, $txn['invoice_uuid'], $txn['amount'], 'Paystack', $reference, 'Online Gateway (Webhook)']);

    $iq = $pdo->prepare("SELECT amount FROM school_invoices WHERE invoice_uuid=? AND school_uuid=?");
    $iq->execute([$txn['invoice_uuid'], $txn['school_uuid']]);
    $invAmount = (float)$iq->fetchColumn();

    $paidSt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM school_receipts WHERE invoice_uuid=?");
    $paidSt->execute([$txn['invoice_uuid']]);
    $totalPaid = (float)$paidSt->fetchColumn();

    $new_status = $totalPaid >= $invAmount ? 'Paid' : 'Partial';
    $pdo->prepare("UPDATE school_invoices SET status=? WHERE invoice_uuid=? AND school_uuid=?")
        ->execute([$new_status, $txn['invoice_uuid'], $txn['school_uuid']]);

    // No logged-in user for a webhook — attribute the audit entry to the system.
    AuditLog::write($pdo, $txn['school_uuid'], 'system:paystack-webhook', 'finance.online_payment', $txn['invoice_uuid'], "Paystack payment verified via webhook: ₦{$txn['amount']} (ref $reference)");

    $pdo->commit();
    echo json_encode(['status' => 'credited', 'reference' => $reference, 'receipt' => $rcp_no]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Paystack webhook crediting error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'reference' => $reference]);
}
