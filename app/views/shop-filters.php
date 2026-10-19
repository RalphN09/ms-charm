<?php
$activeFilters=0;
foreach(['q','category','min','max'] as $filterKey)if(isset($_GET[$filterKey]) && $_GET[$filterKey]!=='')$activeFilters++;
if(!empty($_GET['available']))$activeFilters++;
if(!empty($_GET['sort']) && $_GET['sort']!=='featured')$activeFilters++;
?>
<div class="shop-toolbar">
<p class="shop-result-count"><?= count($list) ?> <?= count($list)===1?'piece':'pieces' ?> to discover</p>
<details class="shop-filter-dropdown" data-shop-filters>
<summary class="filter-trigger"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/></svg><span>Filters</span><?php if($activeFilters): ?><span class="filter-count" aria-label="<?= $activeFilters ?> active filters"><?= $activeFilters ?></span><?php endif ?><svg class="filter-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
<div class="shop-filter-panel"><form class="shop-filter-form" method="get" action="/shop">
<label class="filter-wide">Find a piece<input type="search" name="q" value="<?= e($_GET['q']??'') ?>" placeholder="Search the collection"></label>
<label>Collection<select name="category"><option value="">All collections</option><?php foreach($shopCategories as $c): ?><option value="<?= e($c['slug']) ?>" <?= ($_GET['category']??'')===$c['slug']?'selected':'' ?>><?= e(category_path_name($c,$shopCategories)) ?></option><?php endforeach ?></select></label>
<label>Sort by<select name="sort"><?php foreach(['featured'=>'Featured','newest'=>'Newest','price-low'=>'Price: low to high','price-high'=>'Price: high to low'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($_GET['sort']??'featured')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label>
<label>Min price<input name="min" type="number" min="0" step="0.01" value="<?= e($_GET['min']??'') ?>"></label>
<label>Max price<input name="max" type="number" min="0" step="0.01" value="<?= e($_GET['max']??'') ?>"></label>
<label class="check filter-wide"><input name="available" type="checkbox" value="1" <?= !empty($_GET['available'])?'checked':'' ?>>In stock only</label>
<div class="filter-actions filter-wide"><a href="/shop" class="text-link">Reset filters</a><button class="button" type="submit">Apply filters</button></div>
</form></div>
</details></div>
