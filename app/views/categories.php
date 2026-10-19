<?php
$collectionList=categories();
$renderSubcategories=function(string $parent)use(&$renderSubcategories,$collectionList):void{
    $children=array_values(array_filter($collectionList,fn($c)=>($c['parent_id']??null)===$parent));
    if(!$children)return;
    echo '<ul class="subcategory-links">';
    foreach($children as $child){echo '<li><a href="/shop?category='.e($child['slug']).'">'.e($child['name']).'</a>';$renderSubcategories($child['id']);echo '</li>';}
    echo '</ul>';
};
?>
<section class="page-intro"><span class="eyebrow">THE COLLECTIONS</span><h1>Find your favorite</h1><p>A little something for every side of you.</p></section>
<section class="section"><div class="collections-grid">
<?php foreach($collectionList as $c):if(!empty($c['parent_id']))continue; ?>
<article class="collection-card"><a href="/shop?category=<?= e($c['slug']) ?>"><?php if($c['image']): ?><img src="<?= e(image_url($c['image'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy"><?php else: ?><div class="category-placeholder">M’s</div><?php endif ?><h2><?= e($c['name']) ?></h2><p><?= e($c['description']) ?></p><span class="text-link">Explore collection</span></a><?php $renderSubcategories($c['id']); ?></article>
<?php endforeach ?>
</div></section>
