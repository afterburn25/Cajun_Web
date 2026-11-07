<?php
/** Fast source contract for the owner-only software administration dispatcher. */
declare(strict_types=1);

$root=dirname(__DIR__);
function routing_source(string $path):string{
    global $root;
    $source=file_get_contents($root.'/'.$path);
    if($source===false)throw new RuntimeException('FAIL cannot read '.$path);
    return $source;
}
function routing_ok(bool $condition,string $name):void{
    if(!$condition)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}

$actions=routing_source('site/admin/controllers/actions.php');
$permissions=routing_source('site/app/staff/accounts.php');
$studio=routing_source('site/admin/controllers/studio.php');
$grants=routing_source('site/app/licenses/software_grants.php');
$products=routing_source('site/app/licenses/software_products.php');
$assets=routing_source('site/app/licenses/software_assets.php');

routing_ok(
    str_contains($actions,"str_starts_with(\$action,'studio_')||str_starts_with(\$action,'software_')")
        &&str_contains($actions,'studio_admin_action($action,$user)'),
    'Software administration posts reach the studio controller'
);
routing_ok(
    str_contains($permissions,"if(str_starts_with(\$action,'software_'))return 'staff.manage';"),
    'Every software administration action requires owner-only staff management authority'
);

foreach(['software_license_lifecycle','studio_license_deactivate','studio_license_reactivate'] as $action){
    $start=strpos($studio,"elseif(\$action==='".$action."')");
    routing_ok($start!==false,$action.' branch exists');
    $next=strpos($studio,"}elseif(\$action===",$start+1);
    $branch=substr($studio,$start,$next===false?null:$next-$start);
    $requirePos=strpos($branch,"require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';");
    $readyPos=strpos($branch,'software_commerce_ready()');
    routing_ok(
        $requirePos!==false&&$readyPos!==false&&$requirePos<$readyPos,
        $action.' loads the commerce readiness boundary before calling it'
    );
}

$reviewStart=strpos($studio,"elseif(\$action==='software_license_review')");
$clearStart=strpos($studio,"}elseif(\$action==='software_license_review_clear'",$reviewStart+1);
$issueStart=strpos($studio,"}elseif(\$action==='software_license_issue'",$clearStart+1);
$lifecycleStart=strpos($studio,"}elseif(\$action==='software_license_lifecycle'",$issueStart+1);
routing_ok($reviewStart!==false&&$clearStart!==false&&$issueStart!==false&&$lifecycleStart!==false,
    'Guided review, clear and issue branches are distinct owner actions');
$reviewBranch=substr($studio,$reviewStart,$clearStart-$reviewStart);
$issueBranch=substr($studio,$issueStart,$lifecycleStart-$issueStart);
routing_ok(str_contains($reviewBranch,'software_grant_manual_review(')
    &&str_contains($reviewBranch,"\$_SESSION['software_license_review']")
    &&str_contains($reviewBranch,'software_grant_manual_review_hash($review)'),
    'Customer license review is non-mutating and stores an exact current snapshot');
routing_ok(str_contains($issueBranch,"posted('confirm',1)!=='1'")
    &&str_contains($issueBranch,"hash_equals(\$draft['token'],posted('review_token',64))")
    &&str_contains($issueBranch,'software_grant_manual_review(')
    &&str_contains($issueBranch,"\$current['release']===null")
    &&str_contains($issueBranch,'software_grant_manual_review_hash($current)')
    &&str_contains($issueBranch,'staff_locked(function()')
    &&str_contains($issueBranch,"staff_require('staff.manage');")
    &&str_contains($issueBranch,'db()->beginTransaction()')
    &&str_contains($issueBranch,'software_grant_create(')
    &&strpos($issueBranch,'staff_locked(function()')<strpos($issueBranch,'db()->beginTransaction()')
    &&strpos($issueBranch,'db()->beginTransaction()')<strpos($issueBranch,'software_grant_manual_review(')
    &&strpos($issueBranch,'software_grant_manual_review(')<strpos($issueBranch,'software_grant_create(')
    &&strpos($issueBranch,'software_grant_create(')<strpos($issueBranch,'db()->commit()'),
    'Issuance requires confirmation, exact session token and a fresh review inside the mutation transaction');
routing_ok(
    str_contains($products,'return staff_locked(fn()=>staff_transaction(fn()=>software_product_policy_save_in_transaction(')
    &&str_contains($assets,'return staff_locked(fn()=>staff_transaction(function()use($assetId,$customerVisible,$password):bool{'),
    'Policy and release-visibility mutations use the same staff mutex as final issuance review'
);
routing_ok(str_contains($grants,'function software_grant_manual_review(')
    &&str_contains($grants,'[SoftwareLicensePolicy::STANDARD,SoftwareLicensePolicy::TRIAL,SoftwareLicensePolicy::TEMPORARY]')
    &&str_contains($grants,"WHERE id=? AND active=1")
    &&str_contains($grants,'software_product_policy_purchase_snapshot(software_product_policy(')
    &&str_contains($grants,'software_dependencies_satisfied(')
    &&str_contains($grants,'This customer is missing required software: ')
    &&str_contains($grants,'software_customer_has_valid_product(')
    &&str_contains($grants,"is_published=1 AND released_at IS NOT NULL")
    &&str_contains($grants,'function software_grant_manual_review_hash('),
    'Review reuses current customer, product, policy, dependency and approved-release state while excluding master keys');

echo "Software admin routing contract: PASS".PHP_EOL;
