<?php
function categories(bool $all=false): array {
    $list=rows('SELECT * FROM categories ORDER BY sort_order,name');
    if($all)return $list;$byId=array_column($list,null,'id');
    return array_values(array_filter($list,fn($cat)=>category_visible($cat,$byId)));
}
function category_is_public(?string $id): bool { return $id===null || in_array($id,array_column(categories(),'id'),true); }
function products(array $filter=[],bool $all=false): array {
    $where=$all?['1=1']:['p.active=1','(c.active=1 OR p.category_id IS NULL)'];$args=[];
    if (!empty($filter['q'])) { $where[]='p.name LIKE ?'; $args[]='%'.text_input($filter,'q',160).'%'; }
    if (!empty($filter['category'])) {
        $slug=text_input($filter,'category',160);$list=categories();$matches=array_values(array_filter($list,fn($cat)=>$cat['slug']===$slug));
        if(!$matches)$where[]='1=0';
        else{$ids=category_branch_ids($matches[0]['id'],$list);$where[]='p.category_id IN ('.implode(',',array_fill(0,count($ids),'?')).')';$args=[...$args,...$ids];}
    }
    if (!empty($filter['available'])) $where[]='p.stock>0';
    if (!empty($filter['featured'])) $where[]='p.featured=1';
    $sort=match($filter['sort']??'featured') {'price-low'=>'COALESCE(p.sale_price,p.price) ASC','price-high'=>'COALESCE(p.sale_price,p.price) DESC','newest'=>'p.created_at DESC',default=>'p.featured DESC,p.sort_order,p.created_at DESC'};
    $result=rows('SELECT p.*,c.name AS category_name,c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where).' ORDER BY '.$sort,$args);
    if($all)return $result;$visible=array_column(categories(),'id');
    $result=array_values(array_filter($result,fn($p)=>$p['category_id']===null || in_array($p['category_id'],$visible,true)));
    if(isset($filter['min']) && $filter['min']!=='')$result=array_values(array_filter($result,fn($p)=>product_price($p)>=cents($filter['min'])));
    if(isset($filter['max']) && $filter['max']!=='')$result=array_values(array_filter($result,fn($p)=>product_price($p)<=cents($filter['max'])));
    if(in_array($filter['sort']??'',['price-low','price-high'],true))usort($result,fn($a,$b)=>(product_price($a)<=>product_price($b))*(($filter['sort']==='price-high')?-1:1));return $result;
}
function product_by_slug(string $slug): ?array {
    $p=row('SELECT p.*,c.name AS category_name,c.slug AS category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.slug=? AND p.active=1 AND (c.active=1 OR p.category_id IS NULL)',[$slug]);
    return $p && category_is_public($p['category_id'])?$p:null;
}
function product_images(array $product): array { return json_decode($product['images'],true)?:[]; }
function base_product_price(array $product): int { return (int)($product['sale_price']??$product['price']); }
function product_price(array $product): int {if(has_variants($product)&&empty($product['_selected_variant']))return variant_catalog_price($product);return offers_price($product,$GLOBALS['catalog_offers']??=offers_active());}
function cart_totals(bool $lock=false): array {
    $items=[];$subtotal=0;
    if($lock){variants_lock();promo_lock();delivery_lock();offers_lock();}
    $activeOffers=offers_active();
    $bag=$_SESSION['cart']??[];ksort($bag);
    $variantError='';
    foreach($bag as $key=>$quantity) {
        if(!is_numeric($quantity)||(int)$quantity<1||(int)$quantity>99){unset($_SESSION['cart'][$key]);continue;}
        $parts=explode(':',(string)$key);$id=$parts[0];$variantId=$parts[1]??null;
        if(count($parts)>2||!preg_match('/^[a-f0-9]{32}$/',$id)||($variantId!==null&&!preg_match('/^[a-f0-9]{32}$/',$variantId))){unset($_SESSION['cart'][$key]);continue;}
        $p=row('SELECT p.*,c.active AS category_active FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?'.($lock && env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]);
        if (!$p || !$p['active'] || !category_is_public($p['category_id'])) { unset($_SESSION['cart'][$key]);continue; }
        try{$resolved=variant_resolve($p,$variantId,$lock);}
        catch(DomainException $e){$variantError=$e->getMessage();$p['stock']=0;$resolved=['product'=>$p,'variant'=>null,'options'=>'Unavailable configuration','snapshot'=>null];}
        $p=$resolved['product'];$line=base_product_price($p)*(int)$quantity;$subtotal+=$line;
        $items[]=['product'=>$p,'quantity'=>(int)$quantity,'line_key'=>$key,'variant_id'=>$variantId,'variant'=>$resolved['variant'],'variant_snapshot'=>$resolved['snapshot'],'options'=>$resolved['options'],'line_total'=>$line,'available'=>(int)$p['stock']>=(int)$quantity];
    }
    $s=delivery_store($lock);$deliveryDetails=delivery_price($subtotal,$lock,null,false,$s);$delivery=$deliveryDetails['fee'];
    $promotion=promo_ready()?null:welcome_status(null,$lock);$welcome=welcome_cart_discount($items,$promotion);$priced=offers_cart($items,$activeOffers);
    $welcomeDiscount=$welcome['discount'];$applied=[];
    if($priced['discount']>=$welcomeDiscount && $priced['discount']>0){$welcomeDiscount=0;$applied=$priced['applied'];$items=$priced['items'];}
    $discount=$applied?$priced['discount']:$welcomeDiscount;
    $code=promo_quote($items,$subtotal,$deliveryDetails,$s);$shippingSaving=0;
    if($code['code']){
        $items=array_map(function($line){unset($line['offer_saving'],$line['offer_title']);return $line;},$items);
        $applied=[];$welcomeDiscount=0;$discount=$code['discount'];$shippingSaving=$code['shipping_discount'];
    }
    $eligibleKeys=array_column($code['lines'],'line_key');$remaining=$discount;$remainingEligible=0;
    if(!$applied&&$discount)foreach($items as $line)if(($code['code']&&in_array($line['line_key'],$eligibleKeys,true))||(!$code['code']&&$line['product']['sale_price']===null))$remainingEligible+=$line['line_total'];
    foreach($items as &$line){$saving=(int)($line['offer_saving']??0);if(!$applied&&$discount&&$remainingEligible>0&&(($code['code']&&in_array($line['line_key'],$eligibleKeys,true))||(!$code['code']&&$line['product']['sale_price']===null))){$saving=intdiv($remaining*$line['line_total'],$remainingEligible);$remaining-=$saving;$remainingEligible-=$line['line_total'];}$line['line_discount']=$saving;$line['final_line_total']=$line['line_total']-$saving;}unset($line);
    $canCheckout=$variantError===''&&!array_filter($items,fn($line)=>!$line['available']);
    $tax=(int)round(($subtotal-$discount)*(float)$s['tax_rate']/100);
    return ['variant_error'=>$variantError,'can_checkout'=>$canCheckout,'subtotal'=>$subtotal,'discounted_subtotal'=>$subtotal-$discount,...$welcome,'items'=>$items,'discount'=>$discount,'welcome_discount'=>$welcomeDiscount,'applied_offers'=>$applied,'promo'=>$code['code'],'promo_error'=>$code['error'],'promo_discount'=>$code['discount'],'promo_shipping_discount'=>$shippingSaving,'promo_lines'=>$code['lines'],'delivery'=>$delivery-$shippingSaving,'delivery_before_promo'=>$delivery,'delivery_details'=>$deliveryDetails,'tax'=>$tax,'total'=>max(0,$subtotal-$discount+$delivery-$shippingSaving+$tax),'count'=>array_sum(array_column($items,'quantity'))];
}
function update_cart(array $data): array {
    $key=text_input($data,'line_key',65);if($key!==''){$parts=explode(':',$key);if(count($parts)>2)throw new DomainException('Invalid bag line.');$id=$parts[0];$variantId=$parts[1]??null;}
    else{$id=text_input($data,'product_id',32,true);$variantId=text_input($data,'variant_id',32)?:null;$key=variant_key($id,$variantId);}
    if(!preg_match('/^[a-f0-9]{32}$/',$id)||($variantId!==null&&!preg_match('/^[a-f0-9]{32}$/',$variantId)))throw new DomainException('Invalid product or variant.');
    $qty=int_input($data,'quantity',0,99);$mode=text_input($data,'mode',10)?:'set';if(!in_array($mode,['set','add'],true))throw new DomainException('Invalid bag action.');
    if($qty===0&&$mode==='set'){unset($_SESSION['cart'][$key]);return cart_totals();}
    if($qty===0)throw new DomainException('Choose a quantity from 1 to 99.');
    $p=row('SELECT * FROM products WHERE id=?',[$id]);if(!$p||!$p['active']||!category_is_public($p['category_id']))throw new DomainException('This piece is no longer available.');
    $resolved=variant_resolve($p,$variantId);$p=$resolved['product'];$new=$mode==='add'?(int)($_SESSION['cart'][$key]??0)+$qty:$qty;
    if($new>99||$new>(int)$p['stock'])throw new DomainException('Only '.(int)$p['stock'].' of this configuration are available.');
    $_SESSION['cart'][$key]=$new;return cart_totals();
}

function submit_review(array $data): void {
    rate_limit('review',5,3600);$u=current_user();$pid=text_input($data,'product_id',32);
    if ($pid && !row('SELECT id FROM products WHERE id=? AND active=1',[$pid])) throw new DomainException('This product is unavailable.');
    insert_record('reviews',['id'=>uuid(),'user_id'=>$u['id']??null,'product_id'=>$pid?:null,'name'=>$u['name']??text_input($data,'name',120,true),'rating'=>int_input($data,'rating',1,5),'comment'=>text_input($data,'comment',2000,true),'status'=>'pending','featured'=>0,'created_at'=>now(),'updated_at'=>now()]);
}
