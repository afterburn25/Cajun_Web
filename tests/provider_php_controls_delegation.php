<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/php_controls_delegation_document.php';
require CWP_CODE_ROOT.'/database/schema/provider_php_control_delegation_schema.php';
use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;

$checks=[];
function pc_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function pc_refuse(string $name,callable $work):void {$refused=false;try{$work();}catch(LogicException|RuntimeException|JsonException){$refused=true;}pc_check($name,$refused);}
function pc_seal(array $d):array {
    $r=['format'=>1,'link_id'=>$d['link_id'],'enabled'=>$d['enabled'],'previous_id'=>$d['previous_id'],'previous_sha256'=>$d['previous_sha256'],'owner_id'=>$d['owner_id'],'owner_auth_version'=>$d['owner_auth_version'],'client_auth_version'=>$d['client_auth_version'],'binding'=>$d['binding']];$d['review_sha256']=hash('sha256',provider_php_controls_json($r));return $d;
}
/** Load only these exact production functions; SQL, sessions and authority prerequisites below are modeled seams. */
function pc_load(string $relative,array $names):void {
    $tokens=token_get_all(file_get_contents(CWP_CODE_ROOT.'/'.$relative));$found=[];
    for($i=0;$i<count($tokens);$i++)if(is_array($tokens[$i])&&$tokens[$i][0]===T_FUNCTION){
        $start=$i;$j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(!is_array($tokens[$j]??null)||$tokens[$j][0]!==T_STRING||!in_array($tokens[$j][1],$names,true))continue;
        $name=$tokens[$j][1];$text='';$depth=0;$body=false;
        for($k=$start;$k<count($tokens);$k++){$t=$tokens[$k];$text.=is_array($t)?$t[1]:$t;if($t==='{'){$body=true;$depth++;}elseif($t==='}'){$depth--;if($body&&$depth===0)break;}}
        if(!$body||$depth!==0||isset($found[$name]))throw new RuntimeException('Function extraction failed.');eval($text);$found[$name]=true;$i=$k;
    }if(count($found)!==count($names))throw new RuntimeException('Expected function missing.');
}
function provider_php_controls_guard(bool $transaction=false):void {global $model;if(!$model['mutex']||($transaction&&!$model['transaction']))throw new LogicException('Modeled guard refused.');}
function support_current():?array {global $model;return $model['support']?['client'=>$model['client']]:null;}
function studio_client():?array {global $model;$model['normalizations']++;if(!$model['session_current'])return null;return $model['client'];}
function provider_settings_manager():array {global $model;if(!$model['manager']||$model['support'])throw new RuntimeException('Modeled owner refused.');return $model['owner'];}
function provider_php_controls_ready():bool {global $model;return $model['ready'];}
function provider_php_binding(string $link):PackagePhpVersionBinding {global $model;if(!$model['fresh']||$model['binding']->data['link_id']!==$link)throw new RuntimeException('Modeled fresh assignment refused.');return $model['binding'];}
function provider_php_controls_permission(string $link):?array {global $model;return $model['ready']?$model['permission']:null;}
function staff_ready():bool {global $model;return $model['staff_ready'];}
function table(string $name):string {return $name;}
function one(string $sql,array $args=[]):?array {global $model;if($sql==='SELECT * FROM users WHERE id=?')return $model['owner']['id']===$args[0]?$model['owner']:null;throw new LogicException('Unexpected modeled query.');}
function staff_profile(array $owner):?array {global $model;return $model['owner_enabled']?$owner:null;}
function provider_php_assignment_matches(PackagePhpVersionBinding $binding):bool {global $model;return $model['assignment_matches'];}
function provider_settings_vault_key():string {return 'fixed-synthetic-delegation-test-key';}
function provider_php_digest(array $values):string {$b='';foreach($values as $v){$v=(string)$v;$b.=strlen($v).':'.$v;}return hash('sha256',$b);}
function provider_php_retained_binding(PackagePhpVersionBinding $binding):void {global $model;if(!$model['retained_source'])throw new RuntimeException('Modeled retained source missing.');}
function all(string $sql,array $args=[]):array {global $model;if(str_starts_with($sql,'SELECT id,user_id FROM audit WHERE BINARY action='))return $model['audits'];throw new LogicException('Unexpected modeled read.');}
function pc_reset():void {global $model,$baseModel;$model=$baseModel;$_SESSION=[];$_GET=[];}

