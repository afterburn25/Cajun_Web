<?php
/** Idempotent order-line license issuance. Do not invoke before verified payment. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_license_policy.php';
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_products.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';

function software_grant_create(int $clientId, string $sku, string $kind, string $name,
    ?string $domain, ?string $expires, int $issuer=0, ?int $invoice=null, ?int $unit=null,
    string $purpose='', bool $recurring=false, ?array $purchasePolicy=null,
    #[\SensitiveParameter] ?string $masterPassword=null):array {
    if (!software_commerce_ready()) throw new RuntimeException('Software licensing is not prepared.');
    $issuedAt=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $metadata=SoftwareLicensePolicy::issue($kind,$name,$domain,$expires,$issuer>0,$issuedAt);
    $purpose=trim($purpose);
    if($kind===SoftwareLicensePolicy::MASTER){
        if(strlen($purpose)<10||strlen($purpose)>200)throw new InvalidArgumentException('Master license purpose is required (10–200 characters).');
        $owner=staff_owner($masterPassword??'');
        if((int)$owner['id']!==$issuer)throw new RuntimeException('Master license issuer must be the currently authenticated owner.');
    }elseif($purpose!==''){
        throw new InvalidArgumentException('Purpose is reserved for internal master licenses.');
    }
    if (!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId])) {
        throw new InvalidArgumentException('Active customer is required.');
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku)) throw new InvalidArgumentException('Unknown software SKU.');
    $product=one('SELECT id,family FROM '.table('catalog_products').' WHERE sku=?',[$sku]);
    $legacy=preg_match('/^(?:WEBSITE|SOFTWARE|APP)-[A-Z0-9_-]+$/D',$sku)===1;
    if(!$product||(($product['family']??'')!=='software'&&!$legacy)){
        throw new InvalidArgumentException('Choose a registered software catalog product.');
    }
    $effectivePolicy=$purchasePolicy===null
        ? software_product_policy_purchase_snapshot(software_product_policy_for_sku($sku))
        : software_product_policy_purchase_snapshot($purchasePolicy);
    if($kind===SoftwareLicensePolicy::MASTER){
        // Internal master keys are owner-controlled QA/release keys. They remain
        // product-specific but are not constrained by customer update windows
        // or installation-seat policy.
        $effectivePolicy['update_months']=0;
        $effectivePolicy['max_installations']=25;
    }
    if($kind!==SoftwareLicensePolicy::MASTER
        &&!software_dependencies_satisfied($clientId,$effectivePolicy['dependency_skus'])){
        throw new RuntimeException('Required software dependencies are not currently licensed to this customer.');
    }
    $policy=software_entitlement_policy_snapshot($sku,$metadata['expires_at'],$recurring,$issuedAt,$effectivePolicy);
    if (!db()->inTransaction()) throw new LogicException('Issue grants in the enclosing locked transaction.');
    $key=license_create($clientId,$sku,$issuer,$kind==='master');
    $license=one('SELECT id FROM '.table('website_licenses').' WHERE license_key=?',[$key]);
    if (!$license) throw new RuntimeException('License creation failed.');
    $id=(int)$license['id'];
    query('INSERT INTO '.table('software_entitlements').' (license_id,license_type,licensee,expires_at,updates_until,max_installations,policy_revision,issued_by,purpose,invoice_id,unit_number,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$id,$kind,$metadata['licensee'],$metadata['expires_at'],$policy['updates_until'],$policy['max_installations'],
         $policy['policy_revision'],$issuer,$purpose,$invoice,$unit,$issuedAt->format('Y-m-d H:i:s')]);
    if ($metadata['domain']!==null) {
        query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at) VALUES (?,?,1,?)',
            [$id,$metadata['domain'],gmdate('Y-m-d H:i:s')]);
    }
    license_audit($issuer,'software.issue','Software license '.$id.' issued as '.$kind.' for SKU '.$sku.
        ($kind===SoftwareLicensePolicy::MASTER?' purpose: '.$purpose:''));
    return ['id'=>$id,'key'=>$key,'type'=>$kind,'updates_until'=>$policy['updates_until'],
        'max_installations'=>$policy['max_installations'],'policy_revision'=>$policy['policy_revision']];
}
/**
 * Convert one unlinked trial/temporary grant into a verified paid entitlement.
 * The original key, issuer, creation timestamp and domain history are retained.
 * Ambiguous matches stop for owner review instead of choosing a license.
 */
