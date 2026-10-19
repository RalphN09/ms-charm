<?php
function offers_schema(): array { return [
"CREATE TABLE IF NOT EXISTS offers (id VARCHAR(32) PRIMARY KEY, name VARCHAR(160) NOT NULL, title VARCHAR(200) NOT NULL, description TEXT NOT NULL, image TEXT NOT NULL, cta_text VARCHAR(80) NOT NULL, cta_url TEXT NOT NULL, discount_type VARCHAR(20) NOT NULL, discount_value INTEGER NOT NULL, scope VARCHAR(20) NOT NULL, starts_at VARCHAR(19) NOT NULL, ends_at VARCHAR(19), state VARCHAR(20) NOT NULL, popup_enabled INTEGER NOT NULL, frequency VARCHAR(20) NOT NULL, priority INTEGER NOT NULL DEFAULT 0, minimum_amount INTEGER NOT NULL DEFAULT 0, maximum_discount INTEGER NOT NULL DEFAULT 0, total_limit INTEGER NOT NULL DEFAULT 0, customer_limit INTEGER NOT NULL DEFAULT 0, members_only INTEGER NOT NULL DEFAULT 0, deleted INTEGER NOT NULL DEFAULT 0, created_by VARCHAR(32), created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL)",
"CREATE TABLE IF NOT EXISTS offer_products (offer_id VARCHAR(32) NOT NULL, product_id VARCHAR(32) NOT NULL, excluded INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(offer_id,product_id), FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS offer_categories (offer_id VARCHAR(32) NOT NULL, category_id VARCHAR(32) NOT NULL, PRIMARY KEY(offer_id,category_id), FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE CASCADE, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS offer_redemptions (id VARCHAR(32) PRIMARY KEY, offer_id VARCHAR(32) NOT NULL, order_id VARCHAR(32) NOT NULL, user_id VARCHAR(32), customer_key VARCHAR(64) NOT NULL, amount INTEGER NOT NULL, snapshot TEXT NOT NULL, created_at VARCHAR(19) NOT NULL, UNIQUE(offer_id,order_id), FOREIGN KEY(offer_id) REFERENCES offers(id) ON DELETE RESTRICT, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL)",
"CREATE TABLE IF NOT EXISTS offer_mutex (id VARCHAR(32) PRIMARY KEY)"
]; }
function offers_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Migrate outside checkout.');
    foreach(offers_schema() as $sql)db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    foreach(['offer_discount'=>'INTEGER NOT NULL DEFAULT 0','offer_snapshot'=>'TEXT'] as $column=>$type){
        try{query('SELECT '.$column.' FROM orders WHERE 1=0');}catch(PDOException $e){
            if(!in_array((int)($e->errorInfo[1]??0),[1054],true) && !str_contains($e->getMessage(),'no such column'))throw $e;
            try{query('ALTER TABLE orders ADD COLUMN '.$column.' '.$type);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1060 && !str_contains($e->getMessage(),'duplicate column'))throw $e;}
        }
    }
    if(!row("SELECT id FROM offer_mutex WHERE id='checkout'"))try{insert_record('offer_mutex',['id'=>'checkout']);}catch(PDOException $e){if(!str_starts_with((string)$e->getCode(),'23'))throw $e;}
    // Composite index supports per-account limit checks; repeatable on both drivers.
    try{query('CREATE INDEX offer_customer_lookup ON offer_redemptions (offer_id,customer_key)');}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1061 && !str_contains($e->getMessage(),'already exists'))throw $e;}
}
