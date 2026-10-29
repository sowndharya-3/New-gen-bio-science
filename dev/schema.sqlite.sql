-- DEV/TEST ONLY (local PHP built-in server). Production uses api/schema.mysql.sql.
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT NOT NULL UNIQUE,
    created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL,
    customer_name TEXT NOT NULL, mobile TEXT NOT NULL, email TEXT NOT NULL, address TEXT NOT NULL,
    city TEXT NOT NULL, state TEXT NOT NULL, pincode TEXT NOT NULL, total_amount INTEGER NOT NULL,
    order_status TEXT NOT NULL DEFAULT 'received', payment_status TEXT NOT NULL DEFAULT 'pending',
    payment_method TEXT, razorpay_order_id TEXT UNIQUE, client_token TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id TEXT NOT NULL, product_name TEXT NOT NULL, size_label TEXT NOT NULL,
    unit_price INTEGER NOT NULL, qty INTEGER NOT NULL, line_total INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS order_status_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    status TEXT NOT NULL, changed_at INTEGER NOT NULL, changed_by TEXT);
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    kind TEXT NOT NULL, method TEXT NOT NULL, amount INTEGER NOT NULL, reference TEXT, paid_at INTEGER NOT NULL,
    note TEXT, source TEXT NOT NULL, recorded_by TEXT, created_at INTEGER NOT NULL, UNIQUE (kind, reference));
CREATE TABLE IF NOT EXISTS rate_hits (
    id INTEGER PRIMARY KEY AUTOINCREMENT, ip_hash TEXT NOT NULL, action TEXT NOT NULL, created_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    sort_order INTEGER NOT NULL DEFAULT 0, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL, category TEXT NOT NULL,
    description TEXT, subtitle TEXT, description_heading TEXT, deleted_at INTEGER,
    image_path TEXT, catalog_key TEXT, is_active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS product_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    label TEXT NOT NULL, price INTEGER NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, UNIQUE (product_id, label));
CREATE TABLE IF NOT EXISTS product_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    section_key TEXT NOT NULL, heading TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, UNIQUE (product_id, section_key));
CREATE TABLE IF NOT EXISTS product_section_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, section_id INTEGER NOT NULL REFERENCES product_sections(id) ON DELETE CASCADE,
    text TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1, created_at INTEGER NOT NULL, last_login_at INTEGER);
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT, admin_username TEXT NOT NULL, action TEXT NOT NULL, entity TEXT NOT NULL,
    entity_id TEXT, detail TEXT, created_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS schema_migrations (name TEXT NOT NULL PRIMARY KEY, applied_at INTEGER NOT NULL);
