# NewGen Shop – Orders, Payments, Products & Admin Panel: Deployment Guide

This package is deployed by the hosting/deployment team; the developer has no access to the production server. The Hostinger deployment has **not yet passed the production smoke test** below – do not treat the system as production-ready until it has. Items marked **[DEPLOY TEAM]** need confirmation from the people who run the server.

## What this release adds (2026-10)

- **Product page content from the database:** subtitle (SporoGEN-WS now shows `ENRICH THE INTESTINAL HEALTH`), description heading, description, and five detail sections (Benefits, Features, Mode of Action, Recommendation, Presentation) with ordered bullet points – all editable in the admin panel.
- **Category management** (add, rename, safe delete with product reassignment) and **product Delete** (soft delete; order history is never touched).
- **Checkout payment methods:** Online Payment (Razorpay) and Cash on Delivery, with server-side verification, duplicate-submit protection, and a **Pay Now** retry.
- **Order management:** Payment Method / Payment Status / Order Status shown separately, status transitions, cancel with confirmation, **Record Payment** and **Record Refund**, payment history, and an admin audit log.
- Existing products, prices, images, orders and customers are preserved (additive database migration).

## What the system consists of

| Part | Files |
| --- | --- |
| Customer site | `index.html` (Shop, checkout, order confirmation, **My Orders** tracking by Order ID) |
| Public API | `api/products.php` (catalogue, categories, payment options), `api/create-order.php`, `api/track-order.php`, `api/razorpay-order.php` (Pay Now), `api/verify-payment.php`, `api/razorpay-webhook.php` |
| Staff API (login required) | `api/admin-auth.php`, `api/admin-products.php`, `api/admin-categories.php`, `api/admin-orders.php` |
| Admin panel | `admin/index.html` → `https://<site>/admin/` |
| Shared code | `api/bootstrap.php` (DB, sessions, CSRF, rate limiting, audit log), `api/payment-lib.php` (Razorpay helpers; blocked from the web by `.htaccess`) |
| Database | `api/schema.mysql.sql` (fresh install), `api/migration-2026-10.sql` (**existing** databases), `api/seed-products.sql` + `api/seed-product-content.sql` (fresh install data) |
| Uploads | `uploads/products/` (+ its `.htaccess`) – product images uploaded by staff |
| CLI helper | `tools/create-admin.php` (create/reset a staff login) |

