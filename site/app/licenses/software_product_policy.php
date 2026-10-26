<?php
/** Enforceable per-product software licensing policy stored in existing settings storage. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function software_product_policy_defaults():array{
    return [
        'max_installations'=>2,
        'download_visibility'=>'customer',
        'update_entitlement'=>'all',
        'module'=>'',
        'dependency_sku'=>'',
    ];
}
function software_product_policy_normalize(array $input):array{
    $max=$input['max_installations']??2;
    if(is_string($max)&&preg_match('/^[0-9]{1,2}$/D',$max))$max=(int)$max;
    if(!is_int($max)||$max<1||$max>25)throw new InvalidArgumentException('Allowed installations must be between 1 and 25.');
    $visibility=(string)($input['download_visibility']??'customer');
    if(!in_array($visibility,['customer','owner_only'],true))throw new InvalidArgumentException('Invalid software download visibility.');
    $updates=(string)($input['update_entitlement']??'all');
    if(!in_array($updates,['all','purchase_release'],true))throw new InvalidArgumentException('Invalid software update entitlement.');
    $module=trim((string)($input['module']??''));
    if(strlen($module)>80)throw new InvalidArgumentException('Software module label is too long.');
    $dependency=strtoupper(trim((string)($input['dependency_sku']??'')));
    if($dependency!==''&&!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$dependency)){
        throw new InvalidArgumentException('Invalid software dependency SKU.');
    }
    return [
        'max_installations'=>$max,
        'download_visibility'=>$visibility,
        'update_entitlement'=>$updates,
        'module'=>$module,
        'dependency_sku'=>$dependency,
    ];
}
function software_product_policy_key(int $productId):string{
    if($productId<1)throw new InvalidArgumentException('Software product is required.');
    return 'software_product_policy_'.$productId;
}
function software_product_policy_product(int $productId):array{
    $product=one('SELECT id,sku,family FROM '.table('catalog_products').' WHERE id=?',[$productId]);
    if(!$product)throw new InvalidArgumentException('Software product not found.');
    $legacy=preg_match('/^(?:SOFTWARE|WEBSITE|APP)-[A-Z0-9_-]+$/D',(string)$product['sku'])===1;
    if(($product['family']??'')!=='software'&&!$legacy)throw new InvalidArgumentException('Choose a registered software product.');
    return $product;
}
function software_product_policy(int $productId):array{
    software_product_policy_product($productId);
    $raw=(string)setting(software_product_policy_key($productId),'');
    if($raw==='')return software_product_policy_defaults();
    try{$decoded=json_decode($raw,true,12,JSON_THROW_ON_ERROR);}
    catch(JsonException $e){throw new RuntimeException('Stored software product policy is invalid.',0,$e);}
    if(!is_array($decoded))throw new RuntimeException('Stored software product policy is invalid.');
    return software_product_policy_normalize($decoded);
}
function software_product_policy_for_sku(string $sku):array{
    $product=one('SELECT id FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    if(!$product)throw new InvalidArgumentException('Software product not found.');
    return software_product_policy((int)$product['id']);
}
function software_product_policy_save(int $productId,array $input,int $actor):array{
    $product=software_product_policy_product($productId);
    $policy=software_product_policy_normalize($input);
    if($policy['dependency_sku']!==''){
        if(hash_equals((string)$product['sku'],$policy['dependency_sku']))throw new InvalidArgumentException('Software cannot depend on itself.');
        $dependency=one('SELECT id,sku,family FROM '.table('catalog_products').' WHERE sku=?',[$policy['dependency_sku']]);
        $legacy=$dependency&&preg_match('/^(?:SOFTWARE|WEBSITE|APP)-[A-Z0-9_-]+$/D',(string)$dependency['sku'])===1;
        if(!$dependency||(($dependency['family']??'')!=='software'&&!$legacy)){
            throw new InvalidArgumentException('Dependency must be another registered software product.');
        }
    }
    save_setting(software_product_policy_key($productId),json_encode($policy,JSON_THROW_ON_ERROR));
    license_audit($actor,'software.policy','Software product '.$product['sku'].' licensing policy updated');
    return $policy;
}
function software_product_latest_asset_id(string $sku):?int{
    $row=one('SELECT id FROM '.table('software_assets').' WHERE sku=? AND is_published=1 ORDER BY id DESC LIMIT 1',[$sku]);
    return $row?(int)$row['id']:null;
}
function software_product_order_snapshot(int $productId):array{
    $product=software_product_policy_product($productId);
    $policy=software_product_policy($productId);
    $release=null;
    if($policy['update_entitlement']==='purchase_release'){
        $release=software_product_latest_asset_id((string)$product['sku']);
        if($release===null)throw new RuntimeException('This software requires a published release before purchase-release-only licensing can be sold.');
    }
    return ['policy'=>$policy,'release_id'=>$release];
}
function software_policy_from_order_snapshot(array $quote):array{
    $policy=$quote['software_policy']??software_product_policy_defaults();
    if(!is_array($policy))throw new RuntimeException('Software order policy snapshot is invalid.');
    $normalized=software_product_policy_normalize($policy);
    $release=$quote['software_release_id']??null;
    if($release!==null&&(!is_int($release)&&!(is_string($release)&&preg_match('/^[1-9][0-9]*$/D',$release)))){
        throw new RuntimeException('Software order release snapshot is invalid.');
    }
    return ['policy'=>$normalized,'release_id'=>$release===null?null:(int)$release];
}
function software_license_policy_key(int $licenseId):string{
    if($licenseId<1)throw new InvalidArgumentException('License is required.');
    return 'software_license_policy_'.$licenseId;
}
function software_license_policy_store(int $licenseId,array $snapshot):void{
    $policy=software_product_policy_normalize($snapshot['policy']??[]);
    $release=$snapshot['release_id']??null;
    if($release!==null&&(!is_int($release)||$release<1))throw new InvalidArgumentException('Invalid software release snapshot.');
    save_setting(software_license_policy_key($licenseId),json_encode(['policy'=>$policy,'release_id'=>$release],JSON_THROW_ON_ERROR));
}
function software_license_policy_snapshot(int $licenseId,string $sku):array{
    $raw=(string)setting(software_license_policy_key($licenseId),'');
    if($raw!==''){
        try{$snapshot=json_decode($raw,true,12,JSON_THROW_ON_ERROR);}
        catch(JsonException $e){throw new RuntimeException('Stored software license policy is invalid.',0,$e);}
        if(!is_array($snapshot))throw new RuntimeException('Stored software license policy is invalid.');
        return software_policy_from_order_snapshot([
            'software_policy'=>$snapshot['policy']??null,
            'software_release_id'=>$snapshot['release_id']??null,
        ]);
    }
    $product=one('SELECT id FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    if(!$product)return ['policy'=>software_product_policy_defaults(),'release_id'=>null];
    return software_product_order_snapshot((int)$product['id']);
}
function software_client_has_active_entitlement(int $clientId,string $sku):bool{
    if($clientId<1||$sku==='')return false;
    return (bool)one('SELECT l.id FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.product_id=? AND l.is_active=1 AND l.is_master=0 AND (e.expires_at IS NULL OR e.expires_at>UTC_TIMESTAMP()) LIMIT 1',
        [$clientId,$sku]);
}
function software_policy_customer_can_order(int $clientId,array $product,array $snapshot):bool{
    $policy=$snapshot['policy']??[];
    if(($policy['download_visibility']??'customer')!=='customer')return false;
    $dependency=(string)($policy['dependency_sku']??'');
    return $dependency===''||software_client_has_active_entitlement($clientId,$dependency);
}
function software_policy_asset_allowed(array $snapshot,int $assetId):bool{
    $policy=$snapshot['policy']??[];
    if(($policy['download_visibility']??'customer')!=='customer')return false;
    if(($policy['update_entitlement']??'all')==='all')return true;
    return (int)($snapshot['release_id']??0)===$assetId;
}
