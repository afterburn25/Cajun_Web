<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/php_controls_lifecycle_document.php';
foreach (['provider/ProviderCredential','provider/HttpTransport','provider/TransportRequest','provider/TransportResponse','provider/TransportException','twentyi-package-php/PackagePhpVersionObservation','mail-transport/EndpointPolicy'] as $class) require_once CWP_CODE_ROOT.'/includes/foundation/'.$class.'.php';
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersionBinding,PackagePhpVersion,PackagePhpVersionObservation,PackagePhpOptionSet};
use CajunWebPro\Foundation\Provider\{ProviderCredential,HttpTransport,TransportRequest,TransportResponse,TransportException};

// Execute the exact service bodies with modeled local SQL/session/storage seams.
// No database, real DNS/socket, secret vault, migration or native authority runs.
$path=CWP_CODE_ROOT.'/app/providers/php_controls_reads.php';$source=file_get_contents($path);
$start=strpos($source,'use CajunWebPro\\Foundation\\MailTransport\\EndpointPolicy;');
if ($start===false || substr_count($source,'final class ProviderPhpControlsReadAccessChanged')!==1) throw new RuntimeException('Unexpected read-service source.');
$limitsSource=file_get_contents(CWP_CODE_ROOT.'/app/providers/php_controls_read_limits.php');
$limitsStart=strpos($limitsSource,'/** Admission only,');
if ($limitsStart===false) throw new RuntimeException('Unexpected read-limit source.');
eval(substr($limitsSource,$limitsStart));
eval(substr($source,$start));
set_error_handler(static function(int $severity,string $message,string $file,int $line):never { throw new ErrorException($message,0,$severity,$file,$line); });

