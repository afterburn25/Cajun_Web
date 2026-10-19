<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/support_stackcp_documents.php';
require CWP_CODE_ROOT.'/includes/foundation/twentyi-support-handoff/ClientStackCpAccessRead.php';
require CWP_CODE_ROOT.'/includes/foundation/twentyi-support-handoff/StackCpLaunchSender.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyISupportHandoff\{ClientStackCpAccessRead,ClientStackCpAccessTransport,StackCpLaunchTransport};
// Execute the exact production function bodies. Only database/auth/vault boundaries are modeled.
$source=file_get_contents(CWP_CODE_ROOT.'/app/providers/support_stackcp_launch.php');
$start=strpos($source,'function provider_support_stackcp_launch_actor(');
if ($start===false) throw new RuntimeException('Missing production executor.');
eval('use CajunWebPro\\Foundation\\TwentyISupportHandoff\\{ClientStackCpAccessRead,ClientStackCpAccessFailure,StackCpSupportHandoffContext,StackCpLaunchSender,StackCpLaunchFailure};'.substr($source,$start));
final class SlDb {public bool $tx=false;public function inTransaction():bool{return $this->tx;}}
$database=new SlDb();$locked=false;$checks=[];
const SL_GENERAL='Synthetic-general-key-for-client-launch';const SL_AUTH='Synthetic-auth-key-for-client-launch';
const SL_TOKEN='Synthetic-client-access-token';const SL_SESSION='Synthetic-client-session';
function db():SlDb{return $GLOBALS['database'];}
function staff_locked(callable $fn):mixed {if ($GLOBALS['locked']) throw new LogicException('Nested model mutex.');$GLOBALS['locked']=true;try{return $fn();}finally{$GLOBALS['locked']=false;}}
function staff_transaction(callable $fn):mixed {
    if (db()->tx||!$GLOBALS['locked']) throw new LogicException('Wrong model transaction.');
    $before=$GLOBALS['events'];db()->tx=true;try{return $fn();}catch(Throwable $e){$GLOBALS['events']=$before;throw $e;}finally{db()->tx=false;}
}
function provider_stackcp_guard(bool $tx=false):void {if (!$GLOBALS['locked']||($tx&&!db()->tx)) throw new LogicException('Missing model mutex/transaction.');}
function provider_support_stackcp_actor(string $id):array {
    provider_stackcp_guard(true);
    if (array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION)||($_SESSION['uid']??0)!==2||$id!==$GLOBALS['supportId']||!$GLOBALS['supportValid']||$GLOBALS['supportExpires']<=time()||($_SESSION['auth_version']??'')!==$GLOBALS['staffAuth']) throw new RuntimeException('Authorized support session required.');
    return provider_support_stackcp_principal(['kind'=>'support','id'=>2,'auth_version'=>$GLOBALS['staffAuth'],'client_id'=>1,'client_auth_version'=>$GLOBALS['clientAuth'],'support_id'=>$id,'binding_sha256'=>$GLOBALS['supportBinding'],'expires_epoch'=>$GLOBALS['supportExpires']]);
}
function provider_support_stackcp_events():?array {provider_stackcp_guard();if ($GLOBALS['corrupt']) throw new RuntimeException('Retained history damaged.');if (!$GLOBALS['prepared']) return null;provider_support_stackcp_project($GLOBALS['events']);return $GLOBALS['events'];}
function provider_support_stackcp_permission(string $id,?array $events=null):array {foreach ($events??provider_support_stackcp_events()??[] as $d) if ($d['kind']==='permission'&&$d['id']===$id) return $d;throw new RuntimeException('Missing permission.');}
function provider_support_stackcp_resource(array $p):array{return $p['payload']['resource']['binding'];}
function provider_support_stackcp_current(array $p,?array $events=null):bool {provider_stackcp_guard();return $GLOBALS['current']&&$p['payload']['enabled']&&$p['payload']['resource']['binding']['client_auth_version']===$GLOBALS['clientAuth'];}
function provider_support_stackcp_hmac(string $purpose,string $bytes):string{return hash_hmac('sha256',$purpose.'|'.$bytes,'Synthetic portable proof key');}
function provider_support_stackcp_append(string $kind,string $phase,array $payload,?array $seed=null):array {
    provider_stackcp_guard(true);$events=provider_support_stackcp_events();$state=provider_support_stackcp_project($events);
    $admission=$kind==='admitted'?null:$state['admissions'][$seed['id']]['admission'];
    $permission=provider_support_stackcp_permission($admission['permission_id']??$seed['permission_id'],$events);
    $last=$events[array_key_last($events)];
    $d=provider_support_stackcp_document(['version'=>1,'id'=>$kind==='admitted'?$seed['id']:bin2hex(random_bytes(16)),
        'ordinal'=>count($events)+1,'previous_sha256'=>hash('sha256',provider_support_stackcp_json($last)),
        'permission_id'=>$permission['id'],'admission_id'=>$seed['id'],'profile_id'=>$permission['profile_id'],'package_id'=>$permission['package_id'],
        'client_id'=>$permission['client_id'],'staff_id'=>2,'actor'=>$admission['actor']??$seed['actor'],'recorded_epoch'=>time(),'kind'=>$kind,'phase'=>$phase,'payload'=>$payload]);
    $events[]=$d;provider_support_stackcp_project($events);$GLOBALS['events']=$events;
    if ($GLOBALS['terminalRevoke']&&$kind==='terminal'&&$phase==='delivery') $GLOBALS['current']=false;
    if ($GLOBALS['fault']===$kind.':'.$phase) throw new PDOException('Synthetic immutable event audit rollback.');
    return $d;
}
function provider_settings_load(string $id):array{return ['revision'=>1,'credentials'=>'cipher-general'];}
function provider_settings_unseal(string $id,string $mode,string $cipher):string {++$GLOBALS['keys'];return SL_GENERAL;}
function provider_inventory_fingerprint(string $key):string{return hash('sha256',$key);}
function table(string $name):string{return $name;}
function one(string $sql,array $params):array {return ['ciphertext'=>'cipher-auth','document_sha256'=>str_repeat('a',64),'proof'=>str_repeat('b',64)];}
function config():array{return ['db'=>['prefix'=>'portable_']];}
final class ProviderStackCpAuthVault {public static function context(...$args):string{return 'synthetic context';}public function open(string $context,string $cipher):string{++$GLOBALS['keys'];return SL_AUTH;}}
function provider_stackcp_vault():ProviderStackCpAuthVault{return new ProviderStackCpAuthVault();}
function sl_ok(string $name,bool $passed):void {if (isset($GLOBALS['checks'][$name])) throw new LogicException('Duplicate test name.');$GLOBALS['checks'][$name]=['name'=>$name,'passed'=>$passed];if (!$passed) throw new RuntimeException($name);}
function sl_deny(string $name,callable $fn):void {try{$fn();}catch(Throwable $e){if ($e instanceof Error) throw $e;sl_ok($name,true);return;}sl_ok($name,false);}
function sl_reset():void {
    $GLOBALS['clientAuth']=str_repeat('c',32);$GLOBALS['staffAuth']=str_repeat('d',32);$GLOBALS['supportId']=str_repeat('f',32);$GLOBALS['supportBinding']=str_repeat('f',64);$GLOBALS['supportExpires']=time()+900;$GLOBALS['supportValid']=true;$_SESSION=['uid'=>2,'auth_version'=>$GLOBALS['staffAuth']];
    foreach (['current','prepared'] as $k) $GLOBALS[$k]=true;
    $GLOBALS['corrupt']=false;$GLOBALS['terminalRevoke']=false;$GLOBALS['fault']='';$GLOBALS['mode']='';$GLOBALS['dns']=0;$GLOBALS['keys']=0;$GLOBALS['calls']=[];$GLOBALS['deliveries']=0;$GLOBALS['hook']=null;
    $b=['operation'=>'stackcp.grants','profile_id'=>str_repeat('1',32),'profile_revision'=>1,'credential_sha256'=>hash('sha256','cipher-general'),'key_fingerprint'=>hash('sha256',SL_GENERAL),
        'package_id'=>3501,'client_id'=>1,'client_auth_version'=>$GLOBALS['clientAuth'],'link_id'=>str_repeat('2',32),'link_sha256'=>str_repeat('2',64),'event_id'=>1,'event_sha256'=>str_repeat('3',64),
        'audit_id'=>1,'audit_sha256'=>str_repeat('4',64),'source_id'=>str_repeat('3',32),'source_seq'=>1,'source_sha256'=>str_repeat('5',64),'actor_id'=>9,'actor_auth_version'=>str_repeat('9',32),
        'assignment_id'=>str_repeat('4',32),'assignment_revision'=>1,'assignment_sha256'=>str_repeat('6',64),'assignment_proof'=>str_repeat('7',64),'auth_id'=>str_repeat('5',32),'auth_revision'=>1,
        'auth_sha256'=>str_repeat('a',64),'auth_proof'=>str_repeat('b',64),'auth_ciphertext_sha256'=>hash('sha256','cipher-auth'),'stack_identity'=>'stack-user:351',
        'membership_id'=>str_repeat('6',32),'membership_seq'=>1,'membership_sha256'=>str_repeat('d',64)];
    $r=['binding'=>$b,'grants_id'=>str_repeat('7',32),'grants_seq'=>2,'grants_sha256'=>str_repeat('e',64),'membership_observed_epoch'=>time()-9000,'grants_observed_epoch'=>time()-9000,'staff'=>['id'=>2,'auth_version'=>$GLOBALS['staffAuth']]];
    $p=provider_support_stackcp_document(['version'=>1,'id'=>str_repeat('8',32),'ordinal'=>1,'previous_sha256'=>str_repeat('0',64),'permission_id'=>str_repeat('8',32),'admission_id'=>'',
        'profile_id'=>$b['profile_id'],'package_id'=>3501,'client_id'=>1,'staff_id'=>2,'actor'=>['kind'=>'owner','id'=>9,'auth_version'=>str_repeat('9',32)],'recorded_epoch'=>time()-9000,'kind'=>'permission','phase'=>'permission',
        'payload'=>['revision'=>1,'previous_permission_id'=>'','previous_permission_sha256'=>str_repeat('0',64),'enabled'=>true,'resource'=>$r,'confirmed'=>true]]);
    $GLOBALS['events']=[$p];$GLOBALS['permission']=$p;
}
function sl_review():array{return provider_support_stackcp_review($GLOBALS['permission']['id'],$GLOBALS['supportId']);}
function sl_phase(string $phase):void {
    if (!$GLOBALS['locked']||db()->tx) throw new LogicException('Effect without mutex or with open transaction.');
    $last=$GLOBALS['events'][array_key_last($GLOBALS['events'])];
    if ($last['phase']!==$phase||$last['kind']!==($phase==='delivery'?'terminal':'started')) throw new LogicException('Effect before durable phase.');
}
function sl_dns(string $host):array {if (!$GLOBALS['locked']||db()->tx||$GLOBALS['events'][array_key_last($GLOBALS['events'])]['kind']!=='started') throw new LogicException('Bad DNS boundary or missing durable start.');++$GLOBALS['dns'];if ($GLOBALS['hook']!==null){$hook=$GLOBALS['hook'];$GLOBALS['hook']=null;$hook();}return [['type'=>'A','ip'=>'8.8.8.8']];}
final class SlTransport implements HttpTransport {
    public function __construct(private string $phase,private $authorize){}
    public function send(#[SensitiveParameter] TransportRequest $q):TransportResponse {
        ($this->authorize)();sl_phase($this->phase);
        $read=in_array($this->phase,['membership','grants'],true);
        $actual=$read?new ClientStackCpAccessTransport($this->phase,3501,'stack-user:351','8.8.8.8',$this->authorize):new StackCpLaunchTransport($this->phase,'8.8.8.8',$this->authorize);
        (new ReflectionMethod($actual,'validate'))->invoke($actual,$q);
        $secret=match($this->phase){'membership','grants','brand'=>SL_GENERAL,'token'=>SL_AUTH,'session'=>SL_TOKEN};
        if ($q->headers['Authorization']!=='Bearer '.base64_encode($secret)) throw new LogicException('Wrong credential role.');
        $GLOBALS['calls'][]=$this->phase;$mode=$GLOBALS['mode'];
        if ($mode===$this->phase.'-timeout') throw new TransportException('timeout');
        if ($mode===$this->phase.'-auth') return new TransportResponse(401,[],'untrusted provider string');
        if ($mode===$this->phase.'-malformed') return new TransportResponse(200,[],'{');
        $body=match($this->phase){
            'membership'=>$mode==='membership-missing'?'[{"identity":"stack-user:999"}]':($mode==='membership-echo'?json_encode([['identity'=>'stack-user:351','ignored'=>SL_GENERAL]],JSON_THROW_ON_ERROR):'[{"identity":"stack-user:351"}]'),
            'grants'=>$mode==='grants-extra'?'["package:3501","package:3502"]':'["package:3501"]',
            'brand'=>$mode==='brand-custom'?'{"brandDomain":"custom.invalid"}':'{"brandDomain":null,"brandName":"Synthetic Studio"}',
            'token'=>'{"access_token":"'.SL_TOKEN.'","token_type":"bearer","expires_in":300,"scope":"interactive"}',
            'session'=>'{"session_id":"'.SL_SESSION.'"}',
        };
        if ($mode===$this->phase.'-revoke') $GLOBALS['current']=false;
        if ($mode===$this->phase.'-end') $GLOBALS['supportValid']=false;
        return new TransportResponse(200,[],$body);
    }
}
function sl_factory(string $phase,string $address,callable $authorize):HttpTransport {if ($address!=='8.8.8.8') throw new LogicException('Wrong resolved address.');sl_phase($phase);return new SlTransport($phase,$authorize);}
function sl_delivery(#[SensitiveParameter] string $url):void {sl_phase('delivery');if ($url!=='https://www.stackcp.com/login/sso?PHPSESSID='.rawurlencode(SL_SESSION)) throw new LogicException('Bad handoff.');++$GLOBALS['deliveries'];if ($GLOBALS['mode']==='delivery-throw') throw new RuntimeException('Synthetic terminating browser.');}
function sl_open(array $r,bool $confirmed=true):array{return provider_support_stackcp_open($r['admission_id'],$r['permission_id'],$r['support_id'],$r['expires_epoch'],$r['review_proof'],$confirmed,'sl_delivery','sl_dns','sl_factory','sl_factory');}
function sl_snapshot():array{return [$GLOBALS['events'],$GLOBALS['dns'],$GLOBALS['keys'],$GLOBALS['calls'],$GLOBALS['deliveries']];}
set_error_handler(static function(int $level,string $message):never {throw new ErrorException($message,0,$level);});
try {
    sl_reset();$before=sl_snapshot();$r=sl_review();$history=provider_support_stackcp_history($r['permission_id'],$GLOBALS['supportId']);
    sl_ok('Local review and history do not write, open keys, resolve or send',sl_snapshot()===$before&&$r['available']&&$history['items']===[]);
    sl_ok('Old owner observation does not expire stable support permission',$r['expires_epoch']>=time()+299);
    sl_deny('Explicit consent required',fn()=>sl_open($r,false));sl_ok('Refused consent has no effects',sl_snapshot()===$before);
    foreach (['admission_id','review_proof','expires_epoch'] as $field) {$bad=$r;$bad[$field]=$field==='expires_epoch'?$r[$field]+1:str_repeat('f',$field==='review_proof'?64:32);sl_deny('Changed '.$field.' refuses before admission',fn()=>sl_open($bad));}
    $result=sl_open($r);sl_ok('Actual executor runs two reads and three launch phases in order',$GLOBALS['calls']===['membership','grants','brand','token','session']&&$GLOBALS['deliveries']===1&&$result['state']==='handoff_attempted');
    $events=$GLOBALS['events'];sl_ok('All support phase events retain actual delegated staff and support identity',count($events)===13&&count(array_filter(array_slice($events,1),fn($e)=>$e['actor']['kind']==='support'&&$e['actor']['id']===2&&$e['actor']['support_id']===$GLOBALS['supportId']))===12);
    $before=sl_snapshot();sl_ok('Exact consumed admission replay is inert',sl_open($r)===$result&&sl_snapshot()===$before);
    sl_ok('Public summary contains no credential, token, session or URL',!preg_match('/Synthetic-|PHPSESSID|https:|cipher-|proof|sha256/',json_encode($result,JSON_THROW_ON_ERROR)));
    sl_reset();$first=sl_review();$other=sl_review();sl_open($first);$before=sl_snapshot();sl_deny('Other-window prior-outcome proof becomes stale',fn()=>sl_open($other));sl_ok('Stale other-window form has no effects',sl_snapshot()===$before);
    sl_reset();$oldReview=sl_review();sl_open($oldReview);$p=$GLOBALS['permission'];$last=$GLOBALS['events'][array_key_last($GLOBALS['events'])];$id=bin2hex(random_bytes(16));$next=$p;
    $next['id']=$id;$next['permission_id']=$id;$next['ordinal']=count($GLOBALS['events'])+1;$next['previous_sha256']=hash('sha256',provider_support_stackcp_json($last));$next['recorded_epoch']=time();
    $next['payload']['revision']=2;$next['payload']['previous_permission_id']=$p['id'];$next['payload']['previous_permission_sha256']=hash('sha256',provider_support_stackcp_json($p));
    $GLOBALS['permission']=provider_support_stackcp_document($next);$GLOBALS['events'][]=$GLOBALS['permission'];$renewed=sl_review();$out=sl_open($renewed);
    sl_ok('New delegation keeps previous resource outcome across permission revisions',$out['state']==='handoff_attempted'&&provider_support_stackcp_project($GLOBALS['events'])['admissions'][$renewed['admission_id']]['admission']['payload']['approval']['previous_admission_id']===$oldReview['admission_id']);
    sl_reset();$accepted=[];for ($i=0;$i<5;++$i) {$fresh=sl_review();$accepted[]=[$fresh,sl_open($fresh)];}
    $denied=sl_review();$before=sl_snapshot();sl_deny('Sixth real executor admission is refused at staff rate limit',fn()=>sl_open($denied));
    sl_ok('Rate refusal does not append or consume keys DNS or transport',sl_snapshot()===$before);
    sl_ok('Exact replay remains inert at exhausted admission capacity',sl_open($accepted[0][0])===$accepted[0][1]&&sl_snapshot()===$before);
    $now=time();$limited=[];for ($i=0;$i<30;++$i) $limited[]=['admission'=>['staff_id'=>$i+3,'profile_id'=>str_repeat('1',32),'recorded_epoch'=>$now]];
    sl_deny('Profile rate bound includes other actual staff',fn()=>staff_locked(fn()=>staff_transaction(fn()=>provider_support_stackcp_launch_limit($limited,2,str_repeat('1',32),$now))));
    $limited[0]['admission']['recorded_epoch']=$now-60;staff_locked(fn()=>staff_transaction(fn()=>provider_support_stackcp_launch_limit($limited,2,str_repeat('1',32),$now)));sl_ok('Rolling profile window expires exact sixty-second boundary',true);
    $limited=[];for ($i=0;$i<5;++$i) $limited[]=['admission'=>['staff_id'=>2,'profile_id'=>str_repeat(dechex($i+1),32),'recorded_epoch'=>$now]];
    sl_deny('Staff rate bound counts attempts across different profiles',fn()=>staff_locked(fn()=>staff_transaction(fn()=>provider_support_stackcp_launch_limit($limited,2,str_repeat('a',32),$now))));
    foreach ($limited as &$entry) $entry['admission']['recorded_epoch']=$now-60;unset($entry);
    staff_locked(fn()=>staff_transaction(fn()=>provider_support_stackcp_launch_limit($limited,2,str_repeat('a',32),$now)));sl_ok('Rolling staff window expires without rewriting retained history',true);
    foreach (['membership-missing','membership-echo','membership-malformed','membership-auth','grants-extra','grants-auth','brand-custom'] as $scenario) {
        sl_reset();$r=sl_review();$GLOBALS['mode']=$scenario;$out=sl_open($r);
        sl_ok($scenario.' records not-issued without token or browser',$out['state']==='not_issued'&&!in_array('token',$GLOBALS['calls'],true)&&$GLOBALS['deliveries']===0);
        $before=sl_snapshot();sl_ok($scenario.' replay has no retry',sl_open($r)===$out&&sl_snapshot()===$before);
    }
    foreach (['token-timeout','session-timeout'] as $scenario) {
        sl_reset();$r=sl_review();$GLOBALS['mode']=$scenario;$out=sl_open($r);$before=sl_snapshot();
        sl_ok($scenario.' retains unknown and never retries',$out['state']==='unknown'&&sl_open($r)===$out&&sl_snapshot()===$before&&$GLOBALS['deliveries']===0);
        sl_ok($scenario.' fresh form discloses previous uncertainty',sl_review()['previous_unknown']);
    }
    sl_reset();$r=sl_review();$GLOBALS['mode']='delivery-throw';sl_deny('Throwing browser callback stays consumed',fn()=>sl_open($r));$before=sl_snapshot();$out=sl_open($r);sl_ok('Browser callback cannot redeliver a session',$out['state']==='handoff_attempted'&&sl_snapshot()===$before&&$GLOBALS['deliveries']===1);
    sl_reset();$r=sl_review();$GLOBALS['terminalRevoke']=true;sl_deny('Final current-authority guard runs after durable terminal',fn()=>sl_open($r));$GLOBALS['current']=true;$before=sl_snapshot();$out=sl_open($r);sl_ok('Final guard refusal never delivers or retries',$out['state']==='handoff_attempted'&&$GLOBALS['deliveries']===0&&sl_snapshot()===$before);
    foreach (['started:membership','accepted:membership','started:token','accepted:session','terminal:delivery'] as $faultCase) {
        sl_reset();$r=sl_review();$GLOBALS['fault']=$faultCase;sl_deny($faultCase.' SQL failure propagates',fn()=>sl_open($r));$GLOBALS['fault']='';$before=sl_snapshot();$out=sl_open($r);
        sl_ok($faultCase.' rollback leaves admission consumed without resend',sl_snapshot()===$before&&$GLOBALS['deliveries']===0&&in_array($out['state'],['interrupted','unknown'],true));
    }
    foreach (['contact_id','contact_auth'] as $key) {sl_reset();$r=sl_review();$_SESSION[$key]=null;$before=sl_snapshot();sl_deny($key.' presence denied before normalization',fn()=>sl_open($r));sl_ok($key.' refusal before any effects',sl_snapshot()===$before);}
    sl_reset();$r=sl_review();$_SESSION['uid']=9;sl_deny('Owner cannot impersonate delegated support staff',fn()=>sl_open($r));
    sl_reset();$r=sl_review();unset($_SESSION['uid']);$_SESSION['client_id']=1;sl_deny('Primary client cookie cannot impersonate support staff',fn()=>sl_open($r));
    sl_reset();$_SESSION['client_id']=99;sl_ok('Unrelated ambient client cookie does not select support identity',sl_review()['available']);
    sl_reset();$r=sl_review();$r['support_id']=str_repeat('e',32);sl_deny('Substituted support ID refuses original signed approval',fn()=>sl_open($r));
    sl_reset();$GLOBALS['supportExpires']=time()+100;$r=sl_review();sl_ok('Review never outlives exact support session',$r['expires_epoch']===$GLOBALS['supportExpires']);
    sl_reset();$r=sl_review();$GLOBALS['supportValid']=false;sl_deny('Ended or policy-revoked support refuses before admission',fn()=>sl_open($r));
    sl_reset();$r=sl_review();$GLOBALS['supportExpires']=time();sl_deny('Expired support refuses before admission',fn()=>sl_open($r));
    sl_reset();$r=sl_review();$GLOBALS['supportBinding']=str_repeat('e',64);sl_deny('Changed browser binding invalidates signed approval',fn()=>sl_open($r));
    sl_reset();$r=sl_review();$GLOBALS['staffAuth']=str_repeat('e',32);sl_deny('Rotated staff auth refuses before admission',fn()=>sl_open($r));
    sl_reset();$r=sl_review();$GLOBALS['hook']=static function():void{$GLOBALS['current']=false;};$out=sl_open($r);sl_ok('Authority change during DNS stops before credential opening',$out['state']==='not_issued'&&$GLOBALS['keys']===0&&$GLOBALS['calls']===[]);
    foreach (['membership-revoke','grants-revoke','brand-revoke','token-revoke','session-revoke'] as $scenario) {sl_reset();$r=sl_review();$GLOBALS['mode']=$scenario;$out=sl_open($r);sl_ok($scenario.' refuses subsequent phase and browser',$GLOBALS['deliveries']===0&&$GLOBALS['calls'][array_key_last($GLOBALS['calls'])]===str_replace('-revoke','',$scenario));}
    foreach (['membership','grants','brand','token','session'] as $phase) {sl_reset();$r=sl_review();$GLOBALS['mode']=$phase.'-end';$out=sl_open($r);sl_ok('Support session ending after '.$phase.' stops at exact phase',$out['state']===(in_array($phase,['token','session'],true)?'unknown':'not_issued')&&$GLOBALS['deliveries']===0&&$GLOBALS['calls'][array_key_last($GLOBALS['calls'])]===$phase);}
    sl_reset();$r=sl_review();sl_open($r);$GLOBALS['supportId']=str_repeat('e',32);$r['support_id']=$GLOBALS['supportId'];$before=sl_snapshot();sl_deny('New valid support session cannot replay an old session admission',fn()=>sl_open($r));sl_ok('Cross-session replay leaves all effects unchanged',sl_snapshot()===$before);
    sl_reset();$r=sl_review();$GLOBALS['clientAuth']=str_repeat('d',32);sl_deny('Rotated original client auth denied before effects',fn()=>sl_open($r));
    sl_reset();$GLOBALS['corrupt']=true;sl_deny('History corruption propagates on GET',fn()=>sl_review());sl_ok('Damaged history creates no replacement state',count($GLOBALS['events'])===1);
    sl_reset();$GLOBALS['prepared']=false;sl_ok('Clean absent storage is unavailable without preparation',!sl_review()['ready']&&count($GLOBALS['events'])===1);
    foreach (['[{"identity":"stack-user:351","identity":"stack-user:352"}]','[{"identity":"stack-user:351"},{"identity":"stack-user:351"}]','{}','[1]'] as $i=>$body) sl_deny('Membership parser closed boundary '.$i,fn()=>ClientStackCpAccessRead::project('membership',3501,'stack-user:351',new TransportResponse(200,[],$body),time()));
    foreach (['[]','["package:3501","unknown:1"]','["package:3501","package:3501"]','["package:*"]','[3501]'] as $i=>$body) sl_deny('Grant parser closed boundary '.$i,fn()=>ClientStackCpAccessRead::project('grants',3501,'stack-user:351',new TransportResponse(200,[],$body),time()));
    foreach ([['membership',3501,'stack-user:97','97','[{"identity":"stack-user:\u0039\u0037"}]'],['grants',3501,'stack-user:351','3501','["package:\u0033\u0035\u0030\u0031"]']] as [$phase,$package,$identity,$secret,$body]) {
        $factory=static fn()=>new class($body) implements HttpTransport {public function __construct(private string $body){}public function send(TransportRequest $r):TransportResponse{return new TransportResponse(200,[],$this->body);}};
        sl_deny('Decoded '.$phase.' credential echo cannot enter journal',fn()=>ClientStackCpAccessRead::read($phase,$package,$identity,static fn()=> $secret,static fn()=>null,static fn()=>[['type'=>'A','ip'=>'8.8.8.8']],$factory));
    }
    $fixed=new ClientStackCpAccessTransport('membership',3501,'stack-user:351','8.8.8.8',static fn()=>null);$method=new ReflectionMethod($fixed,'validate');
    foreach ([['GET','https://api.20i.com/package/3502/stackUserList',null],['POST','https://api.20i.com/package/3501/stackUserList','{}'],['GET','https://api.20i.com/package/3501/stackUserList?extra=1',null],['GET','https://auth-api.20i.com:3000/login/authenticate',null]] as $i=>[$verb,$url,$body]) sl_deny('Exact fixed read wire refuses variation '.$i,fn()=>$method->invoke($fixed,new TransportRequest($verb,$url,['Authorization'=>'Bearer '.base64_encode(SL_GENERAL),'Accept'=>'application/json'],$body,15)));
    sl_deny('Private DNS answer refused',fn()=>ClientStackCpAccessTransport::resolve(static fn()=>[['type'=>'A','ip'=>'127.0.0.1']]));
    $out=['passed'=>true,'count'=>count($checks),'checks'=>array_values($checks),'scope'=>'Actual support executor/document/read/sender bodies with modeled storage, support session, authority and vault; no SQL or provider traffic.'];
} catch(Throwable $e) {$out=['passed'=>false,'count'=>count($checks),'checks'=>array_values($checks),'error_class'=>get_class($e),'error'=>$e->getMessage()];}
$json=json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if (isset($argv[1])) file_put_contents($argv[1],$json);else echo $json;
exit($out['passed']?0:1);
