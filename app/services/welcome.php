<?php
// Clock injection is available only to CLI tests; HTTP always uses the server clock.
function welcome_time(): int { return PHP_SAPI==='cli' && isset($GLOBALS['welcome_test_time'])?(int)$GLOBALS['welcome_test_time']:time(); }
function welcome_now(): string { return gmdate('Y-m-d H:i:s',welcome_time()); }
function welcome_default_settings(): array { return ['enabled'=>true,'percentage'=>10,'duration_hours'=>24]; }
function welcome_ready(): bool {
    try{
        query('SELECT id,user_id,email_key,percentage,activated_at,expires_at,status,redeemed_order_id,redeemed_at,discount_amount,reason FROM welcome_promotions WHERE 1=0');
        query('SELECT welcome_discount,welcome_promotion_id,welcome_percentage FROM orders WHERE 1=0');
        return row("SELECT id FROM settings WHERE id='welcome'")!==null;
    }catch(PDOException $ex){
        if(in_array((int)($ex->errorInfo[1]??0),[1054,1146],true) || str_contains($ex->getMessage(),'no such table') || str_contains($ex->getMessage(),'no such column'))return false;
        throw $ex;
    }
}
function welcome_config(): array { return welcome_ready()?settings('welcome'):[...welcome_default_settings(),'enabled'=>false]; }
function welcome_issue(string $userId,string $email): ?array {
    // Called only within successful customer registration's transaction.
    if(!db()->inTransaction())throw new LogicException('Promotion issuance requires registration transaction.');
    $config=welcome_config();if(!$config['enabled'])return null;
    $email=strtolower(trim($email));$emailKey=hash('sha256',$email);
    if(row('SELECT id FROM welcome_promotions WHERE user_id=? OR email_key=?',[$userId,$emailKey]))return null;
    if(row('SELECT id FROM orders WHERE user_id=? OR LOWER(TRIM(email))=?',[$userId,$email]))return null;
    $start=welcome_time();$p=['id'=>uuid(),'user_id'=>$userId,'email_key'=>$emailKey,'percentage'=>(int)$config['percentage'],'activated_at'=>gmdate('Y-m-d H:i:s',$start),'expires_at'=>gmdate('Y-m-d H:i:s',$start+(int)$config['duration_hours']*3600),'status'=>'active','redeemed_order_id'=>null,'redeemed_at'=>null,'discount_amount'=>0,'reason'=>''];
    insert_record('welcome_promotions',$p);return $p;
}
function welcome_status(?array $user=null,bool $lock=false): ?array {
    $user??=current_user();if(!$user || $user['role']!=='customer' || !welcome_ready())return null;
    $p=row('SELECT w.*,o.reference AS order_reference FROM welcome_promotions w LEFT JOIN orders o ON o.id=w.redeemed_order_id WHERE w.user_id=?'.($lock && env('DB_DRIVER')==='mysql'?' FOR UPDATE':''),[$user['id']]);
    if(!$p)return null;
    if($p['status']==='active' && $p['expires_at']<=welcome_now()){
        query("UPDATE welcome_promotions SET status='expired',reason='time_elapsed' WHERE id=? AND status='active' AND expires_at<=?",[$p['id'],welcome_now()]);
        // Reload: another checkout may have redeemed it before this update.
        $p=row('SELECT w.*,o.reference AS order_reference FROM welcome_promotions w LEFT JOIN orders o ON o.id=w.redeemed_order_id WHERE w.id=?',[$p['id']]);
    }
    if($p['status']==='active' && row('SELECT id FROM orders WHERE user_id=? OR LOWER(TRIM(email))=?',[$user['id'],strtolower(trim($user['email']))])){
        query("UPDATE welcome_promotions SET status='expired',reason='first_order_placed' WHERE id=? AND status='active'",[$p['id']]);
        $p=row('SELECT w.*,o.reference AS order_reference FROM welcome_promotions w LEFT JOIN orders o ON o.id=w.redeemed_order_id WHERE w.id=?',[$p['id']]);
    }
    return $p;
}
function welcome_cart_discount(array $items,?array $promotion): array {
    $eligible=0;foreach($items as $line)if($line['product']['sale_price']===null)$eligible+=$line['line_total'];
    $amount=$promotion && $promotion['status']==='active' && $promotion['expires_at']>welcome_now()?intdiv($eligible*(int)$promotion['percentage']+50,100):0;
    return ['discount'=>min($eligible,max(0,$amount)),'eligible_subtotal'=>$eligible,'welcome'=>$promotion];
}
function welcome_redeem(array $promotion,string $orderId,int $amount): void {
    if($amount<=0)return;
    if(query("UPDATE welcome_promotions SET status='redeemed',redeemed_order_id=?,redeemed_at=?,discount_amount=?,reason='' WHERE id=? AND user_id=? AND status='active' AND redeemed_order_id IS NULL AND expires_at>?",[$orderId,welcome_now(),$amount,$promotion['id'],$promotion['user_id'],welcome_now()])->rowCount()!==1)throw new DomainException('Your welcome offer expired or was already used. Please review checkout again.');
}
function welcome_customer_data(): array {
    $p=welcome_status();if(!$p)return ['status'=>'unavailable'];
    return ['status'=>$p['status'],'percentage'=>(int)$p['percentage'],'expires_at'=>$p['expires_at'].' UTC','order_reference'=>$p['order_reference']??null,'server_time'=>welcome_now().' UTC'];
}
function welcome_expiry_label(string $utc): string {
    $date=new DateTimeImmutable($utc,new DateTimeZone('UTC'));return $date->setTimezone(new DateTimeZone(env('APP_TIMEZONE','Asia/Beirut')))->format('j M Y, g:i A T');
}
