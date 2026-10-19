<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/database/offers-migration.php';
offers_migrate_up();echo "Offers migration complete. Existing orders and prices preserved.\n";
