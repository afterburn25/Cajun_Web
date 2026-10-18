<?php
/** Closed local journal contract for the scheduled hosting-list reader. No provider schema is inferred. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function provider_schedule_json(array $value):string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
function provider_schedule_id(mixed $v):bool {return is_string($v)&&preg_match('/\A[a-f0-9]{32}\z/',$v)===1;}
function provider_schedule_hash(mixed $v):bool {return is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/',$v)===1;}
function provider_schedule_number(mixed $v,int $min=0):bool {return is_int($v)&&$v>=$min&&$v<=2147483647;}
function provider_schedule_keys(array $v,array $keys):bool {return array_keys($v)===$keys;}
function provider_schedule_document(string $bytes):array {
    $fail=static function():never{throw new RuntimeException('Original scheduled refresh history needs recovery.');};
    if(strlen($bytes)>16384)$fail();
    try{$d=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);}catch(JsonException){$fail();}
    if(!is_array($d)||!provider_schedule_keys($d,['format','sequence','id','kind','subject','actor','at','previous','data','state'])||$d['format']!==1||!provider_schedule_number($d['sequence'],1)||!provider_schedule_id($d['id'])||!provider_schedule_id($d['subject'])||!provider_schedule_number($d['actor'],1)||!provider_schedule_number($d['at'],1)||!provider_schedule_hash($d['previous'])||!is_array($d['data'])||!is_array($d['state'])||provider_schedule_json($d)!==$bytes)$fail();
    $s=$d['state'];
    if(!provider_schedule_keys($s,['approval','approval_sha256','pending','next','failures','paused'])||($s['approval']!==null&&!provider_schedule_id($s['approval']))||($s['approval_sha256']!==null&&!provider_schedule_hash($s['approval_sha256']))||(($s['approval']===null)!==($s['approval_sha256']===null)&&$d['kind']!=='approval')||($s['pending']!==null&&!provider_schedule_id($s['pending']))||!provider_schedule_number($s['next'])||!provider_schedule_number($s['failures'])||$s['failures']>16||!is_bool($s['paused']))$fail();
    $v=$d['data'];
    switch($d['kind']){
    case 'setup':
        if($d['sequence']!==1||$v!==[]||$d['previous']!==str_repeat('0',64)||$s!==['approval'=>null,'approval_sha256'=>null,'pending'=>null,'next'=>0,'failures'=>0,'paused'=>true])$fail();break;
    case 'approval':
        if(!provider_schedule_keys($v,['profile_id','profile_revision','credential_sha256','owner_auth_sha256','document_root','installation_root','enabled','interval'])||!provider_schedule_id($v['profile_id'])||!provider_schedule_number($v['profile_revision'],1)||!provider_schedule_hash($v['credential_sha256'])||!provider_schedule_hash($v['owner_auth_sha256'])||!is_bool($v['enabled'])||!in_array($v['interval'],[900,1800,3600,21600,86400],true))$fail();
        foreach(['document_root','installation_root'] as $key)if(!is_string($v[$key])||$v[$key]===''||strlen($v[$key])>2048||preg_match('/[\x00-\x1f\x7f]/',$v[$key]))$fail();
        // The approval hash is established by the following state reader, avoiding a self-hash.
        if($s['approval']!==$d['id']||$s['approval_sha256']!==null||$s['pending']!==null||$s['failures']!==0||$s['next']!==($v['enabled']?$d['at']:0)||$s['paused']!==!$v['enabled'])$fail();break;
    case 'pause':
        if($v!==[]||!$s['paused']||$s['next']!==0)$fail();break;
    case 'start':
        if(!provider_schedule_keys($v,['approval_id','approval_sha256','profile_id','profile_revision','lease_until'])||!provider_schedule_id($v['approval_id'])||!provider_schedule_hash($v['approval_sha256'])||!provider_schedule_id($v['profile_id'])||!provider_schedule_number($v['profile_revision'],1)||!provider_schedule_number($v['lease_until'],1)||$v['lease_until']!==$d['at']+120||$s['approval']!==$v['approval_id']||$s['approval_sha256']!==$v['approval_sha256']||$s['pending']!==$d['subject']||$s['next']!==0)$fail();break;
    case 'finish':
        if(!provider_schedule_keys($v,['start_id','start_sha256','result_sha256','outcome','reason','recovered'])||!provider_schedule_id($v['start_id'])||!provider_schedule_hash($v['start_sha256'])||!provider_schedule_hash($v['result_sha256'])||!in_array($v['outcome'],['complete','partial','unavailable','refused','credential','access_changed'],true)||!is_string($v['reason'])||strlen($v['reason'])>80||preg_match('/[^a-z0-9_]/',$v['reason'])||!is_bool($v['recovered'])||$s['pending']!==null||($v['recovered']&&($v['outcome']!=='refused'||$v['reason']!=='interrupted')))$fail();break;
    default:$fail();
    }
    return $d;
}
