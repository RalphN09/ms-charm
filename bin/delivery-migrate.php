<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';require_once ROOT.'/database/governorate-migration.php';governorate_migrate_up();
echo "Governorate delivery upgrade complete. Review all eight rates and migration notices in Admin. Historical orders are preserved.\n";
