<?php
declare(strict_types=1);
// Staff-only order management. This is the ONLY place staff change order status or record payments;
// the public tracking page (track-order.php) reads the same orders table.
// Order status (received..cancelled) and payment status (pending/paid/failed/refunded) are separate:
// a COD order can be Delivered while payment is still Pending until the money is received and recorded.
require __DIR__ . '/bootstrap.php';

$admin = require_admin();
$pdo = db();

const REFUND_METHODS = ['razorpay', 'bank_transfer', 'upi', 'cash', 'cheque', 'other'];

function order_row_out(array $o, array $items): array {
    return [
        'orderId' => $o['public_id'], 'createdAt' => gmdate('c', (int)$o['created_at']),
        'customerName' => $o['customer_name'], 'mobile' => $o['mobile'], 'email' => $o['email'],
        'address' => $o['address'], 'city' => $o['city'], 'state' => $o['state'], 'pincode' => $o['pincode'],
        'total' => (int)$o['total_amount'], 'orderStatus' => $o['order_status'], 'paymentStatus' => $o['payment_status'],
        'paymentMethod' => $o['payment_method'],
        'items' => $items,
    ];
}
function item_out(array $r): array {
    return ['name' => $r['product_name'], 'size' => $r['size_label'], 'qty' => (int)$r['qty'],
            'unitPrice' => (int)$r['unit_price'], 'lineTotal' => (int)$r['line_total']];
}
function payment_out(array $p): array {
    return ['kind' => $p['kind'], 'method' => $p['method'], 'amount' => (int)$p['amount'], 'reference' => $p['reference'],
            'paidAt' => gmdate('c', (int)$p['paid_at']), 'note' => $p['note'], 'source' => $p['source'], 'by' => $p['recorded_by']];
}

// Parses a YYYY-MM-DD payment date. Stored at 12:00 UTC so it shows as the same calendar day in any timezone.
function parse_pay_date(mixed $v, int $orderCreatedAt): ?int {
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v, new DateTimeZone('UTC'));
    if (!$d || $d->format('Y-m-d') !== $v) return null;
    $ts = $d->getTimestamp() + 43200;
    if ($ts > time() + 86400) return null;                                  // not in the future
    if ($ts < $orderCreatedAt - 86400 - 43200) return null;                 // not before the order was placed
    return $ts;
}

