<?php
// Only included inside the authenticated admin Products page.
$filterValue=static fn(string $key): string=>isset($_GET[$key])&&is_string($_GET[$key])?trim($_GET[$key]):'';
$productFilters=['q'=>mb_substr($filterValue('q'),0,150),'category'=>$filterValue('category'),'visibility'=>$filterValue('visibility'),'stock'=>$filterValue('stock'),'sort'=>$filterValue('sort')];
$productCategories=categories(true);
$sortOptions=['default'=>'Display order','newest'=>'Newest first','name'=>'Name: A–Z','name-desc'=>'Name: Z–A','stock-low'=>'Stock: low to high','stock-high'=>'Stock: high to low'];
$sortSql=['default'=>'p.sort_order,p.name,p.id','newest'=>'p.created_at DESC,p.id','name'=>'p.name,p.id','name-desc'=>'p.name DESC,p.id','stock-low'=>'p.stock,p.name,p.id','stock-high'=>'p.stock DESC,p.name,p.id'];
if(!isset($sortOptions[$productFilters['sort']]))$productFilters['sort']='default';
if(!in_array($productFilters['visibility'],['enabled','hidden'],true))$productFilters['visibility']='';
if(!in_array($productFilters['stock'],['in','out','low'],true))$productFilters['stock']='';
if(!in_array($productFilters['category'],array_column($productCategories,'id'),true)&&$productFilters['category']!=='uncategorized')$productFilters['category']='';
$conditions=['1=1'];$parameters=[];
if($productFilters['q']!==''){
    // Escape LIKE wildcards so a typed % or _ is searched literally.
    $conditions[]="p.name LIKE ? ESCAPE '!'";
    $parameters[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$productFilters['q']).'%';
}
if($productFilters['category']==='uncategorized')$conditions[]='p.category_id IS NULL';
elseif($productFilters['category']!==''){
    $ids=category_branch_ids($productFilters['category'],$productCategories);
    $conditions[]='p.category_id IN ('.implode(',',array_fill(0,count($ids),'?')).')';
    $parameters=[...$parameters,...$ids];
}
if($productFilters['visibility']!==''){$conditions[]='p.active=?';$parameters[]=$productFilters['visibility']==='enabled'?1:0;}
if($productFilters['stock']!=='')$conditions[]=match($productFilters['stock']){'in'=>'p.stock>0','out'=>'p.stock<=0','low'=>'p.stock BETWEEN 1 AND 5'};
$records=rows('SELECT p.* FROM products p WHERE '.implode(' AND ',$conditions).' ORDER BY '.$sortSql[$productFilters['sort']],$parameters);
$productTotal=(int)query('SELECT COUNT(*) FROM products')->fetchColumn();
$filterCount=count(array_filter($productFilters,static fn($value)=>$value!==''&&$value!=='default'));
?>
<div class="shop-toolbar">
<p class="shop-result-count"><?= count($records) ?> of <?= $productTotal ?> products<?php if($filterCount): ?> · <a class="text-link" href="/admin/products">Clear filters</a><?php endif ?></p>
<details class="shop-filter-dropdown">
<summary class="filter-trigger"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4"/></svg><span>Filters</span><?php if($filterCount): ?><span class="filter-count"><?= $filterCount ?></span><?php endif ?><svg class="filter-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
<div class="shop-filter-panel"><form class="shop-filter-form" method="get" action="/admin/products">
<label class="filter-wide">Product name<input type="search" name="q" maxlength="150" value="<?= e($productFilters['q']) ?>" placeholder="Search products"></label>
<label class="filter-wide">Collection / subcategory<select name="category"><option value="">All collections</option><option value="uncategorized" <?= $productFilters['category']==='uncategorized'?'selected':'' ?>>No collection</option><?php foreach($productCategories as $category): ?><option value="<?= e($category['id']) ?>" <?= $productFilters['category']===$category['id']?'selected':'' ?>><?= e(category_path_name($category,$productCategories)) ?></option><?php endforeach ?></select><small>Parent collections include their subcategories.</small></label>
<label>Visibility<select name="visibility"><?php foreach([''=>'All products','enabled'=>'Enabled','hidden'=>'Hidden'] as $value=>$label): ?><option value="<?= $value ?>" <?= $productFilters['visibility']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
<label>Stock<select name="stock"><?php foreach([''=>'Any stock','in'=>'In stock','out'=>'Out of stock','low'=>'Low stock (1–5)'] as $value=>$label): ?><option value="<?= $value ?>" <?= $productFilters['stock']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
<label class="filter-wide">Sort by<select name="sort"><?php foreach($sortOptions as $value=>$label): ?><option value="<?= $value ?>" <?= $productFilters['sort']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
<div class="filter-actions filter-wide"><a class="text-link" href="/admin/products">Reset filters</a><button class="button" type="submit">Apply filters</button></div>
</form></div></details></div>
<?php if(!$records&&$filterCount): ?><p role="status">No products match these filters. Try another search or clear the filters.</p><?php endif ?>
