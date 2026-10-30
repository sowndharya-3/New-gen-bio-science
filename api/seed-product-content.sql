-- Initial product content for the Shop products (subtitle, description, detail sections, categories).
-- Fresh install order: schema.mysql.sql, seed-products.sql, then this file. Existing sites: use migration-2026-10.sql instead.
-- Runs once (recorded in schema_migrations); re-importing never overwrites edits made in the admin panel.

SET NAMES utf8mb4;   -- the text contains characters such as the trademark sign

-- ---- Initial product content (one-time seed; guarded by schema_migrations so admin edits are never overwritten) ----

INSERT INTO categories (slug, name, sort_order, created_at, updated_at)
SELECT 'poultry', 'Poultry', 0, 1761264000, 1761264000 FROM (SELECT 1 AS one) d
WHERE NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'poultry');

INSERT INTO categories (slug, name, sort_order, created_at, updated_at)
SELECT 'ruminant', 'Ruminant', 1, 1761264000, 1761264000 FROM (SELECT 1 AS one) d
WHERE NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'ruminant');

INSERT INTO categories (slug, name, sort_order, created_at, updated_at)
SELECT 'aquaculture', 'Aquaculture', 2, 1761264000, 1761264000 FROM (SELECT 1 AS one) d
WHERE NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'aquaculture');

INSERT INTO categories (slug, name, sort_order, created_at, updated_at)
SELECT 'companion', 'Companion', 3, 1761264000, 1761264000 FROM (SELECT 1 AS one) d
WHERE NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'companion');

UPDATE products SET name = 'SporoGEN-WS' WHERE slug = 'sporogen-poultry' AND name = 'SporoGEN Poultry' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'ENRICH THE INTESTINAL HEALTH'), description = COALESCE(description, 'SporoGEN™ is a complete gut health solution formulated with a unique blend of new-generation probiotic spores and prebiotics to support optimal intestinal health and performance in poultry. It contains multi-strain Bacillus spores, yeast, and water-soluble oligosaccharides that help establish a healthy gut microflora, improve nutrient utilization, and reduce enteric challenges.') WHERE slug = 'sporogen-poultry' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Stress-Free Solution'), description = COALESCE(description, 'Stresslyte-C™ is an electrolytes formulation designed to help poultry cope with stress factors. It is a balanced combination of electrolytes, Vitamin C, Betaine, probiotics, and energy sources formulated to restore electrolyte balance, maintain acid–base equilibrium, and improve bird performance during stressful conditions.') WHERE slug = 'stresslyte-c' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Water Acidifier'), description = COALESCE(description, 'Opti-pH™ is an acidifier formulation with a synergistic combination of buffered organic acids, antimicrobial agents, and essential oils. It helps inhibit a wide range of pathogens in poultry drinking water and the gastrointestinal tract. Opti-pH™ supports gut health by reducing gastric pH, improving nutrient digestion, and promoting the growth of beneficial gut bacteria.') WHERE slug = 'opti-ph' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Nutritional Growth Promoter'), description = COALESCE(description, 'Spark-2X™ is a nutritional growth promoter designed to improve growth rate, feed efficiency, and overall performance in poultry. It is a unique combination of highly bioavailable nutrients, essential amino acids, vitamins, trace minerals, enzymes, and functional growth promoters, supporting rapid and uniform growth with better return on investment.') WHERE slug = 'spark-2x' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'New Generation Immunostimulant'), description = COALESCE(description, 'i-IMMUNE™ is a new-generation immunostimulant designed to strengthen both innate and adaptive immune responses in poultry. It is a synergistic blend of organic selenium, beta-glucans, seaweed polysaccharides, vitamins, minerals, selenobiotic, and organic chromium, formulated to improve vaccine response, enhance disease resistance, and reduce oxidative stress during production and stress conditions.') WHERE slug = 'i-immune' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Most Preferred Flavour for Dairy Cattle'), description = COALESCE(description, 'Flavinta™ is a premium flavour solution developed to enhance feed palatability and intake in dairy cattle. With its long-lasting sensory effects and stable formulation, Flavinta™ improves the smell and taste of feed, ensures consistent feed acceptance, and supports optimal feed consumption for better productivity.') WHERE slug = 'flavinta-v' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Most Preferred Flavour for Dairy Cattle'), description = COALESCE(description, 'Flavinta™ is a premium flavour solution developed to enhance feed palatability and intake in dairy cattle. With its long-lasting sensory effects and stable formulation, Flavinta™ improves the smell and taste of feed, ensures consistent feed acceptance, and supports optimal feed consumption for better productivity.') WHERE slug = 'flavinta-mola' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Ideal milk replacer for Calf'), description = COALESCE(description, 'MilkMa™ is a high-quality milk replacer formulated to support healthy growth, immunity, and early development in calves. It provides a balanced supply of energy, proteins, fats, vitamins, and minerals to ensure optimal nutrition during the critical early stages of life.') WHERE slug = 'milkma-shop' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

