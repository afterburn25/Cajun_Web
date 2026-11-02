<?php
/** Owner-reviewed versioned ZIPs stored outside the document root. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function software_asset_private_root():string{
    $root=realpath(config()['storage']??'');
    if(!$root||!is_dir($root))throw new RuntimeException('Private software storage root is unavailable.');
    return $root;
}
function software_asset_contains(string $root,string $path):bool{
    $rootNorm=rtrim(str_replace('\\','/',$root),'/').'/';
    $pathNorm=str_replace('\\','/',$path);
    if(PHP_OS_FAMILY==='Windows'){$rootNorm=strtolower($rootNorm);$pathNorm=strtolower($pathNorm);}
    return str_starts_with($pathNorm,$rootNorm);
}
/** New rows store a path relative to private storage; legacy absolute rows remain readable. */
function software_asset_reference(string $root,string $file):string{
    if(!software_asset_contains($root,$file))throw new RuntimeException('Software ZIP is outside private storage.');
    $rootNorm=rtrim(str_replace('\\','/',$root),'/').'/';
    $fileNorm=str_replace('\\','/',$file);
    $relative=substr($fileNorm,strlen($rootNorm));
    if(!is_string($relative)||$relative===''||str_contains($relative,'\\')||str_contains('/'.$relative.'/','/../')){
        throw new RuntimeException('Software ZIP storage reference is invalid.');
    }
    return $relative;
}
function software_asset_resolve_path(string $stored):?string{
    try{$root=software_asset_private_root();}catch(Throwable){return null;}
    $absolute=preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~',$stored)===1;
    if($absolute){
        $candidate=$stored; // compatibility with earlier branch rows
    }else{
        if($stored===''||str_contains($stored,'\\')||str_contains('/'.$stored.'/','/../')||str_starts_with($stored,'/'))return null;
        $candidate=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$stored);
    }
    $file=realpath($candidate);
    return $file&&is_file($file)&&software_asset_contains($root,$file)
        &&strtolower(pathinfo($file,PATHINFO_EXTENSION))==='zip'?$file:null;
}

/** Return a canonical, portable ZIP member name or null for extraction-unsafe input. */
function software_asset_member_name(string $entry):?string{
    if($entry===''||strlen($entry)>4096||str_starts_with($entry,'/')||str_contains($entry,'\\')
        ||str_contains($entry,':')||preg_match('/[\x00-\x1f\x7f]/D',$entry)===1
        ||preg_match('//u',$entry)!==1)return null;
    $directory=str_ends_with($entry,'/');
    $name=$directory?substr($entry,0,-1):$entry;
    if($name==='')return null;
    foreach(explode('/',$name) as $part){
        if($part===''||$part==='.'||$part==='..'||preg_match('/[. ]$/uD',$part)===1
            ||preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/iuD',$part)===1)return null;
    }
    return $name.($directory?'/':'');
}

function software_asset_publish(string $sku,string $version,string $title,string $path,#[\SensitiveParameter]string $password,bool $customerVisible=true):int{
    if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
    if(support_current())throw new RuntimeException('Leave support mode before software publication.');
    staff_require('staff.manage');
    $sku=strtoupper(trim($sku));$version=trim($version);$title=trim($title);
    if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku)
        ||!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/D',$version)
        ||$title===''||strlen($title)>120)throw new InvalidArgumentException('Enter approved software SKU, version and title.');
    $product=one('SELECT id,family FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    $legacy=preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]+$/D',$sku)===1;
    if(!$product||(($product['family']??'')!=='software'&&!$legacy))
        throw new InvalidArgumentException('Choose a registered software catalog product.');
    $storage=software_asset_private_root();$file=realpath($path);
    if(!$storage||!$file||!is_file($file)||!software_asset_contains($storage,$file)
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
        $expanded=0;$members=[];
        for($i=0;$i<$zip->numFiles;$i++){
            $entry=$zip->getNameIndex($i);
            $stat=$zip->statIndex($i);
            if(!is_array($stat)||!isset($stat['size'])||!is_int($stat['size'])||$stat['size']<0)
                throw new InvalidArgumentException('ZIP member metadata is invalid.');
            $expanded+=$stat['size'];
            if($stat['size']>1024*1024*1024||$expanded>2*1024*1024*1024)
                throw new InvalidArgumentException('ZIP expands beyond the supported software package limit.');
            $canonical=is_string($entry)?software_asset_member_name($entry):null;
            if($canonical===null||preg_match('/\.zip$/i',rtrim($canonical,'/'))){
                throw new InvalidArgumentException('ZIP contains an unsafe path or nested ZIP.');
            }
            $member=rtrim($canonical,'/');
            $collision=function_exists('mb_strtolower')?mb_strtolower($member,'UTF-8'):strtolower($member);
            if(isset($members[$collision]))throw new InvalidArgumentException('ZIP contains duplicate or case-colliding paths.');
            $members[$collision]=true;
            $ops=0;$attr=0;
            if($zip->getExternalAttributesIndex($i,$ops,$attr)){
                $type=(($attr>>16)&0170000);
                if($type!==0&&$type!==0100000&&$type!==0040000)
                    throw new InvalidArgumentException('ZIP contains a symlink or unsupported special file.');
            }
        }
    }finally{$zip->close();}
    $sha=hash_file('sha256',$file);
    if(!is_string($sha))throw new RuntimeException('Could not verify ZIP checksum.');
    $reference=software_asset_reference($storage,$file);
    return staff_locked(fn()=>staff_transaction(function()use($password,$sku,$version,$title,$reference,$sha,$bytes,$customerVisible):int{
        $actor=staff_owner($password);
        if(one('SELECT id FROM '.table('software_assets').' WHERE sku=? AND version=?',[$sku,$version]))
            throw new RuntimeException('This version already has an immutable software asset.');
        $now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('software_assets').' (sku,version,title,file_path,sha256,byte_size,is_published,released_at,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            [$sku,$version,$title,$reference,$sha,$bytes,$customerVisible?1:0,$customerVisible?$now:null,$now]);
        $id=(int)db()->lastInsertId();
        license_audit((int)$actor['id'],'software.asset.publish',
            'Published ZIP '.$id.' for '.$sku.' version '.$version.' as '.($customerVisible?'customer-visible':'owner-private'));
        return $id;
    }));
}

function software_asset_visibility(int $assetId,bool $customerVisible,#[\SensitiveParameter]string $password):bool{
    if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
    if(support_current())throw new RuntimeException('Leave support mode before changing software publication.');
    if($assetId<1)throw new InvalidArgumentException('Software asset is required.');
    staff_require('staff.manage');
    return staff_locked(fn()=>staff_transaction(function()use($assetId,$customerVisible,$password):bool{
        $actor=staff_owner($password);
        $asset=one('SELECT id,sku,version,is_published,released_at FROM '.table('software_assets').' WHERE id=? FOR UPDATE',[$assetId]);
        if(!$asset)throw new InvalidArgumentException('Software asset not found.');
        $current=(int)$asset['is_published']===1;
        if($current===$customerVisible)return false;
        if($customerVisible){
            query('UPDATE '.table('software_assets').' SET is_published=1,released_at=COALESCE(released_at,?) WHERE id=?',
                [gmdate('Y-m-d H:i:s'),$assetId]);
        }else{
            query('UPDATE '.table('software_assets').' SET is_published=0 WHERE id=?',[$assetId]);
        }
        license_audit((int)$actor['id'],'software.asset.visibility',
            'Asset '.$assetId.' '.$asset['sku'].' '.$asset['version'].' changed to '.($customerVisible?'customer-visible':'owner-private'));
        return true;
    }));
}
