<?php
function settings(string $group): array {
    $s=row('SELECT value FROM settings WHERE id=?',[$group]);
    if(!$s)throw new RuntimeException('Store setup is incomplete. Run the installer.');
    return json_decode($s['value'],true,512,JSON_THROW_ON_ERROR);
}
function setting_fields(): array {
    return [
        'welcome'=>['enabled'=>['Enable welcome offer for future registrations','checkbox'],'percentage'=>['Welcome discount percentage (1–100)','number',true],'duration_hours'=>['Validity in hours (1–168)','number',true]],
        'branding'=>['name'=>['Business name','text',true],'slogan'=>['Slogan','text',true],'primary'=>['Primary color','color',true],'secondary'=>['Secondary color','color',true],'accent'=>['Accent color','color',true],'logo'=>['Logo image URL','image'],'favicon'=>['Favicon URL','image']],
        'contact'=>['email'=>['Business email','email'],'phone'=>['Phone number','text'],'whatsapp'=>['WhatsApp number','text'],'address'=>['Address','textarea'],'city'=>['City / area','text'],'hours'=>['Business hours','text'],'heading'=>['Contact page heading','text'],'description'=>['Contact page introduction','textarea'],'form_enabled'=>['Enable contact form','checkbox']],
        'social'=>['instagram'=>['Instagram URL','url'],'facebook'=>['Facebook URL','url'],'tiktok'=>['TikTok URL','url'],'whatsapp'=>['WhatsApp HTTPS link','url'],'other'=>['Other social link','url']],
        'footer'=>['text'=>['Footer description','textarea'],'copyright'=>['Copyright text','text']],
        'seo'=>['title'=>['Default website title','text',true],'description'=>['Default meta description','textarea',true],'sharing_image'=>['Social sharing image URL','image']],
        'store'=>['currency'=>['Currency code (e.g. USD)','text',true],'delivery_fee'=>['Legacy flat delivery fee (used when location-based delivery is off)','money',true],'free_delivery_threshold'=>['Free delivery from (0 to disable)','money',true],'minimum_order'=>['Minimum order amount','money',true],'tax_rate'=>['Tax rate (%)','number',true],'open'=>['Accept new orders','checkbox'],'delivery_description'=>['Delivery description','textarea'],'payment_label'=>['Cash on delivery label','text',true],'announcement'=>['Announcement bar text','text'],'announcement_enabled'=>['Show announcement bar','checkbox']],
    ];
}
function cms_fields(string $entity): array {
    if(in_array($entity,['categories','products'],true)){require_admin();ensure_category_hierarchy();}
    return match($entity) {
        'products'=>['name'=>['Product name','text',true],'slug'=>['URL slug','text',true],'category_id'=>['Category','category'],'short_description'=>['Short description','textarea'],'description'=>['Full description','textarea',true],'price'=>['Price','money',true],'sale_price'=>['Sale price (optional)','money'],'images'=>['Image URLs (one per line)','images'],'stock'=>['Stock quantity','number',true],'active'=>['Enabled / available','checkbox'],'featured'=>['Featured on homepage','checkbox'],'sort_order'=>['Display order','number',true]],
        'categories'=>['name'=>['Category name','text',true],'slug'=>['URL slug','text',true],'parent_id'=>['Parent category (optional)','parent_category'],'description'=>['Description','textarea'],'image'=>['Category image URL','image'],'active'=>['Enabled','checkbox'],'sort_order'=>['Display order','number',true]],
        'homepage'=>['title'=>['Section heading','text',true],'description'=>['Section description','textarea'],'image'=>['Section image URL','image'],'cta_text'=>['Button text','text'],'cta_url'=>['Button destination','link'],'active'=>['Show section','checkbox'],'sort_order'=>['Section order','number',true]],
        'pages'=>['title'=>['Page heading','text',true],'slug'=>['URL slug','text',true],'body'=>['Page content (plain text)','textarea',true],'meta_title'=>['SEO page title','text'],'meta_description'=>['SEO description','textarea'],'active'=>['Published','checkbox']],
        'navigation'=>['label'=>['Link label','text',true],'url'=>['Destination','link',true],'location'=>['Location','nav_location',true],'active'=>['Enabled','checkbox'],'sort_order'=>['Display order','number',true]],
        'promotions'=>['title'=>['Promotion title','text',true],'description'=>['Description','textarea'],'image'=>['Image URL','image'],'cta_text'=>['Button text','text'],'cta_url'=>['Button destination','link'],'active'=>['Enabled','checkbox'],'sort_order'=>['Display order','number',true]],
        'reviews'=>['name'=>['Customer name','text',true],'rating'=>['Rating (1–5)','number',true],'comment'=>['Review','textarea',true],'status'=>['Moderation status','review_status',true],'featured'=>['Featured on homepage','checkbox']],
        'users'=>['name'=>['Name','text',true],'email'=>['Email','email',true],'phone'=>['Phone','text'],'role'=>['Role','role',true],'active'=>['Active account','checkbox'],'password'=>['New password (12+ characters; blank to keep)','password']],
        default=>throw new DomainException('Unknown CMS section.'),
    };
}
function cms_table(string $entity): string { return $entity==='homepage'?'homepage_sections':$entity; }
function cms_write(string $entity,array $data): string {
    require_admin(in_array($entity,['users'],true));
    $fields=cms_fields($entity);$table=cms_table($entity);$id=text_input($data,'id',32);$old=$id?row('SELECT * FROM '.$table.' WHERE id=?',[$id]):null;
    if($id && !$old)throw new DomainException('Record not found.');
    if(!$old && in_array($entity,['homepage','reviews'],true))throw new DomainException('This section cannot be created here.');
    $clean=[];
    foreach($fields as $key=>[$label,$type]) {
        $required=$fields[$key][2]??false;
        $value=$type==='checkbox'?flag($data,$key):text_input($data,$key,in_array($type,['textarea','images'])?20000:500,$required);
        if($type==='number')$value=int_input($data,$key,$key==='rating'?1:0,$key==='rating'?5:1000000);
        if($type==='money')$value=$value===''?null:cents($value);
        if(in_array($type,['image','link'],true))$value=valid_url($value,true);
        if($type==='email' && !filter_var($value,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email address.');
        if($key==='slug') { if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$value))throw new DomainException('Slugs can contain lowercase letters, numbers, and hyphens.'); }
        if($type==='images') { $urls=array_filter(array_map('trim',preg_split('/\r?\n/',$value)));if(count($urls)>12)throw new DomainException('Use no more than 12 product images.');$value=json_encode(array_map(fn($url)=>valid_url($url,true),$urls)); }
        if($type==='category') { if($value && !row('SELECT id FROM categories WHERE id=?',[$value]))throw new DomainException('Category not found.');$value=$value?:null; }
        if($type==='parent_category')$value=$value?:null;
        if($type==='nav_location' && !in_array($value,['header','footer'],true))throw new DomainException('Invalid navigation location.');
        if($type==='review_status' && !in_array($value,['pending','approved','rejected'],true))throw new DomainException('Invalid review status.');
        if($type==='role' && !in_array($value,['customer','admin','owner','developer'],true))throw new DomainException('Invalid role.');
        if($type==='password') { if($value!=='' && strlen($value)<12)throw new DomainException('Use a password with at least 12 characters.');if($value!=='')$clean['password_hash']=password_hash($value,PASSWORD_DEFAULT);continue; }
        $clean[$key]=$value;
    }
    if($entity==='products') {
        if($clean['sale_price']!==null && $clean['sale_price']>=$clean['price'])throw new DomainException('Sale price must be lower than the regular price.');
        if(!empty($_FILES['images_upload']['name'][0])) { $urls=json_decode($clean['images'],true);foreach($_FILES['images_upload']['name'] as $i=>$name) if($name!=='')$urls[]=upload_image(['name'=>$name,'tmp_name'=>$_FILES['images_upload']['tmp_name'][$i],'size'=>$_FILES['images_upload']['size'][$i],'error'=>$_FILES['images_upload']['error'][$i]]);if(count($urls)>12)throw new DomainException('Use no more than 12 images.');$clean['images']=json_encode($urls); }
    }
    foreach(['image','logo','favicon'] as $imageField)if(isset($_FILES[$imageField.'_upload']) && $_FILES[$imageField.'_upload']['error']!==UPLOAD_ERR_NO_FILE)$clean[$imageField]=upload_image($_FILES[$imageField.'_upload']);
    if($entity==='pages') {
        $reserved=['admin','shop','categories','cart','checkout','confirmation','login','register','logout','account','contact','reviews','product','api','assets','uploads','sitemap.xml','robots.txt'];
        if(in_array($clean['slug'],$reserved,true))throw new DomainException('This URL is reserved by the store.');
        if($old && in_array($old['id'],['about','privacy','terms','delivery-returns','faq'],true) && $clean['slug']!==$old['slug'])throw new DomainException('Core store page URLs must remain stable. Their content and SEO are editable.');
    }
    if($entity==='users') {
        $clean['email']=strtolower($clean['email']);
        if(!$old && !isset($clean['password_hash']))throw new DomainException('A password is required for a new user.');
        $u=current_user();if($id===$u['id'] && (!$clean['active'] || $clean['role']!==$u['role']))throw new DomainException('You cannot disable or change your own role.');
        if($old)$clean['auth_version']=(int)$old['auth_version']+1;else $clean['auth_version']=1;
    }
    if($entity!=='navigation')$clean['updated_at']=now();
    if(!$old) { $id=uuid();$clean=['id'=>$id,...$clean];if(in_array($entity,['products','categories','users'],true))$clean['created_at']=now(); }
    $actorId=current_user()['id'];
    db()->beginTransaction();
    try {
        if($entity==='categories' && $clean['parent_id']){
            $all=rows('SELECT * FROM categories ORDER BY id'.(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''));
            if(!in_array($clean['parent_id'],array_column($all,'id'),true))throw new DomainException('Parent category not found.');
            if(in_array($clean['parent_id'],category_branch_ids($id,$all),true))throw new DomainException('A category cannot be its own parent or belong to one of its subcategories.');
        }
        if($entity==='users' && $old && $old['active'] && in_array($old['role'],['owner','developer'],true) && (!$clean['active'] || !in_array($clean['role'],['owner','developer'],true))) {
            $owners=rows("SELECT id FROM users WHERE active=1 AND role IN ('owner','developer')".(env('DB_DRIVER')==='mysql'?' FOR UPDATE':''));
            if(count($owners)<=1)throw new DomainException('Keep at least one active owner or developer.');
        }
        if($old)update_record($table,$id,$clean);else insert_record($table,$clean);
        audit($old?'update':'create',$entity,$id);db()->commit();
        if($entity==='users' && $id===$actorId)$_SESSION['auth_version']=$clean['auth_version'];
        return $id;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function cms_delete(string $entity,string $id): void {
    require_admin($entity==='users');cms_fields($entity);
    if(in_array($entity,['homepage','users'],true))throw new DomainException('Disable this record instead of deleting it.');
    if($entity==='pages' && in_array($id,['about','privacy','terms','faq','delivery-returns'],true))throw new DomainException('Core store pages cannot be deleted.');
    db()->beginTransaction();try {query('DELETE FROM '.cms_table($entity).' WHERE id=?',[$id]);audit('delete',$entity,$id);db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
}
function save_settings(string $group,array $data): void {
    require_admin();$fields=setting_fields()[$group]??null;if(!$fields)throw new DomainException('Unknown settings group.');$clean=[];
    foreach($fields as $key=>[$label,$type]) {
        $value=$type==='checkbox'?(bool)flag($data,$key):text_input($data,$key,5000,$fields[$key][2]??false);
        if($type==='money') { cents($value);$value=(float)$value; }
        if($type==='number') {
            if($group==='welcome')$value=int_input($data,$key,1,$key==='percentage'?100:168);
            else{if(!is_numeric($value) || (float)$value<0 || (float)$value>100)throw new DomainException('Tax rate must be between 0 and 100.');$value=(float)$value;}
        }
        if($type==='color' && !preg_match('/^#[0-9a-fA-F]{6}$/',$value))throw new DomainException('Use a six-digit hex color.');
        if($type==='image')$value=valid_url($value,true);
        if($type==='url')$value=valid_url($value);
        if($type==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid email address.');
        if($key==='currency' && !preg_match('/^[A-Z]{3}$/',$value))throw new DomainException('Use a three-letter uppercase currency code.');
        $clean[$key]=$value;
    }
    foreach(['logo','favicon'] as $key)if(isset($_FILES[$key.'_upload']) && $_FILES[$key.'_upload']['error']!==UPLOAD_ERR_NO_FILE)$clean[$key]=upload_image($_FILES[$key.'_upload']);
    db()->beginTransaction();try {update_record('settings',$group,['value'=>json_encode($clean),'updated_at'=>now()]);audit('update','settings',$group);db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
}
function upload_image(array $file): string {
    require_admin();if($file['error']!==UPLOAD_ERR_OK || $file['size']>4*1024*1024)throw new DomainException('Upload a JPG, PNG, or WebP image smaller than 4 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $size=getimagesize($file['tmp_name']);if(!isset($extensions[$mime]) || !$size || $size[0]>8000 || $size[1]>8000)throw new DomainException('Upload a valid JPG, PNG, or WebP image, up to 8000 pixels per side.');
    $name=uuid().'.'.$extensions[$mime];if(!move_uploaded_file($file['tmp_name'],PUBLIC_ROOT.'/uploads/'.$name))throw new RuntimeException('Image upload failed.');return '/uploads/'.$name;
}