function software_grant_upgrade_existing(int $clientId,string $sku,string $domain,string $targetKind,
    ?string $targetExpires,int $invoice,int $unit,bool $recurring,array $purchasePolicy):?array{
    if(!db()->inTransaction())throw new LogicException('Paid grant upgrade requires the enclosing fulfillment transaction.');
    if($invoice<1||$unit<1||!in_array($targetKind,[SoftwareLicensePolicy::STANDARD,SoftwareLicensePolicy::TEMPORARY],true)){
        throw new InvalidArgumentException('Invalid paid software upgrade target.');
    }
    $domain=license_canonical_domain($domain);
    $candidates=all(
        'SELECT l.id,l.license_key,e.license_type,e.expires_at,e.issued_by,e.created_at '.
        'FROM '.table('website_licenses').' l '.
        'JOIN '.table('software_entitlements').' e ON e.license_id=l.id '.
        'JOIN '.table('license_domains').' d ON d.license_id=l.id '.
        'WHERE l.customer_id=? AND l.product_id=? AND l.is_active=1 AND l.is_master=0 '.
        'AND e.invoice_id IS NULL AND e.unit_number IS NULL AND e.license_type IN (?,?) '.
        'AND d.domain=? AND d.is_active=1 ORDER BY l.id FOR UPDATE',
        [$clientId,$sku,SoftwareLicensePolicy::TRIAL,SoftwareLicensePolicy::TEMPORARY,$domain]
    );
    if(count($candidates)>1)throw new RuntimeException('Multiple trial or temporary licenses match this paid order. Owner review is required.');
    if(!$candidates)return null;
    $candidate=$candidates[0];$licenseId=(int)$candidate['id'];
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $policy=software_entitlement_policy_snapshot($sku,$targetExpires,$recurring,$now,$purchasePolicy);
    $active=(int)query('SELECT COUNT(*) FROM '.table('license_domains').' WHERE license_id=? AND is_active=1',[$licenseId])->fetchColumn();
    if($active>(int)$policy['max_installations']){
        throw new RuntimeException('Existing trial or temporary license exceeds the purchased installation limit. Owner review is required.');
    }
    $changed=query(
        'UPDATE '.table('software_entitlements').' SET license_type=?,expires_at=?,updates_until=?,max_installations=?,policy_revision=?,invoice_id=?,unit_number=? '.
        'WHERE license_id=? AND invoice_id IS NULL AND license_type IN (?,?)',
        [$targetKind,$targetExpires,$policy['updates_until'],$policy['max_installations'],$policy['policy_revision'],
         $invoice,$unit,$licenseId,SoftwareLicensePolicy::TRIAL,SoftwareLicensePolicy::TEMPORARY]
    )->rowCount();
    if($changed!==1)throw new RuntimeException('Software upgrade candidate changed during fulfillment.');
    query('UPDATE '.table('website_licenses').' SET updated_at=? WHERE id=?',[$now->format('Y-m-d H:i:s'),$licenseId]);
    license_audit(0,'software.upgrade',
        'Software license '.$licenseId.' converted '.$candidate['license_type'].' -> '.$targetKind.
        ' via invoice '.$invoice.' unit '.$unit.'; original expiry '.($candidate['expires_at']??'none').
        '; original issuer '.(int)$candidate['issued_by']);
    return [
        'id'=>$licenseId,'key'=>(string)$candidate['license_key'],'type'=>$targetKind,'upgraded'=>true,
        'updates_until'=>$policy['updates_until'],'max_installations'=>$policy['max_installations'],
        'policy_revision'=>$policy['policy_revision']
    ];
}

