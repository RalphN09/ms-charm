<?php
function delivery_schema(): array { return [
"CREATE TABLE IF NOT EXISTS delivery_areas (id VARCHAR(32) PRIMARY KEY, area_key VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(160) NOT NULL, governorate VARCHAR(120) NOT NULL, district VARCHAR(120) NOT NULL, locality VARCHAR(120) NOT NULL, identifier VARCHAR(80) NOT NULL, fee INTEGER NOT NULL, currency VARCHAR(8) NOT NULL, minimum_amount INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, notes TEXT NOT NULL, estimate VARCHAR(160) NOT NULL, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL)",
"CREATE TABLE IF NOT EXISTS delivery_mutex (id VARCHAR(32) PRIMARY KEY)"
]; }
function delivery_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Migrate outside checkout.');
    foreach(delivery_schema() as $sql)db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    foreach(['orders'=>['delivery_area_id'=>'VARCHAR(32)'.(env('DB_DRIVER')==='mysql'?'':' REFERENCES delivery_areas(id) ON DELETE SET NULL'),'delivery_snapshot'=>'TEXT'],'users'=>['delivery_profile'=>'TEXT']] as $table=>$columns)foreach($columns as $col=>$type){
        try{query('SELECT '.$col.' FROM '.$table.' WHERE 1=0');}catch(PDOException $e){
            if((int)($e->errorInfo[1]??0)!==1054 && !str_contains($e->getMessage(),'no such column'))throw $e;
            try{query('ALTER TABLE '.$table.' ADD COLUMN '.$col.' '.$type);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1060 && !str_contains($e->getMessage(),'duplicate column'))throw $e;}
        }
    }
    if(env('DB_DRIVER')==='mysql' && !(int)query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='orders' AND constraint_name='fk_order_delivery'")->fetchColumn()){
        try{query('ALTER TABLE orders ADD CONSTRAINT fk_order_delivery FOREIGN KEY (delivery_area_id) REFERENCES delivery_areas(id) ON DELETE SET NULL');}catch(PDOException $e){if(!in_array((int)($e->errorInfo[1]??0),[1826,1005],true))throw $e;if(!(int)query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='orders' AND constraint_name='fk_order_delivery'")->fetchColumn())throw $e;}
    }
    foreach(['delivery_area_lookup'=>'CREATE INDEX delivery_area_lookup ON delivery_areas (active,currency,governorate,district)','order_delivery_lookup'=>'CREATE INDEX order_delivery_lookup ON orders (delivery_area_id)'] as $sql)try{query($sql);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1061 && !str_contains($e->getMessage(),'already exists'))throw $e;}
    if(!row("SELECT id FROM delivery_mutex WHERE id='checkout'"))try{insert_record('delivery_mutex',['id'=>'checkout']);}catch(PDOException $e){if(!str_starts_with((string)$e->getCode(),'23'))throw $e;}
    if(!row("SELECT id FROM settings WHERE id='shipping'"))try{insert_record('settings',['id'=>'shipping','value'=>json_encode(delivery_defaults()),'updated_at'=>now()]);}catch(PDOException $e){if(!str_starts_with((string)$e->getCode(),'23'))throw $e;}
    if(!delivery_ready())throw new RuntimeException('Delivery upgrade incomplete.');
}
