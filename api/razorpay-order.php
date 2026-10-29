<?php
declare(strict_types=1);
// Public: Razorpay Checkout parameters for an existing, unpaid ONLINE order (the "Pay now" button and
// retries after a closed or failed payment window). Reuses the Razorpay order created at checkout, so
// every attempt pays the same server-fixed amount. The Order ID acts as the customer's reference.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/payment-lib.php';
require_method('POST');

rate_limit('pay', 30, 600);

$id = normalize_public_id((string)(read_json()['orderId'] ?? ''));
$notAvailable = ['ok' => false, 'error' => 'Online payment is not available for this order.'];
if (!$id || !payment_options()['razorpay']) respond(200, $notAvailable);

$q = db()->prepare('SELECT * FROM orders WHERE public_id = ?');
$q->execute([$id]);
$o = $q->fetch();
if (!$o || $o['payment_method'] !== 'razorpay' || !$o['razorpay_order_id']
    || !in_array($o['payment_status'], ['pending', 'failed'], true) || $o['order_status'] === 'cancelled') {
    respond(200, $notAvailable);
}
respond(200, ['ok' => true, 'orderId' => $o['public_id'], 'razorpay' => razorpay_checkout_params($o)]);