/** Whether a client currently holds a valid ordinary entitlement for a product SKU. */
function software_customer_has_valid_product(int $clientId,string $sku):bool {
    if($clientId<1||!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku))return false;
    $licenses=all('SELECT * FROM '.table('website_licenses').' WHERE customer_id=? AND product_id=? AND is_active=1 AND is_master=0 ORDER BY id LIMIT 100',
        [$clientId,$sku]);
    foreach($licenses as $license){
        $domains=all('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 ORDER BY id LIMIT 25',
            [$license['id']]);
        foreach($domains as $domain){
            if(is_string($domain['domain']??null)&&software_grant_verdict($license,$domain['domain']))return true;
        }
    }
    return false;
}

function software_dependencies_satisfied(int $clientId,array $dependencySkus):bool {
    if(!array_is_list($dependencySkus)||count($dependencySkus)>25)return false;
    foreach($dependencySkus as $sku){
        if(!is_string($sku)||!software_customer_has_valid_product($clientId,$sku))return false;
    }
    return true;
}

/** New software entitlements must identify the product being activated.
 *
 * Legacy Website Core licenses predate product-bound validation and retain
 * their existing {license_key, domain} wire contract. A v3 software key is
 * commercially product-specific, however, so allowing its caller to omit the
 * product would let the same valid key/domain authorize unrelated software.
 */
function software_grant_requires_product_identity(array $license):bool {
    $licenseId=(int)($license['id']??0);
    if($licenseId<1||!software_commerce_ready())return false;
    return (bool)one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$licenseId]);
}

/** Classify a mutable license without letting orphan software rows fall back to legacy behavior. */
function software_grant_mutation_entitlement(int $licenseId):?array{
    if($licenseId<1)throw new InvalidArgumentException('License not found.');
    if(!software_commerce_ready()){
        $present=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].'software_entitlements']);
        if($present)throw new RuntimeException('Software licensing storage state is inconsistent.');
        return null;
    }
    $entitlement=one('SELECT license_id,max_installations FROM '.table('software_entitlements').' WHERE license_id=?',[$licenseId]);
    if($entitlement)return $entitlement;
    $legacy=one('SELECT product_id FROM '.table('website_licenses').' WHERE id=?',[$licenseId]);
    if(!$legacy)throw new InvalidArgumentException('License not found.');
    $productId=(string)($legacy['product_id']??'');
    $softwareProduct=one('SELECT id FROM '.table('catalog_products').' WHERE sku=? AND family=?',[$productId,'software']);
    $issued=one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id DESC LIMIT 1',
        ['license.software.issue','Software license '.$licenseId.' issued as %']);
    if($softwareProduct||$issued)throw new RuntimeException('Software entitlement history is incomplete.');
    return null;
}

/** Whether a recent standalone deactivation makes a different-domain add a cooldown bypass. */
function software_grant_recent_deactivation_blocks_add(int $licenseId,string $newDomain):bool{
    $hours=software_domain_change_cooldown_hours();
    if($hours===0)return false;
    $last=one('SELECT id,detail,created_at FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id DESC LIMIT 1',
        ['license.domain.deactivate','License '.$licenseId.' domain %']);
    if(!$last||!is_string($last['created_at']??null)||!is_string($last['detail']??null))return false;
    $stamp=strtotime($last['created_at'].' UTC');
    if($stamp===false||$stamp<=time()-($hours*3600))return false;
    $prefix='License '.$licenseId.' domain ';
    $old=str_starts_with($last['detail'],$prefix)?substr($last['detail'],strlen($prefix)):'';
    if($old!==''&&hash_equals($old,$newDomain))return false; // accidental same-domain reactivation.
    $replace=one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id DESC LIMIT 1',
        ['license.domain.replace','License '.$licenseId.' %']);
    // These mutations share the license lock. Audit IDs retain their order
    // when a later standalone deactivation has the same second timestamp.
    if($replace&&(int)$replace['id']>(int)$last['id'])return false;
    return true;
}

