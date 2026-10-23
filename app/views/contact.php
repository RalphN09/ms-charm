<?php $c=settings('contact');$contactSocials=array_filter(settings('social')); ?>
<section class="page-intro"><span class="eyebrow">LET’S CONNECT</span><h1><?= e($c['heading']) ?></h1><p>Have a question about a piece or your order? Contact us directly through our social pages.</p></section>
<section class="section"><div class="panel"><h2>Find us on social media</h2>
<?php if($contactSocials): ?><div class="social-links"><?php foreach($contactSocials as $name=>$url): ?><a class="button outline" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($name==='other'?'Our social page':ucfirst($name)) ?></a><?php endforeach ?></div>
<?php else: ?><p>Our social links will be available here soon.</p><?php endif ?>
</div></section>
