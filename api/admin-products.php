<?php
declare(strict_types=1);
// Staff-only Shop product management: list, create/edit (content fields, detail sections, pack sizes and
// prices), activate/deactivate, delete, and image upload. "Delete" is a soft delete: the product disappears
// from the Shop and from this list, but its row stays so historical orders (which keep their own copy of
// name, pack and price) are never affected.
require __DIR__ . '/bootstrap.php';

$admin = require_admin();
$pdo = db();

const UPLOAD_URL_PREFIX = '/uploads/products/';
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_IMAGE_SIDE = 1200;                                 // longer side is scaled down to this

function product_out(array $p, array $variants, array $sections): array {
    return ['id' => (int)$p['id'], 'slug' => $p['slug'], 'name' => $p['name'], 'category' => $p['category'],
        'subtitle' => $p['subtitle'], 'descriptionHeading' => $p['description_heading'], 'description' => $p['description'],
        'image' => $p['image_path'], 'active' => (bool)$p['is_active'], 'sizes' => $variants, 'sections' => $sections];
}

function load_products(PDO $pdo, ?int $onlyId = null): array {
    $q = $pdo->prepare('SELECT * FROM products WHERE deleted_at IS NULL' . ($onlyId ? ' AND id = ?' : '') . ' ORDER BY sort_order, id');
    $q->execute($onlyId ? [$onlyId] : []);
    $rows = $q->fetchAll();
    $by = [];
    foreach ($pdo->query('SELECT product_id, label, price FROM product_variants ORDER BY sort_order, id') as $v) {
        $by[$v['product_id']][] = ['label' => $v['label'], 'price' => (int)$v['price']];
    }
    $items = [];
    foreach ($pdo->query('SELECT section_id, text FROM product_section_items ORDER BY sort_order, id') as $i) $items[$i['section_id']][] = $i['text'];
    $secs = [];
    foreach ($pdo->query('SELECT id, product_id, section_key, heading FROM product_sections ORDER BY sort_order, id') as $s) {
        $secs[$s['product_id']][] = ['key' => $s['section_key'], 'heading' => $s['heading'], 'items' => $items[$s['id']] ?? []];
    }
    return array_map(fn($p) => product_out($p, $by[$p['id']] ?? [], $secs[$p['id']] ?? []), $rows);
}

// Validates the submitted detail sections: known keys only, each at most once, in the submitted order.
// Sections without any bullet point are dropped (so the public page never shows an empty block).
function parse_sections(mixed $raw, array &$errors): array {
    if ($raw === null) return [];
    if (!is_array($raw) || count($raw) > count(SECTION_KEYS)) { $errors[] = 'Invalid product sections.'; return []; }
    $out = [];
    foreach ($raw as $s) {
        $key = is_array($s) ? ($s['key'] ?? null) : null;
        if (!is_string($key) || !isset(SECTION_KEYS[$key]) || isset($out[$key])) { $errors[] = 'Invalid or repeated product section.'; return []; }
        $heading = trim((string)($s['heading'] ?? '')) === '' ? SECTION_KEYS[$key] : text_line($s['heading'], 1, 80);
        if ($heading === null) { $errors[] = 'Section headings must be 1-80 characters (no < > characters).'; return []; }
        $items = [];
        $rawItems = $s['items'] ?? [];
        if (!is_array($rawItems) || count($rawItems) > 40) { $errors[] = 'A section can have at most 40 bullet points.'; return []; }
        foreach ($rawItems as $t) {
            if (is_string($t) && trim($t) === '') continue;      // ignore blank bullet rows
            $t = text_line($t, 1, 500);
            if ($t === null) { $errors[] = 'Each bullet point must be 1-500 characters (no < > characters).'; return []; }
            $items[] = $t;
        }
        if ($items) $out[$key] = ['key' => $key, 'heading' => $heading, 'items' => $items];
    }
    return array_values($out);
}

function upload_dir(): string {
    $d = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($d) && !mkdir($d, 0755, true)) respond(500, ['ok' => false, 'error' => 'Upload folder is not writable.']);
    return $d;
}

