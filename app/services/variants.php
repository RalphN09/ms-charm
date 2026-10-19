<?php
function variants_for_update(): string {return db()->inTransaction()&&env('DB_DRIVER')==='mysql'?' FOR UPDATE':'';}
function variants_ready(): bool {
    try{query('SELECT size_enabled,color_enabled,variant_revision FROM products WHERE 1=0');query('SELECT variant_id,variant_snapshot,line_discount,final_line_total FROM order_items WHERE 1=0');return row("SELECT id FROM variant_mutex WHERE id='ready'".variants_for_update())!==null;}
    catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1054,1146],true)||str_contains($e->getMessage(),'no such table')||str_contains($e->getMessage(),'no such column'))return false;throw $e;}
}
function variants_lock(): void {if(!db()->inTransaction())throw new LogicException('Variant lock requires transaction.');try{query("UPDATE variant_mutex SET id=id WHERE id='checkout'");}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1146&&!str_contains($e->getMessage(),'no such table'))throw $e;}}
function has_variants(array $p): bool {return !empty($p['size_enabled'])||!empty($p['color_enabled']);}
function variant_key(string $productId,?string $variantId=null): string {return $variantId?$productId.':'.$variantId:$productId;}
function variant_options(string $id,bool $all=false): array {return variants_ready()?rows('SELECT * FROM product_options WHERE product_id=?'.($all?'':' AND deleted=0').' ORDER BY sort_order,id'.variants_for_update(),[$id]):[];}
function variant_rows(string $id,bool $all=false): array {return variants_ready()?rows('SELECT * FROM product_variants WHERE product_id=?'.($all?'':' AND deleted=0').' ORDER BY created_at,id'.variants_for_update(),[$id]):[];}
function variant_valid(array $p,array $v,array $options): bool {
    if(!has_variants($p)||$v['deleted']||!$v['active'])return false;
    foreach(['size','color'] as $kind){$id=$v[$kind.'_id'];if(!empty($p[$kind.'_enabled'])){if(!$id||!isset($options[$id])||$options[$id]['deleted']||!$options[$id]['active']||$options[$id]['kind']!==$kind)return false;}elseif($id!==null)return false;}return true;
}
function variant_effective_product(array $p,array $v,array $options=[]): array {
    if($v['price_override']!==null){$p['price']=(int)$v['price_override'];$p['sale_price']=null;}
    $p['stock']=(int)$v['stock'];$p['_selected_variant']=$v['id'];
    if($v['color_id']&&isset($options[$v['color_id']])){$images=json_decode($options[$v['color_id']]['images'],true)?:[];if($images)$p['images']=json_encode($images);}
    return $p;
}
function variant_catalog_price(array $p): int {
    $options=array_column(variant_options($p['id']),null,'id');$values=[];$inStock=[];
    foreach(variant_rows($p['id']) as $v)if(variant_valid($p,$v,$options)){$price=offers_price(variant_effective_product($p,$v,$options),$GLOBALS['catalog_offers']??=offers_active());$values[]=$price;if($v['stock']>0)$inStock[]=$price;}
    return min($inStock?:($values?:[base_product_price($p)]));
}
function variant_refresh_stock(string $id,bool $increment=true): void {
    $p=row('SELECT * FROM products WHERE id=?'.variants_for_update(),[$id]);if(!$p||!has_variants($p))return;$options=array_column(variant_options($id),null,'id');$stock=0;foreach(variant_rows($id) as $v)if(variant_valid($p,$v,$options))$stock+=(int)$v['stock'];
    query('UPDATE products SET stock=?,variant_revision=variant_revision+?,updated_at=? WHERE id=?',[$stock,$increment?1:0,now(),$id]);
}
function variant_public(array $p): array {
    $options=variant_options($p['id']);$byId=array_column($options,null,'id');$variants=[];
    foreach(variant_rows($p['id']) as $v){$ep=variant_effective_product($p,$v,$byId);$variants[]=['id'=>$v['id'],'size_id'=>$v['size_id'],'color_id'=>$v['color_id'],'sku'=>$v['sku'],'stock'=>(int)$v['stock'],'available'=>variant_valid($p,$v,$byId)&&$v['stock']>0,'price'=>money(product_price($ep)),'base_price'=>money(base_product_price($ep)),'regular_price'=>money((int)$ep['price'])];}
    return ['sizes'=>array_values(array_map(fn($o)=>['id'=>$o['id'],'label'=>$o['label'],'active'=>(bool)$o['active']],array_filter($options,fn($o)=>$o['kind']==='size'))),'colors'=>array_values(array_map(fn($o)=>['id'=>$o['id'],'label'=>$o['label'],'active'=>(bool)$o['active'],'swatch'=>$o['swatch'],'images'=>json_decode($o['images'],true)?:[]],array_filter($options,fn($o)=>$o['kind']==='color'))),'variants'=>$variants,'images'=>product_images($p),'size_enabled'=>(bool)$p['size_enabled'],'color_enabled'=>(bool)$p['color_enabled'],'name'=>$p['name']];
}
function variant_resolve(array $p,?string $id,bool $lock=false): array {
    if(!has_variants($p)){if($id)throw new DomainException('This product does not use that variant.');return ['product'=>$p,'variant'=>null,'options'=>'','snapshot'=>null,'available'=>true];}
    if(!$id)throw new DomainException('Choose the required size and color on the product page.');
    $v=row('SELECT * FROM product_variants WHERE id=? AND product_id=?'.($lock?variants_for_update():''),[$id,$p['id']]);$options=array_column(variant_options($p['id']),null,'id');
    if(!$v||!variant_valid($p,$v,$options))throw new DomainException('This size/color combination is no longer available. Remove it from your bag and choose another configuration.');
    $size=$v['size_id']?$options[$v['size_id']]['label']:null;$color=$v['color_id']?$options[$v['color_id']]['label']:null;$p=variant_effective_product($p,$v,$options);$label=implode(' · ',array_filter([$color?'Color: '.$color:null,$size?'Size: '.$size:null]));
    return ['product'=>$p,'variant'=>$v,'options'=>$label,'snapshot'=>['variant_id'=>$v['id'],'size'=>$size,'color'=>$color,'sku'=>$v['sku'],'image'=>product_images($p)[0]??''],'available'=>$v['stock']>0];
}
function variant_save(string $productId,array $data,array $current): void {
    if(!array_key_exists('variant_payload',$data))return;if(!variants_ready())throw new DomainException('Initialize product variants first.');
    if(int_input($data,'variant_revision',0,PHP_INT_MAX)!==(int)$current['variant_revision'])throw new DomainException('Stock or product variants changed while this editor was open. Reload before saving to avoid overwriting newer inventory.');
    try{$d=json_decode(text_input($data,'variant_payload',250000,true),true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new DomainException('Invalid variant configuration.');}
    if(!is_array($d))throw new DomainException('Invalid variant configuration.');$mode=text_input($d,'mode',20,true);if(!in_array($mode,['none','sizes','colors','both'],true))throw new DomainException('Choose a valid variant mode.');
    $enabled=['size'=>in_array($mode,['sizes','both'],true),'color'=>in_array($mode,['colors','both'],true)];$existingOptions=array_column(variant_options($productId,true),null,'id');$existingVariants=array_column(variant_rows($productId,true),null,'id');$map=[];$savedOptions=[];$keys=[];
    foreach(['size'=>'sizes','color'=>'colors'] as $kind=>$group){$list=$enabled[$kind]?($d[$group]??[]):[];if(!is_array($list)||count($list)>50||($enabled[$kind]&&!$list))throw new DomainException('Add 1–50 '.$group.' when enabled.');foreach($list as $sort=>$a){
        if(!is_array($a))throw new DomainException('Invalid option.');$key=text_input($a,'key',50,true);if(!preg_match('/^[A-Za-z0-9_-]+$/',$key)||isset($keys[$key]))throw new DomainException('Invalid or repeated option key.');$keys[$key]=true;
        $label=preg_replace('/\s+/u',' ',text_input($a,'label',60,true));$hash=hash('sha256',mb_strtolower($label));if(isset($savedOptions[$kind.':'.$hash]))throw new DomainException('Duplicate '.$kind.' label.');
        $id=text_input($a,'id',32);if($id&&(!isset($existingOptions[$id])||$existingOptions[$id]['kind']!==$kind))throw new DomainException('Option does not belong to this product.');
        $found=row('SELECT * FROM product_options WHERE product_id=? AND kind=? AND option_key=?'.variants_for_update(),[$productId,$kind,$hash]);if(!$id&&$found){if(!$found['deleted'])throw new DomainException('Option already exists; edit it instead.');$id=$found['id'];}elseif($found&&$found['id']!==$id)throw new DomainException('That option label is already reserved, including archived history.');
        $swatch=$kind==='color'?text_input($a,'swatch',7):'';if($swatch!==''&&!preg_match('/^#[A-Fa-f0-9]{6}$/',$swatch))throw new DomainException('Color swatches must use a six-digit HEX value.');
        $images=[];if($kind==='color'){$raw=text_input($a,'images',20000);foreach(array_filter(array_map('trim',preg_split('/\r?\n/',$raw))) as $url)$images[]=valid_url($url,true);
            foreach($_FILES['variant_images']['name'][$key]??[] as $i=>$name)if($name!=='')$images[]=upload_image(['name'=>$name,'tmp_name'=>$_FILES['variant_images']['tmp_name'][$key][$i],'size'=>$_FILES['variant_images']['size'][$key][$i],'error'=>$_FILES['variant_images']['error'][$key][$i]]);
            if(count($images)>12)throw new DomainException('Use at most 12 images per color.');}
        $id=$id?:uuid();$row=['id'=>$id,'product_id'=>$productId,'kind'=>$kind,'label'=>$label,'option_key'=>$hash,'swatch'=>$swatch?:null,'images'=>json_encode($images),'active'=>flag($a,'active'),'deleted'=>0,'sort_order'=>(int)$sort];$savedOptions[$kind.':'.$hash]=$row;$map[$key]=$row;
    }}
    $list=$mode==='none'?[]:($d['variants']??[]);if(!is_array($list)||count($list)>500||($mode!=='none'&&!$list))throw new DomainException('Define 1–500 actual purchasable combinations.');$saved=[];$combos=[];$skus=[];
    foreach($list as $a){if(!is_array($a))throw new DomainException('Invalid combination.');$ids=[];foreach(['size','color'] as $kind){$key=text_input($a,$kind,50);if($enabled[$kind]){if(!isset($map[$key])||$map[$key]['kind']!==$kind)throw new DomainException('Choose the configured '.$kind.' for each combination.');$ids[$kind.'_id']=$map[$key]['id'];}else{$ids[$kind.'_id']=null;}}
        $hash=hash('sha256',($ids['size_id']??'').':'.($ids['color_id']??''));if(isset($combos[$hash]))throw new DomainException('Duplicate size/color combination.');$combos[$hash]=true;
        $id=text_input($a,'id',32);if($id&&!isset($existingVariants[$id]))throw new DomainException('Variant does not belong to this product.');$found=row('SELECT * FROM product_variants WHERE product_id=? AND combination_key=?'.variants_for_update(),[$productId,$hash]);
        if($id&&$existingVariants[$id]['combination_key']!==$hash)throw new DomainException('Archive the old combination and add a new one to change its size or color. Inventory identity must remain stable.');
        if(!$id&&$found){if(!$found['deleted'])throw new DomainException('Combination already exists; edit it instead.');$id=$found['id'];}elseif($found&&$found['id']!==$id)throw new DomainException('Combination already exists, including archived history.');
        $sku=strtoupper(text_input($a,'sku',80));if($sku!==''&&(!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,79}$/',$sku)||isset($skus[$sku])))throw new DomainException('Use a unique SKU of letters, numbers, dots, hyphens or underscores.');if($sku!=='')$skus[$sku]=true;
        $price=text_input($a,'price',20);$id=$id?:uuid();$saved[$id]=['id'=>$id,'product_id'=>$productId,...$ids,'combination_key'=>$hash,'sku'=>$sku?:null,'price_override'=>$price===''?null:cents($price),'stock'=>int_input($a,'stock',0,1000000),'active'=>flag($a,'active'),'deleted'=>0,'created_at'=>$existingVariants[$id]['created_at']??now(),'updated_at'=>now()];
    }
    foreach($existingVariants as $id=>$old)if(!isset($saved[$id])&&!$old['deleted']&&row('SELECT id FROM order_items WHERE variant_id=? LIMIT 1'.variants_for_update(),[$id])&&!flag($data,'confirm_variant_archive'))throw new DomainException('A removed variant is used by saved orders. Confirm archival; historical order selections will be preserved.');
    // Archive before changing unique keys; rows referenced by orders are never deleted.
    foreach($existingVariants as $id=>$old)if(!isset($saved[$id]))update_record('product_variants',$id,['deleted'=>1,'active'=>0,'sku'=>null,'updated_at'=>now()]);
    query('UPDATE product_variants SET sku=NULL WHERE product_id=?',[$productId]);
    foreach($existingOptions as $id=>$old)if(!in_array($id,array_column($map,'id'),true))update_record('product_options',$id,['deleted'=>1,'active'=>0]);
    foreach($map as $o){$id=$o['id'];unset($o['id']);if(isset($existingOptions[$id]))update_record('product_options',$id,$o);else insert_record('product_options',['id'=>$id,...$o]);}
    foreach($saved as $id=>$v){unset($v['id']);if(isset($existingVariants[$id]))update_record('product_variants',$id,$v);else insert_record('product_variants',['id'=>$id,...$v]);}
    update_record('products',$productId,['size_enabled'=>(int)$enabled['size'],'color_enabled'=>(int)$enabled['color'],'variant_revision'=>(int)$current['variant_revision']+1]);variant_refresh_stock($productId,false);unset($GLOBALS['variant_catalog']);
}
function variant_admin_data(?array $p): array {
    $out=['mode'=>!$p||!has_variants($p)?'none':($p['size_enabled']?($p['color_enabled']?'both':'sizes'):'colors'),'sizes'=>[],'colors'=>[],'variants'=>[]];if(!$p||!variants_ready())return $out;
    foreach(variant_options($p['id']) as $o)$out[$o['kind']==='size'?'sizes':'colors'][]=['id'=>$o['id'],'key'=>'o_'.$o['id'],'label'=>$o['label'],'swatch'=>$o['swatch']??'','images'=>implode("\n",json_decode($o['images'],true)?:[]),'active'=>(bool)$o['active']];
    $used=array_column(rows('SELECT DISTINCT variant_id FROM order_items WHERE product_id=? AND variant_id IS NOT NULL',[$p['id']]),'variant_id');
    foreach(variant_rows($p['id']) as $v)$out['variants'][]=['id'=>$v['id'],'size'=>$v['size_id']?'o_'.$v['size_id']:'','color'=>$v['color_id']?'o_'.$v['color_id']:'','sku'=>$v['sku']??'','price'=>$v['price_override']===null?'':number_format($v['price_override']/100,2,'.',''),'stock'=>(int)$v['stock'],'active'=>(bool)$v['active'],'used'=>in_array($v['id'],$used,true)];return $out;
}
function variant_duplicate_product(string $id): void {
    require_admin();db()->beginTransaction();try{variants_lock();$p=row('SELECT * FROM products WHERE id=?'.variants_for_update(),[$id]);if(!$p)throw new DomainException('Product not found.');$copy=$p;$copy['id']=uuid();$copy['name'].=' (copy)';$copy['slug'].='-'.substr($copy['id'],0,6);$copy['active']=0;$copy['created_at']=$copy['updated_at']=now();
        if(has_variants($p)){$copy['stock']=0;$copy['variant_revision']=0;}insert_record('products',$copy);$map=[];
        if(has_variants($p)){foreach(variant_options($p['id']) as $o){$old=$o['id'];$o['id']=uuid();$o['product_id']=$copy['id'];insert_record('product_options',$o);$map[$old]=$o['id'];}foreach(variant_rows($p['id']) as $v){$v['id']=uuid();$v['product_id']=$copy['id'];$v['size_id']=$v['size_id']?($map[$v['size_id']]??null):null;$v['color_id']=$v['color_id']?($map[$v['color_id']]??null):null;$v['combination_key']=hash('sha256',($v['size_id']??'').':'.($v['color_id']??''));$v['stock']=0;$v['created_at']=$v['updated_at']=now();insert_record('product_variants',$v);}}
        audit('duplicate','products',$copy['id']);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
