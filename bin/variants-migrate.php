<?php
if(PHP_SAPI!=='cli')exit('CLI only');require dirname(__DIR__).'/app/bootstrap.php';require_once ROOT.'/database/variants-migration.php';
if(($argv[1]??'up')==='down')variants_migrate_down();else variants_migrate_up();echo "Product variants migration complete.\n";
