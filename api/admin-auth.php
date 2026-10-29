<?php
declare(strict_types=1);
// Staff sign-in. Sessions are server-side (HttpOnly, SameSite=Strict cookie);
// nothing privileged is ever stored in the browser's JavaScript.
require __DIR__ . '/bootstrap.php';

start_admin_session();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {                                     // who am I? (also hands out the CSRF token)
    $u = require_admin();
    respond(200, ['ok' => true, 'user' => $u['username'], 'csrf' => csrf_token()]);
}
require_method('POST');
$in = read_json();
$action = $in['action'] ?? '';

if ($action === 'logout') {
    require_admin();
    require_csrf();
    $_SESSION = [];
    session_destroy();
    respond(200, ['ok' => true]);
}

if ($action !== 'login') respond(400, ['ok' => false, 'error' => 'Invalid request.']);

rate_limit('login', 10, 900);                                // 10 attempts / 15 min / IP
$username = is_string($in['username'] ?? null) ? trim($in['username']) : '';
$password = is_string($in['password'] ?? null) ? $in['password'] : '';

$q = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? AND is_active = 1');
$q->execute([$username]);
$u = $q->fetch();
// Always run a hash check so response time doesn't reveal whether the user exists.
$dummy = '$2y$10$C9VFxUfxZJxAIKZGHR5bj.WV9anmHKLe3iXAVD9OEDgo35xKVDcMa';
$ok = password_verify($password, $u['password_hash'] ?? $dummy) && $u;
if (!$ok) respond(401, ['ok' => false, 'error' => 'Incorrect username or password.']);

session_regenerate_id(true);
$_SESSION['admin_id'] = (int)$u['id'];
$_SESSION['last_active'] = time();
$_SESSION['csrf'] = bin2hex(random_bytes(32));
if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
    db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
}
db()->prepare('UPDATE admin_users SET last_login_at = ? WHERE id = ?')->execute([time(), $u['id']]);
respond(200, ['ok' => true, 'user' => $u['username'], 'csrf' => $_SESSION['csrf']]);
