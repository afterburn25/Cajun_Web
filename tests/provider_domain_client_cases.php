<?php
/** Extends the isolated native domain-search fixture; never connects to a provider. */
declare(strict_types=1);
function dsc_login(int $id):void {
    $c=one('SELECT * FROM '.table('studio_clients').' WHERE id=?',[$id]);
    $_SESSION=['client_id'=>$id,'client_auth'=>$c['auth_version'],'client_last'=>time(),'client_login'=>time(),'csrf'=>bin2hex(random_bytes(32))];
}
function dsc_cool():void {ds_cool();query('UPDATE '.table('provider_domain_client_searches').' SET started_epoch=started_epoch-120 WHERE started_epoch>?',[time()-30]);}
function dsc_age():void {ds_age();query('UPDATE '.table('provider_domain_client_searches').' SET started_epoch=started_epoch-7200');}
function dsc_enable(bool $enabled=true):array {
    global $password;ds_actor();$p=provider_domain_client_policy();provider_domain_client_configure($p['revision']??0,$enabled,$password);return provider_domain_client_policy();
}
function dsc_run(int $client,array $policy,?string $request=null,?callable $resolver=null,?callable $transport=null):string {
    return provider_domain_client_run($client,$policy['id'],$request??bin2hex(random_bytes(16)),'example.invalid',$resolver??'ds_dns',$transport??'ds_read');
}
function dsc_result(string $id):array {return one('SELECT * FROM '.table('provider_domain_client_results').' WHERE id=?',[$id]);}
function dsc_render(int $client):string {
    $policy=provider_domain_client_policy();[,,$cfg]=provider_domain_client_authority($client,$policy['id']);
    $searchContext=['policy'=>$policy['id'],'tlds'=>$cfg['tlds']];$searchHistory=provider_domain_client_history($client);$historyUnavailable=false;
    ob_start();require __DIR__.'/../site/portal/views/domain_search.php';return ob_get_clean();
}
function ds_client_cases(string $profileId):void {
    global $checks,$password,$secret,$response,$sent,$dns;
    $first=count($checks);ds_actor();provider_domain_search_configure($profileId,2,6,true,'invalid',$password);
    $ownersBefore=all('SELECT * FROM '.table('provider_domain_searches').' ORDER BY id');
    ds_native_ok('Client search starts unprepared and GET does not create tables',!provider_domain_client_family()&&!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pds_provider_domain_client_searches']));
    ds_native_deny('Client search preparation refuses revoked owner authority',fn()=>cwp_test_owner_revoked(fn()=>provider_domain_client_prepare('wrong')));
    ds_actor(2);ds_native_deny('Delegated administrator cannot prepare client searches',fn()=>provider_domain_client_prepare($password));ds_actor();
    ds_native_ok('Interrupted initial client preparation keeps empty tables without a marker',ds_audit_fail('domain_client_prepared',fn()=>provider_domain_client_prepare($password))&&!one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_client_schema_version'])&&ds_count('provider_domain_client_policies')===0&&ds_count('provider_domain_client_searches')===0&&ds_count('provider_domain_client_results')===0);
    ds_native_deny('Interrupted initial client preparation refuses reads until completed',fn()=>provider_domain_client_family());
    provider_domain_client_prepare($password);provider_domain_client_prepare($password);
    ds_native_ok('Repeated additive client preparation preserves every staff lookup',provider_domain_client_family()&&$ownersBefore===all('SELECT * FROM '.table('provider_domain_searches').' ORDER BY id'));
    ds_native_ok('Client search starts disabled without an owner grant',provider_domain_client_policy()===null);
    ds_native_deny('Client search policy refuses revoked owner authority',fn()=>cwp_test_owner_revoked(fn()=>provider_domain_client_configure(0,true,'wrong')));
    ds_native_ok('Client policy audit failure rolls back policy and pointer',ds_audit_fail('domain_client_configured',fn()=>provider_domain_client_configure(0,true,$password))&&provider_domain_client_policy()===null&&ds_count('provider_domain_client_policies')===0);
    $policy=dsc_enable();ds_native_deny('Stale client policy form cannot overwrite current approval',fn()=>provider_domain_client_configure(0,false,$password));
    $clients=[];
    foreach(['First','Second'] as $name){query('INSERT INTO '.table('studio_clients').' (name,email,password_hash,auth_version,active,created_at) VALUES (?,?,?,?,1,?)',[$name,$name.'@example.invalid',password_hash('Synthetic client password',PASSWORD_DEFAULT),bin2hex(random_bytes(16)),gmdate('Y-m-d H:i:s')]);$clients[]=(int)db()->lastInsertId();}
    [$one,$two]=$clients;$before=$sent;$beforeDns=$dns;
    ds_native_deny('Staff identity cannot submit a client domain lookup',fn()=>dsc_run($one,$policy));
    dsc_login($one);ds_native_deny('Client cannot claim another client identity',fn()=>dsc_run($two,$policy));
    $_GET['support']='fixture';ds_native_deny('Support session cannot search as a client',fn()=>dsc_run($one,$policy));unset($_GET['support']);
    $_SESSION['client_login']=time()-28801;ds_native_deny('Expired client session cannot search',fn()=>dsc_run($one,$policy));dsc_login($one);
    ds_native_deny('Client cannot use an older policy ID',fn()=>provider_domain_client_run($one,str_repeat('f',32),bin2hex(random_bytes(16)),'example.invalid','ds_dns','ds_read'));
    ds_native_deny('Client cannot search an unoffered extension',fn()=>provider_domain_client_run($one,$policy['id'],bin2hex(random_bytes(16)),'example.com','ds_dns','ds_read'));
    ds_native_ok('Rejected client requests never reach DNS or credentials',$sent===$before&&$dns===$beforeDns&&ds_count('provider_domain_client_searches')===0);
    ds_native_ok('Client intent audit failure prevents any request',ds_audit_fail('domain_client_started',fn()=>dsc_run($one,$policy))&&$sent===$before&&$dns===$beforeDns&&ds_count('provider_domain_client_searches')===0);
    $body='{"header":{"names":["example.invalid"]}}'."\n".'{"name":"example.invalid","can":"register"}'."\n".'{"name":"suggested.invalid","can":"register"}';
    $response=new \CajunWebPro\Foundation\Provider\TransportResponse(200,[],$body);dsc_age();$request=bin2hex(random_bytes(16));$result=dsc_run($one,$policy,$request);
    $intent=one('SELECT * FROM '.table('provider_domain_client_searches').' WHERE id=?',[$result]);
    ds_native_ok('Client lookup uses the existing authenticated bounded reader',$sent===$before+1&&dsc_result($result)['state']==='checked');
    ds_native_ok('Client identity and exact owner policy are retained separately',(int)$intent['client_id']===$one&&$intent['policy_id']===$policy['id']&&!array_key_exists('actor_id',$intent)&&$intent['profile_id']===$profileId&&(int)$intent['config_revision']===7);
    $audit=one('SELECT user_id,detail FROM '.table('audit').' WHERE action=? AND detail=?',['provider.domain_client_started','Client '.$one.' search '.$result]);
    ds_native_ok('Client audit never impersonates a staff owner',(int)$audit['user_id']===0);
    $html=dsc_render($one);$projection=provider_domain_client_history($one);
    ds_native_ok('Native client form shows checked availability and escaped query',str_contains($html,'Available when checked')&&str_contains($html,'name="search_request"')&&str_contains($html,'name="csrf"')&&str_contains($html,'suggested.invalid'));
    ds_native_ok('Client projection omits provider and owner grant secrets',!str_contains(json_encode($projection),$profileId)&&!str_contains(json_encode($projection),$secret)&&!str_contains(json_encode($projection),$policy['credential_sha256'])&&!str_contains($html,$profileId));
    $_POST=['domain_query'=>'<script>bad</script>'];$escaped=dsc_render($one);$_POST=[];ds_native_ok('Client search retains entered text without executing markup',str_contains($escaped,'&lt;script&gt;bad&lt;/script&gt;')&&!str_contains($escaped,'<script>bad</script>'));
    $sentBefore=$sent;$rowsBefore=ds_count('provider_domain_client_searches');dsc_run($one,$policy,$request);
    ds_native_ok('Same client request replay is inert even during cooldown',$sent===$sentBefore&&ds_count('provider_domain_client_searches')===$rowsBefore);
    ds_native_deny('A reused request cannot change its query',fn()=>provider_domain_client_run($one,$policy['id'],$request,'other.invalid','ds_dns','ds_read'));
    dsc_login($two);ds_native_ok('Second client cannot read first client history',provider_domain_client_history($two)===[]);
    ds_native_deny('Second client cannot replay first client request',fn()=>dsc_run($two,$policy,$request));
    ds_native_deny('Clients share one provider cooldown',fn()=>dsc_run($two,$policy));
    ds_actor();ds_native_deny('Client lookup consumes owner cooldown too',fn()=>ds_run($profileId,2,7));
    dsc_cool();ds_run($profileId,2,7);dsc_login($one);ds_native_deny('Owner lookup consumes client cooldown too',fn()=>dsc_run($one,$policy));
    dsc_cool();$sentBefore=$sent;
    $result=dsc_run($one,$policy,null,function()use($one):string {query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=?',[$one]);return ds_dns();});
    ds_native_ok('Client deactivation before credential access discards lookup',dsc_result($result)['state']==='access_changed'&&dsc_result($result)['result']==='null'&&$sent===$sentBefore);
    query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=?',[$one]);dsc_login($one);dsc_cool();
    $result=dsc_run($one,$policy,null,null,function($row,$key,$address,$authorize,$query)use($one):array {$value=ds_read($row,$key,$address,$authorize,$query);dsc_enable(false);dsc_login($one);return $value;});
    ds_native_ok('Owner revocation before result removes availability and fingerprint',dsc_result($result)['state']==='access_changed'&&dsc_result($result)['result']==='null'&&dsc_result($result)['key_fingerprint']==='');
    ds_native_ok('Disabled search preserves that clients own previous results',count(provider_domain_client_history($one))===3);
    $policy=dsc_enable();dsc_login($one);dsc_cool();$sentBefore=$sent;
    $request=bin2hex(random_bytes(16));ds_native_ok('Client result audit failure rolls back result but retains spent intent',ds_audit_fail('domain_client_finished',fn()=>dsc_run($one,$policy,$request))&&one('SELECT id FROM '.table('provider_domain_client_searches').' WHERE id=?',[$request])&&!one('SELECT id FROM '.table('provider_domain_client_results').' WHERE id=?',[$request])&&$sent===$sentBefore+1);
    $sentBefore=$sent;dsc_run($one,$policy,$request);ds_native_ok('Incomplete client request replay never sends again',$sent===$sentBefore);
    dsc_cool();$response=new \CajunWebPro\Foundation\Provider\TransportResponse(429,[],'PRIVATE_PROVIDER_DETAIL');dsc_run($one,$policy);dsc_cool();
    dsc_login($two);ds_native_deny('Provider client rate limit pauses every client',fn()=>dsc_run($two,$policy));ds_actor();ds_native_deny('Provider client rate limit pauses owner searches too',fn()=>ds_run($profileId,2,7));
    query('UPDATE '.table('provider_domain_client_results').' SET finished_epoch=finished_epoch-86401 WHERE reason=?',['rate_limited']);$response=new \CajunWebPro\Foundation\Provider\TransportResponse(200,[],$body);dsc_age();
    $workers=[];
    foreach([$one,$two] as $client){$process=proc_open([PHP_BINARY,__DIR__.'/provider_domain_search_native.php','concurrent-client-search',(string)$client,$policy['id']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Cannot create isolated client process.');stream_set_timeout($pipes[1],20);stream_set_timeout($pipes[2],20);if(trim((string)fgets($pipes[1]))!=='ready')throw new RuntimeException('Client process did not reach barrier.');$workers[]=[$process,$pipes];}
    foreach($workers as [$process,$pipes]){fwrite($pipes[0],"start\n");fflush($pipes[0]);fclose($pipes[0]);}
    $outcomes=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||$error!=='')throw new RuntimeException('Client process failed: '.$error);$outcomes[]=json_decode($out,true,8,JSON_THROW_ON_ERROR);}
    ds_native_ok('Concurrent clients serialize into one shared provider request',count(array_filter($outcomes,fn($r)=>$r['state']==='recorded'))===1&&count(array_filter($outcomes,fn($r)=>$r['state']==='refused'))===1&&array_sum(array_column($outcomes,'sent'))===1);
    dsc_age();ds_actor();
    for($i=0;$i<20;$i++)query('INSERT INTO '.table('provider_domain_searches').' (id,profile_id,profile_revision,config_revision,actor_id,query_name,tlds,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$profileId,2,7,1,'example.invalid','["invalid"]',gmdate('Y-m-d H:i:s',time()-60),time()-60]);
    dsc_login($one);ds_native_deny('Owner requests consume the client hourly budget',fn()=>dsc_run($one,$policy));dsc_age();
    ds_actor();$originalOwner=one('SELECT auth_version FROM '.table('users').' WHERE id=?',[$policy['owner_id']]);query('UPDATE '.table('users').' SET auth_version=? WHERE id=?',[bin2hex(random_bytes(16)),$policy['owner_id']]);dsc_login($one);ds_native_deny('Revoked approving owner authentication invalidates client grant',fn()=>dsc_run($one,$policy));query('UPDATE '.table('users').' SET auth_version=? WHERE id=?',[$originalOwner['auth_version'],$policy['owner_id']]);
    $credential=one('SELECT credentials FROM '.table('provider_profiles').' WHERE id=?',[$profileId]);query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',['different-encrypted-context',$profileId]);ds_native_deny('Changed provider credential context cannot reuse client grant',fn()=>dsc_run($one,$policy));query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$credential['credentials'],$profileId]);
    $oldPolicy=one('SELECT id FROM '.table('provider_domain_client_policies').' ORDER BY revision LIMIT 1');save_setting('provider_domain_client_policy',$oldPolicy['id']);ds_native_deny('Restoring an old pointer cannot revive revoked policy',fn()=>provider_domain_client_policy());save_setting('provider_domain_client_policy',$policy['id']);
    $saved=all('SELECT * FROM '.table('provider_domain_client_searches').' ORDER BY id');ds_actor();provider_domain_client_prepare($password);ds_native_ok('Client preparation preserves original intents exactly',$saved===all('SELECT * FROM '.table('provider_domain_client_searches').' ORDER BY id'));
    db()->exec('RENAME TABLE '.table('provider_domain_client_results').' TO pds_saved_client_results');
    try{ds_native_deny('Missing client history stops shared owner budget',fn()=>ds_run($profileId,2,7));ds_native_deny('Client preparation never recreates lost result history',fn()=>provider_domain_client_prepare($password));save_setting('provider_domain_client_schema_version','');ds_native_deny('Lost client marker and result table remain recoverable without DDL',fn()=>provider_domain_client_prepare($password));ds_native_ok('Lost client results were not recreated',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',['pds_provider_domain_client_results']));}
    finally{save_setting('provider_domain_client_schema_version','1');db()->exec('RENAME TABLE pds_saved_client_results TO '.table('provider_domain_client_results'));}
    dsc_login($one);ds_native_ok('Exact client history restoration recovers private history',provider_domain_client_family()&&count(provider_domain_client_history($one))>=5);
    ds_actor();provider_domain_search_configure($profileId,2,7,true,'invalid',$password);dsc_login($one);ds_native_deny('Changed extension settings require new explicit client approval',fn()=>dsc_run($one,$policy));
    $report=['passed'=>true,'checks'=>array_slice($checks,$first),'scope'=>'Native client domain lookup and shared limits, synthetic provider only.'];file_put_contents(__DIR__.'/../qa/provider-domain-client-native-results.json',json_encode($report,JSON_PRETTY_PRINT)."\n");
}
