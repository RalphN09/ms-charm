<?php
function variants_schema(): array {return [
"CREATE TABLE IF NOT EXISTS product_options (id VARCHAR(32) PRIMARY KEY, product_id VARCHAR(32) NOT NULL, kind VARCHAR(10) NOT NULL, label VARCHAR(60) NOT NULL, option_key VARCHAR(64) NOT NULL, swatch VARCHAR(7), images TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, deleted INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, UNIQUE(product_id,kind,option_key), FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)",
"CREATE TABLE IF NOT EXISTS product_variants (id VARCHAR(32) PRIMARY KEY, product_id VARCHAR(32) NOT NULL, size_id VARCHAR(32), color_id VARCHAR(32), combination_key VARCHAR(64) NOT NULL, sku VARCHAR(80), price_override INTEGER, stock INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, deleted INTEGER NOT NULL DEFAULT 0, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL, UNIQUE(product_id,combination_key), UNIQUE(product_id,sku), FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE, FOREIGN KEY(size_id) REFERENCES product_options(id) ON DELETE RESTRICT, FOREIGN KEY(color_id) REFERENCES product_options(id) ON DELETE RESTRICT)",
"CREATE TABLE IF NOT EXISTS variant_mutex (id VARCHAR(32) PRIMARY KEY)"
];}
function variants_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Migrate outside business transactions.');
    foreach(variants_schema() as $sql)db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    foreach(['products'=>['size_enabled'=>'INTEGER NOT NULL DEFAULT 0','color_enabled'=>'INTEGER NOT NULL DEFAULT 0','variant_revision'=>'INTEGER NOT NULL DEFAULT 0'],'order_items'=>['variant_id'=>'VARCHAR(32)','variant_snapshot'=>'TEXT','line_discount'=>'INTEGER NOT NULL DEFAULT 0','final_line_total'=>'INTEGER']] as $table=>$cols)foreach($cols as $col=>$type){
        try{query('SELECT '.$col.' FROM '.$table.' WHERE 1=0');}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1054&&!str_contains($e->getMessage(),'no such column'))throw $e;try{query('ALTER TABLE '.$table.' ADD COLUMN '.$col.' '.$type);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1060&&!str_contains($e->getMessage(),'duplicate column'))throw $e;}}
    }
    foreach(['CREATE INDEX variant_product_lookup ON product_variants (product_id,deleted,active)','CREATE INDEX order_variant_lookup ON order_items (variant_id)'] as $sql)try{query($sql);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1061&&!str_contains($e->getMessage(),'already exists'))throw $e;}
    if(!row("SELECT id FROM variant_mutex WHERE id='checkout'"))try{insert_record('variant_mutex',['id'=>'checkout']);}catch(PDOException $e){if(!str_starts_with((string)$e->getCode(),'23'))throw $e;}
    db()->beginTransaction();try{variants_lock();if(!row("SELECT id FROM variant_mutex WHERE id='ready'".variants_for_update()))insert_record('variant_mutex',['id'=>'ready']);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function variants_migrate_down(): void {
    if(!variants_ready())throw new DomainException('Complete the variants migration first.');
    if((int)query('SELECT COUNT(*) FROM product_options')->fetchColumn()||(int)query('SELECT COUNT(*) FROM product_variants')->fetchColumn()||(int)query('SELECT COUNT(*) FROM order_items WHERE variant_snapshot IS NOT NULL OR final_line_total IS NOT NULL')->fetchColumn())throw new DomainException('Variant or line-price history exists. Disable variants instead of removing history.');
    query(env('DB_DRIVER')==='mysql'?'DROP INDEX order_variant_lookup ON order_items':'DROP INDEX IF EXISTS order_variant_lookup');
    foreach(['size_enabled','color_enabled','variant_revision'] as $c)query('ALTER TABLE products DROP COLUMN '.$c);foreach(['variant_id','variant_snapshot','line_discount','final_line_total'] as $c)query('ALTER TABLE order_items DROP COLUMN '.$c);
    foreach(['product_variants','product_options','variant_mutex'] as $t)query('DROP TABLE '.$t);
}
