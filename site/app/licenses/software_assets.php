<?php
/** Owner-reviewed versioned ZIPs stored outside the document root. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function software_asset_publish(string $sku,string $version,string $title,string $path,#[\SensitiveParameter]string $password):int{
    if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
    if(support_current())throw new RuntimeException('Leave support mode before software publication.');
    $actor=staff_owner($password);
    $sku=strtoupper(trim($sku));$version=trim($version);$title=trim($title);
    if(!preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]{1,50}$/D',$sku)
        ||!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/D',$version)
        ||$title===''||strlen($title)>120)throw new InvalidArgumentException('Enter approved software SKU, version and title.');
    $product=one('SELECT id FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    if(!$product)throw new InvalidArgumentException('Software SKU is not registered in the catalog.');
    $storage=realpath(config()['storage']??'');$file=realpath($path);
    if(!$storage||!$file||!is_file($file)||!str_starts_with($file,rtrim($storage,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
        ||strtolower(pathinfo($file,PATHINFO_EXTENSION))!=='zip'){
        throw new RuntimeException('Select a ZIP already stored inside the configured private storage root.');
    }
    $bytes=filesize($file);
    if($bytes===false||$bytes<22||$bytes>300*1024*1024)throw new InvalidArgumentException('ZIP must be between 22 bytes and 300 MB.');
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP validation extension unavailable.');
    $zip=new ZipArchive();
    if($zip->open($file,ZipArchive::RDONLY)!==true)throw new InvalidArgumentException('Archive is not a readable ZIP.');
    try{
        if($zip->numFiles<1||$zip->numFiles>12000)throw new InvalidArgumentException('Unexpected software ZIP member count.');
        for($i=0;$i<$zip->numFiles;$i++){
            $entry=$zip->getNameIndex($i);
            if(!is_string($entry)||str_starts_with($entry,'/')||str_contains($entry,'\\')
                ||preg_match('~(^|/)\.\.(/|$)~',$entry)||preg_match('/\.zip$/i',rtrim($entry,'/'))){
                throw new InvalidArgumentException('ZIP contains an unsafe path or nested ZIP.');
            }
            $ops=0;$attr=0;
            if($zip->getExternalAttributesIndex($i,$ops,$attr)
                &&(($attr>>16)&0170000)===0120000)throw new InvalidArgumentException('ZIP contains a symlink.');
        }
    }finally{$zip->close();}
    $sha=hash_file('sha256',$file);
    if(!is_string($sha))throw new RuntimeException('Could not verify ZIP checksum.');
    return staff_locked(fn()=>staff_transaction(function()use($actor,$sku,$version,$title,$file,$sha,$bytes):int{
        if(one('SELECT id FROM '.table('software_assets').' WHERE sku=? AND version=?',[$sku,$version]))
            throw new RuntimeException('This version already has an immutable published asset.');
        query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,created_at) VALUES (?,?,?,?,?,?,1,?)',
            [$sku,$version,$title,$file,$sha,$bytes,gmdate('Y-m-d H:i:s')]);
        $id=(int)db()->lastInsertId();
        license_audit((int)$actor['id'],'software.asset.publish',
            'Published ZIP '.$id.' for '.$sku.' version '.$version);
        return $id;
    }));
}
