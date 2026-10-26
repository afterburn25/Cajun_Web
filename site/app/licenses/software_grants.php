<?php
/** Idempotent order-line license issuance. Do not invoke before verified payment. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/licenses/service.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_license_policy.php';
require_once CWP_CODE_ROOT.'/database/migrations/software_commerce_migrate.php';

function software_grant_create(int $clientId, string $sku, string $kind, string $name,
    ?string $domain, ?string $expires, int $issuer=0, ?int $invoice=null, ?int $unit=null, string $purpose=''):array {
    if (!software_commerce_ready()) throw new RuntimeException('Software licensing is not prepared.');
    $metadata=SoftwareLicensePolicy::issue($kind,$name,$domain,$expires,$issuer>0,
        new DateTimeImmutable('now',new DateTimeZone('UTC')));
    $purpose=trim($purpose);
    if($kind===SoftwareLicensePolicy::MASTER){
        if(strlen($purpose)<10||strlen($purpose)>200)throw new InvalidArgumentException('Master license purpose is required (10–200 characters).');
    }elseif($purpose!==''){
        throw new InvalidArgumentException('Purpose is reserved for internal master licenses.');
    }
    if (!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId])) {
        throw new InvalidArgumentException('Active customer is required.');
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,59}$/D',$sku)) throw new InvalidArgumentException('Unknown software SKU.');
    if (!db()->inTransaction()) throw new LogicException('Issue grants in the enclosing locked transaction.');
    $key=license_create($clientId,$sku,$issuer,$kind==='master');
    $license=one('SELECT id FROM '.table('website_licenses').' WHERE license_key=?',[$key]);
    if (!$license) throw new RuntimeException('License creation failed.');
    $id=(int)$license['id'];
    query('INSERT INTO '.table('software_entitlements').' (license_id,license_type,licensee,expires_at,issued_by,purpose,invoice_id,unit_number,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
        [$id,$kind,$metadata['licensee'],$metadata['expires_at'],$issuer,$purpose,$invoice,$unit,gmdate('Y-m-d H:i:s')]);
    if ($metadata['domain']!==null) {
        query('INSERT INTO '.table('license_domains').' (license_id,domain,is_active,registered_at) VALUES (?,?,1,?)',
            [$id,$metadata['domain'],gmdate('Y-m-d H:i:s')]);
    }
    license_audit($issuer,'software.issue','Software license '.$id.' issued as '.$kind.' for SKU '.$sku.
        ($kind===SoftwareLicensePolicy::MASTER?' purpose: '.$purpose:''));
    return ['id'=>$id,'key'=>$key,'type'=>$kind];
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
    if (!$row) return true; // legacy product licenses are not migrated implicitly
    $bound=one('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1 AND domain=?',
        [(int)$license['id'],license_canonical_domain($domain)]);
    return SoftwareLicensePolicy::authorized([
        'type'=>$row['license_type'],'active'=>(int)$license['is_active']===1,
        'domain'=>$bound['domain']??null,'expires_at'=>$row['expires_at'],
        'internal_only'=>(int)$license['is_master']===1
    ],$domain,new DateTimeImmutable('now',new DateTimeZone('UTC')));
}
