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
    routing_ok(
        strpos($branch,"require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';")
            < strpos($branch,'software_commerce_ready()'),
        $action.' loads the commerce readiness boundary before calling it'
    );
}

echo "Software admin routing contract: PASS".PHP_EOL;
