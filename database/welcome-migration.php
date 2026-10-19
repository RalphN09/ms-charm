<?php
function welcome_schema_sql(): string {
    return "CREATE TABLE IF NOT EXISTS welcome_promotions (id VARCHAR(32) PRIMARY KEY, user_id VARCHAR(32) NOT NULL UNIQUE, email_key VARCHAR(64) NOT NULL UNIQUE, percentage INTEGER NOT NULL, activated_at VARCHAR(19) NOT NULL, expires_at VARCHAR(19) NOT NULL, status VARCHAR(20) NOT NULL, redeemed_order_id VARCHAR(32) UNIQUE, redeemed_at VARCHAR(19), discount_amount INTEGER NOT NULL DEFAULT 0, reason VARCHAR(60) NOT NULL DEFAULT '', FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT)";
}
function welcome_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Run migrations outside business transactions.');
    db()->exec(welcome_schema_sql().(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    foreach(['welcome_discount'=>'INTEGER NOT NULL DEFAULT 0','welcome_promotion_id'=>'VARCHAR(32)','welcome_percentage'=>'INTEGER NOT NULL DEFAULT 0'] as $column=>$definition){
        try{query('SELECT '.$column.' FROM orders WHERE 1=0');continue;}catch(PDOException $ex){if((int)($ex->errorInfo[1]??0)!==1054 && !str_contains($ex->getMessage(),'no such column'))throw $ex;}
        try{query('ALTER TABLE orders ADD COLUMN '.$column.' '.$definition);}catch(PDOException $ex){if((int)($ex->errorInfo[1]??0)!==1060 && !str_contains($ex->getMessage(),'duplicate column'))throw $ex;}
    }
    if(!row("SELECT id FROM settings WHERE id='welcome'")){
        try{insert_record('settings',['id'=>'welcome','value'=>json_encode(welcome_default_settings()),'updated_at'=>now()]);}
        catch(PDOException $ex){if(!str_starts_with((string)$ex->getCode(),'23'))throw $ex;}
    }
    if(!welcome_ready())throw new RuntimeException('Welcome migration did not complete.');
}
function welcome_migrate_down(): void {
    // Never destroy a customer's issued entitlement or discount audit history.
    if(!welcome_ready())throw new RuntimeException('Complete the migration before reverting.');
    if((int)query('SELECT COUNT(*) FROM welcome_promotions')->fetchColumn() || (int)query('SELECT COUNT(*) FROM orders WHERE welcome_promotion_id IS NOT NULL OR welcome_discount<>0')->fetchColumn())throw new DomainException('Cannot remove welcome schema after offers have been issued. Disable future offers in Admin instead; promotion history is retained.');
    foreach(['welcome_discount','welcome_promotion_id','welcome_percentage'] as $column)query('ALTER TABLE orders DROP COLUMN '.$column);
    query("DELETE FROM settings WHERE id='welcome'");query('DROP TABLE welcome_promotions');
}
