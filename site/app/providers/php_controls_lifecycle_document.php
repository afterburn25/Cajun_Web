<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
foreach(['twentyi-package-php/PackagePhpVersionBinding','twentyi-package-php/PackagePhpOptionSet','twentyi-operations/PhpVersionChange','access/AuthorizationVersion','provider/ProviderOperation','provider/ProviderGateway'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/'.$class.'.php';

use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersionBinding,PackagePhpVersion,PackagePhpOptionSet};
use CajunWebPro\Foundation\TwentyI\Operations\PhpVersionChange;
use CajunWebPro\Foundation\Access\AuthorizationVersion;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};

/** Pure historical structure only. Native readers must pin original rows, HMACs, audits and current authority. */
function provider_php_controls_lifecycle_keys(array $d,array $keys):void {if(array_keys($d)!==$keys)throw new InvalidArgumentException('Invalid PHP lifecycle fields.');}
function provider_php_controls_lifecycle_id(mixed $v):void {if(!is_string($v)||preg_match('/\A[a-f0-9]{32}\z/',$v)!==1)throw new InvalidArgumentException('Invalid PHP lifecycle identity.');}
function provider_php_controls_lifecycle_hash(mixed $v):void {if(!is_string($v)||preg_match('/\A[a-f0-9]{64}\z/',$v)!==1)throw new InvalidArgumentException('Invalid PHP lifecycle hash.');}
function provider_php_controls_lifecycle_number(mixed $v,int $min=0):void {if(!is_int($v)||$v<$min||$v>2147483647)throw new InvalidArgumentException('Invalid PHP lifecycle number.');}
function provider_php_controls_lifecycle_seq(mixed $v):void {if(!is_int($v)||$v<1||$v>999999999999999)throw new InvalidArgumentException('Invalid PHP lifecycle sequence.');}
function provider_php_controls_lifecycle_json(array $d):string {return json_encode($d,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_php_controls_lifecycle_sha(array $d):string {return hash('sha256',provider_php_controls_lifecycle_json($d));}
function provider_php_controls_lifecycle_actor(array $a):array {
    provider_php_controls_lifecycle_keys($a,['kind','id','auth_version']);
    if(!in_array($a['kind'],['staff','client'],true))throw new InvalidArgumentException('Invalid PHP lifecycle actor.');
    provider_php_controls_lifecycle_number($a['id'],1);
    if(!is_string($a['auth_version'])||!AuthorizationVersion::valid($a['auth_version']))throw new InvalidArgumentException('Invalid PHP lifecycle authorization witness.');return $a;
}
/** Exact SQL actor projection; no client identity can become a staff ID. */
function provider_php_controls_lifecycle_actor_columns(array $actor):array {
    $a=provider_php_controls_lifecycle_actor($actor);return ['actor_staff_id'=>$a['kind']==='staff'?$a['id']:null,'actor_client_id'=>$a['kind']==='client'?$a['id']:null];
}
function provider_php_controls_lifecycle_assert_actor_columns(array $actor,array $columns):void {
    if($columns!==provider_php_controls_lifecycle_actor_columns($actor))throw new InvalidArgumentException('PHP lifecycle actor columns disagree.');
}
function provider_php_controls_lifecycle_context(array $d):array {
    provider_php_controls_lifecycle_keys($d,['format','operation','actor','binding','permission_id','permission_sha256']);
    if($d['format']!==1||$d['operation']!==PhpVersionChange::OPERATION||!is_array($d['actor'])||!is_array($d['binding']))throw new InvalidArgumentException('Invalid PHP lifecycle context.');
    $actor=provider_php_controls_lifecycle_actor($d['actor']);$b=new PackagePhpVersionBinding($d['binding']);
    if($b->data!==$d['binding'])throw new InvalidArgumentException('Noncanonical PHP assignment.');
    foreach(['profile_revision','client_id'] as $key)provider_php_controls_lifecycle_number($b->data[$key],1);
    foreach(['event_id','audit_id','source_seq'] as $key)provider_php_controls_lifecycle_seq($b->data[$key]);
    if($actor['kind']==='staff'){
        if($d['permission_id']!==null||$d['permission_sha256']!==null)throw new InvalidArgumentException('Owner context cannot adopt client permission.');
    }else{
        provider_php_controls_lifecycle_id($d['permission_id']);provider_php_controls_lifecycle_hash($d['permission_sha256']);
        if($actor['id']!==$b->data['client_id'])throw new InvalidArgumentException('PHP client does not own this assignment.');
    }return $d;
}
function provider_php_controls_lifecycle_read(array $d):array {
    provider_php_controls_lifecycle_keys($d,['format','id','seq','kind','context','started_epoch']);
    if($d['format']!==1||!in_array($d['kind'],['current','available'],true)||!is_array($d['context']))throw new InvalidArgumentException('Invalid PHP read intent.');
    provider_php_controls_lifecycle_id($d['id']);provider_php_controls_lifecycle_seq($d['seq']);provider_php_controls_lifecycle_number($d['started_epoch']);provider_php_controls_lifecycle_context($d['context']);return $d;
}
function provider_php_controls_lifecycle_result(array $d,array $read):array {
    $read=provider_php_controls_lifecycle_read($read);
    provider_php_controls_lifecycle_keys($d,['format','read_id','read_sha256','state','reason','finished_epoch','value','options','options_sha256']);
    if($d['format']!==1||$d['read_id']!==$read['id']||$d['read_sha256']!==provider_php_controls_lifecycle_sha($read))throw new InvalidArgumentException('PHP result belongs to another intent.');
    provider_php_controls_lifecycle_number($d['finished_epoch']);if($d['finished_epoch']<$read['started_epoch'])throw new InvalidArgumentException('PHP result predates its intent.');
    if($d['state']==='complete'){
        if($d['reason']!=='')throw new InvalidArgumentException('A complete PHP result has no failure reason.');
        if($read['kind']==='current'){
            if(PackagePhpVersion::value($d['value'])!==$d['value']||$d['options']!==null||$d['options_sha256']!==null)throw new InvalidArgumentException('Invalid scalar PHP observation.');
        }else{
            if($d['value']!==null||!is_array($d['options']))throw new InvalidArgumentException('Invalid normalized PHP choices.');
            $set=PackagePhpOptionSet::fromNormalizedRows($d['options']);if($set->options!==$d['options']||$set->sha256!==$d['options_sha256'])throw new InvalidArgumentException('PHP choices hash disagrees.');
        }
    }else{
        if(!in_array($d['state'],['unavailable','unknown'],true)||!in_array($d['reason'],['authentication','access_changed','unavailable','timeout','protocol','unexpected_status','unexpected_response','response_limit','credential','unknown'],true)||$d['value']!==null||$d['options']!==null||$d['options_sha256']!==null)throw new InvalidArgumentException('Invalid unavailable PHP observation.');
    }return $d;
}
/** Historical checks do not use the wall clock. New admission calls this explicitly. */
function provider_php_controls_lifecycle_fresh(array $read,array $result,int $now):void {
    $read=provider_php_controls_lifecycle_read($read);$result=provider_php_controls_lifecycle_result($result,$read);provider_php_controls_lifecycle_number($now);
    if($result['state']!=='complete'||$read['started_epoch']<$now-900||$read['started_epoch']>$now+30||$result['finished_epoch']>$now+30)throw new InvalidArgumentException('Refresh these exact PHP observations.');
}
function provider_php_controls_lifecycle_review(array $context,array $current,array $currentResult,array $available,array $availableResult,mixed $desired):array {
    $context=provider_php_controls_lifecycle_context($context);$current=provider_php_controls_lifecycle_read($current);$available=provider_php_controls_lifecycle_read($available);
    $currentResult=provider_php_controls_lifecycle_result($currentResult,$current);$availableResult=provider_php_controls_lifecycle_result($availableResult,$available);
    if($current['kind']!=='current'||$available['kind']!=='available'||$current['id']===$available['id']||$current['seq']===$available['seq']||$current['context']!==$context||$available['context']!==$context||$currentResult['state']!=='complete'||$availableResult['state']!=='complete')throw new InvalidArgumentException('Review needs both exact PHP observations.');
    $selected=PackagePhpOptionSet::fromNormalizedRows($availableResult['options'])->select($desired);
    if($selected['value']===$currentResult['value'])throw new InvalidArgumentException('The latest observation already reports this PHP value.');
    return ['format'=>1,'context'=>$context,'current_read_id'=>$current['id'],'current_read_sha256'=>provider_php_controls_lifecycle_sha($current),'current_result_sha256'=>provider_php_controls_lifecycle_sha($currentResult),'options_read_id'=>$available['id'],'options_read_sha256'=>provider_php_controls_lifecycle_sha($available),'options_result_sha256'=>provider_php_controls_lifecycle_sha($availableResult),'before_value'=>$currentResult['value'],'selected'=>$selected,'params'=>PhpVersionChange::params(['packageId'=>$context['binding']['package_id'],'value'=>$selected['value']])];
}
function provider_php_controls_lifecycle_request(array $d,array $current,array $currentResult,array $available,array $availableResult):array {
    provider_php_controls_lifecycle_keys($d,['format','id','seq','review','review_sha256','confirmed','approved_epoch','expires_epoch','idempotency_key']);
    if($d['format']!==1||$d['confirmed']!==true||!is_array($d['review']))throw new InvalidArgumentException('Invalid reviewed PHP request.');
    provider_php_controls_lifecycle_id($d['id']);provider_php_controls_lifecycle_seq($d['seq']);provider_php_controls_lifecycle_number($d['approved_epoch']);provider_php_controls_lifecycle_number($d['expires_epoch']);
    $r=$d['review'];if(!is_array($r['context']??null)||!is_array($r['selected']??null))throw new InvalidArgumentException('Invalid PHP request review.');
    $exact=provider_php_controls_lifecycle_review($r['context'],$current,$currentResult,$available,$availableResult,$r['selected']['value']??null);
    if($r!==$exact||$d['review_sha256']!==provider_php_controls_lifecycle_sha($exact)||$d['expires_epoch']!==$d['approved_epoch']+900||$d['idempotency_key']!=='phpversion:'.$d['id'])throw new InvalidArgumentException('PHP request no longer matches its exact review.');
    provider_php_controls_lifecycle_fresh($current,$currentResult,$d['approved_epoch']);provider_php_controls_lifecycle_fresh($available,$availableResult,$d['approved_epoch']);return $d;
}
/** Canonical native projection of the existing ledger row; no provider acknowledgement is inferred. */
function provider_php_controls_lifecycle_ledger(?array $row,array $request,int $recorded):?array {
    if($row===null)return null;
    provider_php_controls_lifecycle_keys($row,['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch','reconciled_outcome','reconciled_epoch']);
    provider_php_controls_lifecycle_id($row['id']);provider_php_controls_lifecycle_hash($row['fingerprint']);provider_php_controls_lifecycle_number($row['claimed_epoch']);provider_php_controls_lifecycle_number($row['attempts']);
    $b=$request['review']['context']['binding'];$op=new ProviderOperation(PhpVersionChange::OPERATION,$request['idempotency_key'],$request['review']['params']);
    $fp=ProviderGateway::requestFingerprint('twenty-i',$b['profile_id'].':'.$b['profile_revision'].':'.$b['key_fingerprint'],$op);
    if($row['idempotency_key']!==$request['idempotency_key']||$row['fingerprint']!==$fp||$row['claimed_epoch']<$request['approved_epoch']||$row['claimed_epoch']>$recorded||$row['claimed_at']!==gmdate('Y-m-d H:i:s',$row['claimed_epoch'])||$row['external_id']!==null||$row['reconciled_outcome']!==null||$row['reconciled_epoch']!==null)throw new InvalidArgumentException('PHP ledger does not match its original request.');
    foreach(['result_message'=>200,'provider_code'=>64] as $key=>$max){$v=$row[$key];if($key==='provider_code'&&$v===null)continue;if(!is_string($v)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match('/[\x00-\x1f\x7f]/',$v))throw new InvalidArgumentException('Invalid bounded PHP ledger result.');}
    if($row['status']==='claimed'){
        if($row['operation']!==null||$row['attempts']!==0||$row['resolved_epoch']!==null||$row['result_message']!==''||$row['provider_code']!==null)throw new InvalidArgumentException('Invalid unfinished PHP claim.');
    }else{
        provider_php_controls_lifecycle_number($row['resolved_epoch']);
        if(!in_array($row['status'],['failed','uncertain'],true)||$row['operation']!==PhpVersionChange::OPERATION||$row['attempts']!==1||$row['resolved_epoch']<$row['claimed_epoch']||$row['resolved_epoch']>$recorded)throw new InvalidArgumentException('Invalid single-attempt PHP completion.');
    }return $row;
}
function provider_php_controls_lifecycle_terminal(array $d,array $request,array $current,array $currentResult,array $available,array $availableResult):array {
    $request=provider_php_controls_lifecycle_request($request,$current,$currentResult,$available,$availableResult);
    provider_php_controls_lifecycle_keys($d,['format','request_id','request_sha256','request_actor','recorded_by','phase','recorded_epoch','ledger','ledger_sha256','recovered_from_ledger']);
    if($d['format']!==1||$d['request_id']!==$request['id']||$d['request_sha256']!==provider_php_controls_lifecycle_sha($request)||$d['request_actor']!==$request['review']['context']['actor']||!is_array($d['recorded_by'])||!is_bool($d['recovered_from_ledger'])||!in_array($d['phase'],['not_sent','attempted'],true)||($d['ledger']!==null&&!is_array($d['ledger'])))throw new InvalidArgumentException('Invalid PHP dispatch terminal.');
    $actor=provider_php_controls_lifecycle_actor($d['recorded_by']);provider_php_controls_lifecycle_number($d['recorded_epoch']);if($d['recorded_epoch']<$request['approved_epoch'])throw new InvalidArgumentException('PHP terminal predates approval.');
    $ledger=provider_php_controls_lifecycle_ledger($d['ledger'],$request,$d['recorded_epoch']);
    if($d['ledger_sha256']!==hash('sha256',json_encode($ledger,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))throw new InvalidArgumentException('PHP terminal ledger hash disagrees.');
    if($d['recovered_from_ledger']){
        if($actor['kind']!=='staff'||$d['phase']!=='attempted'||$ledger===null||$ledger['status']==='claimed')throw new InvalidArgumentException('Recovery needs an original finished ledger and current owner.');
    }elseif($actor!==$d['request_actor'])throw new InvalidArgumentException('Normal completion cannot relabel the requester.');
    if($d['phase']==='not_sent'&&$ledger!==null)throw new InvalidArgumentException('A ledger claim cannot prove no send.');return $d;
}
/** Only the current package-list source may differ; account, assignment and retained client stay exact. */
function provider_php_controls_lifecycle_same_resource(array $original,array $later):bool {
    $a=(new PackagePhpVersionBinding($original))->data;$b=(new PackagePhpVersionBinding($later))->data;
    foreach(['source_id','source_seq','source_sha256'] as $key){unset($a[$key],$b[$key]);}return $a===$b;
}
function provider_php_controls_lifecycle_check(array $d,array $request,array $terminal,array $current,array $currentResult,array $available,array $availableResult,array $read,array $result):array {
    $terminal=provider_php_controls_lifecycle_terminal($terminal,$request,$current,$currentResult,$available,$availableResult);$read=provider_php_controls_lifecycle_read($read);$result=provider_php_controls_lifecycle_result($result,$read);
    provider_php_controls_lifecycle_keys($d,['format','id','request_id','request_sha256','terminal_sha256','read_id','read_sha256','result_sha256','checked_by','floor','recorded_epoch','disposition','value']);
    provider_php_controls_lifecycle_id($d['id']);provider_php_controls_lifecycle_seq($d['floor']);provider_php_controls_lifecycle_number($d['recorded_epoch']);
    if($read['started_epoch']<$d['recorded_epoch']-900)throw new InvalidArgumentException('PHP verification receipt was stale when recorded.');
    if($d['format']!==1||$d['request_id']!==$request['id']||$d['request_sha256']!==provider_php_controls_lifecycle_sha($request)||$d['terminal_sha256']!==provider_php_controls_lifecycle_sha($terminal)||$d['read_id']!==$read['id']||$d['read_sha256']!==provider_php_controls_lifecycle_sha($read)||$d['result_sha256']!==provider_php_controls_lifecycle_sha($result)||$d['checked_by']!==$read['context']['actor']||$terminal['phase']!=='attempted'||$read['kind']!=='current'||$d['floor']<max($current['seq'],$available['seq'])||$read['seq']<=$d['floor']||$read['started_epoch']<$terminal['recorded_epoch']||$d['recorded_epoch']<$result['finished_epoch'])throw new InvalidArgumentException('PHP verification is not the exact later scalar read.');
    $origin=$request['review']['context'];$later=$read['context'];
    if(!provider_php_controls_lifecycle_same_resource($origin['binding'],$later['binding']))throw new InvalidArgumentException('PHP verification changed account or assignment.');
    if($later['actor']['kind']==='client'&&($later['actor']!==$origin['actor']||$later['permission_id']!==$origin['permission_id']||$later['permission_sha256']!==$origin['permission_sha256']))throw new InvalidArgumentException('Client verification needs its own original authorization.');
    $value=$result['state']==='complete'?$result['value']:null;$state=$value===null?'unavailable':($value===$request['review']['selected']['value']?'observed':'different');
    if($d['disposition']!==$state||$d['value']!==$value)throw new InvalidArgumentException('PHP verification cannot invent the desired value.');return $d;
}
/** Canonical bytes and exact referenced historical documents are required; this is not native authority. */
function provider_php_controls_lifecycle_parse(string $kind,string $json,array ...$sources):array {
    if(strlen($json)>131072)throw new InvalidArgumentException('PHP lifecycle document is too large.');
    $d=json_decode($json,true,24,JSON_THROW_ON_ERROR);if(!is_array($d))throw new InvalidArgumentException('Invalid PHP lifecycle document.');
    $counts=['context'=>0,'read'=>0,'result'=>1,'request'=>4,'terminal'=>5,'check'=>8];
    if(!isset($counts[$kind])||count($sources)!==$counts[$kind])throw new InvalidArgumentException('Invalid PHP lifecycle document dependencies.');
    $valid=match($kind){'context'=>provider_php_controls_lifecycle_context($d),'read'=>provider_php_controls_lifecycle_read($d),'result'=>provider_php_controls_lifecycle_result($d,...$sources),'request'=>provider_php_controls_lifecycle_request($d,...$sources),'terminal'=>provider_php_controls_lifecycle_terminal($d,...$sources),'check'=>provider_php_controls_lifecycle_check($d,...$sources)};
    if(provider_php_controls_lifecycle_json($valid)!==$json)throw new InvalidArgumentException('PHP lifecycle document is not canonical.');return $valid;
}