// Validate + store an uploaded image under a random name. Re-encodes with GD
// when available (drops any embedded payload and caps the size).
function store_image(array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $big = in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        respond(422, ['ok' => false, 'error' => $big ? 'Image is too large (max 5 MB).' : 'Upload failed. Please try again.']);
    }
    if ($f['size'] > MAX_IMAGE_BYTES) respond(422, ['ok' => false, 'error' => 'Image is too large (max 5 MB).']);
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => ['jpg', 'image/jpeg'], IMAGETYPE_PNG => ['png', 'image/png'], IMAGETYPE_WEBP => ['webp', 'image/webp']];
    if (!$info || !isset($types[$info[2]])) respond(422, ['ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.']);
    if ($info[0] < 50 || $info[1] < 50 || $info[0] * $info[1] > 40000000) respond(422, ['ok' => false, 'error' => 'Image dimensions are not supported.']);
    $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : $types[$info[2]][1];
    if ($mime !== $types[$info[2]][1]) respond(422, ['ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.']);

    $ext = $types[$info[2]][0];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = upload_dir() . '/' . $name;

    $done = false;
    if (function_exists('imagecreatefromstring')) {
        $im = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
        if ($im) {
            $w = imagesx($im); $h = imagesy($im);
            $scale = min(1, MAX_IMAGE_SIDE / max($w, $h));
            if ($scale < 1) {
                $n = imagescale($im, (int)round($w * $scale), (int)round($h * $scale));
                if ($n) { imagedestroy($im); $im = $n; }
            }
            if ($ext === 'png') { imagesavealpha($im, true); $done = imagepng($im, $dest, 6); }
            elseif ($ext === 'webp' && function_exists('imagewebp')) { imagesavealpha($im, true); $done = imagewebp($im, $dest, 86); }
            elseif ($ext === 'jpg') { $done = imagejpeg($im, $dest, 86); }
            imagedestroy($im);
        }
    }
    if (!$done && !move_uploaded_file($f['tmp_name'], $dest)) respond(500, ['ok' => false, 'error' => 'Could not save the image.']);
    @chmod($dest, 0644);
    return UPLOAD_URL_PREFIX . $name;
}

