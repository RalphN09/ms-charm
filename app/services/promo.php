<?php
function promo_now(): string {return gmdate('Y-m-d H:i:s',PHP_SAPI==='cli'&&isset($GLOBALS['promo_test_time'])?(int)$GLOBALS['promo_test_time']:time());}
function promo_for_update(): string {return db()->inTransaction()&&env('DB_DRIVER')==='mysql'?' FOR UPDATE':'';}
function promo_ready(): bool {
    try{query('SELECT promo_discount,promo_shipping_discount,promo_snapshot FROM orders WHERE 1=0');query('SELECT id FROM welcome_email_claims WHERE 1=0');return row("SELECT id FROM promo_mutex WHERE id='ready'")!==null;}
    catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1054,1146],true)||str_contains($e->getMessage(),'no such table')||str_contains($e->getMessage(),'no such column'))return false;throw $e;}
}
function promo_lock(): void {
    if(!db()->inTransaction())throw new LogicException('Code lock requires transaction.');
    try{query("UPDATE promo_mutex SET id=id WHERE id='checkout'");}catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==1146&&!str_contains($e->getMessage(),'no such table'))throw $e;}
}
function promo_email(string $email): string {$email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new DomainException('Enter a valid email address.');return $email;}
function promo_email_key(string $email): string {return hash('sha256',promo_email($email));}
function promo_code(string $s): string {$s=strtoupper(trim($s));if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{3,63}$/',$s))throw new DomainException('Use a code with 4–64 letters, numbers, hyphens or underscores.');return $s;}
function promo_random(string $prefix='MC'): string {return $prefix.'-'.strtoupper(bin2hex(random_bytes(12)));}
function promo_status(array $p): string {if($p['archived'])return 'Archived';if(!$p['active'])return 'Disabled';if($p['ends_at']&&$p['ends_at']<=promo_now())return 'Expired';return $p['starts_at']>promo_now()?'Scheduled':'Active';}
function promo_counts(string $id,?string $emailKey=null): array {
    $rows=rows('SELECT state,email_key FROM promo_redemptions WHERE promo_id=?'.promo_for_update(),[$id]);$r=['reserved'=>0,'redeemed'=>0,'legacy'=>0,'email'=>0];
    foreach($rows as $a){if(isset($r[$a['state']])){$r[$a['state']]++;if($emailKey!==null&&hash_equals($emailKey,$a['email_key']))$r['email']++;}}$r['held']=$r['reserved']+$r['redeemed']+$r['legacy'];return $r;
}
function promo_welcome_record(string $key,int $percentage,string $start,string $end): array {return ['id'=>uuid(),'code'=>promo_random('WELCOME'),'name'=>'Email welcome offer','kind'=>'welcome','discount_type'=>'percentage','discount_value'=>$percentage,'currency'=>settings('store')['currency'],'minimum_amount'=>0,'maximum_discount'=>0,'starts_at'=>$start,'ends_at'=>$end,'active'=>1,'archived'=>0,'total_limit'=>1,'email_limit'=>1,'scope'=>'all','email_key'=>$key,'legacy_welcome_id'=>null,'created_at'=>promo_now(),'updated_at'=>promo_now()];}
function promo_prior_purchase(string $key,string $email): bool {
    if(row("SELECT id FROM orders WHERE LOWER(TRIM(email))=? AND status<>'cancelled' AND (status='completed' OR payment_status='paid') LIMIT 1".promo_for_update(),[$email]))return true;
    if(welcome_ready()&&row("SELECT id FROM welcome_promotions WHERE email_key=? AND status='redeemed'".promo_for_update(),[$key]))return true;return false;
}
function promo_claim(array $data): array {
    if(!promo_ready())throw new DomainException('Welcome codes are being set up. Please try again later.');
    rate_limit('welcome_claim',8,3600);$email=promo_email(text_input($data,'email',254,true));$hash=promo_email_key($email);
    db()->beginTransaction();try{
        promo_lock();$claim=row('SELECT * FROM welcome_email_claims WHERE email_key=?'.promo_for_update(),[$hash]);
        if($claim){
            $p=row('SELECT * FROM promo_codes WHERE id=?'.promo_for_update(),[$claim['promo_id']]);$counts=promo_counts($p['id'],$hash);
            if($counts['redeemed']+$counts['legacy']>0)throw new DomainException('The welcome offer is limited to one successful use per email address.');
            $u=current_user();$known=($_SESSION['welcome_claims'][$hash]??'')===$p['id']||($u&&hash_equals(promo_email_key($u['email']),$hash));
            if(!$known)throw new DomainException('A welcome offer has already been claimed for this email. Use your saved code before it expires; one offer is issued per email.');
            if(promo_status($p)!=='Active')throw new DomainException('This email’s welcome offer has expired or is unavailable. Its expiry cannot be reset.');
        }else{
            $config=welcome_config();$legacy=welcome_ready()?row('SELECT * FROM welcome_promotions WHERE email_key=?'.promo_for_update(),[$hash]):null;
            if(!$config['enabled']&&!$legacy)throw new DomainException('The welcome offer is currently unavailable.');
            if(promo_prior_purchase($hash,$email))throw new DomainException('The welcome offer is available only before a first successful purchase, once per email address.');
            if($legacy&&($legacy['status']!=='active'||$legacy['expires_at']<=promo_now()))throw new DomainException('This email’s existing welcome offer has ended. It cannot be reissued.');
            $start=$legacy['activated_at']??promo_now();$end=$legacy['expires_at']??gmdate('Y-m-d H:i:s',strtotime($start.' UTC')+(int)$config['duration_hours']*3600);$p=promo_welcome_record($hash,(int)($legacy['percentage']??$config['percentage']),$start,$end);$p['legacy_welcome_id']=$legacy['id']??null;insert_record('promo_codes',$p);insert_record('welcome_email_claims',['id'=>uuid(),'email_key'=>$hash,'promo_id'=>$p['id'],'created_at'=>$start]);
        }
        db()->commit();$_SESSION['welcome_claims'][$hash]=$p['id'];$_SESSION['checkout_address']??=[];$_SESSION['checkout_address']['email']=$email;return ['code'=>$p['code'],'percentage'=>(int)$p['discount_value'],'expires'=>welcome_expiry_label($p['ends_at']),'message'=>!empty($counts['reserved'])?'Your code is reserved by a pending order. An unpaid cancellation releases it before expiry.':'Your welcome code is ready. Copy it, then apply it at checkout using this same email address.'];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function promo_selected(): ?array {return promo_ready()&&!empty($_SESSION['promo_code'])?row('SELECT * FROM promo_codes WHERE code=?'.promo_for_update(),[$_SESSION['promo_code']]):null;}
function promo_quote(array $items,int $subtotal,array $delivery,array $store): array {
    $out=['discount'=>0,'shipping_discount'=>0,'code'=>null,'error'=>'','lines'=>[]];if(!promo_ready()||empty($_SESSION['promo_code']))return $out;
    try{
        $p=promo_selected();if(!$p)throw new DomainException('This promo code is invalid.');if(promo_status($p)!=='Active')throw new DomainException('This promo code is disabled, archived, scheduled or expired.');
        if($p['currency']!==$store['currency'])throw new DomainException('This code is unavailable in the store’s current currency.');
        $email=text_input($_SESSION['checkout_address']??[],'email',254);$email=$email?:current_user()['email']??'';
        if(($p['email_limit']||$p['kind']==='welcome')&&$email==='')throw new DomainException('Enter your checkout email before applying this code.');
        $emailKey=$email!==''?promo_email_key($email):null;$counts=promo_counts($p['id'],$emailKey);
        if($p['total_limit']&&$counts['held']>=$p['total_limit'] || $p['email_limit']&&$counts['email']>=$p['email_limit'])throw new DomainException('This code has reached its usage limit or is reserved by a pending order.');
        if($p['kind']==='welcome'){
            if($emailKey===null||!hash_equals($p['email_key'],$emailKey))throw new DomainException('Use the same checkout email address that claimed this welcome code.');
            if(promo_prior_purchase($emailKey,$email))throw new DomainException('The welcome code is limited to a first successful purchase, once per email address.');
        }
        $productIds=array_column(rows('SELECT product_id FROM promo_products WHERE promo_id=?'.promo_for_update(),[$p['id']]),'product_id');$categoryIds=[];
        foreach(rows('SELECT category_id FROM promo_categories WHERE promo_id=?'.promo_for_update(),[$p['id']]) as $a)$categoryIds=[...$categoryIds,...category_branch_ids($a['category_id'],categories())];
        $eligible=0;$lines=[];foreach($items as $line){$product=$line['product'];$match=match($p['scope']){'products'=>in_array($product['id'],$productIds,true),'categories'=>in_array($product['category_id'],$categoryIds,true),default=>true};
            if($match&&($p['discount_type']==='free_shipping'||$product['sale_price']===null)){$eligible+=$line['line_total'];$lines[]=['product_id'=>$product['id'],'quantity'=>$line['quantity'],'subtotal'=>$line['line_total']];}}
        if(!$lines||$eligible<(int)$p['minimum_amount'])throw new DomainException('This code needs eligible merchandise of at least '.money((int)$p['minimum_amount']).'. Merchandise codes exclude sale items.');
        if($p['discount_type']==='free_shipping'){
            if($delivery['status']!=='ready')throw new DomainException('Choose a supported delivery area before applying free shipping.');
            if((int)$delivery['fee']===0)throw new DomainException('Delivery is already free. Remove this code to continue without using it.');$out['shipping_discount']=(int)$delivery['fee'];
        }else{
            $amount=$p['discount_type']==='percentage'?intdiv($eligible*(int)$p['discount_value']+50,100):min($eligible,(int)$p['discount_value']);if($p['maximum_discount'])$amount=min($amount,(int)$p['maximum_discount']);$out['discount']=max(0,min($eligible,$amount));if($out['discount']===0)throw new DomainException('This code provides no saving on the current eligible items.');
        }
        $out['code']=$p;$out['lines']=$lines;
    }catch(DomainException $e){$out['error']=$e->getMessage();}return $out;
}
function promo_apply(array $data): array {
    if(!promo_ready())throw new DomainException('Promo codes are being set up. Please try again shortly.');rate_limit('promo_apply',30,3600);$action=text_input($data,'promo_action',20,true);
    $key=text_input($data,'idempotency_key',64);if($key!==''&&!isset($_SESSION['checkout_keys'][$key]))throw new DomainException('Please reopen checkout before applying a code.');
    if($key!==''){delivery_accept_revision($data,$key);delivery_capture($data);}
    if($action==='remove'){unset($_SESSION['promo_code']);}elseif($action==='apply'){
        $code=promo_code(text_input($data,'promo_code',64,true));$old=$_SESSION['promo_code']??null;
        if(array_key_exists('email',$data)){$_SESSION['checkout_address']??=[];$_SESSION['checkout_address']['email']=promo_email(text_input($data,'email',254,true));}
        $_SESSION['promo_code']=$code;$cart=cart_totals();if($cart['promo_error']!==''){if($old!==null)$_SESSION['promo_code']=$old;else unset($_SESSION['promo_code']);throw new DomainException($cart['promo_error']);}
    }else throw new DomainException('Unknown promo action.');
    $cart=cart_totals();if($key!=='')delivery_remember_quote($key,$cart);return $cart;
}
function promo_reserve(array $cart,string $orderId,string $email): void {
    if(empty($cart['promo']))return;$p=$cart['promo'];if(promo_status($p)!=='Active')throw new DomainException('The code expired. Review checkout again.');
    insert_record('promo_redemptions',['id'=>uuid(),'promo_id'=>$p['id'],'order_id'=>$orderId,'email_key'=>promo_email_key($email),'state'=>'reserved','amount'=>$cart['promo_discount'],'shipping_amount'=>$cart['promo_shipping_discount'],'created_at'=>promo_now(),'finalized_at'=>null]);
}
function promo_order_lifecycle(array $order): void {
    if(!promo_ready())return;$r=row('SELECT * FROM promo_redemptions WHERE order_id=?'.promo_for_update(),[$order['id']]);if(!$r)return;
    if($r['state']==='released'&&($order['payment_status']==='paid'||$order['status']==='completed'))throw new DomainException('This order released its promo reservation. Create a new order and revalidate the code before accepting payment or completing delivery.');
    if($r['state']!=='reserved')return;
    if($order['payment_status']==='paid'||$order['status']==='completed')query("UPDATE promo_redemptions SET state='redeemed',finalized_at=? WHERE id=? AND state='reserved'",[promo_now(),$r['id']]);
    elseif($order['status']==='cancelled'||$order['payment_status']==='refunded')query("UPDATE promo_redemptions SET state='released' WHERE id=? AND state='reserved'",[$r['id']]);
}
function promo_admin_action(array $d): void {
    require_admin();if(!promo_ready())throw new DomainException('Initialize promo storage first.');$action=text_input($d,'action',20,true);$id=text_input($d,'id',32);
    db()->beginTransaction();try{
        promo_lock();$old=$id?row('SELECT * FROM promo_codes WHERE id=?'.promo_for_update(),[$id]):null;if($id&&!$old)throw new DomainException('Code not found.');
        if($old&&$old['archived']&&in_array($action,['save','enable'],true))throw new DomainException('Archived codes are preserved for history. Create a new code instead.');
        if($old&&$old['kind']==='welcome'&&!in_array($action,['disable','archive'],true))throw new DomainException('Issued welcome values and expiry cannot be changed. Manage future claims in Welcome offers; issued codes may be disabled or archived.');
        if($action==='save'){
            $p=['name'=>text_input($d,'name',160,true),'code'=>trim(text_input($d,'code',64))===''?promo_random():promo_code(text_input($d,'code',64)),'kind'=>'manual'];
            if($old&&$old['code']!==$p['code']&&row('SELECT id FROM promo_redemptions WHERE promo_id=? LIMIT 1'.promo_for_update(),[$id]))throw new DomainException('A used code cannot be renamed. Create a new code instead.');
            if(row('SELECT id FROM promo_codes WHERE code=? AND id<>?'.promo_for_update(),[$p['code'],$id]))throw new DomainException('That promo code already exists.');
            $p['discount_type']=text_input($d,'discount_type',20,true);if(!in_array($p['discount_type'],['percentage','fixed','free_shipping'],true))throw new DomainException('Choose a supported discount type.');
            $p['discount_value']=match($p['discount_type']){'percentage'=>int_input($d,'discount_value',1,100),'fixed'=>cents(text_input($d,'discount_value',20,true)),default=>0};if($p['discount_type']==='fixed'&&$p['discount_value']<=0)throw new DomainException('Fixed savings must be positive.');
            foreach(['minimum_amount','maximum_discount'] as $k)$p[$k]=cents(text_input($d,$k,20)?:'0');foreach(['total_limit','email_limit'] as $k)$p[$k]=int_input($d,$k,0,1000000);
            $p['currency']=settings('store')['currency'];if($old&&$old['currency']!==$p['currency']&&!flag($d,'confirm_currency'))throw new DomainException('Confirm the monetary values are in the new store currency.');
            $p['starts_at']=offer_date(text_input($d,'starts_at',30));$p['ends_at']=offer_date(text_input($d,'ends_at',30),true);if($p['ends_at']&&$p['ends_at']<=$p['starts_at'])throw new DomainException('Expiry must be after the start.');$p['active']=flag($d,'active');$p['scope']=text_input($d,'scope',20,true);if(!in_array($p['scope'],['all','products','categories'],true))throw new DomainException('Select a valid scope.');
            $selected=[];foreach(['products','categories'] as $group){$ids=$d[$group]??[];if(!is_array($ids)||count($ids)>2000)throw new DomainException('Invalid targets.');foreach($ids as $target){if(!is_string($target)||strlen($target)>32||!row('SELECT id FROM '.$group.' WHERE id=?',[$target]))throw new DomainException('A selected target is unavailable.');$selected[$group][]=$target;}$selected[$group]=array_unique($selected[$group]??[]);}
            if($p['scope']!=='all'&&empty($selected[$p['scope']]))throw new DomainException('Select at least one target.');
            $id=$old['id']??uuid();$p['updated_at']=promo_now();if($old)update_record('promo_codes',$id,$p);else insert_record('promo_codes',['id'=>$id,...$p,'archived'=>0,'email_key'=>null,'legacy_welcome_id'=>null,'created_at'=>promo_now()]);
            foreach(['promo_products','promo_categories'] as $table)query('DELETE FROM '.$table.' WHERE promo_id=?',[$id]);
            if($p['scope']!=='all')foreach($selected[$p['scope']] as $target)insert_record($p['scope']==='products'?'promo_products':'promo_categories',['promo_id'=>$id,$p['scope']==='products'?'product_id':'category_id'=>$target]);
        }elseif($old&&in_array($action,['enable','disable','archive','delete'],true)){
            if($action==='delete'&&$old['kind']==='manual'&&!row('SELECT id FROM promo_redemptions WHERE promo_id=? LIMIT 1'.promo_for_update(),[$id]))query('DELETE FROM promo_codes WHERE id=?',[$id]);
            else update_record('promo_codes',$id,['active'=>$action==='enable'?1:0,'archived'=>in_array($action,['delete','archive'],true)?1:(int)$old['archived'],'updated_at'=>promo_now()]);
        }else throw new DomainException('Unknown code action.');audit($action,'promo_codes',$id);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
