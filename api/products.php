<?php
declare(strict_types=1);
// Public: the active Shop catalogue. Single source of truth for the Shop pages;
// create-order.php prices orders from the same tables. Inactive and deleted products are never returned.
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/payment-lib.php';
require_method('GET');

$pdo = db();
$rows = $pdo->query('SELECT id, slug, name, category, description, subtitle, description_heading, image_path, catalog_key
                     FROM products WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order, id')->fetchAll();

$variants = [];
foreach ($pdo->query('SELECT product_id, label, price FROM product_variants ORDER BY sort_order, id')->fetchAll() as $v) {
    $variants[$v['product_id']][] = ['label' => $v['label'], 'price' => (int)$v['price']];
}

// Detail sections with their bullet points, in the order set in the admin panel. Empty sections are skipped.
$sections = [];
$bySection = [];
foreach ($pdo->query('SELECT section_id, text FROM product_section_items ORDER BY sort_order, id')->fetchAll() as $i) {
    $bySection[$i['section_id']][] = $i['text'];
}
foreach ($pdo->query('SELECT id, product_id, section_key, heading FROM product_sections ORDER BY sort_order, id')->fetchAll() as $s) {
    if (!empty($bySection[$s['id']])) {
        $sections[$s['product_id']][] = ['key' => $s['section_key'], 'heading' => $s['heading'], 'items' => $bySection[$s['id']]];
    }
}

$out = [];
foreach ($rows as $r) {
    if (empty($variants[$r['id']])) continue;               // not purchasable without a pack size
    $out[] = [
        'id'                 => $r['slug'],
        'name'               => $r['name'],
        'category'           => $r['category'],
        'subtitle'           => $r['subtitle'],
        'descriptionHeading' => $r['description_heading'],
        'description'        => $r['description'],
        'sections'           => $sections[$r['id']] ?? [],
        'image'              => $r['image_path'],
        'catalogKey'         => $r['catalog_key'],
        'sizes'              => $variants[$r['id']],
    ];
}
$categories = $pdo->query('SELECT slug, name FROM categories ORDER BY sort_order, id')->fetchAll();
respond(200, ['ok' => true, 'products' => $out, 'categories' => $categories, 'payments' => payment_options()]);
