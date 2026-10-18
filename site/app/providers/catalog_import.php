<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/billing/catalog.php';
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/package_types.php';
require_once __DIR__.'/catalog_import_document.php';
require_once __DIR__.'/catalog_import_storage.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_catalog_import_migrate.php';

function provider_catalog_import_owner(#[\SensitiveParameter] ?string $password=null): array {
    $actor=provider_settings_manager(false);
    catalog_require();
    if($password!==null&&!password_verify($password,$actor['password_hash']))throw new InvalidArgumentException('Your current password is not correct.');
    return $actor;
}
function provider_catalog_import_transaction(): void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(!db()->inTransaction()||(int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Catalog import requires the owning account transaction.');
}
/** Current authority and saved response only. This path has no transport or URL input. */
function provider_catalog_import_context(string $source='packages.list'): array {
    provider_catalog_import_transaction();$actor=provider_catalog_import_owner();
    if(!in_array($source,['packages.list','package.types'],true))throw new InvalidArgumentException('Choose a supported saved hosting source.');
    if(!provider_catalog_import_ready())throw new RuntimeException('Enable observed hosting type imports first.');
    $profile=provider_settings_profile();
    if(!$profile)throw new RuntimeException('Save a 20i provider profile and refresh hosting inventory first.');
    [, $row]=provider_inventory_authority($profile['id'],(int)$profile['revision']);
    if($source==='package.types'){
        if(!provider_package_types_ready())throw new RuntimeException('Prepare hosting catalog storage first.');
        $observation=provider_package_types_source($profile['id']);
    }else{
        $observation=one('SELECT f.id,f.profile_revision,r.state,r.scope,r.coverage,r.package_count,r.skipped,r.finished_at,r.key_fingerprint FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results')." r ON r.id=f.id WHERE f.profile_id=? AND COALESCE(f.operation,'packages.list')='packages.list' ORDER BY f.seq DESC LIMIT 1",[$profile['id']]);
    }
    if(!$observation||($observation['state']??null)!=='complete'||(int)$observation['profile_revision']!==(int)$profile['revision']||(int)$observation['skipped']!==0||$observation['scope']!=='twenty-i|'.$profile['id'].'|'.$source||$observation['coverage']!=='unknown')throw new RuntimeException($source==='package.types'?'Refresh the hosting catalog under the current 20i settings. Its newest response must finish with valid types; account-wide coverage remains unknown.':'Refresh hosting inventory under the current 20i settings. Its newest response must finish without skipped packages; account-wide coverage remains unknown.');
    $secret=provider_settings_unseal($row['id'],$row['environment'],$row['credentials']);
    if(!hash_equals(provider_inventory_fingerprint($secret),(string)$observation['key_fingerprint']))throw new RuntimeException('The saved inventory belongs to different credentials. Refresh it before importing.');
    if($source==='package.types'){
        $saved=provider_package_types_observation($observation);
        $document=ProviderCatalogImportDocument::fromPackageTypes($saved['types']);
    }else{
        $packages=all('SELECT type_ref,package_type_name FROM '.table('provider_observation_packages').' WHERE refresh_id=? ORDER BY package_id LIMIT 8193',[$observation['id']]);
        if(count($packages)!==(int)$observation['package_count'])throw new RuntimeException('The saved hosting observation needs recovery.');
        $document=ProviderCatalogImportDocument::fromRows($packages);
    }
    foreach($document->types as $type)if(str_contains($type['name'],$secret)||str_contains($type['name'],base64_encode($secret)))throw new RuntimeException('The saved hosting type name cannot be displayed safely.');
    unset($secret);
    $hash=hash('sha256',json_encode([$profile['id'],(int)$profile['revision'],$observation['id'],$observation['key_fingerprint'],(int)$actor['id'],$actor['auth_version'],$document->types,$document->unnamed],JSON_THROW_ON_ERROR));
    // Preserve existing inventory preview bytes; new catalog previews add their exact source operation.
    if($source==='package.types')$hash=hash('sha256',json_encode([$source,$hash],JSON_THROW_ON_ERROR));
    return ['profile_id'=>$profile['id'],'revision'=>(int)$profile['revision'],'refresh_id'=>$observation['id'],'source_key_fingerprint'=>$observation['key_fingerprint'],'observed_at'=>$observation['finished_at'],'hash'=>$hash,'document'=>$document,'source_operation'=>$source];
}
/** Exact original intents, never the currently selected observation. Validate history once per page/call. */
function provider_catalog_import_origins(array $records): array {
    provider_catalog_import_transaction();
    if($records===[])return [];
    // A changed/missing catalog intent cannot masquerade as legacy inventory provenance.
    $catalogReady=provider_package_types_ready();$sources=[];$origins=[];
    foreach($records as $record){
        provider_catalog_import_validate_record($record);
        $id=$record['refresh_id'];
        if(!isset($sources[$id])){
            $saved=one('SELECT f.*,r.* FROM '.table('provider_refreshes').' f JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.id=?',[$id]);
            $operation=$saved['operation']??'packages.list';
            if(!$saved||!in_array($operation,['packages.list','package.types'],true))throw new RuntimeException('This imported product source needs recovery.');
            $types=[];
            if($operation==='package.types'){
                if(!$catalogReady)throw new RuntimeException('This imported catalog source needs recovery.');
                $observation=provider_package_types_observation($saved);
                foreach($observation['types'] as $type)$types['type:'.$type['id']]=$type['label'];
            }
            $sources[$id]=[$saved,$operation,$types];
        }
        [$saved,$operation,$types]=$sources[$id];
        if($saved['profile_id']!==$record['profile_id']||(int)$saved['profile_revision']!==(int)$record['source_revision']||!hash_equals($record['source_key_fingerprint'],(string)$saved['key_fingerprint'])||($operation==='package.types'&&($types['type:'.$record['type_ref']]??null)!==$record['type_name']))throw new RuntimeException('This imported product source needs recovery.');
        $origins[(int)$record['product_id']]=['origin_operation'=>$operation,'origin_name'=>$record['type_name']];
    }
    return $origins;
}
function provider_catalog_import_preview(int $page=1,string $source='packages.list'): array {
    return staff_locked(fn()=>staff_transaction(function()use($page,$source):array {
        $context=provider_catalog_import_context($source);$document=$context['document'];unset($context['document']);
        $page=max(1,min(410,$page));$types=array_slice($document->types,($page-1)*20,21);$more=count($types)>20;$types=array_slice($types,0,20);
        $records=[];
        foreach($types as &$type){$record=one('SELECT * FROM '.table('catalog_provider_imports').' WHERE profile_id=? AND type_ref=?',[$context['profile_id'],$type['type_ref']]);$type['product_id']=$record?(int)$record['product_id']:null;$type['earlier_credentials']=$record&&!hash_equals($record['source_key_fingerprint'],$context['source_key_fingerprint']);if($record)$records[]=$record;}unset($type,$context['source_key_fingerprint']);
        $origins=provider_catalog_import_origins($records);
        foreach($types as &$type)$type+=$type['product_id']!==null?$origins[$type['product_id']]:['origin_operation'=>null,'origin_name'=>null];unset($type);
        return $context+['types'=>$types,'unnamed'=>$document->unnamed,'page'=>$page,'has_more'=>$more,'total_types'=>count($document->types)];
    }));
}
function provider_catalog_import_create(string $expected,string $type,string $sku,string $term,#[\SensitiveParameter] string $password,string $source='packages.list'): int {
    if(!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new InvalidArgumentException('Review the current saved hosting observation first.');
    return staff_locked(fn()=>staff_transaction(function()use($expected,$type,$sku,$term,$password,$source):int {
        $actor=provider_catalog_import_owner($password);$context=provider_catalog_import_context($source);
        if(!hash_equals($context['hash'],$expected))throw new RuntimeException('The observed hosting types or your access changed. Review the preview again before importing.');
        $document=$context['document'];$selected=$document->type($type);
        $old=one('SELECT product_id FROM '.table('catalog_provider_imports').' WHERE profile_id=? AND type_ref=?',[$context['profile_id'],$type]);
        if($old){$record=provider_catalog_import_record((int)$old['product_id']);if(!hash_equals($record['source_key_fingerprint'],$context['source_key_fingerprint']))throw new RuntimeException('This type already has a draft from earlier credentials. Review its original source; it cannot be reused as an import from the current credentials.');provider_catalog_import_origins([$record]);return (int)$old['product_id'];}
        return provider_catalog_import_create_locked($context,$actor,$type,$sku,$term);
    }));
}
/** Shared writer: the caller holds the mutex/transaction and freshly reviewed context. */
function provider_catalog_import_create_locked(array $context,array $actor,string $type,string $sku,string $term): int {
        provider_catalog_import_transaction();$document=$context['document'];$selected=$document->type($type);
        $product=catalog_save_in_transaction(0,0,$document->draft($type,$sku,$term));
        $sourceHash=hash('sha256',json_encode([$context['profile_id'],$context['revision'],$context['refresh_id'],$context['source_key_fingerprint'],$type,$selected['name']],JSON_THROW_ON_ERROR));
        query('INSERT INTO '.table('catalog_provider_imports').' (profile_id,type_ref,product_id,source_revision,refresh_id,source_key_fingerprint,type_name,source_hash,actor_id,created_at,media_id,media_revision) VALUES (?,?,?,?,?,?,?,?,?,?,NULL,0)',[$context['profile_id'],$type,$product,$context['revision'],$context['refresh_id'],$context['source_key_fingerprint'],$selected['name'],$sourceHash,(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        $count=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_count'])['value']);
        if($count>=999999999)throw new RuntimeException('Import history has reached its supported bound.');
        save_setting('provider_catalog_import_count',(string)($count+1));
        catalog_audit((int)$actor['id'],'provider_import.create','Product '.$product.' source '.$sourceHash);
        return $product;
}
/** At most 50 new local drafts. Preflight all saved identities and SKUs before writing. */
function provider_catalog_import_batch(string $expected,string $term,string $skuPrefix,#[\SensitiveParameter] string $password,string $source='package.types',int $offset=0): array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new InvalidArgumentException('Review the current saved hosting observation first.');
    return staff_locked(fn()=>staff_transaction(function()use($expected,$term,$skuPrefix,$password,$source,$offset):array {
        $actor=provider_catalog_import_owner($password);$context=provider_catalog_import_context($source);
        if(!hash_equals($context['hash'],$expected))throw new RuntimeException('The observed hosting types or your access changed. Review the preview again before importing.');
        $document=$context['document'];$records=[];
        // Chunk indexed lookups, including identities outside the current UI page/batch.
        foreach(array_chunk(array_column($document->types,'type_ref'),128) as $ids){
            $rows=all('SELECT * FROM '.table('catalog_provider_imports').' WHERE profile_id=? AND type_ref IN ('.implode(',',array_fill(0,count($ids),'?')).')',[$context['profile_id'],...$ids]);
            foreach($rows as $record){
                if(!hash_equals($record['source_key_fingerprint'],$context['source_key_fingerprint']))throw new RuntimeException('This catalog contains a draft from earlier credentials. Review its original source before importing the remaining types.');
                $records[]=$record;
            }
        }
        provider_catalog_import_origins($records);
        $plan=$document->batchPlan(array_column($records,'type_ref'),$term,$skuPrefix,$offset);
        foreach(array_chunk(array_column($plan['missing'],'sku'),128) as $skus){
            if(all('SELECT id,sku FROM '.table('catalog_products').' WHERE sku IN ('.implode(',',array_fill(0,count($skus),'?')).')',$skus))throw new RuntimeException('A generated SKU is already used by another product. Choose a different prefix before importing.');
        }
        $count=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_catalog_import_count'])['value']);
        if($count>999999999-count($plan['selected']))throw new RuntimeException('Import history has reached its supported bound.');
        // Validate every selected product before the first write, in addition to SKU preflight.
        foreach($plan['selected'] as $item)catalog_product_input($document->draft($item['type_ref'],$item['sku'],$term));
        $created=[];
        foreach($plan['selected'] as $item)$created[]=provider_catalog_import_create_locked($context,$actor,$item['type_ref'],$item['sku'],$term);
        return ['created_ids'=>$created,'created_count'=>count($created),'existing_count'=>$plan['existing_count'],'remaining_count'=>$plan['remaining_count'],'next_offset'=>$plan['next_offset'],'total_types'=>$plan['total_types'],'source_operation'=>$context['source_operation']];
    }));
}
function provider_catalog_import_image(int $product,int $revision,int $imageRevision,bool $remove,#[\SensitiveParameter] string $password): void {
    $uploaded=null;
    try {staff_locked(function()use($product,$revision,$imageRevision,$remove,$password,&$uploaded):void {
        staff_transaction(function()use($product,$revision,$imageRevision,$remove,$password,&$uploaded):void {
            $actor=provider_catalog_import_owner($password);$record=provider_catalog_import_record($product);
            $current=one('SELECT revision FROM '.table('catalog_products').' WHERE id=? FOR UPDATE',[$product]);
            if(!$record||!$current||(int)$current['revision']!==$revision||(int)$record['media_revision']!==$imageRevision||$imageRevision>=2147483647)throw new RuntimeException('This product or image changed. Reload before replacing it.');
            if($remove&&isset($_FILES['product_image'])&&($_FILES['product_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)throw new InvalidArgumentException('Choose either a replacement image or removal.');
            $media=null;
            if(!$remove){$uploaded=upload_image('product_image');if(!$uploaded)throw new InvalidArgumentException('Choose a JPG, PNG or WebP product image.');$media=$uploaded;}
            query('UPDATE '.table('catalog_provider_imports').' SET media_id=?,media_revision=? WHERE product_id=?',[$media,$imageRevision+1,$product]);
            catalog_audit((int)$actor['id'],'provider_import.image','Product '.$product.' image revision '.($imageRevision+1));
        });
    });}catch(Throwable $e){
        // Only the newly allocated local upload may be removed after DB rollback.
        if(is_string($uploaded)&&preg_match('/^[a-f0-9]{32}$/D',$uploaded)){
            $path=rtrim(config()['storage'],'/\\').'/media/'.$uploaded.'.php';
            if(!is_link($path)&&is_file($path))@unlink($path);
        }
        throw $e;
    }
}
