<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
$method=$_SERVER['REQUEST_METHOD'];
try {
    if($path==='/assets/theme.css') {
        $b=settings('branding');header('Content-Type: text/css');
        foreach(['primary','secondary','accent'] as $key)if(!preg_match('/^#[a-fA-F0-9]{6}$/',$b[$key]))$b[$key]='#425c4b';
        echo ':root{--sage:'.$b['primary'].';--white:'.$b['secondary'].';--accent:'.$b['accent'].'}';exit;
    }
    if($path==='/robots.txt'){header('Content-Type: text/plain');echo "User-agent: *\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\nDisallow: /confirmation\nSitemap: ".rtrim(env('APP_URL'),' /')."/sitemap.xml\n";exit;}
    if($path==='/sitemap.xml'){
        header('Content-Type: application/xml');$base=rtrim(env('APP_URL'),' /');$urls=['/','/shop','/categories','/contact','/reviews'];foreach(products() as $p)$urls[]='/product/'.$p['slug'];foreach(rows('SELECT slug FROM pages WHERE active=1') as $p)$urls[]='/'.$p['slug'];
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($urls as $url)echo '<url><loc>'.e($base.$url).'</loc></url>';echo '</urlset>';exit;
    }
    if($path==='/api/offers'){if($method!=='GET')json_response(['message'=>'Method not allowed.'],405);json_response(offers_public());}
    if($path==='/api/welcome'){
        if($method!=='GET')json_response(['message'=>'Method not allowed.'],405);
        $welcomeUser=current_user();if(!$welcomeUser)json_response(['message'=>'Please sign in.'],401);
        json_response(welcome_customer_data());
    }
    if($method==='POST') {
        verify_csrf();
        if($path==='/api/cart') {
            try{$data=json_decode(file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new DomainException('Invalid bag update.');}if(!is_array($data))throw new DomainException('Invalid bag update.');
            $cart=update_cart($data);json_response(['ok'=>true,'count'=>$cart['count'],'total'=>money($cart['total'])]);
        }
        if($path==='/api/delivery'){
            $cart=delivery_update_quote($_POST);ob_start();require ROOT.'/app/views/totals.php';$summary=ob_get_clean();
            json_response(['ok'=>true,'ready'=>$cart['delivery_details']['status']==='ready','summary'=>$summary,'message'=>$cart['delivery_details']['message'],'city'=>$cart['delivery_details']['locality'],'city_locked'=>$cart['delivery_details']['mode']==='area'&&$cart['delivery_details']['locality']!=='','city_required'=>$cart['delivery_details']['mode']!=='area'||$cart['delivery_details']['locality']==='','currency'=>settings('store')['currency']]);
        }
        switch($path) {
            case '/login': authenticate($_POST);$u=current_user();redirect(in_array($u['role'],['owner','admin','developer'],true)?'/admin':'/account');
            case '/register': register_customer($_POST);redirect('/account');
            case '/logout': $_SESSION=[];session_regenerate_id(true);redirect('/');
            case '/account': update_account($_POST);flash('Your details have been saved.');redirect('/account');
            case '/checkout': if(isset($_POST['review_delivery'])){delivery_update_quote($_POST);flash('Delivery and totals updated. Please review them before placing your order.');redirect('/checkout');}$order=place_order($_POST);redirect('/confirmation/'.$order['id']);
            case '/reviews': submit_review($_POST);flash('Thank you. Your review will appear after approval.');redirect('/reviews');
            case '/contact':
                if(!settings('contact')['form_enabled'])throw new DomainException('The contact form is currently closed.');
                rate_limit('contact',5,3600);$email=text_input($_POST,'email',254,true);if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email address.');
                insert_record('messages',['id'=>uuid(),'name'=>text_input($_POST,'name',120,true),'email'=>$email,'message'=>text_input($_POST,'message',5000,true),'status'=>'new','created_at'=>now()]);flash('Your message has been sent to the store.');redirect('/contact');
        }
        if(str_starts_with($path,'/admin')) {
            require_admin();$section=text_input($_POST,'section',40,true);$action=text_input($_POST,'action',40,true);
            if(!welcome_ready()){require_once ROOT.'/database/welcome-migration.php';welcome_migrate_up();}
            if($section==='delivery'){if(!delivery_ready()){require_once ROOT.'/database/delivery-migration.php';delivery_migrate_up();}delivery_admin_action($_POST);flash('Delivery settings saved.');redirect('/admin/delivery');}
            if($section==='offers'){if(!offers_ready()){require_once ROOT.'/database/offers-migration.php';offers_migrate_up();}offer_admin_action($_POST);flash('Offer saved.');redirect('/admin/offers');}
            if($action==='settings')save_settings($section,$_POST);
            elseif($action==='save')cms_write($section,$_POST);
            elseif($action==='delete')cms_delete($section,text_input($_POST,'id',32,true));
            elseif($action==='duplicate') {
                if($section!=='products')throw new DomainException('Only products can be duplicated.');
                $p=row('SELECT * FROM products WHERE id=?',[text_input($_POST,'id',32,true)]);if(!$p)throw new DomainException('Product not found.');
                $p['id']=uuid();$p['name'].=' (copy)';$p['slug'].='-'.substr($p['id'],0,6);$p['active']=0;$p['created_at']=$p['updated_at']=now();insert_record('products',$p);audit('duplicate','products',$p['id']);
            }elseif($action==='order')change_order($_POST);
            elseif($action==='message') { $status=text_input($_POST,'status',20,true);if(!in_array($status,['new','read','resolved'],true))throw new DomainException('Invalid message status.');update_record('messages',text_input($_POST,'id',32,true),['status'=>$status]); }
            else throw new DomainException('Unknown action.');
            flash('Changes saved.');redirect('/admin/'.$section);
        }
        http_response_code(404);render('error',['title'=>'Page not found','message'=>'We couldn’t find that page.']);exit;
    }
    if($method!=='GET'){http_response_code(405);header('Allow: GET, POST');exit;}
    if($path==='/api/cart'){json_response(cart_totals());}
    if($path==='/api/delivery')json_response(['message'=>'Use POST with a checkout session and CSRF token.'],405);
    if(str_starts_with($path,'/admin')) {
        require_admin();$section=explode('/',trim($path,'/'))[1]??'dashboard';
        if(!welcome_ready()){require_once ROOT.'/database/welcome-migration.php';welcome_migrate_up();}
        $allowed=['delivery','offers','dashboard','products','categories','orders','customers','reviews','homepage','pages','navigation','promotions','welcome','social','contact','branding','seo','footer','store','users','system','messages'];
        if(!in_array($section,$allowed,true))throw new DomainException('Unknown admin section.');
        if(in_array($section,['users','system'],true))require_admin(true);
        if($section==='delivery' && !delivery_ready()){require_once ROOT.'/database/delivery-migration.php';delivery_migrate_up();}
        if($section==='offers' && !offers_ready()){require_once ROOT.'/database/offers-migration.php';offers_migrate_up();}
        $record=null;if(!empty($_GET['edit']) && in_array($section,['products','categories','reviews','homepage','pages','navigation','promotions','users'],true))$record=row('SELECT * FROM '.cms_table($section).' WHERE id=?',[text_input($_GET,'edit',32)]);
        render('admin',['title'=>'Store management','section'=>$section,'record'=>$record]);exit;
    }
    switch($path) {
        case '/': render('home',['title'=>settings('seo')['title']]);break;
        case '/shop': render('shop',['title'=>'The collection','list'=>products($_GET)]);break;
        case '/offers': render('offers',['title'=>'Offers & Discounts']);break;
        case '/categories': render('categories',['title'=>'Find your favorite']);break;
        case '/cart': render('cart',['title'=>'Your bag','cart'=>cart_totals()]);break;
        case '/checkout':
            delivery_prefill();$cart=cart_totals();if(!$cart['items'])redirect('/cart');$key=uuid();$_SESSION['checkout_keys'][$key]=true;if(count($_SESSION['checkout_keys'])>20)array_shift($_SESSION['checkout_keys']);
            delivery_remember_quote($key,$cart);
            foreach(array_keys($_SESSION['checkout_quotes']) as $quoteKey)if(!isset($_SESSION['checkout_keys'][$quoteKey]))unset($_SESSION['checkout_quotes'][$quoteKey],$_SESSION['delivery_revisions'][$quoteKey]);
            render('checkout',['title'=>'A little closer to yours','cart'=>$cart,'key'=>$key]);break;
        case '/login': case '/register':render('auth',['title'=>$path==='/login'?'Welcome back':'Make yourself at home','mode'=>trim($path,'/')]);break;
        case '/account':$u=require_user();render('account',['title'=>'Your account','user'=>$u]);break;
        case '/contact':render('contact',['title'=>settings('contact')['heading']]);break;
        case '/reviews':render('reviews',['title'=>'Words from our community']);break;
        default:
            if(preg_match('~^/product/([a-z0-9-]+)$~',$path,$m)) { $p=product_by_slug($m[1]);if(!$p)throw new RuntimeException('Page not found.',404);render('product',['title'=>$p['name'],'product'=>$p,'description'=>$p['short_description']]); }
            elseif(preg_match('~^/confirmation/([a-f0-9]{32})$~',$path,$m)) {
                $o=row('SELECT * FROM orders WHERE id=?',[$m[1]]);$u=current_user();
                if(!$o || (empty($_SESSION['order_access'][$o['id']]) && (!$u || $o['user_id']!==$u['id'])))throw new RuntimeException('Page not found.',404);
                render('confirmation',['title'=>'Your order is in','order'=>$o]);
            }else {
                $page=row('SELECT * FROM pages WHERE slug=? AND active=1',[trim($path,'/')]);if(!$page)throw new RuntimeException('Page not found.',404);
                render('page',['title'=>$page['meta_title']?:$page['title'],'description'=>$page['meta_description'],'page'=>$page]);
            }
    }
}catch(DomainException $ex) {
    if(str_starts_with($path,'/api/'))json_response(['ok'=>false,'message'=>$ex->getMessage()],422);
    if($method==='POST') {flash($ex->getMessage(),'error');$back=str_starts_with($path,'/admin')?'/admin/'.(preg_match('/^[a-z]+$/',(string)($_POST['section']??''))?$_POST['section']:'dashboard'):$path;redirect($back);}
    http_response_code(422);render('error',['title'=>'Please check your details','message'=>$ex->getMessage()]);
}catch(Throwable $ex) {
    error_log((string)$ex);$status=in_array($ex->getCode(),[403,404],true)?$ex->getCode():500;http_response_code($status);
    $message=match($status){403=>'This area is available to authorized store administrators.',404=>'We couldn’t find that page.',default=>'Something went wrong. Please try again shortly.'};
    if(str_starts_with($path,'/api/'))json_response(['ok'=>false,'message'=>$message],$status);
    if($ex instanceof PDOException && str_starts_with((string)$ex->getCode(),'23') && $method==='POST') {flash('This email or URL slug is already in use. Choose another.','error');redirect(str_starts_with($path,'/admin')?'/admin':'/login');}
    try {render('error',['title'=>$status===403?'Access restricted':($status===404?'Page not found':'Please try again'),'message'=>$message]);}catch(Throwable){echo '<h1>Store setup required</h1><p>Please follow the installation guide before opening the store.</p>';}
}
