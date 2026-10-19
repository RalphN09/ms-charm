<?php if(!empty($discountOrder['welcome_discount'])): ?>
<div class="summary-line welcome-saving"><span>New Customer Welcome Discount — <?= (int)$discountOrder['welcome_percentage'] ?>%</span><strong>−<?= e($discountOrder['currency']) ?> <?= number_format($discountOrder['welcome_discount']/100,2) ?></strong></div>
<?php endif ?>
<?php foreach(json_decode($discountOrder['offer_snapshot']??'[]',true)?:[] as $applied): ?><div class="summary-line welcome-saving"><span><?= e($applied['title']) ?></span><strong>−<?= e($discountOrder['currency']) ?> <?= number_format($applied['amount']/100,2) ?></strong></div><?php endforeach ?>
