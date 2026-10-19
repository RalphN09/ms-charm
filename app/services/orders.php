<?php
const ORDER_STATUSES=['pending','confirmed','preparing','out_for_delivery','completed','cancelled'];
function place_order(array $data): array {
    $key=text_input($data,'idempotency_key',64,true);
    if (!isset($_SESSION['checkout_keys'][$key])) throw new DomainException('Please open checkout again before placing your order.');
    $existing=row('SELECT * FROM orders WHERE idempotency_key=?',[$key]);if($existing) return $existing;
    rate_limit('checkout',10,3600);
    $s=settings('store');if(!$s['open']) throw new DomainException('The store is currently closed for orders.');
    if (!flag($data,'terms')) throw new DomainException('Please accept the store terms.');
    $draft=delivery_capture($data,true);
    $details=[]; foreach(['customer_name'=>120,'phone'=>40,'email'=>254,'address'=>1500] as $field=>$max) $details[$field]=text_input($data,$field,$max,true);
    if (!filter_var($details['email'],FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.');
    if (!preg_match('/^[+0-9() .-]{6,40}$/',$details['phone'])) throw new DomainException('Enter a valid phone number.');
    db()->beginTransaction();
    try {
        // Serialize all checkout attempts for an account before reading products/offers.
        delivery_lock();$s=delivery_store(true);if(!$s['open'])throw new DomainException('The store is currently closed for orders.');
        offers_lock();$customer=current_user();
        if($customer && env('DB_DRIVER')==='mysql')query('SELECT id FROM users WHERE id=? FOR UPDATE',[$customer['id']]);
        // A concurrent submission may have completed while the account lock was pending.
        $existing=row('SELECT * FROM orders WHERE idempotency_key=?'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$key]);
        if($existing){db()->commit();return $existing;}
        $cart=cart_totals(true);if(!$cart['items']) throw new DomainException('Your bag is empty.');
        if($cart['delivery_details']['status']!=='ready')throw new DomainException($cart['delivery_details']['message']);
        if(delivery_config(true)['enabled'] && ($_SESSION['checkout_quotes'][$key]['delivery_signature']??'')!==$cart['delivery_details']['signature'])throw new DomainException('Your delivery area or rate changed. Review the updated delivery fee and total before placing your order.');
        $addressFields=delivery_order_fields($data,$cart);$details['address']=$addressFields['address'];$details['city']=$addressFields['city'];
        $quotedDiscount=(int)($_SESSION['checkout_quotes'][$key]['discount']??0);
        if($quotedDiscount>$cart['discount'] || (isset($_SESSION['checkout_quotes'][$key]['total']) && $cart['total']>$_SESSION['checkout_quotes'][$key]['total']))throw new DomainException('Prices or offers changed. Review the updated checkout total and submit again.');
        if($cart['subtotal']<cents($s['minimum_order'])) throw new DomainException('The minimum order is '.money(cents($s['minimum_order'])).'.');
        foreach($cart['items'] as $line) {
            $p=$line['product'];
            // Atomic stock claim prevents overselling even when two customers order together.
            if(query('UPDATE products SET stock=stock-?,updated_at=? WHERE id=? AND stock>=? AND active=1',[$line['quantity'],now(),$p['id'],$line['quantity']])->rowCount()!==1) throw new DomainException($p['name'].' no longer has enough stock. Please update your bag.');
        }
        $id=uuid();$reference='MC-'.gmdate('ymd').'-'.strtoupper(substr($id,0,8));
        $order=['id'=>$id,'reference'=>$reference,'user_id'=>current_user()['id']??null,...$details,'notes'=>text_input($data,'notes',2000),'internal_notes'=>'','subtotal'=>$cart['subtotal'],'delivery_fee'=>$cart['delivery'],'tax'=>$cart['tax'],'total'=>$cart['total'],'currency'=>$s['currency'],'status'=>'pending','payment_method'=>'cash_on_delivery','payment_status'=>'unpaid','idempotency_key'=>$key,'stock_released'=>0,'created_at'=>now(),'updated_at'=>now()];
        insert_record('orders',$order);
        if(delivery_ready()){$saved=['delivery_area_id'=>$addressFields['delivery_area_id'],'delivery_snapshot'=>$addressFields['delivery_snapshot']];update_record('orders',$id,$saved);$order=[...$order,...$saved];
            if($customer && flag($data,'save_address')){$draft['city']=$details['city'];update_record('users',$customer['id'],['delivery_profile'=>json_encode($draft,JSON_UNESCAPED_UNICODE)]);}
        }
        if(welcome_ready()){
            if($cart['welcome_discount']>0){
                welcome_redeem($cart['welcome'],$id,$cart['welcome_discount']);
                $pricing=['welcome_discount'=>$cart['welcome_discount'],'welcome_promotion_id'=>$cart['welcome']['id'],'welcome_percentage'=>(int)$cart['welcome']['percentage']];
                update_record('orders',$id,$pricing);$order=[...$order,...$pricing];
            }elseif($cart['welcome'] && $cart['welcome']['status']==='active'){
                query("UPDATE welcome_promotions SET status='expired',reason='first_order_placed' WHERE id=? AND status='active'",[$cart['welcome']['id']]);
            }
        }
        if(offers_ready()){
            offers_redeem($cart['applied_offers'],$id);$pricing=['offer_discount'=>array_sum(array_column($cart['applied_offers'],'amount')),'offer_snapshot'=>json_encode($cart['applied_offers'])];update_record('orders',$id,$pricing);$order=[...$order,...$pricing];
        }
        foreach($cart['items'] as $line) { $p=$line['product'];insert_record('order_items',['id'=>uuid(),'order_id'=>$id,'product_id'=>$p['id'],'product_name'=>$p['name'],'unit_price'=>base_product_price($p),'quantity'=>$line['quantity'],'image'=>product_images($p)[0]??'']); }
        db()->commit();$_SESSION['cart']=[];$_SESSION['order_access'][$id]=true;
        return $order;
    } catch(Throwable $e) { if(db()->inTransaction())db()->rollBack();throw $e; }
}
function change_order(array $data): void {
    require_admin();$id=text_input($data,'id',32,true);$status=text_input($data,'status',30,true);
    if(!in_array($status,ORDER_STATUSES,true)) throw new DomainException('Invalid order status.');
    db()->beginTransaction();
    try {
        $o=row('SELECT * FROM orders WHERE id=?'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$id]);
        if(!$o) throw new DomainException('Order not found.');
        if($o['status']==='cancelled' && $status!=='cancelled') throw new DomainException('Cancelled orders cannot be reopened. Create a new order.');
        if($o['status']==='completed' && $status!=='completed') throw new DomainException('Completed orders cannot be changed.');
        if($status==='cancelled' && !$o['stock_released']) {
            // Conditional state transition is also safe under SQLite's serialized writes.
            if(query('UPDATE orders SET stock_released=1 WHERE id=? AND stock_released=0',[$id])->rowCount()===1) foreach(rows('SELECT product_id,quantity FROM order_items WHERE order_id=?',[$id]) as $item) if($item['product_id'])query('UPDATE products SET stock=stock+?,updated_at=? WHERE id=?',[$item['quantity'],now(),$item['product_id']]);
        }
        $payment=text_input($data,'payment_status',30,true);
        if(!in_array($payment,['unpaid','paid','refunded'],true))throw new DomainException('Invalid payment status.');
        update_record('orders',$id,['status'=>$status,'internal_notes'=>text_input($data,'internal_notes',5000),'payment_status'=>$payment,'updated_at'=>now()]);audit('update','orders',$id);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
