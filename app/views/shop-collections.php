<?php if($shopCurrent):
    $byId=array_column($shopCategories,null,'id');$ancestors=[];$cursor=$shopCurrent;
    while(!empty($cursor['parent_id']) && isset($byId[$cursor['parent_id']])){$cursor=$byId[$cursor['parent_id']];array_unshift($ancestors,$cursor);}
    $children=array_values(array_filter($shopCategories,fn($cat)=>($cat['parent_id']??null)===$shopCurrent['id']));
?>
<nav class="breadcrumbs collection-breadcrumbs" aria-label="Collection breadcrumb"><a href="/categories">Collections</a><?php foreach($ancestors as $ancestor): ?><span aria-hidden="true">/</span><a href="/shop?category=<?= e($ancestor['slug']) ?>"><?= e($ancestor['name']) ?></a><?php endforeach ?><span aria-hidden="true">/</span><span aria-current="page"><?= e($shopCurrent['name']) ?></span></nav>
<?php if($children): ?>
<section class="shop-subcategories" aria-labelledby="shop-subcategories-title"><h2 id="shop-subcategories-title">Shop by subcategory</h2><nav class="shop-subcategory-grid" aria-label="Subcategories of <?= e($shopCurrent['name']) ?>"><?php foreach($children as $subcategory): ?><a class="shop-subcategory-link" href="/shop?category=<?= e($subcategory['slug']) ?>"><?php if($subcategory['image']): ?><img src="<?= e(image_url($subcategory['image'])) ?>" alt="" width="64" height="64" loading="lazy"><?php endif ?><span><?= e($subcategory['name']) ?></span></a><?php endforeach ?></nav></section>
<?php endif;endif ?>

