<?php
function governorate_schema(): array {return [
"CREATE TABLE IF NOT EXISTS governorate_rates (id VARCHAR(32) PRIMARY KEY, fee INTEGER, currency VARCHAR(8) NOT NULL, minimum_amount INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 0, notes TEXT NOT NULL, estimate VARCHAR(160) NOT NULL, origin VARCHAR(20) NOT NULL, source_snapshot TEXT NOT NULL, created_at VARCHAR(19) NOT NULL, updated_at VARCHAR(19) NOT NULL)"
];}
function governorate_migrate_up(): void {
    if(db()->inTransaction())throw new LogicException('Migrate outside checkout.');if(!delivery_ready()){require_once __DIR__.'/delivery-migration.php';delivery_migrate_up();}
    foreach(governorate_schema() as $sql)db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
    try{query('SELECT delivery_governorate FROM orders WHERE 1=0');}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1054&&!str_contains($e->getMessage(),'no such column'))throw $e;try{query('ALTER TABLE orders ADD COLUMN delivery_governorate VARCHAR(32)'.(env('DB_DRIVER')==='mysql'?'':' REFERENCES governorate_rates(id) ON DELETE RESTRICT'));}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1060&&!str_contains($e->getMessage(),'duplicate column'))throw $e;}}
    try{query('CREATE INDEX order_governorate_lookup ON orders (delivery_governorate)');}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1061&&!str_contains($e->getMessage(),'already exists'))throw $e;}
    if(env('DB_DRIVER')==='mysql'&&!(int)query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='orders' AND constraint_name='fk_order_governorate'")->fetchColumn()){
        try{query('ALTER TABLE orders ADD CONSTRAINT fk_order_governorate FOREIGN KEY (delivery_governorate) REFERENCES governorate_rates(id) ON DELETE RESTRICT');}catch(PDOException $e){if(!in_array((int)($e->errorInfo[1]??0),[1826,1005],true))throw $e;if(!(int)query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='orders' AND constraint_name='fk_order_governorate'")->fetchColumn())throw $e;}
    }
    db()->beginTransaction();try{
        // Same lock as existing delivery checkouts/admin; publish readiness only after the import commits.
        delivery_lock();$s=delivery_store(true);$c=delivery_config(true);$legacy=rows('SELECT * FROM delivery_areas'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''));
        $unmapped=array_values(array_filter($legacy,fn($a)=>governorate_legacy_id($a['governorate'])===null));
        foreach(lebanon_governorates() as $id=>$g){
            if(governorate_rate($id,true))continue;
            $matched=array_values(array_filter($legacy,fn($a)=>governorate_legacy_id($a['governorate'])===$id));
            $r=['id'=>$id,'fee'=>null,'currency'=>$s['currency'],'minimum_amount'=>0,'active'=>0,'notes'=>'','estimate'=>'','origin'=>'missing','source_snapshot'=>json_encode(['areas'=>$matched,'unmapped'=>$unmapped,'policy'=>$c],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()];
            if(!$c['enabled']){$r['fee']=cents((string)$s['delivery_fee']);$r['active']=1;$r['origin']='legacy_flat';}
            elseif($matched){
                $policies=array_unique(array_map(fn($a)=>json_encode([(int)$a['fee'],$a['currency'],(int)$a['minimum_amount'],(int)$a['active']]),$matched));
                if(count($policies)===1&&$matched[0]['currency']===$s['currency']){$a=$matched[0];$r['fee']=(int)$a['fee'];$r['currency']=$a['currency'];$r['minimum_amount']=(int)$a['minimum_amount'];$r['active']=(int)$a['active'];$r['origin']='uniform_areas';foreach(['notes','estimate'] as $k){$values=array_unique(array_column($matched,$k));if(count($values)===1)$r[$k]=reset($values);}}
                else $r['origin']='conflict';
            }
            // Unknown legacy governorate labels may belong anywhere; require explicit review instead of guessing.
            if($c['enabled']&&$unmapped){$r['fee']=null;$r['active']=0;$r['origin']='unmapped';}
            insert_record('governorate_rates',$r);
        }
        if(!row("SELECT id FROM delivery_mutex WHERE id='governorates_ready'".(env('DB_DRIVER')==='mysql'?' FOR UPDATE':'')))insert_record('delivery_mutex',['id'=>'governorates_ready']);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}

}
