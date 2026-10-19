<?php
// Build an archive containing source only, excluding secrets and all runtime data.
if(PHP_SAPI!=='cli')exit('CLI only');
$root=dirname(__DIR__);$dest=$argv[1]??dirname($root).'/ms-charm-hostinger.zip';
$zip=new ZipArchive();if($zip->open($dest,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)exit("Cannot create archive.\n");
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
$count=0;
foreach($it as $file){if(!$file->isFile())continue;$rel=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
    if($rel==='.env' || str_starts_with($rel,'.git/') || (str_starts_with($rel,'storage/') && $rel!=='storage/.gitkeep') || (str_starts_with($rel,'public/uploads/') && !in_array($rel,['public/uploads/.htaccess','public/uploads/.gitkeep'],true)))continue;
    $zip->addFile($file->getPathname(),'ms-charm/'.$rel);$count++;
}
$zip->close();echo "Packaged $count source files. Runtime data, uploads, sessions, and .env excluded.\n";
