<?php
$shopCategories=categories();$shopSlug=text_input($_GET,'category',160);$shopCurrent=null;
foreach($shopCategories as $category)if($category['slug']===$shopSlug){$shopCurrent=$category;break;}
?>
<section class="page-intro"><span class="eyebrow">ACCESSORIES & MORE</span><h1><?= e($shopCurrent['name']??'The collection') ?></h1><p><?= e($shopCurrent['description']??'Little pieces. Endless possibilities.') ?></p></section><section class="section shop-section"><?php require ROOT.'/app/views/shop-collections.php'; ?><?php require ROOT.'/app/views/shop-filters.php'; ?><div class="product-grid"><?php foreach($list as $p)require ROOT.'/app/views/product-card.php'; ?></div><?php if(!$list): ?><div class="empty-state"><h2>No pieces found</h2><p>Try another search or explore all collections.</p><a class="button" href="/shop">See all pieces</a></div><?php endif ?></section>
