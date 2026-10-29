<?php
declare(strict_types=1);
// Staff-only product category management. A category's slug (stored in products.category) never changes;
// renaming only changes the display name. A category that still has products cannot be deleted until
// those products are moved to another category.
require __DIR__ . '/bootstrap.php';

$admin = require_admin();
$pdo = db();

function categories_out(PDO $pdo): array {
    $counts = [];
    foreach ($pdo->query('SELECT category, COUNT(*) c FROM products WHERE deleted_at IS NULL GROUP BY category') as $r) $counts[$r['category']] = (int)$r['c'];
    return array_map(fn($c) => ['id' => (int)$c['id'], 'slug' => $c['slug'], 'name' => $c['name'], 'productCount' => $counts[$c['slug']] ?? 0],
        $pdo->query('SELECT id, slug, name FROM categories ORDER BY sort_order, id')->fetchAll());
}

function name_taken(PDO $pdo, string $name, ?int $exceptId): bool {
    $q = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?)');
    $q->execute([$name]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) if ((int)$id !== $exceptId) return true;
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') respond(200, ['ok' => true, 'categories' => categories_out($pdo)]);

require_method('POST');
require_csrf();
$in = read_json();
$action = $in['action'] ?? '';

if ($action === 'create' || $action === 'rename') {
    $name = safe_label($in['name'] ?? null, 2, 40);
    if (!$name) respond(422, ['ok' => false, 'error' => 'Enter a category name (2-40 letters/numbers; no < > " characters).']);
    $id = null;
    if ($action === 'rename') {
        $id = $in['id'] ?? null;
        if (!is_int($id)) respond(422, ['ok' => false, 'error' => 'Invalid category.']);
        $q = $pdo->prepare('SELECT id, name FROM categories WHERE id = ?');
        $q->execute([$id]);
        $cur = $q->fetch();
        if (!$cur) respond(404, ['ok' => false, 'error' => 'Category not found.']);
    }
    if (name_taken($pdo, $name, $id)) respond(409, ['ok' => false, 'error' => 'A category with this name already exists.']);
    $now = time();
    try {
        if ($action === 'rename') {
            $pdo->prepare('UPDATE categories SET name = ?, updated_at = ? WHERE id = ?')->execute([$name, $now, $id]);
            audit($admin, 'category.rename', 'category', (string)$id, $cur['name'] . ' -> ' . $name);
        } else {
            $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)), '-') ?: 'category';
            $slug = $base = substr($base, 0, 20);
            for ($i = 2; ; $i++) {
                $c = $pdo->prepare('SELECT 1 FROM categories WHERE slug = ?');
                $c->execute([$slug]);
                if (!$c->fetchColumn()) break;
                $slug = substr($base, 0, 17) . '-' . $i;
            }
            $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
            $pdo->prepare('INSERT INTO categories (slug, name, sort_order, created_at, updated_at) VALUES (?,?,?,?,?)')->execute([$slug, $name, $order, $now, $now]);
            audit($admin, 'category.create', 'category', $slug, $name);
        }
    } catch (Throwable $e) {
        if (is_duplicate_key($e)) respond(409, ['ok' => false, 'error' => 'A category with this name already exists.']);
        error_log('newgen admin-categories: ' . $e->getMessage());
        respond(500, ['ok' => false, 'error' => 'Could not save the category.']);
    }
    respond(200, ['ok' => true, 'categories' => categories_out($pdo)]);
}

if ($action === 'delete') {
    $id = $in['id'] ?? null;
    $reassign = $in['reassignTo'] ?? null;
    if (!is_int($id)) respond(422, ['ok' => false, 'error' => 'Invalid category.']);
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id, slug, name FROM categories WHERE id = ?');
        $q->execute([$id]);
        $cat = $q->fetch();
        if (!$cat) { $pdo->rollBack(); respond(404, ['ok' => false, 'error' => 'Category not found.']); }
        if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() <= 1) {
            $pdo->rollBack(); respond(409, ['ok' => false, 'error' => 'At least one category must remain.']);
        }
        $q = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category = ? AND deleted_at IS NULL');
        $q->execute([$cat['slug']]);
        $n = (int)$q->fetchColumn();
        $moved = 0;
        if ($n > 0) {
            $target = null;
            if (is_string($reassign) && $reassign !== $cat['slug']) {
                $t = $pdo->prepare('SELECT slug FROM categories WHERE slug = ?');
                $t->execute([$reassign]);
                $target = $t->fetchColumn() ?: null;
            }
            if (!$target) {
                $pdo->rollBack();
                respond(409, ['ok' => false, 'productCount' => $n,
                    'error' => $n . ' product' . ($n === 1 ? ' still uses' : 's still use') . ' this category. Move ' . ($n === 1 ? 'it' : 'them') . ' to another category first.']);
            }
            // every product row (including soft-deleted ones) so nothing keeps pointing at a removed category
            $pdo->prepare('UPDATE products SET category = ?, updated_at = ? WHERE category = ?')->execute([$target, time(), $cat['slug']]);
            $moved = $n;
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('newgen admin-categories: ' . $e->getMessage());
        respond(500, ['ok' => false, 'error' => 'Could not delete the category. Nothing was changed.']);
    }
    audit($admin, 'category.delete', 'category', $cat['slug'], $cat['name'] . ($moved ? ' (moved ' . $moved . ' product(s) to ' . $target . ')' : ''));
    respond(200, ['ok' => true, 'categories' => categories_out($pdo)]);
}

respond(400, ['ok' => false, 'error' => 'Invalid request.']);
