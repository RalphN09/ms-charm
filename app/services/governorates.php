<?php
// Shared by checkout, administration, validation and migration. Prices are always database values.
function lebanon_governorates(): array {return [
    'beirut'=>['en'=>'Beirut','ar'=>'بيروت'],
    'mount-lebanon'=>['en'=>'Mount Lebanon','ar'=>'جبل لبنان'],
    'north-lebanon'=>['en'=>'North Lebanon','ar'=>'الشمال'],
    'akkar'=>['en'=>'Akkar','ar'=>'عكار'],
    'beqaa'=>['en'=>'Beqaa','ar'=>'البقاع'],
    'baalbek-hermel'=>['en'=>'Baalbek-Hermel','ar'=>'بعلبك الهرمل'],
    'south-lebanon'=>['en'=>'South Lebanon','ar'=>'الجنوب'],
    'nabatieh'=>['en'=>'Nabatieh','ar'=>'النبطية'],
];}
function governorate_ready(): bool {
    try{query('SELECT delivery_governorate FROM orders WHERE 1=0');query('SELECT id,fee,currency,active FROM governorate_rates WHERE 1=0');return row("SELECT id FROM delivery_mutex WHERE id='governorates_ready'".(db()->inTransaction()&&env('DB_DRIVER')==='mysql'?' FOR UPDATE':''))!==null;}
    catch(PDOException $e){if(in_array((int)($e->errorInfo[1]??0),[1054,1146],true)||str_contains($e->getMessage(),'no such table')||str_contains($e->getMessage(),'no such column'))return false;throw $e;}
}
function governorate_id(string $id): string {if(!isset(lebanon_governorates()[$id]))throw new DomainException('Choose one of the eight supported Lebanese governorates.');return $id;}
function governorate_legacy_id(string $name): ?string {
    $norm=delivery_normalize($name);foreach(lebanon_governorates() as $id=>$g)if(in_array($norm,[delivery_normalize($id),delivery_normalize($g['en']),delivery_normalize($g['ar'])],true))return $id;
    // Explicit known spelling variants only; never infer a governorate from city/address text.
    return ['bekaa'=>'beqaa','nabatyeh'=>'nabatieh','nabatiyeh'=>'nabatieh','north'=>'north-lebanon','south'=>'south-lebanon','mont liban'=>'mount-lebanon','baalbeck hermel'=>'baalbek-hermel'][$norm]??null;
}
function governorate_rate(string $id,bool $lock=false): ?array {return row('SELECT * FROM governorate_rates WHERE id=?'.($lock&&env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]);}
function governorate_price(int $subtotal,bool $lock=false,?string $id=null,?array $store=null): array {
    $s=$store??delivery_store($lock);$config=delivery_config($lock);$id??=$_SESSION['delivery_governorate']??'';
    $q=['status'=>'pending','message'=>'Select your governorate to calculate delivery and the final total.','area_id'=>null,'name'=>'','fee'=>0,'currency'=>$s['currency'],'minimum_amount'=>0,'notes'=>'','estimate'=>'','governorate'=>'','governorate_id'=>$id,'district'=>'','locality'=>'','identifier'=>'','mode'=>'governorate'];
    if($id!==''){
        $g=lebanon_governorates()[$id]??null;$rate=$g?governorate_rate($id,$lock):null;
        if(!$g){$q['status']='unavailable';$q['message']='Choose one of the eight supported Lebanese governorates.';}
        else{$q['name']=$q['governorate']=$g['en'];
            if(!$rate||$rate['fee']===null||!$rate['active']){$q['status']='unavailable';$q['message']='Delivery to '.$g['en'].' is currently unavailable. Please contact the store.';}
            elseif($rate['currency']!==$s['currency']){$q['status']='unavailable';$q['message']='Delivery to '.$g['en'].' needs a currency update. Please contact the store.';}
            else{$q['status']='ready';$q['message']='';$q['fee']=(int)$rate['fee'];$q['minimum_amount']=(int)$rate['minimum_amount'];$q['notes']=$rate['notes'];$q['estimate']=$rate['estimate'];
                if($subtotal<$q['minimum_amount']){$q['status']='minimum';$q['message']='Delivery to '.$g['en'].' requires '.money($q['minimum_amount']).' of merchandise before product discounts.';}
                elseif($config['honor_free_threshold']&&(float)$s['free_delivery_threshold']>0&&$subtotal>=cents((string)$s['free_delivery_threshold'])){$q['fee']=0;$q['message']='Your order qualifies for the store’s configured free-delivery threshold.';}
            }
        }
    }
    $q['signature']=hash('sha256',json_encode($q));return $q;
}
function governorate_prefill(array $draft): array {
    $id=$draft['governorate']??'';
    if($id!==''&&!isset(lebanon_governorates()[$id]))$id=governorate_legacy_id($id)??'';
    if($id===''&&!empty($draft['delivery_area'])){$old=row('SELECT governorate FROM delivery_areas WHERE id=?',[$draft['delivery_area']]);if($old)$id=governorate_legacy_id($old['governorate'])??'';}
    if($id!=='')$draft['governorate']=$id;$_SESSION['delivery_governorate']=$id;return $draft;
}
function governorate_admin_action(array $data): void {
    require_admin();if(!governorate_ready())throw new DomainException('Initialize governorate delivery first.');
    $action=text_input($data,'action',20,true);db()->beginTransaction();try{
        delivery_lock();$s=delivery_store(true);
        if($action==='settings'){$c=delivery_config(true);$c['honor_free_threshold']=(bool)flag($data,'honor_free_threshold');update_record('settings','shipping',['value'=>json_encode($c),'updated_at'=>now()]);audit('settings','governorate_rates','policy');}
        elseif($action==='save'){
            $id=governorate_id(text_input($data,'governorate',32,true));$old=governorate_rate($id,true);if(!$old)throw new DomainException('Governorate configuration is missing. Run the delivery upgrade.');
            $raw=text_input($data,'fee',20);$fee=$raw===''?null:cents($raw);$active=flag($data,'active');if($active&&$fee===null)throw new DomainException('Enter a delivery fee before enabling this governorate. Zero is an explicit free-delivery rate.');
            if($old['currency']!==$s['currency']&&!flag($data,'confirm_currency'))throw new DomainException('Confirm the re-entered amounts are in the new store currency.');
            $minimum=cents(text_input($data,'minimum_amount',20)?:'0');$notes=text_input($data,'notes',1500);$estimate=text_input($data,'estimate',160);
            if(in_array($old['origin'],['conflict','missing','unmapped'],true)&&!flag($data,'confirm_resolution'))throw new DomainException('Review the migration notes and confirm this governorate’s rate and coverage.');
            update_record('governorate_rates',$id,['fee'=>$fee,'currency'=>$s['currency'],'minimum_amount'=>$minimum,'active'=>$active,'notes'=>$notes,'estimate'=>$estimate,'origin'=>'configured','updated_at'=>now()]);audit('update','governorate_rates',$id);
        }else throw new DomainException('City-based delivery controls are retired. Save a governorate rate instead.');
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
