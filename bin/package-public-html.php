<?php
// All-in-public_html distribution with Apache access protection.
if(PHP_SAPI!=='cli')exit('CLI only');
$root=dirname(__DIR__);$dest=$argv[1]??dirname($root).'/ms-charm-public-html.zip';
$zip=new ZipArchive();if($zip->open($dest,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)exit("Cannot create archive.\n");
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $file){if(!$file->isFile())continue;$rel=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
    if($rel==='.env' || $rel==='.gitignore' || str_starts_with($rel,'.git/') || str_starts_with($rel,'tests/') || $rel==='public/router.php' || (str_starts_with($rel,'storage/') && $rel!=='storage/.gitkeep') || (str_starts_with($rel,'public/uploads/') && !in_array($rel,['public/uploads/.htaccess','public/uploads/.gitkeep'],true)))continue;
    $target=str_starts_with($rel,'public/')?substr($rel,7):$rel;
    if($rel==='public/index.php'){
        $code=file_get_contents($file->getPathname());$code=str_replace("require dirname(__DIR__).'/app/bootstrap.php';","require __DIR__.'/app/bootstrap.php';",$code,$replacements);
        if($replacements!==1)exit("Unexpected entrypoint; refusing to package.\n");$zip->addFromString($target,$code);
    }elseif($rel==='public/.htaccess'){
        $zip->addFromString($target,"Options -Indexes -MultiViews\nDirectoryIndex index.php\nRewriteEngine On\n# Private code/data are never served over HTTP. Internal PHP includes still work.\nRewriteRule ^(?:app|bin|database|storage|tests)(?:/|$) - [F,L,NC]\nRewriteRule ^(?:README\\.md|ASSET-CREDITS\\.md|DEPLOY-IN-PUBLIC-HTML\\.md)$ - [F,L,NC]\n<FilesMatch \"^\\.\">\n  Require all denied\n</FilesMatch>\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^ index.php [QSA,L]\n");
    }elseif($rel==='.env.example'){
        $zip->addFromString($target,"APP_ENV=production\nAPP_URL=https://mscharms.com\nAPP_TIMEZONE=Asia/Beirut\nDB_DRIVER=mysql\nDB_HOST=localhost\nDB_PORT=3306\nDB_NAME=YOUR_FULL_HOSTINGER_DATABASE_NAME\nDB_USER=YOUR_FULL_HOSTINGER_DATABASE_USERNAME\nDB_PASSWORD=YOUR_DATABASE_PASSWORD\nSESSION_SECURE=true\nPUBLIC_PATH=.\nMAIL_TRANSPORT=smtp\nMAIL_HOST=\nMAIL_PORT=465\nMAIL_ENCRYPTION=ssl\nMAIL_USER=\nMAIL_PASSWORD=\nMAIL_FROM_ADDRESS=\nMAIL_FROM_NAME=Ms Charm\n");
    }elseif($rel==='README.md'){
        $zip->addFromString($target,"# This is the all-in-public_html distribution\n\nFollow DEPLOY-IN-PUBLIC-HTML.md for deployment paths. Its instructions replace the sibling-folder deployment section below. This archive has a different index.php entrypoint and PUBLIC_PATH=. configuration. Keep all supplied .htaccess files in place.\n\n".file_get_contents($file->getPathname()));
    }else{$zip->addFile($file->getPathname(),$target);}
}
foreach(['app','bin','database','storage'] as $directory)$zip->addFromString($directory.'/.htaccess',"Require all denied\n");
$zip->addFile($root.'/DEPLOY-IN-PUBLIC-HTML.md','DEPLOY-IN-PUBLIC-HTML.md');
$zip->close();echo "All-in-public_html archive created. Secrets and runtime data excluded.\n";
