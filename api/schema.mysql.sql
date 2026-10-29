-- NewGen Shop schema (fresh install). Safe to re-run; uses IF NOT EXISTS.
-- Existing databases: do NOT re-import this file to upgrade; use migration-2026-10.sql instead.
CREATE TABLE IF NOT EXISTS orders (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id      VARCHAR(20)  NOT NULL,
    created_at     INT UNSIGNED NOT NULL,            -- unix time (UTC)
    updated_at     INT UNSIGNED NOT NULL,
    customer_name  VARCHAR(100) NOT NULL,
    mobile         VARCHAR(15)  NOT NULL,
    email          VARCHAR(150) NOT NULL,
    address        VARCHAR(500) NOT NULL,
    city           VARCHAR(80)  NOT NULL,
    state          VARCHAR(80)  NOT NULL,
    pincode        VARCHAR(6)   NOT NULL,
    total_amount   INT UNSIGNED NOT NULL,            -- whole rupees
    order_status   VARCHAR(20)  NOT NULL DEFAULT 'received',
    payment_status VARCHAR(20)  NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(20)  NULL,                -- cod | razorpay; NULL = placed before payment methods existed
    razorpay_order_id VARCHAR(40) NULL,              -- Razorpay order created by the server for online payments
    client_token   CHAR(32)     NULL,                -- per-checkout token: a repeated submit returns the same order
    UNIQUE KEY uq_orders_public_id (public_id),
    UNIQUE KEY uq_orders_client_token (client_token),
    UNIQUE KEY uq_orders_razorpay (razorpay_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    product_id   VARCHAR(60)  NOT NULL,
    product_name VARCHAR(120) NOT NULL,
    size_label   VARCHAR(60)  NOT NULL,
    unit_price   INT UNSIGNED NOT NULL,
    qty          INT UNSIGNED NOT NULL,
    line_total   INT UNSIGNED NOT NULL,
    KEY idx_items_order (order_id),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_status_history (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    status     VARCHAR(20)  NOT NULL,
    changed_at INT UNSIGNED NOT NULL,
    changed_by VARCHAR(60)  NULL,                    -- staff username; NULL = customer/system
    KEY idx_hist_order (order_id),
    CONSTRAINT fk_hist_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment audit trail, separate from order status. One row per payment received,
-- refund recorded, or failed online attempt. (kind, reference) is unique, so a repeated
-- gateway callback or a re-entered transaction number cannot be recorded twice.
CREATE TABLE IF NOT EXISTS payments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    kind        VARCHAR(10)  NOT NULL,               -- payment | refund | failure
    method      VARCHAR(20)  NOT NULL,               -- razorpay | cod | cash | upi | bank_transfer | cheque | other
    amount      INT UNSIGNED NOT NULL,               -- whole rupees
    reference   VARCHAR(80)  NULL,                   -- gateway payment id or staff-entered transaction number
    paid_at     INT UNSIGNED NOT NULL,               -- when the money moved (unix time, UTC)
    note        VARCHAR(300) NULL,
    source      VARCHAR(10)  NOT NULL,               -- admin | razorpay
    recorded_by VARCHAR(60)  NULL,                   -- staff username; NULL for gateway events
    created_at  INT UNSIGNED NOT NULL,
    KEY idx_pay_order (order_id),
    UNIQUE KEY uq_pay_ref (kind, reference),
    CONSTRAINT fk_pay_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_hits (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip_hash    CHAR(64)    NOT NULL,
    action     VARCHAR(20) NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    KEY idx_rate (ip_hash, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(20)  NOT NULL,                -- stable key stored in products.category; never changes
    name       VARCHAR(40)  NOT NULL,                -- display name; editable
    sort_order INT          NOT NULL DEFAULT 0,
    created_at INT UNSIGNED NOT NULL,
    updated_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_category_slug (slug),
    UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(60)  NOT NULL,               -- stable key used in URLs, carts and order items
    name        VARCHAR(80)  NOT NULL,
    category    VARCHAR(20)  NOT NULL,               -- categories.slug
    description TEXT NULL,                           -- main description paragraph
    subtitle    VARCHAR(120) NULL,                   -- shown directly under the product name
    description_heading VARCHAR(120) NULL,           -- heading shown above the description
    deleted_at  INT UNSIGNED NULL,                   -- soft delete: hidden everywhere, order history untouched
    image_path  VARCHAR(200) NULL,                   -- /assets/... or /uploads/products/...
    catalog_key VARCHAR(60)  NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  INT UNSIGNED NOT NULL,
    updated_at  INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_products_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_variants (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    label      VARCHAR(60)  NOT NULL,                -- pack size, e.g. '1 Kg'
    price      INT UNSIGNED NOT NULL,                -- whole rupees
    sort_order INT          NOT NULL DEFAULT 0,
    UNIQUE KEY uq_variant (product_id, label),
    CONSTRAINT fk_variant_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product detail sections (Benefits, Features, Mode of Action, Recommendation, Presentation).
CREATE TABLE IF NOT EXISTS product_sections (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED NOT NULL,
    section_key VARCHAR(20)  NOT NULL,               -- benefits | features | mode_of_action | recommendation | presentation
    heading     VARCHAR(80)  NOT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    UNIQUE KEY uq_product_section (product_id, section_key),
    CONSTRAINT fk_section_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_section_items (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    text       VARCHAR(500) NOT NULL,                -- one bullet point
    sort_order INT          NOT NULL DEFAULT 0,
    KEY idx_item_section (section_id),
    CONSTRAINT fk_item_section FOREIGN KEY (section_id) REFERENCES product_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(40)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    INT UNSIGNED NOT NULL,
    last_login_at INT UNSIGNED NULL,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who changed what in the admin panel (categories, products, order status, payments).
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(40)  NOT NULL,
    action         VARCHAR(40)  NOT NULL,
    entity         VARCHAR(30)  NOT NULL,
    entity_id      VARCHAR(60)  NULL,
    detail         VARCHAR(500) NULL,
    created_at     INT UNSIGNED NOT NULL,
    KEY idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Records which one-time data seeds have already run, so a re-import never overwrites admin edits.
CREATE TABLE IF NOT EXISTS schema_migrations (
    name       VARCHAR(60)  NOT NULL PRIMARY KEY,
    applied_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
