<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';require ROOT.'/database/delivery-migration.php';delivery_migrate_up();
echo "Delivery upgrade complete. Existing delivery settings remain in use until location-based delivery is enabled in Admin.\n";
