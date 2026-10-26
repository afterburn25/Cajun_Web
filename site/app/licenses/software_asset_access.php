<?php
/** Customer entitlement check for private software assets. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';

function software_asset_customer_authorized(int $clientId,string $sku):bool{
    if($clientId<1||!software_commerce_ready()||!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku))return false;
    $licenses=all('SELECT l.* FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.product_id=? AND l.is_active=1 AND l.is_master=0 LIMIT 100',
        [$clientId,$sku]);
    foreach($licenses as $license){
        $domains=all('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 ORDER BY id LIMIT 3',[$license['id']]);
        foreach($domains as $domain){
            if(is_string($domain['domain']??null)&&software_grant_verdict($license,$domain['domain']))return true;
        }
    }
    return false;
}
