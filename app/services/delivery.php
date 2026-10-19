<?php
function delivery_ready(): bool {
    try{query('SELECT delivery_area_id,delivery_snapshot FROM orders WHERE 1=0');query('SELECT delivery_profile FROM users WHERE 1=0');query('SELECT id FROM delivery_areas WHERE 1=0');return row("SELECT id FROM settings WHERE id='shipping'")!==null && row("SELECT id FROM delivery_mutex WHERE id='checkout'")!==null;}
    catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1054,1146],true)||str_contains($e->getMessage(),'no such table')||str_contains($e->getMessage(),'no such column'))return false;throw $e;}
}
function delivery_defaults(): array {return ['enabled'=>false,'unsupported'=>'block','fallback_fee'=>cents((string)settings('store')['delivery_fee']),'currency'=>settings('store')['currency'],'honor_free_threshold'=>true];}
function delivery_store(bool $lock=false): array {return json_decode(row("SELECT value FROM settings WHERE id='store'".($lock && env('DB_DRIVER')==='mysql'?' FOR UPDATE':''))['value'],true,512,JSON_THROW_ON_ERROR);}
function delivery_config(bool $lock=false): array {
    if(!delivery_ready())return delivery_defaults();
    return json_decode(row("SELECT value FROM settings WHERE id='shipping'".($lock&&env('DB_DRIVER')==='mysql'?' FOR UPDATE':''))['value'],true,512,JSON_THROW_ON_ERROR);
}
function delivery_lock(): void {if(delivery_ready()){if(!db()->inTransaction())throw new LogicException('Delivery lock requires transaction.');query("UPDATE delivery_mutex SET id=id WHERE id='checkout'");}}
function delivery_areas(): array {return delivery_ready()?rows('SELECT * FROM delivery_areas WHERE active=1 AND currency=? ORDER BY governorate,district,locality,name',[settings('store')['currency']]):[];}
function delivery_label(array $a): string {return $a['name'].' — '.implode(' / ',array_unique(array_filter([$a['governorate'],$a['district'],$a['locality']])));}
function delivery_normalize(string $s): string {return trim(preg_replace('/[\p{Z}\s\p{P}]+/u',' ',mb_strtolower($s)));}
function delivery_price(int $subtotal,bool $lock=false,?string $areaId=null,bool $preview=false,?array $store=null): array {
    $s=$store??delivery_store($lock);$c=delivery_config($lock);$id=$areaId??($_SESSION['delivery_area']??'');
    $q=['status'=>'ready','message'=>'','area_id'=>null,'name'=>'Standard delivery','fee'=>0,'currency'=>$s['currency'],'minimum_amount'=>0,'notes'=>'','estimate'=>'','governorate'=>'','district'=>'','locality'=>'','identifier'=>'','mode'=>'legacy'];
    if(!$c['enabled']&&!$preview){$q['fee']=$subtotal>0?cents((string)$s['delivery_fee']):0;if((float)$s['free_delivery_threshold']>0&&$subtotal>=cents((string)$s['free_delivery_threshold']))$q['fee']=0;}
    else{
        $q['mode']='area';$q['name']='';
        if($id===''){$q['status']='pending';$q['message']='Select your delivery area to calculate delivery and the final total.';}
        elseif($id==='other'&&$c['unsupported']==='fallback'){$q['mode']='fallback';$q['name']='Other area (configured flat rate)';$q['fee']=(int)$c['fallback_fee'];if($c['currency']!==$s['currency']){$q['status']='unavailable';$q['message']='Delivery rates need a currency update. Please contact the store.';}}
        else{
            $a=row('SELECT * FROM delivery_areas WHERE id=?'.($lock&&env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]);
            if(!$a||!$a['active']){$q['status']='unavailable';$q['message']='Delivery is currently unavailable for this area. Please choose a supported area or contact the store.';}
            elseif($a['currency']!==$s['currency']){$q['status']='unavailable';$q['message']='This area’s delivery rate needs a currency update. Please contact the store.';}
            else{foreach(['name','currency','minimum_amount','notes','estimate','governorate','district','locality','identifier'] as $k)$q[$k]=$a[$k];$q['area_id']=$a['id'];$q['fee']=(int)$a['fee'];$q['minimum_amount']=(int)$a['minimum_amount'];
                if($subtotal<$q['minimum_amount']){$q['status']='minimum';$q['message']='Delivery to '.$a['name'].' requires '.money($q['minimum_amount']).' of merchandise before product discounts.';}
            }
        }
        if($q['status']==='ready'&&$c['honor_free_threshold']&&(float)$s['free_delivery_threshold']>0&&$subtotal>=cents((string)$s['free_delivery_threshold'])){$q['fee']=0;$q['message']='Your order qualifies for the store’s configured free-delivery threshold.';}
    }
    $q['signature']=hash('sha256',json_encode($q));return $q;
}
function delivery_prefill(): array {
    if(isset($_SESSION['checkout_address']))return $_SESSION['checkout_address'];
    $u=current_user();$draft=[];
    if($u&&delivery_ready())$draft=json_decode(row('SELECT delivery_profile FROM users WHERE id=?',[$u['id']])['delivery_profile']??'{}',true)?:[];
    if($draft){$_SESSION['checkout_address']=$draft;$_SESSION['delivery_area']=$draft['delivery_area']??'';}return $draft;
}
function delivery_capture(array $data,bool $validate=false): array {
    $draft=[];foreach(['customer_name'=>120,'phone'=>40,'email'=>254,'address'=>1500,'city'=>120,'building'=>120,'floor'=>80,'notes'=>2000,'delivery_area'=>32] as $k=>$max)$draft[$k]=text_input($data,$k,$max);
    $draft['save_address']=flag($data,'save_address');
    $_SESSION['checkout_address']=$draft;$_SESSION['delivery_area']=$draft['delivery_area'];
    if($validate && delivery_config()['enabled'] && $draft['delivery_area']==='')throw new DomainException('Please select your delivery area and review the delivery fee.');return $draft;
}
function delivery_remember_quote(string $key,array $cart): void {
    if(!isset($_SESSION['checkout_keys'][$key]))throw new DomainException('Please reopen checkout to refresh your delivery quote.');
    $_SESSION['checkout_quotes'][$key]=['discount'=>$cart['discount'],'total'=>$cart['total'],'delivery_signature'=>$cart['delivery_details']['signature']];
}
function delivery_update_quote(array $data): array {
    rate_limit('delivery_quote',120,3600);$key=text_input($data,'idempotency_key',64,true);
    if(!isset($_SESSION['checkout_keys'][$key]))throw new DomainException('Please reopen checkout to refresh your delivery quote.');
    if(isset($data['delivery_revision'])){
        $revision=int_input($data,'delivery_revision',1,1000000000);
        if($revision<=(int)($_SESSION['delivery_revisions'][$key]??0))throw new DomainException('A newer delivery selection is already being reviewed. Please use your latest selection.');
        $_SESSION['delivery_revisions'][$key]=$revision;
    }
    delivery_capture($data);$cart=cart_totals();
    if($cart['delivery_details']['mode']==='area' && $cart['delivery_details']['locality']!=='')$_SESSION['checkout_address']['city']=$cart['delivery_details']['locality'];
    delivery_remember_quote($key,$cart);return $cart;
}
function delivery_order_fields(array $data,array $cart): array {
    $q=$cart['delivery_details'];$draft=$_SESSION['checkout_address']??[];
    $building=text_input($data,'building',120,delivery_config()['enabled']);$floor=text_input($data,'floor',80);
    $address=text_input($data,'address',1500,true);$city=$q['mode']==='area'&&$q['locality']!==''?$q['locality']:text_input($data,'city',120,true);
    $snapshot=$q;unset($snapshot['signature']);$snapshot['street']=$address;$snapshot['building']=$building;$snapshot['floor']=$floor;$snapshot['instructions']=text_input($data,'notes',2000);
    $full=$address.($building!==''?"\nBuilding: ".$building:'').($floor!==''?"\nFloor / apartment: ".$floor:'');
    return ['address'=>$full,'city'=>$city,'delivery_area_id'=>$q['area_id'],'delivery_snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE)];
}
function delivery_admin_action(array $d): void {
    require_admin();$action=text_input($d,'action',20,true);db()->beginTransaction();try{
        delivery_lock();$s=delivery_store(true);
        if($action==='settings'){
            $policy=text_input($d,'unsupported',20,true);if(!in_array($policy,['block','fallback'],true))throw new DomainException('Choose a supported policy.');
            $old=delivery_config(true);if($old['currency']!==$s['currency']&&!flag($d,'confirm_currency'))throw new DomainException('Confirm the fallback amount is in the new store currency.');
            $c=['enabled'=>(bool)flag($d,'enabled'),'unsupported'=>$policy,'fallback_fee'=>cents(text_input($d,'fallback_fee',20,true)),'currency'=>$s['currency'],'honor_free_threshold'=>(bool)flag($d,'honor_free_threshold')];
            update_record('settings','shipping',['value'=>json_encode($c),'updated_at'=>now()]);audit('settings','delivery','shipping');
        }else{
            $id=text_input($d,'id',32);$old=$id?row('SELECT * FROM delivery_areas WHERE id=?'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]):null;
            if($id&&!$old)throw new DomainException('Delivery area not found.');
            if($action==='save'){
                $a=[];foreach(['name'=>160,'governorate'=>120,'district'=>120,'locality'=>120,'identifier'=>80,'notes'=>1500,'estimate'=>160] as $k=>$max)$a[$k]=text_input($d,$k,$max,in_array($k,['name','governorate'],true));
                if($old&&$old['currency']!==$s['currency']&&!flag($d,'confirm_currency'))throw new DomainException('Confirm the re-entered rate is in the new store currency.');
                $a['fee']=cents(text_input($d,'fee',20,true));$a['minimum_amount']=cents(text_input($d,'minimum_amount',20,true));$a['currency']=$s['currency'];$a['active']=flag($d,'active');
                $a['area_key']=hash('sha256',implode('|',array_map(fn($k)=>delivery_normalize($a[$k]),['name','governorate','district','locality','identifier'])));
                if(row('SELECT id FROM delivery_areas WHERE area_key=? AND id<>?'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$a['area_key'],$id]))throw new DomainException('This delivery area already exists. Edit its existing rate instead.');
                $id=$old['id']??uuid();$a['updated_at']=now();if($old)update_record('delivery_areas',$id,$a);else insert_record('delivery_areas',['id'=>$id,...$a,'created_at'=>now()]);
            }elseif($old&&in_array($action,['activate','deactivate','delete'],true)){
                if($action==='delete'){if(row('SELECT id FROM orders WHERE delivery_area_id=? LIMIT 1'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]))throw new DomainException('This area is used by existing orders. Deactivate it instead to preserve history.');query('DELETE FROM delivery_areas WHERE id=?',[$id]);}
                else update_record('delivery_areas',$id,['active'=>$action==='activate'?1:0,'updated_at'=>now()]);
            }else throw new DomainException('Unknown delivery action.');audit($action,'delivery_areas',$id);
        }db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
