<?php
/** Independent regression review of d830e91; synthetic native fixture only. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'||!defined('CWP_BOOT'))exit(2);
$reviewStart=count($checks);
$reviewRows=static fn():array=>[
    all('SELECT * FROM '.table('provider_package_links').' ORDER BY id'),
    all('SELECT * FROM '.table('provider_package_link_events').' ORDER BY id'),
    all('SELECT * FROM '.table('audit').' ORDER BY id'),
];
$before=$reviewRows();
pi_deny('Independent: current revision cannot approve the previous observation',fn()=>provider_inventory_link($id,201,1,6,$password));
pi_ok('Independent: stale observation refusal preserves mappings and audit',$reviewRows()===$before);
pi_aged();$scripted=[new \CajunWebPro\Foundation\Provider\TransportResponse(200,[],json_encode([pi_item(201,'approved-context.example')]))];
pi_ok('Independent: fresh approved-context observation completes',pi_refresh($id,null,null,6)==='complete');pi_cool();
provider_inventory_link($id,201,1,6,$password);$approved=provider_inventory_links($id)[0];
pi_ok('Independent: correct client can read approved package',provider_inventory_client_services(1)[0]['package']['name']==='approved-context.example'&&provider_inventory_client_services(2)===[]);
provider_settings_save($id,6,'Independent context replacement',true,'INDEPENDENT_REPLACEMENT_KEY',$password);$secret='INDEPENDENT_REPLACEMENT_KEY';
$hidden=provider_inventory_client_services(1);
pi_ok('Independent: old package is hidden immediately before a replacement refresh',$hidden[0]['context_changed']&&$hidden[0]['package']===null&&$hidden[0]['observed_at']===null);
$before=$reviewRows();pi_deny('Independent: reapproval requires a current observation',fn()=>provider_inventory_relink($approved['id'],7,$password));
pi_ok('Independent: refused reapproval leaves old approval intact',$reviewRows()===$before);
$scripted=[new \CajunWebPro\Foundation\Provider\TransportResponse(200,[],json_encode([pi_item(201,'replacement-private.example')]))];
pi_ok('Independent: replacement refresh with the same package identity completes',pi_refresh($id,null,null,7)==='complete');pi_cool();
$hidden=provider_inventory_client_services(1);
pi_ok('Independent: replacement data cannot flow through the old mapping',$hidden[0]['context_changed']&&$hidden[0]['package']===null&&!str_contains(json_encode($hidden),'replacement-private.example')&&provider_inventory_links($id)[0]['key_fingerprint']===$approved['key_fingerprint']);
$before=$reviewRows();pi_actor(2);
pi_deny('Independent: administrator cannot reapprove a context change',fn()=>provider_inventory_relink($approved['id'],7,$password));pi_actor();
pi_deny('Independent: wrong password cannot reapprove a context change',fn()=>provider_inventory_relink($approved['id'],7,'wrong'));
pi_deny('Independent: stale form cannot reapprove a context change',fn()=>provider_inventory_relink($approved['id'],6,$password));
pi_ok('Independent: rejected approval attempts preserve events and mapping',$reviewRows()===$before);
pi_ok('Independent: audit failure rolls back context approval',pi_audit_failure('service_relinked',fn()=>provider_inventory_relink($approved['id'],7,$password))&&$reviewRows()===$before&&provider_inventory_client_services(1)[0]['package']===null);
provider_inventory_relink($approved['id'],7,$password);$reapproved=provider_inventory_links($id)[0];
pi_ok('Independent: explicit approval alone releases the replacement observation',$reapproved['id']===$approved['id']&&(int)$reapproved['client_id']===1&&$reapproved['key_fingerprint']!==$approved['key_fingerprint']&&provider_inventory_client_services(1)[0]['package']['name']==='replacement-private.example'&&provider_inventory_client_services(2)===[]);
pi_ok('Independent: reapproval adds one event and one audit row',pi_count('provider_package_link_events')===count($before[1])+1&&pi_count('audit')===count($before[2])+1);
provider_settings_save($id,7,'Independent same-key change',true,'',$password);
pi_ok('Independent: same-key configuration change also requires new approval',provider_inventory_client_services(1)[0]['context_changed']&&provider_inventory_client_services(1)[0]['package']===null);
$scripted=[new \CajunWebPro\Foundation\Provider\TransportResponse(200,[],json_encode([pi_item(999,'unrelated.example')]))];
pi_ok('Independent: current observation can honestly omit the mapped service',pi_refresh($id,null,null,8)==='complete');pi_cool();
$before=$reviewRows();pi_deny('Independent: omitted package cannot be reapproved',fn()=>provider_inventory_relink($approved['id'],8,$password));
pi_ok('Independent: absent reapproval preserves hidden mapping and audit',$reviewRows()===$before&&provider_inventory_client_services(1)[0]['package']===null);
// Reconstruct the preceding v1 layout while retaining all original v1 data.
$legacyRows=static function():array {
    $out=[];
    foreach(['provider_refresh_results','provider_package_links'] as $name){
        $rows=all('SELECT * FROM '.table($name).' ORDER BY id');
        foreach($rows as &$row){unset($row['key_fingerprint']);if($name==='provider_package_links')unset($row['profile_revision']);}unset($row);
        $out[$name]=$rows;
    }
    return $out;
};
$legacy=$legacyRows();
db()->exec('ALTER TABLE '.table('provider_refresh_results').' DROP COLUMN key_fingerprint');
db()->exec('ALTER TABLE '.table('provider_package_links').' DROP COLUMN key_fingerprint, DROP COLUMN profile_revision');
save_setting('provider_inventory_schema_version','1');
provider_inventory_migrate($password);
pi_ok('Independent: v1 upgrade preserves every preexisting result and mapping field',provider_inventory_ready()&&$legacyRows()===$legacy);
$legacyLink=provider_inventory_links($id)[0];$legacyView=provider_inventory_client_services(1);
pi_ok('Independent: migrated unverified context is hidden rather than adopted',$legacyLink['key_fingerprint']===''&&(int)$legacyLink['profile_revision']===0&&$legacyView[0]['context_changed']&&$legacyView[0]['package']===null);
$before=$reviewRows();provider_inventory_migrate($password);
pi_ok('Independent: repeated schema preparation preserves mappings and history',array_slice($reviewRows(),0,2)===array_slice($before,0,2)&&$legacyRows()===$legacy);
$reviewChecks=array_slice($checks,$reviewStart);
file_put_contents(__DIR__.'/../qa/provider-inventory-independent-results.json',json_encode(['source'=>'d830e9176cd21e8831b893adc8cf00f59ab94ea2','passed'=>true,'scope'=>'Independent synthetic native correction, reapproval rollback, context isolation and prior-schema migration controls; no HTTP/live provider acceptance','checks'=>$reviewChecks],JSON_PRETTY_PRINT));
