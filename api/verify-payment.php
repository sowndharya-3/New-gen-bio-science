<?php
declare(strict_types=1);
// Public: called by the browser after Razorpay Checkout reports success. The order is marked paid ONLY if the
// Razorpay signature verifies with the secret key that exists only on the server. Reaching a "success" page,
// or sending any other data, never changes the payment status. Safe to call repeatedly.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/payment-lib.php';
require_method('POST');

rate_limit('verify', 40, 600);

$in = read_json();
$id = normalize_public_id((string)($in['orderId'] ?? ''));
$rzpOrder = $in['razorpayOrderId'] ?? null;
$rzpPayment = $in['razorpayPaymentId'] ?? null;
$sig = $in['razorpaySignature'] ?? null;
$bad = ['ok' => false, 'error' => 'We could not verify this payment. If money was deducted, it will be reconciled automatically or contact us with your Order ID.'];

$cfg = razorpay_config();
if (!$cfg) respond(503, ['ok' => false, 'error' => 'Online payment is not available right now.']);
if (!$id || !is_string($rzpOrder) || !is_string($rzpPayment) || !is_string($sig)
    || !preg_match('/^order_[A-Za-z0-9]{6,30}$/', $rzpOrder) || !preg_match('/^pay_[A-Za-z0-9]{6,30}$/', $rzpPayment)
    || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
    respond(400, $bad);
}

$pdo = db();
$q = $pdo->prepare('SELECT id, razorpay_order_id, total_amount, payment_status, payment_method FROM orders WHERE public_id = ?');
$q->execute([$id]);
$o = $q->fetch();
if (!$o || $o['payment_method'] !== 'razorpay' || $o['razorpay_order_id'] !== $rzpOrder
    || !razorpay_signature_ok($rzpOrder, $rzpPayment, $sig, $cfg['key_secret'])) {
    error_log('newgen verify-payment: rejected for ' . ($id ?? 'invalid id'));
    respond(400, $bad);
}

// The signature proves Razorpay accepted a payment against OUR order, whose amount the server fixed at checkout.
try {
    $result = mark_order_paid($pdo, $rzpOrder, $rzpPayment, (int)$o['total_amount'] * 100, 'INR');
} catch (Throwable $e) {
    error_log('newgen verify-payment: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'We could not record your payment right now. Please contact us with your Order ID.']);
}
respond(200, ['ok' => true, 'paymentStatus' => 'paid', 'alreadyRecorded' => $result === 'already']);
