<?php
if(PHP_SAPI!=='cli')exit('CLI only');require dirname(__DIR__).'/app/bootstrap.php';require ROOT.'/database/promo-migration.php';
if(($argv[1]??'up')==='down')promo_migrate_down();else promo_migrate_up();echo "Promo migration complete.\n";
