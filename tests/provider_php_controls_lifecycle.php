<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/php_controls_lifecycle_document.php';
require CWP_CODE_ROOT.'/database/schema/provider_php_control_lifecycle_schema.php';
use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpOptionSet;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};
$checks=[];
function pl_ok(string $name,bool $value):void {global $checks;$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function pl_deny(string $name,callable $fn):void {$caught=false;try{$fn();}catch(LogicException|RuntimeException|JsonException){$caught=true;}pl_ok($name,$caught);}
function pl_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function pl_hash(int $n):string{return str_pad(dechex($n),64,'0',STR_PAD_LEFT);}
function pl_read(array $context,string $kind,int $seq,int $epoch):array{return ['format'=>1,'id'=>pl_id(100+$seq),'seq'=>$seq,'kind'=>$kind,'context'=>$context,'started_epoch'=>$epoch];}
function pl_result(array $read,mixed $data,string $state='complete',string $reason=''):array {
    $complete=$state==='complete';$set=$complete&&$read['kind']==='available'?PackagePhpOptionSet::fromNormalizedRows($data):null;
    return ['format'=>1,'read_id'=>$read['id'],'read_sha256'=>provider_php_controls_lifecycle_sha($read),'state'=>$state,'reason'=>$reason,'finished_epoch'=>$read['started_epoch']+1,'value'=>$complete&&$read['kind']==='current'?$data:null,'options'=>$set?->options,'options_sha256'=>$set?->sha256];
}
function pl_request(array $context,array $current,array $cr,array $available,array $ar,int $now):array {
    $r=provider_php_controls_lifecycle_review($context,$current,$cr,$available,$ar,'php83');return ['format'=>1,'id'=>pl_id(300),'seq'=>1,'review'=>$r,'review_sha256'=>provider_php_controls_lifecycle_sha($r),'confirmed'=>true,'approved_epoch'=>$now,'expires_epoch'=>$now+900,'idempotency_key'=>'phpversion:'.pl_id(300)];
}
function pl_ledger(array $request,string $state='uncertain'):array {
    $b=$request['review']['context']['binding'];$op=new ProviderOperation('packages.phpversion',$request['idempotency_key'],$request['review']['params']);$t=$request['approved_epoch']+1;
    return ['id'=>pl_id(400),'idempotency_key'=>$request['idempotency_key'],'fingerprint'=>ProviderGateway::requestFingerprint('twenty-i',$b['profile_id'].':'.$b['profile_revision'].':'.$b['key_fingerprint'],$op),'operation'=>$state==='claimed'?null:'packages.phpversion','status'=>$state,'result_message'=>$state==='claimed'?'':'Provider outcome uncertain.','provider_code'=>null,'external_id'=>null,'attempts'=>$state==='claimed'?0:1,'claimed_at'=>gmdate('Y-m-d H:i:s',$t),'claimed_epoch'=>$t,'resolved_epoch'=>$state==='claimed'?null:$t+1,'reconciled_outcome'=>null,'reconciled_epoch'=>null];
}
function pl_terminal(array $request,?array $ledger,string $phase='attempted',?array $owner=null):array {
    return ['format'=>1,'request_id'=>$request['id'],'request_sha256'=>provider_php_controls_lifecycle_sha($request),'request_actor'=>$request['review']['context']['actor'],'recorded_by'=>$owner??$request['review']['context']['actor'],'phase'=>$phase,'recorded_epoch'=>$request['approved_epoch']+3,'ledger'=>$ledger,'ledger_sha256'=>hash('sha256',json_encode($ledger,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)),'recovered_from_ledger'=>$owner!==null];
}
function pl_check(array $q,array $t,array $r,array $result):array {
    $value=$result['state']==='complete'?$result['value']:null;return ['format'=>1,'id'=>pl_id(500),'request_id'=>$q['id'],'request_sha256'=>provider_php_controls_lifecycle_sha($q),'terminal_sha256'=>provider_php_controls_lifecycle_sha($t),'read_id'=>$r['id'],'read_sha256'=>provider_php_controls_lifecycle_sha($r),'result_sha256'=>provider_php_controls_lifecycle_sha($result),'checked_by'=>$r['context']['actor'],'floor'=>2,'recorded_epoch'=>$result['finished_epoch']+1,'disposition'=>$value===null?'unavailable':($value===$q['review']['selected']['value']?'observed':'different'),'value'=>$value];
}
try{
    $binding=['profile_id'=>pl_id(1),'profile_revision'=>1,'key_fingerprint'=>pl_hash(2),'credential_sha256'=>pl_hash(3),'package_id'=>701,'client_id'=>1,'link_id'=>pl_id(4),'link_sha256'=>pl_hash(5),'event_id'=>6,'event_sha256'=>pl_hash(7),'audit_id'=>8,'audit_sha256'=>pl_hash(9),'source_id'=>pl_id(10),'source_seq'=>11,'source_sha256'=>pl_hash(12)];
    $client=['kind'=>'client','id'=>1,'auth_version'=>str_repeat('a',32)];$owner=['kind'=>'staff','id'=>1,'auth_version'=>str_repeat('b',64)];
    $context=['format'=>1,'operation'=>'packages.phpversion','actor'=>$client,'binding'=>$binding,'permission_id'=>pl_id(13),'permission_sha256'=>pl_hash(14)];
    $ownerContext=array_replace($context,['actor'=>$owner,'permission_id'=>null,'permission_sha256'=>null]);$now=1700001000;
    $options=[['value'=>'php82','label'=>'Synthetic PHP 8.2','handler'=>'synthetic-handler'],['value'=>'php83','label'=>'Synthetic PHP 8.3','handler'=>'synthetic-handler']];
    $current=pl_read($context,'current',1,$now-35);$cr=pl_result($current,'php82');$available=pl_read($context,'available',2,$now-3);$ar=pl_result($available,$options);$q=pl_request($context,$current,$cr,$available,$ar,$now);
    $sources=[$current,$cr,$available,$ar];$ledger=pl_ledger($q);$terminal=pl_terminal($q,$ledger);$read=pl_read($context,'current',3,$now+4);$result=pl_result($read,'php83');$check=pl_check($q,$terminal,$read,$result);
    pl_ok('Client and equal-numbered staff keep distinct actor columns',provider_php_controls_lifecycle_actor_columns($client)===['actor_staff_id'=>null,'actor_client_id'=>1]&&provider_php_controls_lifecycle_actor_columns($owner)===['actor_staff_id'=>1,'actor_client_id'=>null]);
    foreach([['actor_staff_id'=>1,'actor_client_id'=>1],['actor_staff_id'=>null,'actor_client_id'=>null],['actor_staff_id'=>1,'actor_client_id'=>null],['actor_staff_id'=>null,'actor_client_id'=>'1']] as $i=>$columns)pl_deny('Client actor column mismatch '.$i,fn()=>provider_php_controls_lifecycle_assert_actor_columns($client,$columns));
    pl_ok('Exact SQL client actor columns accepted',provider_php_controls_lifecycle_assert_actor_columns($client,['actor_staff_id'=>null,'actor_client_id'=>1])===null);
    foreach(['context'=>[$context,[]],'read'=>[$current,[]],'result'=>[$cr,[$current]],'request'=>[$q,$sources],'terminal'=>[$terminal,[$q,...$sources]],'check'=>[$check,[$q,$terminal,...$sources,$read,$result]]] as $kind=>[$d,$deps]){
        $json=provider_php_controls_lifecycle_json($d);pl_ok('Canonical historical '.$kind.' roundtrip',provider_php_controls_lifecycle_parse($kind,$json,...$deps)===$d);
        pl_deny('Noncanonical whitespace '.$kind,fn()=>provider_php_controls_lifecycle_parse($kind,$json.' ',...$deps));
        pl_deny('Unknown field '.$kind,fn()=>provider_php_controls_lifecycle_parse($kind,provider_php_controls_lifecycle_json($d+['extra'=>1]),...$deps));
    }
    pl_ok('Both current and available receipts roundtrip',provider_php_controls_lifecycle_parse('result',provider_php_controls_lifecycle_json($ar),$available)===$ar);
    pl_deny('Unknown parser kind',fn()=>provider_php_controls_lifecycle_parse('grant','{}'));
    pl_deny('Missing request dependencies',fn()=>provider_php_controls_lifecycle_parse('request',provider_php_controls_lifecycle_json($q)));
    pl_deny('Duplicate JSON keys cannot collapse into accepted canonical bytes',fn()=>provider_php_controls_lifecycle_parse('context',str_replace('"format":1','"format":1,"format":1',provider_php_controls_lifecycle_json($context))));
    pl_deny('Oversized JSON bounded before decode',fn()=>provider_php_controls_lifecycle_parse('context',str_repeat(' ',131073)));
    pl_deny('Nonfinite JSON refused',fn()=>provider_php_controls_lifecycle_parse('context','{"format":NaN}'));
    pl_deny('Malformed UTF8 refused',fn()=>provider_php_controls_lifecycle_parse('context',"{\"format\":\"\xFF\"}"));
    foreach(['operation'=>'other','format'=>2,'permission_id'=>null,'permission_sha256'=>null] as $key=>$v)pl_deny('Client context closed '.$key,fn()=>provider_php_controls_lifecycle_context(array_replace($context,[$key=>$v])));
    pl_deny('Wrong assigned client',fn()=>provider_php_controls_lifecycle_context(array_replace($context,['actor'=>array_replace($client,['id'=>2])])));
    pl_deny('Forged system actor not allowed',fn()=>provider_php_controls_lifecycle_actor(array_replace($client,['kind'=>'system'])));
    pl_deny('Malformed authorization version refused',fn()=>provider_php_controls_lifecycle_actor(array_replace($client,['auth_version'=>'1'])));
    pl_deny('Owner cannot carry a client grant',fn()=>provider_php_controls_lifecycle_context(array_replace($context,['actor'=>$owner])));
    pl_ok('Owner context needs no fabricated client permission',provider_php_controls_lifecycle_context($ownerContext)===$ownerContext);
    $oc=pl_read($ownerContext,'current',1,$now-35);$ocr=pl_result($oc,'php82');$oa=pl_read($ownerContext,'available',2,$now-3);$oar=pl_result($oa,$options);$oq=pl_request($ownerContext,$oc,$ocr,$oa,$oar,$now);$ot=pl_terminal($oq,pl_ledger($oq));
    pl_ok('Real owner request and normal completion need no fabricated client or grant',provider_php_controls_lifecycle_terminal($ot,$oq,$oc,$ocr,$oa,$oar)['request_actor']===$owner&&provider_php_controls_lifecycle_sha($oq)!==provider_php_controls_lifecycle_sha($q));
    foreach(['profile_revision'=>2147483648,'source_seq'=>1000000000000000] as $key=>$value){$bad=$ownerContext;$bad['binding'][$key]=$value;pl_deny('Context SQL numeric bound '.$key,fn()=>provider_php_controls_lifecycle_context($bad));}
    foreach(['seq'=>0,'started_epoch'=>-1,'kind'=>'write','id'=>'bad'] as $k=>$v)pl_deny('Read intent rejects '.$k,fn()=>provider_php_controls_lifecycle_read(array_replace($current,[$k=>$v])));
    foreach(['read_id'=>$available['id'],'read_sha256'=>pl_hash(99),'finished_epoch'=>$current['started_epoch']-1,'options'=>[],'options_sha256'=>pl_hash(1),'value'=>82,'reason'=>'unknown'] as $k=>$v)pl_deny('Scalar receipt rejects '.$k,fn()=>provider_php_controls_lifecycle_result(array_replace($cr,[$k=>$v]),$current));
    pl_deny('Choices receipt hash mismatch',fn()=>provider_php_controls_lifecycle_result(array_replace($ar,['options_sha256'=>pl_hash(99)]),$available));
    pl_deny('Duplicate normalized values',fn()=>pl_result($available,[$options[0],$options[0]]));
    $empty=pl_result($available,[]);pl_ok('Empty normalized set remains a complete but unselectable observation',provider_php_controls_lifecycle_result($empty,$available)===$empty);
    pl_deny('Empty normalized set never grants a version',fn()=>provider_php_controls_lifecycle_review($context,$current,$cr,$available,$empty,'php83'));
    foreach(['PHP83',' php83','php99',83] as $i=>$v)pl_deny('Exact option membership '.$i,fn()=>provider_php_controls_lifecycle_review($context,$current,$cr,$available,$ar,$v));
    pl_deny('Current value no-op refuses',fn()=>provider_php_controls_lifecycle_review($context,$current,$cr,$available,$ar,'php82'));
    $unavailable=pl_result($available,null,'unknown','unexpected_response');pl_ok('Finite failed choices retain no options',provider_php_controls_lifecycle_result($unavailable,$available)===$unavailable);
    pl_deny('Failed latest receipt cannot authorize fallback',fn()=>provider_php_controls_lifecycle_review($context,$current,$cr,$available,$unavailable,'php83'));
    pl_deny('Failure reason cannot store raw exception',fn()=>provider_php_controls_lifecycle_result(array_replace($unavailable,['reason'=>'secret response body']),$available));
    pl_deny('Failed receipt cannot retain positive choices',fn()=>provider_php_controls_lifecycle_result(array_replace($unavailable,['options'=>$options]),$available));
    provider_php_controls_lifecycle_fresh($current,$cr,$current['started_epoch']+900);pl_ok('Exact freshness boundary accepted',true);
    pl_deny('Expired observation refused for new admission',fn()=>provider_php_controls_lifecycle_fresh($current,$cr,$current['started_epoch']+901));
    pl_deny('Future observation beyond clock skew refused',fn()=>provider_php_controls_lifecycle_fresh($current,$cr,$current['started_epoch']-31));
    foreach(['confirmed'=>false,'review_sha256'=>pl_hash(99),'expires_epoch'=>$now+901,'idempotency_key'=>'different','id'=>pl_id(301),'seq'=>0] as $k=>$v)pl_deny('Request identity or approval rejects '.$k,fn()=>provider_php_controls_lifecycle_request(array_replace($q,[$k=>$v]),...$sources));
    $changed=$q;$changed['review']['selected']['label']='Different displayed label';pl_deny('Selection display cannot change under review hash',fn()=>provider_php_controls_lifecycle_request($changed,...$sources));
    $changed=$q;$changed['review']['params']['packageId']=702;pl_deny('Target package cannot be substituted',fn()=>provider_php_controls_lifecycle_request($changed,...$sources));
    $changed=$q;$changed['review']['before_value']='php80';pl_deny('Before value must equal retained scalar',fn()=>provider_php_controls_lifecycle_request($changed,...$sources));
    $changed=$current;$changed['context']['binding']['credential_sha256']=pl_hash(55);$changedResult=pl_result($changed,'php82');pl_deny('Read from changed credentials cannot join original context',fn()=>provider_php_controls_lifecycle_request($q,$changed,$changedResult,$available,$ar));
    $newq=$q;$newq['id']=pl_id(301);$newq['idempotency_key']='phpversion:'.$newq['id'];pl_ok('A separately valid request is a new identity not tamper detection',provider_php_controls_lifecycle_request($newq,...$sources)===$newq&&provider_php_controls_lifecycle_sha($newq)!==provider_php_controls_lifecycle_sha($q));
    pl_deny('Different valid request cannot reuse original terminal',fn()=>provider_php_controls_lifecycle_terminal($terminal,$newq,...$sources));
    pl_ok('Historical request parses long after expiry without current-clock authority',provider_php_controls_lifecycle_request($q,...$sources)===$q);
    foreach(['request_actor'=>$owner,'recorded_by'=>$owner,'request_sha256'=>pl_hash(99),'ledger_sha256'=>pl_hash(99),'phase'=>'succeeded','recorded_epoch'=>$now-1] as $k=>$v)pl_deny('Terminal rejects '.$k,fn()=>provider_php_controls_lifecycle_terminal(array_replace($terminal,[$k=>$v]),$q,...$sources));
    $notSent=pl_terminal($q,null,'not_sent');pl_ok('No-entry no-ledger terminal is representable',provider_php_controls_lifecycle_terminal($notSent,$q,...$sources)===$notSent);
    pl_deny('Claimed or finished ledger cannot prove no-send',fn()=>provider_php_controls_lifecycle_terminal(pl_terminal($q,$ledger,'not_sent'),$q,...$sources));
    $missing=pl_terminal($q,null);$claimed=pl_terminal($q,pl_ledger($q,'claimed'));
    pl_ok('Entered missing or unfinished ledger remains attempted',provider_php_controls_lifecycle_terminal($missing,$q,...$sources)['phase']==='attempted'&&provider_php_controls_lifecycle_terminal($claimed,$q,...$sources)['phase']==='attempted');
    $recovered=pl_terminal($q,$ledger,'attempted',$owner);pl_ok('Owner recovery preserves original client and records real owner',provider_php_controls_lifecycle_terminal($recovered,$q,...$sources)['request_actor']===$client&&$recovered['recorded_by']===$owner);
    foreach([null,pl_ledger($q,'claimed')] as $i=>$bad)pl_deny('Recovery refuses missing or unfinished ledger '.$i,fn()=>provider_php_controls_lifecycle_terminal(pl_terminal($q,$bad,'attempted',$owner),$q,...$sources));
    pl_deny('Client cannot self-recover a missing terminal',fn()=>provider_php_controls_lifecycle_terminal(pl_terminal($q,$ledger,'attempted',$client),$q,...$sources));
    pl_deny('Recovery never invents not-sent',fn()=>provider_php_controls_lifecycle_terminal(pl_terminal($q,null,'not_sent',$owner),$q,...$sources));
    foreach(['idempotency_key'=>'phpversion:'.pl_id(333),'fingerprint'=>pl_hash(55),'operation'=>'packages.forcessl','status'=>'succeeded','attempts'=>2,'claimed_epoch'=>$now-1,'resolved_epoch'=>$now+99,'reconciled_outcome'=>'succeeded','external_id'=>'remote','provider_code'=>"bad\nraw",'result_message'=>str_repeat('x',201)] as $k=>$v){$l=array_replace($ledger,[$k=>$v]);pl_deny('Ledger recovery rejects '.$k,fn()=>provider_php_controls_lifecycle_terminal(pl_terminal($q,$l,'attempted',$owner),$q,...$sources));}
    pl_ok('Later exact desired scalar is observed only',provider_php_controls_lifecycle_check($check,$q,$terminal,...[...$sources,$read,$result])['disposition']==='observed');
    $different=pl_result($read,'php82');$dc=pl_check($q,$terminal,$read,$different);pl_ok('Different scalar never produces observed state',provider_php_controls_lifecycle_check($dc,$q,$terminal,...[...$sources,$read,$different])['disposition']==='different');
    pl_deny('Different scalar cannot be relabeled observed',fn()=>provider_php_controls_lifecycle_check(array_replace($dc,['disposition'=>'observed']),$q,$terminal,...[...$sources,$read,$different]));
    $failed=pl_result($read,null,'unknown','timeout');$fc=pl_check($q,$terminal,$read,$failed);pl_ok('Failed readback remains unavailable',provider_php_controls_lifecycle_check($fc,$q,$terminal,...[...$sources,$read,$failed])['disposition']==='unavailable');
    foreach(['floor'=>3,'request_sha256'=>pl_hash(90),'terminal_sha256'=>pl_hash(91),'read_id'=>pl_id(999),'read_sha256'=>pl_hash(92),'result_sha256'=>pl_hash(93),'checked_by'=>$owner,'value'=>'php82','recorded_epoch'=>$now] as $k=>$v)pl_deny('Readback exact witness '.$k,fn()=>provider_php_controls_lifecycle_check(array_replace($check,[$k=>$v]),$q,$terminal,...[...$sources,$read,$result]));
    $pre=pl_read($context,'current',3,$now+2);$pr=pl_result($pre,'php83');pl_deny('Preterminal scalar cannot verify write',fn()=>provider_php_controls_lifecycle_check(pl_check($q,$terminal,$pre,$pr),$q,$terminal,...[...$sources,$pre,$pr]));
    pl_deny('Stale readback cannot release a fence',fn()=>provider_php_controls_lifecycle_check(array_replace($check,['recorded_epoch'=>$read['started_epoch']+901]),$q,$terminal,...[...$sources,$read,$result]));
    pl_deny('No-send terminal is not a readback transition',fn()=>provider_php_controls_lifecycle_check(pl_check($q,$notSent,$read,$result),$q,$notSent,...[...$sources,$read,$result]));
    foreach(['credential_sha256'=>pl_hash(80),'key_fingerprint'=>pl_hash(81),'link_id'=>pl_id(82),'package_id'=>702,'event_id'=>66,'client_id'=>2] as $key=>$v){$ctx=$ownerContext;$ctx['binding'][$key]=$v;$r=pl_read($ctx,'current',3,$now+4);$rr=pl_result($r,'php83');pl_deny('Readback cannot switch '.$key,fn()=>provider_php_controls_lifecycle_check(pl_check($q,$terminal,$r,$rr),$q,$terminal,...[...$sources,$r,$rr]));}
    $ctx=$ownerContext;$ctx['binding']['source_id']=pl_id(88);$ctx['binding']['source_seq']=22;$ctx['binding']['source_sha256']=pl_hash(89);$r=pl_read($ctx,'current',3,$now+4);$rr=pl_result($r,'php83');$rc=pl_check($q,$terminal,$r,$rr);
    pl_ok('Owner readback may use later inventory source while retaining exact original assignment',provider_php_controls_lifecycle_check($rc,$q,$terminal,...[...$sources,$r,$rr])['checked_by']===$owner);
    $ctx=$context;$ctx['permission_id']=pl_id(77);$r=pl_read($ctx,'current',3,$now+4);$rr=pl_result($r,'php83');pl_deny('Client regrant cannot silently adopt old request',fn()=>provider_php_controls_lifecycle_check(pl_check($q,$terminal,$r,$rr),$q,$terminal,...[...$sources,$r,$rr]));
    $schema=provider_php_control_lifecycle_schema('pltest_');pl_ok('Exactly five additive lifecycle tables',array_keys($schema)===['provider_php_control_reads','provider_php_control_read_results','provider_php_changes','provider_php_dispatch_ends','provider_php_change_checks']);
    foreach($schema as $name=>$sql){pl_ok('InnoDB and restrictive history '.$name,str_contains($sql,'ENGINE=InnoDB')&&!str_contains($sql,'CASCADE'));pl_ok('No fabricated grant or refresh relationship '.$name,!str_contains($sql,'provider_operation_grants')&&!str_contains($sql,'provider_refreshes'));}
    foreach(['provider_php_control_reads','provider_php_changes','provider_php_dispatch_ends','provider_php_change_checks'] as $name)pl_ok('Real staff/client XOR schema '.$name,str_contains($schema[$name],'actor_staff_id INT UNSIGNED NULL,actor_client_id INT UNSIGNED NULL')&&str_contains($schema[$name],'CHECK ((actor_staff_id IS NULL) <> (actor_client_id IS NULL))')&&str_contains($schema[$name],'`pltest_users`')&&str_contains($schema[$name],'`pltest_studio_clients`'));
    pl_ok('Results inherit exact actor through their original read foreign key',str_contains($schema['provider_php_control_read_results'],'FOREIGN KEY (read_id) REFERENCES `pltest_provider_php_control_reads`'));
    foreach(['','x','bad-prefix','bad`prefix','1bad',str_repeat('a',21)] as $i=>$prefix)pl_deny('Unsafe schema prefix '.$i,fn()=>provider_php_control_lifecycle_schema($prefix));
    $typeEscaped=false;try{pl_deny('Not a denial outcome',static function():void{throw new TypeError('Synthetic engine fault');});}catch(TypeError){$typeEscaped=true;}pl_ok('Unexpected engine failures escape negative helper',$typeEscaped);
    echo json_encode(['passed'=>true,'count'=>count($checks),'scope'=>'Pure historical documents and schema declarations; synthetic normalized options, no database/provider/current authority.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){echo json_encode(['passed'=>false,'count'=>count($checks),'checks'=>$checks,'failure'=>['class'=>get_class($e),'message'=>$e->getMessage(),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;exit(1);}