// Optional free text: reference numbers and notes.
function opt_text(mixed $v, int $max, string $pattern = '/^[^<>\x00-\x1F\x7F]+$/u'): array {   // [ok, value]
    if ($v === null || (is_string($v) && trim($v) === '')) return [true, null];
    if (!is_string($v)) return [false, null];
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    return (mb_strlen($v) <= $max && preg_match($pattern, $v)) ? [true, $v] : [false, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {                                // single order + status history + payments
        $id = normalize_public_id((string)$_GET['id']);
        $q = $pdo->prepare('SELECT * FROM orders WHERE public_id = ?');
        $q->execute([$id ?? '']);
        $o = $q->fetch();
        if (!$o) respond(404, ['ok' => false, 'error' => 'Order not found.']);
        $q = $pdo->prepare('SELECT product_name, size_label, qty, unit_price, line_total FROM order_items WHERE order_id = ? ORDER BY id');
        $q->execute([$o['id']]);
        $items = array_map('item_out', $q->fetchAll());
        $q = $pdo->prepare('SELECT status, changed_at, changed_by FROM order_status_history WHERE order_id = ? ORDER BY id');
        $q->execute([$o['id']]);
        $history = array_map(fn($h) => ['status' => $h['status'], 'at' => gmdate('c', (int)$h['changed_at']), 'by' => $h['changed_by']], $q->fetchAll());
        $q = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id');
        $q->execute([$o['id']]);
        $payments = array_map('payment_out', $q->fetchAll());
        $paid = $o['payment_status'] === 'paid';
        $cancelled = $o['order_status'] === 'cancelled';
        respond(200, ['ok' => true, 'order' => order_row_out($o, $items), 'history' => $history, 'payments' => $payments,
            'razorpayOrderId' => $o['razorpay_order_id'],
            'nextStatuses' => ORDER_TRANSITIONS[$o['order_status']] ?? [],
            'canRecordPayment' => !$cancelled && !in_array($o['payment_status'], ['paid', 'refunded'], true),
            'canRecordRefund' => $cancelled && $paid]);
    }

    // Dashboard counts: straight from the orders table.
    $stats = ['total' => 0, 'received' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0, 'paymentPending' => 0];
    foreach ($pdo->query('SELECT order_status, COUNT(*) c FROM orders GROUP BY order_status') as $r) {
        if (isset($stats[$r['order_status']])) $stats[$r['order_status']] = (int)$r['c'];
        $stats['total'] += (int)$r['c'];
    }
    $stats['paymentPending'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'")->fetchColumn();

    // Listing with search + filters.
    $where = []; $args = [];
    $qs = trim((string)($_GET['q'] ?? ''));
    if ($qs !== '') {
        $like = '%' . preg_replace('/[!%_]/', '!$0', $qs) . '%';     // '!' as LIKE escape char (portable MySQL/SQLite)
        $where[] = "(public_id LIKE ? ESCAPE '!' OR customer_name LIKE ? ESCAPE '!' OR mobile LIKE ? ESCAPE '!')";
        array_push($args, $like, $like, $like);
    }
    $st = (string)($_GET['status'] ?? '');
    if ($st !== '') {
        if (!in_array($st, ORDER_STATUSES, true)) respond(422, ['ok' => false, 'error' => 'Invalid order status filter.']);
        $where[] = 'order_status = ?'; $args[] = $st;
    }
    $ps = (string)($_GET['payment'] ?? '');
    if ($ps !== '') {
        if (!in_array($ps, PAYMENT_STATUSES, true)) respond(422, ['ok' => false, 'error' => 'Invalid payment status filter.']);
        $where[] = 'payment_status = ?'; $args[] = $ps;
    }
    $pm = (string)($_GET['method'] ?? '');
    if ($pm !== '') {
        if (!in_array($pm, CHECKOUT_METHODS, true)) respond(422, ['ok' => false, 'error' => 'Invalid payment method filter.']);
        $where[] = 'payment_method = ?'; $args[] = $pm;
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $q = $pdo->prepare("SELECT COUNT(*) FROM orders $w");
    $q->execute($args);
    $matching = (int)$q->fetchColumn();

    $q = $pdo->prepare("SELECT * FROM orders $w ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $q->execute($args);
    $orders = $q->fetchAll();
    $itemsBy = [];
    if ($orders) {
        $ids = array_column($orders, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("SELECT order_id, product_name, size_label, qty, unit_price, line_total FROM order_items WHERE order_id IN ($in) ORDER BY id");
        $q->execute($ids);
        foreach ($q->fetchAll() as $r) $itemsBy[$r['order_id']][] = item_out($r);
    }
    respond(200, ['ok' => true, 'stats' => $stats, 'matching' => $matching,
        'orders' => array_map(fn($o) => order_row_out($o, $itemsBy[$o['id']] ?? []), $orders)]);
}

require_method('POST');
require_csrf();
$in = read_json();
$action = $in['action'] ?? '';
$id = normalize_public_id((string)($in['orderId'] ?? ''));
if (!$id) respond(422, ['ok' => false, 'error' => 'Invalid order.']);

// Loads the order inside a transaction (row-locked) so two staff members cannot race each other.
function lock_order(PDO $pdo, string $publicId): array {
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT * FROM orders WHERE public_id = ?' . row_lock());
    $q->execute([$publicId]);
    $o = $q->fetch();
    if (!$o) { $pdo->rollBack(); respond(404, ['ok' => false, 'error' => 'Order not found.']); }
    return $o;
}
function fail(PDO $pdo, int $code, string $msg): never {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond($code, ['ok' => false, 'error' => $msg]);
}

// ---- change order status -------------------------------------------------------------------------------
if ($action === 'status') {
    $status = $in['status'] ?? null;
    if (!is_string($status) || !in_array($status, ORDER_STATUSES, true)) respond(422, ['ok' => false, 'error' => 'Invalid order status.']);
    try {
        $o = lock_order($pdo, $id);
        if ($o['order_status'] !== $status) {                          // no-op changes aren't logged
            if (!in_array($status, ORDER_TRANSITIONS[$o['order_status']] ?? [], true)) {
                fail($pdo, 409, 'An order that is ' . $o['order_status'] . ' cannot be changed to ' . $status . '.'
                    . ($status === 'cancelled' ? ' Orders can only be cancelled before they are shipped.' : ''));
            }
            if ($status === 'cancelled' && ($in['confirm'] ?? false) !== true) fail($pdo, 422, 'Please confirm the cancellation.');
            $now = time();
            $pdo->prepare('UPDATE orders SET order_status = ?, updated_at = ? WHERE id = ?')->execute([$status, $now, $o['id']]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_at, changed_by) VALUES (?,?,?,?)')->execute([$o['id'], $status, $now, $admin['username']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('newgen admin-orders: ' . $e->getMessage());
        respond(500, ['ok' => false, 'error' => 'Update failed. The status was not changed.']);
    }
    if ($o['order_status'] !== $status) audit($admin, 'order.status', 'order', $id, $o['order_status'] . ' -> ' . $status);
    respond(200, ['ok' => true, 'orderId' => $id, 'orderStatus' => $status]);
}

// ---- record a payment received (COD, cash, UPI, bank transfer, cheque, other) ----------------------------------
if ($action === 'record_payment' || $action === 'record_refund') {
    $refund = $action === 'record_refund';
    $method = $in['method'] ?? null;
    $amount = $in['amount'] ?? null;
    [$refOk, $reference] = opt_text($in['reference'] ?? null, 80, '/^[A-Za-z0-9][A-Za-z0-9 ._\-\/]*$/');
    [$noteOk, $note] = opt_text($in['note'] ?? null, 300);
    $errors = [];
    if (!is_string($method) || !in_array($method, $refund ? REFUND_METHODS : OFFLINE_METHODS, true)) $errors[] = 'Choose a payment method.';
    if (!is_int($amount) || $amount < 1) $errors[] = 'Enter the amount as a whole number of rupees.';
    if (!$refOk) $errors[] = 'Reference may use letters, digits, spaces and . _ - / only (max 80 characters).';
    if (!$noteOk) $errors[] = 'Notes can be up to 300 characters (no < > characters).';
    if ($errors) respond(422, ['ok' => false, 'error' => $errors[0], 'errors' => $errors]);
    try {
        $o = lock_order($pdo, $id);
        $paidAt = parse_pay_date($in['paidAt'] ?? null, (int)$o['created_at']);
        if ($paidAt === null) fail($pdo, 422, 'Enter a valid ' . ($refund ? 'refund' : 'payment') . ' date (not in the future, not before the order date).');
        if ($amount !== (int)$o['total_amount']) {
            fail($pdo, 422, 'The amount must equal the order total of ₹' . number_format((int)$o['total_amount']) . '. Partial payments are not supported, so nothing was recorded.');
        }
        if ($refund) {
            if ($o['order_status'] !== 'cancelled' || $o['payment_status'] !== 'paid') fail($pdo, 409, 'A refund can only be recorded for a cancelled order that was paid.');
        } else {
            if ($o['order_status'] === 'cancelled') fail($pdo, 409, 'A payment cannot be recorded on a cancelled order.');
            if ($o['payment_status'] === 'paid') fail($pdo, 409, 'This order is already marked as paid. No duplicate payment was recorded.');
            if ($o['payment_status'] === 'refunded') fail($pdo, 409, 'This order has been refunded.');
        }
        $now = time();
        $pdo->prepare('INSERT INTO payments (order_id, kind, method, amount, reference, paid_at, note, source, recorded_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$o['id'], $refund ? 'refund' : 'payment', $method, $amount, $reference, $paidAt, $note, 'admin', $admin['username'], $now]);
        $pdo->prepare('UPDATE orders SET payment_status = ?, updated_at = ? WHERE id = ?')->execute([$refund ? 'refunded' : 'paid', $now, $o['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_duplicate_key($e)) respond(409, ['ok' => false, 'error' => 'This reference number has already been recorded. No duplicate was added.']);
        error_log('newgen admin-orders: ' . $e->getMessage());
        respond(500, ['ok' => false, 'error' => 'Could not record this. Nothing was changed.']);
    }
    audit($admin, $refund ? 'order.refund' : 'order.payment', 'order', $id, $method . ' ₹' . $amount . ($reference ? ' ref ' . $reference : ''));
    respond(200, ['ok' => true, 'orderId' => $id, 'paymentStatus' => $refund ? 'refunded' : 'paid']);
}

respond(400, ['ok' => false, 'error' => 'Invalid request.']);
