<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) {http_response_code(404);exit;}
use CajunWebPro\Foundation\TwentyIStackCpAccess\{StackCpAccessBinding,StackCpAccessObservation};
function provider_stackcp_access_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_stackcp_access_common_keys(bool $finished=false):array {
    return ['id','profile_id','profile_revision','actor_id','started_at','started_epoch','operation','seq',...($finished?['state','reason','scope','coverage','package_count','skipped','skip_reasons','finished_at','elapsed_ms','key_fingerprint']:[])];
}
/** Database scalar types normalize to exact strings; nulls and absent fields never become empty. */
function provider_stackcp_access_common(array $row,bool $finished=false):array {
    $out=[];foreach (provider_stackcp_access_common_keys($finished) as $key) {if (!array_key_exists($key,$row)||$row[$key]===null||(!is_int($row[$key])&&!is_string($row[$key]))) throw new RuntimeException('StackCP common observation history needs recovery.');$out[$key]=(string)$row[$key];}return $out;
}
function provider_stackcp_access_hash(array $row):string {
    $out=[];foreach ($row as $key=>$value) {if (!is_int($value)&&!is_string($value)&&$value!==null) throw new RuntimeException('StackCP source history needs recovery.');$out[$key]=$value===null?null:(string)$value;}return hash('sha256',provider_stackcp_access_json($out));
}
function provider_stackcp_access_document(string $kind,array $input):array {
    $keys=['version','kind','id','ordinal','previous_sha256','common',...($kind==='started'?['binding']:['read_sha256','state','reason','observed_epoch','values'])];
    if (!in_array($kind,['started','finished'],true)||array_keys($input)!==$keys||$input['version']!==1||$input['kind']!==$kind) throw new InvalidArgumentException('Invalid StackCP access history document.');
    $out=['version'=>1,'kind'=>$kind,'id'=>provider_stackcp_hex($input['id']),'ordinal'=>provider_stackcp_number($input['ordinal']),'previous_sha256'=>provider_stackcp_hex($input['previous_sha256'],64)];
    if (!is_array($input['common'])||array_keys($input['common'])!==provider_stackcp_access_common_keys($kind==='finished')) throw new InvalidArgumentException('Invalid StackCP common history document.');
    $out['common']=provider_stackcp_access_common($input['common'],$kind==='finished');if ($out['common']!==$input['common']||$out['common']['id']!==$out['id']) throw new InvalidArgumentException('Invalid StackCP common history identity.');
    if ($kind==='started') {
        if (!is_array($input['binding'])) throw new InvalidArgumentException('Invalid StackCP access binding.');$binding=new StackCpAccessBinding($input['binding']);$out['binding']=$binding->data;
        foreach (['operation','profile_id','profile_revision','actor_id'] as $key) if ((string)$binding->data[$key]!==$out['common'][$key]) throw new InvalidArgumentException('Invalid StackCP start history context.');
    } else {
        $out['read_sha256']=provider_stackcp_hex($input['read_sha256'],64);
        if (!is_string($input['state'])||!is_string($input['reason'])||!in_array($input['state'],['complete','unavailable','refused','access_changed','credential'],true)||!preg_match('/\A[a-z0-9_]{0,40}\z/',$input['reason'])||!is_int($input['observed_epoch'])||$input['observed_epoch']<0||($input['values']!==null&&!is_array($input['values']))) throw new InvalidArgumentException('Invalid StackCP access finish.');
        foreach (['state','reason','observed_epoch'] as $key) $out[$key]=$input[$key];
        $out['values']=$input['values']===null?null:json_decode(provider_stackcp_access_json($input['values']),true,4,JSON_THROW_ON_ERROR);
        if ($out['state']!==$out['common']['state']||$out['reason']!==$out['common']['reason']||($out['state']!=='complete'&&($out['values']!==null||$out['observed_epoch']!==0))) throw new InvalidArgumentException('Invalid StackCP finish context.');
    }
    return $out;
}
function provider_stackcp_access_hmac(string $kind,array $document):string {return provider_stackcp_proof(provider_stackcp_vault(),'access-'.$kind,provider_stackcp_access_json($document));}
function provider_stackcp_access_row(string $kind,array $row):array {
    try {
        if (!is_string($row['document_json'])||strlen($row['document_json'])>393216) throw new InvalidArgumentException();$d=provider_stackcp_access_document($kind,json_decode($row['document_json'],true,16,JSON_THROW_ON_ERROR));
        if (provider_stackcp_access_json($d)!==$row['document_json']||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256'])||!hash_equals(provider_stackcp_access_hmac($kind,$d),$row['proof'])||$d['id']!==$row['id']||(string)$d['ordinal']!==(string)$row['ordinal']) throw new InvalidArgumentException();
        if ($kind==='started') foreach (['profile_id','package_id','client_id','stack_identity','operation','assignment_id'] as $key) if ((string)$d['binding'][$key]!==($row[$key]===null?null:(string)$row[$key])) throw new InvalidArgumentException();
        return $d;
    } catch (JsonException|InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP access signed history needs recovery.',0,$e);}
}
function provider_stackcp_access_finish_check(array $start,array $finish):void {
    $binding=new StackCpAccessBinding($start['binding']);$c=$finish['common'];
    foreach (provider_stackcp_access_common_keys() as $key) if ($c[$key]!==$start['common'][$key]) throw new RuntimeException('StackCP access result context needs recovery.');
    if ($c['coverage']!=='unknown'||$c['package_count']!=='0'||$c['skipped']!=='0'||$c['skip_reasons']!=='[]') throw new RuntimeException('StackCP access result coverage needs recovery.');
    if ($finish['state']==='complete') {
        try {$o=new StackCpAccessObservation($binding,$finish['observed_epoch'],'complete',$finish['reason'],$finish['values']);}catch(InvalidArgumentException|TypeError $e) {throw new RuntimeException('StackCP saved access values need recovery.',0,$e);}
        if ($c['scope']!==$binding->scope||$c['key_fingerprint']!==$binding->data['key_fingerprint']||$o->observedAt<(int)$c['started_epoch']) throw new RuntimeException('StackCP saved access context needs recovery.');
    }
}