/** Staff domain mutation path: preserve legacy behavior, but fail closed for software orphans.
 * Staff intentionally bypasses the customer cooldown for owner-reviewed support changes.
 */
function software_grant_staff_domain_mode(int $licenseId):bool{
    $entitlement=software_grant_mutation_entitlement($licenseId);
    if($entitlement===null)return false;
    $license=one('SELECT is_master FROM '.table('website_licenses').' WHERE id=?',[$licenseId]);
    if(!$license)throw new InvalidArgumentException('License not found.');
    if((int)$license['is_master']===1)throw new InvalidArgumentException('Master software licenses do not use installation domains.');
    return true;
}
function software_grant_staff_register_domain(int $licenseId,string $domain,int $actor):bool{
    $software=software_grant_staff_domain_mode($licenseId);
    license_register_domain($licenseId,$domain,$actor);
    return $software;
}
function software_grant_staff_deactivate_domain(int $licenseId,string $domain,int $actor):bool{
    $software=software_grant_staff_domain_mode($licenseId);
    license_deactivate_domain($licenseId,$domain,$actor);
    return $software;
}
function software_grant_staff_replace_domain(int $licenseId,string $oldDomain,string $newDomain,int $actor):bool{
    $software=software_grant_staff_domain_mode($licenseId);
    license_replace_domain($licenseId,$oldDomain,$newDomain,$actor);
    return $software;
}

/** Distinguish reversible suspension from owner revocation while reusing the existing active flag for enforcement. */
function software_grant_lifecycle(int $licenseId):array{
    $entitlement=software_grant_mutation_entitlement($licenseId);
    if($entitlement===null)throw new InvalidArgumentException('Software entitlement not found.');
    $license=one('SELECT is_active FROM '.table('website_licenses').' WHERE id=?',[$licenseId]);
    if(!$license)throw new InvalidArgumentException('License not found.');
    if((int)$license['is_active']===1)return ['state'=>'active','reason'=>'','changed_at'=>null,'changed_by'=>null];
    $event=one(
        'SELECT action,detail,created_at,user_id FROM '.table('audit').
        ' WHERE action IN (?,?,?) AND (detail LIKE ? OR detail LIKE ?) ORDER BY id DESC LIMIT 1',
        ['license.software.suspend','license.software.revoke','license.deactivate',
         'Software license '.$licenseId.' %','License '.$licenseId.' %']
    );
    $state=($event['action']??'')==='license.software.revoke'?'revoked':'suspended';
    return [
        'state'=>$state,
        'reason'=>(string)($event['detail']??''),
        'changed_at'=>$event['created_at']??null,
        'changed_by'=>isset($event['user_id'])?(int)$event['user_id']:null
    ];
}

/** Owner lifecycle control. Revocation/restoration require a current owner session; suspension/resume preserve key/domain history. */
function software_grant_lifecycle_transition(int $licenseId,string $action,string $reason,int $actor,
    #[\SensitiveParameter]?string $ownerPassword=null):array{
    if(support_current())throw new RuntimeException('Software license lifecycle changes are unavailable during support access.');
    if(!in_array($action,['suspend','resume','revoke','restore'],true)){
        throw new InvalidArgumentException('Invalid software license lifecycle action.');
    }
    $reason=trim($reason);
    if(strlen($reason)<5||strlen($reason)>200){
        throw new InvalidArgumentException('Enter a lifecycle reason between 5 and 200 characters.');
    }
    $staff=staff_require('staff.manage');
    if((int)$staff['id']!==$actor)throw new RuntimeException('Software lifecycle actor does not match the current owner.');
    if(in_array($action,['revoke','restore'],true)){
        $owner=staff_owner($ownerPassword??'');
        if((int)$owner['id']!==$actor)throw new RuntimeException('Software lifecycle actor does not match the currently authenticated owner.');
    }
    $result=[];
    license_transaction(function()use($licenseId,$action,$reason,$actor,&$result):void{
        license_lock($licenseId);
        $current=software_grant_lifecycle($licenseId);
        $allowed=match($action){
            'suspend'=>$current['state']==='active',
            'resume'=>$current['state']==='suspended',
            'revoke'=>in_array($current['state'],['active','suspended'],true),
            'restore'=>$current['state']==='revoked',
            default=>false
        };
        if(!$allowed)throw new RuntimeException('Software license cannot transition from '.$current['state'].' using '.$action.'.');
        $active=in_array($action,['resume','restore'],true)?1:0;
        query('UPDATE '.table('website_licenses').' SET is_active=?,updated_at=? WHERE id=?',
            [$active,gmdate('Y-m-d H:i:s'),$licenseId]);
        $past=match($action){'suspend'=>'suspended','resume'=>'resumed','revoke'=>'revoked','restore'=>'restored'};
        license_audit($actor,'software.'.$action,'Software license '.$licenseId.' '.$past.': '.$reason);
        $result=['state'=>match($action){'suspend'=>'suspended','revoke'=>'revoked',default=>'active'},'reason'=>$reason];
    });
    return $result;
}