function delete_if_orphan_upload(PDO $pdo, ?string $path): void {
    if (!$path || !preg_match('#^/uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) return;
    $q = $pdo->prepare('SELECT COUNT(*) FROM products WHERE image_path = ?');
    $q->execute([$path]);
    if ((int)$q->fetchColumn() === 0) @unlink(dirname(__DIR__) . $path);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(200, ['ok' => true, 'products' => load_products($pdo),
        'categories' => $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, id')->fetchAll(),
        'sectionKeys' => array_map(fn($k, $label) => ['key' => $k, 'label' => $label], array_keys(SECTION_KEYS), SECTION_KEYS)]);
}

require_method('POST');
require_csrf();

// A file bigger than PHP's post_max_size arrives as an empty request.
if (empty($_FILES) && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/')) {
    respond(422, ['ok' => false, 'error' => 'Image is too large (max 5 MB).']);
}

// --- image upload (multipart): returns a path to attach via action=save -----
if (isset($_FILES['image'])) {
    rate_limit('upload', 60, 3600);
    respond(200, ['ok' => true, 'path' => store_image($_FILES['image'])]);
}

$in = read_json();
$action = $in['action'] ?? '';

if ($action === 'toggle') {
    $id = $in['id'] ?? null;
    if (!is_int($id) || !is_bool($in['active'] ?? null)) respond(422, ['ok' => false, 'error' => 'Invalid request.']);
    $q = $pdo->prepare('UPDATE products SET is_active = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL');
    $q->execute([$in['active'] ? 1 : 0, time(), $id]);
    $p = load_products($pdo, $id);
    if (!$p) respond(404, ['ok' => false, 'error' => 'Product not found.']);
    audit($admin, $in['active'] ? 'product.activate' : 'product.deactivate', 'product', (string)$id, $p[0]['name']);
    respond(200, ['ok' => true, 'product' => $p[0]]);
}

if ($action === 'delete') {
    $id = $in['id'] ?? null;
    if (!is_int($id) || ($in['confirm'] ?? false) !== true) respond(422, ['ok' => false, 'error' => 'Please confirm the deletion.']);
    $q = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND deleted_at IS NULL');
    $q->execute([$id]);
    $p = $q->fetch();
    if (!$p) respond(404, ['ok' => false, 'error' => 'Product not found.']);
    // Soft delete: hidden from the Shop (is_active = 0) and from this list. Orders and order items are never touched.
    $now = time();
    $pdo->prepare('UPDATE products SET is_active = 0, deleted_at = ?, updated_at = ? WHERE id = ?')->execute([$now, $now, $id]);
    audit($admin, 'product.delete', 'product', (string)$id, $p['name']);
    respond(200, ['ok' => true]);
}

if ($action !== 'save') respond(400, ['ok' => false, 'error' => 'Invalid request.']);

// --- create / update -----------------------------------------------------------
$id = $in['id'] ?? null;
if ($id !== null && !is_int($id)) respond(422, ['ok' => false, 'error' => 'Invalid product.']);
$existing = null;
if ($id !== null) {
    $q = $pdo->prepare('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL');
    $q->execute([$id]);
    $existing = $q->fetch();
    if (!$existing) respond(404, ['ok' => false, 'error' => 'Product not found.']);
}

$name = safe_label($in['name'] ?? null, 2, 80);
$category = $in['category'] ?? null;
$desc = $in['description'] ?? null;
$active = $in['active'] ?? true;
$errors = [];
if (!$name) $errors[] = 'Enter a product name (2-80 letters/numbers; no < > " characters).';
if (!is_string($category) || !in_array($category, category_slugs($pdo), true)) $errors[] = 'Choose a category.';
if ($desc !== null && (!is_string($desc) || mb_strlen($desc) > 4000)) $errors[] = 'Description must be under 4000 characters.';
if (!is_bool($active)) $errors[] = 'Invalid availability.';
$subtitle = null; $descHeading = null;
foreach (['subtitle' => 'Subtitle', 'descriptionHeading' => 'Description heading'] as $field => $label) {
    $v = $in[$field] ?? null;
    $clean = ($v === null || (is_string($v) && trim($v) === '')) ? null : text_line($v, 1, 120);
    if ($v !== null && !(is_string($v) && trim($v) === '') && $clean === null) $errors[] = $label . ' must be 1-120 characters (no < > characters).';
    if ($field === 'subtitle') $subtitle = $clean; else $descHeading = $clean;
}
$sections = parse_sections($in['sections'] ?? null, $errors);
// A field that is not sent at all is left as it is (only the admin form sends everything).
$replaceSections = array_key_exists('sections', $in);
if ($existing) {
    if (!array_key_exists('subtitle', $in)) $subtitle = $existing['subtitle'];
    if (!array_key_exists('descriptionHeading', $in)) $descHeading = $existing['description_heading'];
}

$sizes = [];
$rawSizes = $in['sizes'] ?? null;
if (!is_array($rawSizes) || !$rawSizes || count($rawSizes) > 20) {
    $errors[] = 'Add at least one pack size with a price.';
} else {
    foreach ($rawSizes as $s) {
        $label = is_array($s) ? safe_label($s['label'] ?? null, 1, 60) : null;
        $price = is_array($s) ? ($s['price'] ?? null) : null;
        if (!$label || !is_int($price) || $price < 1 || $price > 10000000) {
            $errors[] = 'Each pack size needs a label and a whole-rupee price (at least 1).';
            break;
        }
        if (isset($sizes[mb_strtolower($label)])) { $errors[] = 'Pack sizes must be unique.'; break; }
        $sizes[mb_strtolower($label)] = ['label' => $label, 'price' => $price];
    }
}

// Image: keep the current one, clear it, or attach a freshly uploaded file.
$image = $existing['image_path'] ?? null;
if (array_key_exists('image', $in)) {
    $img = $in['image'];
    if ($img === null || $img === '') $image = null;
    elseif ($img === $image) { /* unchanged */ }
    elseif (is_string($img) && preg_match('#^/uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$#', $img) && is_file(dirname(__DIR__) . $img)) $image = $img;
    else $errors[] = 'Invalid image. Please upload it again.';
}
if ($errors) respond(422, ['ok' => false, 'error' => $errors[0], 'errors' => $errors]);

$now = time();
try {
    $pdo->beginTransaction();
    if ($existing) {
        $pdo->prepare('UPDATE products SET name=?, category=?, description=?, subtitle=?, description_heading=?, image_path=?, is_active=?, updated_at=? WHERE id=?')
            ->execute([$name, $category, ($desc === null || trim($desc) === '') ? null : trim($desc), $subtitle, $descHeading, $image, $active ? 1 : 0, $now, $existing['id']]);
        $pid = (int)$existing['id'];
    } else {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)), '-') ?: 'product';
        $base = substr($base, 0, 50);
        $slug = $base;
        for ($i = 2; ; $i++) {
            $c = $pdo->prepare('SELECT 1 FROM products WHERE slug = ?');
            $c->execute([$slug]);
            if (!$c->fetchColumn()) break;
            $slug = $base . '-' . $i;
        }
        $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM products')->fetchColumn();
        $pdo->prepare('INSERT INTO products (slug, name, category, description, subtitle, description_heading, image_path, catalog_key, is_active, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NULL,?,?,?,?)')
            ->execute([$slug, $name, $category, ($desc === null || trim($desc) === '') ? null : trim($desc), $subtitle, $descHeading, $image, $active ? 1 : 0, $order, $now, $now]);
        $pid = (int)$pdo->lastInsertId();
    }
    // Replace the detail sections (their bullet points go with them: ON DELETE CASCADE) in the submitted order.
    $insSec = $pdo->prepare('INSERT INTO product_sections (product_id, section_key, heading, sort_order) VALUES (?,?,?,?)');
    $insItem = $pdo->prepare('INSERT INTO product_section_items (section_id, text, sort_order) VALUES (?,?,?)');
    if ($replaceSections) $pdo->prepare('DELETE FROM product_sections WHERE product_id = ?')->execute([$pid]);
    foreach ($replaceSections ? $sections : [] as $n => $s) {
        $insSec->execute([$pid, $s['key'], $s['heading'], $n]);
        $sid = (int)$pdo->lastInsertId();
        foreach ($s['items'] as $j => $t) $insItem->execute([$sid, $t, $j]);
    }
    // Replace the variant set (order history keeps its own snapshot of labels/prices).
    $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$pid]);
    $ins = $pdo->prepare('INSERT INTO product_variants (product_id, label, price, sort_order) VALUES (?,?,?,?)');
    $i = 0;
    foreach ($sizes as $s) $ins->execute([$pid, $s['label'], $s['price'], $i++]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('newgen admin-products: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Could not save the product. Nothing was changed.']);
}
if ($existing && $existing['image_path'] !== $image) delete_if_orphan_upload($pdo, $existing['image_path']);
audit($admin, $existing ? 'product.update' : 'product.create', 'product', (string)$pid, $name);
respond(200, ['ok' => true, 'product' => load_products($pdo, $pid)[0]]);
