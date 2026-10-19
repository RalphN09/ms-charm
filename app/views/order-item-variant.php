<?php $itemVariant=json_decode($item['variant_snapshot']??'{}',true)?:[];$itemOptions=implode(' · ',array_filter([!empty($itemVariant['color'])?'Color: '.$itemVariant['color']:null,!empty($itemVariant['size'])?'Size: '.$itemVariant['size']:null,!empty($itemVariant['sku'])?'SKU: '.$itemVariant['sku']:null])); ?>
<?php if($itemOptions): ?><small><?= e($itemOptions) ?></small><?php endif ?>
<?php if(!empty($item['line_discount'])): ?><small>Line discount: <?= e(($order['currency']??$o['currency'])) ?> <?= number_format($item['line_discount']/100,2) ?></small><?php endif ?>