function software_grant_require_customer_active(int $licenseId):void{
    $row=one(
        'SELECT l.is_active,e.expires_at FROM '.table('website_licenses').' l JOIN '.table('software_entitlements').' e ON e.license_id=l.id WHERE l.id=?',
        [$licenseId]
    );
    if(!$row||(int)$row['is_active']!==1)throw new RuntimeException('This software license is suspended or revoked.');
    if($row['expires_at']!==null&&(string)$row['expires_at']<=gmdate('Y-m-d H:i:s')){
        throw new RuntimeException('This software license has expired.');
    }
}

function software_grant_require_client_owner(int $licenseId,int $clientId):void{
    if($licenseId<1||$clientId<1)throw new InvalidArgumentException('License not found.');
    $license=one('SELECT customer_id,is_master FROM '.table('website_licenses').' WHERE id=?',[$licenseId]);
    if(!$license||(int)$license['customer_id']!==$clientId||(int)$license['is_master']===1){
        throw new InvalidArgumentException('License not found.');
    }
}

/** Client-safe add/reactivation path that cannot bypass software replacement cooldown. */
function software_grant_register_domain(int $licenseId,string $domain,int $actor):bool{
    software_grant_require_client_owner($licenseId,$actor);
    $entitlement=software_grant_mutation_entitlement($licenseId);
    if($entitlement===null){license_register_domain($licenseId,$domain,$actor);return false;}
    $domain=license_canonical_domain($domain);
    license_transaction(function()use($licenseId,$domain,$actor):void{
        license_lock($licenseId);
        software_grant_require_customer_active($licenseId);
        $existing=one('SELECT is_active FROM '.table('license_domains').' WHERE license_id=? AND domain=?',[$licenseId,$domain]);
        if(!$existing||!(int)$existing['is_active']){
            if(software_grant_recent_deactivation_blocks_add($licenseId,$domain)){
                throw new RuntimeException('This license recently changed installations. Try again after the owner-configured cooldown.');
            }
        }
        license_register_domain_tx($licenseId,$domain,$actor);
    });
    return true;
}

/** Client-safe deactivation path that refuses incomplete software entitlement history. */
function software_grant_deactivate_domain(int $licenseId,string $domain,int $actor):bool{
    software_grant_require_client_owner($licenseId,$actor);
    $entitlement=software_grant_mutation_entitlement($licenseId);
    if($entitlement===null){license_deactivate_domain($licenseId,$domain,$actor);return false;}
    $domain=license_canonical_domain($domain);
    license_transaction(function()use($licenseId,$domain,$actor):void{
        license_lock($licenseId);
        software_grant_require_customer_active($licenseId);
        if(!one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',[$licenseId,$domain])){
            throw new InvalidArgumentException('Current domain is not active on this license.');
        }
        license_deactivate_domain_tx($licenseId,$domain,$actor);
    });
    return true;
}

