<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
/** Actual storage functions; SQL/session/vault boundaries are finite models, not native acceptance. */
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes_storage.php';
require CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
set_error_handler(static function(int $level,string $message,string $file,int $line):never{throw new ErrorException($message,0,$level,$file,$line);});
$checks=[];
function check(string $name,bool $pass):void {global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function refused(string $name,callable $call):void {$thrown=false;try{$call();}catch(LogicException|RuntimeException|JsonException){$thrown=true;}check($name,$thrown);}
function reset_state():void {$GLOBALS['s']=['tables'=>[],'settings'=>[],'audits'=>[],'rows'=>[],'mutex'=>true,'tx'=>false,'ddl'=>[],'calls'=>[],'owner'=>true,'read_ready'=>true,'operations_ready'=>true,'history'=>0,'history_bad'=>false,'grant_count'=>0,'ledger_residue'=>false,'grant_residue'=>false,'audit_fail'=>false,'sql_fault'=>null];}
function config():array {return ['db'=>['name'=>'modeled_only','prefix'=>'rnsm_']];}
function table(string $name):string {return '`rnsm_'.$name.'`';}
function provider_settings_vault_key():string {return str_repeat('k',32);}
function registered_nameservers_guard(bool $transaction=true):void {if(!$GLOBALS['s']['mutex']||($transaction&&!$GLOBALS['s']['tx']))throw new LogicException('Modeled mutex/transaction guard.');}
function provider_settings_owner(string $password):array {$GLOBALS['owner_cache_seen']=array_key_exists('cwp_settings',$GLOBALS);if(!$GLOBALS['s']['mutex']||!$GLOBALS['s']['owner']||$password!=='synthetic-password')throw new RuntimeException('Modeled fresh owner refused.');return ['id'=>7];}
function registered_nameservers_ready():bool {return $GLOBALS['s']['read_ready'];}
function provider_operations_ready():bool {return $GLOBALS['s']['operations_ready'];}
function registered_nameserver_changes_authenticated_history():void {$GLOBALS['s']['history']++;if($GLOBALS['s']['history_bad'])throw new RuntimeException('Modeled authenticated historical parser refused.');}
function staff_locked(callable $f):mixed {$old=$GLOBALS['s']['mutex'];$GLOBALS['s']['mutex']=true;try{return $f();}finally{$GLOBALS['s']['mutex']=$old;}}
function staff_transaction(callable $f):mixed {if($GLOBALS['s']['tx'])throw new LogicException('Nested model transaction.');$before=$GLOBALS['s'];$GLOBALS['s']['tx']=true;try{$out=$f();$GLOBALS['s']['tx']=false;return $out;}catch(Throwable $e){$GLOBALS['s']=$before;throw $e;}}
function save_setting(string $name,string $value):void {registered_nameservers_guard();$GLOBALS['s']['settings'][$name]=$value;}
function provider_settings_audit(int $actor,string $action,string $detail):void {registered_nameservers_guard();if($GLOBALS['s']['audit_fail'])throw new RuntimeException('Modeled audit fault.');$GLOBALS['s']['audits'][]=['id'=>count($GLOBALS['s']['audits'])+1,'user_id'=>$actor,'action'=>'provider.'.$action,'detail'=>$detail];}
final class ModelResult {public function __construct(private mixed $value){}public function fetchColumn():mixed{return $this->value;}}
final class ModelDb {
    public function inTransaction():bool{return $GLOBALS['s']['tx'];}
    public function exec(string $sql):int {if($this->inTransaction())throw new LogicException('DDL inside transaction.');if(!preg_match('/^CREATE TABLE `rnsm_([a-z_]+)` /',$sql,$m))throw new LogicException('Unexpected model DDL.');$GLOBALS['s']['ddl'][]=$m[1];$GLOBALS['s']['tables'][$m[1]]=metadata($m[1]);return 0;}
}
function db():ModelDb {static $db;return $db??=new ModelDb;}
function metadata(string $name):array {
    $id=['char(32)','ascii_bin'];$hash=['char(64)','ascii_bin'];$n=['bigint unsigned',null];$actor=['int unsigned',null];$doc=['longtext','ascii_bin'];$disp=['varchar(12)','ascii_bin'];
    $fields=match($name){
        'provider_nameserver_change_requests'=>['id'=>$id,'grant_seq'=>$n,'profile_id'=>$id,'domain_id'=>$n,'link_id'=>$id,'actor_id'=>$actor,'document_json'=>$doc,'document_sha256'=>$hash,'proof'=>$hash],
        'provider_nameserver_change_terminals'=>['id'=>$id,'actor_id'=>$actor,'disposition'=>$disp,'ledger_sha256'=>$hash,'document_json'=>$doc,'document_sha256'=>$hash,'proof'=>$hash],
        'provider_nameserver_change_checks'=>['id'=>$id,'request_id'=>$id,'actor_id'=>$actor,'receipt_id'=>$id,'disposition'=>$disp,'document_json'=>$doc,'document_sha256'=>$hash,'proof'=>$hash],
        default=>throw new LogicException('Unexpected model metadata table.'),
    };
    $columns=[];foreach($fields as $key=>[$type,$collation])$columns[]=['Field'=>$key,'Type'=>$type,'Null'=>'NO','Default'=>null,'Extra'=>'','Collation'=>$collation];
    $indexes=['PRIMARY'=>[0,['id']]];$foreigns=[];
    if($name==='provider_nameserver_change_requests'){$indexes+=['nameserver_change_seq'=>[0,['grant_seq']],'nameserver_change_resource'=>[1,['profile_id','domain_id']]];$foreigns=['id'=>'provider_operation_grants'];}
    elseif($name==='provider_nameserver_change_terminals')$foreigns=['id'=>'provider_nameserver_change_requests'];
    else {$indexes+=['nameserver_check_receipt'=>[0,['receipt_id']],'nameserver_check_request'=>[1,['request_id']]];$foreigns=['request_id'=>'provider_nameserver_change_requests','receipt_id'=>'provider_refreshes'];}
    $rows=[];foreach($indexes as $key=>[$unique,$cols])foreach($cols as $i=>$col)$rows[]=['Key_name'=>$key,'Non_unique'=>$unique,'Seq_in_index'=>$i+1,'Column_name'=>$col,'Sub_part'=>null,'Index_type'=>'BTREE'];
    $rels=[];foreach($foreigns as $col=>$to)$rels[]=['COLUMN_NAME'=>$col,'REFERENCED_TABLE_NAME'=>'rnsm_'.$to,'REFERENCED_COLUMN_NAME'=>'id','DELETE_RULE'=>'RESTRICT','UPDATE_RULE'=>'RESTRICT'];
    return ['columns'=>$columns,'indexes'=>$rows,'foreigns'=>$rels,'engine'=>'InnoDB'];
}
function one(string $sql,array $args=[]):?array {
    $GLOBALS['s']['calls'][]=$sql;$s=$GLOBALS['s'];
    if(str_contains($sql,'information_schema.tables')){$name=substr($args[0],5);$exists=in_array($name,['settings','audit','provider_operation_grants','provider_operations'],true)||isset($s['tables'][$name]);return $exists?(str_starts_with($sql,'SELECT ENGINE')?['ENGINE'=>$s['tables'][$name]['engine']??'InnoDB']:['TABLE_NAME'=>$args[0]]):null;}
    if(str_starts_with($sql,'SELECT value FROM `rnsm_settings`'))return array_key_exists($args[0],$s['settings'])?['value'=>$s['settings'][$args[0]]]:null;
    if(str_contains($sql,'FROM `rnsm_audit` WHERE BINARY LEFT(action,?)')){foreach($s['audits'] as $row)if(str_starts_with($row['action'],$args[1])&&(!str_contains($sql,'NOT IN')||!in_array($row['action'],array_slice($args,2),true)||$row['user_id']<1))return $row;return null;}
    if($sql==="SELECT id FROM `rnsm_audit` WHERE BINARY action='provider.registered_nameserver_changes_prepared'")return $s['audits'][0]??null;
    if(str_starts_with($sql,'SELECT id FROM `rnsm_provider_operation_grants`'))return $s['grant_residue']?['id'=>'orphan']:null;
    if(str_starts_with($sql,'SELECT id FROM `rnsm_provider_operations`'))return $s['ledger_residue']?['id'=>'orphan']:null;
    if(str_starts_with($sql,'SELECT w.id FROM ')||str_starts_with($sql,'SELECT q.id FROM ')||str_starts_with($sql,'SELECT p.id FROM ')||str_starts_with($sql,'SELECT t.id FROM ')||str_starts_with($sql,'SELECT c.id FROM '))return $s['sql_fault']!==null&&str_contains($sql,$s['sql_fault'])?['id'=>'bad']:null;
    throw new LogicException('Unmodeled one query: '.$sql);
}
function all(string $sql,array $args=[]):array {
    $GLOBALS['s']['calls'][]=$sql;$s=$GLOBALS['s'];
    if(str_contains($sql,'information_schema.tables')&&str_contains($sql,'LEFT(TABLE_NAME'))return array_map(static fn($x)=>['TABLE_NAME'=>'rnsm_'.$x],array_values(array_filter(array_keys($s['tables']),static fn($x)=>str_starts_with('rnsm_'.$x,$args[1]))));
    if(str_starts_with($sql,'SELECT name FROM `rnsm_settings`'))return array_map(static fn($x)=>['name'=>$x],array_values(array_filter(array_keys($s['settings']),static fn($x)=>str_starts_with($x,$args[1]))));
    if(preg_match('/^SHOW (FULL COLUMNS|INDEX) FROM `rnsm_([a-z_]+)`$/',$sql,$m))return $s['tables'][$m[2]][$m[1]==='INDEX'?'indexes':'columns'];
    if(str_contains($sql,'information_schema.KEY_COLUMN_USAGE'))return $s['tables'][substr($args[0],5)]['foreigns'];
    throw new LogicException('Unmodeled all query: '.$sql);
}
function query(string $sql,array $args=[]):ModelResult {
    $GLOBALS['s']['calls'][]=$sql;$s=$GLOBALS['s'];
    if(preg_match('/^SELECT COUNT\(\*\) FROM `rnsm_([a-z_]+)`(.*)$/',$sql,$m)){
        if($m[1]==='provider_operation_grants')return new ModelResult($s['grant_count']);
        if($m[1]==='audit'){preg_match("/BINARY action='([^']+)'/",$m[2],$a);$rows=array_filter($s['audits'],static fn($r)=>$r['action']===$a[1]);if(str_contains($m[2],'detail='))$rows=array_filter($rows,static fn($r)=>$r['detail']==='Registered nameserver changes ready'&&$r['user_id']>0);return new ModelResult(count($rows));}
        return new ModelResult(count($s['rows'][$m[1]]??[]));
    }
    if(preg_match('/^INSERT INTO `rnsm_([a-z_]+)` \(([^)]+)\) VALUES /',$sql,$m)){$GLOBALS['s']['rows'][$m[1]][]=array_combine(explode(',',$m[2]),$args);return new ModelResult(1);}
    throw new LogicException('Unmodeled query: '.$sql);
}
function present(int $count):void {foreach(array_slice(array_values(registered_nameserver_changes_tables()),0,$count) as $name)$GLOBALS['s']['tables'][$name]=metadata($name);}
try{
    reset_state();check('Only three distinct namespaced tables',array_values(registered_nameserver_changes_tables())===array_keys(registered_nameserver_changes_schema('rnsm_')));
    refused('Invalid schema prefix rejected',fn()=>registered_nameserver_changes_schema('bad-'));
    check('Request resource index uses profile and numeric registrar identity',str_contains(registered_nameserver_changes_schema('rnsm_')['provider_nameserver_change_requests'],'domain_id BIGINT UNSIGNED')&&str_contains(registered_nameserver_changes_schema('rnsm_')['provider_nameserver_change_requests'],'nameserver_change_resource(profile_id,domain_id)'));
    check('Framed digest separates ambiguous concatenations',registered_nameserver_changes_digest(['a','bc'])!==registered_nameserver_changes_digest(['ab','c']));
    check('HMAC domains separate row kinds',registered_nameserver_changes_hmac('request',['x'])!==registered_nameserver_changes_hmac('terminal',['x']));
    refused('HMAC refuses an unknown evidence kind',fn()=>registered_nameserver_changes_hmac('unknown',[]));
    check('Absent ledger has explicit zero identity',registered_nameserver_changes_ledger_digest(null)===str_repeat('0',64));
    $ledger=array_fill_keys(['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch'],'a');$digest=registered_nameserver_changes_ledger_digest($ledger);
    foreach(array_keys($ledger) as $key){$changed=$ledger;$changed[$key]='b';check('Ledger witness binds '.$key,registered_nameserver_changes_ledger_digest($changed)!==$digest);}
    for($i=0;$i<=3;$i++){reset_state();present($i);check('Unused ordered empty prefix '.$i.' returns unprepared',registered_nameserver_changes_ready()===false);registered_nameserver_changes_prepare('synthetic-password');check('Preparation resumes exact prefix '.$i,count($GLOBALS['s']['ddl'])===3-$i&&registered_nameserver_changes_ready());check('Preparation only one owner audit '.$i,count($GLOBALS['s']['audits'])===1&&$GLOBALS['s']['audits'][0]['user_id']===7);}
    reset_state();$GLOBALS['s']['tables']['provider_nameserver_change_terminals']=metadata('provider_nameserver_change_terminals');refused('Out of order empty table refuses before DDL',fn()=>registered_nameserver_changes_prepare('synthetic-password'));check('Out of order refusal creates nothing',$GLOBALS['s']['ddl']===[]);
    reset_state();$GLOBALS['s']['tables']['provider_nameserver_change_extra']=[];refused('Unknown namespace table refuses even empty',fn()=>registered_nameserver_changes_prepare('synthetic-password'));check('Unknown table refusal creates nothing',$GLOBALS['s']['ddl']===[]);
    reset_state();present(1);$GLOBALS['s']['tables']['provider_nameserver_change_requests']['foreigns'][]=$GLOBALS['s']['tables']['provider_nameserver_change_requests']['foreigns'][0];refused('Duplicate foreign relationships refuse empty schema',fn()=>registered_nameserver_changes_prepare('synthetic-password'));check('Duplicate relationship refusal creates nothing',$GLOBALS['s']['ddl']===[]);
    foreach(['column','index','foreign','engine'] as $bad){reset_state();present(1);$name='provider_nameserver_change_requests';if($bad==='column')$GLOBALS['s']['tables'][$name]['columns'][3]['Type']='varchar(32)';if($bad==='index')$GLOBALS['s']['tables'][$name]['indexes'][2]['Column_name']='link_id';if($bad==='foreign')$GLOBALS['s']['tables'][$name]['foreigns'][0]['DELETE_RULE']='CASCADE';if($bad==='engine')$GLOBALS['s']['tables'][$name]['engine']='MyISAM';refused('Empty incompatible '.$bad.' refuses preparation',fn()=>registered_nameserver_changes_prepare('synthetic-password'));check('No DDL after incompatible '.$bad,$GLOBALS['s']['ddl']===[]);}
    foreach(['counter','audit','grant','ledger','rows','empty_marker','unknown_setting','unknown_audit'] as $bad){reset_state();if($bad==='counter')$GLOBALS['s']['settings']['registered_nameserver_changes_request_count']='0';if($bad==='audit')$GLOBALS['s']['audits'][]=['id'=>1,'user_id'=>7,'action'=>'provider.registered_nameserver_changes_request','detail'=>'retained'];if($bad==='grant')$GLOBALS['s']['grant_residue']=true;if($bad==='ledger')$GLOBALS['s']['ledger_residue']=true;if($bad==='rows'){present(1);$GLOBALS['s']['rows']['provider_nameserver_change_requests']=[['id'=>'retained']];}if($bad==='empty_marker')$GLOBALS['s']['settings']['registered_nameserver_changes_schema_version']='';if($bad==='unknown_setting')$GLOBALS['s']['settings']['registered_nameserver_changes_future']='1';if($bad==='unknown_audit')$GLOBALS['s']['audits'][]=['id'=>1,'user_id'=>7,'action'=>'provider.registered_nameserver_changes_future','detail'=>''];refused('Absent marker residue refuses '.$bad,fn()=>registered_nameserver_changes_ready());}
    reset_state();registered_nameserver_changes_prepare('synthetic-password');$prepared=$GLOBALS['s'];registered_nameserver_changes_prepare('synthetic-password');check('Already prepared is unchanged',array_diff_key($GLOBALS['s'],['calls'=>0,'history'=>0])===array_diff_key($prepared,['calls'=>0,'history'=>0]));
    $GLOBALS['s']['history_bad']=true;refused('Active state propagates authenticated history refusal',fn()=>registered_nameserver_changes_ready());
    foreach(['schema','counter','prepared_audit','missing_table','grant_count'] as $bad){$GLOBALS['s']=$prepared;if($bad==='schema')$GLOBALS['s']['settings']['registered_nameserver_changes_schema_version']='2';if($bad==='counter')unset($GLOBALS['s']['settings']['registered_nameserver_changes_terminal_count']);if($bad==='prepared_audit')$GLOBALS['s']['audits']=[];if($bad==='missing_table')unset($GLOBALS['s']['tables']['provider_nameserver_change_checks']);if($bad==='grant_count')$GLOBALS['s']['grant_count']=1;refused('Active history refuses '.$bad,fn()=>registered_nameserver_changes_ready());}
    foreach(['w.actor_id<1','g.id IS NULL','p.idempotency_key','t.disposition','c.disposition'] as $fault){$GLOBALS['s']=$prepared;$GLOBALS['s']['sql_fault']=$fault;refused('Correspondence query refusal propagates '.$fault,fn()=>registered_nameserver_changes_ready());}
    reset_state();$GLOBALS['cwp_settings']=['staff_schema_version'=>'1'];registered_nameserver_changes_prepare('synthetic-password');check('Preparation reloads settings before owner admission under mutex',$GLOBALS['owner_cache_seen']===false);
    reset_state();$GLOBALS['s']['mutex']=false;refused('Readiness requires owning mutex',fn()=>registered_nameserver_changes_ready());
    reset_state();$GLOBALS['s']['tx']=true;refused('Preparation rejects inherited transaction',fn()=>registered_nameserver_changes_prepare('synthetic-password'));check('Inherited transaction creates no tables',$GLOBALS['s']['ddl']===[]);
    foreach(['owner','password','read_ready','operations_ready'] as $bad){reset_state();if($bad!=='password')$GLOBALS['s'][$bad]=false;refused('Preparation fresh prerequisite '.$bad,fn()=>registered_nameserver_changes_prepare($bad==='password'?'wrong':'synthetic-password'));check('No DDL before '.$bad,$GLOBALS['s']['ddl']===[]);}
    reset_state();$GLOBALS['s']['audit_fail']=true;refused('Preparation audit fault propagates',fn()=>registered_nameserver_changes_prepare('synthetic-password'));check('DDL interruption leaves only exact empty tables',count($GLOBALS['s']['tables'])===3&&$GLOBALS['s']['settings']===[]&&$GLOBALS['s']['audits']===[]);$GLOBALS['s']['audit_fail']=false;registered_nameserver_changes_prepare('synthetic-password');check('Empty DDL interruption resumes',registered_nameserver_changes_ready());
    $row=['id'=>str_repeat('a',32),'grant_seq'=>1,'profile_id'=>str_repeat('b',32),'domain_id'=>91,'link_id'=>str_repeat('c',32),'actor_id'=>7,'document_json'=>'{}','document_sha256'=>hash('sha256','{}'),'proof'=>str_repeat('d',64)];
    refused('Insert requires transaction as well as mutex',fn()=>registered_nameserver_changes_insert('request',$row));
    $GLOBALS['s']['audit_fail']=true;refused('Insert audit fault propagates',fn()=>staff_transaction(fn()=>registered_nameserver_changes_insert('request',$row)));check('Caller transaction rolls row and counter back',($GLOBALS['s']['rows']['provider_nameserver_change_requests']??[])===[]&&registered_nameserver_changes_counter('request')===0);
    $GLOBALS['s']['audit_fail']=false;staff_transaction(fn()=>registered_nameserver_changes_insert('request',$row));check('Insert writes exact row and separate counter',$GLOBALS['s']['rows']['provider_nameserver_change_requests']===[array_map(static fn($x)=>$x,$row)]&&registered_nameserver_changes_counter('request')===1);check('Insert audit binds ordered row digest',end($GLOBALS['s']['audits'])['detail']==='Registered nameserver change request '.$row['id'].' '.registered_nameserver_changes_digest(registered_nameserver_changes_values('request',$row)));
    foreach(['shape','actor','oversize','sha'] as $bad){$changed=$row;if($bad==='shape')$changed['extra']='bad';if($bad==='actor')$changed['actor_id']=0;if($bad==='oversize'){$changed['document_json']=str_repeat('a',4097);$changed['document_sha256']=hash('sha256',$changed['document_json']);}if($bad==='sha')$changed['document_sha256']=str_repeat('0',64);refused('Insert rejects '.$bad,fn()=>staff_transaction(fn()=>registered_nameserver_changes_insert('request',$changed)));}
    $GLOBALS['s']['settings']['registered_nameserver_changes_request_count']='999999999999999';refused('Counter overflow refuses before insert',fn()=>staff_transaction(fn()=>registered_nameserver_changes_insert('request',$row)));
    echo json_encode(['passed'=>true,'count'=>count($checks),'scope'=>'Actual storage/schema functions with modeled SQL, mutex, session, vault and service-history boundaries; no native SQL or provider execution.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){echo json_encode(['passed'=>false,'count'=>count($checks),'checks'=>$checks,'error'=>get_class($e).': '.$e->getMessage()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit(1);}
