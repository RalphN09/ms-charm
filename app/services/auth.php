<?php
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $u=row('SELECT id,name,email,phone,role,active,auth_version FROM users WHERE id=?',[$_SESSION['user_id']]);
    if (!$u || !$u['active'] || (int)$u['auth_version']!==($_SESSION['auth_version']??-1)) { unset($_SESSION['user_id']); return null; }
    return $u;
}
function require_user(): array {
    $u=current_user(); if (!$u) { flash('Please sign in to continue.','error'); redirect('/login'); } return $u;
}
function require_admin(bool $ownerOnly=false): array {
    $u=current_user();
    $roles=$ownerOnly?['owner','developer']:['owner','admin','developer'];
    if (!$u || !in_array($u['role'],$roles,true)) { http_response_code(403); throw new RuntimeException('Access denied.',403); }
    return $u;
}
function authenticate(array $data): void {
    rate_limit('login',10,900);
    $email=strtolower(text_input($data,'email',254,true)); $password=text_input($data,'password',200,true);
    $u=row('SELECT * FROM users WHERE email=?',[$email]);
    $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    if (!password_verify($password,$u['password_hash']??$dummy) || !$u || !$u['active']) throw new DomainException('Email or password is incorrect.');
    session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['auth_version']=(int)$u['auth_version']; unset($_SESSION['csrf']);
}
function register_customer(array $data): void {
    rate_limit('register',5,3600);
    $name=text_input($data,'name',120,true); $email=strtolower(text_input($data,'email',254,true));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email address.');
    $password=text_input($data,'password',200,true);
    if (strlen($password)<12) throw new DomainException('Use a password with at least 12 characters.');
    if (row('SELECT id FROM users WHERE email=?',[$email])) throw new DomainException('This email is already registered. Please sign in.');
    $id=uuid();
    db()->beginTransaction();
    try{
        insert_record('users',['id'=>$id,'name'=>$name,'email'=>$email,'phone'=>text_input($data,'phone',40),'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'customer','active'=>1,'auth_version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $promotion=welcome_issue($id,$email);db()->commit();
    }catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();throw $ex;}
    session_regenerate_id(true); $_SESSION['user_id']=$id; $_SESSION['auth_version']=1; unset($_SESSION['csrf']);
    if($promotion)flash('Your '.$promotion['percentage'].'% welcome discount is ready! Use it before '.welcome_expiry_label($promotion['expires_at']).'.');
}
function update_account(array $data): void {
    $u=require_user(); $changes=['name'=>text_input($data,'name',120,true),'phone'=>text_input($data,'phone',40),'updated_at'=>now()];
    $password=text_input($data,'new_password',200);
    if ($password!=='') {
        $record=row('SELECT password_hash FROM users WHERE id=?',[$u['id']]);
        if (!password_verify(text_input($data,'current_password',200,true),$record['password_hash'])) throw new DomainException('Current password is incorrect.');
        if (strlen($password)<12) throw new DomainException('Use a password with at least 12 characters.');
        $changes['password_hash']=password_hash($password,PASSWORD_DEFAULT); $changes['auth_version']=(int)$u['auth_version']+1;
        $_SESSION['auth_version']=$changes['auth_version']; session_regenerate_id(true);
    }
    update_record('users',$u['id'],$changes);
}
