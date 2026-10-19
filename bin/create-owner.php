<?php
require dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit('CLI only');
echo 'Owner name: '; $name=trim((string)fgets(STDIN));
echo 'Owner email: '; $email=strtolower(trim((string)fgets(STDIN)));
echo 'Password (12+ characters; terminal input is visible): '; $password=trim((string)fgets(STDIN));
if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) exit("Invalid details.\n");
if (row('SELECT id FROM users WHERE email=?',[$email])) exit("Email already exists.\n");
insert_record('users',['id'=>uuid(),'name'=>$name,'email'=>$email,'phone'=>'','password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'owner','active'=>1,'auth_version'=>1,'created_at'=>now(),'updated_at'=>now()]);
echo "Owner created. Sign in at /login. No default credentials are installed.\n";
