<?php
function email_verification_ready(): bool {
    try{query('SELECT id,email,token_hash,expires_at,verified_at FROM welcome_email_verifications WHERE 1=0');return true;}
    catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1146||str_contains($e->getMessage(),'no such table'))return false;throw $e;}
}
function welcome_email_verified(string $key): bool {return email_verification_ready()&&row('SELECT id FROM welcome_email_verifications WHERE id=? AND verified_at IS NOT NULL'.promo_for_update(),[$key])!==null;}
function welcome_verification_origin(): string {
    $origin=rtrim(env('APP_URL'),'/');$parts=parse_url($origin);
    if(!$parts||!isset($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||($parts['path']??'')!==''||(($parts['scheme']??'')!=='https'&&!(env('APP_ENV')==='test'&&($parts['scheme']??'')==='http'&&in_array($parts['host'],['localhost','127.0.0.1'],true))))throw new DomainException('Email verification is not available yet. Please contact the store.');
    return $origin;
}
function welcome_verification_request(array $data): array {
    if(!promo_ready()||!email_verification_ready())throw new DomainException('Email verification is being set up. Please try again later.');
    rate_limit('welcome_verification_request',8,3600);$email=promo_email(text_input($data,'email',254,true));$key=promo_email_key($email);
    if(!welcome_config()['enabled'])throw new DomainException('The welcome offer is currently unavailable.');
    if(!welcome_mail_ready())throw new DomainException('Email verification is not available yet. Please contact the store or try again later.');
    $origin=welcome_verification_origin();$reply=['verification_required'=>true,'message'=>'If this email is eligible, a verification link is on its way. Check your inbox and spam folder, then open the link within 15 minutes. You can request another email after 60 seconds.'];
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$stamp=promo_now();
    db()->beginTransaction();try{
        promo_lock();$old=row('SELECT * FROM welcome_email_verifications WHERE id=?'.promo_for_update(),[$key]);
        if($old&&$old['sent_at']&&strtotime($old['sent_at'].' UTC')>strtotime($stamp.' UTC')-60){db()->commit();return $reply;}
        $sameWindow=$old&&strtotime($old['window_start'].' UTC')>strtotime($stamp.' UTC')-3600;
        $window=$sameWindow?$old['window_start']:$stamp;$attempts=$sameWindow?(int)$old['attempts']:0;
        if($attempts>=3){db()->commit();return $reply;}
        $claim=row('SELECT p.* FROM promo_codes p JOIN welcome_email_claims w ON w.promo_id=p.id WHERE w.email_key=?'.promo_for_update(),[$key]);
        if(promo_prior_purchase($key,$email)||($claim&&(promo_status($claim)!=='Active'||promo_counts($claim['id'],$key)['redeemed']>0))){db()->commit();return $reply;}
        $v=['email'=>$email,'token_hash'=>$hash,'expires_at'=>gmdate('Y-m-d H:i:s',strtotime($stamp.' UTC')+900),'sent_at'=>$stamp,'window_start'=>$window,'attempts'=>$attempts+1];
        if($old)update_record('welcome_email_verifications',$key,$v);else insert_record('welcome_email_verifications',['id'=>$key,...$v,'verified_at'=>null,'created_at'=>$stamp]);
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    try{welcome_send_verification($email,$origin.'/welcome/verify?token='.$token);}catch(Throwable $e){query('UPDATE welcome_email_verifications SET token_hash=NULL,email=NULL,expires_at=NULL,sent_at=NULL WHERE id=? AND token_hash=?',[$key,$hash]);throw $e;}
    return $reply;
}
function welcome_verification_complete(array $data): array {
    if(!promo_ready()||!email_verification_ready())throw new DomainException('Email verification is being set up.');
    rate_limit('welcome_verification_confirm',20,3600);$token=text_input($data,'token',64,true);if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new DomainException('This verification link is invalid or expired. Request a new email.');
    $oldSession=$_SESSION;db()->beginTransaction();try{
        promo_lock();$v=row('SELECT * FROM welcome_email_verifications WHERE token_hash=?'.promo_for_update(),[hash('sha256',$token)]);
        if(!$v||!$v['email']||$v['expires_at']<=promo_now())throw new DomainException('This verification link is invalid, expired or already used. Request a new email.');
        update_record('welcome_email_verifications',$v['id'],['verified_at'=>promo_now(),'token_hash'=>null,'email'=>null,'expires_at'=>null]);
        $_SESSION['welcome_verified'][$v['id']]=promo_now();$result=promo_claim(['email'=>$v['email']]);
        db()->commit();return $result;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$_SESSION=$oldSession;throw $e;}
}