/** Owner-configured customer domain-move cooldown; zero disables the delay. */
function software_domain_change_cooldown_hours():int {
    $raw=(string)setting('software_domain_change_cooldown_hours','24');
    if(!preg_match('/^(?:0|[1-9][0-9]{0,2})$/D',$raw))return 24;
    return min(720,(int)$raw);
}

/** Customer software-domain replacement with a serialized per-license cooldown. */
function software_grant_replace_domain(int $licenseId,string $oldDomain,string $newDomain,int $actor):void {
    software_grant_require_client_owner($licenseId,$actor);
    $entitlement=software_grant_mutation_entitlement($licenseId);
    if($entitlement===null){
        license_replace_domain($licenseId,$oldDomain,$newDomain,$actor);
        return;
    }
    $oldDomain=license_canonical_domain($oldDomain);
    $newDomain=license_canonical_domain($newDomain);
    if($oldDomain===$newDomain)throw new InvalidArgumentException('The new domain is the same as the current one.');
    license_transaction(function()use($licenseId,$oldDomain,$newDomain,$actor):void{
        license_lock($licenseId);
        software_grant_require_customer_active($licenseId);
        if(software_grant_recent_deactivation_blocks_add($licenseId,$newDomain)){
            throw new RuntimeException('This license recently changed installations. Try again after the owner-configured cooldown.');
        }
        $hours=software_domain_change_cooldown_hours();
        if($hours>0){
            $last=one('SELECT created_at FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id DESC LIMIT 1',
                ['license.domain.replace','License '.$licenseId.' %']);
            if($last&&is_string($last['created_at']??null)){
                $stamp=strtotime($last['created_at'].' UTC');
                if($stamp!==false&&$stamp>time()-($hours*3600)){
                    throw new RuntimeException('This license recently changed domains. Try again after the owner-configured cooldown.');
                }
            }
        }
        if(!one('SELECT id FROM '.table('license_domains').' WHERE license_id=? AND domain=? AND is_active=1',[$licenseId,$oldDomain])){
            throw new InvalidArgumentException('Current domain is not active on this license.');
        }
        license_deactivate_domain_tx($licenseId,$oldDomain,$actor);
        license_register_domain_tx($licenseId,$newDomain,$actor);
        license_audit($actor,'domain.replace','License '.$licenseId.' '.$oldDomain.' -> '.$newDomain);
    });
}