$checks=[];$env=[];
function pr_id(int $id):string { return str_pad(dechex($id),32,'0',STR_PAD_LEFT); }
function pr_hash(int $id):string { return str_pad(dechex($id),64,'0',STR_PAD_LEFT); }
function pr_reset():void {
    global $env;
    $env=['lock'=>0,'tx'=>false,'events'=>[],'reads'=>[],'results'=>[],'audits'=>[],'refreshes'=>[],'checks'=>[],
        'ready'=>true,'damaged'=>false,'owner'=>true,'client'=>true,'contact'=>false,'support'=>false,'delegated'=>true,
        'revision'=>1,'key'=>'synthetic-package-key','unseal_fail'=>false,'read_fail'=>false,'result_fail'=>false,'get_wrong'=>false,'reauthorize_calls'=>0,'revoke_on_final'=>false];
}
function pr_event(string $name):void { $GLOBALS['env']['events'][]=$name; }
function pr_boundary(string $name):void {
    if ($GLOBALS['env']['lock']<1 || $GLOBALS['env']['tx']) throw new LogicException('Invalid external boundary '.$name);
    pr_event($name);
}
final class PrDatabase {
    public function inTransaction():bool { return $GLOBALS['env']['tx']; }
}
function db():PrDatabase { return new PrDatabase(); }
function staff_locked(callable $fn):mixed {
    $GLOBALS['env']['lock']++;pr_event('lock');
    try { return $fn(); } finally { $GLOBALS['env']['lock']--;pr_event('unlock'); }
}
function staff_transaction(callable $fn):mixed {
    global $env;
    if ($env['tx'] || $env['lock']<1) throw new LogicException('Invalid modeled transaction.');
    $save=[$env['reads'],$env['results'],$env['audits']];$env['tx']=true;pr_event('begin');
    try { $value=$fn();pr_event('commit');return $value; }
    catch (Throwable $e) { [$env['reads'],$env['results'],$env['audits']]=$save;pr_event('rollback');throw $e; }
    finally { $env['tx']=false; }
}
function provider_php_controls_guard(bool $tx=false):void {
    if ($GLOBALS['env']['lock']<1 || ($tx&&!$GLOBALS['env']['tx'])) throw new LogicException('Missing modeled guard.');
}
function table(string $name):string { return $name; }
function pr_rows(string $table):array {
    return match($table) {
        'provider_checks'=>$GLOBALS['env']['checks'],'provider_refreshes'=>$GLOBALS['env']['refreshes'],
        'provider_php_control_reads'=>array_map(static function(array $d):array {
            $a=$d['context']['actor'];return ['id'=>$d['id'],'profile_id'=>$d['context']['binding']['profile_id'],'started_epoch'=>$d['started_epoch'],'actor_staff_id'=>$a['kind']==='staff'?$a['id']:null,'actor_client_id'=>$a['kind']==='client'?$a['id']:null];
        },array_values($GLOBALS['env']['reads'])),default=>throw new LogicException('Unexpected modeled table.'),
    };
}
function one(string $sql,array $params=[]):?array {
    provider_php_controls_guard(true);
    if (!preg_match('/\ASELECT id FROM (provider_php_control_reads|provider_refreshes|provider_checks) WHERE profile_id=\? AND started_epoch>\? LIMIT 1\z/',$sql,$m)) throw new LogicException('Unexpected modeled SELECT.');
    foreach(pr_rows($m[1]) as $r) if ($r['profile_id']===$params[0] && $r['started_epoch']>$params[1]) return ['id'=>$r['id']];
    return null;
}
final class PrCount { public function __construct(private int $value) {} public function fetchColumn():int { return $this->value; } }
function query(string $sql,array $params=[]):PrCount {
    provider_php_controls_guard(true);
    if (!preg_match('/\ASELECT COUNT\(\*\) FROM (provider_php_control_reads|provider_refreshes|provider_checks) WHERE (actor_staff_id|actor_client_id|actor_id)=\? AND started_epoch>\?\z/',$sql,$m)) throw new LogicException('Unexpected modeled COUNT.');
    return new PrCount(count(array_filter(pr_rows($m[1]),static fn(array $r):bool=>$r[$m[2]]===$params[0]&&$r['started_epoch']>$params[1])));
}
function provider_inventory_fingerprint(string $secret):string { return hash_hmac('sha256',$secret,'synthetic-vault'); }
function pr_context(string $kind,int $id=7):array {
    $b=new PackagePhpVersionBinding(['profile_id'=>pr_id(1),'profile_revision'=>$GLOBALS['env']['revision'],'key_fingerprint'=>provider_inventory_fingerprint('synthetic-package-key'),'credential_sha256'=>pr_hash(2),'package_id'=>701,'client_id'=>7,'link_id'=>pr_id(3),'link_sha256'=>pr_hash(4),'event_id'=>5,'event_sha256'=>pr_hash(6),'audit_id'=>7,'audit_sha256'=>pr_hash(8),'source_id'=>pr_id(9),'source_seq'=>10,'source_sha256'=>pr_hash(11)]);
    $d=['format'=>1,'operation'=>'packages.phpversion','actor'=>['kind'=>$kind,'id'=>$id,'auth_version'=>str_repeat($kind==='staff'?'a':'b',32)],'binding'=>$b->data,'permission_id'=>$kind==='client'?pr_id(12):null,'permission_sha256'=>$kind==='client'?pr_hash(13):null];
    return ['document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d),'binding'=>$b];
}
function provider_settings_owner(string $password):array {
    provider_php_controls_guard(true);pr_event('owner');
    if (!$GLOBALS['env']['owner'] || $GLOBALS['env']['support'] || $password!=='correct owner password') throw new RuntimeException('Synthetic owner denied.');
    return ['id'=>7,'auth_version'=>str_repeat('a',32)];
}
function provider_php_controls_owner_context(string $link):array {
    provider_php_controls_guard(true);
    if (!$GLOBALS['env']['owner'] || $GLOBALS['env']['support'] || $link!==pr_id(3)) throw new RuntimeException('Synthetic owner context denied.');
    return pr_context('staff');
}
function provider_php_controls_client_context(int $id,string $link):array {
    provider_php_controls_guard(true);pr_event('client');
    if (!$GLOBALS['env']['client'] || !$GLOBALS['env']['delegated'] || $GLOBALS['env']['contact'] || $GLOBALS['env']['support'] || $id!==7 || $link!==pr_id(3)) throw new RuntimeException('Synthetic client context denied.');
    return pr_context('client');
}
function provider_php_controls_reauthorize(array $context):array {
    provider_php_controls_guard(true);$GLOBALS['env']['reauthorize_calls']++;
    if ($GLOBALS['env']['revoke_on_final'] && $GLOBALS['env']['reauthorize_calls']>=5) $GLOBALS['env']['delegated']=false;
    $a=$context['document']['actor'];$now=$a['kind']==='staff'?provider_php_controls_owner_context(pr_id(3)):provider_php_controls_client_context($a['id'],pr_id(3));
    if ($now['sha256']!==$context['sha256']) throw new RuntimeException('Synthetic current context changed.');
    return $now;
}
function provider_php_controls_connection(array $context):array {
    pr_boundary('connection');
    return staff_transaction(function()use($context):array { provider_php_controls_reauthorize($context);return ['id'=>pr_id(1),'environment'=>'live','credentials'=>'synthetic-ciphertext']; });
}
function provider_settings_unseal(string $id,string $environment,string $ciphertext):string {
    pr_boundary('unseal');
    if ($GLOBALS['env']['unseal_fail']) throw new RuntimeException('Synthetic private error must not escape.');
    return $GLOBALS['env']['key'];
}
function provider_settings_key_value(string $value):string { return $value; }
function provider_php_controls_lifecycle_ready():bool {
    provider_php_controls_guard();
    if ($GLOBALS['env']['damaged']) throw new RuntimeException('Synthetic history damaged.');
    return $GLOBALS['env']['ready'];
}
function provider_php_controls_lifecycle_next(string $kind):int {
    provider_php_controls_guard(true);if ($kind!=='read') throw new LogicException('Unexpected sequence.');return count($GLOBALS['env']['reads'])+1;
}
function provider_php_controls_lifecycle_insert(string $kind,array $d):array {
    provider_php_controls_guard(true);
    if ($kind==='read') {
        provider_php_controls_lifecycle_read($d);pr_event('intent');
        $GLOBALS['env']['reads'][$d['id']]=$d;
        if ($GLOBALS['env']['read_fail']) throw new PDOException('Synthetic intent audit failure.');
        $actor=$d['context']['actor'];
    } elseif ($kind==='result') {
        $read=$GLOBALS['env']['reads'][$d['read_id']]??throw new RuntimeException('Missing intent.');
        provider_php_controls_lifecycle_result($d,$read);pr_event('result');$actor=$read['context']['actor'];
        if (isset($GLOBALS['env']['results'][$d['read_id']])) throw new RuntimeException('Duplicate result.');
        $GLOBALS['env']['results'][$d['read_id']]=$d;
        if ($GLOBALS['env']['result_fail']) throw new PDOException('Synthetic result audit failure.');
    } else throw new LogicException('Unexpected insertion.');
    $GLOBALS['env']['audits'][]=['kind'=>$kind,'actor'=>$actor,'user_id'=>$actor['kind']==='staff'?$actor['id']:0];
    return ['row'=>[],'document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d)];
}
function provider_php_controls_lifecycle_get(string $kind,string $id):?array {
    provider_php_controls_guard(true);
    if ($kind!=='read') throw new LogicException('Unexpected lookup.');
    if ($GLOBALS['env']['get_wrong']) return null;
    $d=$GLOBALS['env']['reads'][$id]??null;return $d?['row'=>[],'document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d)]:null;
}
function pr_resolve():string { pr_boundary('resolver');return '8.8.8.8'; }
function pr_scalar(array $row,string $secret,string $address,callable $authorize,PackagePhpVersionBinding $binding,string $kind):PackagePhpVersionObservation {
    pr_boundary('transport');$authorize();
    $transport=new class implements HttpTransport {
        public function send(TransportRequest $request):TransportResponse {
            pr_boundary('synthetic-send');
            if ($request->method!=='GET' || $request->url!=='https://api.20i.com/package/701/web/phpVersion' || $request->body!==null) throw new LogicException('Unexpected scalar endpoint.');
            return new TransportResponse(200,[],'"php83"');
        }
    };
    return PackagePhpVersion::read($transport,new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string { $authorize();return $secret; }),$binding,time());
}
function pr_options(array $row,string $secret,string $address,callable $authorize,PackagePhpVersionBinding $binding,string $kind):PackagePhpOptionSet {
    pr_boundary('transport');$authorize();return PackagePhpOptionSet::fromNormalizedRows([['value'=>'php83','label'=>'Synthetic PHP 8.3','handler'=>'synthetic-handler']]);
}
function pr_owner(string $kind='current',mixed $resolver='pr_resolve',mixed $transport='pr_scalar'):array {
    return provider_php_controls_read_owner(pr_id(3),$kind,'correct owner password',$resolver,$transport);
}
function pr_client(string $kind='current',mixed $resolver='pr_resolve',mixed $transport='pr_scalar'):array {
    return provider_php_controls_read_client(7,pr_id(3),$kind,$resolver,$transport);
}
function pr_ok(string $name,bool $value):void {
    $GLOBALS['checks'][]=['name'=>$name,'passed'=>$value];if (!$value) throw new RuntimeException($name);
}
function pr_case(string $name,callable $fn):void { pr_reset();pr_ok($name,$fn()===true); }
function pr_denied(callable $fn,string $class=RuntimeException::class):bool {
    try { $fn(); } catch (LogicException|RuntimeException|JsonException $e) { if (!$e instanceof $class) throw $e;return true; }return false;
}
function pr_old(string $bucket,int $actor=7,int $count=1,int $age=60):void {
    for($i=0;$i<$count;$i++) $GLOBALS['env'][$bucket][]=['id'=>pr_id(100+$i),'profile_id'=>pr_id(1),'actor_id'=>$actor,'started_epoch'=>time()-$age];
}
function pr_existing(string $kind,int $actor=7,int $count=1,int $age=60):void {
    for($i=0;$i<$count;$i++) {
        $c=pr_context($kind,$actor);$d=['format'=>1,'id'=>pr_id(200+$i),'seq'=>$i+1,'kind'=>'current','context'=>$c['document'],'started_epoch'=>time()-$age];
        $GLOBALS['env']['reads'][$d['id']]=$d;
    }
}
function pr_limit(string $bucket):void { staff_locked(fn()=>staff_transaction(fn()=>provider_php_controls_old_read_limit($bucket,pr_id(1),7,time()))); }

try {
    pr_case('Owner exact scalar receipt follows committed intent without network transaction',function():bool {
        $r=pr_owner();$d=$GLOBALS['env']['reads'][$r['id']];$v=$GLOBALS['env']['results'][$r['id']];$events=$GLOBALS['env']['events'];
        return array_keys($r)===['id','kind','state','reason']&&$r['state']==='complete'&&$d['context']['actor']['kind']==='staff'&&$v['value']==='php83'&&array_search('intent',$events,true)<array_search('commit',$events,true)&&array_search('commit',$events,true)<array_search('resolver',$events,true)&&array_search('resolver',$events,true)<array_search('unseal',$events,true)&&$GLOBALS['env']['lock']===0&&!$GLOBALS['env']['tx'];
    });
    pr_case('Primary client remains real client in intent and modeled inherited result audit',function():bool {
        $r=pr_client();return $r['state']==='complete'&&$GLOBALS['env']['reads'][$r['id']]['context']['actor']['kind']==='client'&&array_column($GLOBALS['env']['audits'],'user_id')===[0,0];
    });
    pr_case('Default available persists unavailable and never resolves or opens credentials',function():bool {
        $r=pr_client('available',static function():never { throw new Error('Resolver forbidden'); },null);
        return $r['state']==='unavailable'&&$r['reason']==='unexpected_response'&&!in_array('unseal',$GLOBALS['env']['events'],true)&&$GLOBALS['env']['results'][$r['id']]['options']===null;
    });
    pr_case('Synthetic available seam retains exact typed set identity',function():bool {
        $r=pr_owner('available','pr_resolve','pr_options');$d=$GLOBALS['env']['results'][$r['id']];return $r['state']==='complete'&&$d['value']===null&&$d['options_sha256']===PackagePhpOptionSet::fromNormalizedRows($d['options'])->sha256;
    });
    pr_case('Empty synthetic options are complete but select no version',function():bool {
        $r=pr_client('available','pr_resolve',static fn()=>PackagePhpOptionSet::fromNormalizedRows([]));$d=$GLOBALS['env']['results'][$r['id']];return $d['options']===[]&&pr_denied(fn()=>PackagePhpOptionSet::fromNormalizedRows($d['options'])->select('php83'),LogicException::class);
    });
    pr_case('Inherited transaction refuses before mutex or durable work',function():bool {
        $GLOBALS['env']['tx']=true;return pr_denied(fn()=>pr_owner(),LogicException::class)&&$GLOBALS['env']['events']===[];
    });
    pr_case('Unknown read kind has no intent',function():bool { return pr_denied(fn()=>pr_owner('write'),LogicException::class)&&$GLOBALS['env']['reads']===[]; });
    pr_case('No caller supplied actor combination can bypass wrapper identity',function():bool {
        return pr_denied(fn()=>provider_php_controls_read_attempt(pr_id(3),'current',7,'password',null,null),LogicException::class)&&pr_denied(fn()=>provider_php_controls_read_attempt(pr_id(3),'current',null,null,null,null),LogicException::class);
    });
    pr_case('Wrong owner password creates no intent',function():bool { return pr_denied(fn()=>provider_php_controls_read_owner(pr_id(3),'current','wrong'))&&$GLOBALS['env']['reads']===[]; });
    foreach (['owner','client','delegated'] as $field) pr_case('Current modeled authority denial '.$field,function()use($field):bool {
        $GLOBALS['env'][$field]=false;return pr_denied(fn()=>$field==='owner'?pr_owner():pr_client())&&$GLOBALS['env']['reads']===[];
    });
    foreach (['contact','support'] as $field) pr_case('Primary-client wrapper refuses modeled '.$field,function()use($field):bool {
        $GLOBALS['env'][$field]=true;return pr_denied(fn()=>pr_client())&&$GLOBALS['env']['reads']===[];
    });
    pr_case('Wrong primary client ID cannot create intent',function():bool { return pr_denied(fn()=>provider_php_controls_read_client(8,pr_id(3),'current'))&&$GLOBALS['env']['reads']===[]; });
    foreach (['ready','damaged'] as $field) pr_case('Unready or damaged history denies before network '.$field,function()use($field):bool {
        $GLOBALS['env'][$field]=$field==='damaged';return pr_denied(fn()=>pr_owner())&&$GLOBALS['env']['reads']===[]&&!in_array('resolver',$GLOBALS['env']['events'],true);
    });
    pr_case('Intent audit failure rolls back before any resolver',function():bool {
        $GLOBALS['env']['read_fail']=true;return pr_denied(fn()=>pr_owner(),PDOException::class)&&$GLOBALS['env']['reads']===[]&&!in_array('resolver',$GLOBALS['env']['events'],true);
    });
    pr_case('Result audit failure retains original pending intent without false receipt',function():bool {
        $GLOBALS['env']['result_fail']=true;return pr_denied(fn()=>pr_owner(),PDOException::class)&&count($GLOBALS['env']['reads'])===1&&$GLOBALS['env']['results']===[];
    });
    pr_case('Missing exact original cannot fall back to any latest intent',function():bool {
        $GLOBALS['env']['get_wrong']=true;return pr_denied(fn()=>pr_owner())&&count($GLOBALS['env']['reads'])===1&&$GLOBALS['env']['results']===[];
    });
    pr_case('Resolver refusal records finite unavailable without credentials',function():bool {
        $r=pr_owner('current',static fn()=>'127.0.0.1');return $r['reason']==='unavailable'&&!in_array('unseal',$GLOBALS['env']['events'],true);
    });
    pr_case('Changed context at resolver discards result before unseal',function():bool {
        $r=pr_owner('current',static function():string { pr_boundary('resolver');$GLOBALS['env']['revision']=2;return '8.8.8.8'; });return $r['reason']==='access_changed'&&!in_array('unseal',$GLOBALS['env']['events'],true);
    });
    pr_case('Credential unseal failure retains no private text',function():bool {
        $GLOBALS['env']['unseal_fail']=true;$r=pr_owner();return $r['reason']==='credential'&&!str_contains(json_encode($GLOBALS['env']['results']),'Synthetic private error');
    });
    pr_case('Changed secret HMAC prevents transport',function():bool {
        $GLOBALS['env']['key']='other-key';$r=pr_owner();return $r['reason']==='access_changed'&&!in_array('transport',$GLOBALS['env']['events'],true);
    });
    pr_case('Revocation after returned scalar discards positive observation',function():bool {
        $r=pr_client('current','pr_resolve',static function(...$args):PackagePhpVersionObservation { $o=pr_scalar(...$args);$GLOBALS['env']['delegated']=false;return $o; });return $r['reason']==='access_changed'&&$GLOBALS['env']['results'][$r['id']]['value']===null;
    });
    pr_case('Final short transaction independently reauthorizes',function():bool {
        $GLOBALS['env']['revoke_on_final']=true;$r=pr_client('available','pr_resolve',static fn()=>PackagePhpOptionSet::fromNormalizedRows([]));return $r['reason']==='access_changed';
    });
    foreach (['connect','tls','unavailable','timeout','protocol','unknown'] as $reason) pr_case('Finite transport exception '.$reason,function()use($reason):bool {
        $r=pr_owner('current','pr_resolve',static fn()=>throw new TransportException($reason));return in_array($r['state'],['unknown','unavailable'],true)&&$GLOBALS['env']['results'][$r['id']]['value']===null;
    });
    foreach (['authentication','rate_limited','remote_error','unexpected_status','response_limit','malformed_body','unexpected_shape','invalid_value','transport_connect','transport_tls','transport_timeout','transport_protocol','transport_unavailable','transport_unknown'] as $reason) pr_case('Existing scalar reader failure projection '.$reason,function()use($reason):bool {
        $r=pr_owner('current','pr_resolve',static fn($p,$s,$a,$auth,$b)=>new PackagePhpVersionObservation($b,time(),'unavailable',$reason,null));return $r['state']!=='complete'&&$GLOBALS['env']['results'][$r['id']]['value']===null;
    });
    pr_case('PDO transport failure is not swallowed into a denial pass',function():bool {
        return pr_denied(fn()=>pr_owner('current','pr_resolve',static fn()=>throw new PDOException('Synthetic DB fault')),PDOException::class)&&count($GLOBALS['env']['reads'])===1&&$GLOBALS['env']['results']===[];
    });
    pr_case('Unexpected engine error propagates and leaves intent incomplete',function():bool {
        $caught=false;try { pr_owner('current','pr_resolve',static fn()=>throw new TypeError('Synthetic engine fault')); } catch (TypeError) { $caught=true; }return $caught&&count($GLOBALS['env']['reads'])===1&&$GLOBALS['env']['results']===[]&&$GLOBALS['env']['lock']===0;
    });
    pr_case('Wrong typed result does not get persisted',function():bool {
        return pr_denied(fn()=>pr_owner('current','pr_resolve',static fn()=>PackagePhpOptionSet::fromNormalizedRows([])),LogicException::class)&&$GLOBALS['env']['results']===[];
    });
    pr_case('Wrong package result does not get persisted',function():bool {
        return pr_denied(fn()=>pr_owner('current','pr_resolve',static function($p,$s,$a,$auth,$b):PackagePhpVersionObservation { $d=$b->data;$d['package_id']=702;return new PackagePhpVersionObservation(new PackagePhpVersionBinding($d),time(),'complete','','php83'); }),LogicException::class)&&$GLOBALS['env']['results']===[];
    });
    foreach ([-1,31] as $offset) pr_case('Wrong observation time refused '.$offset,function()use($offset):bool {
        return pr_denied(fn()=>pr_owner('current','pr_resolve',static fn($p,$s,$a,$auth,$b)=>new PackagePhpVersionObservation($b,time()+$offset,'complete','','php83')),LogicException::class)&&$GLOBALS['env']['results']===[];
    });
    foreach (['current','available'] as $kind) pr_case('Credential echo cannot persist from synthetic '.$kind,function()use($kind):bool {
        $r=pr_owner($kind,'pr_resolve',static fn($p,$s,$a,$auth,$b)=>$kind==='current'?new PackagePhpVersionObservation($b,time(),'complete','',$s):PackagePhpOptionSet::fromNormalizedRows([['value'=>'php83','label'=>base64_encode($s),'handler'=>'synthetic']]));return $r['reason']==='unexpected_response'&&$GLOBALS['env']['results'][$r['id']]['value']===null&&$GLOBALS['env']['results'][$r['id']]['options']===null;
    });
    foreach (['checks','refreshes'] as $bucket) pr_case('New profile cooldown includes old '.$bucket,function()use($bucket):bool { pr_old($bucket,7,1,0);return pr_denied(fn()=>pr_client())&&$GLOBALS['env']['reads']===[]; });
    pr_case('New profile cooldown spans different actors',function():bool { pr_existing('staff',99,1,0);return pr_denied(fn()=>pr_client())&&count($GLOBALS['env']['reads'])===1; });
    pr_case('New staff hourly limit sums three original buckets',function():bool { pr_old('checks',7,7);pr_old('refreshes',7,7);pr_existing('staff',7,6);return pr_denied(fn()=>pr_owner())&&count($GLOBALS['env']['reads'])===6; });
    pr_case('Nineteen staff reads allow one more',function():bool { pr_old('checks',7,7);pr_old('refreshes',7,7);pr_existing('staff',7,5);return pr_owner()['state']==='complete'; });
    pr_case('Equal-numbered staff history does not consume client hourly limit',function():bool { pr_old('checks',7,20);pr_old('refreshes',7,20);pr_existing('staff',7,20);return pr_client()['state']==='complete'; });
    pr_case('Client hourly limit counts only actual matching client',function():bool { pr_existing('client',7,20);return pr_denied(fn()=>pr_client())&&count($GLOBALS['env']['reads'])===20; });
    foreach (['refresh','check'] as $bucket) pr_case('Clean absent history leaves legacy bucket unchanged '.$bucket,function()use($bucket):bool { $GLOBALS['env']['ready']=false;pr_limit($bucket);return true; });
    foreach (['refresh','check'] as $bucket) pr_case('Damaged established history blocks legacy bucket '.$bucket,function()use($bucket):bool { $GLOBALS['env']['damaged']=true;return pr_denied(fn()=>pr_limit($bucket)); });
    foreach (['refresh','check'] as $bucket) pr_case('New client read also cools down legacy profile '.$bucket,function()use($bucket):bool { pr_existing('client',7,1,0);return pr_denied(fn()=>pr_limit($bucket)); });
    foreach (['refresh','check'] as $bucket) pr_case('Legacy bucket includes new staff hourly reads '.$bucket,function()use($bucket):bool { pr_old($bucket==='refresh'?'refreshes':'checks',7,10);pr_existing('staff',7,10);return pr_denied(fn()=>pr_limit($bucket)); });
    foreach (['refresh','check'] as $bucket) pr_case('Legacy buckets do not merge each other '.$bucket,function()use($bucket):bool { pr_old('refreshes',7,19);pr_old('checks',7,19);pr_limit($bucket);return true; });
    pr_case('Legacy staff bucket excludes equal-numbered client quota',function():bool { pr_existing('client',7,20);pr_limit('refresh');return true; });
    pr_case('Outer owning mutex remains held after nested read returns',function():bool { return staff_locked(function():bool { $r=pr_client();return $r['state']==='complete'&&$GLOBALS['env']['lock']===1&&!$GLOBALS['env']['tx']; })&&$GLOBALS['env']['lock']===0; });
    pr_case('Two explicit reads retain different IDs without latest-success fallback',function():bool {
        $first=pr_owner('available',null,null);$firstIntent=$GLOBALS['env']['reads'][$first['id']];
        // Modeled time setup only; real native tests must wait for the cooldown.
        $GLOBALS['env']['reads'][$first['id']]['started_epoch']-=31;
        $second=pr_client('available',null,null);return $first['id']!==$second['id']&&$second['id']===array_key_last($GLOBALS['env']['reads'])&&$firstIntent['id']===$first['id'];
    });
    echo json_encode(['passed'=>true,'count'=>count($checks),'scope'=>'Actual read-service bodies and unchanged scalar reader with modeled SQL/authority/storage and synthetic transport only; no native/provider acceptance.','source_sha256'=>hash('sha256',$source),'limits_sha256'=>hash('sha256',$limitsSource),'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['passed'=>false,'count'=>count($checks),'checks'=>$checks,'failure'=>['class'=>get_class($e),'message'=>$e->getMessage(),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;exit(1);
}
