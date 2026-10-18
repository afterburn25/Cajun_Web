<?php
/** Real native inventory persistence and existing reader; isolated transport, no network. */
declare(strict_types=1);
require_once __DIR__.'/../site/app/hosting/fulfillment_confirmation.php';
function hc_read(array $profile,string $secret,string $address,callable $authorize,string $operation):\CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot {
    $credential=new \CajunWebPro\Foundation\Provider\ProviderCredential('twenty-i',$profile['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return \CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader::read(new HfTransport(Closure::fromCallable($authorize)),$credential,$operation,0,time());
}
function hc_args(array $c):array {
    global $password;$a=one('SELECT binding_sha256 FROM '.table('hosting_fulfillments').' WHERE id=?',[$c['request']]);
    return [$c['order']->data()['id'],1,$c['request'],$a['binding_sha256'],$password];
}
function hc_run(array $c,?callable $resolve=null):string {return hosting_fulfillment_confirm(...[...hc_args($c),$resolve??static fn()=>'8.8.8.8','hc_read']);}
function hc_body(bool $enabled=true):string {return json_encode([['id'=>866260,'enabled'=>$enabled,'typeRef'=>811,'name'=>'orders.example','names'=>['orders.example'],'created'=>'2020-01-15T16:30:30+00:00']],JSON_THROW_ON_ERROR);}
function hc_snapshot():string {
    $data=['hosting'=>hf_snapshot()];foreach(['hosting_observations','hosting_reconciliations','provider_refreshes','provider_refresh_results','provider_observation_packages'] as $name)$data[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');return service_plans_json($data);
}
/** Exercise the real production cooldown; never rewrite timestamps retained as proof. */
function hc_cooldown():void {
    global $solProfile;$last=one('SELECT MAX(started_epoch) AS epoch FROM '.table('provider_refreshes').' WHERE profile_id=?',[$solProfile]);
    $remaining=31- (time()-(int)($last['epoch']??0));if($remaining>0)sleep($remaining);
}
(static function()use(&$checks,$password):void {
    $start=count($checks);
    try{
        $c=hf_context(80000);HfTransport::$body='{"result":866260}';hf_run($c);$calls=HfTransport::$calls;
        rb_ok('Pending owner status card does not prepare inventory on GET',str_contains(hv_render($c),'Prepare status checks')&&!provider_inventory_ready()&&HfTransport::$calls===$calls);
        rb_deny('Status history preparation requires the owner password',fn()=>hosting_fulfillment_confirmation_prepare('wrong'));
        hosting_fulfillment_confirmation_prepare($password);$before=hf_snapshot(false);hosting_fulfillment_confirmation_prepare($password);
        rb_ok('Explicit repeated status preparation preserves orders attempts and resources',provider_inventory_ready()&&hf_snapshot(false)===$before&&HfTransport::$calls===$calls);
        $args=hc_args($c);$before=hc_snapshot();
        foreach([[1,2],[2,rb_id(999999)],[3,str_repeat('a',64)],[4,'wrong']] as [$index,$value]){
            $bad=$args;$bad[$index]=$value;rb_deny('Confirmation refuses changed client attempt approval or password '.$index,fn()=>hosting_fulfillment_confirm(...$bad));
        }
        rb_client();rb_deny('Client cannot request the private provider confirmation read',fn()=>hc_run($c));rb_staff(2);rb_deny('Financial delegate cannot confirm hosting',fn()=>hc_run($c));rb_staff();
        rb_ok('Refused confirmation requests change no history and perform no read',hc_snapshot()===$before&&HfTransport::$calls===$calls);
        HfTransport::$body=hc_body(false);rb_ok('Authenticated disabled package remains pending',hc_run($c)==='not_enabled'&&hf_read($c)->account()->providerRef()===null&&HfTransport::$calls===$calls+1);
        $refresh=provider_inventory_latest($c['binding']['profile_id']);$observation=one('SELECT * FROM '.table('hosting_observations').' WHERE id=?',[$refresh['id']]);
        rb_ok('Status proof retains the exact refresh and original assigned package',json_decode($observation['observation_json'],true)['evidence_ref']==='provider-refresh:'.$refresh['id']&&HfTransport::$requests[array_key_last(HfTransport::$requests)]['method']==='GET');
        $before=hc_snapshot();$calls=HfTransport::$calls;rb_deny('Status refresh shares the existing production cooldown',fn()=>hc_run($c));rb_ok('Cooldown cannot send or replace saved proof',hc_snapshot()===$before&&HfTransport::$calls===$calls);
        $approval=one('SELECT binding_json FROM '.table('hosting_fulfillments').' WHERE id=?',[$c['request']]);$binding=json_decode($approval['binding_json'],true);$result=json_decode(one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$c['request']])['result_json'],true);
        $package=one('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=?',[$refresh['id']]);
        foreach(['package_id'=>866261,'type_ref'=>812,'name'=>'foreign.example','names'=>'["foreign.example"]'] as $column=>$value){
            query('UPDATE '.table('provider_observation_packages').' SET enabled=1,'.$column.'=? WHERE id=?',[$value,$package['id']]);
            try{
                rb_ok('Exact confirmation rejects different observed '.$column,hosting_fulfillment_confirmation_proof($binding,$result,$refresh['id'])['outcome']==='unknown');
                rb_deny('Original status proof detects altered '.$column,fn()=>hosting_fulfillment_view($c['order']->data()['id'],1));
            }finally{query('UPDATE '.table('provider_observation_packages').' SET enabled=?,'.$column.'=? WHERE id=?',[$package['enabled'],$package[$column],$package['id']]);}
        }
        $stored=one('SELECT * FROM '.table('provider_refresh_results').' WHERE id=?',[$refresh['id']]);
        foreach(['scope'=>'twenty-i|foreign|packages.list','key_fingerprint'=>'','finished_at'=>'2000-01-01 00:00:00'] as $column=>$value){
            query('UPDATE '.table('provider_refresh_results').' SET '.$column.'=? WHERE id=?',[$value,$refresh['id']]);
            try{rb_deny('Status proof refuses altered original '.$column,fn()=>hosting_fulfillment_view($c['order']->data()['id'],1));}
            finally{query('UPDATE '.table('provider_refresh_results').' SET '.$column.'=? WHERE id=?',[$stored[$column],$refresh['id']]);}
        }
        rb_ok('Restoring exact saved status evidence preserves all original rows',hc_snapshot()===$before);
        hc_cooldown();HfTransport::$body=hc_body(true);$provider=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$c['request']]);
        $observations=all('SELECT * FROM '.table('hosting_observations').' ORDER BY id');$calls=HfTransport::$calls;
        rb_ok('Final confirmation audit failure rolls back native adoption while retaining the authenticated read',hf_audit_failure('fulfillment_checked',fn()=>hc_run($c))&&HfTransport::$calls===$calls+1&&hf_read($c)->account()->providerRef()===null&&all('SELECT * FROM '.table('hosting_observations').' ORDER BY id')===$observations&&provider_inventory_latest($c['binding']['profile_id'])['state']==='complete');
        hc_cooldown();
        rb_ok('Exact enabled ID domain and type confirm the existing order service',hc_run($c)==='confirmed'&&hf_read($c)->account()->status()==='active'&&hf_read($c)->account()->providerRef()==='866260');
        rb_ok('Confirmation preserves the original provider acknowledgement and purchase count',one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$c['request']])===$provider&&(int)$provider['attempts']===1);
        $before=hc_snapshot();$calls=HfTransport::$calls;rb_ok('Replayed active confirmation is inert without another read',hc_run($c)==='already_confirmed'&&hc_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_client();$view=hosting_order_links_context($c['order']->data()['id'],1,true);rb_staff();rb_ok('Client sees confirmed service with no provider identifiers or proof', $view['progress']['status']==='active'&&array_keys($view['progress'])===['account_id','created_at','status','order_cancelled']&&!str_contains(service_plans_json($view),'866260'));
        db()->exec('RENAME TABLE '.table('provider_observation_packages').' TO '.table('hc_saved_packages'));
        try{rb_deny('Confirmed service refuses missing original inventory proof',fn()=>hosting_fulfillment_view($c['order']->data()['id'],1));rb_deny('Status preparation cannot replace established missing observations',fn()=>hosting_fulfillment_confirmation_prepare($password));}
        finally{db()->exec('RENAME TABLE '.table('hc_saved_packages').' TO '.table('provider_observation_packages'));}
        rb_ok('Restored confirmation proof preserves active service and original rows',hc_snapshot()===$before&&hc_run($c)==='already_confirmed');
        query('DELETE FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version']);
        try{rb_deny('Lost original inventory marker cannot recreate confirmation history',fn()=>hosting_fulfillment_confirmation_prepare($password));}
        finally{save_setting('provider_inventory_schema_version','5');}
        rb_ok('Exact inventory marker restoration retains confirmed service',hc_snapshot()===$before&&hc_run($c)==='already_confirmed');
        $revoked=hf_context(82000);HfTransport::$body='{"result":866261}';hf_run($revoked);hc_cooldown();$calls=HfTransport::$calls;
        try{rb_deny('Client revocation after read intent prevents credential use and confirmation',fn()=>hc_run($revoked,static function():string{query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');return '8.8.8.8';}));}
        finally{query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');}
        rb_ok('Revoked read retains an access-changed receipt without a read or adoption',HfTransport::$calls===$calls&&provider_inventory_latest($revoked['binding']['profile_id'])['state']==='access_changed'&&hf_read($revoked)->account()->providerRef()===null&&!one('SELECT id FROM '.table('hosting_observations').' WHERE dispatch_id=?',[$revoked['request']]));
    }catch(Throwable $e){$checks[]=['name'=>'Unexpected native confirmation failure: '.$e->getMessage(),'passed'=>false];throw $e;}
    finally{
        HfTransport::$status=200;HfTransport::$body='{"result":866239}';HfTransport::$beforeSend=null;HfTransport::$afterSend=null;$_POST=[];$_SERVER['REQUEST_METHOD']='GET';rb_staff();
        $named=array_slice($checks,$start);file_put_contents(__DIR__.'/../qa/hosting-confirmation-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Real native persisted exact-ID confirmation through the existing inventory reader with isolated transport. No live provider call.','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
})();
