<?php
// Command-line only: create or reset a staff login.
//   php tools/create-admin.php <username>
// Reads the password from the terminal (never from the web). Uses the same
// config/database as the website (../newgen-config.php or api/config.php).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../api/bootstrap.php';

$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
    fwrite(STDERR, "Usage: php tools/create-admin.php <username>   (3-40 letters, digits, . _ -)\n");
    exit(1);
}
fwrite(STDOUT, "Password (min 12 characters): ");
$pw = rtrim((string)fgets(STDIN), "\r\n");
if (mb_strlen($pw) < 12) { fwrite(STDERR, "Password too short.\n"); exit(1); }

$pdo = db();
$hash = password_hash($pw, PASSWORD_DEFAULT);
$q = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
$q->execute([$username]);
if ($id = $q->fetchColumn()) {
    $pdo->prepare('UPDATE admin_users SET password_hash = ?, is_active = 1 WHERE id = ?')->execute([$hash, $id]);
    echo "Password reset for '$username'.\n";
} else {
    $pdo->prepare('INSERT INTO admin_users (username, password_hash, is_active, created_at) VALUES (?,?,1,?)')->execute([$username, $hash, time()]);
    echo "Admin '$username' created.\n";
}
