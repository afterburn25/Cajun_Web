<?php
/** Native customer domain cooldown regressions in a fresh disposable schema. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
define('CWP_BOOT',true);require __DIR__.'/../site/includes/bootstrap.php';
require CWP_CODE_ROOT.'/database/schema/base.php';
require_once CWP_CODE_ROOT.'/app/licenses/software_grants.php';
$prefix=getenv('CWP_COOLDOWN_TEST_PREFIX')?:'sdc_';
if(!preg_match('/\Asdc_[a-z0-9_]{0,8}\z/',$prefix))exit(2);
$GLOBALS['cwp_config']=['db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1',
    'port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test',
    'user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>$prefix]];
$checks=[];
function cooldown_ok(string $name,bool $passed):void{
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException($name);
}
function cooldown_denied(callable $work):bool{
    try{$work();}catch(InvalidArgumentException|RuntimeException $error){
        if($error instanceof PDOException)throw $error;
        return str_contains($error->getMessage(),'cooldown');
    }
    return false;
}
function cooldown_snapshot(int $id):string{
    return json_encode([all('SELECT * FROM '.table('license_domains').' WHERE license_id=? ORDER BY id',[$id]),
        all('SELECT * FROM '.table('audit').' ORDER BY id')],JSON_THROW_ON_ERROR);
}
try{
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$prefix.'settings'])){
        throw new RuntimeException('Disposable cooldown prefix already exists; use a fresh test prefix.');
    }
    foreach(schema_statements($prefix)+software_commerce_schema($prefix) as $sql)db()->exec($sql);
    foreach(['license_schema_version'=>'3','software_commerce_version'=>'3','software_domain_change_cooldown_hours'=>'24'] as $key=>$value)save_setting($key,$value);
    $now=gmdate('Y-m-d H:i:s');
    query('INSERT INTO '.table('studio_clients').' (name,email,auth_version,created_at) VALUES (?,?,?,?)',
        ['Synthetic cooldown buyer','cooldown@example.invalid',str_repeat('a',32),$now]);
    $key=license_create(1,'SOFTWARE-COOLDOWN',1,false,null);$id=(int)license_get($key)['id'];
    query('INSERT INTO '.table('software_entitlements').' (license_id,license_type,licensee,expires_at,updates_until,max_installations,policy_revision,issued_by,purpose,invoice_id,unit_number,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$id,'standard','Synthetic cooldown buyer',null,null,1,0,1,'',null,null,$now]);
    license_register_domain($id,'original.example',1);
    cooldown_ok('Native fixture has prepared commerce and a one-installation entitlement',software_commerce_ready()&&license_domain_limit($id)===1);

    software_grant_replace_domain($id,'original.example','second.example',1);
    $before=cooldown_snapshot($id);
    cooldown_ok('Immediate repeated replacement remains denied without mutation',
        cooldown_denied(fn()=>software_grant_replace_domain($id,'second.example','third.example',1))&&cooldown_snapshot($id)===$before);
    software_grant_deactivate_domain($id,'second.example',1);
    $replace=one('SELECT id FROM '.table('audit').' WHERE action=? ORDER BY id DESC LIMIT 1',['license.domain.replace']);
    $deactivate=one('SELECT id FROM '.table('audit').' WHERE action=? ORDER BY id DESC LIMIT 1',['license.domain.deactivate']);
    // Force the real same-second collision deterministically, even on a slow runner.
    $sameSecond=gmdate('Y-m-d H:i:s');
    query('UPDATE '.table('audit').' SET created_at=? WHERE id IN (?,?)',[$sameSecond,$replace['id'],$deactivate['id']]);
    cooldown_ok('Standalone deactivation follows replacement despite identical timestamps',(int)$deactivate['id']>(int)$replace['id']);
    cooldown_ok('Same-second standalone deactivation retains the configured cooldown',software_grant_recent_deactivation_blocks_add($id,'third.example'));
    $before=cooldown_snapshot($id);
    cooldown_ok('Replace then deactivate cannot add a different domain within the cooldown',
        cooldown_denied(fn()=>software_grant_register_domain($id,'third.example',1))&&cooldown_snapshot($id)===$before);
    software_grant_register_domain($id,'second.example',1);
    cooldown_ok('Accidental same-domain reactivation remains available',license_domain_authorized(license_get($key),'second.example'));

    save_setting('software_domain_change_cooldown_hours','0');
    software_grant_deactivate_domain($id,'second.example',1);software_grant_register_domain($id,'third.example',1);
    cooldown_ok('Owner-disabled cooldown still permits the customer to change domains',license_domain_authorized(license_get($key),'third.example'));
    save_setting('software_domain_change_cooldown_hours','24');
    software_grant_deactivate_domain($id,'third.example',1);
    $deactivate=one('SELECT id FROM '.table('audit').' WHERE action=? ORDER BY id DESC LIMIT 1',['license.domain.deactivate']);
    query('UPDATE '.table('audit').' SET created_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-172800),$deactivate['id']]);
    software_grant_register_domain($id,'fourth.example',1);
    cooldown_ok('Expired cooldown still permits a new installation domain',license_domain_authorized(license_get($key),'fourth.example'));
    software_grant_deactivate_domain($id,'fourth.example',1);
    cooldown_ok('Later standalone deactivation remains blocked with an earlier replacement in history',
        cooldown_denied(fn()=>software_grant_register_domain($id,'fifth.example',1)));
    software_grant_register_domain($id,'fourth.example',1);
    software_grant_staff_replace_domain($id,'fourth.example','owner-reviewed.example',1);
    cooldown_ok('Owner-reviewed replacement still bypasses the customer cooldown',license_domain_authorized(license_get($key),'owner-reviewed.example'));
    cooldown_ok('Replacement-internal deactivation does not impose an extra add restriction',
        !software_grant_recent_deactivation_blocks_add($id,'unused-seat.example'));
    cooldown_ok('Domain changes preserve the same license and one active installation',
        (int)license_get($key)['customer_id']===1&&(int)license_get($key)['is_active']===1
        &&all('SELECT domain FROM '.table('license_domains').' WHERE license_id=? AND is_active=1',[$id])===[['domain'=>'owner-reviewed.example']]);
}catch(Throwable $error){$checks[]=['name'=>'Unexpected failure: '.$error->getMessage(),'passed'=>false];}
$passed=!in_array(false,array_column($checks,'passed'),true);
echo json_encode(['passed'=>$passed,'php'=>PHP_VERSION,'database'=>db()->getAttribute(PDO::ATTR_SERVER_VERSION),
    'scope'=>'Actual PDO domain mutation services and audit ordering; synthetic isolated data, no gateway or full installed acceptance',
    'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
exit($passed?0:1);
