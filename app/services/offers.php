<?php
function offers_now(): string { return gmdate('Y-m-d H:i:s',PHP_SAPI==='cli' && isset($GLOBALS['offers_test_time'])?(int)$GLOBALS['offers_test_time']:time()); }
function offers_ready(): bool {
    try{query('SELECT offer_discount,offer_snapshot FROM orders WHERE 1=0');query('SELECT id FROM offers WHERE 1=0');return row("SELECT id FROM offer_mutex WHERE id='checkout'")!==null;}
    catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1054,1146],true)||str_contains($e->getMessage(),'no such table')||str_contains($e->getMessage(),'no such column'))return false;throw $e;}
}
function offer_status(array $o): string {
    if($o['deleted'] || $o['state']==='disabled')return 'Disabled';
    if($o['state']==='draft')return 'Draft';
    if($o['ends_at'] && $o['ends_at']<=offers_now())return 'Expired';
    if($o['starts_at']>offers_now())return 'Scheduled';return 'Active';
}
function offers_lock(): void {
    if(!offers_ready())return;
    // A stable row also serializes creation/edits with checkout and guest redemptions.
    if(!db()->inTransaction())throw new LogicException('Offer locking requires a transaction.');
    query("UPDATE offer_mutex SET id=id WHERE id='checkout'");
}
function offer_identity(): string { $u=current_user();return $u?hash('sha256',strtolower(trim($u['email']))):''; }
function offers_active(): array {
    if(!offers_ready())return [];
    $locking=db()->inTransaction() && env('DB_DRIVER')==='mysql'?' FOR UPDATE':'';
    $list=rows("SELECT * FROM offers WHERE deleted=0 AND state='enabled' AND starts_at<=? AND (ends_at IS NULL OR ends_at>?) ORDER BY priority DESC,created_at,id".$locking,[offers_now(),offers_now()]);
    if(!$list)return [];
    $products=[];foreach(rows('SELECT * FROM offer_products'.$locking) as $p)$products[$p['offer_id']][]=$p;
    $cats=[];foreach(rows('SELECT * FROM offer_categories'.$locking) as $c)$cats[$c['offer_id']][]=$c['category_id'];
    $counts=[];$personal=[];$identity=offer_identity();
    if($locking){
        // Current reads after mutex acquisition avoid MySQL repeatable-read snapshots.
        foreach(rows('SELECT offer_id,customer_key FROM offer_redemptions'.$locking) as $r){$counts[$r['offer_id']]=($counts[$r['offer_id']]??0)+1;if($identity && $identity===$r['customer_key'])$personal[$r['offer_id']]=($personal[$r['offer_id']]??0)+1;}
    }else{
        foreach(rows('SELECT offer_id,COUNT(*) AS n FROM offer_redemptions GROUP BY offer_id') as $r)$counts[$r['offer_id']]=(int)$r['n'];
        if($identity)foreach(rows('SELECT offer_id,COUNT(*) AS n FROM offer_redemptions WHERE customer_key=? GROUP BY offer_id',[$identity]) as $r)$personal[$r['offer_id']]=(int)$r['n'];
    }
    $categories=categories();$out=[];
    foreach($list as $o){
        if(($o['members_only']||$o['customer_limit'])&&!current_user())continue;
        if($o['total_limit'] && ($counts[$o['id']]??0)>=$o['total_limit'])continue;
        if($o['customer_limit'] && ($personal[$o['id']]??0)>=$o['customer_limit'])continue;
        $o['products']=[];$o['excluded']=[];
        foreach($products[$o['id']]??[] as $p)$o[$p['excluded']?'excluded':'products'][]=$p['product_id'];
        $o['categories']=[];foreach($cats[$o['id']]??[] as $id)$o['categories']=[...$o['categories'],...category_branch_ids($id,$categories)];
        $out[]=$o;
    }return $out;
}
function offer_matches(array $o,array $p): bool {
    if(in_array($p['id'],$o['excluded'],true))return false;
    return match($o['scope']){'products'=>in_array($p['id'],$o['products'],true),'categories'=>in_array($p['category_id'],$o['categories'],true),default=>true};
}
function offer_unit_saving(array $o,array $p): int {
    $regular=(int)$p['price'];$base=base_product_price($p);
    $saving=$o['discount_type']==='percentage'?intdiv($regular*(int)$o['discount_value']+50,100):min($regular,(int)$o['discount_value']);
    // Existing sale prices compete with, and never stack onto, an offer.
    return max(0,$base-max(0,$regular-$saving));
}
function offers_price(array $p,?array $list=null): int {
    foreach($list??offers_active() as $o){
        // Conditional basket offers are advertised with terms, not unconditional card prices.
        if(($o['minimum_amount']||$o['maximum_discount']) && offer_matches($o,$p))return base_product_price($p);
        if(offer_matches($o,$p) && ($saving=offer_unit_saving($o,$p))>0)return base_product_price($p)-$saving;
    }return base_product_price($p);
}
function offer_product_conditions(array $p): array {
    $terms=[];foreach($GLOBALS['catalog_offers']??=offers_active() as $o)if(($o['minimum_amount']||$o['maximum_discount']) && offer_matches($o,$p))$terms[]=offer_terms($o);
    return $terms;
}
function offers_cart(array $items,array $active): array {
    $assigned=[];$applied=[];
    foreach($active as $o){
        $eligible=0;$candidates=[];
        foreach($items as $i=>$line)if(!isset($assigned[$i]) && offer_matches($o,$line['product'])){
            $saving=offer_unit_saving($o,$line['product'])*$line['quantity'];
            if($saving>0){$eligible+=$line['line_total'];$candidates[$i]=$saving;}
        }
        if($eligible<(int)$o['minimum_amount'] || !$candidates)continue;
        $remaining=$o['maximum_discount']?(int)$o['maximum_discount']:array_sum($candidates);$amount=0;$lines=[];
        foreach($candidates as $i=>$saving){$take=min($remaining,$saving);if(!$take)break;$assigned[$i]=true;$items[$i]['offer_saving']=$take;$items[$i]['offer_title']=$o['title'];$remaining-=$take;$amount+=$take;$lines[]=['product_id'=>$items[$i]['product']['id'],'variant_id'=>$items[$i]['variant_id']??null,'line_key'=>$items[$i]['line_key']??$items[$i]['product']['id'],'quantity'=>$items[$i]['quantity'],'original_line'=>$items[$i]['line_total'],'discount'=>$take];}
        if($amount)$applied[]=['id'=>$o['id'],'title'=>$o['title'],'type'=>$o['discount_type'],'value'=>(int)$o['discount_value'],'amount'=>$amount,'lines'=>$lines];
    }return ['items'=>$items,'applied'=>$applied,'discount'=>array_sum(array_column($applied,'amount'))];
}
function offers_redeem(array $applied,string $orderId): void {
    foreach($applied as $a){
        $o=row('SELECT * FROM offers WHERE id=?',[$a['id']]);
        if(!$o || offer_status($o)!=='Active')throw new DomainException('An offer has ended. Please review checkout.');
        insert_record('offer_redemptions',['id'=>uuid(),'offer_id'=>$a['id'],'order_id'=>$orderId,'user_id'=>current_user()['id']??null,'customer_key'=>offer_identity(),'amount'=>$a['amount'],'snapshot'=>json_encode($a),'created_at'=>offers_now()]);
    }
}
function offer_terms(array $o): string {
    $text=$o['discount_type']==='percentage'?$o['discount_value'].'% off':money((int)$o['discount_value']).' off per piece';
    $text.=' · '.match($o['scope']){'products'=>'Selected pieces','categories'=>'Selected collections and subcollections','exclude'=>'Storewide with exclusions',default=>'Storewide'};
    if($o['minimum_amount'])$text.=' · Eligible purchase of '.money((int)$o['minimum_amount']).' minimum';
    if($o['maximum_discount'])$text.=' · Savings capped at '.money((int)$o['maximum_discount']).' per order';
    if($o['ends_at'])$text.=' · Ends '.welcome_expiry_label($o['ends_at']);
    return $text.(promo_ready()?' · Offers cannot be combined. Applying a promo or welcome code at checkout replaces automatic offers. Sale prices do not stack.':' · Offers cannot be combined. We apply the better of promotional savings and your welcome discount. Sale prices do not stack.');
}
function offers_public(): array {
    $products=products();$result=[];
    foreach(offers_active() as $o){
        $possible=false;foreach($products as $p)if($p['stock']>0 && offer_matches($o,$p) && offer_unit_saving($o,$p)>0){$possible=true;break;}
        if(!$possible)continue;
        $destination=$o['cta_url'];
        if($destination==='/shop' && $o['scope']==='products' && count($o['products'])===1){$p=row('SELECT slug FROM products WHERE id=? AND active=1',[$o['products'][0]]);if($p)$destination='/product/'.$p['slug'];}
        if($destination==='/shop' && $o['scope']==='categories'){$c=row('SELECT c.slug FROM categories c JOIN offer_categories oc ON oc.category_id=c.id WHERE oc.offer_id=? AND c.active=1 ORDER BY c.sort_order LIMIT 1',[$o['id']]);if($c)$destination='/shop?category='.rawurlencode($c['slug']);}
        if(str_starts_with($destination,'/')){
            $path=parse_url($destination,PHP_URL_PATH);$available=in_array($path,['/','/shop','/categories','/offers','/contact','/reviews','/register','/login'],true);
            if(preg_match('~^/product/([a-z0-9-]+)$~',(string)$path,$m))$available=product_by_slug($m[1])!==null;
            elseif(!$available)$available=row('SELECT id FROM pages WHERE slug=? AND active=1',[trim((string)$path,'/')])!==null;
            if(!$available)$destination='/shop';
        }
        $result[]=['id'=>$o['id'],'popup_version'=>substr(hash('sha256',$o['id'].':'.$o['updated_at']),0,16),'title'=>$o['title'],'description'=>$o['description'],'image'=>image_url($o['image']),'cta_text'=>$o['cta_text']?:'Shop now','cta_url'=>$destination,'terms'=>offer_terms($o),'frequency'=>$o['frequency'],'popup_enabled'=>(bool)$o['popup_enabled']];
    }return $result;
}
function offer_date(string $date,bool $optional=false): ?string {
    if($date==='')return $optional?null:offers_now();
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new DateTimeZone(env('APP_TIMEZONE','Asia/Beirut')));
    if(!$d || $d->format('Y-m-d\TH:i')!==$date)throw new DomainException('Enter a valid date and time.');
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function offer_admin_action(array $d): void {
    require_admin();$action=text_input($d,'action',20,true);$id=text_input($d,'id',32);
    db()->beginTransaction();try{
        offers_lock();$old=$id?row('SELECT * FROM offers WHERE id=? AND deleted=0'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]):null;
        if($id&&!$old)throw new DomainException('Offer no longer exists.');
        if($action==='save'){
            $o=[];foreach(['name'=>160,'title'=>200,'description'=>2000,'cta_text'=>80] as $k=>$max)$o[$k]=text_input($d,$k,$max,in_array($k,['name','title'],true));
            foreach(['discount_type'=>['percentage','fixed'],'scope'=>['all','products','categories','exclude'],'state'=>['draft','enabled','disabled'],'frequency'=>['session','day','once']] as $k=>$options){$o[$k]=text_input($d,$k,20,true);if(!in_array($o[$k],$options,true))throw new DomainException('Invalid '.$k);}
            $o['discount_value']=$o['discount_type']==='percentage'?int_input($d,'discount_value',1,100):cents(text_input($d,'discount_value',20,true));
            if($o['discount_value']<=0)throw new DomainException('Discount must be greater than zero.');
            if(in_array($o['scope'],['all','exclude'],true)&&!flag($d,'confirm_storewide'))throw new DomainException('Confirm the storewide scope before saving.');
            $o['starts_at']=offer_date(text_input($d,'starts_at',30));$o['ends_at']=offer_date(text_input($d,'ends_at',30),true);
            if($o['ends_at'] && $o['ends_at']<=$o['starts_at'])throw new DomainException('End time must be after the start time.');
            foreach(['minimum_amount','maximum_discount'] as $k)$o[$k]=cents(text_input($d,$k,20)?:'0');
            foreach(['total_limit','customer_limit'] as $k)$o[$k]=int_input($d,$k,0,1000000);
            $o['priority']=int_input($d,'priority',0,9999);$o['members_only']=flag($d,'members_only');$o['popup_enabled']=flag($d,'popup_enabled');
            $o['cta_url']=valid_url(text_input($d,'cta_url',1000)?:'/shop',true);
            $o['image']=$old['image']??'';if(flag($d,'remove_image'))$o['image']='';
            if(isset($_FILES['image']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE)$o['image']=upload_image($_FILES['image']);
            $selections=[];foreach(['products','exclusions','categories'] as $key){
                $ids=$d[$key]??[];if(!is_array($ids)||count($ids)>2000)throw new DomainException('Invalid selection.');
                foreach($ids as $pid){if(!is_string($pid)||!preg_match('/^[a-f0-9]{32}$/',$pid) && !preg_match('/^[a-z0-9-]{1,32}$/',$pid))throw new DomainException('Invalid selection.');$table=$key==='categories'?'categories':'products';if(!row('SELECT id FROM '.$table.' WHERE id=?',[$pid]))throw new DomainException('A selected record is no longer available.');$selections[$key][]=$pid;}
                $selections[$key]=array_values(array_unique($selections[$key]??[]));
            }
            if($o['scope']==='products'&&empty($selections['products']) || $o['scope']==='categories'&&empty($selections['categories']))throw new DomainException('Select at least one product or collection.');
            $id=$old['id']??uuid();$o['updated_at']=now();
            if($old)update_record('offers',$id,$o);else insert_record('offers',['id'=>$id,...$o,'deleted'=>0,'created_by'=>current_user()['id'],'created_at'=>now()]);
            query('DELETE FROM offer_products WHERE offer_id=?',[$id]);query('DELETE FROM offer_categories WHERE offer_id=?',[$id]);
            foreach($o['scope']==='products'?($selections['products']??[]):($o['scope']==='exclude'?($selections['exclusions']??[]):[]) as $pid)insert_record('offer_products',['offer_id'=>$id,'product_id'=>$pid,'excluded'=>$o['scope']==='exclude'?1:0]);
            foreach($o['scope']==='categories'?($selections['categories']??[]):[] as $cid)insert_record('offer_categories',['offer_id'=>$id,'category_id'=>$cid]);
        }elseif($old && $action==='duplicate'){
            $id=uuid();$copy=$old;$copy['id']=$id;$copy['name']=mb_substr($old['name'],0,150).' (copy)';$copy['state']='draft';$copy['created_by']=current_user()['id'];$copy['created_at']=$copy['updated_at']=now();insert_record('offers',$copy);
            foreach(rows('SELECT * FROM offer_products WHERE offer_id=?',[$old['id']]) as $r){$r['offer_id']=$id;insert_record('offer_products',$r);}foreach(rows('SELECT * FROM offer_categories WHERE offer_id=?',[$old['id']]) as $r){$r['offer_id']=$id;insert_record('offer_categories',$r);}
        }elseif($old && in_array($action,['enable','disable','delete'],true)){
            if($action==='enable'&&in_array($old['scope'],['all','exclude'],true)&&!flag($d,'confirm_storewide'))throw new DomainException('Confirm storewide activation.');
            update_record('offers',$id,['state'=>$action==='enable'?'enabled':'disabled','deleted'=>$action==='delete'?1:0,...($action==='enable'?['popup_enabled'=>1]:[]),'updated_at'=>now()]);
        }else throw new DomainException('Unknown offer action.');
        audit($action,'offers',$id);db()->commit();unset($GLOBALS['catalog_offers']);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
