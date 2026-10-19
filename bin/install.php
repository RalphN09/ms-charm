<?php
require dirname(__DIR__).'/app/bootstrap.php';
require ROOT.'/database/seed.php';
require ROOT.'/database/welcome-migration.php';
if (PHP_SAPI !== 'cli') exit('CLI only');
foreach(require ROOT.'/database/schema.php' as $sql) db()->exec($sql.(env('DB_DRIVER')==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':''));
ensure_category_hierarchy();
if (!(int)query('SELECT COUNT(*) FROM settings')->fetchColumn()) { db()->beginTransaction(); seed_store(); db()->commit(); echo "Schema installed; editable sample catalog seeded.\n"; }
else echo "Schema already installed. Existing data preserved.\n";
welcome_migrate_up();
require_once ROOT.'/database/offers-migration.php';offers_migrate_up();
require_once ROOT.'/database/delivery-migration.php';delivery_migrate_up();
