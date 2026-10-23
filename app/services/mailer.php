<?php
function welcome_mail_ready(): bool {
    if(env('APP_ENV')==='test'&&env('MAIL_TRANSPORT')==='test')return true;
    return env('MAIL_TRANSPORT','smtp')==='smtp'&&env('MAIL_HOST')!==''&&env('MAIL_USER')!==''&&env('MAIL_PASSWORD')!==''&&filter_var(env('MAIL_FROM_ADDRESS'),FILTER_VALIDATE_EMAIL)&&in_array(env('MAIL_ENCRYPTION','ssl'),['ssl','tls'],true)&&in_array(env('MAIL_PORT','465'),['465','587'],true);
}
function welcome_send_verification(string $email,string $url): void {
    if(!welcome_mail_ready())throw new DomainException('Email verification is not available yet. Please contact the store or try again later.');
    $brand=settings('branding')['name'];$subject='Verify your email for your welcome offer';
    $text="Welcome to $brand!\n\nVerify your email to receive your welcome discount code:\n$url\n\nOpen this link and click Verify my email. The link expires in 15 minutes and can be used once. If you did not request this, ignore this email.\n";
    store_send_email($email,$subject,$text,['url'=>$url]);
}
function store_send_email(string $email,string $subject,string $text,array $testMetadata=[]): void {
    if(!welcome_mail_ready()||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Email delivery is not configured.');
    $brand=settings('branding')['name'];
    if(env('APP_ENV')==='test'&&env('MAIL_TRANSPORT')==='test'){
        // Explicit local test transport only. Never enabled by missing production credentials.
        $dir=ROOT.'/storage/test-mail';if(!is_dir($dir))mkdir($dir,0700,true);
        $file=$dir.'/'.uuid().'.json';if(file_put_contents($file,json_encode(['to'=>$email,'subject'=>$subject,'text'=>$text,...$testMetadata]),LOCK_EX)===false)throw new RuntimeException('Test email capture failed.');@chmod($file,0600);return;
    }
    require_once ROOT.'/app/lib/PHPMailer/Exception.php';require_once ROOT.'/app/lib/PHPMailer/PHPMailer.php';require_once ROOT.'/app/lib/PHPMailer/SMTP.php';
    try{
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);$mail->isSMTP();$mail->Host=env('MAIL_HOST');$mail->SMTPAuth=true;$mail->Username=env('MAIL_USER');$mail->Password=env('MAIL_PASSWORD');
        $mail->SMTPSecure=env('MAIL_ENCRYPTION','ssl')==='ssl'?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS:\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port=(int)env('MAIL_PORT','465');$mail->Timeout=10;$mail->SMTPDebug=0;$mail->CharSet='UTF-8';$mail->setFrom(env('MAIL_FROM_ADDRESS'),env('MAIL_FROM_NAME',$brand));$mail->addAddress($email);$mail->Subject=$subject;$mail->Body=$text;$mail->send();
    }catch(Throwable){error_log('Store SMTP delivery failed. Check the configured sender and SMTP settings.');throw new DomainException('We could not send the email. Please try again later or contact the store.');}
}
function notify_admin_new_order(array $order): void {
    // Called only for a newly committed order, never for checkout replays.
    // Delivery failures must never make a saved order look like a failed checkout.
    try{
        $recipient=trim((string)(settings('store')['order_notification_email']??''));
        if($recipient==='')$recipient=env('MAIL_FROM_ADDRESS');
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new DomainException('Set an order notification email in Store settings.');
        $currency=$order['currency'];$amount=static fn($value)=>$currency.' '.number_format((int)$value/100,2,'.',',');
        $text="A new order is ready to process.\n\nOrder: ".$order['reference']."\nStatus: Pending\nPayment: Cash on delivery (unpaid)\n\nCustomer: ".$order['customer_name']."\nEmail: ".$order['email']."\nPhone: ".$order['phone']."\nCity: ".$order['city']."\nAddress: ".$order['address']."\n\nItems:\n";
        foreach(rows('SELECT * FROM order_items WHERE order_id=?',[$order['id']]) as $item){
            $text.=$item['quantity'].' × '.$item['product_name'];
            $variant=json_decode($item['variant_snapshot']??'null',true);
            foreach(['size','color','sku'] as $key)if(!empty($variant[$key]))$text.=' | '.ucfirst($key).': '.$variant[$key];
            $text.=' | Unit price: '.$amount($item['unit_price'])."\n";
        }
        $discount=(int)($order['offer_discount']??0)+(int)($order['promo_discount']??0)+(int)($order['welcome_discount']??0);
        $text.="\nSubtotal: ".$amount($order['subtotal'])."\nDiscount: ".$amount($discount)."\nDelivery: ".$amount($order['delivery_fee'])."\nTax: ".$amount($order['tax'])."\nTotal to collect: ".$amount($order['total'])."\n";
        if($order['notes']!=='')$text.="\nCustomer notes: ".$order['notes']."\n";
        $origin=rtrim(env('APP_URL'),'/');
        if(filter_var($origin,FILTER_VALIDATE_URL))$text.="\nOpen in admin (sign-in required):\n".$origin.'/admin/orders?view='.rawurlencode($order['id'])."\n";
        store_send_email($recipient,'New order '.$order['reference'].' — '.settings('branding')['name'],$text,['kind'=>'new_order','order_id'=>$order['id']]);
    }catch(Throwable){error_log('Admin order email failed for order '.$order['id'].'. Check order notification recipient and SMTP configuration. The order was saved successfully.');}
}
