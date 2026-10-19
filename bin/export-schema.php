<?php
// SQL export for Hostinger phpMyAdmin when CLI migrations are inconvenient.
if(PHP_SAPI!=='cli')exit('CLI only');
foreach(require dirname(__DIR__).'/database/schema.php' as $sql)echo $sql." ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
