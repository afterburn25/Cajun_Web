<?php
/** Per-product software licensing policy. Existing entitlements snapshot this policy at issue time. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';

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

function software_product_policy_assert_acyclic(int $productId,array $replacementDependencies):void{
    if($productId<1||!array_is_list($replacementDependencies))throw new InvalidArgumentException('Invalid software dependency graph.');
    $graph=[];
    foreach(all('SELECT product_id,dependencies FROM '.table('software_product_policies').' ORDER BY product_id') as $row){
        $ids=json_decode((string)$row['dependencies'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($ids)||!array_is_list($ids)||count($ids)>25)throw new RuntimeException('Existing software dependency graph is invalid.');
        $clean=[];
        foreach($ids as $id){$id=(int)$id;if($id<1)throw new RuntimeException('Existing software dependency graph is invalid.');$clean[$id]=true;}
        $graph[(int)$row['product_id']]=array_keys($clean);
    }
    $graph[$productId]=array_values(array_unique(array_map('intval',$replacementDependencies)));
    $visiting=[];$done=[];
    $walk=function(int $node)use(&$walk,&$graph,&$visiting,&$done):void{
        if(isset($done[$node]))return;
        if(isset($visiting[$node]))throw new InvalidArgumentException('Software dependency policies cannot contain a circular dependency.');
        $visiting[$node]=true;
        foreach($graph[$node]??[] as $dep)$walk((int)$dep);
        unset($visiting[$node]);$done[$node]=true;
    };
    foreach(array_keys($graph) as $node)$walk((int)$node);
}

function software_product_policy_save(int $productId,int $expectedRevision,string $moduleKey,string $dependencies,
    int $updateMonths,int $maxInstallations,#[\SensitiveParameter] string $password):array{
    if(!software_commerce_ready())throw new RuntimeException('Prepare software commerce first.');
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
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku))throw new InvalidArgumentException('Dependency SKU format is invalid.');
        if(isset($seen[$sku]))continue;
        $seen[$sku]=true;
        $dep=one('SELECT id,sku,family FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
        if(!$dep)throw new InvalidArgumentException('Unknown dependency SKU: '.$sku);
        $depLegacy=preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]+$/D',(string)$dep['sku'])===1;
        if(($dep['family']??'')!=='software'&&!$depLegacy)throw new InvalidArgumentException('Dependencies must be software products.');
        if((int)$dep['id']===$productId)throw new InvalidArgumentException('A software product cannot depend on itself.');
        $ids[]=(int)$dep['id'];
    }
    sort($ids,SORT_NUMERIC);$encoded=json_encode($ids,JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');

    staff_locked(fn()=>staff_transaction(function()use($productId,$expectedRevision,$moduleKey,$encoded,$updateMonths,$maxInstallations,$password,$now):void{
        $actor=staff_owner($password);
        $actorId=(int)$actor['id'];
        $current=one('SELECT * FROM '.table('software_product_policies').' WHERE product_id=? FOR UPDATE',[$productId]);
        $revision=(int)($current['revision']??0);
        if($revision!==$expectedRevision)throw new RuntimeException('This software policy changed. Reload before saving.');
        $replacement=json_decode($encoded,true,32,JSON_THROW_ON_ERROR);
        software_product_policy_assert_acyclic($productId,$replacement);
        if($current){
            query('UPDATE '.table('software_product_policies').' SET module_key=?,dependencies=?,update_months=?,max_installations=?,revision=?,updated_by=?,updated_at=? WHERE product_id=?',
                [$moduleKey,$encoded,$updateMonths,$maxInstallations,$revision+1,$actorId,$now,$productId]);
        }else{
            query('INSERT INTO '.table('software_product_policies').' (product_id,module_key,dependencies,update_months,max_installations,revision,updated_by,updated_at) VALUES (?,?,?,?,?,1,?,?)',
                [$productId,$moduleKey,$encoded,$updateMonths,$maxInstallations,$actorId,$now]);
        }
        license_audit($actorId,'software.policy','Software product '.$productId.' policy revision '.($revision+1).
            '; update months '.$updateMonths.'; max installations '.$maxInstallations);
    }));
    return software_product_policy($productId);
}

function software_product_policy_purchase_snapshot(array $policy):array{
    $module=(string)($policy['module_key']??'');
    $deps=$policy['dependency_skus']??null;
    $months=$policy['update_months']??null;
    $max=$policy['max_installations']??null;
    $revision=$policy['revision']??null;
    if($module!==''&&!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/D',$module)
        ||!is_array($deps)||!array_is_list($deps)||count($deps)>25
        ||!is_int($months)||$months<0||$months>120
        ||!is_int($max)||$max<1||$max>25
        ||!is_int($revision)||$revision<0){
        throw new RuntimeException('Software product policy snapshot is invalid.');
    }
    $clean=[];$seen=[];
    foreach($deps as $sku){
        if(!is_string($sku)||!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku)){
            throw new RuntimeException('Software dependency policy snapshot is invalid.');
        }
        if(isset($seen[$sku]))continue;
        $seen[$sku]=true;$clean[]=$sku;
    }
    sort($clean,SORT_STRING);
    return [
        'module_key'=>$module,
        'dependency_skus'=>$clean,
        'update_months'=>$months,
        'max_installations'=>$max,
        'revision'=>$revision,
    ];
}

function software_policy_add_months(DateTimeImmutable $base,int $months):string{
    if($months<1||$months>120)throw new InvalidArgumentException('Software update period must be between one and 120 months.');
    $utc=$base->setTimezone(new DateTimeZone('UTC'));
    $year=(int)$utc->format('Y');$month=(int)$utc->format('n');$day=(int)$utc->format('j');
    $absolute=$year*12+($month-1)+$months;
    $targetYear=intdiv($absolute,12);$targetMonth=($absolute%12)+1;
    $first=$utc->setDate($targetYear,$targetMonth,1);
    $targetDay=min($day,(int)$first->format('t'));
    return $first->setDate($targetYear,$targetMonth,$targetDay)->format('Y-m-d H:i:s');
}

function software_entitlement_policy_snapshot(string $sku,?string $expires,bool $recurring,DateTimeImmutable $now,?array $purchasePolicy=null):array{
    $policy=$purchasePolicy===null
        ? software_product_policy_purchase_snapshot(software_product_policy_for_sku($sku))
        : software_product_policy_purchase_snapshot($purchasePolicy);
    $updatesUntil=null;
    if($recurring){
        if($expires===null)throw new InvalidArgumentException('Recurring software entitlement requires paid-through expiry.');
        $updatesUntil=$expires;
    }elseif($policy['update_months']>0){
        $candidate=software_policy_add_months($now,(int)$policy['update_months']);
        $updatesUntil=$expires!==null&&$expires<$candidate?$expires:$candidate;
    }
    return [
        'updates_until'=>$updatesUntil,
        'max_installations'=>(int)$policy['max_installations'],
        'policy_revision'=>(int)$policy['revision'],
    ];
}
