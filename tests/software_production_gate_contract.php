<?php
/** Pure production-payment activation contract: no DB, network or merchant secrets. */
declare(strict_types=1);
define('CWP_BOOT',true);
$softwareGateSettings=[];
function setting(string $name,mixed $default=null):mixed{
    global $softwareGateSettings;
    return array_key_exists($name,$softwareGateSettings)?$softwareGateSettings[$name]:$default;
}
require dirname(__DIR__).'/site/app/billing/authorize_net_client.php';

function gatecheck(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}

gatecheck(!software_anet_production_enabled(),
    'Production is disabled with no owner review and no code-level activation');
$softwareGateSettings['software_gateway_production_reviewed']='1';
gatecheck(!software_anet_production_enabled(),
    'Database review flag alone cannot enable live software charging');
define('CWP_SOFTWARE_PRODUCTION_PAYMENTS',true);
gatecheck(software_anet_production_enabled(),
    'Production becomes eligible only with code-level activation and reviewed setting');
$softwareGateSettings['software_gateway_production_reviewed']='0';
gatecheck(!software_anet_production_enabled(),
    'Removing owner-reviewed activation disables production even when code switch exists');

$controller=file_get_contents(dirname(__DIR__).'/site/admin/controllers/studio.php');
$view=file_get_contents(dirname(__DIR__).'/site/admin/views/studio/licenses.php');
if(!is_string($controller)||!is_string($view))throw new RuntimeException('Production gate UI sources unavailable.');
gatecheck(str_contains($controller,"$action==='software_gateway_production_review'")
    &&str_contains($controller,"CWP_SOFTWARE_PRODUCTION_PAYMENTS")
    &&str_contains($controller,"software_gateway_production_reviewed"),
    'Owner review action is itself protected by the code-level production gate');
gatecheck(str_contains($view,"admin_action('software_gateway_production_review')")
    &&str_contains($view,'This records review only.')
    &&!str_contains($view,'<option value="production">'),
    'Owner review UI cannot directly select production checkout');

echo 'Software production payment gate: PASS'.PHP_EOL;
