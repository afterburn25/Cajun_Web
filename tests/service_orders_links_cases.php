<?php
/** Native synthetic order-to-service association; no external request or invented provider response. */
declare(strict_types=1);
require_once __DIR__.'/../site/app/hosting/order_links.php';
require_once __DIR__.'/../site/app/hosting/operations.php';
$linkCheckStart=count($checks);
function sol_snapshot():string {
    $out=[];foreach(['hosting_order_links','hosting_records','hosting_record_events','service_orders','service_order_events','studio_invoices','invoice_payments','audit'] as $n)$out[$n]=all('SELECT * FROM '.table($n).' ORDER BY id');return service_plans_json($out);
}
function sol_accepted(int $base):ServiceOrderDocument {
    $s=so_source($base);$o=so_offer($s,so_invoice(),$base+10);rb_client();
    $o=service_orders_decide($o->data()['id'],1,0,rb_id($base+11),'accept',['domain_name'=>'orders.example','review_sha256'=>$o->fingerprint()],true);rb_staff();return $o;
}
function sol_manual(ServiceOrderDocument $o,int $event):ServiceOrderDocument {
    global $password;return service_orders_change($o->data()['id'],1,$o->revision(),rb_id($event),'allow_manual',['reason'=>'Synthetic owner approved unpaid setup','expires_at'=>time()+3600],$password);
}
function sol_create(ServiceOrderDocument $o,int $account=30100,?int $revision=null):array {
    global $password,$solProfile,$solPackage;
    return hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id($account),$solPackage,$solProfile,$revision??1,42,$password);
}
function sol_read(ServiceOrderDocument $o):array {
    return recurring_billing_locked(fn()=>hosting_order_links_validate(one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$o->data()['id']]),service_orders_validate(service_orders_row($o->data()['id']))));
}
try{
    rb_staff();hosting_records_migrate($password);provider_settings_migrate($password);
    $solProfile=provider_settings_save('',0,'Synthetic order mapping',true,'synthetic-order-mapping-key',$password);
    $solPackage=new \CajunWebPro\Foundation\Hosting\HostingPackage('synthetic-hosting','Synthetic selected package','811','owner-selected-region',['websites'=>1]);
    rb_ok('Order association read does not prepare storage',!hosting_storage_ready('order_links'));
    rb_deny('Order association setup requires owner password',fn()=>hosting_order_links_migrate('wrong'));
    rb_staff(2);rb_deny('Financial administrator cannot prepare order associations',fn()=>hosting_order_links_migrate($password));rb_staff();
    hosting_order_links_migrate($password);rb_ok('Owner prepares empty additive order associations',hosting_storage_ready('order_links')&&rb_count('hosting_order_links')===0);
    db()->exec('ALTER TABLE '.table('hosting_order_links').' ADD INDEX unexpected(created_epoch)');rb_deny('Association schema refuses unexpected indexes',fn()=>hosting_order_links_migrate($password));db()->exec('ALTER TABLE '.table('hosting_order_links').' DROP INDEX unexpected');
    $o=sol_accepted(30000);rb_deny('Unpaid accepted order cannot create a local hosting service',fn()=>sol_create($o));$o=sol_manual($o,30012);
    rb_deny('Association requires current owner password',fn()=>hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id(30100),$solPackage,$solProfile,1,42,'wrong'));
    rb_staff(2);rb_deny('Delegated financial access cannot map provider hosting',fn()=>sol_create($o));rb_staff();
    rb_deny('Association refuses cross-client order selection',fn()=>hosting_order_links_create($o->data()['id'],2,$o->revision(),$o->fingerprint(),rb_id(30100),$solPackage,$solProfile,1,42,$password));
    rb_deny('Association rejects self alias without explicit reseller identity',fn()=>hosting_order_links_request($o,$solPackage,0));
    $before=sol_snapshot();rb_ok('Association audit failure rolls back new hosting record and mapping',rb_fail(fn()=>sol_create($o))&&sol_snapshot()===$before);
    rb_ok('Association insert failure rolls back newly created hosting record',rb_fail(fn()=>sol_create($o),'hosting_order_links')&&sol_snapshot()===$before);
    $trigger=config()['db']['prefix'].'link_audit_fail';db()->exec('CREATE TRIGGER `'.$trigger.'` BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_records.order_linked' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic association audit failure'; END IF; END");
    try{$failed=false;try{sol_create($o);}catch(PDOException){$failed=true;}rb_ok('Final association audit failure rolls back all newly created records',$failed&&sol_snapshot()===$before);}finally{db()->exec('DROP TRIGGER `'.$trigger.'`');}
    $linked=sol_create($o);$b=$linked['binding'];
    rb_ok('Accepted order deterministically owns one new pending hosting record',$linked['journal']->account()->status()==='pending'&&$b['client_id']===1&&$b['order_id']===$o->data()['id']&&$b['account_id']===rb_id(30100));
    rb_ok('Mapping retains exact original consent and manual policy',$b['order_sha256']===$o->fingerprint()&&$b['commercial']['basis']==='owner-manual-policy'&&$b['commercial']['manual_event_id']===rb_id(30012));
    rb_ok('Mapping uses documented addWeb body without mail or unrelated fields',$b['request']['method']==='POST'&&$b['request']['url']==='https://api.20i.com/reseller/42/addWeb'&&json_decode($b['request']['body'],true)===['type'=>'811','domain_name'=>'orders.example']);
    $before=sol_snapshot();rb_ok('Exact mapping replay preserves single account and audit',sol_create($o)['binding']===$b&&sol_snapshot()===$before);
    $review=hosting_order_links_review($o->data()['id'],1);rb_ok('Current mapping review is explicitly not entitlement or dispatch authority',$review['commercial']['eligible']&&$review['profile_current']&&!$review['provider_entitlement_verified']&&!$review['dispatch_authorized']);
    rb_deny('Another client cannot be selected for a retained mapping',fn()=>hosting_order_links_review($o->data()['id'],2));
    rb_deny('A second service identity cannot consume the same order',fn()=>sol_create($o,30101));
    $other=sol_manual(sol_accepted(31000),31012);rb_deny('A different order cannot adopt an existing service',fn()=>sol_create($other,30100));
    rb_deny('Changed package cannot replace a pinned mapping',fn()=>hosting_order_links_create($o->data()['id'],1,$o->revision(),$o->fingerprint(),rb_id(30100),new \CajunWebPro\Foundation\Hosting\HostingPackage('different','Different','999','elsewhere',['websites'=>1]),$solProfile,1,42,$password));
    query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');rb_deny('Inactive client blocks mapping replay',fn()=>sol_create($o));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
    provider_settings_save($solProfile,1,'Changed connection context',true,'',$password);rb_deny('Changed connection version refuses old mapping replay',fn()=>sol_create($o));rb_ok('Connection changes preserve historical service association',sol_read($o)['binding']===$b);
    rb_ok('Current mapping review detects changed provider context',!hosting_order_links_review($o->data()['id'],1)['profile_current']);
    $changed=service_orders_change($other->data()['id'],1,$other->revision(),rb_id(31013),'revoke_manual',['reason'=>'Withdraw synthetic temporary permission'],$password);
    rb_deny('Stale order mapping cannot reuse former manual permission',fn()=>sol_create($other,31100,2));rb_deny('Revoked policy blocks new service mapping',fn()=>sol_create($changed,31100,2));
    hosting_operations_migrate($password);
    rb_deny('Generic hosting dispatch cannot bypass order-specific authority',fn()=>hosting_operations_claim(rb_id(30100),1,0,rb_id(30200),'hosting.provision',[],$solProfile,2,$password));
    rb_ok('Mapping does not create dispatch or provider attempt',rb_count('hosting_dispatches')===0&&rb_count('provider_operations')===0);
    $raw=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$o->data()['id']]);
    query('UPDATE '.table('hosting_order_links').' SET binding_json=? WHERE id=?',['{',$raw['id']]);rb_deny('Malformed mapping produces controlled restoration refusal',fn()=>sol_read($o));query('UPDATE '.table('hosting_order_links').' SET binding_json=? WHERE id=?',[$raw['binding_json'],$raw['id']]);
    $corrupt=$b;$corrupt['request']['params']['domain_name']='foreign.example';$bytes=service_plans_json($corrupt);query('UPDATE '.table('hosting_order_links').' SET binding_json=?,binding_sha256=? WHERE id=?',[$bytes,hash('sha256',$bytes),$raw['id']]);rb_deny('Rehashed foreign domain still fails retained order binding',fn()=>sol_read($o));query('UPDATE '.table('hosting_order_links').' SET binding_json=?,binding_sha256=? WHERE id=?',[$raw['binding_json'],$raw['binding_sha256'],$raw['id']]);
    $cancelled=service_orders_change($o->data()['id'],1,$o->revision(),rb_id(30013),'cancel',['reason'=>'Cancel synthetic unprovisioned request'],$password);rb_ok('Order cancellation preserves original mapped service and invoice',sol_read($cancelled)['binding']===$b&&ledger_invoice($o->data()['invoice']['id'])!==null);
    rb_ok('Cancelled order cannot remain commercially eligible through its mapping',!hosting_order_links_review($o->data()['id'],1)['commercial']['eligible']);
    $before=sol_snapshot();hosting_order_links_migrate($password);$after=sol_snapshot();rb_ok('Repeated migration preserves every original association',sol_read($cancelled)['binding']===$b&&rb_count('hosting_order_links')===1);
    $saved='saved_hosting_order_links';db()->exec('RENAME TABLE '.table('hosting_order_links').' TO '.table($saved));
    try{
        rb_deny('Missing association table refuses empty recreation',fn()=>hosting_order_links_migrate($password));
        rb_deny('Missing association table cannot make generic dispatch eligible',fn()=>hosting_operations_claim(rb_id(30100),1,0,rb_id(30200),'hosting.provision',[],$solProfile,2,$password));
        rb_ok('Missing association was not recreated',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'hosting_order_links']));
        save_setting('hosting_order_links_schema_version','');rb_deny('Lost association marker and table still require restoration',fn()=>hosting_order_links_migrate($password));save_setting('hosting_order_links_schema_version','1');
    }finally{db()->exec('RENAME TABLE '.table($saved).' TO '.table('hosting_order_links'));}
    rb_ok('Exact association table restoration preserves original binding',sol_read($cancelled)['binding']===$b);
    query('DELETE FROM '.table('hosting_order_links').' WHERE id=?',[$raw['id']]);rb_deny('Lost association row cannot bypass generic dispatch',fn()=>hosting_operations_claim(rb_id(30100),1,0,rb_id(30200),'hosting.provision',[],$solProfile,2,$password));
    query('INSERT INTO '.table('hosting_order_links').' ('.implode(',',array_keys($raw)).') VALUES ('.implode(',',array_fill(0,count($raw),'?')).')',array_values($raw));
    rb_ok('Saved association and audit exclude credential plaintext and passwords',!str_contains(sol_snapshot(),'synthetic-order-mapping-key')&&!str_contains(sol_snapshot(),$password));
}catch(Throwable $e){$checks[]=['name'=>'Order association fixture unexpected failure: '.$e->getMessage(),'passed'=>false];throw $e;}
finally{
    $named=array_slice($checks,$linkCheckStart);file_put_contents(__DIR__.'/../qa/service-orders-links-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Synthetic native atomic order-derived hosting identity, request mapping, authority, rollback and restoration. No provider entitlement, HTTP, dispatch or live provisioning acceptance.','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
