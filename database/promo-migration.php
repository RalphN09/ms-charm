<?php
function promo_schema(): array {return [
"CREATE TABLE IF NOT EXISTS promo_codes (id VARCHAR(32) PRIMARY KEY, code VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, kind VARCHAR(20) NOT NULL, discount_type VARCHAR(20) NOT NULL, discount_value INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, minimum_amount INTEGER NOT NULL DEFAULT 0, maximum_discount INTEGER NOT NULL DEFAULT 0, starts_at VARCHAR(19) NOT NULL, ends_at VARCHAR(19), active INTEGER NOT NULL DEFAULT 1, archived INTEGER NOT NULL DEFAULT 0, total_limit INTEGER NOT NULL DEFAULT 0, email_limit INTEGER NOT NULL DEFAULT 0, scope VARCHAR(20) NOT NULL, email_key VARCHAR(64), legacy_welcome_id VARCHAR(32) UNIQUE, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS promo_products (promo_id VARCHAR(32) NOT NULL, product_id VARCHAR(32) NOT NULL, PRIMARY KEY(promo_id,product_id), FOREIGN KEY(promo_id) REFERENCES promo_codes(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS promo_categories (promo_id VARCHAR(32) NOT NULL, category_id VARCHAR(32) NOT NULL, PRIMARY KEY(promo_id,category_id), FOREIGN KEY(promo_id) REFERENCES promo_codes(id) ON DELETE CASCADE, FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS welcome_email_claims (id VARCHAR(32) PRIMARY KEY, email_key VARCHAR(64) NOT NULL UNIQUE, promo_id VARCHAR(32) NOT NULL UNIQUE, created_at VARCHAR(19) NOT NULL, FOREIGN KEY(promo_id) REFERENCES promo_codes(id) ON DELETE RESTRICT)",
"CREATE TABLE IF NOT EXISTS promo_redemptions (id VARCHAR(32) PRIMARY KEY, promo_id VARCHAR(32) NOT NULL, order_id VARCHAR(32) NOT NULL UNIQUE, email_key VARCHAR(64) NOT NULL, state VARCHAR(20) NOT NULL, amount INTEGER NOT NULL, shipping_amount INTEGER NOT NULL, created_at VARCHAR(19) NOT NULL, finalized_at VARCHAR(19), FOREIGN KEY(promo_id) REFERENCES promo_codes(id) ON DELETE RESTRICT, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE RESTRICT)",
"CREATE TABLE IF NOT EXISTS promo_mutex (id VARCHAR(32) PRIMARY KEY)"
];}
function promo_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Migrate outside business transactions.');
    foreach(promo_schema() as $sql)db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    foreach(['promo_discount'=>'INTEGER NOT NULL DEFAULT 0','promo_shipping_discount'=>'INTEGER NOT NULL DEFAULT 0','promo_snapshot'=>'TEXT'] as $col=>$type){
        try{query('SELECT '.$col.' FROM orders WHERE 1=0');}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1054&&!str_contains($e->getMessage(),'no such column'))throw $e;
            try{query('ALTER TABLE orders ADD COLUMN '.$col.' '.$type);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1060&&!str_contains($e->getMessage(),'duplicate column'))throw $e;}}
    }
    foreach(['CREATE INDEX promo_email_usage ON promo_redemptions (promo_id,email_key,state)','CREATE INDEX promo_schedule ON promo_codes (active,archived,starts_at)'] as $sql)try{query($sql);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1061&&!str_contains($e->getMessage(),'already exists'))throw $e;}
    if(!row("SELECT id FROM promo_mutex WHERE id='checkout'"))try{insert_record('promo_mutex',['id'=>'checkout']);}catch(PDOException $e){if(!str_starts_with((string)$e->getCode(),'23'))throw $e;}
    db()->beginTransaction();try{
        promo_lock();
        // Bridge existing issued entitlements by their original email hash; no reset or expiry extension.
        if(welcome_ready())foreach(rows('SELECT * FROM welcome_promotions') as $w){
            if(row('SELECT id FROM welcome_email_claims WHERE email_key=?'.promo_for_update(),[$w['email_key']]))continue;
            $p=promo_welcome_record($w['email_key'],(int)$w['percentage'],$w['activated_at'],$w['expires_at']);$p['legacy_welcome_id']=$w['id'];$p['active']=$w['status']==='active'?1:0;insert_record('promo_codes',$p);
            insert_record('welcome_email_claims',['id'=>uuid(),'email_key'=>$w['email_key'],'promo_id'=>$p['id'],'created_at'=>$w['activated_at']]);
            if($w['status']==='redeemed'&&$w['redeemed_order_id']&&row('SELECT id FROM orders WHERE id=?',[$w['redeemed_order_id']]))insert_record('promo_redemptions',['id'=>uuid(),'promo_id'=>$p['id'],'order_id'=>$w['redeemed_order_id'],'email_key'=>$w['email_key'],'state'=>'legacy','amount'=>(int)$w['discount_amount'],'shipping_amount'=>0,'created_at'=>$w['redeemed_at']??$w['activated_at'],'finalized_at'=>$w['redeemed_at']]);
        }
        if(!row("SELECT id FROM promo_mutex WHERE id='ready'".promo_for_update()))insert_record('promo_mutex',['id'=>'ready']);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function promo_migrate_down(): void {
    if(!promo_ready())throw new DomainException('Complete the promo upgrade first.');
    if((int)query('SELECT COUNT(*) FROM promo_codes')->fetchColumn()||(int)query('SELECT COUNT(*) FROM promo_redemptions')->fetchColumn()||(int)query('SELECT COUNT(*) FROM orders WHERE promo_snapshot IS NOT NULL OR promo_discount<>0 OR promo_shipping_discount<>0')->fetchColumn())throw new DomainException('Promo history exists. Disable codes instead; rollback cannot erase claims or order history.');
    foreach(['promo_discount','promo_shipping_discount','promo_snapshot'] as $col)query('ALTER TABLE orders DROP COLUMN '.$col);
    foreach(['promo_redemptions','welcome_email_claims','promo_products','promo_categories','promo_mutex','promo_codes'] as $table)query('DROP TABLE '.$table);
}
