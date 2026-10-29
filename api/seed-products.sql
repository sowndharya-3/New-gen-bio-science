-- Initial Shop catalogue (idempotent: INSERT IGNORE). Run after schema.mysql.sql.
-- Existing rows are never overwritten, so admin edits survive a re-run.
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('sporogen-poultry','SporoGEN-WS','poultry','/assets/shop-sporogen-ws.webp','sporogen',1,0,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',1120,0 FROM products WHERE slug='sporogen-poultry';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('stresslyte-c','Stresslyte-C','poultry','/assets/shop-stresslyte-c.webp','stresslyte-c',1,1,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',295,0 FROM products WHERE slug='stresslyte-c';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('opti-ph','Opti-pH','poultry','/assets/shop-opti-ph.webp','opti-ph',1,2,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',260,0 FROM products WHERE slug='opti-ph';
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'5 Kg',1280,1 FROM products WHERE slug='opti-ph';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('spark-2x','Spark-2X','poultry','/assets/shop-spark-2x.webp','spark-2x',1,3,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',365,0 FROM products WHERE slug='spark-2x';
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'5 Kg',1550,1 FROM products WHERE slug='spark-2x';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('i-immune','I-Immune','poultry','/assets/shop-i-immune.webp','i-immune',1,4,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',1200,0 FROM products WHERE slug='i-immune';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('flavinta-v','Flavinta-V','ruminant','/assets/shop-flavinta-v.webp','flavinta',1,5,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',790,0 FROM products WHERE slug='flavinta-v';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('flavinta-mola','Flavinta-Mola','ruminant','/assets/shop-flavinta-mola.webp','flavinta',1,6,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',790,0 FROM products WHERE slug='flavinta-mola';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('milkma-shop','MilkMa','ruminant','/assets/MilkMa-new.webp','milkma',1,7,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Kg',460,0 FROM products WHERE slug='milkma-shop';
INSERT IGNORE INTO products (slug,name,category,image_path,catalog_key,is_active,sort_order,created_at,updated_at) VALUES ('breed-ezy-shop','Breed Ezy','ruminant','/assets/Breed-Ezy-new.webp','breed-ezy',1,8,1761264000,1761264000);
INSERT IGNORE INTO product_variants (product_id,label,price,sort_order) SELECT id,'1 Canister (20 Tablets)',585,0 FROM products WHERE slug='breed-ezy-shop';
