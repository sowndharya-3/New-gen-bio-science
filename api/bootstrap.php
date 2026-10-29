<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

const ORDER_STATUSES = ['received', 'processing', 'shipped', 'delivered', 'cancelled'];
// Allowed next order statuses. Forward flow, one-step corrections back, cancellation only before
// shipping; 'delivered' and 'cancelled' are final. Order status and payment status are independent.
const ORDER_TRANSITIONS = [
    'received'   => ['processing', 'shipped', 'cancelled'],
    'processing' => ['received', 'shipped', 'cancelled'],
    'shipped'    => ['processing', 'delivered'],
    'delivered'  => [],
    'cancelled'  => [],
];
const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];
const CHECKOUT_METHODS = ['cod', 'razorpay'];                 // what a customer can choose
const OFFLINE_METHODS = ['cod', 'cash', 'upi', 'bank_transfer', 'cheque', 'other'];   // what staff can record as received
const SECTION_KEYS = ['benefits' => 'Benefits', 'features' => 'Features', 'mode_of_action' => 'Mode of Action',
                      'recommendation' => 'Recommendation', 'presentation' => 'Presentation'];

function respond(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        header('Allow: ' . $method);
        respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
    }
}

function read_json(): array {
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) respond(400, ['ok' => false, 'error' => 'Invalid request.']);
    return $data;
}

function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        // Prefer a file above the web root; fall back to api/config.php.
        foreach ([dirname(__DIR__, 2) . '/newgen-config.php', __DIR__ . '/config.php'] as $f) {
            if (is_file($f)) { $cfg = require $f; break; }
        }
        if ($cfg === null) {
            error_log('newgen: config file missing');
            respond(500, ['ok' => false, 'error' => 'Service temporarily unavailable.']);
        }
    }
    return $cfg;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config();
    try {
        if (($c['driver'] ?? 'mysql') === 'sqlite') {          // dev/test only
            $pdo = new PDO('sqlite:' . $c['sqlite_path']);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 10000');        } else {
            $dsn = 'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=' . ($c['db_charset'] ?? 'utf8mb4');
            $pdo = new PDO($dsn, $c['db_user'], $c['db_pass']);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('newgen db connect: ' . $e->getMessage());
        respond(500, ['ok' => false, 'error' => 'Service temporarily unavailable.']);
    }
    return $pdo;
}

// Allow at most $max hits per $window seconds per client IP and action.
function rate_limit(string $action, int $max, int $window): void {
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();
    $pdo = db();
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate_hits WHERE ip_hash = ? AND action = ? AND created_at >= ?');
    $q->execute([$ip, $action, $now - $window]);
    if ((int)$q->fetchColumn() >= $max) {
        header('Retry-After: ' . $window);
        respond(429, ['ok' => false, 'error' => 'Too many requests. Please try again later.']);
    }
    $pdo->prepare('INSERT INTO rate_hits (ip_hash, action, created_at) VALUES (?, ?, ?)')->execute([$ip, $action, $now]);
    if (random_int(1, 50) === 1) {                               // occasional cleanup
        $pdo->prepare('DELETE FROM rate_hits WHERE created_at < ?')->execute([$now - 86400]);
    }
}

// Unpredictable public ID, e.g. NG-7K3M9Q2XH4PA (12 chars, ~59 bits of randomness).
function new_public_id(): string {
    $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';              // no 0/O/1/I/L
    $id = '';
    for ($i = 0; $i < 12; $i++) $id .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return 'NG-' . $id;
}

function normalize_public_id(string $s): ?string {
    $s = strtoupper(trim($s));
    return preg_match('/^NG-[2-9A-HJKMNP-Z]{12}$/', $s) ? $s : null;
}

// ---------------------------------------------------------------------------
// Admin session helpers (staff only; customers never log in)
// ---------------------------------------------------------------------------
const ADMIN_IDLE_SECONDS = 7200;

function start_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('ngadmin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/api/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
    if (isset($_SESSION['last_active']) && time() - $_SESSION['last_active'] > ADMIN_IDLE_SECONDS) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    if (!empty($_SESSION['admin_id'])) $_SESSION['last_active'] = time();
}

// Returns ['id'=>..,'username'=>..] or responds 401. Re-checks the account
// is still active on every request, so disabling a user takes effect at once.
function require_admin(): array {
    start_admin_session();
    if (empty($_SESSION['admin_id'])) respond(401, ['ok' => false, 'error' => 'Please sign in.']);
    $q = db()->prepare('SELECT id, username FROM admin_users WHERE id = ? AND is_active = 1');
    $q->execute([$_SESSION['admin_id']]);
    $u = $q->fetch();
    if (!$u) { $_SESSION = []; session_destroy(); respond(401, ['ok' => false, 'error' => 'Please sign in.']); }
    return $u;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

// All state-changing admin requests: same-origin + CSRF header.
function require_csrf(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ? explode(':', $_SERVER['HTTP_HOST'])[0] : '')) {
        respond(403, ['ok' => false, 'error' => 'Forbidden.']);
    }
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        respond(403, ['ok' => false, 'error' => 'Session expired. Please reload the page.']);
    }
}

// Valid category slugs (categories are managed in the admin panel).
function category_slugs(PDO $pdo): array {
    return $pdo->query('SELECT slug FROM categories ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
}

// Row lock inside a transaction (SQLite, used only for local tests, has no FOR UPDATE).
function row_lock(): string {
    return (config()['driver'] ?? 'mysql') === 'sqlite' ? '' : ' FOR UPDATE';
}

function is_duplicate_key(Throwable $e): bool {
    return $e instanceof PDOException && (string)$e->getCode() === '23000';
}

// One line of free text shown on the site (always HTML-escaped on output): no control characters or angle brackets.
function text_line(mixed $v, int $min, int $max): ?string {
    if (!is_string($v)) return null;
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    $len = mb_strlen($v);
    if ($len < $min || $len > $max) return null;
    return preg_match('/[<>\x00-\x1F\x7F]/u', $v) ? null : $v;
}

// Audit trail of admin changes. Never blocks the action it records.
function audit(array $admin, string $action, string $entity, ?string $entityId, ?string $detail = null): void {
    try {
        db()->prepare('INSERT INTO admin_audit_log (admin_username, action, entity, entity_id, detail, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$admin['username'], $action, $entity, $entityId, $detail === null ? null : mb_substr($detail, 0, 500), time()]);
    } catch (Throwable $e) {
        error_log('newgen audit: ' . $e->getMessage());
    }
}

// Product/variant text: letters, digits, spaces and a few safe punctuation marks.
function safe_label(mixed $v, int $min, int $max): ?string {
    if (!is_string($v)) return null;
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    $len = mb_strlen($v);
    if ($len < $min || $len > $max) return null;
    return preg_match('/^[\p{L}\p{N} \-_.,&()™®\/+%]+$/u', $v) ? $v : null;
}
