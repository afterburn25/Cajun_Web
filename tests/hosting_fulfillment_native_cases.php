<?php
/** Disposable native orders use the real gateway/adapter/ledger with an isolated in-memory transport. */
declare(strict_types=1);
require_once __DIR__.'/../site/app/hosting/fulfillment.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,ProviderCredential,ProviderGateway,TransportException};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;
final class HfTransport implements HttpTransport {
    public static int $calls=0;
    public static array $requests=[];
    public static int $status=200;
    public static string $body='{"result":866239}';
    public static bool $timeout=false;
    public static ?Closure $beforeSend=null;
    public static ?Closure $afterSend=null;
    public function __construct(private Closure $authorize){}
    public function send(TransportRequest $request):TransportResponse {
        if(self::$beforeSend)(self::$beforeSend)();
        ($this->authorize)();self::$calls++;
        self::$requests[]=['method'=>$request->method,'url'=>$request->url,'body'=>$request->body,'header_names'=>array_keys($request->headers)];
        if(self::$afterSend)(self::$afterSend)();
        if(self::$timeout)throw new TransportException(TransportException::KIND_TIMEOUT);
        return new TransportResponse(self::$status,[],self::$body);
    }
}
function hf_perform(array $profile,string $secret,string $address,callable $authorize,\CajunWebPro\Foundation\Provider\ProviderOperation $operation):\CajunWebPro\Foundation\Provider\OperationResult {
    $identity=$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($secret);
    $credential=new ProviderCredential('twenty-i',$identity,static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(),new HfTransport(Closure::fromCallable($authorize)),new ProviderOperationLedger()))->perform($credential,$operation);
}
function hf_context(int $base,bool $manual=true):array {
    global $solProfile;
    rb_staff();$order=sol_accepted($base);$receipt=null;
    if($manual)$order=sol_manual($order,$base+12);
    else{$invoice=$order->data()['invoice']['id'];$receipt=ledger_receive($invoice,2500,'bank','Synthetic hosting fulfillment receipt '.(string)$base,gmdate('Y-m-d'),hash('sha256','hosting-fulfillment-receipt-'.$base),ledger_review_hash(ledger_invoice($invoice)));}
    $saved=sol_create($order,$base+100,(int)provider_settings_load($solProfile)['revision']);
    $link=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$order->data()['id']]);
    return ['order'=>$order,'link'=>$link,'binding'=>$saved['binding'],'receipt'=>$receipt,'request'=>rb_id($base+50),'review'=>['account_type'=>true,'defaults'=>true,'costs'=>true,'confirmation'=>'CREATE orders.example']];
}
function hf_run(array $c,?callable $resolver=null):array {
    global $password;$o=$c['order'];
    return hosting_fulfillment_execute($o->data()['id'],1,$o->revision(),$o->fingerprint(),$c['link']['binding_sha256'],$c['request'],$c['review'],$password,$resolver??static fn():string=>'8.8.8.8','hf_perform');
}
function hf_snapshot(bool $withAudit=true):string {
    $out=[];foreach(['hosting_fulfillments','hosting_order_links','hosting_records','hosting_record_events','hosting_dispatches','hosting_operation_results','hosting_resources','provider_operations','service_orders','service_order_events','studio_invoices','invoice_payments','audit'] as $name){if(!$withAudit&&$name==='audit')continue;$out[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');}return service_plans_json($out);
}
function hf_read(array $c):\CajunWebPro\Foundation\Hosting\HostingJournal {return hosting_operations_validate(hosting_records_row($c['link']['account_id'],1));}
function hf_recovery_args(array $c):array {
    global $password;
    $approval=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=?',[$c['request']]);
    $provider=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$c['request']]);
    return [$c['order']->data()['id'],1,$c['request'],$approval['binding_sha256'],hash('sha256',service_plans_json($provider)),$password];
}
function hf_audit_failure(string $action,callable $fn):bool {
    $trigger=config()['db']['prefix'].'hf_fail';db()->exec('CREATE TRIGGER `'.$trigger.'` BEFORE INSERT ON '.table('audit')." FOR EACH ROW BEGIN IF NEW.action='hosting_records.".$action."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic final fulfillment audit failure'; END IF; END");
    try{$fn();$failed=false;}catch(PDOException){$failed=true;}finally{db()->exec('DROP TRIGGER `'.$trigger.'`');}return $failed;
}
if(($argv[1]??'')==='fulfillment-worker'){
    rb_staff();$p=json_decode(base64_decode($argv[2],true),true,16,JSON_THROW_ON_ERROR);HfTransport::$body='{"result":866242}';
    echo "ready\n";fflush(STDOUT);if(trim((string)fgets(STDIN))!=='go')exit(2);
    $result=null;for($attempt=0;$attempt<40;$attempt++){
        try{$result=hosting_fulfillment_execute($p['order_id'],1,$p['revision'],$p['order_hash'],$p['link_hash'],$p['request'],$p['review'],$password,static fn()=>'8.8.8.8','hf_perform');break;}
        catch(RuntimeException $e){if($e->getMessage()!=='Another account change is in progress. Please retry.')throw $e;usleep(250000);}
    }
    if($result===null)throw new RuntimeException('Fulfillment worker never obtained the change lock.');
    echo json_encode(['state'=>$result['state'],'transport_calls'=>HfTransport::$calls],JSON_THROW_ON_ERROR);exit;
}
(static function()use(&$checks,$password):void {
    $start=count($checks);
    try{
        rb_staff();hosting_reconciliation_migrate($password);provider_operations_migrate($password);
        rb_ok('Merged license administration cannot inherit ordinary website editing authority',staff_view_permission('studio-licenses')==='staff.manage'&&array_reduce(['studio_license_create','studio_license_deactivate','studio_license_bundle','studio_domain_add','studio_domain_deactivate'],static fn(bool $safe,string $action):bool=>$safe&&staff_action_permission($action)==='staff.manage',true));
        rb_ok('Fulfillment GET readiness never prepares storage',!hosting_storage_ready('fulfillment'));
        rb_deny('Fulfillment preparation requires current owner password',fn()=>hosting_fulfillment_migrate('wrong'));
        rb_staff(2);rb_deny('Delegated financial access cannot prepare fulfillment',fn()=>hosting_fulfillment_migrate($password));rb_staff();
        hosting_fulfillment_migrate($password);rb_ok('Fulfillment uses additive existing native dependencies',hosting_storage_ready('fulfillment')&&rb_count('hosting_fulfillments')===0);
        db()->exec('ALTER TABLE '.table('hosting_fulfillments').' ADD INDEX unexpected(created_epoch)');
        try{rb_deny('Fulfillment preparation rejects incompatible extra indexes',fn()=>hosting_fulfillment_migrate($password));}
        finally{db()->exec('ALTER TABLE '.table('hosting_fulfillments').' DROP INDEX unexpected');}
        $c=hf_context(50000);$o=$c['order'];$before=hf_snapshot();$calls=HfTransport::$calls;
        foreach(['account_type','defaults','costs'] as $field){$bad=$c;$bad['review'][$field]=false;rb_deny('Dispatch requires explicit '.$field.' review',fn()=>hf_run($bad));}
        $bad=$c;$bad['review']['confirmation']='CREATE foreign.example';rb_deny('Fulfillment confirmation must name the accepted domain',fn()=>hf_run($bad));
        rb_staff(2);rb_deny('Delegated staff cannot fulfill a provider order',fn()=>hf_run($c));rb_client();rb_deny('Client cannot supply owner provisioning authority',fn()=>hf_run($c));rb_staff();
        rb_deny('Fulfillment cannot substitute another client',fn()=>hosting_fulfillment_execute($o->data()['id'],2,$o->revision(),$o->fingerprint(),$c['link']['binding_sha256'],$c['request'],$c['review'],$password,static fn()=>'8.8.8.8','hf_perform'));
        rb_deny('Fulfillment requires current owner password',fn()=>hosting_fulfillment_execute($o->data()['id'],1,$o->revision(),$o->fingerprint(),$c['link']['binding_sha256'],$c['request'],$c['review'],'wrong',static fn()=>'8.8.8.8','hf_perform'));
        rb_ok('Authorization refusals mutate no order dispatch ledger or audit',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_ok('Final approval audit failure rolls back both approval and hosting fence',hf_audit_failure('fulfillment_approved',fn()=>hf_run($c))&&hf_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_ok('Fulfillment insertion failure rolls back the same native hosting attempt',rb_fail(fn()=>hf_run($c),'hosting_fulfillments')&&hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $invoice=one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$o->data()['invoice']['id']]);
        $result=hf_run($c);$journal=hf_read($c);
        rb_ok('Dedicated order fulfillment sends once through existing native gateway ledger',$result===['state'=>'accepted_pending','attempt_started'=>true]&&HfTransport::$calls===$calls+1&&one('SELECT status FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$c['request']])['status']==='accepted_pending');
        rb_ok('Native request uses exact stored addWeb intent without unrelated fields',HfTransport::$requests[array_key_last(HfTransport::$requests)]===['method'=>'POST','url'=>$c['binding']['request']['url'],'body'=>$c['binding']['request']['body'],'header_names'=>['Authorization','Accept','Content-Type']]);
        rb_ok('Provider assigned ID stays pending on the existing client account',$journal->account()->status()==='provisioning'&&$journal->account()->acknowledgedResourceRef()==='866239'&&$journal->account()->providerRef()===null&&$journal->account()->clientRef==='client:1');
        rb_ok('Manual fulfillment does not mark invoice paid or change its contents',one('SELECT * FROM '.table('studio_invoices').' WHERE id=?',[$o->data()['invoice']['id']])===$invoice);
        $calls=HfTransport::$calls;$before=hf_snapshot();rb_ok('Exact submit replay sends no additional provider request',hf_run($c)===['state'=>'replayed','attempt_started'=>false]&&HfTransport::$calls===$calls&&hf_snapshot()===$before);
        $new=$c;$new['request']=rb_id(50051);rb_deny('New request identity cannot duplicate pending provisioning',fn()=>hf_run($new));
        rb_ok('Refused second identity preserves original acknowledgement and all rows',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_client();$view=hosting_order_links_context($o->data()['id'],1,true);rb_ok('Client sees actual pending service without provider identity or approval details',array_keys($view['progress'])===['account_id','created_at','status','order_cancelled']&&$view['progress']['status']==='provisioning'&&!str_contains(service_plans_json($view),'866239'));rb_staff();
        $original=one('SELECT * FROM '.table('hosting_fulfillments').' WHERE id=?',[$c['request']]);query('DELETE FROM '.table('hosting_fulfillments').' WHERE id=?',[$c['request']]);
        try{rb_deny('Lost fulfillment row cannot be treated as another first attempt',fn()=>hf_run($c));rb_client();rb_deny('Client progress refuses lost original fulfillment authority',fn()=>hosting_order_links_context($o->data()['id'],1,true));}
        finally{rb_staff();query('INSERT INTO '.table('hosting_fulfillments').' ('.implode(',',array_keys($original)).') VALUES ('.implode(',',array_fill(0,count($original),'?')).')',array_values($original));}
        rb_ok('Original approval restoration preserves the same history without a send',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $provider=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$c['request']]);query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$provider['id']]);
        try{rb_deny('Missing original provider acknowledgement cannot be rebuilt from hosting state',fn()=>hf_run($c));}
        finally{query('INSERT INTO '.table('provider_operations').' ('.implode(',',array_keys($provider)).') VALUES ('.implode(',',array_fill(0,count($provider),'?')).')',array_values($provider));}
        rb_ok('Restoring original provider ledger is exact and does not resend',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $expired=hf_context(51000);$calls=HfTransport::$calls;
        $resolver=static function()use($expired,$password):string{$o=$expired['order'];service_orders_change($o->data()['id'],1,$o->revision(),rb_id(51090),'revoke_manual',['reason'=>'Synthetic revocation before secret access'],$password);return '8.8.8.8';};
        rb_ok('Revoked commercial policy before credential access prevents dispatch',hf_run($expired,$resolver)===['state'=>'failed','attempt_started'=>false]&&HfTransport::$calls===$calls&&hf_read($expired)->account()->status()==='failed');
        $changed=hf_context(52000);$calls=HfTransport::$calls;
        HfTransport::$beforeSend=static function()use($changed,$password):void{$o=$changed['order'];service_orders_change($o->data()['id'],1,$o->revision(),rb_id(52090),'revoke_manual',['reason'=>'Synthetic revocation at send boundary'],$password);};
        try{$r=hf_run($changed);rb_ok('Commercial authority is checked again at the actual send boundary',$r['state']==='timeout'&&HfTransport::$calls===$calls&&hf_read($changed)->account()->awaitingReconciliation());}
        finally{HfTransport::$beforeSend=null;}
        $unknown=hf_context(53000);$calls=HfTransport::$calls;HfTransport::$timeout=true;
        try{$r=hf_run($unknown);rb_ok('Ambiguous purchase persists unknown and performs only one attempt',$r['state']==='timeout'&&HfTransport::$calls===$calls+1&&hf_read($unknown)->account()->awaitingReconciliation());}
        finally{HfTransport::$timeout=false;}
        $calls=HfTransport::$calls;rb_ok('Unknown original request replay cannot repeat the purchase',hf_run($unknown)['state']==='replayed'&&HfTransport::$calls===$calls);
        $failed=hf_context(54000);HfTransport::$status=400;
        try{rb_ok('Definite provider refusal records safe failure separately from unknown',hf_run($failed)['state']==='failed'&&!hf_read($failed)->account()->awaitingReconciliation());}
        finally{HfTransport::$status=200;}
        hosting_operations_retry_provision($failed['link']['account_id'],1,hf_read($failed)->revision(),rb_id(54091),$password);$failed['request']=rb_id(54092);HfTransport::$body='{"result":866240}';$calls=HfTransport::$calls;
        rb_ok('Deliberate eligible retry uses a fresh attempt and current order authorization',hf_run($failed)['state']==='accepted_pending'&&HfTransport::$calls===$calls+1&&hf_read($failed)->account()->acknowledgedResourceRef()==='866240');
        $lost=hf_context(55000);$calls=HfTransport::$calls;HfTransport::$body='{"result":866241}';
        rb_ok('Final hosting-result audit failure retains provider acknowledgement and original fence',hf_audit_failure('result_recorded',fn()=>hf_run($lost))&&HfTransport::$calls===$calls+1&&hf_read($lost)->account()->status()==='provisioning'&&!one('SELECT id FROM '.table('hosting_operation_results').' WHERE id=?',[$lost['request']])&&one('SELECT status FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$lost['request']])['status']==='accepted_pending');
        $calls=HfTransport::$calls;rb_ok('Missing local result cannot reclaim an already accepted purchase',hf_run($lost)['state']==='replayed'&&HfTransport::$calls===$calls);
        $recover=hf_recovery_args($lost);$before=hf_snapshot();
        foreach([1=>2,3=>str_repeat('a',64),4=>str_repeat('b',64),5=>'wrong'] as $field=>$value){$bad=$recover;$bad[$field]=$value;rb_deny('Local recovery rejects foreign or stale reviewed authority '.$field,fn()=>hosting_fulfillment_recover(...$bad));}
        rb_staff(2);rb_deny('Delegated staff cannot recover provider fulfillment',fn()=>hosting_fulfillment_recover(...$recover));rb_client();rb_deny('Client cannot supply retained-result recovery authority',fn()=>hosting_fulfillment_recover(...$recover));rb_staff();
        rb_ok('Rejected local recovery leaves all records and provider count unchanged',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $retained=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$lost['request']]);
        foreach(['fingerprint'=>str_repeat('c',64),'operation'=>'domains.renew','external_id'=>'0','attempts'=>2,'claimed_epoch'=>0,'resolved_epoch'=>time()+3600,'status'=>'claimed'] as $field=>$value){
            query('UPDATE '.table('provider_operations').' SET '.$field.'=? WHERE id=?',[$value,$retained['id']]);
            try{$changedProof=hf_recovery_args($lost);rb_deny('Recovery validates original provider '.$field.' beyond its reviewed hash',fn()=>hosting_fulfillment_recover(...$changedProof));}
            finally{query('UPDATE '.table('provider_operations').' SET '.$field.'=? WHERE id=?',[$retained[$field],$retained['id']]);}
        }
        query('DELETE FROM '.table('provider_operations').' WHERE id=?',[$retained['id']]);
        try{rb_deny('Recovery cannot fabricate a missing original provider response',fn()=>hosting_fulfillment_recover(...$recover));}
        finally{query('INSERT INTO '.table('provider_operations').' ('.implode(',',array_keys($retained)).') VALUES ('.implode(',',array_fill(0,count($retained),'?')).')',array_values($retained));}
        rb_ok('Damaged proof refusals retain original approval and exact restored rows',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_ok('Recovery audit failure rolls back the reconstructed native result',hf_audit_failure('fulfillment_recovered',fn()=>hosting_fulfillment_recover(...$recover))&&hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $lo=$lost['order'];service_orders_change($lo->data()['id'],1,$lo->revision(),rb_id(55090),'revoke_manual',['reason'=>'Synthetic cancellation of future authority after purchase'],$password);
        rb_ok('Exact original response recovers locally despite later commercial revocation',hosting_fulfillment_recover(...$recover)==='accepted_pending'&&hf_read($lost)->account()->acknowledgedResourceRef()==='866241'&&hf_read($lost)->account()->providerRef()===null&&HfTransport::$calls===$calls);
        rb_ok('Recovery preserves original response time and every provider ledger field',one('SELECT * FROM '.table('provider_operations').' WHERE id=?',[$retained['id']])===$retained&&json_decode(one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$lost['request']])['result_json'],true)['at']===(int)$retained['resolved_epoch']);
        $before=hf_snapshot();rb_ok('Repeated local recovery is inert and never sends a provider request',hosting_fulfillment_recover(...$recover)==='already_recorded'&&hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $originalArgs=hf_recovery_args($c);rb_ok('Recovery cannot rewrite an already recorded normal result',hosting_fulfillment_recover(...$originalArgs)==='already_recorded'&&hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $unfinishedArgs=hf_recovery_args($changed);rb_deny('A claimed provider row cannot become a recovered rejection or acceptance',fn()=>hosting_fulfillment_recover(...$unfinishedArgs));
        $lostFailure=hf_context(62000);HfTransport::$status=400;
        try{rb_ok('Definite rejection fixture retains provider result when local finalization fails',hf_audit_failure('result_recorded',fn()=>hf_run($lostFailure)));}
        finally{HfTransport::$status=200;}
        $failureArgs=hf_recovery_args($lostFailure);$calls=HfTransport::$calls;
        rb_ok('Retained definite failure recovers as failure without resending or activating',hosting_fulfillment_recover(...$failureArgs)==='failed'&&hf_read($lostFailure)->account()->status()==='failed'&&!hf_read($lostFailure)->account()->awaitingReconciliation()&&HfTransport::$calls===$calls);
        $lostUnknown=hf_context(63000);HfTransport::$timeout=true;
        try{rb_ok('Unknown response fixture retains provider result when local finalization fails',hf_audit_failure('result_recorded',fn()=>hf_run($lostUnknown)));}
        finally{HfTransport::$timeout=false;}
        $unknownArgs=hf_recovery_args($lostUnknown);$calls=HfTransport::$calls;
        rb_ok('Retained unknown outcome remains unresolved after local recovery',hosting_fulfillment_recover(...$unknownArgs)==='timeout'&&hf_read($lostUnknown)->account()->awaitingReconciliation()&&hf_read($lostUnknown)->account()->providerRef()===null&&HfTransport::$calls===$calls);
        $race=hf_context(56000);$ro=$race['order'];$payload=base64_encode(service_plans_json(['order_id'=>$ro->data()['id'],'revision'=>$ro->revision(),'order_hash'=>$ro->fingerprint(),'link_hash'=>$race['link']['binding_sha256'],'request'=>$race['request'],'review'=>$race['review']]));$workers=[];
        for($i=0;$i<2;$i++){
            $process=proc_open([PHP_BINARY,__DIR__.'/recurring_billing_native.php','fulfillment-worker',$payload],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('Cannot start isolated fulfillment worker');
            stream_set_timeout($pipes[1],20);stream_set_timeout($pipes[2],20);$workers[]=[$process,$pipes];
        }
        foreach($workers as [$process,$pipes])if(trim((string)fgets($pipes[1]))!=='ready')throw new RuntimeException('Fulfillment worker did not become ready');
        foreach($workers as [$process,$pipes]){fwrite($pipes[0],"go\n");fclose($pipes[0]);}
        $raceResults=[];
        foreach($workers as [$process,$pipes]){
            $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            if(proc_close($process)!==0||$error!=='')throw new RuntimeException('Fulfillment worker failed: '.$error);
            $raceResults[]=json_decode($output,true,16,JSON_THROW_ON_ERROR);
        }
        $states=array_column($raceResults,'state');sort($states);
        rb_ok('Competing native processes share one request fence and one provider attempt',$states===['accepted_pending','replayed']&&array_sum(array_column($raceResults,'transport_calls'))===1&&(int)one('SELECT attempts FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$race['request']])['attempts']===1&&(int)query('SELECT COUNT(*) FROM '.table('hosting_fulfillments').' WHERE order_id=?',[$ro->data()['id']])->fetchColumn()===1);
        $revoked=hf_context(57000);HfTransport::$body='{"result":866243}';
        HfTransport::$afterSend=static function():void{query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);};
        try{$r=hf_run($revoked);rb_staff();rb_ok('Revoked session after acceptance cannot erase the assigned resource',$r['state']==='accepted_pending'&&hf_read($revoked)->account()->acknowledgedResourceRef()==='866243');}
        finally{HfTransport::$afterSend=null;rb_staff();}
        $paid=hf_context(58000,false);HfTransport::$body='{"result":866244}';$calls=HfTransport::$calls;
        rb_ok('Verified receipt fulfills the agreed order without a manual policy',hf_run($paid)['state']==='accepted_pending'&&HfTransport::$calls===$calls+1&&json_decode(one('SELECT binding_json FROM '.table('hosting_fulfillments').' WHERE id=?',[$paid['request']])['binding_json'],true)['commercial']['basis']==='verified-payment');
        $reversed=hf_context(59000,false);$calls=HfTransport::$calls;
        $resolver=static function()use($reversed,$password):string{ledger_reverse($reversed['receipt'],'Synthetic pre-send payment reversal',hash('sha256','hosting-fulfillment-reverse-59000'),ledger_review_hash(ledger_invoice($reversed['order']->data()['invoice']['id'])),$password);return '8.8.8.8';};
        rb_ok('Receipt reversal before credential access prevents the purchase',hf_run($reversed,$resolver)===['state'=>'failed','attempt_started'=>false]&&HfTransport::$calls===$calls);
        $unreachable=hf_context(60000);$calls=HfTransport::$calls;
        rb_ok('Endpoint failure before the gateway is a safe local failure without a provider claim',hf_run($unreachable,static function():string{throw new RuntimeException('synthetic private resolver detail');})===['state'=>'failed','attempt_started'=>false]&&HfTransport::$calls===$calls&&!one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=?',['hosting-order:'.$unreachable['request']])&&!str_contains(hf_snapshot(),'synthetic private resolver detail'));
        $falseResponse=hf_context(61000);HfTransport::$body='{"result":false}';$calls=HfTransport::$calls;
        rb_ok('Successful HTTP with false result cannot activate an order-derived service',hf_run($falseResponse)['state']==='timeout'&&hf_read($falseResponse)->account()->providerRef()===null&&HfTransport::$calls===$calls+1);$calls=HfTransport::$calls;
        $before=hf_snapshot(false);hosting_fulfillment_migrate($password);rb_ok('Repeated preparation preserves every fulfillment and original attempt',hf_snapshot(false)===$before&&HfTransport::$calls===$calls);
        db()->exec('RENAME TABLE '.table('hosting_fulfillments').' TO '.table('saved_fulfillments'));
        try{rb_deny('Missing established fulfillment table cannot be recreated',fn()=>hosting_fulfillment_migrate($password));save_setting('hosting_fulfillment_schema_version','');rb_deny('Lost marker and table still require original fulfillment restoration',fn()=>hosting_fulfillment_migrate($password));}
        finally{save_setting('hosting_fulfillment_schema_version','1');db()->exec('RENAME TABLE '.table('saved_fulfillments').' TO '.table('hosting_fulfillments'));}
        rb_ok('Fulfillment restoration cannot trigger a hidden provider attempt',HfTransport::$calls===$calls&&hf_run($c)['state']==='replayed');
        service_orders_change($o->data()['id'],1,$o->revision(),rb_id(50090),'cancel',['reason'=>'Synthetic cancellation after accepted provider purchase'],$password);rb_client();$view=hosting_order_links_context($o->data()['id'],1,true);rb_staff();
        rb_ok('Order cancellation preserves accepted service and does not delete hosting',$view['progress']['order_cancelled']&&$view['progress']['status']==='provisioning'&&hf_read($c)->account()->acknowledgedResourceRef()==='866239'&&HfTransport::$calls===$calls);
        rb_ok('Fulfillment history and audit contain no password or plaintext provider credential',!str_contains(hf_snapshot(),$password)&&!str_contains(hf_snapshot(),'synthetic-order-mapping-key'));
    }catch(Throwable $e){$checks[]=['name'=>'Unexpected native fulfillment failure: '.$e->getMessage(),'passed'=>false];throw $e;}
    finally{
        HfTransport::$beforeSend=null;HfTransport::$afterSend=null;HfTransport::$timeout=false;
        $named=array_slice($checks,$start);file_put_contents(__DIR__.'/../qa/hosting-fulfillment-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Synthetic native client/order/manual-consent to existing gateway/ledger and assigned-ID hosting result with isolated transport. No external network, provider entitlement verification or HTTP approval UI acceptance.','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
})();
