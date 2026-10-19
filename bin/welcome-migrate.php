<?php
if(PHP_SAPI!=='cli')exit('CLI only');
require dirname(__DIR__).'/app/bootstrap.php';require ROOT.'/database/welcome-migration.php';
$direction=$argv[1]??'up';
try{if($direction==='up')welcome_migrate_up();elseif($direction==='down')welcome_migrate_down();else throw new DomainException('Use up or down.');echo "Welcome migration $direction complete.\n";}
catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
