<?php
// Existing stores upgrade when an authenticated administrator opens Collections/Products.
// Public requests never run schema changes.
function ensure_category_hierarchy(): void {
    static $ready=false;if($ready)return;
    try{query('SELECT parent_id FROM categories WHERE 1=0');$ready=true;return;}
    catch(PDOException $ex){
        if((int)($ex->errorInfo[1]??0)!==1054 && !str_contains($ex->getMessage(),'no such column: parent_id'))throw $ex;
    }
    try{
        if(env('DB_DRIVER')==='mysql')query('ALTER TABLE categories ADD COLUMN parent_id VARCHAR(32) NULL, ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL');
        else query('ALTER TABLE categories ADD COLUMN parent_id VARCHAR(32) REFERENCES categories(id) ON DELETE SET NULL');
    }catch(PDOException $ex){
        // A second admin request may have completed the same upgrade while we waited.
        if((int)($ex->errorInfo[1]??0)!==1060 && !str_contains($ex->getMessage(),'duplicate column name'))throw $ex;
    }
    query('SELECT parent_id FROM categories WHERE 1=0');$ready=true;
}
function category_branch_ids(string $id,array $all): array {
    $ids=[$id=>true];do{$changed=false;foreach($all as $cat)if(isset($ids[$cat['parent_id']??'']) && !isset($ids[$cat['id']])){$ids[$cat['id']]=true;$changed=true;}}while($changed);
    return array_keys($ids);
}
function category_path_name(array $cat,array $all): string {
    $byId=array_column($all,null,'id');$parts=[$cat['name']];$seen=[$cat['id']=>true];$parent=$cat['parent_id']??null;
    while($parent && isset($byId[$parent]) && !isset($seen[$parent])){$seen[$parent]=true;array_unshift($parts,$byId[$parent]['name']);$parent=$byId[$parent]['parent_id']??null;}
    return implode(' / ',$parts);
}
function category_visible(array $cat,array $byId): bool {
    $seen=[];while(true){if(!$cat['active'] || isset($seen[$cat['id']]))return false;$seen[$cat['id']]=true;$parent=$cat['parent_id']??null;if(!$parent)return true;if(!isset($byId[$parent]))return false;$cat=$byId[$parent];}
}
