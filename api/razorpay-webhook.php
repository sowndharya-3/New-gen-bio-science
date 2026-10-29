<?php
declare(strict_types=1);
// Razorpay webhook (configure in the Razorpay dashboard: URL https://<site>/api/razorpay-webhook.php, events
// payment.captured, order.paid, payment.failed, with the secret stored as razorpay_webhook_secret in the server config).
// Every request must carry a valid X-Razorpay-Signature, so only Razorpay can change payment status here.
// This also covers customers who paid but closed the browser before the success callback ran.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/payment-lib.php';
require_method('POST');

$cfg = razorpay_config();
$secret = $cfg['webhook_secret'] ?? '';
if (!$cfg || $secret === '' || str_starts_with($secret, 'CHANGE_ME')) respond(503, ['ok' => false, 'error' => 'Webhook not configured.']);

$raw = (string)file_get_contents('php://input', false, null, 0, 1048576);
$sig = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
if ($sig === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) respond(400, ['ok' => false, 'error' => 'Invalid signature.']);

$evt = json_decode($raw, true);
if (!is_array($evt)) respond(400, ['ok' => false, 'error' => 'Invalid payload.']);
$event = (string)($evt['event'] ?? '');
$pay = $evt['payload']['payment']['entity'] ?? null;
if (!is_array($pay) || !is_string($pay['id'] ?? null) || !is_string($pay['order_id'] ?? null)
    || !preg_match('/^pay_[A-Za-z0-9]{6,30}$/', $pay['id']) || !preg_match('/^order_[A-Za-z0-9]{6,30}$/', $pay['order_id'])) {
    respond(200, ['ok' => true, 'ignored' => true]);            // not a payment event we act on
}

$pdo = db();
try {
    if ($event === 'payment.captured' || $event === 'order.paid') {
        $r = mark_order_paid($pdo, $pay['order_id'], $pay['id'], (int)($pay['amount'] ?? 0), (string)($pay['currency'] ?? ''));
        respond(200, ['ok' => true, 'result' => $r]);
    }
    if ($event === 'payment.failed') {
        $reason = (string)($pay['error_description'] ?? $pay['error_reason'] ?? 'Payment failed');
        record_payment_failure($pdo, $pay['order_id'], $pay['id'], $reason);
        respond(200, ['ok' => true]);
    }
} catch (Throwable $e) {
    error_log('newgen razorpay-webhook: ' . $e->getMessage());
    respond(500, ['ok' => false]);                               // non-2xx makes Razorpay retry later
}
respond(200, ['ok' => true, 'ignored' => true]);