try{
    $binding=new PackagePhpVersionBinding(['profile_id'=>str_repeat('a',32),'profile_revision'=>2,'key_fingerprint'=>str_repeat('b',64),'credential_sha256'=>str_repeat('c',64),'package_id'=>101,'client_id'=>7,'link_id'=>str_repeat('d',32),'link_sha256'=>str_repeat('e',64),'event_id'=>5,'event_sha256'=>str_repeat('f',64),'audit_id'=>6,'audit_sha256'=>str_repeat('1',64),'source_id'=>str_repeat('2',32),'source_seq'=>3,'source_sha256'=>str_repeat('3',64)]);
    $d=pc_seal(['format'=>1,'id'=>str_repeat('4',32),'link_id'=>$binding->data['link_id'],'client_id'=>7,'revision'=>1,'owner_id'=>8,'owner_auth_version'=>str_repeat('5',64),'client_auth_version'=>str_repeat('6',32),'recorded_epoch'=>1700000000,'previous_id'=>'','previous_sha256'=>str_repeat('0',64),'enabled'=>true,'review_sha256'=>str_repeat('0',64),'binding'=>$binding->data]);
    $json=provider_php_controls_json($d);pc_check('Canonical delegation roundtrips exactly',provider_php_controls_delegation_parse($json)===$d);
    foreach(['id'=>'bad','client_id'=>'7','revision'=>0,'owner_id'=>0,'owner_auth_version'=>'not-auth','client_auth_version'=>str_repeat('a',33),'recorded_epoch'=>-1,'enabled'=>1,'previous_id'=>str_repeat('a',32),'previous_sha256'=>str_repeat('a',64),'review_sha256'=>str_repeat('a',64)] as $key=>$v){$bad=$d;$bad[$key]=$v;pc_refuse('Reject invalid delegation '.$key,fn()=>provider_php_controls_delegation_document($bad));}
    $bad=$d;$bad['enabled']=false;pc_refuse('First delegation cannot be a revocation',fn()=>provider_php_controls_delegation_document(pc_seal($bad)));
    $bad=$d;$bad['binding']['client_id']=99;pc_refuse('Binding cannot target a different client',fn()=>provider_php_controls_delegation_document(pc_seal($bad)));
    $bad=$d;$bad['binding']['link_id']=str_repeat('a',32);pc_refuse('Binding cannot target a different assignment',fn()=>provider_php_controls_delegation_document(pc_seal($bad)));
    $bad=$d;$bad['owner_id']=9;pc_refuse('Retained review must match its owner',fn()=>provider_php_controls_delegation_document($bad));
    $valid=pc_seal($bad);pc_check('A separately reviewed owner is a different valid document',provider_php_controls_delegation_document($valid)===$valid&&hash('sha256',provider_php_controls_json($valid))!==hash('sha256',$json));
    pc_refuse('Noncanonical whitespace is refused',fn()=>provider_php_controls_delegation_parse($json.' '));
    pc_refuse('Duplicate JSON field is refused',fn()=>provider_php_controls_delegation_parse(str_replace('"format":1','"format":1,"format":1',$json)));
    pc_refuse('Unknown field is refused',fn()=>provider_php_controls_delegation_document($d+['extra'=>true]));
    pc_refuse('Oversized document is refused',fn()=>provider_php_controls_delegation_parse(str_repeat(' ',16385)));
    $revoked=$d;$revoked['id']=str_repeat('7',32);$revoked['revision']=2;$revoked['previous_id']=$d['id'];$revoked['previous_sha256']=hash('sha256',$json);$revoked['enabled']=false;$revoked=pc_seal($revoked);pc_check('Historical revocation roundtrips',provider_php_controls_delegation_parse(provider_php_controls_json($revoked))===$revoked);
    $schema=provider_php_control_delegation_schema('test_');pc_check('Dedicated schema has one immutable table and two actual identity FKs',count($schema)===1&&substr_count(current($schema),'FOREIGN KEY')===2&&str_contains(current($schema),'UNIQUE KEY php_control_delegation_revision(link_id,revision)')&&!str_contains(current($schema),'CASCADE'));
    pc_refuse('Schema prefix injection is refused',fn()=>provider_php_control_delegation_schema('x`_'));
    $baseModel=['mutex'=>true,'transaction'=>true,'support'=>false,'normalizations'=>0,'session_current'=>true,'manager'=>true,'ready'=>true,'fresh'=>true,'staff_ready'=>true,'owner_enabled'=>true,'assignment_matches'=>true,'retained_source'=>true,'audits'=>[['id'=>'9','user_id'=>'8']],'binding'=>$binding,'owner'=>['id'=>8,'role'=>'owner','auth_version'=>$d['owner_auth_version'],'password_hash'=>'synthetic-owner-hash'],'client'=>['id'=>7,'active'=>1,'auth_version'=>$d['client_auth_version'],'password_hash'=>password_hash('Synthetic-client-password-123!',PASSWORD_DEFAULT)],'permission'=>['document'=>$d,'sha256'=>hash('sha256',$json),'binding'=>$binding]];
    pc_load('app/providers/php_controls_authority.php',['provider_php_controls_context','provider_php_controls_primary_client','provider_php_controls_client_password','provider_php_controls_owner_context','provider_php_controls_client_context','provider_php_controls_reauthorize']);
    pc_load('app/providers/php_controls_delegation_storage.php',['provider_php_controls_row_values','provider_php_controls_proof','provider_php_controls_audit_detail','provider_php_controls_decode']);
    pc_reset();$clientContext=provider_php_controls_client_context(7,$d['link_id']);pc_check('Actual primary client context has no staff impersonation',$clientContext['document']['actor']===['kind'=>'client','id'=>7,'auth_version'=>$d['client_auth_version']]);
    pc_check('Client context excludes password and email',!str_contains(provider_php_controls_json($clientContext['document']),'password')&&!str_contains(provider_php_controls_json($clientContext['document']),'email'));
    pc_check('Exact unchanged current context reauthorizes',provider_php_controls_reauthorize($clientContext)['sha256']===$clientContext['sha256']);
    pc_check('Actual password check accepts the synthetic current client password',provider_php_controls_client_password(7,'Synthetic-client-password-123!')['id']===7);
    pc_refuse('Wrong current client password refuses',fn()=>provider_php_controls_client_password(7,'wrong'));
    foreach(['contact_id'=>17,'contact_auth'=>'retained-contact-auth'] as $key=>$value){pc_reset();$_SESSION[$key]=$value;pc_refuse('Known '.$key.' refuses before identity normalization',fn()=>provider_php_controls_client_context(7,$d['link_id']));pc_check('No client normalizer ran for '.$key,$model['normalizations']===0);}
    pc_reset();$_GET['support']='';pc_refuse('Support URL refuses before client normalization',fn()=>provider_php_controls_client_context(7,$d['link_id']));pc_check('Support URL did not normalize client',$model['normalizations']===0);
    foreach(['support'=>true,'mutex'=>false,'transaction'=>false,'session_current'=>false,'ready'=>false,'fresh'=>false,'staff_ready'=>false,'owner_enabled'=>false,'assignment_matches'=>false] as $key=>$value){pc_reset();$model[$key]=$value;pc_refuse('Current client refuses changed '.$key,fn()=>provider_php_controls_client_context(7,$d['link_id']));}
    pc_reset();$model['permission']=null;$model['old_visibility_enabled']=true;pc_refuse('Saved-value sharing alone does not permit changes',fn()=>provider_php_controls_client_context(7,$d['link_id']));
    pc_reset();$model['permission']['document']['enabled']=false;pc_refuse('Revoked delegation refuses',fn()=>provider_php_controls_client_context(7,$d['link_id']));
    pc_reset();$model['client']['active']=0;pc_refuse('Disabled primary client refuses',fn()=>provider_php_controls_client_context(7,$d['link_id']));
    pc_reset();$model['client']['auth_version']=str_repeat('a',32);pc_refuse('Rotated client auth requires new delegation',fn()=>provider_php_controls_client_context(7,$d['link_id']));
    pc_reset();$model['owner']['auth_version']=str_repeat('b',32);pc_refuse('Rotated granting owner auth refuses',fn()=>provider_php_controls_client_context(7,$d['link_id']));
    pc_reset();$model['owner']['role']='administrator';pc_refuse('Demoted granting owner refuses',fn()=>provider_php_controls_client_context(7,$d['link_id']));
    pc_reset();pc_refuse('Wrong primary client cannot choose another assignment',fn()=>provider_php_controls_client_context(9,$d['link_id']));
    pc_reset();$new=$binding->data;$new['source_id']=str_repeat('8',32);$new['source_seq']=4;$model['binding']=new PackagePhpVersionBinding($new);pc_check('Fresh replacement inventory does not itself revoke delegation',provider_php_controls_client_context(7,$d['link_id'])['document']['binding']['source_seq']===4);pc_refuse('An older reviewed operation context cannot authorize newer inventory',fn()=>provider_php_controls_reauthorize($clientContext));
    pc_reset();$GLOBALS['cwp_settings']=['staff_schema_version'=>''];$ownerContext=provider_php_controls_owner_context($d['link_id']);pc_check('Owner context clears request settings cache',!isset($GLOBALS['cwp_settings']));pc_check('Owner uses actual staff identity without client permission',$ownerContext['document']['actor']['kind']==='staff'&&$ownerContext['document']['permission_id']===null);
    $row=['id'=>$d['id'],'link_id'=>$d['link_id'],'client_id'=>7,'revision'=>1,'owner_id'=>8,'recorded_epoch'=>$d['recorded_epoch'],'previous_sha256'=>$d['previous_sha256'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];$row['proof']=provider_php_controls_proof($row);
    pc_check('Exact retained row and historical source decode',provider_php_controls_decode($row)['document']===$d);
    pc_check('Audit witness remains below existing 400-byte bound',strlen(provider_php_controls_audit_detail($row))<=400);
    foreach(['document_sha256'=>str_repeat('a',64),'proof'=>str_repeat('b',64),'owner_id'=>'9','revision'=>'2'] as $key=>$value){$bad=$row;$bad[$key]=$value;pc_refuse('Changed retained row '.$key.' refuses',fn()=>provider_php_controls_decode($bad));}
    foreach(['missing'=>[],'duplicate'=>[['id'=>9,'user_id'=>8],['id'=>10,'user_id'=>8]],'wrong actor'=>[['id'=>9,'user_id'=>99]]] as $name=>$audit){pc_reset();$model['audits']=$audit;pc_refuse('Retained '.$name.' audit refuses',fn()=>provider_php_controls_decode($row));}
    pc_reset();$model['retained_source']=false;pc_refuse('Lost original package source refuses historical delegation',fn()=>provider_php_controls_decode($row));
    $engineEscaped=false;try{pc_refuse('Unexpected engine error must not become a denial',static function():void{throw new TypeError('synthetic engine error');});}catch(TypeError){$engineEscaped=true;}pc_check('Unexpected engine errors escape the denial helper',$engineEscaped);
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'scope'=>'Actual pure documents and extracted production authority/row validators with explicitly modeled SQL/session/prerequisite seams. No native SQL, provider, HTTP or image acceptance.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(1);}
