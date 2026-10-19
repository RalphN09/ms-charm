<?php
[$label,$type]=$spec;$required=$spec[2]??false;$value=$values[$key]??($type==='number'||$type==='money'?'0':'');
if($type==='images' && $value!=='')$value=implode("\n",json_decode($value,true)?:[]);
if($type==='money' && isset($values[$key]) && !isset(setting_fields()[$section]))$value=number_format((int)$values[$key]/100,2,'.','');
if($key==='sale_price' && !isset($values[$key]))$value='';
$numericBounds=$section==='welcome'?'min="1" max="'.($key==='duration_hours'?168:100).'"':'min="0"';
$selectOptions=match($type){'nav_location'=>['header'=>'Header','footer'=>'Footer'],'review_status'=>['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'],'role'=>['customer'=>'Customer','admin'=>'Admin','owner'=>'Owner','developer'=>'Developer'],default=>[]};
if(in_array($type,['category','parent_category'],true)) {
    $allCategories=categories(true);$excluded=$type==='parent_category' && !empty($values['id'])?category_branch_ids($values['id'],$allCategories):[];
    $selectOptions=[''=>$type==='parent_category'?'None — top-level category':'Uncategorized'];
    foreach($allCategories as $cat)if(!in_array($cat['id'],$excluded,true))$selectOptions[$cat['id']]=category_path_name($cat,$allCategories);
    asort($selectOptions,SORT_NATURAL|SORT_FLAG_CASE);
    $selectOptions=[''=>$type==='parent_category'?'None — top-level category':'Uncategorized',...$selectOptions];
}
?>
<label class="<?= in_array($type,['textarea','images'],true)?'span-2':'' ?> <?= $type==='checkbox'?'check':'' ?>">
<?php if($type==='checkbox'): ?><input name="<?= e($key) ?>" type="checkbox" value="1" <?= $value?'checked':'' ?>><?= e($label) ?>
<?php else: ?><?= e($label) ?>
<?php if(in_array($type,['textarea','images'],true)): ?><textarea name="<?= e($key) ?>" rows="<?= $key==='body'||$key==='description'?7:3 ?>" <?= $required?'required':'' ?>><?= e($value) ?></textarea>
<?php elseif($selectOptions): ?><select name="<?= e($key) ?>" <?= $required?'required':'' ?>><?php foreach($selectOptions as $option=>$optionLabel): ?><option value="<?= e($option) ?>" <?= (string)$value===(string)$option?'selected':'' ?>><?= e($optionLabel) ?></option><?php endforeach ?></select>
<?php else: ?><input name="<?= e($key) ?>" type="<?= match($type){'money','number'=>'number','password'=>'password','email'=>'email','color'=>'color',default=>'text'} ?>" value="<?= $type==='password'?'':e($value) ?>" <?= $required?'required':'' ?> <?= in_array($type,['money','number'],true)?$numericBounds.' step="'.($type==='money'||$key==='tax_rate'?'0.01':'1').'"':'' ?> <?= $type==='password'?'autocomplete="new-password" minlength="12"':'' ?>><?php endif ?>
<?php if($type==='image'): ?><input type="file" name="<?= e($key) ?>_upload" accept="image/jpeg,image/png,image/webp"><small>Upload JPG, PNG, or WebP, up to 4 MB. Or use an HTTPS image URL.</small><?php if($value): ?><img class="admin-image-preview" src="<?= e(image_url((string)$value)) ?>" alt="Current image"><?php endif ?><?php endif ?>
<?php if($type==='images'): ?><input type="file" name="images_upload[]" multiple accept="image/jpeg,image/png,image/webp"><small>Multiple uploads supported. Remove a URL above to remove an image. Up to 12 images, 4 MB each.</small><?php endif ?>
<?php if($type==='parent_category'): ?><small>Leave blank for a normal category, or choose a parent to make this a subcategory.</small><?php endif ?>
<?php endif ?></label>
