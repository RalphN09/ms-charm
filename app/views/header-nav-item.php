<?php
$previewCollections=parse_url($n['url'],PHP_URL_PATH)==='/categories'
    ?array_values(array_filter(categories(),static fn($category)=>empty($category['parent_id'])))
    :[];
?>
<?php if($previewCollections): ?><div class="nav-collections-preview"><?php endif ?>
<a href="<?= e($n['url']) ?>" <?= ($path??'/')===$n['url']?'aria-current="page"':'' ?>><?= e($n['label']) ?><?php if($previewCollections): ?><span class="nav-preview-chevron" aria-hidden="true">⌄</span><?php endif ?></a>
<?php if($previewCollections): ?>
<ul class="nav-collections-list" aria-label="Main collections">
<?php foreach($previewCollections as $previewCollection): ?><li><span><?= e($previewCollection['name']) ?></span></li><?php endforeach ?>
</ul></div>
<?php endif ?>
