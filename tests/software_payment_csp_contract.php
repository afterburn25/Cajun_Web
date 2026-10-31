<?php
/** Source-level payment CSP contract; no network, merchant credentials, or card data. */
declare(strict_types=1);

function cspcheck(bool $ok,string $name):void{
    if(!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}

$root=dirname(__DIR__);
$http=file_get_contents($root.'/site/includes/security/http.php');
$portal=file_get_contents($root.'/site/portal/views/software.php');
$generated=file_get_contents($root.'/site/install/bootstrap.generated.php');
if(!is_string($http)||!is_string($portal)||!is_string($generated)){
    throw new RuntimeException('Required CSP source is unavailable.');
}

cspcheck(str_contains($http,"script-src 'self' 'nonce-") && str_contains($http,"connect-src 'self';"),
    'Default CSP remains self-only for scripts and connections');
cspcheck(str_contains($http,"'sandbox','production'"),
    'Payment CSP accepts only the two known gateway environments');
cspcheck(str_contains($http,"https://jstest.authorize.net") && str_contains($http,"https://js.authorize.net"),
    'Payment CSP names exact Authorize.net script hosts');
cspcheck(str_contains($http,"https://apitest.authorize.net")
    && str_contains($http,"https://api.authorize.net")
    && str_contains($http,"https://api2.authorize.net"),
    'Payment CSP names exact Authorize.net tokenization/API hosts');
cspcheck(!str_contains($http,'*.authorize.net'),
    'Payment CSP does not wildcard the Authorize.net domain');
cspcheck(str_contains($portal,"authorize_net_security_headers(\$gateway['mode'])")
    && strpos($portal,'authorize_net_security_headers') < strpos($portal,"site_head('My Software & Licenses'"),
    'Payment CSP is selected before portal output');
cspcheck(substr_count($portal,"'<script nonce=\"'.nonce().'\" defer src=\"'")===2,
    'Authorize.net and local checkout scripts carry the request nonce');
cspcheck(str_contains($generated,'function authorize_net_security_headers(string $mode): void'),
    'Generated installer bootstrap contains the canonical payment CSP helper');

echo 'Software payment CSP contract: PASS'.PHP_EOL;
