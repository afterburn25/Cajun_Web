<?php
/** Guided, owner-private software package onboarding. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/migrations/software_onboarding_migrate.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_assets.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_products.php';

function software_onboarding_require():array{
    if(!software_onboarding_ready())throw new RuntimeException('Prepare guided software uploads first.');
    if(support_current())throw new RuntimeException('Leave support mode before managing software packages.');
    staff_require('catalog.manage');staff_require('staff.manage');
    return staff_owner('');
}
function software_onboarding_request_key(string $value):string{
    $value=strtolower(trim($value));
    if(!preg_match('/^[a-f0-9]{64}$/D',$value))throw new InvalidArgumentException('Reload the software upload request.');
    return $value;
}
function software_onboarding_metadata_value(mixed $value,int $limit):string{
    if(!is_string($value))return '';
    $value=trim(preg_replace('/\s+/u',' ',$value)??'');
    return strlen($value)<=$limit?$value:'';
}
/** Read a small allowlisted JSON manifest from the validated ZIP; never include or execute package code. */
function software_onboarding_metadata(string $path,array $inspection):array{
    $result=['title'=>'','version'=>'','embedded_sku'=>'','module_key'=>'','compatibility'=>'Not detected in supported package metadata.','source'=>'No supported metadata detected.'];
    $candidates=[];
    foreach($inspection['members'] as $member){
        $clean=rtrim((string)$member,'/');
        if(substr_count($clean,'/')>1)continue;
        $base=strtolower(basename($clean));
        if(in_array($base,['cajunwebpro.json','package.json','composer.json'],true))$candidates[$base][]=$clean;
    }
    $zip=new ZipArchive();if($zip->open($path,ZipArchive::RDONLY)!==true)return $result;
    try{
        foreach(['cajunwebpro.json','package.json','composer.json'] as $base){
            if(count($candidates[$base]??[])!==1)continue;
            $raw=$zip->getFromName($candidates[$base][0],262145,ZipArchive::FL_UNCHANGED);
            if(!is_string($raw)||strlen($raw)>262144)continue;
            try{$doc=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}
            if(!is_array($doc))continue;
            $extra=is_array($doc['extra']['cajunwebpro']??null)?$doc['extra']['cajunwebpro']:[];
            $cwp=is_array($doc['cajunwebpro']??null)?$doc['cajunwebpro']:[];
            $title=software_onboarding_metadata_value($doc['title']??$doc['displayName']??$doc['name']??'',120);
            if(str_contains($title,'/'))$title=substr($title,strrpos($title,'/')+1);
            $version=software_onboarding_metadata_value($doc['version']??'',40);
            $sku=strtoupper(software_onboarding_metadata_value($doc['sku']??$doc['product_id']??$cwp['sku']??$extra['sku']??'',60));
            $module=software_onboarding_metadata_value($doc['module_key']??$cwp['module_key']??$extra['module_key']??'',80);
            if($version!==''&&!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/D',$version))$version='';
            if($sku!==''&&!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku))$sku='';
            if($module!==''&&!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/D',$module))$module='';
            $compat=[];
            foreach(['engines','require'] as $key)if(is_array($doc[$key]??null))foreach($doc[$key] as $name=>$constraint){
                if(is_string($name)&&is_string($constraint)&&strlen($name)<=40&&strlen($constraint)<=80)$compat[]=$name.' '.$constraint;
            }
            $result=['title'=>$title,'version'=>$version,'embedded_sku'=>$sku,'module_key'=>$module,
                'compatibility'=>$compat?implode('; ',array_slice($compat,0,12)):'Not detected in supported package metadata.',
                'source'=>'Read from '.$base.' without executing package code.'];
            return $result;
        }
    }finally{$zip->close();}
    return $result;
}
function software_onboarding_copy_upload(string $source,string $target):void{
    $in=@fopen($source,'rb');$out=@fopen($target,'x+b');
    if(!$in||!$out){if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);throw new RuntimeException('The private package staging file could not be created.');}
    try{
        $limit=300*1024*1024;$copied=stream_copy_to_stream($in,$out,$limit+1);
        if(!is_int($copied)||$copied<22||$copied>$limit||!feof($in))throw new InvalidArgumentException('ZIP must be between 22 bytes and 300 MB.');
        fflush($out);if(function_exists('fsync'))fsync($out);
    }catch(Throwable $e){fclose($in);fclose($out);@unlink($target);throw $e;}
    fclose($in);fclose($out);
}
/** Core staging entry used by the HTTP action and native tests. */
function software_onboarding_stage(string $source,string $originalName,bool $requireHttpUpload=true,int $targetProductId=0):array{
    $actor=software_onboarding_require();
    software_onboarding_cleanup_expired();
    if($targetProductId<0||$targetProductId>9999999)throw new InvalidArgumentException('Choose an existing software product.');
    if($requireHttpUpload&&!is_uploaded_file($source))throw new InvalidArgumentException('Choose a ZIP from this device and upload it again.');
    $originalName=basename(str_replace('\\','/',$originalName));
    if($originalName===''||strlen($originalName)>255||strtolower(pathinfo($originalName,PATHINFO_EXTENSION))!=='zip')
        throw new InvalidArgumentException('Choose one ZIP package.');
    $root=software_asset_private_root();$directory=$root.DIRECTORY_SEPARATOR.'software-onboarding'.DIRECTORY_SEPARATOR.'staging';
    if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Private software staging is unavailable.');
    $key=bin2hex(random_bytes(32));$target=$directory.DIRECTORY_SEPARATOR.$key.'.zip';
    software_onboarding_copy_upload($source,$target);
    try{
        $inspection=software_asset_inspect($target);$metadata=software_onboarding_metadata($target,$inspection);$metadata['target_product_id']=$targetProductId;
        $now=gmdate('Y-m-d H:i:s');
        staff_locked(fn()=>staff_transaction(function()use($key,$actor,$originalName,$inspection,$metadata,$now,$targetProductId):void{
            software_onboarding_require();
            if($targetProductId>0){
                $target=one('SELECT id FROM '.table('catalog_products')." WHERE id=? AND family='software' FOR UPDATE",[$targetProductId]);
                if(!$target)throw new InvalidArgumentException('Choose an existing software product.');
            }
            query('INSERT INTO '.table('software_upload_requests').' (request_key,actor_id,state,original_name,stored_path,sha256,byte_size,metadata,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$key,(int)$actor['id'],'review',$originalName,$inspection['reference'],$inspection['sha256'],$inspection['byte_size'],json_encode($metadata,JSON_THROW_ON_ERROR),$now,$now]);
            license_audit((int)$actor['id'],'software.onboarding.upload','Validated private ZIP request '.$key.' ('.$inspection['byte_size'].' bytes)');
        }));
    }catch(Throwable $e){@unlink($target);throw $e;}
    return software_onboarding_request($key);
}
function software_onboarding_upload_file(string $field='package',int $targetProductId=0):array{
    $file=$_FILES[$field]??null;
    if(!is_array($file)||is_array($file['error']??null)||!is_int($file['error']??null))throw new InvalidArgumentException('Choose one ZIP package.');
    $error=$file['error'];
    if($error!==UPLOAD_ERR_OK)throw new InvalidArgumentException(match($error){
        UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'The ZIP exceeds this server’s upload limit.',
        UPLOAD_ERR_PARTIAL=>'The ZIP upload was interrupted. Choose it again to retry.',
        UPLOAD_ERR_NO_FILE=>'Choose a ZIP package.',
        UPLOAD_ERR_NO_TMP_DIR,UPLOAD_ERR_CANT_WRITE=>'The server cannot stage uploads right now.',
        UPLOAD_ERR_EXTENSION=>'The server refused this upload.',default=>'The ZIP upload did not complete.'});
    if(!is_string($file['tmp_name']??null)||!is_string($file['name']??null))throw new InvalidArgumentException('Choose one ZIP package.');
    return software_onboarding_stage($file['tmp_name'],$file['name'],true,$targetProductId);
}
function software_onboarding_request(string $key,bool $lock=false):array{
    $actor=software_onboarding_require();$key=software_onboarding_request_key($key);
    $row=one('SELECT * FROM '.table('software_upload_requests').' WHERE request_key=? AND actor_id=?'.($lock?' FOR UPDATE':''),[$key,(int)$actor['id']]);
    if(!$row)throw new InvalidArgumentException('Software upload request not found.');
    try{$row['metadata']=json_decode((string)$row['metadata'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable){throw new RuntimeException('Software upload metadata needs recovery.');}
    if(!is_array($row['metadata']))throw new RuntimeException('Software upload metadata needs recovery.');
    return $row;
}
function software_onboarding_generated_sku(string $title):string{
    $ascii=strtoupper(preg_replace('/[^A-Za-z0-9]+/','-',trim($title))??'');$ascii=trim($ascii,'-');
    if($ascii==='')$ascii='PACKAGE';
    $identity=hash('sha256',function_exists('mb_strtolower')?mb_strtolower(trim($title),'UTF-8'):strtolower(trim($title)));
    return substr('SOFTWARE-'.$ascii,0,50).'-'.strtoupper(substr($identity,0,8));
}
function software_onboarding_record_failure(string $requestKey,Throwable $error):void{
    try{
        if(!software_onboarding_ready())return;$actor=software_onboarding_require();$key=software_onboarding_request_key($requestKey);
        $message=trim(preg_replace('/\s+/u',' ',$error->getMessage())??'Software onboarding failed.');
        if($message==='')$message='Software onboarding failed.';$message=substr($message,0,500);
        staff_locked(fn()=>staff_transaction(fn()=>query("UPDATE ".table('software_upload_requests')." SET last_error=?,updated_at=? WHERE request_key=? AND actor_id=? AND state='review'",
            [$message,gmdate('Y-m-d H:i:s'),$key,(int)$actor['id']])));
    }catch(Throwable){/* Preserve the original failure; history recording is best-effort only. */}
}
function software_onboarding_finish(string $requestKey,array $input):array{
    $title=gtext($input,'title',120);$version=gtext($input,'version',40);$productId=gint($input,'product_id',0,2147483647);
    if($title===''||!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/D',$version))throw new InvalidArgumentException('Review the software name and enter a supported version.');
    try{return staff_locked(fn()=>staff_transaction(function()use($requestKey,$title,$version,$productId):array{
        $actor=software_onboarding_require();$request=software_onboarding_request($requestKey,true);
        if($request['state']==='complete')return ['product_id'=>(int)$request['product_id'],'asset_id'=>(int)$request['asset_id'],'reused'=>true];
        if($request['state']!=='review')throw new RuntimeException('This software upload is not ready to save.');
        $boundProductId=(int)($request['metadata']['target_product_id']??0);
        if($boundProductId>0&&$productId!==$boundProductId)throw new InvalidArgumentException('This upload is bound to its selected software product. Start a new upload to choose another product.');
        $resolved=software_asset_resolve_path((string)$request['stored_path']);
        if($resolved===null)throw new RuntimeException('The staged ZIP is missing. Upload it again; no product was changed.');
        $inspection=software_asset_inspect($resolved);
        if(!hash_equals((string)$request['sha256'],$inspection['sha256'])||(int)$request['byte_size']!==$inspection['byte_size'])
            throw new RuntimeException('The staged ZIP changed. Upload it again; no product was changed.');
        $metadata=$request['metadata'];$embedded=(string)($metadata['embedded_sku']??'');
        $product=null;$newProduct=false;
        if($productId>0){
            $product=one('SELECT * FROM '.table('catalog_products').' WHERE id=? FOR UPDATE',[$productId]);
            if(!$product||$product['family']!=='software')throw new InvalidArgumentException('Choose an existing software product.');
            if($embedded!==''&&!hash_equals($embedded,(string)$product['sku']))throw new InvalidArgumentException('The package SKU does not match the selected product.');
        }else{
            $sku=$embedded!==''?$embedded:software_onboarding_generated_sku($title);
            $product=one('SELECT * FROM '.table('catalog_products').' WHERE sku=? FOR UPDATE',[$sku]);
            if($product){
                if($product['family']!=='software')throw new RuntimeException('The package SKU is already assigned to a non-software product.');
                $same=one('SELECT id,sha256 FROM '.table('software_assets').' WHERE sku=? AND version=? FOR UPDATE',[$sku,$version]);
                if(!$same||!hash_equals((string)$same['sha256'],$inspection['sha256']))
                    throw new RuntimeException('This software identity already exists. Select that product before adding a version.');
                $productId=(int)$product['id'];
            }else{
                $productId=catalog_save_in_transaction(0,0,['sku'=>$sku,'title'=>$title,'family'=>'software','status'=>'draft','term'=>'once','currency'=>'USD','amount'=>'','setup'=>'0.00','cost'=>'','provider_ref'=>'','summary'=>'','features'=>'','terms'=>'','sort_order'=>'0']);
                $product=one('SELECT * FROM '.table('catalog_products').' WHERE id=?',[$productId]);$newProduct=true;
            }
        }
        $sku=(string)$product['sku'];
        $existing=one('SELECT id,sha256 FROM '.table('software_assets').' WHERE sku=? AND version=? FOR UPDATE',[$sku,$version]);
        if($existing){
            if(!hash_equals((string)$existing['sha256'],$inspection['sha256']))throw new RuntimeException('That version already exists with different package bytes. Use a new version.');
            $assetId=(int)$existing['id'];$reused=true;
        }else{
            if(!$newProduct&&software_product_policy($productId)['revision']===0){
                software_product_policy_save_in_transaction($productId,0,(string)($metadata['module_key']??''),'',0,2,'');
            }elseif($newProduct){
                software_product_policy_save_in_transaction($productId,0,(string)($metadata['module_key']??''),'',0,2,'');
            }
            $assetId=software_asset_insert_in_transaction($sku,$version,$title,$inspection,'',false);$reused=false;
        }
        $now=gmdate('Y-m-d H:i:s');
        query('UPDATE '.table('software_upload_requests')." SET state='complete',product_id=?,asset_id=?,last_error='',updated_at=?,completed_at=? WHERE id=?",
            [$productId,$assetId,$now,$now,(int)$request['id']]);
        license_audit((int)$actor['id'],'software.onboarding.save','Saved draft software product '.$productId.' with private asset '.$assetId.($reused?' (existing bytes)':''));
        return ['product_id'=>$productId,'asset_id'=>$assetId,'reused'=>$reused];
    }));}catch(Throwable $error){software_onboarding_record_failure($requestKey,$error);throw $error;}
}

/** Delete only expired, unfinished staging bytes while retaining their audit rows. */
function software_onboarding_cleanup_expired(bool $requireOwner=true):int{
    if(!software_onboarding_ready())return 0;
    if(!$requireOwner&&PHP_SAPI!=='cli')throw new RuntimeException('Scheduled software cleanup is available only to the local CLI job runner.');
    return staff_locked(function()use($requireOwner):int{
        if($requireOwner)software_onboarding_require();
        $cutoff=gmdate('Y-m-d H:i:s',time()-172800);$removed=0;
        foreach(all("SELECT id,stored_path FROM ".table('software_upload_requests')." WHERE state='review' AND updated_at<? ORDER BY id LIMIT 50",[$cutoff]) as $row){
            $file=software_asset_resolve_path((string)$row['stored_path']);$deleted=$file===null||@unlink($file);
            if($file!==null&&$deleted)$removed++;
            staff_transaction(fn()=>query($deleted
                ?"UPDATE ".table('software_upload_requests')." SET state='expired',last_error='Staged package expired before save.',updated_at=? WHERE id=? AND state='review'"
                :"UPDATE ".table('software_upload_requests')." SET last_error='Expired staging cleanup could not remove the private ZIP.' WHERE id=? AND state='review'",
                $deleted?[gmdate('Y-m-d H:i:s'),(int)$row['id']]:[(int)$row['id']]));
        }
        return $removed;
    });
}
