<?php
require_once __DIR__.'/welcome-migration.php';
require_once __DIR__.'/offers-migration.php';
require_once __DIR__.'/delivery-migration.php';
require_once __DIR__.'/promo-migration.php';
require_once __DIR__.'/governorate-migration.php';
require_once __DIR__.'/variants-migration.php';
require_once __DIR__.'/email-verification-migration.php';
// Same schema on SQLite (local) and MySQL/MariaDB (Hostinger). Money is integer cents.
return [
"CREATE TABLE IF NOT EXISTS users (id VARCHAR(32) PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL UNIQUE, phone VARCHAR(40) NOT NULL DEFAULT '', delivery_profile TEXT, password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'customer', active INTEGER NOT NULL DEFAULT 1, auth_version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS categories (id VARCHAR(32) PRIMARY KEY, parent_id VARCHAR(32), name VARCHAR(120) NOT NULL, slug VARCHAR(160) NOT NULL UNIQUE, description TEXT NOT NULL, image TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(parent_id) REFERENCES categories(id) ON DELETE SET NULL)",
"CREATE TABLE IF NOT EXISTS products (id VARCHAR(32) PRIMARY KEY, category_id VARCHAR(32), name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL UNIQUE, short_description TEXT NOT NULL, description TEXT NOT NULL, price INTEGER NOT NULL, sale_price INTEGER, images TEXT NOT NULL, stock INTEGER NOT NULL DEFAULT 0, size_enabled INTEGER NOT NULL DEFAULT 0, color_enabled INTEGER NOT NULL DEFAULT 0, variant_revision INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, featured INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL)",
...delivery_schema(),
...governorate_schema(),
"CREATE TABLE IF NOT EXISTS orders (id VARCHAR(32) PRIMARY KEY, reference VARCHAR(30) NOT NULL UNIQUE, user_id VARCHAR(32), customer_name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL, phone VARCHAR(40) NOT NULL, address TEXT NOT NULL, city VARCHAR(120) NOT NULL, notes TEXT NOT NULL, internal_notes TEXT NOT NULL, subtotal INTEGER NOT NULL, promo_discount INTEGER NOT NULL DEFAULT 0, promo_shipping_discount INTEGER NOT NULL DEFAULT 0, promo_snapshot TEXT, delivery_area_id VARCHAR(32), delivery_snapshot TEXT, delivery_governorate VARCHAR(32), offer_discount INTEGER NOT NULL DEFAULT 0, offer_snapshot TEXT, welcome_discount INTEGER NOT NULL DEFAULT 0, welcome_promotion_id VARCHAR(32), welcome_percentage INTEGER NOT NULL DEFAULT 0, delivery_fee INTEGER NOT NULL, tax INTEGER NOT NULL, total INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, status VARCHAR(30) NOT NULL, payment_method VARCHAR(30) NOT NULL, payment_status VARCHAR(30) NOT NULL, idempotency_key VARCHAR(64) NOT NULL UNIQUE, stock_released INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT fk_order_delivery FOREIGN KEY(delivery_area_id) REFERENCES delivery_areas(id) ON DELETE SET NULL, CONSTRAINT fk_order_governorate FOREIGN KEY(delivery_governorate) REFERENCES governorate_rates(id) ON DELETE RESTRICT)",
"CREATE TABLE IF NOT EXISTS order_items (id VARCHAR(32) PRIMARY KEY, order_id VARCHAR(32) NOT NULL, product_id VARCHAR(32), product_name VARCHAR(160) NOT NULL, unit_price INTEGER NOT NULL, quantity INTEGER NOT NULL, image TEXT NOT NULL, variant_id VARCHAR(32), variant_snapshot TEXT, line_discount INTEGER NOT NULL DEFAULT 0, final_line_total INTEGER, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL)",
"CREATE TABLE IF NOT EXISTS reviews (id VARCHAR(32) PRIMARY KEY, user_id VARCHAR(32), product_id VARCHAR(32), name VARCHAR(120) NOT NULL, rating INTEGER NOT NULL, comment TEXT NOT NULL, status VARCHAR(20) NOT NULL, featured INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL)",
"CREATE TABLE IF NOT EXISTS settings (id VARCHAR(32) PRIMARY KEY, value TEXT NOT NULL, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS homepage_sections (id VARCHAR(32) PRIMARY KEY, title VARCHAR(200) NOT NULL, description TEXT NOT NULL, image TEXT NOT NULL, cta_text VARCHAR(80) NOT NULL, cta_url TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS pages (id VARCHAR(32) PRIMARY KEY, slug VARCHAR(160) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, meta_title VARCHAR(200) NOT NULL, meta_description TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS navigation (id VARCHAR(32) PRIMARY KEY, label VARCHAR(80) NOT NULL, url TEXT NOT NULL, location VARCHAR(20) NOT NULL, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0)",
"CREATE TABLE IF NOT EXISTS promotions (id VARCHAR(32) PRIMARY KEY, title VARCHAR(200) NOT NULL, description TEXT NOT NULL, image TEXT NOT NULL, cta_text VARCHAR(80) NOT NULL, cta_url TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS messages (id VARCHAR(32) PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(254) NOT NULL, message TEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS audit_log (id VARCHAR(32) PRIMARY KEY, user_id VARCHAR(32), action VARCHAR(60) NOT NULL, entity VARCHAR(40) NOT NULL, entity_id VARCHAR(64) NOT NULL, created_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS rate_limits (id VARCHAR(32) PRIMARY KEY, bucket VARCHAR(64) NOT NULL, created_at VARCHAR(19) NOT NULL)",
welcome_schema_sql(),
...offers_schema(),
...promo_schema(),
...variants_schema(),
...email_verification_schema(),
];

