<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_password_reset_template_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

/** Private, bounded, append-only history. Content never enters the audit or generic operation scope. */
const PROVIDER_RESET_TEMPLATE_TABLE='provider_password_reset_template_events';
const PROVIDER_RESET_TEMPLATE_SETTING='provider_password_reset_template_';
const PROVIDER_RESET_TEMPLATE_OPERATION='reseller.passwordresettemplate';
const PROVIDER_RESET_TEMPLATE_LIMIT=10000;

function provider_password_reset_template_guard(bool $write=false):void {
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($write&&!db()->inTransaction())||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Template history requires the owning staff mutex; writes require a transaction.');
}
function provider_password_reset_template_json(array $value):string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_password_reset_template_id(string $value):string {
    if(preg_match('/\A[a-f0-9]{32}\z/',$value)!==1)throw new InvalidArgumentException('The template request is unavailable.');return $value;
}
function provider_password_reset_template_digest(string $value):string {
    if(preg_match('/\A[a-f0-9]{64}\z/',$value)!==1)throw new RuntimeException('Template history needs recovery.');return $value;
}
function provider_password_reset_template_hmac(string $purpose,string $bytes):string {
    provider_password_reset_template_guard();
    return hash_hmac('sha256','password-reset-template-v1|'.config()['db']['prefix'].'|'.$purpose.'|'.$bytes,provider_settings_vault_key());
}
function provider_password_reset_template_exists():bool {
    return one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].PROVIDER_RESET_TEMPLATE_TABLE])!==null;
}
function provider_password_reset_template_schema_validate():void {
    $sql=provider_password_reset_template_schema(config()['db']['prefix'])[PROVIDER_RESET_TEMPLATE_TABLE];
    catalog_validate_schema(PROVIDER_RESET_TEMPLATE_TABLE,$sql,['reset_template_seq'=>['seq']]);
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].PROVIDER_RESET_TEMPLATE_TABLE]);
    if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Template history requires transactional storage.');
    foreach(all('SHOW FULL COLUMNS FROM '.table(PROVIDER_RESET_TEMPLATE_TABLE)) as $c){
        $text=preg_match('/^(?:char|varchar|longtext)/i',$c['Type'])===1;
        if($c['Default']!==null||$c['Extra']!==''||$c['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('Template history columns need recovery.');
    }
    $indexes=[];foreach(all('SHOW INDEX FROM '.table(PROVIDER_RESET_TEMPLATE_TABLE)) as $i){
        if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Template history indexes need recovery.');
        $indexes[$i['Key_name']][0]=(int)$i['Non_unique'];$indexes[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];
    }
    foreach($indexes as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($indexes);
    $expected=['PRIMARY'=>[0,['id']],'reset_template_seq'=>[0,['seq']],'reset_template_profile'=>[1,['profile_id','seq']],'reset_template_request'=>[1,['request_id','seq']]];ksort($expected);
    if($indexes!==$expected||one('SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].PROVIDER_RESET_TEMPLATE_TABLE]))throw new RuntimeException('Template history relationships need recovery.');
}
function provider_password_reset_template_seal(string $id,string $profile,#[SensitiveParameter] string $content):string {
    provider_password_reset_template_guard(true);$iv=random_bytes(12);$tag='';
    $aad='password-reset-template-content-v1|'.config()['db']['prefix'].'|'.$profile.'|'.$id;
    $cipher=openssl_encrypt($content,'aes-256-gcm',provider_settings_vault_key(),OPENSSL_RAW_DATA,$iv,$tag,$aad);
    if($cipher===false||strlen($tag)!==16)throw new RuntimeException('The private template could not be protected.');return base64_encode($iv.$tag.$cipher);
}
function provider_password_reset_template_open(array $event):string {
    provider_password_reset_template_guard();$d=$event['document'];
    $row=one('SELECT document_json,document_sha256,proof,ciphertext FROM '.table(PROVIDER_RESET_TEMPLATE_TABLE).' WHERE id=?',[$d['id']]);
    if(!$row||$row['document_json']!==$event['row']['document_json']||$row['document_sha256']!==$event['row']['document_sha256']||$row['proof']!==$event['row']['proof']||!hash_equals(hash('sha256',$row['ciphertext']),$d['ciphertext_sha256']))throw new RuntimeException('Private template history changed.');
    $raw=base64_decode($row['ciphertext'],true);
    if($raw===false||strlen($raw)<28||base64_encode($raw)!==$row['ciphertext'])throw new RuntimeException('Private template history needs recovery.');
    $aad='password-reset-template-content-v1|'.config()['db']['prefix'].'|'.$d['profile_id'].'|'.$d['id'];
    $text=openssl_decrypt(substr($raw,28),'aes-256-gcm',provider_settings_vault_key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$aad);
    if(!is_string($text)||!hash_equals($d['data']['content_sha256']??'',hash('sha256',$text))||strlen($text)!==($d['data']['content_bytes']??null))throw new RuntimeException('Private template history needs recovery.');return $text;
}
/** Returns null only for a never-prepared, unused namespace; partial loss never means a fresh start. */
function provider_password_reset_template_events():?array {
    provider_password_reset_template_guard();$prefix=PROVIDER_RESET_TEMPLATE_SETTING;
    $settings=all('SELECT name,value FROM '.table('settings').' WHERE LEFT(name,?)=? ORDER BY name',[strlen($prefix),$prefix]);$s=[];foreach($settings as $row)$s[substr($row['name'],strlen($prefix))]=$row['value'];
    $audits=all('SELECT id,user_id,action,detail FROM '.table('audit').' WHERE LEFT(action,?)=? ORDER BY id',[strlen('provider.reset_template.'),'provider.reset_template.']);
    $exists=provider_password_reset_template_exists();
    if(!$s){
        $used=$audits!==[]||($exists&&one('SELECT id FROM '.table(PROVIDER_RESET_TEMPLATE_TABLE).' LIMIT 1'));
        foreach(['provider_operation_grants'=>"BINARY operation='reseller.passwordresettemplate'",'provider_operations'=>"BINARY operation='reseller.passwordresettemplate' OR LEFT(idempotency_key,15)='reset-template:'"] as $name=>$where){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])&&one('SELECT id FROM '.table($name).' WHERE '.$where.' LIMIT 1'))$used=true;
        }
        if($used)throw new RuntimeException('Restore the original password-reset template history.');return null;
    }
    $keys=array_keys($s);sort($keys);$required=['checkpoint','count','head','preparation','preparation_proof','version'];sort($required);
    if($keys!==$required||$s['version']!=='1'||!$exists||!provider_operations_ready()||preg_match('/\A(?:0|[1-9][0-9]{0,4})\z/',$s['count'])!==1||(int)$s['count']>PROVIDER_RESET_TEMPLATE_LIMIT)throw new RuntimeException('Template history needs recovery.');
    provider_password_reset_template_schema_validate();provider_password_reset_template_digest($s['head']);
    if((int)query('SELECT COUNT(*) FROM '.table(PROVIDER_RESET_TEMPLATE_TABLE))->fetchColumn()!==(int)$s['count'])throw new RuntimeException('Template history count needs recovery.');
    try{$prepared=json_decode($s['preparation'],true,8,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Template preparation needs recovery.');}
    if(!is_array($prepared)||array_keys($prepared)!==['version','actor_id','at']||$prepared['version']!==1||!is_int($prepared['actor_id'])||$prepared['actor_id']<1||!is_int($prepared['at'])||$prepared['at']<1||provider_password_reset_template_json($prepared)!==$s['preparation']||!hash_equals(provider_password_reset_template_hmac('prepared',$s['preparation']),$s['preparation_proof'])||!hash_equals(provider_password_reset_template_hmac('checkpoint',$s['count'].'|'.$s['head'].'|'.$s['preparation_proof']),$s['checkpoint']))throw new RuntimeException('Template preparation needs recovery.');
    $expectedAudit=[['user_id'=>$prepared['actor_id'],'action'=>'provider.reset_template.prepared','detail'=>'Password-reset template storage '.$s['preparation_proof']]];
    $events=[];$head=str_repeat('0',64);$seq=0;
    do{
      $rows=all('SELECT * FROM '.table(PROVIDER_RESET_TEMPLATE_TABLE).' WHERE seq>? ORDER BY seq LIMIT 32',[$seq]);
      foreach($rows as $row){
        if($seq>=PROVIDER_RESET_TEMPLATE_LIMIT)throw new RuntimeException('Template history exceeds its bound.');
        try{$d=json_decode($row['document_json'],true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Template history document needs recovery.');}
        if(!is_array($d)||array_keys($d)!==['version','id','seq','kind','profile_id','request_id','actor_id','at','previous_sha256','data','ciphertext_sha256']||$d['version']!==1||$d['seq']!==++$seq||!is_int($d['actor_id'])||$d['actor_id']<1||!is_int($d['at'])||$d['at']<1||!is_array($d['data'])||$d['previous_sha256']!==$head||!in_array($d['kind'],['read_start','read_end','request','terminal','check','dismissal'],true))throw new RuntimeException('Template history graph needs recovery.');
        foreach(['id','profile_id','request_id'] as $key)if(!is_string($d[$key])||preg_match('/\A[a-f0-9]{32}\z/',$d[$key])!==1||$d[$key]!==$row[$key])throw new RuntimeException('Template history identity needs recovery.');
        if((string)$d['seq']!==(string)$row['seq']||(string)$d['actor_id']!==(string)$row['actor_id']||(string)$d['at']!==(string)$row['recorded_epoch']||$d['kind']!==$row['kind']||provider_password_reset_template_json($d)!==$row['document_json']||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256'])||!hash_equals(hash('sha256',$row['ciphertext']),$d['ciphertext_sha256'])||!hash_equals(provider_password_reset_template_hmac('event',$row['document_json']),$row['proof']))throw new RuntimeException('Template history proof needs recovery.');
        if(strlen($row['ciphertext'])>88000||($row['ciphertext']!==''&&(!in_array($d['kind'],['request','read_end'],true)||base64_decode($row['ciphertext'],true)===false)))throw new RuntimeException('Template private history needs recovery.');
        $row['ciphertext_bytes']=strlen($row['ciphertext']);unset($row['ciphertext']);
        $events[$d['id']]=['row'=>$row,'document'=>$d];$head=$row['document_sha256'];
        $expectedAudit[]=['user_id'=>$d['actor_id'],'action'=>'provider.reset_template.event','detail'=>'Password-reset template event '.$d['id'].' '.$head.' '.$row['proof']];
      }
    }while(count($rows)===32);
    if($seq!==(int)$s['count']||$head!==$s['head']||count($audits)!==count($expectedAudit))throw new RuntimeException('Template history checkpoint needs recovery.');
    foreach($audits as $i=>$a)if((int)$a['user_id']!==$expectedAudit[$i]['user_id']||$a['action']!==$expectedAudit[$i]['action']||$a['detail']!==$expectedAudit[$i]['detail'])throw new RuntimeException('Template history audit correspondence needs recovery.');
    return $events;
}
function provider_password_reset_template_append(string $kind,string $id,string $profile,string $request,int $actor,array $data,#[SensitiveParameter] ?string $content=null,?int $at=null):array {
    provider_password_reset_template_guard(true);provider_password_reset_template_id($id);provider_password_reset_template_id($profile);provider_password_reset_template_id($request);
    $prefix=PROVIDER_RESET_TEMPLATE_SETTING;$count=(int)(one('SELECT value FROM '.table('settings').' WHERE name=?',[$prefix.'count'])['value']??'-1');
    if($count<0||$count>=PROVIDER_RESET_TEMPLATE_LIMIT)throw new RuntimeException('Template history is full or unavailable.');
    $head=one('SELECT value FROM '.table('settings').' WHERE name=?',[$prefix.'head'])['value']??'';provider_password_reset_template_digest($head);
    $cipher=$content===null?'':provider_password_reset_template_seal($id,$profile,$content);
    $d=['version'=>1,'id'=>$id,'seq'=>$count+1,'kind'=>$kind,'profile_id'=>$profile,'request_id'=>$request,'actor_id'=>$actor,'at'=>$at??time(),'previous_sha256'=>$head,'data'=>$data,'ciphertext_sha256'=>hash('sha256',$cipher)];
    $json=provider_password_reset_template_json($d);if(strlen($json)>8192)throw new LogicException('Template evidence exceeds its metadata bound.');
    $hash=hash('sha256',$json);$proof=provider_password_reset_template_hmac('event',$json);
    query('INSERT INTO '.table(PROVIDER_RESET_TEMPLATE_TABLE).' (id,seq,kind,profile_id,request_id,actor_id,recorded_epoch,document_json,document_sha256,ciphertext,proof) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$id,$count+1,$kind,$profile,$request,$actor,$d['at'],$json,$hash,$cipher,$proof]);
    provider_settings_audit($actor,'reset_template.event','Password-reset template event '.$id.' '.$hash.' '.$proof);
    $preparation=one('SELECT value FROM '.table('settings').' WHERE name=?',[$prefix.'preparation_proof'])['value']??'';
    save_setting($prefix.'count',(string)($count+1));save_setting($prefix.'head',$hash);save_setting($prefix.'checkpoint',provider_password_reset_template_hmac('checkpoint',($count+1).'|'.$hash.'|'.$preparation));
    return ['row'=>['id'=>$id,'seq'=>$count+1,'kind'=>$kind,'profile_id'=>$profile,'request_id'=>$request,'actor_id'=>$actor,'recorded_epoch'=>$d['at'],'document_json'=>$json,'document_sha256'=>$hash,'ciphertext_bytes'=>strlen($cipher),'proof'=>$proof],'document'=>$d];
}
