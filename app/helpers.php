<?php
function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function uuid(): string { return bin2hex(random_bytes(16)); }
function money(int $cents): string { return settings('store')['currency'].' '.number_format($cents/100,2); }
function cents(mixed $amount): int {
    if (!is_scalar($amount) || !preg_match('/^\d{1,7}(\.\d{1,2})?$/', (string)$amount)) throw new DomainException('Enter a valid amount with up to two decimal places.');
    return (int)round((float)$amount*100);
}
function text_input(array $data,string $key,int $max=5000,bool $required=false): string {
    if (isset($data[$key]) && !is_scalar($data[$key])) throw new DomainException('Invalid '.$key.'.');
    $v=trim((string)($data[$key]??''));
    if (($required && $v==='') || mb_strlen($v)>$max) throw new DomainException('Please check '.str_replace('_',' ',$key).'.');
    return $v;
}
function int_input(array $data,string $key,int $min=0,int $max=1000000): int {
    $v=$data[$key]??null;
    if (filter_var($v,FILTER_VALIDATE_INT)===false || (int)$v<$min || (int)$v>$max) throw new DomainException('Please check '.str_replace('_',' ',$key).'.');
    return (int)$v;
}
function flag(array $data,string $key): int { return isset($data[$key]) && in_array($data[$key],['1',1,true,'on'],true) ? 1 : 0; }
function valid_url(string $value,bool $relative=false): string {
    if ($value==='') return '';
    if ($relative && preg_match('~^/(?!/)[^\s\\\\]*$~',$value)) return $value;
    if (!filter_var($value,FILTER_VALIDATE_URL) || !in_array(parse_url($value,PHP_URL_SCHEME),['https'],true)) throw new DomainException('Use an HTTPS URL'.($relative?' or a path starting with /.':'.'));
    return $value;
}
function slug(string $s): string { return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($s)),'-'); }
function csrf(): string { return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function verify_csrf(): void {
    $token=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf']??'');
    if (!is_string($token) || !hash_equals(csrf(),$token)) throw new DomainException('Your session changed. Refresh the page and try again.');
}
function redirect(string $path): never { header('Location: '.$path,true,303); exit; }
function flash(string $message,string $kind='success'): void { $_SESSION['flash']=['message'=>$message,'kind'=>$kind]; }
function json_response(mixed $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json'); echo json_encode($data,JSON_UNESCAPED_SLASHES); exit; }
function render(string $view,array $vars=[]): void {
    global $path;
    extract($vars); ob_start(); require ROOT.'/app/views/'.$view.'.php'; $content=ob_get_clean(); require ROOT.'/app/views/layout.php';
}
function paragraphs(string $value): string { return implode('',array_map(fn($p)=>'<p>'.nl2br(e($p)).'</p>',preg_split('/\n\s*\n/',$value))); }
function image_url(string $url): string { try { return valid_url($url,true); } catch (Throwable) { return '/assets/favicon.svg'; } }
function audit(string $action,string $entity,string $entityId): void {
    insert_record('audit_log',['id'=>uuid(),'user_id'=>$_SESSION['user_id']??null,'action'=>$action,'entity'=>$entity,'entity_id'=>$entityId,'created_at'=>now()]);
}
function rate_limit(string $action,int $limit,int $seconds): void {
    $key=hash('sha256',$action.'|'.($_SERVER['REMOTE_ADDR']??'cli'));
    $cutoff=gmdate('Y-m-d H:i:s',time()-$seconds);
    if ((int)query('SELECT COUNT(*) FROM rate_limits WHERE bucket=? AND created_at>?',[$key,$cutoff])->fetchColumn()>=$limit) throw new DomainException('Too many attempts. Please try again later.');
    insert_record('rate_limits',['id'=>uuid(),'bucket'=>$key,'created_at'=>now()]);
    query('DELETE FROM rate_limits WHERE created_at<?',[gmdate('Y-m-d H:i:s',time()-86400)]);
}
