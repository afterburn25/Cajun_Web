<?php
/** Actual intake and authority-validator bodies; modeled persistence/session boundaries, no SQL or network. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../site');
set_error_handler(static function(int $n,string $m,string $f,int $l):never{throw new ErrorException($m,0,$n,$f,$l);});
require CWP_CODE_ROOT.'/app/providers/cloud_site_requests_document.php';
require CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryRead.php';
use CajunWebPro\Foundation\TwentyICloudAssignment\CloudAssignmentBinding;
use CajunWebPro\Foundation\TwentyI\Operations\CloudWebsiteCreate;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
class_alias(CloudWebsiteCreate::class,'CloudWebsiteCreate');
class_alias(InventoryRead::class,'InventoryRead');
class_alias(CloudAssignmentBinding::class,'CloudAssignmentBinding');
$checks=[];$extracted=[];$ci=[];

/** Token extraction preserves complete production bodies, including quoted/interpolated braces. */
function ci_extract(string $file,array $names):void {
    $text=file_get_contents(CWP_CODE_ROOT.'/app/providers/'.$file);$tokens=token_get_all($text);$found=[];
    for($i=0;$i<count($tokens);++$i){
        if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;
        $j=$i+1;while(is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)++$j;
        $name=is_array($tokens[$j])?$tokens[$j][1]:'';if(!in_array($name,$names,true))continue;
        $code='';$depth=0;$opened=false;
        for(;$i<count($tokens);++$i){$t=$tokens[$i];$code.=is_array($t)?$t[1]:$t;
            if($t==='{'||(is_array($t)&&in_array($t[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))){++$depth;$opened=true;}
            elseif($t==='}'&&--$depth===0&&$opened)break;
        }
        if(!$opened||$depth!==0)throw new LogicException('Production function boundary missing: '.$name);
        eval($code);$found[]=$name;$GLOBALS['extracted'][$file][$name]=hash('sha256',$code);
    }
    sort($found);sort($names);if($found!==$names)throw new LogicException('Exact production function set changed: '.$file);
}
function ci_check(string $name,bool $passed):void {
    foreach($GLOBALS['checks'] as $c)if($c['name']===$name)throw new LogicException('Duplicate check: '.$name);
    $GLOBALS['checks'][]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new LogicException($name);
}
/** Engine/type/missing-symbol errors never count as expected refusals. */
function ci_deny(string $name,callable $run,string $message='',?string $class=null):void {
    $caught=null;try{$run();}catch(RuntimeException|InvalidArgumentException $e){$caught=$e;}
    ci_check($name,$caught!==null&&($class===null||$caught instanceof $class)&&($message===''||$caught->getMessage()===$message));
}
function ci_logic(string $name,callable $run):void {
    $caught=null;try{$run();}catch(LogicException $e){$caught=$e;}ci_check($name,$caught!==null&&$caught->getMessage()==='Cloud assignment evidence requires the owning account transaction and mutex.');
}
function ci_trace(string $name):void{$GLOBALS['ci']['trace'][]=$name;}
function ci_changed(string $path,mixed $value):void {$at=&$GLOBALS['ci'];foreach(explode('.',$path) as $key)$at=&$at[$key];$at=$value;unset($at);}
function ci_model_failure(string $name):void {$e=$GLOBALS['ci'][$name]??null;if($e instanceof Throwable)throw $e;}
final class CloudIntakeModelDb {public function inTransaction():bool{return $GLOBALS['ci']['tx'];}}
function db():CloudIntakeModelDb {static $db;return $db??=new CloudIntakeModelDb();}
function config():array{return ['db'=>['name'=>'portable-no-database','prefix'=>'modeled_']];}
function table(string $name):string{return $name;}
function query(string $sql,array $params=[]):object {
    if($sql!=='SELECT IS_USED_LOCK(?)=CONNECTION_ID()'||count($params)!==1)throw new LogicException('Unexpected SQL mutation/query in portable intake model.');
    return new class {public function fetchColumn():int{return $GLOBALS['ci']['locked']?1:0;}};
}
function staff_locked(callable $run):mixed {$old=$GLOBALS['ci']['locked'];$GLOBALS['ci']['locked']=true;try{return $run();}finally{$GLOBALS['ci']['locked']=$old;}}
function staff_transaction(callable $run):mixed {$old=$GLOBALS['ci']['tx'];$GLOBALS['ci']['tx']=true;try{return $run();}finally{$GLOBALS['ci']['tx']=$old;}}
function one(string $sql,array $params=[]):?array {
    ci_trace('query:'.$sql);$m=$GLOBALS['ci'];
    if($sql==='SELECT * FROM provider_cloud_links WHERE id=?')return ($m['link']['id']??null)===($params[0]??null)?$m['link']:null;
    if($sql==='SELECT * FROM provider_cloud_link_events WHERE BINARY link_id=? ORDER BY id DESC LIMIT 1')return ($m['event']['link_id']??null)===($params[0]??null)?$m['event']:null;
    if($sql==='SELECT * FROM provider_profiles WHERE id=?')return ($m['profile']['id']??null)===($params[0]??null)?$m['profile']:null;
    if($sql==='SELECT id FROM studio_clients WHERE id=? AND active=1'||$sql==='SELECT auth_version FROM studio_clients WHERE id=? AND active=1'){
        $c=$m['clients'][$params[0]]??null;return $c&&$c['active']===1?$c:null;
    }
    if($sql==='SELECT u.auth_version,p.role,p.enabled FROM users u JOIN staff_profiles p ON p.user_id=u.id WHERE u.id=?')return ($m['owner']['id']??null)===($params[0]??null)?$m['owner']:null;
    if($sql==='SELECT * FROM provider_refresh_results WHERE id=?')return ($m['result']['id']??null)===($params[0]??null)?$m['result']:null;
    if($sql==='SELECT * FROM provider_operation_grants WHERE id=?')return $m['grants'][$params[0]]??null;
    throw new LogicException('Unmodeled production lookup: '.$sql);
}
function all(string $sql,array $params=[]):array {
    ci_trace('list:'.$sql);ci_model_failure('list_error');
    if(!in_array($sql,['SELECT id FROM provider_cloud_links ORDER BY profile_id,server_id','SELECT id FROM provider_cloud_links WHERE client_id=? ORDER BY profile_id,server_id'],true))throw new LogicException('Unmodeled collection lookup.');
    $l=$GLOBALS['ci']['link'];return $l&&(!$params||(int)$l['client_id']===$params[0])?[['id'=>$l['id']]]:[];
}
function provider_cloud_assignment_ready():bool {provider_cloud_assignment_guard();ci_trace('assignment-ready');ci_model_failure('assignment_error');return $GLOBALS['ci']['assignment_ready'];}
function provider_cloud_assignment_witness(string $kind,string $source):array {ci_trace('witness:'.$kind.':'.$source);$v=$GLOBALS['ci']['witnesses'][$kind][$source]??null;if(!$v)provider_cloud_assignment_error();return $v;}
function provider_cloud_assignment_source(string $id):?array {ci_trace('retained-source:'.$id);return ($GLOBALS['ci']['source']['id']??null)===$id?$GLOBALS['ci']['source']:null;}
function provider_cloud_assignment_server(string $id,int $server):array {ci_trace('retained-server:'.$id);if($id!==$GLOBALS['ci']['anchor']['source_id']||$server!==$GLOBALS['ci']['anchor']['server_id'])provider_cloud_assignment_error();return ['sha256'=>$GLOBALS['ci']['server_sha256']];}
/** Strict current-read seam: any intake call fails; fresh() tests enable it explicitly. */
function provider_cloud_assignment_binding(string $id,?string $refresh=null):CloudAssignmentBinding {
    ci_trace('fresh-binding');++$GLOBALS['ci']['fresh_calls'];provider_cloud_assignment_guard();
    if(!$GLOBALS['ci']['allow_fresh'])throw new LogicException('Local intake reached fresh inventory.');
    if($GLOBALS['ci']['latest']!=='fresh')throw new RuntimeException('Modeled latest cloud observation is not fresh and complete.');
    if($id!==$GLOBALS['ci']['link']['id'])throw new LogicException('Fresh binding selected another link.');return new CloudAssignmentBinding($GLOBALS['ci']['fresh_binding']);
}
function provider_cloud_assignment_read(...$args):never{throw new LogicException('Local intake attempted current inventory read.');}
function provider_inventory_refresh(...$args):never{throw new LogicException('Local intake attempted provider refresh.');}
function provider_settings_unseal(...$args):never{throw new LogicException('Local intake attempted credential opening.');}
function provider_operations_perform(...$args):never{throw new LogicException('Local intake attempted provider send.');}
function provider_cloud_sites_create(...$args):never{throw new LogicException('Local intake attempted cloud creation.');}
function provider_settings_vault_key():string {return str_repeat('synthetic-local-hmac-key-',2);}
function support_current():mixed{return $GLOBALS['ci']['support'];}
function studio_client():?array {ci_trace('session-client');return $GLOBALS['ci']['session'];}
function provider_settings_manager():array {if(!$GLOBALS['ci']['manager'])throw new RuntimeException('Modeled owner permission refused.');return $GLOBALS['ci']['owner'];}
function provider_settings_owner(string $password):array {if($password!=='synthetic-owner-password')throw new RuntimeException('Modeled owner password refused.');return provider_settings_manager();}
function provider_cloud_site_requests_ready():bool {provider_cloud_assignment_guard();ci_trace('request-ready');ci_model_failure('request_error');return $GLOBALS['ci']['request_ready'];}
function provider_cloud_site_requests_state():array {provider_cloud_assignment_guard();if(!provider_cloud_site_requests_ready())throw new RuntimeException('Modeled request storage is unprepared.');ci_trace('complete-request-history');ci_model_failure('state_error');return provider_cloud_site_requests_project($GLOBALS['ci']['events']);}
function provider_cloud_site_requests_append(string $id,string $subject,int $client,string $kind,array $actor,array $payload):array {
    provider_cloud_assignment_guard();ci_trace('append');$events=$GLOBALS['ci']['events'];
    $e=['version'=>1,'id'=>$id,'subject_id'=>$subject,'client_id'=>$client,'kind'=>$kind,'actor'=>$actor,'recorded_epoch'=>time(),'ordinal'=>count($events)+1,'previous'=>$events?hash('sha256',provider_cloud_site_requests_json(end($events))):str_repeat('0',64),'payload'=>$payload];
    provider_cloud_site_requests_event_document($e);provider_cloud_site_requests_project([...$events,$e]);$GLOBALS['ci']['events'][]=$e;return $e;
}
function provider_cloud_sites_result(?array $grant):?array{return $grant['result']??null;}

function ci_reset():void {
    $_GET=[];$_SESSION=[];$profile=str_repeat('1',32);$link=str_repeat('2',32);$source=str_repeat('3',32);$credential=hash('sha256','synthetic-sealed-credential');$key=str_repeat('4',64);
    $a=['profile_id'=>$profile,'profile_revision'=>2,'credential_sha256'=>$credential,'key_fingerprint'=>$key,'source_id'=>$source,'source_seq'=>7,'start_proof'=>str_repeat('5',64),'finish_proof'=>str_repeat('6',64),'server_id'=>1401,'server_sha256'=>str_repeat('7',64)];
    $l=['id'=>$link,'profile_id'=>$profile,'server_id'=>'1401','client_id'=>'11','revision'=>'3','linked_by'=>'21','linked_at'=>'2000-01-01 00:00:00','key_fingerprint'=>$key,'profile_revision'=>'2','refresh_id'=>$source];
    $e=['id'=>'9','link_id'=>$link,'profile_id'=>$profile,'server_id'=>'1401','client_id'=>'11','revision'=>'3','event'=>'link','actor_id'=>'21','recorded_at'=>'2000-01-01 00:00:00','key_fingerprint'=>$key,'profile_revision'=>'2','refresh_id'=>$source];
    $p=['id'=>$profile,'revision'=>'2','provider_code'=>'twenty-i','environment'=>'live','enabled'=>'1','credentials'=>'synthetic-sealed-credential'];
    $d=['legacy'=>false,'actor_id'=>21,'source'=>provider_cloud_assignment_event_fields($e),'extra'=>['snapshot'=>provider_cloud_assignment_link_fields($l),'owner_auth'=>hash('sha256','owner-auth'),'credentials'=>provider_cloud_assignment_profile_context($p),'anchor'=>$a]];
    $GLOBALS['ci']=['locked'=>true,'tx'=>true,'assignment_ready'=>true,'request_ready'=>true,'assignment_error'=>null,'request_error'=>null,'state_error'=>null,'list_error'=>null,'link'=>$l,'event'=>$e,'profile'=>$p,'owner'=>['id'=>21,'auth_version'=>'owner-auth','role'=>'owner','enabled'=>'1'],'clients'=>[11=>['id'=>11,'active'=>1,'auth_version'=>'client-auth'],12=>['id'=>12,'active'=>1,'auth_version'=>'other-client-auth']],'session'=>['id'=>11,'auth_version'=>'client-auth'],'support'=>false,'manager'=>true,'anchor'=>$a,'source'=>['id'=>$source,'profile_id'=>$profile,'profile_revision'=>'2','seq'=>'7','started_epoch'=>1],'result'=>['id'=>$source,'state'=>'complete','skipped'=>'0','reason'=>'','skip_reasons'=>'[]','coverage'=>'unknown','scope'=>'twenty-i|'.$profile.'|'.InventoryRead::CLOUD_SERVERS_LIST,'key_fingerprint'=>$key],'witnesses'=>['event'=>['9'=>['row'=>['proof'=>str_repeat('8',64)],'document'=>$d]],'start'=>[$source=>['row'=>['proof'=>$a['start_proof']],'document'=>['legacy'=>false,'extra'=>['credentials'=>['credential_sha256'=>$credential]]]]],'finish'=>[$source=>['row'=>['proof'=>$a['finish_proof']],'document'=>['legacy'=>false]]]],'server_sha256'=>$a['server_sha256'],'events'=>[],'grants'=>[],'latest'=>'expired','allow_fresh'=>false,'fresh_calls'=>0,'fresh_binding'=>null,'trace'=>[]];
}
function ci_expected():array {
    $m=$GLOBALS['ci'];$l=$m['link'];$a=$m['anchor'];return ['profile_id'=>$l['profile_id'],'profile_revision'=>(int)$l['profile_revision'],'credential_sha256'=>$a['credential_sha256'],'key_fingerprint'=>$l['key_fingerprint'],'server_id'=>(int)$l['server_id'],'client_id'=>(int)$l['client_id'],'link_id'=>$l['id'],'link_revision'=>(int)$l['revision'],'link_sha256'=>hash('sha256',provider_cloud_assignment_json(provider_cloud_assignment_link_fields($l))),'event_id'=>(int)$m['event']['id'],'event_proof'=>$m['witnesses']['event']['9']['row']['proof'],'source_id'=>$a['source_id'],'source_seq'=>$a['source_seq'],'start_proof'=>$a['start_proof'],'finish_proof'=>$a['finish_proof'],'server_sha256'=>$a['server_sha256']];
}
function ci_original_request(array $binding):array {
    return ['version'=>1,'id'=>str_repeat('a',32),'subject_id'=>str_repeat('a',32),'client_id'=>11,'kind'=>'request','actor'=>['kind'=>'client','id'=>11,'auth_sha256'=>hash('sha256','client-auth')],'recorded_epoch'=>time(),'ordinal'=>1,'previous'=>str_repeat('0',64),'payload'=>['binding'=>$binding,'domain'=>'website.example.invalid','aliases'=>['alias.example.invalid'],'confirmed'=>true]];
}
function ci_submit(string $id='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',?string $context=null):array {
    $b=ci_expected();$actor=['kind'=>'client','id'=>11,'auth_sha256'=>hash('sha256','client-auth')];
    return provider_cloud_site_requests_create($id,11,$b['link_id'],$context??provider_cloud_site_requests_context($b,$actor),'website.example.invalid',['alias.example.invalid'],true);
}

try {
    if(!function_exists('token_get_all')||!class_exists('PDOException'))throw new LogicException('This portable test requires tokenizer and PDO, without any database driver.');
    ci_extract('cloud_assignment_storage.php',['provider_cloud_assignment_guard','provider_cloud_assignment_json','provider_cloud_assignment_error','provider_cloud_assignment_fields','provider_cloud_assignment_event_fields','provider_cloud_assignment_link_fields']);
    ci_extract('cloud_assignment.php',['provider_cloud_assignment_profile_context','provider_cloud_assignment_owner_auth','provider_cloud_assignment_assignment','provider_cloud_assignment_anchor','provider_cloud_assignment_inspect']);
    ci_extract('settings.php',['provider_settings_load']);
    ci_extract('operations.php',['provider_operations_canonical','provider_operations_scope']);
    ci_extract('cloud_site_requests_storage.php',['provider_cloud_site_requests_json']);
    ci_extract('cloud_site_requests.php',['provider_cloud_site_requests_actor','provider_cloud_site_requests_original_client','provider_cloud_site_requests_intake_binding','provider_cloud_site_requests_static_current','provider_cloud_site_requests_context','provider_cloud_site_requests_size','provider_cloud_site_requests_fresh','provider_cloud_site_requests_options','provider_cloud_site_requests_create']);
    ci_reset();$expected=ci_expected();$actual=provider_cloud_site_requests_intake_binding($expected['link_id']);
    ci_check('Intake returns exact ordered original-anchor 16-field binding',$actual===$expected&&count($actual)===16);
    ci_check('Historical anchor age is not intake expiry',$ci['source']['started_epoch']===1&&$actual['source_id']===$ci['anchor']['source_id']);
    ci_check('Intake authenticates assignment history before lookup',array_search('assignment-ready',$ci['trace'],true)<array_search('query:SELECT * FROM provider_cloud_links WHERE id=?',$ci['trace'],true));
    ci_check('Intake opens no fresh inventory or mutation path',$ci['fresh_calls']===0&&$ci['events']===[]);
    ci_reset();$ci['locked']=false;ci_logic('Intake refuses missing owning mutex',fn()=>provider_cloud_site_requests_intake_binding(str_repeat('2',32)));
    ci_reset();$ci['tx']=false;ci_logic('Intake refuses missing transaction',fn()=>provider_cloud_site_requests_intake_binding(str_repeat('2',32)));
    foreach(['expired','failed','unfinished','omitted-server','partial','credential','refused','access_changed'] as $latest){
        ci_reset();$ci['latest']=$latest;$ci['locked']=false;$ci['tx']=false;$options=provider_cloud_site_requests_options(11);
        ci_check('Local option survives healthy latest '.$latest,count($options)===1&&$options[0]['binding']===ci_expected());
        $r=ci_submit(str_repeat('a',32),$options[0]['context']);
        ci_check('Local intake saves one pending event with latest '.$latest,$r['stage']==='pending'&&count($ci['events'])===1&&$r['request']['payload']['binding']===ci_expected()&&$ci['fresh_calls']===0);
        ci_check('Inspection restores modeled scope after '.$latest,!$ci['locked']&&!$ci['tx']);
    }
    $mutations=['missing assignment'=>['link',null],'legacy assignment'=>['witnesses.event.9.document.legacy',true],'inactive client'=>['clients.11.active',0],'missing client'=>['clients.11',null],'disabled profile'=>['profile.enabled','0'],'empty credential'=>['profile.credentials',''],'wrong provider'=>['profile.provider_code','other'],'wrong environment'=>['profile.environment','sandbox'],'changed profile revision'=>['profile.revision','3'],'changed ciphertext'=>['profile.credentials','replacement-sealed-credential'],'changed linked revision'=>['link.profile_revision','3'],'changed linked key'=>['link.key_fingerprint',str_repeat('b',64)],'changed client assignment'=>['link.client_id','12'],'changed server assignment'=>['link.server_id','1402'],'changed link revision'=>['link.revision','4'],'changed original owner auth'=>['owner.auth_version','rotated-owner'],'disabled original owner'=>['owner.enabled','0'],'demoted original owner'=>['owner.role','staff'],'missing original owner'=>['owner',null],'missing original event'=>['event',null],'latest event unlink'=>['event.event','unlink'],'snapshot changed'=>['witnesses.event.9.document.extra.snapshot.revision','5'],'missing original anchor'=>['witnesses.event.9.document.extra.anchor',null],'missing original source'=>['source',null],'missing original result'=>['result',null],'original result incomplete'=>['result.state','partial'],'original result skipped'=>['result.skipped','1'],'original server row changed'=>['server_sha256',str_repeat('b',64)],'original start proof changed'=>['witnesses.start.'.str_repeat('3',32).'.row.proof',str_repeat('b',64)],'original finish proof changed'=>['witnesses.finish.'.str_repeat('3',32).'.row.proof',str_repeat('b',64)]];
    foreach($mutations as $label=>[$path,$value]){ci_reset();ci_changed($path,$value);ci_deny('Intake refuses '.$label,fn()=>provider_cloud_site_requests_intake_binding(str_repeat('2',32)));ci_check('Refusal has no event or fresh read: '.$label,$ci['events']===[]&&$ci['fresh_calls']===0);}
    // Unlike the raw-link negatives, these preserve the modeled current event/snapshot.
    // The intake helper must still reject disagreement with the retained original read anchor.
    foreach(['server_id'=>'1402','key_fingerprint'=>str_repeat('b',64),'refresh_id'=>str_repeat('c',32)] as $field=>$value){
        ci_reset();$ci['link'][$field]=$value;$ci['event'][$field]=$value;
        $ci['witnesses']['event']['9']['document']['source']=provider_cloud_assignment_event_fields($ci['event']);
        $ci['witnesses']['event']['9']['document']['extra']['snapshot']=provider_cloud_assignment_link_fields($ci['link']);
        ci_deny('Coherent current link cannot substitute original anchor '.$field,fn()=>provider_cloud_site_requests_intake_binding(str_repeat('2',32)),'This cloud server assignment changed.');
    }
    ci_reset();$ci['profile']['credentials']='replacement-sealed-credential';$ci['witnesses']['event']['9']['document']['extra']['credentials']=provider_cloud_assignment_profile_context($ci['profile']);
    ci_deny('Even matching current signed context cannot replace anchor ciphertext',fn()=>provider_cloud_site_requests_intake_binding(str_repeat('2',32)),'This cloud server assignment changed.');
    ci_reset();$ci['witnesses']['event']['9']['document']['extra']['credentials']['environment']='sandbox';
    ci_deny('Intake binds exact signed original credential context',fn()=>provider_cloud_site_requests_intake_binding(str_repeat('2',32)));
    foreach(['assignment_error','request_error','state_error'] as $path){
        ci_reset();$failure=new RuntimeException('Synthetic original history corruption: '.$path);$ci[$path]=$failure;
        ci_deny('Options propagate '.$path,fn()=>provider_cloud_site_requests_options(11),$failure->getMessage());
        ci_check('Corrupt '.$path.' never reaches link eligibility list',!array_filter($ci['trace'],fn($v)=>str_starts_with($v,'list:')));
        ci_deny('New intake propagates '.$path,fn()=>ci_submit(),$failure->getMessage());
    }
    ci_reset();$ci['assignment_error']=new PDOException('Synthetic database failure');ci_deny('Options propagate PDO history error',fn()=>provider_cloud_site_requests_options(11),'Synthetic database failure',PDOException::class);
    ci_reset();$ci['list_error']=new PDOException('Synthetic listing failure');ci_deny('Options propagate PDO listing error',fn()=>provider_cloud_site_requests_options(11),'Synthetic listing failure',PDOException::class);
    ci_reset();$ci['assignment_ready']=false;ci_check('Clean unprepared assignment exposes no local options',provider_cloud_site_requests_options(11)===[]);ci_deny('Clean unprepared assignment cannot create request',fn()=>ci_submit());
    ci_reset();$ci['request_ready']=false;ci_check('Clean unprepared request storage exposes no local options',provider_cloud_site_requests_options(11)===[]);
    ci_deny('Clean unprepared request storage cannot create request',fn()=>ci_submit());
    ci_reset();$ownerOptions=provider_cloud_site_requests_options();ci_check('Current owner may inspect local options without client session authority',count($ownerOptions)===1&&$ownerOptions[0]['binding']===ci_expected());
    $ci['manager']=false;ci_deny('Owner options still require current permission',fn()=>provider_cloud_site_requests_options());
    foreach(['support','support query','contact ID','contact auth','wrong session client','stale session auth','inactive session client'] as $case){
        ci_reset();switch($case){case 'support':$ci['support']=true;break;case 'support query':$_GET['support']='';break;case 'contact ID':$_SESSION['contact_id']=7;break;case 'contact auth':$_SESSION['contact_auth']='';break;case 'wrong session client':$ci['session']['id']=12;break;case 'stale session auth':$ci['session']['auth_version']='old';break;case 'inactive session client':$ci['clients'][11]['active']=0;break;}
        ci_deny('Options refuse '.$case,fn()=>provider_cloud_site_requests_options(11));ci_deny('Request refuses '.$case,fn()=>ci_submit());ci_check('Denied '.$case.' has no append',$ci['events']===[]);
    }
    ci_reset();$ci['link']['client_id']='12';$ci['event']['client_id']='12';$ci['witnesses']['event']['9']['document']['source']=provider_cloud_assignment_event_fields($ci['event']);$ci['witnesses']['event']['9']['document']['extra']['snapshot']=provider_cloud_assignment_link_fields($ci['link']);
    ci_check('Other client assignment is not offered',provider_cloud_site_requests_options(11)===[]);ci_deny('Direct ID cannot submit another client assignment',fn()=>ci_submit());
    ci_reset();$old=ci_expected();foreach(['source_id','source_seq','start_proof','finish_proof','server_sha256'] as $key)$old[$key]=is_int($old[$key])?99:str_repeat('c',strlen($old[$key]));
    $event=ci_original_request($old);$ci['events']=[$event];$bytes=provider_cloud_site_requests_json($ci['events']);$context=provider_cloud_site_requests_context($old,$event['actor']);$r=ci_submit($event['id'],$context);
    ci_check('Prior full-binding replay remains exact despite different five observation fields',$r['request']===$event&&provider_cloud_site_requests_json($ci['events'])===$bytes&&$ci['fresh_calls']===0);
    ci_deny('Prior replay refuses rebased anchor context',fn()=>ci_submit());
    foreach(['domain','aliases','link','context'] as $field){
        ci_reset();$ci['events']=[$event];$before=provider_cloud_site_requests_json($ci['events']);
        ci_deny('Prior replay refuses changed '.$field,fn()=>provider_cloud_site_requests_create($event['id'],11,$field==='link'?str_repeat('f',32):$old['link_id'],$field==='context'?str_repeat('0',64):$context,$field==='domain'?'other.example.invalid':'website.example.invalid',$field==='aliases'?['other-alias.example.invalid']:['alias.example.invalid'],true));
        ci_check('Rejected replay retains original bytes for '.$field,provider_cloud_site_requests_json($ci['events'])===$before);
    }
    ci_reset();$ci['events']=[$event];$ci['clients'][11]['auth_version']='rotated';$ci['session']['auth_version']='rotated';ci_deny('Prior replay still checks original client auth version',fn()=>ci_submit($event['id'],$context));
    foreach(array_keys(array_slice($old,0,11,true)) as $field){
        ci_reset();$bad=$event;$v=$bad['payload']['binding'][$field];$bad['payload']['binding'][$field]=is_int($v)?$v+1:str_repeat('d',strlen($v));$ci['events']=[$bad];
        ci_deny('Prior replay refuses changed retained static field '.$field,fn()=>provider_cloud_site_requests_static_current(provider_cloud_site_requests_state()['requests'][$bad['id']]));
    }
    ci_reset();$b=ci_expected();$actor=['kind'=>'client','id'=>11,'auth_sha256'=>hash('sha256','client-auth')];$context=provider_cloud_site_requests_context($b,$actor);
    ci_deny('New request still requires explicit confirmation',fn()=>provider_cloud_site_requests_create(str_repeat('a',32),11,$b['link_id'],$context,'website.example.invalid',[],false));
    ci_deny('New request still requires exact context',fn()=>ci_submit(str_repeat('a',32),str_repeat('0',64)));
    ci_deny('Invalid local request identity remains refused',fn()=>ci_submit('not-an-id'));
    ci_deny('Noncanonical primary hostname remains refused',fn()=>provider_cloud_site_requests_create(str_repeat('a',32),11,$b['link_id'],$context,'UPPER.example.invalid',[],true));
    $r=ci_submit();ci_deny('Overlapping pending request remains fenced',fn()=>ci_submit(str_repeat('b',32)));
    ci_check('Duplicate overlap refusal does not append',count($ci['events'])===1);
    ci_reset();$aliases=[];for($i=0;$i<20;++$i)$aliases[]='a'.$i.'.'.str_repeat('b',60).'.'.str_repeat('c',60).'.'.str_repeat('d',60).'.example.invalid';
    ci_deny('Exact future grant size remains bounded',fn()=>provider_cloud_site_requests_size(ci_expected(),'website.example.invalid',$aliases));
    ci_reset();for($i=0;$i<10;++$i){$e=ci_original_request(ci_expected());$e['id']=$e['subject_id']=str_pad(dechex($i+1),32,'0',STR_PAD_LEFT);$e['ordinal']=$i+1;$e['payload']['domain']='prior'.$i.'.example.invalid';$e['payload']['aliases']=[];$ci['events'][]=$e;}
    ci_deny('Ten local requests still enforce daily quota',fn()=>ci_submit());ci_check('Quota refusal leaves ten original events intact',count($ci['events'])===10);
    foreach(['expired','failed','unfinished','omitted-server'] as $latest){
        ci_reset();$r=ci_submit();$ci['allow_fresh']=true;$ci['latest']=$latest;
        ci_deny('Owner fresh boundary still refuses latest '.$latest,fn()=>provider_cloud_assignment_inspect(fn()=>provider_cloud_site_requests_fresh($r)),'Modeled latest cloud observation is not fresh and complete.');
        ci_check('Owner boundary actually consulted strict read for '.$latest,$ci['fresh_calls']===1);
    }
    ci_reset();$r=ci_submit();$fresh=ci_expected();$fresh['source_id']=str_repeat('e',32);$fresh['source_seq']=88;$fresh['start_proof']=str_repeat('b',64);$fresh['finish_proof']=str_repeat('c',64);$fresh['server_sha256']=str_repeat('d',64);$ci['allow_fresh']=true;$ci['latest']='fresh';$ci['fresh_binding']=$fresh;
    ci_check('Owner fresh boundary may return newer same-assignment observation',provider_cloud_site_requests_fresh($r)===$fresh);
    $ci['fresh_binding']['client_id']=12;ci_deny('Owner fresh boundary refuses changed assignment',fn()=>provider_cloud_site_requests_fresh($r));
    $priorCount=count($checks);$engineEscaped=false;try{ci_deny('Engine failure must not produce a passing refusal',static function():never{throw new TypeError('Synthetic engine error');});}catch(TypeError){$engineEscaped=true;}
    ci_check('Unexpected engine errors escape expected-refusal helper',$engineEscaped&&count($checks)===$priorCount);
    echo json_encode(['passed'=>true,'count'=>count($checks),'scope'=>'Portable actual function bodies with modeled persistence/session/readiness; no SQL, provider transport, browser or elapsed-time acceptance.','checks'=>$checks,'extracted_sha256'=>$extracted],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){
    echo json_encode(['passed'=>false,'count'=>count($checks),'checks'=>$checks,'error'=>['class'=>get_class($e),'message'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine()]],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit(1);
}
