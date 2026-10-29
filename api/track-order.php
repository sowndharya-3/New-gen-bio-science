<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/payment-lib.php';
require_method('POST');

rate_limit('track', 20, 600);

$id = normalize_public_id((string)(read_json()['orderId'] ?? ''));
// 200 (not 404) so a mistyped ID is a normal result, not a failed network request.
$notFound = ['ok' => false, 'error' => 'Order not found. Please check your Order ID and try again.'];
if (!$id) respond(200, $notFound);

$pdo = db();
$q = $pdo->prepare('SELECT id, public_id, created_at, order_status, payment_status, payment_method, razorpay_order_id, total_amount FROM orders WHERE public_id = ?');
$q->execute([$id]);
$o = $q->fetch();
if (!$o) respond(200, $notFound);

$q = $pdo->prepare('SELECT product_name, size_label, qty FROM order_items WHERE order_id = ? ORDER BY id');
$q->execute([$o['id']]);
$items = array_map(fn($r) => ['name' => $r['product_name'], 'size' => $r['size_label'], 'qty' => (int)$r['qty']], $q->fetchAll());

// Deliberately excludes name, phone, email and address.
respond(200, ['ok' => true, 'order' => [
    'orderId'       => $o['public_id'],
    'date'          => gmdate('c', (int)$o['created_at']),
    'items'         => $items,
    'total'         => (int)$o['total_amount'],
    'orderStatus'   => $o['order_status'],
    'paymentStatus' => $o['payment_status'],
    'paymentMethod' => $o['payment_method'],
    // "Pay now" is offered only for an unpaid, uncancelled online order while online payment is configured.
    'canPay'        => $o['payment_method'] === 'razorpay' && $o['razorpay_order_id'] !== null
                       && in_array($o['payment_status'], ['pending', 'failed'], true)
                       && $o['order_status'] !== 'cancelled' && payment_options()['razorpay'],
]]);
