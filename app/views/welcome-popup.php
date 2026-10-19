<?php
$welcomeSettings=welcome_config();
if(!$u && $welcomeSettings['enabled'] && !in_array($path??'',['/login','/register'],true) && http_response_code()<400):
?>
<div class="welcome-discover"><a class="text-link" href="/register?welcome=1" data-open-welcome>New here? Enjoy <?= (int)$welcomeSettings['percentage'] ?>% off your first order</a></div>
<dialog class="welcome-dialog" id="welcome-dialog" aria-labelledby="welcome-title" aria-describedby="welcome-description" data-welcome-popup>
<button class="welcome-close" type="button" data-close-welcome aria-label="Close welcome offer">×</button>
<div class="welcome-dialog-inner"><span class="eyebrow">A LITTLE WELCOME FROM <?= e($brand['name']) ?></span><h2 id="welcome-title">Welcome! Get <?= (int)$welcomeSettings['percentage'] ?>% off your first order.</h2><p id="welcome-description">Create your account and enjoy <?= (int)$welcomeSettings['percentage'] ?>% off full-priced pieces. Your welcome offer is valid for <?= (int)$welcomeSettings['duration_hours'] ?> hours after you register.</p><a class="button full" href="/register?welcome=1" data-claim-welcome>Create my account</a><button class="text-button" type="button" data-close-welcome>Maybe later</button><p class="welcome-signin">Already have an account? <a class="text-link" href="/login">Sign in</a></p><small>One offer per new customer. Excludes sale items, delivery and tax. Cannot combine with other offers; the best bag saving applies.</small></div></dialog>
<?php endif ?>
