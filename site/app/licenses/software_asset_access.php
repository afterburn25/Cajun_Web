<?php
/** Customer entitlement check for private software assets. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';

function software_asset_entitlement_authorized(int $clientId,int $licenseId,array $asset):?array{
    if($clientId<1||$licenseId<1||!software_commerce_ready())return null;
    $sku=(string)($asset['sku']??'');$released=$asset['released_at']??null;
    if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku)
        ||!is_string($released)
        ||!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D',$released))return null;
    $license=one('SELECT l.*,e.invoice_id,e.unit_number,e.updates_until FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.id=? AND l.customer_id=? AND l.product_id=? AND l.is_active=1 AND l.is_master=0',
        [$licenseId,$clientId,$sku]);
    if(!$license)return null;
    if($license['invoice_id']!==null){
        $unit=(int)($license['unit_number']??0);
        $order=one('SELECT client_id,sku,quantity FROM '.table('software_orders').' WHERE invoice_id=?',
            [(int)$license['invoice_id']]);
        if(!$order||(int)$order['client_id']!==$clientId||(string)$order['sku']!==$sku
            ||$unit<1||$unit>(int)$order['quantity'])return null;
    }elseif($license['unit_number']!==null){
        return null;
    }
    if($license['updates_until']!==null&&$released>(string)$license['updates_until'])return null;
    $domains=all('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 ORDER BY id LIMIT 25',[$licenseId]);
    foreach($domains as $domain){
        if(is_string($domain['domain']??null)&&software_grant_verdict($license,$domain['domain']))return $license;
    }
    return null;
}

function software_asset_customer_authorized(int $clientId,string $sku,?string $assetReleasedAt=null):bool{
    if($clientId<1||!software_commerce_ready()||!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku))return false;
    if($assetReleasedAt!==null&&!preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D',$assetReleasedAt))return false;
    $licenses=all('SELECT l.*,e.updates_until FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.customer_id=? AND l.product_id=? AND l.is_active=1 AND l.is_master=0 LIMIT 100',
        [$clientId,$sku]);
    foreach($licenses as $license){
        if($assetReleasedAt!==null&&$license['updates_until']!==null&&$assetReleasedAt>$license['updates_until'])continue;
        $domains=all('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 ORDER BY id LIMIT 25',[$license['id']]);
        foreach($domains as $domain){
            if(is_string($domain['domain']??null)&&software_grant_verdict($license,$domain['domain']))return true;
        }
    }
    return false;
}
