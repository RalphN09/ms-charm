<?php if(!empty($discountOrder['welcome_discount'])): ?>
<div class="summary-line welcome-saving"><span>New Customer Welcome Discount — <?= (int)$discountOrder['welcome_percentage'] ?>%</span><strong>−<?= e($discountOrder['currency']) ?> <?= number_format($discountOrder['welcome_discount']/100,2) ?></strong></div>
<?php endif ?>
<?php foreach(json_decode($discountOrder['offer_snapshot']??'[]',true)?:[] as $applied): ?><div class="summary-line welcome-saving"><span><?= e($applied['title']) ?></span><strong>−<?= e($discountOrder['currency']) ?> <?= number_format($applied['amount']/100,2) ?></strong></div><?php endforeach ?>

<?php $savedCode=json_decode($discountOrder['promo_snapshot']??'{}',true)?:[];if($savedCode): ?><div class="summary-line welcome-saving"><span>Code <?= e($savedCode['code']) ?></span><strong><?= !empty($discountOrder['promo_discount'])?'−'.e($discountOrder['currency']).' '.number_format($discountOrder['promo_discount']/100,2):'Free delivery' ?></strong></div><?php endif ?>
