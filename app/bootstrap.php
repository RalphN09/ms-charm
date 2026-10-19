<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
foreach (is_file(ROOT.'/.env') ? file(ROOT.'/.env', FILE_IGNORE_NEW_LINES) : [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$key, $value] = explode('=', $line, 2);
    if (getenv(trim($key)) === false) putenv(trim($key).'='.trim($value, " \t\n\r\0\x0B\"'"));
}
function env(string $key, string $default = ''): string { return getenv($key) !== false ? (string)getenv($key) : $default; }
$publicPath=env('PUBLIC_PATH','public');
define('PUBLIC_ROOT',preg_match('~^(?:[A-Za-z]:|/)~',$publicPath)?$publicPath:ROOT.'/'.$publicPath);
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Beirut'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT.'/storage/application.log');
require_once ROOT.'/app/database.php';
require_once ROOT.'/app/helpers.php';
require_once ROOT.'/app/services/auth.php';
require_once ROOT.'/app/services/welcome.php';
require_once ROOT.'/app/services/category-hierarchy.php';
require_once ROOT.'/app/services/catalog.php';
require_once ROOT.'/app/services/offers.php';
require_once ROOT.'/app/services/delivery.php';
require_once ROOT.'/app/services/orders.php';
require_once ROOT.'/app/services/cms.php';
if (PHP_SAPI !== 'cli') {
    $sessionPath=ROOT.'/storage/sessions';
    if(!is_dir($sessionPath))mkdir($sessionPath,0700,true);
    session_save_path($sessionPath);
    ini_set('session.gc_maxlifetime','7200');
    session_name('mscharm_session');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>env('SESSION_SECURE', 'true') === 'true', 'httponly'=>true, 'samesite'=>'Lax']);
    ini_set('session.use_strict_mode', '1');
    if(!session_start())throw new RuntimeException('Secure session storage is unavailable.');
    if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) {
        $_SESSION = []; session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    define('CSP_NONCE',base64_encode(random_bytes(18)));
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self'; script-src 'self' 'nonce-".CSP_NONCE."'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'");
    header('Cache-Control: no-store');
    if (env('APP_ENV', 'production') === 'production' && env('SESSION_SECURE', 'true') === 'true') header('Strict-Transport-Security: max-age=31536000');
}
