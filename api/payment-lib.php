<?php
declare(strict_types=1);
// Shared payment helpers. Not an endpoint (blocked by .htaccess); loaded by the payment endpoints.
// Razorpay credentials live ONLY in the server-side config file, never in the website or the database.

function razorpay_config(): ?array {
    $c = config();
    $id = (string)($c['razorpay_key_id'] ?? '');
    $secret = (string)($c['razorpay_key_secret'] ?? '');
    if ($id === '' || $secret === '' || str_starts_with($id, 'CHANGE_ME') || str_starts_with($secret, 'CHANGE_ME')) return null;
    return [
        'key_id'         => $id,
        'key_secret'     => $secret,
        'webhook_secret' => (string)($c['razorpay_webhook_secret'] ?? ''),
        'api_base'       => rtrim((string)($c['razorpay_api_base'] ?? 'https://api.razorpay.com'), '/'),
    ];
}

// What the checkout may offer. COD is always available; online payment only when the server has keys.
function payment_options(): array {
    return ['cod' => true, 'razorpay' => razorpay_config() !== null];
}

// Creates the Razorpay order for $amountRupees and returns its id. The amount is fixed server-side.
function razorpay_create_order(int $amountRupees, string $receipt): string {
    $cfg = razorpay_config();
    if (!$cfg) throw new RuntimeException('razorpay not configured');
    if (!function_exists('curl_init')) throw new RuntimeException('curl extension missing');
    $ch = curl_init($cfg['api_base'] . '/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['amount' => $amountRupees * 100, 'currency' => 'INR', 'receipt' => $receipt]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => $cfg['key_id'] . ':' . $cfg['key_secret'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = is_string($res) ? json_decode($res, true) : null;
    if ($code !== 200 || !is_array($d) || !preg_match('/^order_[A-Za-z0-9]{6,30}$/', (string)($d['id'] ?? ''))
        || (int)($d['amount'] ?? 0) !== $amountRupees * 100) {
        error_log('newgen razorpay create order failed: HTTP ' . $code);
        throw new RuntimeException('razorpay order failed');
    }
    return $d['id'];
}

// Razorpay checkout signature: HMAC-SHA256(secret, "<razorpay_order_id>|<razorpay_payment_id>").
function razorpay_signature_ok(string $orderId, string $paymentId, string $signature, string $secret): bool {
    return $signature !== '' && hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $secret), $signature);
}

// What the customer's browser needs to open Razorpay Checkout for an existing online order.
function razorpay_checkout_params(array $order): array {
    $cfg = razorpay_config();
    return ['keyId' => $cfg['key_id'], 'orderId' => $order['razorpay_order_id'],
            'amount' => (int)$order['total_amount'] * 100, 'currency' => 'INR'];
}

// Marks the order paid after a VERIFIED Razorpay payment. Idempotent: callbacks may repeat or race.
// Returns 'paid' | 'already' | 'unknown_order' | 'mismatch'.
function mark_order_paid(PDO $pdo, string $razorpayOrderId, string $paymentId, int $amountPaise, string $currency): string {
    $now = time();
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id, total_amount, payment_status FROM orders WHERE razorpay_order_id = ?' . row_lock());
        $q->execute([$razorpayOrderId]);
        $o = $q->fetch();
        if (!$o) { $pdo->rollBack(); return 'unknown_order'; }
        if (in_array($o['payment_status'], ['paid', 'refunded'], true)) { $pdo->rollBack(); return 'already'; }
        if ($amountPaise !== (int)$o['total_amount'] * 100 || strtoupper($currency) !== 'INR') {
            // Never mark paid when the gateway amount differs from the order total.
            $pdo->prepare("INSERT INTO payments (order_id, kind, method, amount, reference, paid_at, note, source, recorded_by, created_at)
                           VALUES (?, 'failure', 'razorpay', ?, ?, ?, ?, 'razorpay', NULL, ?)")
                ->execute([$o['id'], intdiv($amountPaise, 100), 'mismatch-' . $paymentId, $now, 'Amount or currency did not match the order total; not marked paid.', $now]);
            $pdo->commit();
            return 'mismatch';
        }
        $pdo->prepare("INSERT INTO payments (order_id, kind, method, amount, reference, paid_at, note, source, recorded_by, created_at)
                       VALUES (?, 'payment', 'razorpay', ?, ?, ?, NULL, 'razorpay', NULL, ?)")
            ->execute([$o['id'], (int)$o['total_amount'], $paymentId, $now, $now]);
        $pdo->prepare("UPDATE orders SET payment_status = 'paid', updated_at = ? WHERE id = ?")->execute([$now, $o['id']]);
        $pdo->commit();
        return 'paid';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_duplicate_key($e)) return 'already';             // same payment id already recorded (repeated callback)
        throw $e;
    }
}

// Records a failed online attempt. A pending order becomes 'failed'; a later successful retry
// on the same Razorpay order still marks it paid. Returns true if the order was found.
function record_payment_failure(PDO $pdo, string $razorpayOrderId, string $paymentId, string $reason): bool {
    $now = time();
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id, total_amount, payment_status FROM orders WHERE razorpay_order_id = ?' . row_lock());
        $q->execute([$razorpayOrderId]);
        $o = $q->fetch();
        if (!$o) { $pdo->rollBack(); return false; }
        if ($o['payment_status'] !== 'paid') {
            $pdo->prepare("INSERT INTO payments (order_id, kind, method, amount, reference, paid_at, note, source, recorded_by, created_at)
                           VALUES (?, 'failure', 'razorpay', ?, ?, ?, ?, 'razorpay', NULL, ?)")
                ->execute([$o['id'], (int)$o['total_amount'], $paymentId, $now, mb_substr($reason, 0, 300), $now]);
            if ($o['payment_status'] === 'pending') {
                $pdo->prepare("UPDATE orders SET payment_status = 'failed', updated_at = ? WHERE id = ?")->execute([$now, $o['id']]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_duplicate_key($e)) return true;                  // repeated callback
        throw $e;
    }
}