**Single source of truth:** products, prices, categories and product content live in the database. The Shop pages read them through `api/products.php`, and `create-order.php` prices every order from the same tables; prices sent by the browser are ignored. The old `api/catalog.php` and the token-based `api/admin-status.php` were removed; all order-status and payment changes go through the signed-in admin panel (or Razorpay's signed webhook) only.

## Requirements **[DEPLOY TEAM]**

- Apache or LiteSpeed (Hostinger) honouring `.htaccess` (`AllowOverride All`, `mod_rewrite`). The `.htaccess` files are required – they protect `api/` internals and make `uploads/` non-executable.
- PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo` and **`curl`** (used to create Razorpay orders); `gd` strongly recommended (images are re-encoded and shrunk to 1200px; without it they are validated and stored as-is).
- MySQL/MariaDB database + user (SELECT/INSERT/UPDATE/DELETE/CREATE, and **ALTER** for the one-time migration).
- HTTPS on the production domain (session cookie is marked `Secure` automatically over HTTPS; Razorpay Checkout requires HTTPS).
- `uploads/products/` writable by the PHP user.
- A way to run SQL (phpMyAdmin/cPanel) and, ideally, SSH/CLI to run `tools/create-admin.php` (alternative below).
- If the host sets `upload_max_filesize` / `post_max_size` below 5 MB, raise them (the app rejects images over 5 MB).

## Upgrade an existing production site

The production database already has the earlier tables, so use the **migration**, not the schema file.

1. **Back up** the site files and export the database (phpMyAdmin → Export).
2. In phpMyAdmin select the site database → **Import** → `api/migration-2026-10.sql`.
   - It only adds columns, indexes and tables, and checks each one first, so it is safe to import again if something is interrupted.
   - Existing orders, customers, products, prices, pack sizes and images are not changed. Old orders show payment method **Not recorded**.
   - It seeds the four default categories and moves the existing product text (subtitle, description, detail sections) from the website into the database **once**, so it becomes editable in the admin panel. It also renames *SporoGEN Poultry* to **SporoGEN-WS** with the packaging subtitle. A marker row in `schema_migrations` stops it from ever overwriting admin edits on a re-run.
   - Verify: `SHOW TABLES;` now includes `categories`, `payments`, `product_sections`, `product_section_items`, `admin_audit_log`, `schema_migrations`; and `SELECT COUNT(*) FROM categories;` returns 4.
3. Upload `index.html`, `admin/` and the whole `api/` folder. **Do not overwrite** your server config (`newgen-config.php` or `api/config.php`) or anything in `uploads/products/`.
4. Check the live site and admin panel with the smoke test below. Cash on Delivery works immediately; online payment stays disabled until the Razorpay keys are added (next section).
5. Delete the `tools/` folder from the server if it is still there, and any `.sql` files containing password hashes.

Import the **migration first, then upload the code**: the new code expects the new tables and columns.

## Razorpay online payments (optional until ready)

Razorpay keys live **only** in the server config file – never in `index.html`, the database or the ZIP.

1. In the Razorpay dashboard create API keys (use **Test mode** keys first). Add to `newgen-config.php` (or `api/config.php`), see `api/config.sample.php`:

   ```php
   'razorpay_key_id'         => 'rzp_test_xxxxxxxx',
   'razorpay_key_secret'     => '...',
   'razorpay_webhook_secret' => '...',
   ```

2. In Razorpay → Settings → Webhooks add `https://<site>/api/razorpay-webhook.php`, the same secret, and the events `payment.captured`, `order.paid` and `payment.failed`. The webhook also records payments for customers who paid but closed the browser before returning to the site.
3. Place a test order with Razorpay **test** keys and confirm: it becomes **Paid** only after payment; a cancelled or failed payment leaves it Pending/Failed; Pay Now works. Only then switch to the live keys. **No real payment should be made without the owner's explicit approval.**
4. Until the keys are present, the checkout shows Online Payment as unavailable and offers Cash on Delivery only; the payment endpoints refuse requests.

## Fresh install (new database)

1. Create the database/user. In phpMyAdmin import, in this order: `api/schema.mysql.sql`, `api/seed-products.sql`, `api/seed-product-content.sql`.
2. Create the config from `api/config.sample.php` with the real DB credentials:
   - Preferred: save as `newgen-config.php` **one level above the web root**.
   - Otherwise `api/config.php` (the `.htaccess` blocks it; verify `https://<site>/api/config.php` returns 403).
3. Upload `index.html`, `.htaccess`, `assets/`, `api/`, `admin/`, `uploads/`, `tools/`.
   - **On every later deployment do NOT overwrite or delete `uploads/products/*.(jpg|png|webp)`** – those are the staff-uploaded images and the database points to them. Deploy code files only.
4. Create the first staff login (next section), then run the smoke test.

## Create the first staff login

**The admin panel cannot be used until a row exists in `admin_users` – the table is empty after the schema import.** Passwords are never stored in the code, the ZIP or the database; only a bcrypt hash is stored.

- **With SSH/CLI** (run from the site folder): `php tools/create-admin.php <username>` – it asks for a password (min 12 characters) and stores only the hash. Run again with the same username to reset a password.
- **Without CLI (typical on shared hosting):** generate a bcrypt hash on any computer (`php -r "echo password_hash('YOUR-LONG-PASSWORD', PASSWORD_DEFAULT);"`), then in phpMyAdmin select the database → **SQL** tab (or **Import** a `.sql` file containing the same statement):

  ```sql
  INSERT INTO admin_users (username, password_hash, is_active, created_at)
  VALUES ('alice', '<bcrypt hash>', 1, UNIX_TIMESTAMP());
  ```

  Usernames: 3–40 characters (letters, digits, `.` `_` `-`). Passwords: 12+ characters. Keep any `.sql` file with a hash **out of the website folder and the ZIP**, and delete it after importing.
- Verify: `SELECT username, is_active FROM admin_users;` returns the new user with `is_active = 1`, then sign in at `/admin/`.
- Reset a password without CLI: generate a new hash and `UPDATE admin_users SET password_hash = '<new hash>' WHERE username = 'alice';`
- To disable a user: `UPDATE admin_users SET is_active = 0 WHERE username = 'alice';` (takes effect immediately).
- There is no change-password screen in the admin panel yet. Rotate any credential that was shared in chat/email/tickets.
- Consider restricting `/admin/` by IP at the web-server level as an extra layer.

## Using the admin panel (`/admin/`)

### Orders tab

Counts (total, received, processing, shipped, delivered, cancelled, payment pending) come live from the database. Search by Order ID / name / mobile and filter by **Order Status**, **Payment Status** or payment method. **View Order** shows the customer, delivery address, items with the prices charged at purchase time, **Payment method**, **Payment Status** (with the payment reference when there is one), **Order Status**, the status history and the payment history.

- **Order Status** and **Payment Status** are separate. A Cash on Delivery order can be *Delivered* while payment is still *Pending*.
- **Change order status** offers only valid next steps. **Cancel order** asks for confirmation and is only possible before the order is shipped.
- **Record payment** (COD, cash, UPI, bank transfer, cheque, other): enter method, amount received, payment date, optional reference and notes. Do this **only after the money has actually been received**. The amount must equal the order total (partial payments are rejected), a payment cannot be recorded twice, and a reference number cannot be reused.
- **Record refund** appears for a cancelled order that was paid. It only records that you refunded the customer (do the refund in Razorpay or your bank first); the panel never moves money.

### Products tab

Lists image, name, subtitle, category, price range, pack sizes and status. Actions: **Edit**, **View**, **Deactivate / Activate** and **Delete**.

- **Add / Edit product:** name, category, **subtitle** (shown directly under the name), **description heading**, **description**, image (JPG/PNG/WebP up to 5 MB), pack sizes with whole-rupee prices, availability, and the **detail sections** editor: add any of the five sections, set each heading, add/edit/remove/reorder bullet points, reorder or remove whole sections. Sections without bullet points are not shown to customers.
- **Deactivate** hides a product from the Shop temporarily. **Delete** removes it from the Shop and from the panel's list, but the row is kept (soft delete), and past orders keep their own copy of name, pack and price – so order history and reports are never affected.
- **Manage categories:** add a category, rename it (it updates everywhere), or delete it. A category that still has products must have them moved to another category first; the last remaining category cannot be deleted.

### Public product page layout

Name → subtitle → description heading → description. Benefits / Features / Mode of Action appear beside the product and Recommendation / Presentation below it, each group in the order set in the panel.

## Order status and payment rules

| Order status now | Can change to |
| --- | --- |
| Received | Processing, Shipped, Cancelled |
| Processing | Received, Shipped, Cancelled |
| Shipped | Processing, Delivered |
| Delivered | final |
| Cancelled | final |

- **Online (Razorpay) orders** start as Payment *Pending* and become *Paid* only when the server verifies Razorpay's signature (checkout callback) or receives Razorpay's signed webhook, and the amount matches the order total. A failed attempt marks the payment *Failed*; the customer can retry with **Pay Now** (confirmation page or My Orders). Reaching the success page never marks anything paid.
- **Cash on Delivery orders** start as Payment *Pending* and stay so until staff record the payment.
- **Cancelling a paid order** does not refund it. The order shows *Cancelled* with payment *Paid* and a warning until a refund is recorded (*Refunded*).
- Every status change, payment and refund is logged with the staff username; changes to categories and products go to the **admin audit log** (`admin_audit_log` table; view it in phpMyAdmin – there is no screen for it yet).

## Troubleshooting admin login

| Symptom | Cause / fix |
| --- | --- |
| `Too many requests. Please try again later.` (HTTP 429) | Login throttle: 10 attempts per 15 minutes per IP. **Every** attempt counts, including successful ones and attempts made before the account existed. Wait 15 minutes, or clear it: `DELETE FROM rate_hits WHERE action = 'login';` |
| `Incorrect username or password.` (HTTP 401) | Wrong credentials, or no row in `admin_users` (check with `SELECT username, is_active FROM admin_users;`), or `is_active = 0`. Passwords are case-sensitive – paste rather than type, no trailing space. Stop after a few tries to avoid the 429 lockout. |
| The throttle trips on the very first careful attempt | The server may pass every visitor to PHP with the same IP (proxy/CDN), so all visitors share one bucket. Check: `SELECT ip_hash, COUNT(*) FROM rate_hits GROUP BY ip_hash;` – one hash with many hits from several people confirms it. Report it to the developer: the code must then read the real client IP from the host's trusted header. |
| Login succeeds but the panel keeps signing out | Site must be on HTTPS (cookie is `Secure`/`SameSite=Strict`, path `/api/`); sessions also expire after 2 hours idle. |
| `/api/admin-auth.php` returns 403/404 | `.htaccess` not honoured or `api/` not uploaded correctly. |
| Products or Orders fail to load after the upgrade | The migration was not imported (the new code needs the new columns/tables). Import `api/migration-2026-10.sql`. |

## Security notes

- Staff-only endpoints require a server-side session (HttpOnly, SameSite=Strict cookie scoped to `/api/`), a CSRF token header and a same-origin check on every change; accounts are re-checked on every request; 2-hour idle timeout; login limited to 10 attempts / 15 min / IP (keyed on PHP's `REMOTE_ADDR`; see Troubleshooting if the host sits behind a shared proxy).
- Payment status can only be changed by (a) a signed-in staff member through the admin API with a CSRF token, or (b) the server after verifying Razorpay's HMAC signature. Browser-supplied data never marks an order paid.
- Razorpay secrets exist only in the server config file. No secrets or tokens exist in frontend JavaScript or in the database. Customers never log in.
- Customer name/phone/email/address are returned only by the staff API; the public tracking API returns just Order ID, date, items, total, payment method and the two statuses.
- All admin-entered text is HTML-escaped when shown on the site; angle brackets are rejected on input.
- Uploaded files get random names, are re-encoded, and `uploads/products/.htaccess` blocks script execution.
- Prices always come from the database; browser-submitted prices are ignored.

## Known limitations

- Tested locally with PHP 8.3 against **MySQL 8.0** (Docker): the migration (applied twice, compared with a fresh install), the full API and browser suites, and Razorpay flows against a **mock** Razorpay API with real HMAC signatures. SQLite was used for quick runs only. **Not yet tested on the Hostinger production database** (different MySQL/MariaDB version, host and PHP build) and **not tested against the real Razorpay service** – repeat the smoke test there with Razorpay test keys **[DEPLOY TEAM]**.
- Partial payments are not supported (the recorded amount must equal the order total). Refunds are recorded manually; the panel does not call Razorpay's refund API.
- A product subtitle/description applies to the Shop page only; the Products & Services pages keep their own text.
- On the public page the five sections are grouped (three beside the product, two below it); order can be changed within each group.
- A category's internal key ("slug") never changes when it is renamed. Products are never hard-deleted, so a deleted product's name/slug stays reserved.
- The "Short description" and "Description" requirements are one field here: **Description** (shown below the Description heading).
- Milkma and Breed Ezy keep their existing images (no new photos were supplied). SporoGEN-WS uses the supplied SporoGEN-WS 1 kg pack photo; its URL stays `/shop/product/sporogen-poultry` so old links, carts and orders keep working.
- The admin audit log has no screen yet (query the table in phpMyAdmin).

## Production smoke test

1. Open `/shop`: all products show their images and the category filters match the admin categories; open SporoGEN-WS – name, subtitle `ENRICH THE INTESTINAL HEALTH`, description heading, description and sections show.
2. Checkout with **Cash on Delivery** → Order ID shown, cart cleared, confirmation says payment is Pending.
3. `/admin/` → sign in → the order appears with method *Cash on Delivery*; set it to *Shipped* then *Delivered*; payment is still *Pending*; **Record payment** → *Paid*.
4. `/my-orders` → enter the Order ID → shows the statuses and payment method; no personal details are shown.
5. With Razorpay **test** keys: place an online order, pay with a test card → *Paid* only afterwards; close the window instead → *Pending* with a working **Pay Now**.
6. Admin → Products: edit subtitle/description/bullets and reload the product page; add a category and a product in it; delete a test product and confirm old orders still show it.
7. Cancel a test order (confirmation required) and check the history; sign out; confirm `/api/admin-orders.php` returns 401 when opened directly.
