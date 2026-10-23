<?php
require dirname(__DIR__).'/app/bootstrap.php';require ROOT.'/database/email-verification-migration.php';
if(PHP_SAPI!=='cli')exit('CLI only');
if(($argv[1]??'up')==='down')email_verification_migrate_down();else email_verification_migrate_up();
echo "Welcome email verification migration complete.\n";
