<?php
/** Per-product software licensing policy. Existing entitlements snapshot this policy at issue time. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function software_product_policy_defaults():array{
    return [
        'product_id'=>0,
        'module_key'=>'',
        'dependency_ids'=>[],
        'dependency_skus'=>[],
        'update_months'=>0, // 0 = lifetime updates while the license itself is valid.
        'max_installations'=>2,
        'revision'=>0,
    ];
}

function software_product_policy(int $productId):array{
    if($productId<1)throw new InvalidArgumentException('Software product is required.');
    $product=one('SELECT id,sku,family FROM '.table('catalog_products').' WHERE id=?',[$productId]);
    if(!$product)throw new InvalidArgumentException('Software product not found.');
    $legacy=preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]+$/D',(string)$product['sku'])===1;
    if(($product['family']??'')!=='software'&&!$legacy)throw new InvalidArgumentException('Choose a software catalog product.');
    $policy=software_product_policy_defaults();
    $policy['product_id']=$productId;
    if(!software_commerce_table_present('software_product_policies'))return $policy;
    $row=one('SELECT * FROM '.table('software_product_policies').' WHERE product_id=?',[$productId]);
    if(!$row)return $policy;
    $ids=json_decode((string)$row['dependencies'],true,32,JSON_THROW_ON_ERROR);
    if(!is_array($ids)||!array_is_list($ids)||count($ids)>25)throw new RuntimeException('Software dependency policy is invalid.');
    $ids=array_values(array_unique(array_map('intval',$ids)));
    foreach($ids as $id)if($id<1)throw new RuntimeException('Software dependency policy is invalid.');
    $skus=[];
    if($ids){
        $marks=implode(',',array_fill(0,count($ids),'?'));
        foreach(all('SELECT id,sku FROM '.table('catalog_products').' WHERE id IN ('.$marks.')',$ids) as $dep){
            $skus[(int)$dep['id']]=(string)$dep['sku'];
        }
        foreach($ids as $id)if(!isset($skus[$id]))throw new RuntimeException('Software dependency policy references a missing product.');
    }
    $months=(int)$row['update_months'];$max=(int)$row['max_installations'];$revision=(int)$row['revision'];
    if($months<0||$months>120||$max<1||$max>25||$revision<1)throw new RuntimeException('Software product policy is invalid.');
    return [
        'product_id'=>$productId,
        'module_key'=>(string)$row['module_key'],
        'dependency_ids'=>$ids,
        'dependency_skus'=>array_values(array_map(fn($id)=>$skus[$id],$ids)),
        'update_months'=>$months,
        'max_installations'=>$max,
        'revision'=>$revision,
    ];
}

function software_product_policy_for_sku(string $sku):array{
    $sku=strtoupper(trim($sku));
    if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku))throw new InvalidArgumentException('Unknown software SKU.');
    $product=one('SELECT id FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    if(!$product)throw new InvalidArgumentException('Software product not found.');
    return software_product_policy((int)$product['id']);
}

function software_product_policy_save(int $productId,int $expectedRevision,string $moduleKey,string $dependencies,
    int $updateMonths,int $maxInstallations,int $actor):array{
    if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
    if($actor<1)throw new InvalidArgumentException('Owner identity is required.');
    if($expectedRevision<0||$updateMonths<0||$updateMonths>120||$maxInstallations<1||$maxInstallations>25){
        throw new InvalidArgumentException('Software policy values are outside supported limits.');
    }
    $moduleKey=trim($moduleKey);
    if($moduleKey!==''&&!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/D',$moduleKey)){
        throw new InvalidArgumentException('Module key may use letters, numbers, dots, underscores and dashes.');
    }
    $product=one('SELECT id,sku,family FROM '.table('catalog_products').' WHERE id=?',[$productId]);
    if(!$product)throw new InvalidArgumentException('Software product not found.');
    $legacy=preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]+$/D',(string)$product['sku'])===1;
    if(($product['family']??'')!=='software'&&!$legacy)throw new InvalidArgumentException('Choose a software catalog product.');

    $tokens=preg_split('/[\s,]+/',strtoupper(trim($dependencies)),-1,PREG_SPLIT_NO_EMPTY)?:[];
    if(count($tokens)>25)throw new InvalidArgumentException('Use no more than 25 software dependencies.');
    $ids=[];$seen=[];
    foreach($tokens as $sku){
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku)||isset($seen[$sku]))continue;
        $seen[$sku]=true;
        $dep=one('SELECT id,sku,family FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
        if(!$dep)throw new InvalidArgumentException('Unknown dependency SKU: '.$sku);
        $depLegacy=preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]+$/D',(string)$dep['sku'])===1;
        if(($dep['family']??'')!=='software'&&!$depLegacy)throw new InvalidArgumentException('Dependencies must be software products.');
        if((int)$dep['id']===$productId)throw new InvalidArgumentException('A software product cannot depend on itself.');
        $ids[]=(int)$dep['id'];
    }
    sort($ids,SORT_NUMERIC);$encoded=json_encode($ids,JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');

    staff_locked(function()use($productId,$expectedRevision,$moduleKey,$encoded,$updateMonths,$maxInstallations,$actor,$now):void{
        if(db()->inTransaction())throw new LogicException('Software product policy owns its transaction.');
        db()->beginTransaction();
        try{
            $current=one('SELECT * FROM '.table('software_product_policies').' WHERE product_id=? FOR UPDATE',[$productId]);
            $revision=(int)($current['revision']??0);
            if($revision!==$expectedRevision)throw new RuntimeException('This software policy changed. Reload before saving.');
            if($current){
                query('UPDATE '.table('software_product_policies').' SET module_key=?,dependencies=?,update_months=?,max_installations=?,revision=?,updated_by=?,updated_at=? WHERE product_id=?',
                    [$moduleKey,$encoded,$updateMonths,$maxInstallations,$revision+1,$actor,$now,$productId]);
            }else{
                query('INSERT INTO '.table('software_product_policies').' (product_id,module_key,dependencies,update_months,max_installations,revision,updated_by,updated_at) VALUES (?,?,?,?,?,1,?,?)',
                    [$productId,$moduleKey,$encoded,$updateMonths,$maxInstallations,$actor,$now]);
            }
            license_audit($actor,'software.policy','Software product '.$productId.' policy revision '.($revision+1).
                '; update months '.$updateMonths.'; max installations '.$maxInstallations);
            db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    });
    return software_product_policy($productId);
}

function software_entitlement_policy_snapshot(string $sku,?string $expires,bool $recurring,DateTimeImmutable $now):array{
    $policy=software_product_policy_for_sku($sku);
    $updatesUntil=null;
    if($recurring){
        if($expires===null)throw new InvalidArgumentException('Recurring software entitlement requires paid-through expiry.');
        $updatesUntil=$expires;
    }elseif($policy['update_months']>0){
        $candidate=$now->modify('+'.(int)$policy['update_months'].' months')->format('Y-m-d H:i:s');
        $updatesUntil=$expires!==null&&$expires<$candidate?$expires:$candidate;
    }
    return [
        'updates_until'=>$updatesUntil,
        'max_installations'=>(int)$policy['max_installations'],
        'policy_revision'=>(int)$policy['revision'],
    ];
}
