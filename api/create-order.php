<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/payment-lib.php';
require_method('POST');

$in = read_json();

function clean(mixed $v, int $min, int $max): ?string {
    if (!is_string($v)) return null;
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    $len = mb_strlen($v);
    return ($len >= $min && $len <= $max) ? $v : null;
}

$name    = clean($in['customerName'] ?? null, 2, 100);
$email   = clean($in['email'] ?? null, 5, 150);
$address = clean($in['address'] ?? null, 5, 500);
$city    = clean($in['city'] ?? null, 2, 80);
$state   = clean($in['state'] ?? null, 2, 80);
$pincode = clean($in['pincode'] ?? null, 6, 6);
$mobile  = preg_replace('/[\s\-]/', '', (string)($in['mobile'] ?? ''));
$mobile  = preg_replace('/^(\+91|91|0)(?=\d{10}$)/', '', $mobile);
$method  = $in['paymentMethod'] ?? null;
$token   = $in['clientToken'] ?? null;

$errors = [];
if (!$name) $errors[] = 'Please enter your full name.';
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) $errors[] = 'Please enter a valid 10-digit mobile number.';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
if (!$address) $errors[] = 'Please enter your delivery address.';
if (!$city) $errors[] = 'Please enter your city.';
if (!$state) $errors[] = 'Please enter your state.';
if (!$pincode || !preg_match('/^\d{6}$/', $pincode)) $errors[] = 'Please enter a valid 6-digit pincode.';
if (!is_string($method) || !in_array($method, CHECKOUT_METHODS, true)) {
    $errors[] = 'Please choose a payment method.';
} elseif ($method === 'razorpay' && !payment_options()['razorpay']) {
    $errors[] = 'Online payment is not available right now. Please choose Cash on Delivery.';
}
if ($token !== null && (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token))) $errors[] = 'Invalid request. Please reload the page.';

// Items: re-priced from the products/product_variants tables; browser prices are ignored.
$catalog = [];
foreach (db()->query('SELECT p.slug, p.name, v.label, v.price FROM products p
                      JOIN product_variants v ON v.product_id = p.id WHERE p.is_active = 1 AND p.deleted_at IS NULL') as $r) {
    $catalog[$r['slug']]['name'] = $r['name'];
    $catalog[$r['slug']]['sizes'][$r['label']] = (int)$r['price'];
}
$lines = [];
$rawItems = $in['items'] ?? null;
if (!is_array($rawItems) || !$rawItems || count($rawItems) > 50) {
    $errors[] = 'Your cart is empty or invalid.';
} else {
    foreach ($rawItems as $it) {
        $pid  = is_array($it) ? ($it['productId'] ?? null) : null;
        $size = is_array($it) ? ($it['size'] ?? null) : null;
        $qty  = is_array($it) ? ($it['qty'] ?? null) : null;
        if (!is_string($pid) || !is_string($size) || !is_int($qty) || $qty < 1 || $qty > 1000
            || !isset($catalog[$pid]['sizes'][$size])) {
            $errors[] = 'One or more items in your cart are no longer available. Please review your cart.';
            break;
        }
        $key = $pid . '|' . $size;
        $lines[$key]['qty'] = ($lines[$key]['qty'] ?? 0) + $qty;
        $lines[$key]['pid'] = $pid;
        $lines[$key]['size'] = $size;
    }
}
if ($errors) respond(422, ['ok' => false, 'error' => $errors[0], 'errors' => $errors]);

$pdo = db();

// Response for an order that exists (new, or returned again for a repeated submit of the same checkout).
function order_response(array $o, bool $duplicate): array {
    $out = ['ok' => true, 'orderId' => $o['public_id'], 'total' => (int)$o['total_amount'],
            'paymentMethod' => $o['payment_method'], 'paymentStatus' => $o['payment_status'], 'duplicate' => $duplicate];
    if ($o['payment_method'] === 'razorpay' && $o['payment_status'] !== 'paid' && $o['razorpay_order_id'] && razorpay_config()) {
        $out['razorpay'] = razorpay_checkout_params($o);
    }
    return $out;
}
function find_by_token(PDO $pdo, string $token): ?array {
    $q = $pdo->prepare('SELECT * FROM orders WHERE client_token = ?');
    $q->execute([$token]);
    return $q->fetch() ?: null;
}

// Same checkout submitted twice (double click, retry after a slow response): return the first order.
if ($token !== null && ($existing = find_by_token($pdo, $token))) respond(200, order_response($existing, true));

rate_limit('create', 10, 3600);

$total = 0;
foreach ($lines as &$l) {
    $l['name']  = $catalog[$l['pid']]['name'];
    $l['unit']  = $catalog[$l['pid']]['sizes'][$l['size']];
    $l['total'] = $l['unit'] * $l['qty'];
    $total += $l['total'];
}
unset($l);

// Online payment: the Razorpay order (with the server-computed amount) is created first, so a
// gateway failure never leaves a half-created local order behind.
$rzpOrderId = null;
if ($method === 'razorpay') {
    try {
        $rzpOrderId = razorpay_create_order($total, 'NG' . bin2hex(random_bytes(8)));
    } catch (Throwable $e) {
        respond(502, ['ok' => false, 'error' => 'Online payment could not be started. Please try again, or choose Cash on Delivery. Your cart has been kept.']);
    }
}

$now = time();
try {
    $pdo->beginTransaction();
    $publicId = null;
    for ($attempt = 0; $attempt < 5 && !$publicId; $attempt++) {
        $candidate = new_public_id();
        $dup = $pdo->prepare('SELECT 1 FROM orders WHERE public_id = ?');
        $dup->execute([$candidate]);
        if ($dup->fetchColumn()) continue;                       // UNIQUE index is the final guard
        $pdo->prepare('INSERT INTO orders (public_id, created_at, updated_at, customer_name, mobile, email, address, city, state, pincode, total_amount, order_status, payment_status, payment_method, razorpay_order_id, client_token)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$candidate, $now, $now, $name, $mobile, $email, $address, $city, $state, $pincode, $total, 'received', 'pending', $method, $rzpOrderId, $token]);
        $publicId = $candidate;
    }
    if (!$publicId) throw new RuntimeException('could not allocate order id');
    $orderId = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, size_label, unit_price, qty, line_total) VALUES (?,?,?,?,?,?,?)');
    foreach ($lines as $l) $ins->execute([$orderId, $l['pid'], $l['name'], $l['size'], $l['unit'], $l['qty'], $l['total']]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_at) VALUES (?,?,?)')->execute([$orderId, 'received', $now]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Two simultaneous submits of the same checkout: the unique token makes the loser return the winner's order.
    if (is_duplicate_key($e) && $token !== null && ($existing = find_by_token($pdo, $token))) respond(200, order_response($existing, true));
    error_log('newgen create-order: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'We could not save your order. Your cart has been kept, please try again.']);
}

$q = $pdo->prepare('SELECT * FROM orders WHERE public_id = ?');
$q->execute([$publicId]);
respond(201, order_response($q->fetch(), false));
