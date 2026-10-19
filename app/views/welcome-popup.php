<?php
$welcomeSettings=welcome_config();
if(promo_ready() && $welcomeSettings['enabled'] && !in_array($path??'',['/login','/register','/checkout','/welcome'],true) && http_response_code()<400):
?>
<div class="welcome-discover"><a class="text-link" href="/welcome" data-open-welcome>New here? Get <?= (int)$welcomeSettings['percentage'] ?>% off with just your email</a></div>
<dialog class="welcome-dialog" id="welcome-dialog" aria-labelledby="welcome-title" aria-describedby="welcome-description" data-welcome-popup>
<button class="welcome-close" type="button" data-close-welcome aria-label="Close welcome offer">×</button>
<div class="welcome-dialog-inner"><span class="eyebrow">A LITTLE WELCOME FROM <?= e($brand['name']) ?></span><h2 id="welcome-title">Welcome! Get <?= (int)$welcomeSettings['percentage'] ?>% off your first order.</h2><p id="welcome-description">Just your email. No account or password needed. Claim your private code, valid for <?= (int)$welcomeSettings['duration_hours'] ?> hours.</p><?php require ROOT.'/app/views/welcome-claim-form.php'; ?><button class="text-button" type="button" data-close-welcome>Maybe later</button><small>One successful use per email. Full-priced pieces only. No stacking. Save your code; it is displayed here, not emailed.</small></div></dialog>
<?php endif ?>