/** Runtime type and expiration guard for the existing /api/validate.php path. */
function software_grant_verdict(array $license,string $domain):bool {
    if (!software_commerce_ready()) {
        // A genuine pre-commerce installation has no entitlement table, so
        // existing v3 keys remain compatible. If the table exists but the
        // readiness marker disappeared or setup is incomplete, fail
        // inconclusively through the API rather than silently authorizing.
        $present=one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].'software_entitlements']);
        if($present)throw new RuntimeException('Software licensing storage state is inconsistent.');
        return true;
    }
    $row=one('SELECT * FROM '.table('software_entitlements').' WHERE license_id=?',[(int)$license['id']]);
    if (!$row) {
        $productId=(string)($license['product_id']??'');
        if($productId===''){
            $stored=one('SELECT product_id FROM '.table('website_licenses').' WHERE id=?',[(int)$license['id']]);
            $productId=(string)($stored['product_id']??'');
        }
        $softwareProduct=one('SELECT id FROM '.table('catalog_products').' WHERE sku=? AND family=?',
            [$productId,'software']);
        $issued=one('SELECT id FROM '.table('audit').' WHERE action=? AND detail LIKE ? ORDER BY id DESC LIMIT 1',
            ['license.software.issue','Software license '.(int)$license['id'].' issued as %']);
        if($softwareProduct||$issued){
            throw new RuntimeException('Software entitlement history is incomplete.');
        }
        return true; // genuine pre-commerce v3 licenses are not migrated implicitly
    }
    $kind=SoftwareLicensePolicy::type((string)($row['license_type']??''));
    $isMaster=(int)($license['is_master']??0)===1;
    if(($kind===SoftwareLicensePolicy::MASTER)!==$isMaster){
        throw new RuntimeException('Software entitlement type and master flag disagree.');
    }
    if($kind===SoftwareLicensePolicy::STANDARD&&$row['expires_at']!==null){
        throw new RuntimeException('Perpetual software entitlement has unexpected expiry metadata.');
    }
    if(in_array($kind,[SoftwareLicensePolicy::TRIAL,SoftwareLicensePolicy::TEMPORARY],true)
        &&$row['expires_at']===null){
        throw new RuntimeException('Time-limited software entitlement is missing expiry metadata.');
    }
    $storedLicense=one('SELECT customer_id,product_id FROM '.table('website_licenses').' WHERE id=?',[(int)$license['id']]);
    if(!$storedLicense)throw new RuntimeException('Software license identity is missing.');
    $invoiceId=$row['invoice_id']===null?null:(int)$row['invoice_id'];
    $unit=$row['unit_number']===null?null:(int)$row['unit_number'];
    if(($invoiceId===null)!==($unit===null)){
        throw new RuntimeException('Software entitlement order-line identity is incomplete.');
    }
    if($invoiceId!==null){
        $order=one('SELECT o.client_id,o.sku,o.quantity,i.status AS invoice_status FROM '.table('software_orders').' o JOIN '.table('studio_invoices').' i ON i.id=o.invoice_id WHERE o.invoice_id=?',[$invoiceId]);
        if(!$order||(int)$order['client_id']!==(int)$storedLicense['customer_id']
            ||(string)$order['sku']!==(string)$storedLicense['product_id']
            ||$unit<1||$unit>(int)$order['quantity']
            ||(string)$order['invoice_status']!=='paid'){
            throw new RuntimeException('Software entitlement does not match a verified paid order line.');
        }
    }
    if((int)$license['is_master']!==1){
        $limit=(int)($row['max_installations']??0);
        if($limit<1||$limit>25)throw new RuntimeException('Software installation policy is invalid.');
        $active=(int)query('SELECT COUNT(*) FROM '.table('license_domains').' WHERE license_id=? AND is_active=1',
            [(int)$license['id']])->fetchColumn();
        if($active>$limit)return false;
    }
    $bound=one('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 AND domain=?',
        [(int)$license['id'],license_canonical_domain($domain)]);
    return SoftwareLicensePolicy::authorized([
        'type'=>$row['license_type'],'active'=>(int)$license['is_active']===1,
        'domain'=>$bound['domain']??null,'expires_at'=>$row['expires_at'],
        'internal_only'=>(int)$license['is_master']===1
    ],$domain,new DateTimeImmutable('now',new DateTimeZone('UTC')));
}

/** Optional installed-version check for newer clients; legacy clients may omit it. */
function software_grant_version_verdict(array $license,string $version):?bool {
    if(!preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,39}$/D',$version)){
        throw new InvalidArgumentException('Invalid software version format.');
    }
    if(!software_commerce_ready())return true;
    $entitlement=one('SELECT updates_until FROM '.table('software_entitlements').' WHERE license_id=?',[(int)$license['id']]);
    if(!$entitlement)return true; // genuine pre-commerce license, already classified by software_grant_verdict()
    $sku=(string)($license['product_id']??'');
    if($sku===''){
        $stored=one('SELECT product_id FROM '.table('website_licenses').' WHERE id=?',[(int)$license['id']]);
        $sku=(string)($stored['product_id']??'');
    }
    if((int)($license['is_master']??0)===1){
        return one('SELECT id FROM '.table('software_assets').' WHERE sku=? AND version=? LIMIT 1',[$sku,$version])
            ? true : null;
    }
    $asset=one('SELECT released_at FROM '.table('software_assets').' WHERE sku=? AND version=? AND is_published=1 AND released_at IS NOT NULL',
        [$sku,$version]);
    if(!$asset)return null;
    return $entitlement['updates_until']===null||(string)$asset['released_at']<=(string)$entitlement['updates_until'];
}
