<?php
declare(strict_types=1);
// Exact production function bodies with modeled SQL/auth/clock-free read seams.
// The actual gateway, registrar builder/classifier and binding classes execute.
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
$repo=dirname(__DIR__);
$service=$repo.'/site/app/providers/registered_nameserver_changes.php';
$storage=$repo.'/site/app/providers/registered_nameserver_changes_storage.php';
$source=file_get_contents($service);$checks=[];
function ck(string $name,bool $value):void {global $checks;if(!$value)throw new RuntimeException($name);$checks[]=['name'=>$name,'passed'=>true];}
function refuse(string $name,callable $fn):void {try{$fn();}catch(LogicException|RuntimeException|JsonException $e){ck($name,true);return;}throw new RuntimeException('Expected refusal: '.$name);}
function body(string $source,string $name):string {
    $start=strpos($source,'function '.$name.'(');if($start===false)throw new LogicException('Missing production function.');$tokens=token_get_all('<?php '.substr($source,$start));$out='';$depth=0;$opened=false;
    foreach(array_slice($tokens,1) as $token){$out.=is_array($token)?$token[1]:$token;if($token==='{'){$depth++;$opened=true;}elseif($token==='}'&&--$depth===0&&$opened)return $out;}throw new LogicException('Unclosed production function.');
}
foreach(['ProviderCapability','ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderOperation','OperationResult','ConnectionCheck','RetryPolicy','LedgerClaim','OperationLedger','ProviderAdapter','ProviderGateway'] as $class)require_once $repo.'/foundation/provider/'.$class.'.php';
require_once $repo.'/foundation/twentyi/TwentyIAdapter.php';
foreach(['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $class)require_once $repo.'/foundation/twentyi-operations/'.$class.'.php';
require_once $repo.'/foundation/mail-transport/EndpointPolicy.php';
require_once $repo.'/foundation/twentyi-registrar-nameservers/RegisteredNameserverBinding.php';
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameserverBinding;
use CajunWebPro\Foundation\TwentyI\Operations\{RegistrarNameserverChange,TwentyIOperationAdapter};
use CajunWebPro\Foundation\Provider\{ProviderGateway,ProviderOperation,ProviderCredential,HttpTransport,TransportRequest,TransportResponse,OperationResult,OperationLedger,LedgerClaim};
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
$imports='use CajunWebPro\\Foundation\\TwentyIRegistrarNameservers\\RegisteredNameserverBinding;use CajunWebPro\\Foundation\\TwentyI\\Operations\\RegistrarNameserverChange;use CajunWebPro\\Foundation\\Provider\\{ProviderOperation,ProviderGateway};use CajunWebPro\\Foundation\\MailTransport\\EndpointPolicy;';
eval($imports.substr($source,strpos($source,'function registered_nameserver_changes_grant_values')));
$s=file_get_contents($storage);foreach(['tables','columns','values','json','digest','hmac','ledger_values','ledger_digest'] as $name)eval(body($s,'registered_nameserver_changes_'.$name));
$ops=file_get_contents($repo.'/site/app/providers/operations.php');foreach(['provider_operations_scope','provider_operations_canonical'] as $name)eval(body($ops,$name));
$classStart=strpos($ops,'final class ProviderOperationLedger');$classEnd=strpos($ops,'/**\n * The canonical',$classStart);if($classEnd===false)$classEnd=strpos($ops,'function provider_operations_scope',$classStart);$classCode=substr($ops,$classStart,$classEnd-$classStart);$classCode=substr($classCode,0,strrpos($classCode,'}')+1);
eval('use CajunWebPro\\Foundation\\Provider\\{OperationLedger,LedgerClaim,OperationResult};'.$classCode);
final class ProviderOperationAccessChanged extends RuntimeException {}
final class ModelDB {public bool $tx=false;public function inTransaction():bool{return $this->tx;}}
final class ModelResult {public function __construct(private mixed $v=null,private int $count=0){}public function fetchColumn():mixed{return $this->v;}public function rowCount():int{return $this->count;}}
$db=new ModelDB();$locked=false;$m=[];$calls=[];
function db():ModelDB {return $GLOBALS['db'];}
function table(string $name):string{return $name;}
function registered_nameservers_guard(bool $transaction=true):void {if(!$GLOBALS['locked']||($transaction&&!db()->inTransaction()))throw new LogicException('Modeled owning guard.');}
function staff_locked(callable $fn):mixed {if($GLOBALS['locked'])throw new LogicException('Unexpected nested lock.');$GLOBALS['locked']=true;try{return $fn();}finally{$GLOBALS['locked']=false;}}
function staff_transaction(callable $fn):mixed {if(db()->tx)throw new LogicException('Nested transaction.');$before=$GLOBALS['m'];db()->tx=true;try{return $fn();}catch(Throwable $e){$GLOBALS['m']=$before;throw $e;}finally{db()->tx=false;}}
function local(callable $fn):mixed {return staff_locked(fn()=>staff_transaction($fn));}
function provider_settings_vault_key():string{return str_repeat('v',32);}
function registered_nameservers_json(array $d):string{return json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
function registered_nameservers_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Invalid ID.');}
function provider_settings_manager():array {$a=$GLOBALS['m']['actor'];if(!$a['active']||$a['role']!=='owner'||$GLOBALS['m']['support']||$a['auth_version']!==$GLOBALS['m']['session_auth'])throw new RuntimeException('Owner authority refused.');return $a;}
function provider_settings_owner(string $password):array {$a=provider_settings_manager();if($password!=='Owner password')throw new InvalidArgumentException('Bad password.');return $a;}
function provider_checks_authority(string $id,int $revision):array {$a=provider_settings_manager();$p=$GLOBALS['m']['profile'];if($p['id']!==$id||$p['revision']!==$revision||!$p['enabled'])throw new RuntimeException('This provider profile changed. Reload before checking.');return [$a,$p];}
function provider_settings_unseal(string $id,string $env,string $cipher):string {$GLOBALS['calls'][]='unseal';return 'synthetic-key';}
function provider_settings_key_value(string $secret):string {return $secret;}
function provider_operations_credential_fingerprint(string $secret):string{return hash_hmac('sha256',$secret,provider_settings_vault_key());}
function provider_operations_ready():bool{return true;}
function registered_nameservers_binding(string $link,int $revision):RegisteredNameserverBinding {$m=$GLOBALS['m'];provider_settings_manager();if(!$m['linked']||$link!==$m['binding']->data['link_id']||$revision!==$m['binding']->data['link_revision'])throw new RuntimeException('This registered-domain assignment changed. Reload it.');if($m['stale'])throw new RuntimeException('Refresh registered domains and confirm this assignment first.');return $m['binding'];}
function registered_nameservers_read_row(string $id):array {if(!isset($GLOBALS['m']['reads'][$id]))throw new RuntimeException('Original read missing.');return $GLOBALS['m']['reads'][$id];}
function registered_nameservers_original(string $id):array {$r=registered_nameservers_read_row($id);return [$r['binding'],['actor_id'=>$GLOBALS['m']['read_actors'][$id],'started_epoch'=>$r['observed_epoch']]];}
function registered_nameservers_refresh(string $link,int $revision,int $profileRevision,string $sourceId,string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->tx)throw new LogicException('Read inherited transaction.');$GLOBALS['calls'][]='refresh';$a=provider_settings_owner($password);$b=registered_nameservers_binding($link,$revision);$n=max(array_column($GLOBALS['m']['reads'],'seq'))+1;$id=str_pad(dechex(100+$n),32,'0',STR_PAD_LEFT);$state=$GLOBALS['m']['read_mode'];$names=$state==='complete'?$GLOBALS['m']['read_names']:null;
    $GLOBALS['m']['reads'][$id]=['id'=>$id,'seq'=>$n,'binding'=>$b,'state'=>$state,'nameservers'=>$names,'observed_epoch'=>time()];$GLOBALS['m']['read_actors'][$id]=$a['id'];return ['id'=>$id,'state'=>$state];
}
function registered_nameserver_changes_ready():bool {registered_nameservers_guard(false);if(!$GLOBALS['m']['ready'])return false;registered_nameserver_changes_authenticated_history();return true;}
function registered_nameserver_changes_counter(string $kind):int{return count($GLOBALS['m'][$kind]);}
function registered_nameserver_changes_insert(string $kind,array $row):void {registered_nameservers_guard();if(array_keys($row)!==registered_nameserver_changes_columns($kind))throw new LogicException('Column shape mismatch.');$GLOBALS['m'][$kind][$row['id']]=$row;if($GLOBALS['m']['fail']===$kind)throw new PDOException('Synthetic audit insertion fault.');}
function all(string $sql,array $args=[]):array {
    foreach(registered_nameserver_changes_tables() as $kind=>$table)if(str_contains($sql,'FROM '.$table.' ')){$rows=array_values($GLOBALS['m'][$kind]);usort($rows,fn($a,$b)=>strcmp($a['id'],$b['id']));if($args)$rows=array_values(array_filter($rows,fn($r)=>strcmp($r['id'],$args[0])>0));return array_slice($rows,0,64);}throw new LogicException('Unmodeled all query: '.$sql);
}
function one(string $sql,array $args=[]):?array {
    $m=$GLOBALS['m'];
    if(str_contains($sql,'FROM provider_operation_grants'))return $m['grants'][$args[0]]??null;
    if(str_contains($sql,'FROM provider_operations'))return $m['ledgers'][$args[0]]??null;
    if(str_contains($sql,'FROM provider_registrar_links'))return $m['linked']&&$args[0]===$m['binding']->data['link_id']?['profile_id'=>$m['profile']['id'],'domain_id'=>14701,'domain'=>'owned.test','revision'=>$m['binding']->data['link_revision']]:null;
    if(str_contains($sql,'FROM registered_nameserver_bindings')){$rows=array_filter($m['reads'],fn($r)=>$r['binding']->data['link_id']===$args[0]);usort($rows,fn($a,$b)=>$b['seq']<=>$a['seq']);return $rows?['id'=>$rows[0]['id']]:null;}
    if(str_contains($sql,'SELECT q.*')){$rows=array_filter($m['request'],function($r)use($args,$m){if($r['profile_id']!==$args[0]||(int)$r['domain_id']!==$args[1])return false;if(($m['terminal'][$r['id']]['disposition']??null)==='not_sent')return false;foreach($m['check'] as $c)if($c['request_id']===$r['id']&&$c['disposition']==='observed')return false;return true;});usort($rows,fn($a,$b)=>$b['grant_seq']<=>$a['grant_seq']);return $rows[0]??null;}
    if(str_contains($sql,'FROM provider_nameserver_change_requests')){if(str_contains($sql,'ORDER BY grant_seq')){$rows=array_values(array_filter($m['request'],fn($r)=>$r['profile_id']===$args[0]&&(int)$r['domain_id']===$args[1]));usort($rows,fn($a,$b)=>$b['grant_seq']<=>$a['grant_seq']);return $rows[0]??null;}return $m['request'][$args[0]]??null;}
    if(str_contains($sql,'FROM provider_nameserver_change_terminals'))return $m['terminal'][$args[0]]??null;
    if(str_contains($sql,'FROM provider_nameserver_change_checks')){$rows=array_values(array_filter($m['check'],fn($r)=>$r['request_id']===$args[0]&&(!str_contains($sql,"disposition='observed'")||$r['disposition']==='observed')));usort($rows,fn($a,$b)=>$m['reads'][$b['receipt_id']]['seq']<=>$m['reads'][$a['receipt_id']]['seq']);return $rows[0]??null;}
    throw new LogicException('Unmodeled one query: '.$sql);
}
function query(string $sql,array $args=[]):ModelResult {
    if(str_contains($sql,'COALESCE(MAX(seq)'))return new ModelResult(max(array_column($GLOBALS['m']['reads'],'seq')));
    if(str_starts_with($sql,'INSERT INTO provider_operation_grants')){$keys=['id','profile_id','operation','scope_fingerprint','profile_revision','scope_params','granted_by','granted_at','expires_epoch','consumed_key','consumed_at'];$g=array_combine($keys,$args);$g['seq']=count($GLOBALS['m']['grants'])+1;$g['revoked_at']=null;$GLOBALS['m']['grants'][$g['id']]=$g;return new ModelResult(null,1);}
    if(str_starts_with($sql,'INSERT INTO provider_operations')){$keys=['id','idempotency_key','fingerprint','status','result_message','attempts','claimed_at','claimed_epoch'];$r=array_combine($keys,$args);if(isset($GLOBALS['m']['ledgers'][$r['idempotency_key']]))throw new LogicException('Unexpected second claim.');$r+=['operation'=>null,'provider_code'=>null,'external_id'=>null,'resolved_epoch'=>null,'reconciled_outcome'=>null,'reconciled_epoch'=>null];$GLOBALS['m']['ledgers'][$r['idempotency_key']]=$r;return new ModelResult(null,1);}
    if(str_starts_with($sql,'UPDATE provider_operations')){$key=$args[7];$r=&$GLOBALS['m']['ledgers'][$key];foreach(['operation','status','result_message','provider_code','external_id','attempts','resolved_epoch'] as $i=>$name)$r[$name]=$args[$i];return new ModelResult(null,1);}
    throw new LogicException('Unmodeled query: '.$sql);
}
function reset_model():void {
    $b=new RegisteredNameserverBinding(['profile_id'=>str_repeat('a',32),'profile_revision'=>1,'key_fingerprint'=>provider_operations_credential_fingerprint('synthetic-key'),'domain_id'=>14701,'domain'=>'owned.test','link_id'=>str_repeat('b',32),'link_revision'=>1,'client_id'=>1,'link_event_id'=>1,'link_event_sha256'=>str_repeat('c',64),'link_audit_id'=>1,'link_audit_sha256'=>str_repeat('d',64),'source_refresh_id'=>str_repeat('e',32),'source_seq'=>1,'source_sha256'=>str_repeat('f',64)]);
    $id=str_repeat('1',32);$GLOBALS['m']=['binding'=>$b,'ready'=>true,'linked'=>true,'stale'=>false,'support'=>false,'actor'=>['id'=>1,'role'=>'owner','active'=>true,'auth_version'=>str_repeat('a',32)],'session_auth'=>str_repeat('a',32),'profile'=>['id'=>str_repeat('a',32),'revision'=>1,'enabled'=>true,'environment'=>'live','credentials'=>'synthetic-cipher'],'reads'=>[$id=>['id'=>$id,'seq'=>2,'binding'=>$b,'state'=>'complete','nameservers'=>['old1.test','old2.test'],'observed_epoch'=>time()]],'read_actors'=>[$id=>1],'request'=>[],'terminal'=>[],'check'=>[],'grants'=>[],'ledgers'=>[],'fail'=>'','read_mode'=>'complete','read_names'=>['new1.test','new2.test']];$GLOBALS['calls']=[];
}
final class SyntheticTransport implements HttpTransport {
    public function send(TransportRequest $request):TransportResponse {registered_nameservers_guard(false);if(db()->tx)throw new LogicException('Network under transaction.');RegistrarNameserverChange::wire($request);$GLOBALS['calls'][]=['wire'=>$request->body,'url'=>$request->url];return new TransportResponse(200,[],'{}');}
}
function perform(array $profile,string $secret,string $address,callable $authorize,ProviderOperation $operation):OperationResult {
    registered_nameservers_guard(false);if(db()->tx)throw new LogicException('Gateway under transaction.');$identity=$profile['id'].':'.$profile['revision'].':'.provider_operations_credential_fingerprint($secret);
    $credential=new ProviderCredential('twenty-i',$identity,function()use($authorize,$secret){$authorize();$GLOBALS['calls'][]='claimed-authorize';return $secret;});return (new ProviderGateway(new TwentyIOperationAdapter(),new SyntheticTransport(),new ProviderOperationLedger()))->perform($credential,$operation);
}
function change(?callable $resolver=null,?callable $send=null,array $desired=['new1.test','new2.test'],string $id='22222222222222222222222222222222'):array {
    $b=$GLOBALS['m']['binding'];return registered_nameserver_changes_change($id,$b->data['link_id'],str_repeat('1',32),$b->sha256,$desired,true,'Owner password',$resolver??function(){registered_nameservers_guard(false);if(db()->tx)throw new LogicException('DNS under transaction.');$GLOBALS['calls'][]='dns';return '8.8.8.8';},$send??'perform');
}
reset_model();
try {
    $form=local(fn()=>registered_nameserver_changes_form(str_repeat('b',32),1));ck('Current form exposes exact complete saved set and binding',$form['eligible']&&$form['old_nameservers']===['old1.test','old2.test']&&$form['binding_sha256']===$m['binding']->sha256);
    $r=change();ck('Real gateway sends only whole disjoint arrays to numeric registrar route',count(array_filter($calls,'is_array'))===1&&array_values(array_filter($calls,'is_array'))[0]===['wire'=>'{"ns":["new1.test","new2.test"],"old-ns":["old1.test","old2.test"]}','url'=>'https://api.20i.com/domain/14701/nameservers']);
    ck('Claimed null operation survives actual credential authorization callback',in_array('claimed-authorize',$calls,true));
    ck('HTTP 200 remains uncertain with exactly one durable attempt',$r['status']==='uncertain'&&$m['ledgers']['nameservers:'.str_repeat('2',32)]['status']==='uncertain'&&$m['ledgers']['nameservers:'.str_repeat('2',32)]['attempts']===1);
    ck('Durable request and consumed owner grant are committed before DNS',count($m['grants'])===1&&count($m['request'])===1&&$m['grants'][str_repeat('2',32)]['consumed_key']==='nameservers:'.str_repeat('2',32));
    ck('Finished uncertain request offers only separate readback',$r['can_verify']===true);
    ck('Pending resource masks ordinary current display',local(fn()=>registered_nameserver_changes_current(str_repeat('b',32)))===false);
    $prior=$calls;$replay=change();ck('Same request replay performs no DNS credential or second send',$calls===$prior&&$replay['id']===$r['id']);
    refuse('Reused request cannot change desired whole set',fn()=>change(desired:['other.test']));
    $m['read_names']=['other.test'];$v=registered_nameserver_changes_verify($r['id'],'Owner password');ck('Different explicit readback remains fenced',$v['last_check']['disposition']==='different'&&local(fn()=>registered_nameserver_changes_current(str_repeat('b',32)))===false);
    $m['read_mode']='unavailable';$v=registered_nameserver_changes_verify($r['id'],'Owner password');ck('Unavailable explicit readback cannot release fence',$v['last_check']['nameservers']===null&&$v['last_check']['disposition']==='unavailable'&&local(fn()=>registered_nameserver_changes_current(str_repeat('b',32)))===false);
    $m['read_mode']='complete';$m['read_names']=['new1.test','new2.test'];$v=registered_nameserver_changes_verify($r['id'],'Owner password');ck('Fresh matching explicit readback releases only after finished ledger',$v['status']==='observed'&&local(fn()=>registered_nameserver_changes_current(str_repeat('b',32)))===true);
    ck('Observed readback disables repeated verify control',$v['can_verify']===false);
    refuse('Repeated observation verification is refused',fn()=>registered_nameserver_changes_verify($r['id'],'Owner password'));
    $original=$m;$m['request'][$r['id']]['domain_id']=14702;refuse('Changed resource index fails authenticated history',fn()=>local(fn()=>registered_nameserver_changes_ready()));$m=$original;
    $m['reads'][str_repeat('1',32)]['nameservers']=['changed.test'];refuse('Original complete old-set mutation is detected',fn()=>local(fn()=>registered_nameserver_changes_ready()));$m=$original;
    $checkId=array_key_first($m['check']);$m['check'][$checkId]['disposition']='observed';refuse('Changed readback disposition cannot release a fence',fn()=>local(fn()=>registered_nameserver_changes_ready()));$m=$original;
    $m['ledgers']['nameservers:'.$r['id']]['attempts']=2;refuse('Changed pinned original ledger is refused',fn()=>local(fn()=>registered_nameserver_changes_ready()));$m=$original;
    foreach([[],['old1.test'],['old1.test','new1.test'],['old1.test','old2.test'],['https://new.test'],['new.test','NEW.test.']] as $i=>$bad){reset_model();refuse('Registrar subset refusal '.$i,fn()=>change(desired:$bad));ck('Subset refusal precedes grant key DNS '.$i,$m['grants']===[]&&$calls===[]);}
    reset_model();$m['reads'][str_repeat('1',32)]['nameservers']=[];refuse('Empty complete current set cannot approve replacement',fn()=>change());
    foreach(['unavailable','unfinished'] as $state){reset_model();$id=str_repeat('3',32);$m['reads'][$id]=['id'=>$id,'seq'=>3,'binding'=>$m['binding'],'state'=>$state,'nameservers'=>null,'observed_epoch'=>time()];$m['read_actors'][$id]=1;refuse('Latest '.$state.' masks older complete admission',fn()=>change());}
    reset_model();$m['reads'][str_repeat('1',32)]['observed_epoch']=time()-901;refuse('Stale complete read is refused',fn()=>change());
    reset_model();$m['reads'][str_repeat('1',32)]['observed_epoch']=time()+31;refuse('Future complete read is refused',fn()=>change());
    foreach(['role','support','auth'] as $state){reset_model();if($state==='role')$m['actor']['role']='administrator';elseif($state==='support')$m['support']=true;else $m['session_auth']=str_repeat('z',32);refuse('Current '.$state.' authority denied before network',fn()=>change());ck('No dispatch after '.$state.' denial',$calls===[]&&$m['request']===[]);}
    reset_model();refuse('Rotated owner cannot receive a new privileged summary',fn()=>change(function(){$GLOBALS['m']['actor']['auth_version']=str_repeat('z',32);return '8.8.8.8';}));ck('Authority rotation at DNS creates no-send terminal',$m['terminal'][str_repeat('2',32)]['disposition']==='not_sent'&&$m['ledgers']===[]&&!in_array('unseal',$calls,true));
    reset_model();$m['fail']='request';refuse('Request audit fault prevents every boundary',fn()=>change());ck('Request fault rolls back grant and request',$m['request']===[]&&$m['grants']===[]&&$calls===[]);
    reset_model();$m['fail']='terminal';refuse('Terminal audit fault preserves original completed ledger',fn()=>change());ck('Completed uncertain ledger survives terminal rollback',count($m['request'])===1&&$m['terminal']===[]&&count($m['ledgers'])===1);$m['fail']='';$s=local(fn()=>registered_nameserver_changes_summary(str_repeat('2',32)));ck('Only exact finished ledger exposes recovery hash',is_string($s['recovery_ledger_sha256'])&&strlen($s['recovery_ledger_sha256'])===64);
    $beforeCalls=$calls;refuse('Wrong reviewed ledger hash cannot recover',fn()=>registered_nameserver_changes_recover_finished($s['id'],str_repeat('0',64),'Owner password'));$r=registered_nameserver_changes_recover_finished($s['id'],$s['recovery_ledger_sha256'],'Owner password');ck('Recovery records attempted witness without resend',$r['recovered_dispatch']&&$r['status']==='uncertain'&&$calls===$beforeCalls);
    reset_model();$r=change(send:fn()=>null);ck('Entered performer without ledger stays uncertain',$r['status']==='uncertain'&&$r['can_verify']===false);refuse('Missing ledger cannot verify or release fence',fn()=>registered_nameserver_changes_verify($r['id'],'Owner password'));
    reset_model();$r=change(send:function($p,$s,$a,$auth,$op){$identity=$p['id'].':'.$p['revision'].':'.provider_operations_credential_fingerprint($s);(new ProviderOperationLedger())->claim($op->idempotencyKey,ProviderGateway::requestFingerprint('twenty-i',$identity,$op),time());});ck('Claimed-only request has no readback control',$r['can_verify']===false);refuse('Claimed-only terminal cannot verify or release fence',fn()=>registered_nameserver_changes_verify($r['id'],'Owner password'));
    reset_model();$r=change();$m['linked']=false;$summary=local(fn()=>registered_nameserver_changes_summary($r['id']));ck('Unlinked original remains readable but unavailable',$summary['domain']==='owned.test'&&!$summary['current_assignment']);ck('Unlinked resource remains fenced by profile and registrar ID',local(fn()=>registered_nameserver_changes_pending(str_repeat('a',32),14701))!==null);refuse('Unlinked request cannot read back through a new assignment',fn()=>registered_nameserver_changes_verify($r['id'],'Owner password'));
    reset_model();$tooLarge=[];for($i=0;$i<64;$i++)$tooLarge[]='ns'.$i.'.'.str_repeat('a',60).'.test';refuse('Full-set approval refuses beyond unchanged grant storage bound',fn()=>change(desired:$tooLarge));ck('Oversize refusal occurs before grant/key/DNS',$m['grants']===[]&&$calls===[]);
    reset_model();$m['ready']=false;ck('Clean absent write storage preserves original current read',local(fn()=>registered_nameserver_changes_current(str_repeat('b',32)))===true);ck('Unprepared form cannot expose approval',local(fn()=>registered_nameserver_changes_form(str_repeat('b',32),1))['eligible']===false);
    reset_model();db()->tx=true;refuse('Inherited transaction refused before owning mutex',fn()=>change());db()->tx=false;ck('Failure releases owning mutex',$locked===false);
    ck('Unknown and SQL errors cannot be hidden as ineligibility',!registered_nameserver_changes_unavailable(new RuntimeException('Original history corrupt'))&&!registered_nameserver_changes_unavailable(new PDOException('Database failure')));
    ck('Service uses neither delta nor between for registrar fields',!str_contains($source,'::between(')&&!str_contains($source,'::delta('));
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'service_sha256'=>hash_file('sha256',$service),'storage_dependency_sha256'=>hash_file('sha256',$storage),'scope'=>'Actual service bodies and gateway/typed contracts; modeled SQL/auth/read/storage. No SQL/network/native acceptance.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage().' at '.$e->getLine()."\n");echo json_encode(['passed'=>false,'checks'=>$checks,'service_sha256'=>hash_file('sha256',$service)],JSON_PRETTY_PRINT),"\n";exit(1);}