UPDATE products SET subtitle = COALESCE(subtitle, 'Bolus for Better Reproduction'), description = COALESCE(description, 'Breed-Ezy™ is an innovative, proven, safe and highly bioavailable mineral bolus enriched with essential vitamins and fatty acids. It is specially formulated to enhance reproductive performance, improve fertility, and support overall reproductive health in cattle.') WHERE slug = 'breed-ezy-shop' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'sporogen-poultry' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Improves intestinal health in poultry' AS x UNION ALL SELECT 1, 'Promotes the growth of beneficial gut bacteria' UNION ALL SELECT 2, 'Enhances nutrient utilization' UNION ALL SELECT 3, 'Improves body weight gain and Feed Conversion Ratio (FCR)') t
WHERE p.slug = 'sporogen-poultry' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Micro-encapsulated yeast formulation' AS x UNION ALL SELECT 1, 'Proven strains of beneficial microbes' UNION ALL SELECT 2, 'Microbes present in the form of active spores' UNION ALL SELECT 3, 'Resistant to a wide range of pH and temperature' UNION ALL SELECT 4, 'Contains Mannan Oligo Saccharides (MOS – water soluble)') t
WHERE p.slug = 'sporogen-poultry' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Active probiotic spores survive feed processing and gastric conditions' AS x UNION ALL SELECT 1, 'Colonize the gut and competitively exclude pathogenic bacteria' UNION ALL SELECT 2, 'Improve gut microflora balance and villi integrity' UNION ALL SELECT 3, 'Enhance digestive enzyme activity and nutrient absorption' UNION ALL SELECT 4, 'Support immune response and gut barrier function') t
WHERE p.slug = 'sporogen-poultry' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Available Variant: WS (Water Soluble), FS (Feed Supplement)' AS x UNION ALL SELECT 1, 'SporoGEN-FS: 25 kg bags' UNION ALL SELECT 2, 'SporoGEN-WS: 100 g, 250 g, 500 g, and 1 kg packs') t
WHERE p.slug = 'sporogen-poultry' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'SporoGEN-FS: 25 kg bags' AS x UNION ALL SELECT 1, 'SporoGEN-WS: 100 g, 250 g, 500 g, and 1 kg packs') t
WHERE p.slug = 'sporogen-poultry' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'stresslyte-c' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Better electrolyte balance and livability' AS x UNION ALL SELECT 1, 'Improves osmoregulation of cells' UNION ALL SELECT 2, 'Helps birds cope with heat stress' UNION ALL SELECT 3, 'Supports better performance and productivity') t
WHERE p.slug = 'stresslyte-c' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Balanced electrolyte formulation with Vitamin C and Betaine' AS x UNION ALL SELECT 1, 'Supports osmotic balance and hydration' UNION ALL SELECT 2, 'Helps maintain acid–base balance' UNION ALL SELECT 3, 'Provides quick energy during stress') t
WHERE p.slug = 'stresslyte-c' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Replenishes electrolytes lost during heat and environmental stress' AS x UNION ALL SELECT 1, 'Maintains acid–base balance and cellular hydration' UNION ALL SELECT 2, 'Vitamin C acts as an antioxidant and stress reducer' UNION ALL SELECT 3, 'Betaine supports osmotic balance and cellular protection' UNION ALL SELECT 4, 'Improves water intake and thermoregulation' UNION ALL SELECT 5, 'Reduces the incidence of necrotic enteritis') t
WHERE p.slug = 'stresslyte-c' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Water: 1–2 g / litre' AS x UNION ALL SELECT 1, 'Feed: 1–2 kg / MT of feed' UNION ALL SELECT 2, 'Use during heat stress or as recommended by a veterinarian / nutritionist') t
WHERE p.slug = 'stresslyte-c' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Packaging: 1 kg and 25 kg bags' AS x) t
WHERE p.slug = 'stresslyte-c' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'opti-ph' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Controls microbial load in drinking water' AS x UNION ALL SELECT 1, 'Promotes healthy gut microflora' UNION ALL SELECT 2, 'Helps maintain optimum gut pH' UNION ALL SELECT 3, 'Improves weight gain and livability' UNION ALL SELECT 4, 'Optimizes Feed Conversion Ratio (FCR)') t
WHERE p.slug = 'opti-ph' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Unique combination of organic acids with essential oils' AS x UNION ALL SELECT 1, 'Compatible with other feed additives' UNION ALL SELECT 2, 'Suitable for both hard and soft water' UNION ALL SELECT 3, 'Effective across a wide range of water pH' UNION ALL SELECT 4, 'Long-lasting antimicrobial effect') t
WHERE p.slug = 'opti-ph' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Direct killing of pathogens in drinking water and the gastrointestinal tract' AS x UNION ALL SELECT 1, 'Reduction of gastric pH' UNION ALL SELECT 2, 'Improved digestion and nutrient absorption' UNION ALL SELECT 3, 'Promotion of beneficial bacterial growth in the gut') t
WHERE p.slug = 'opti-ph' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Normal condition: 1 ml / 10 litre of drinking water' AS x UNION ALL SELECT 1, 'Disease condition: 1 ml / 5 litre of drinking water') t
WHERE p.slug = 'opti-ph' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Liquid formulation' AS x UNION ALL SELECT 1, '1 Litre and 5 Litre HDPE cans' UNION ALL SELECT 2, '25 kg pack') t
WHERE p.slug = 'opti-ph' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'spark-2x' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Improves weight gain' AS x UNION ALL SELECT 1, 'Enhances feed conversion ratio (FCR)' UNION ALL SELECT 2, 'Improves overall productivity' UNION ALL SELECT 3, 'Delivers better economic returns') t
WHERE p.slug = 'spark-2x' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Unique combination of functional growth nutrients' AS x UNION ALL SELECT 1, 'Highly bioavailable form of nutrients' UNION ALL SELECT 2, 'Rich in essential amino acids' UNION ALL SELECT 3, 'Provides extra energy and protein for growth' UNION ALL SELECT 4, 'Supports uniform growth and peak production') t
WHERE p.slug = 'spark-2x' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Supplies readily absorbable amino acids and protein hydrolysates to support rapid growth and muscle development' AS x UNION ALL SELECT 1, 'Enhances nutrient bioavailability, improving feed utilization and Feed Conversion Ratio (FCR)' UNION ALL SELECT 2, 'Provides essential minerals and energy to support metabolism, bone development, and peak production' UNION ALL SELECT 3, 'Supports gut health through yeast extracts, improving nutrient absorption and overall performance') t
WHERE p.slug = 'spark-2x' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Powder: Broilers, Breeders & Layers: 500 g / MT of feed' AS x UNION ALL SELECT 1, 'Liquid: Chicks: 20 ml / 100 birds; Broilers, Breeders & Layers: 15–20 ml / 100 birds') t
WHERE p.slug = 'spark-2x' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Spark-2X Powder: 1 kg and 25 kg packs' AS x UNION ALL SELECT 1, 'Spark-2X Liquid: 1 litre and 5 litre HDPE cans') t
WHERE p.slug = 'spark-2x' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'i-immune' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Improves vaccine response and antibody titres' AS x UNION ALL SELECT 1, 'Enhances disease resistance' UNION ALL SELECT 2, 'Reduces oxidative stress in poultry' UNION ALL SELECT 3, 'Strengthens innate and adaptive immunity' UNION ALL SELECT 4, 'Improves overall performance and production') t
WHERE p.slug = 'i-immune' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Ideal combination of advanced immunostimulants' AS x UNION ALL SELECT 1, 'Targets host-specific immune system' UNION ALL SELECT 2, 'Enhances phagocytosis and immune cell activation' UNION ALL SELECT 3, 'Modulates inflammatory responses' UNION ALL SELECT 4, 'Seaweed polysaccharides boost immune function and antibody production') t
WHERE p.slug = 'i-immune' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Seaweed polysaccharides stimulate immune modulation, inhibit viral infection, and enhance antioxidant activity' AS x UNION ALL SELECT 1, '1,3 Beta-glucans activate macrophages, natural killer cells, and complement pathways' UNION ALL SELECT 2, 'Alpha Mono Laurate enhances humoral and cell-mediated immunity and improves resilience against pathogens' UNION ALL SELECT 3, 'Coenzyme Q10 protects immune cells from oxidative damage, reduces inflammatory markers, and improves cellular energy metabolism') t
WHERE p.slug = 'i-immune' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Feed Supplement – FS: Breeders: 250–500 g / MT of feed; Broilers: 100–200 g / MT of feed; Layers: 250 g / MT of feed' AS x UNION ALL SELECT 1, 'Water Soluble – WS: Chicks: 50 ml / 1000 birds; Broilers & Layers: 100–200 ml / 1000 birds; Breeders: 200–300 ml / 1000 birds' UNION ALL SELECT 2, 'Or as recommended by a veterinarian or poultry nutritionist') t
WHERE p.slug = 'i-immune' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Powder / Liquid (as applicable)' AS x UNION ALL SELECT 1, '50 kg Bucket' UNION ALL SELECT 2, '500 ml Bottle' UNION ALL SELECT 3, '1 L HDPE Bottle') t
WHERE p.slug = 'i-immune' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'flavinta-v' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Increases cattle affinity towards feed' AS x UNION ALL SELECT 1, 'Ensures consistent feed consumption' UNION ALL SELECT 2, 'Improves overall feed acceptance' UNION ALL SELECT 3, 'Supports better productivity through improved feed intake') t
WHERE p.slug = 'flavinta-v' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Long-lasting sensory effects' AS x UNION ALL SELECT 1, 'Enhanced smell, taste, and palatability of feed' UNION ALL SELECT 2, 'Stable during feed processing' UNION ALL SELECT 3, 'Masks unpleasant feed odours') t
WHERE p.slug = 'flavinta-v' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Enhances sensory appeal of feed' AS x UNION ALL SELECT 1, 'Stimulates appetite and feed intake' UNION ALL SELECT 2, 'Reduces feed refusal caused by unpleasant odours') t
WHERE p.slug = 'flavinta-v' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, '500 g to 1 kg per ton of feed' AS x) t
WHERE p.slug = 'flavinta-v' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, '25 kg Bags' AS x) t
WHERE p.slug = 'flavinta-v' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'flavinta-mola' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Increases cattle affinity towards feed' AS x UNION ALL SELECT 1, 'Ensures consistent feed consumption' UNION ALL SELECT 2, 'Improves overall feed acceptance' UNION ALL SELECT 3, 'Supports better productivity through improved feed intake') t
WHERE p.slug = 'flavinta-mola' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Long-lasting sensory effects' AS x UNION ALL SELECT 1, 'Enhanced smell, taste, and palatability of feed' UNION ALL SELECT 2, 'Stable during feed processing' UNION ALL SELECT 3, 'Masks unpleasant feed odours') t
WHERE p.slug = 'flavinta-mola' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Enhances sensory appeal of feed' AS x UNION ALL SELECT 1, 'Stimulates appetite and feed intake' UNION ALL SELECT 2, 'Reduces feed refusal caused by unpleasant odours') t
WHERE p.slug = 'flavinta-mola' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, '500 g to 1 kg per ton of feed' AS x) t
WHERE p.slug = 'flavinta-mola' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, '25 kg Bags' AS x) t
WHERE p.slug = 'flavinta-mola' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'milkma-shop' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Ideal milk replacer which increases body weight gain and growth rate' AS x UNION ALL SELECT 1, 'Easily digestible for young calves' UNION ALL SELECT 2, 'Minimizes the risk of diarrhoea' UNION ALL SELECT 3, 'Ensures easy mixing and high palatability') t
WHERE p.slug = 'milkma-shop' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Fortified with essential vitamins and minerals' AS x UNION ALL SELECT 1, 'Provides optimal nourishment for newborn calves' UNION ALL SELECT 2, 'Easy to mix and highly palatable' UNION ALL SELECT 3, 'Low in fibre') t
WHERE p.slug = 'milkma-shop' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Supplies balanced energy, protein, vitamins, and minerals required for early growth' AS x UNION ALL SELECT 1, 'Provides easily digestible nutrients to support gut health and nutrient absorption' UNION ALL SELECT 2, 'Supports immunity and metabolic development during early life stages' UNION ALL SELECT 3, 'Ensures consistent nutrition when whole milk is insufficient') t
WHERE p.slug = 'milkma-shop' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Bottle feeding is recommended for convenient feeding, controlled nutrition, and to stimulate the natural suckling reflex in calves' AS x UNION ALL SELECT 1, '100–150 g of milk replacer in 1 litre of warm water for 2 months' UNION ALL SELECT 2, 'Follow feeding schedule as recommended by a veterinarian or calf nutrition expert') t
WHERE p.slug = 'milkma-shop' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Powder formulation' AS x UNION ALL SELECT 1, '100 g and 1 kg bags') t
WHERE p.slug = 'milkma-shop' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_sections (product_id, section_key, heading, sort_order)
SELECT p.id, t.k, t.h, t.n FROM products p JOIN (SELECT 'benefits' AS k, 'Benefits' AS h, 0 AS n UNION ALL SELECT 'features', 'Features', 1 UNION ALL SELECT 'mode_of_action', 'Mode of Action', 2 UNION ALL SELECT 'recommendation', 'Recommendation', 3 UNION ALL SELECT 'presentation', 'Presentation', 4) t
WHERE p.slug = 'breed-ezy-shop' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed') AND NOT EXISTS (SELECT 1 FROM product_sections s WHERE s.product_id = p.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Improves heat expression' AS x UNION ALL SELECT 1, 'Enhances conception rate' UNION ALL SELECT 2, 'Supports development of underdeveloped genitalia' UNION ALL SELECT 3, 'Helps manage delayed onset of puberty' UNION ALL SELECT 4, 'Promotes better fertility and reproductive efficiency') t
WHERE p.slug = 'breed-ezy-shop' AND s.section_key = 'benefits' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Unique combination of trace minerals, vitamins, beta-carotene, and essential fatty acids' AS x UNION ALL SELECT 1, 'Highly bioavailable formulation for better absorption' UNION ALL SELECT 2, 'Multi-purpose bolus supporting fertility, health, and welfare' UNION ALL SELECT 3, 'Designed to maintain optimal reproductive performance') t
WHERE p.slug = 'breed-ezy-shop' AND s.section_key = 'features' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'Beta-carotene supports reproductive efficiency by improving conception and pregnancy rates' AS x UNION ALL SELECT 1, 'Omega-3 fatty acids and CoQ10 stimulate PGF₂-alpha production' UNION ALL SELECT 2, 'Strengthens immune competence and uterine health' UNION ALL SELECT 3, 'Helps reduce uterine infections' UNION ALL SELECT 4, 'Promotes a healthier uterine environment for improved fertility outcomes') t
WHERE p.slug = 'breed-ezy-shop' AND s.section_key = 'mode_of_action' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, 'To improve heat: 2 boluses for 10 days during the oestrus cycle' AS x UNION ALL SELECT 1, 'To improve conception rate: 1 bolus for 10 days before and after AI' UNION ALL SELECT 2, 'Underdeveloped genitalia & delayed puberty: 2 boluses for 20 days') t
WHERE p.slug = 'breed-ezy-shop' AND s.section_key = 'recommendation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO product_section_items (section_id, text, sort_order)
SELECT s.id, t.x, t.n FROM product_sections s JOIN products p ON p.id = s.product_id JOIN (SELECT 0 AS n, '4 Boli / Strip' AS x UNION ALL SELECT 1, '20 Boluses in Plastic Container') t
WHERE p.slug = 'breed-ezy-shop' AND s.section_key = 'presentation' AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed')
AND NOT EXISTS (SELECT 1 FROM product_section_items i WHERE i.section_id = s.id);

INSERT INTO schema_migrations (name, applied_at) SELECT '2026-10-content-seed', 1761264000 FROM (SELECT 1 AS one) d WHERE NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '2026-10-content-seed');
