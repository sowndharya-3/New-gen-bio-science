-- NewGen Shop: November 2026 update for EXISTING databases (run after migration-2026-10.sql).
-- Safe to run more than once: each statement changes nothing the second time.

-- 1. Shop product URL slugs requested by the client (/shop/product/<slug>).
--    Old order_items rows keep the old product_id text; they are display-only and unaffected.
UPDATE products SET slug = 'sporogen'  WHERE slug = 'sporogen-poultry';
UPDATE products SET slug = 'milkma'    WHERE slug = 'milkma-shop';
UPDATE products SET slug = 'breed-ezy' WHERE slug = 'breed-ezy-shop';

-- 2. SporoGEN-WS: the "Complete Gut Health Solution" heading above the description was removed.
UPDATE products SET description_heading = NULL WHERE slug = 'sporogen' AND description_heading = 'Complete Gut Health Solution';

INSERT IGNORE INTO schema_migrations (name, applied_at) VALUES ('2026-11-slugs', UNIX_TIMESTAMP());

-- Verify:
-- SELECT slug, name FROM products ORDER BY sort_order;   -- expect sporogen, milkma, breed-ezy among the slugs
