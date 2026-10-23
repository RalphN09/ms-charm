<?php if(!in_array($path??'',['/checkout','/login','/register','/welcome','/welcome/verify'],true) && http_response_code()<400): ?>
<div class="welcome-discover"><a class="text-link" href="/offers">Explore current offers</a></div>
<?php require ROOT.'/app/views/offer-dialog.php'; ?>
<?php endif ?>
