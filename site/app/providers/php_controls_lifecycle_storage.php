<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/php_controls_authority.php';
require_once CWP_CODE_ROOT.'/app/providers/php_controls_lifecycle_document.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/database/schema/provider_php_control_lifecycle_schema.php';

/** Internal local persistence only. No current authority, credential opening or network action. */
function provider_php_controls_lifecycle_tables():array {
    return ['read'=>'provider_php_control_reads','result'=>'provider_php_control_read_results','request'=>'provider_php_changes','terminal'=>'provider_php_dispatch_ends','check'=>'provider_php_change_checks'];
}
function provider_php_controls_lifecycle_table(string $kind):string {
    return provider_php_controls_lifecycle_tables()[$kind]??throw new InvalidArgumentException('Unknown PHP lifecycle record kind.');
}
function provider_php_controls_lifecycle_key(string $kind):string {
    provider_php_controls_lifecycle_table($kind);return match($kind){'result'=>'read_id','terminal'=>'request_id',default=>'id'};
}
function provider_php_controls_lifecycle_namespaces():array {
    $prefix=config()['db']['prefix'];$tables=[];
    foreach(['provider_php_control_','provider_php_change','provider_php_dispatch_'] as $part){$p=$prefix.$part;foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND BINARY LEFT(TABLE_NAME,?)=BINARY ?',[strlen($p),$p]) as $r)if($r['TABLE_NAME']!==$prefix.'provider_php_control_delegations')$tables[]=$r['TABLE_NAME'];}
    $tables=array_values(array_unique($tables));sort($tables,SORT_STRING);
    $settings=array_column(all('SELECT name FROM '.table('settings').' WHERE BINARY LEFT(name,?)=BINARY ? ORDER BY name',[strlen('provider_php_control_lifecycle_'),'provider_php_control_lifecycle_']),'name');
    $actions=array_column(all('SELECT DISTINCT action FROM '.table('audit').' WHERE BINARY LEFT(action,?)=BINARY ? ORDER BY action',[strlen('provider.php_control_lifecycle_'),'provider.php_control_lifecycle_']),'action');
    return ['tables'=>$tables,'settings'=>$settings,'actions'=>$actions];
}
function provider_php_controls_lifecycle_counter(string $kind):int {
    provider_php_controls_lifecycle_table($kind);$v=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_'.$kind.'_count'])['value']??null;
    if(!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]{0,14})\z/',$v)!==1)throw new RuntimeException('PHP lifecycle count needs recovery.');return (int)$v;
}
function provider_php_controls_lifecycle_schema_contract(string $kind):array {
    $actors=['actor_client_id'=>['studio_clients','id'],'actor_staff_id'=>['users','id']];
    $a=static fn(string $n):array=>[$n.'_client'=>[1,['actor_client_id']],$n.'_staff'=>[1,['actor_staff_id']]];
    return match($kind){
        'read'=>['indexes'=>['PRIMARY'=>[0,['id']],'seq'=>[0,['seq']],'php_control_read_resource'=>[1,['profile_id','package_id','kind','seq']],'php_control_read_profile_time'=>[1,['profile_id','started_epoch']],'php_control_read_staff_time'=>[1,['actor_staff_id','started_epoch']],'php_control_read_client_time'=>[1,['actor_client_id','started_epoch']],...$a('php_control_read')],'foreign'=>['profile_id'=>['provider_profiles','id'],...$actors]],
        'result'=>['indexes'=>['PRIMARY'=>[0,['read_id']]],'foreign'=>['read_id'=>['provider_php_control_reads','id']]],
        'request'=>['indexes'=>['PRIMARY'=>[0,['id']],'seq'=>[0,['seq']],'php_change_key'=>[0,['idempotency_key']],'php_change_resource'=>[1,['profile_id','package_id','seq']],'current_read_id'=>[1,['current_read_id']],'options_read_id'=>[1,['options_read_id']],...$a('php_change')],'foreign'=>['profile_id'=>['provider_profiles','id'],'current_read_id'=>['provider_php_control_reads','id'],'options_read_id'=>['provider_php_control_reads','id'],...$actors]],
        'terminal'=>['indexes'=>['PRIMARY'=>[0,['request_id']],...$a('php_dispatch_end')],'foreign'=>['request_id'=>['provider_php_changes','id'],...$actors]],
        'check'=>['indexes'=>['PRIMARY'=>[0,['id']],'php_change_check_read'=>[0,['read_id']],'php_change_check_request'=>[1,['request_id']],...$a('php_change_check')],'foreign'=>['request_id'=>['provider_php_changes','id'],'read_id'=>['provider_php_control_reads','id'],...$actors]],
        default=>throw new InvalidArgumentException('Unknown PHP lifecycle schema kind.'),
    };
}
function provider_php_controls_lifecycle_validate_schema(string $kind):void {
    $name=provider_php_controls_lifecycle_table($kind);$sql=provider_php_control_lifecycle_schema(config()['db']['prefix'])[$name];$columns=all('SHOW FULL COLUMNS FROM '.table($name));
    // Same declared-column comparison as catalog_validate_schema; these tables also have non-id primary keys.
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT|TEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
    if(count($columns)!==count($matches))throw new RuntimeException('PHP lifecycle columns need recovery.');
    foreach($columns as $i=>$c){$m=$matches[$i];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($c['Type']));$null=!str_contains($m[3],'NOT NULL')&&!str_contains($m[3],'PRIMARY KEY');$text=preg_match('/^(?:char|varchar|longtext)/i',$c['Type'])===1;$extra=$c['Field']==='seq'?'auto_increment':'';if($c['Field']!==$m[1]||$type!==strtolower($m[2])||$c['Null']!==($null?'YES':'NO')||$c['Extra']!==$extra||$c['Default']!==null||$c['Collation']!==($text?'ascii_bin':null))throw new RuntimeException('PHP lifecycle columns need recovery.');}
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('PHP lifecycle indexes need recovery.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);$contract=provider_php_controls_lifecycle_schema_contract($kind);$expected=$contract['indexes'];ksort($expected);if($actual!==$expected)throw new RuntimeException('PHP lifecycle indexes need recovery.');
    $foreign=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.COLUMN_NAME',[config()['db']['prefix'].$name]);
    $expected=$contract['foreign'];ksort($expected);if(count($foreign)!==count($expected))throw new RuntimeException('PHP lifecycle relationships need recovery.');
    foreach($expected as $column=>[$target,$key]){$f=array_shift($foreign);if($f['COLUMN_NAME']!==$column||$f['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$f['REFERENCED_COLUMN_NAME']!==$key||!in_array($f['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($f['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('PHP lifecycle relationships need recovery.');}
    $scoped=one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=\'information_schema\' AND TABLE_NAME=\'CHECK_CONSTRAINTS\' AND COLUMN_NAME=\'TABLE_NAME\'');
    $checks=all('SELECT c.CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS c JOIN information_schema.TABLE_CONSTRAINTS t ON t.CONSTRAINT_SCHEMA=c.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME=c.CONSTRAINT_NAME'.($scoped?' AND c.TABLE_NAME=t.TABLE_NAME':'').' WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME=? AND t.CONSTRAINT_TYPE=\'CHECK\'',[config()['db']['prefix'].$name]);
    if(count($checks)!==($kind==='result'?0:1)||($checks&&preg_replace('/[\s`()]/','',strtolower($checks[0]['CHECK_CLAUSE']))!=='actor_staff_idisnull<>actor_client_idisnull'))throw new RuntimeException('PHP lifecycle actor constraint needs recovery.');
    if(one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=\'information_schema\' AND TABLE_NAME=\'TABLE_CONSTRAINTS\' AND COLUMN_NAME=\'ENFORCED\'')&&one('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_TYPE=\'CHECK\' AND ENFORCED<>\'YES\'',[config()['db']['prefix'].$name]))throw new RuntimeException('PHP lifecycle actor constraint is disabled.');
    $switch=one("SHOW VARIABLES LIKE 'check_constraint_checks'");if($switch&&strtoupper((string)$switch['Value'])!=='ON')throw new RuntimeException('PHP lifecycle actor checks must be enabled.');
    if(strtolower((string)query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[config()['db']['prefix'].$name])->fetchColumn())!=='innodb')throw new RuntimeException('PHP lifecycle tables require transactional storage.');
}
function provider_php_controls_lifecycle_preparation_witness(string $kind):int {
    if(!in_array($kind,['started','prepared'],true))throw new LogicException('Invalid PHP preparation witness.');$detail=$kind==='started'?'PHP change lifecycle preparation started':'PHP change lifecycle storage ready';
    $rows=all('SELECT id,user_id,detail FROM '.table('audit').' WHERE BINARY action=? LIMIT 2',['provider.php_control_lifecycle_'.$kind]);
    if(count($rows)!==1||(int)$rows[0]['user_id']<1||$rows[0]['detail']!==$detail)throw new RuntimeException('PHP lifecycle preparation needs recovery.');return (int)$rows[0]['id'];
}
function provider_php_controls_lifecycle_witness_engines():void {
    foreach(['settings','audit','provider_operations'] as $name)if(strtolower((string)query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[config()['db']['prefix'].$name])->fetchColumn())!=='innodb')throw new RuntimeException('PHP lifecycle witnesses require transactional storage.');
}
/** Exact ledger subset. Other operation types remain outside this subsystem. */
function provider_php_controls_lifecycle_ledgers():array {
    if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_operations']))return [];
    return all('SELECT id FROM '.table('provider_operations').' WHERE BINARY operation=? OR BINARY LEFT(idempotency_key,?)=BINARY ? LIMIT 1',['packages.phpversion',strlen('phpversion:'),'phpversion:']);
}
function provider_php_controls_lifecycle_ledger_row(array $r):array {
    $out=[];foreach(['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch','reconciled_outcome','reconciled_epoch'] as $key){if(!array_key_exists($key,$r))throw new RuntimeException('PHP ledger columns need recovery.');$v=$r[$key];if(in_array($key,['attempts','claimed_epoch','resolved_epoch','reconciled_epoch'],true)&&$v!==null){if(!is_int($v)&&(!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]*)\z/',$v)!==1))throw new RuntimeException('PHP ledger number needs recovery.');$v=(int)$v;} $out[$key]=$v;}return $out;
}
function provider_php_controls_lifecycle_no_grants():void {
    if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_operation_grants'])&&one('SELECT id FROM '.table('provider_operation_grants').' WHERE BINARY operation=? LIMIT 1',['packages.phpversion']))throw new RuntimeException('PHP changes cannot use general owner grants.');
}
function provider_php_controls_lifecycle_ledger_for(array $request):?array {
    $r=one('SELECT * FROM '.table('provider_operations').' WHERE BINARY idempotency_key=?',[$request['idempotency_key']]);if(!$r)return null;$row=provider_php_controls_lifecycle_ledger_row($r);
    try{return provider_php_controls_lifecycle_ledger($row,$request,max($row['claimed_epoch'],$row['resolved_epoch']??0));}catch(InvalidArgumentException|TypeError $e){throw new RuntimeException('Original PHP operation ledger needs recovery.',0,$e);}
}
function provider_php_controls_lifecycle_actor_for(string $kind,array $d,array $sources):array {
    return match($kind){'read'=>$d['context']['actor'],'result'=>$sources[0]['context']['actor'],'request'=>$d['review']['context']['actor'],'terminal'=>$d['recorded_by'],'check'=>$d['checked_by'],default=>throw new InvalidArgumentException('Unknown PHP lifecycle actor kind.')};
}
function provider_php_controls_lifecycle_project(string $kind,array $d,array $sources):array {
    $actor=provider_php_controls_lifecycle_actor_columns(provider_php_controls_lifecycle_actor_for($kind,$d,$sources));
    $b=match($kind){'read'=>$d['context']['binding'],'request'=>$d['review']['context']['binding'],default=>null};
    $r=match($kind){
        'read'=>['id'=>$d['id'],'seq'=>$d['seq'],'profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'link_id'=>$b['link_id'],'kind'=>$d['kind'],...$actor,'started_epoch'=>$d['started_epoch']],
        'result'=>['read_id'=>$d['read_id'],'state'=>$d['state'],'reason'=>$d['reason'],'finished_epoch'=>$d['finished_epoch']],
        'request'=>['id'=>$d['id'],'seq'=>$d['seq'],'profile_id'=>$b['profile_id'],'package_id'=>$b['package_id'],'link_id'=>$b['link_id'],...$actor,'current_read_id'=>$d['review']['current_read_id'],'options_read_id'=>$d['review']['options_read_id'],'idempotency_key'=>$d['idempotency_key'],'approved_epoch'=>$d['approved_epoch'],'expires_epoch'=>$d['expires_epoch']],
        'terminal'=>['request_id'=>$d['request_id'],'disposition'=>$d['phase'],'recorded_epoch'=>$d['recorded_epoch'],...$actor,'ledger_sha256'=>$d['ledger_sha256']],
        'check'=>['id'=>$d['id'],'request_id'=>$d['request_id'],'read_id'=>$d['read_id'],...$actor,'recorded_epoch'=>$d['recorded_epoch'],'disposition'=>$d['disposition']],
        default=>throw new InvalidArgumentException('Unknown PHP lifecycle projection kind.'),
    };$r['document_json']=provider_php_controls_lifecycle_json($d);$r['document_sha256']=hash('sha256',$r['document_json']);return $r;
}
function provider_php_controls_lifecycle_values(array $row):array {return array_map(static fn($v)=>$v===null?null:(string)$v,$row);}
function provider_php_controls_lifecycle_proof(string $kind,array $row):string {unset($row['proof']);return hash_hmac('sha256','packages.phpversion|lifecycle|'.$kind.'|'.provider_php_controls_lifecycle_json(provider_php_controls_lifecycle_values($row)),provider_settings_vault_key());}
function provider_php_controls_lifecycle_audit_detail(string $kind,array $row,array $actor):string {
    return 'PHP lifecycle '.$kind.' '.$row[provider_php_controls_lifecycle_key($kind)].' '.$actor['kind'].':'.$actor['id'].' '.hash('sha256',provider_php_controls_lifecycle_json(provider_php_controls_lifecycle_values($row)));
}
/** Uses only retained authority; rotating an account or revoking a grant does not erase history. */
function provider_php_controls_lifecycle_retained_context(array $context):void {
    $b=new \CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding($context['binding']);provider_php_retained_binding($b);
    if($context['actor']['kind']==='client'){
        $row=one('SELECT * FROM '.table('provider_php_control_delegations').' WHERE id=?',[$context['permission_id']]);if(!$row)throw new RuntimeException('Original PHP client permission is missing.');$p=provider_php_controls_decode($row);$d=$p['document'];
        if(!$d['enabled']||$p['sha256']!==$context['permission_sha256']||$d['client_id']!==$context['actor']['id']||$d['client_auth_version']!==$context['actor']['auth_version']||!provider_php_controls_lifecycle_same_resource($d['binding'],$context['binding']))throw new RuntimeException('Original PHP client permission disagrees.');
    }
}
/** Dependency loader is scoped to one complete validation, never a reusable authority cache. */
function provider_php_controls_lifecycle_sources(string $kind,array $d,array &$state):array {
    $get=static function(string $k,mixed $id)use(&$state):array{provider_php_controls_lifecycle_id($id);return provider_php_controls_lifecycle_decode($k,$id,$state)??throw new RuntimeException('Original PHP lifecycle dependency is missing.');};
    if($kind==='read')return [];
    if($kind==='result')return [$get('read',$d['read_id']??null)];
    if($kind==='request'){if(!is_array($d['review']??null))throw new InvalidArgumentException('Missing PHP request review.');return [$get('read',$d['review']['current_read_id']??null),$get('result',$d['review']['current_read_id']??null),$get('read',$d['review']['options_read_id']??null),$get('result',$d['review']['options_read_id']??null)];}
    $request=$get('request',$d['request_id']??null);$sources=provider_php_controls_lifecycle_sources('request',$request['document'],$state);
    if($kind==='terminal')return [$request,...$sources];
    return [$request,$get('terminal',$d['request_id']),...$sources,$get('read',$d['read_id']??null),$get('result',$d['read_id']??null)];
}
function provider_php_controls_lifecycle_decode(string $kind,string $id,array &$state):?array {
    $table=provider_php_controls_lifecycle_table($kind);provider_php_controls_lifecycle_id($id);if(isset($state['decoded'][$kind][$id]))return $state['decoded'][$kind][$id];$r=one('SELECT * FROM '.table($table).' WHERE '.provider_php_controls_lifecycle_key($kind).'=?',[$id]);if($r===null)return null;
    if(isset($state['visiting'][$kind][$id]))throw new RuntimeException('PHP lifecycle dependency cycle needs recovery.');$state['visiting'][$kind][$id]=true;
    try{
        if(!is_string($r['document_json']??null)||strlen($r['document_json'])>131072)throw new RuntimeException('PHP lifecycle document needs recovery.');$raw=json_decode($r['document_json'],true,24,JSON_THROW_ON_ERROR);if(!is_array($raw))throw new RuntimeException('PHP lifecycle document needs recovery.');
        $refs=provider_php_controls_lifecycle_sources($kind,$raw,$state);$sources=array_column($refs,'document');$d=provider_php_controls_lifecycle_parse($kind,$r['document_json'],...$sources);$expected=provider_php_controls_lifecycle_project($kind,$d,$sources);$expected['proof']=provider_php_controls_lifecycle_proof($kind,$expected);
        if(provider_php_controls_lifecycle_values($r)!==provider_php_controls_lifecycle_values($expected))throw new RuntimeException('PHP lifecycle row, identity or proof needs recovery.');
        $actor=provider_php_controls_lifecycle_actor_for($kind,$d,$sources);$detail=provider_php_controls_lifecycle_audit_detail($kind,$expected,$actor);$audits=all('SELECT id,user_id,detail FROM '.table('audit').' WHERE BINARY action=? AND BINARY detail=? LIMIT 2',['provider.php_control_lifecycle_'.$kind,$detail]);
        if(count($audits)!==1||(int)$audits[0]['user_id']!==($actor['kind']==='staff'?$actor['id']:0))throw new RuntimeException('PHP lifecycle audit needs recovery.');$auditId=(int)$audits[0]['id'];
        if($auditId<=$state['prepared'])throw new RuntimeException('PHP lifecycle record predates preparation.');foreach($refs as $ref)if($ref['audit_id']>=$auditId)throw new RuntimeException('PHP lifecycle dependency order needs recovery.');
        if($kind==='read')provider_php_controls_lifecycle_retained_context($d['context']);
        if($kind==='terminal'){
            $ledger=provider_php_controls_lifecycle_ledger_for($sources[0]);if($d['phase']==='not_sent'&&$ledger!==null)throw new RuntimeException('An operation claim cannot prove no send.');
            if($d['ledger']!==null&&$d['ledger']['status']!=='claimed'&&$ledger!==$d['ledger'])throw new RuntimeException('Original finished PHP operation ledger changed.');
            // Missing/claimed snapshots may remain pending even if the ledger later finishes. They never establish completion.
            if($d['ledger']!==null&&$d['ledger']['status']==='claimed'&&($ledger===null||array_diff_assoc(array_intersect_key($ledger,array_flip(['id','idempotency_key','fingerprint','claimed_at','claimed_epoch'])),array_intersect_key($d['ledger'],array_flip(['id','idempotency_key','fingerprint','claimed_at','claimed_epoch'])))))throw new RuntimeException('Original PHP claim changed.');
        }
        if($kind==='check'&&($sources[1]['ledger']===null||$sources[1]['ledger']['status']==='claimed'))throw new RuntimeException('PHP verification requires recovery of an original finished operation ledger.');
        return $state['decoded'][$kind][$id]=['row'=>$r,'document'=>$d,'sha256'=>$r['document_sha256'],'audit_id'=>$auditId];
    }catch(JsonException|InvalidArgumentException|TypeError $e){throw new RuntimeException('PHP lifecycle retained document needs recovery.',0,$e);}finally{unset($state['visiting'][$kind][$id]);}
}
/** Full validation in keyset batches; the dependency cache is discarded after every record. */
function provider_php_controls_lifecycle_validate_history(array &$state):void {
    foreach(provider_php_controls_lifecycle_tables() as $kind=>$name)if(one('SELECT '.provider_php_controls_lifecycle_key($kind).' FROM '.table($name).' WHERE OCTET_LENGTH(document_json)>131072 LIMIT 1'))throw new RuntimeException('PHP lifecycle retained document is too large.');
    foreach(provider_php_controls_lifecycle_tables() as $kind=>$name){
        if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==$state['counts'][$kind]||(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE BINARY action=?',['provider.php_control_lifecycle_'.$kind])->fetchColumn()!==$state['counts'][$kind])throw new RuntimeException('PHP lifecycle counts disagree.');
        $sequential=in_array($kind,['read','request'],true);$column=$sequential?'seq':provider_php_controls_lifecycle_key($kind);$last=$sequential?0:'';$seen=0;$audit=0;
        do{$rows=all('SELECT '.provider_php_controls_lifecycle_key($kind).','.$column.' AS cursor_value FROM '.table($name).' WHERE '.$column.'>? ORDER BY '.$column.' LIMIT 64',[$last]);
            foreach($rows as $row){$state['decoded']=[];$state['visiting']=[];$record=provider_php_controls_lifecycle_decode($kind,$row[provider_php_controls_lifecycle_key($kind)],$state)??throw new RuntimeException('PHP lifecycle record disappeared.');$d=$record['document'];$seen++;
                if($sequential&&($d['seq']!==$seen||$record['audit_id']<=$audit))throw new RuntimeException('PHP lifecycle sequence needs recovery.');$audit=$record['audit_id'];$last=$sequential?(int)$row['cursor_value']:$row['cursor_value'];
                if($kind==='request')provider_php_controls_lifecycle_request_order($record,$state);
                if($kind==='check'){$release=provider_php_controls_lifecycle_release($d['request_id'],$state);if($release!==null&&$record['audit_id']>$release)throw new RuntimeException('PHP verification continued after resolution.');}
            }
        }while(count($rows)===64);
        if($seen!==$state['counts'][$kind])throw new RuntimeException('PHP lifecycle traversal count disagrees.');
    }
    $expected=(int)query('SELECT COUNT(*) FROM '.table('provider_operations').' WHERE BINARY operation=? OR BINARY LEFT(idempotency_key,?)=BINARY ?',['packages.phpversion',strlen('phpversion:'),'phpversion:'])->fetchColumn();$seen=0;
    $last='';do{$rows=all('SELECT * FROM '.table('provider_operations').' WHERE (BINARY operation=? OR BINARY LEFT(idempotency_key,?)=BINARY ?) AND id>? ORDER BY id LIMIT 64',['packages.phpversion',strlen('phpversion:'),'phpversion:',$last]);
        foreach($rows as $raw){$state['decoded']=[];$state['visiting']=[];$row=provider_php_controls_lifecycle_ledger_row($raw);if(preg_match('/\Aphpversion:([a-f0-9]{32})\z/',$row['idempotency_key'],$m)!==1)throw new RuntimeException('PHP operation has an unrecognized request key.');$request=provider_php_controls_lifecycle_decode('request',$m[1],$state);if(!$request)throw new RuntimeException('Original PHP operation request is missing.');if(provider_php_controls_lifecycle_ledger_for($request['document'])!==$row)throw new RuntimeException('Original PHP operation ledger disagrees.');$last=$row['id'];$seen++;}
    }while(count($rows)===64);if($seen!==$expected)throw new RuntimeException('PHP operation traversal count disagrees.');$state['decoded']=[];$state['visiting']=[];
}
function provider_php_controls_lifecycle_request_order(array $record,array &$state):void {
    $d=$record['document'];$b=$d['review']['context']['binding'];$previous=one('SELECT id FROM '.table('provider_php_changes').' WHERE profile_id=? AND package_id=? AND seq<? ORDER BY seq DESC LIMIT 1',[$b['profile_id'],$b['package_id'],$d['seq']]);
    if($previous){$release=provider_php_controls_lifecycle_release($previous['id'],$state);if($release===null||$release>=$record['audit_id'])throw new RuntimeException('Overlapping PHP requests need recovery.');foreach(['current_read_id','options_read_id'] as $field){$read=provider_php_controls_lifecycle_decode('read',$d['review'][$field],$state);if(!$read||$read['audit_id']<=$release)throw new RuntimeException('PHP request reused observations from before its previous change.');}}
    // Audits are already authenticated independently. This indexed selection includes unfinished/failed newer intents.
    $prefix="CONCAT('PHP lifecycle read ',r.id,' ')";
    foreach(['current'=>'current_read_id','available'=>'options_read_id'] as $kind=>$field){$latest=one('SELECT r.id FROM '.table('provider_php_control_reads').' r JOIN '.table('audit').' a ON BINARY a.action=? AND BINARY LEFT(a.detail,LENGTH('.$prefix.'))=BINARY '.$prefix.' WHERE r.link_id=? AND BINARY r.kind=? AND a.id<? ORDER BY r.seq DESC LIMIT 1',['provider.php_control_lifecycle_read',$b['link_id'],$kind,$record['audit_id']]);if(($latest['id']??null)!==$d['review'][$field])throw new RuntimeException('PHP request did not use the latest observation intent.');}
}
/** Original local release witness; a finished single-attempt ledger and later exact scalar are required. */
function provider_php_controls_lifecycle_release(string $requestId,array &$state):?int {
    $terminal=provider_php_controls_lifecycle_decode('terminal',$requestId,$state);if(!$terminal)return null;if($terminal['document']['phase']==='not_sent')return $terminal['audit_id'];
    if($terminal['document']['ledger']===null||$terminal['document']['ledger']['status']==='claimed')return null;
    $rows=all('SELECT id FROM '.table('provider_php_change_checks').' WHERE request_id=? AND BINARY disposition=? LIMIT 2',[$requestId,'observed']);if(count($rows)>1)throw new RuntimeException('PHP request has multiple release witnesses.');if(!$rows)return null;$check=provider_php_controls_lifecycle_decode('check',$rows[0]['id'],$state);return $check['audit_id'];
}
/** Retained-state fingerprint covering every table the validators read; a changed stamp forces a fresh full validation. */
function provider_php_controls_lifecycle_stamp():array {
    static $columns=[];
    $prefix=config()['db']['prefix'];$stamp=['audit'=>implode('.',one('SELECT COUNT(*) c,COALESCE(MAX(id),0) m FROM '.table('audit')))];
    foreach(['settings','provider_php_control_reads','provider_php_control_read_results','provider_php_changes','provider_php_dispatch_ends','provider_php_change_checks','provider_php_control_delegations','provider_refreshes','provider_refresh_results','provider_php_reads','provider_php_observations','provider_php_permissions','provider_package_links','provider_package_link_events','provider_observation_packages','service_observation_permissions','provider_profiles','provider_revisions','provider_operations','provider_operation_grants','users','studio_clients'] as $name){
        if(empty($columns[$name]))$columns[$name]=array_column(all('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ORDINAL_POSITION',[$prefix.$name]),'COLUMN_NAME');
        if(!$columns[$name]){$stamp[$name]='missing';continue;}
        $body='CONCAT('.implode(',CHAR(30),',array_map(static fn(string $c):string=>'COALESCE(`'.$c.'`,CHAR(31))',$columns[$name])).')';
        $r=one('SELECT COUNT(*) c,COALESCE(BIT_XOR(CRC32('.$body.')),0) x,COALESCE(SUM(CRC32('.$body.')),0) s FROM `'.$prefix.$name.'`');
        $stamp[$name]=$r['c'].'.'.$r['x'].'.'.$r['s'];
    }
    ksort($stamp);return $stamp;
}
/** Full history validation. LIMIT-based UI pagination does not bound this work. No DDL or current-authority lookup. */
function provider_php_controls_lifecycle_history():?array {
    static $memo=null,$memoStamp=null;
    provider_php_controls_guard();$stamp=PHP_SAPI==='cli'?null:provider_php_controls_lifecycle_stamp();
    if($stamp!==null&&$memoStamp!==null&&$stamp===$memoStamp)return $memo;
    $n=provider_php_controls_lifecycle_namespaces();$version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_schema_version'])['value']??'';$ledgers=provider_php_controls_lifecycle_ledgers();provider_php_controls_lifecycle_no_grants();
    if($version===''){if($n['tables']||$n['settings']||$n['actions']||$ledgers)throw new RuntimeException('Restore original PHP change lifecycle history.');if($stamp!==null){$memo=null;$memoStamp=$stamp;}return null;}
    $tables=array_map(static fn($v)=>config()['db']['prefix'].$v,array_values(provider_php_controls_lifecycle_tables()));sort($tables,SORT_STRING);$settings=['provider_php_control_lifecycle_schema_version'];$actions=['provider.php_control_lifecycle_started','provider.php_control_lifecycle_prepared'];foreach(array_keys(provider_php_controls_lifecycle_tables()) as $kind){$settings[]='provider_php_control_lifecycle_'.$kind.'_count';$actions[]='provider.php_control_lifecycle_'.$kind;}sort($settings,SORT_STRING);
    if($version!=='1'||$n['tables']!==$tables||$n['settings']!==$settings||array_diff($n['actions'],$actions))throw new RuntimeException('PHP change lifecycle storage needs recovery.');
    if(!provider_php_controls_ready()||!provider_operations_ready())throw new RuntimeException('Original PHP delegation and operation storage is required.');foreach(array_keys(provider_php_controls_lifecycle_tables()) as $kind)provider_php_controls_lifecycle_validate_schema($kind);
    provider_php_controls_lifecycle_witness_engines();
    $start=provider_php_controls_lifecycle_preparation_witness('started');$prepared=provider_php_controls_lifecycle_preparation_witness('prepared');if($start>=$prepared)throw new RuntimeException('PHP lifecycle preparation order needs recovery.');$state=['counts'=>[],'decoded'=>[],'visiting'=>[],'prepared'=>$prepared];
    foreach(array_keys(provider_php_controls_lifecycle_tables()) as $kind)$state['counts'][$kind]=provider_php_controls_lifecycle_counter($kind);
    provider_php_controls_lifecycle_validate_history($state);
    if($stamp!==null){$memo=$state;$memoStamp=$stamp;}
    return $state;
}
function provider_php_controls_lifecycle_ready():bool {return provider_php_controls_lifecycle_history()!==null;}
function provider_php_controls_lifecycle_get(string $kind,string $id):?array {provider_php_controls_lifecycle_table($kind);provider_php_controls_lifecycle_id($id);$s=provider_php_controls_lifecycle_history();return $s===null?null:provider_php_controls_lifecycle_decode($kind,$id,$s);}
/** Authenticated canonical 14-column current ledger, including a still-claimed row. */
function provider_php_controls_lifecycle_ledger_snapshot(string $requestId):?array {
    provider_php_controls_lifecycle_id($requestId);$s=provider_php_controls_lifecycle_history();$request=$s===null?null:provider_php_controls_lifecycle_decode('request',$requestId,$s);if(!$request)throw new RuntimeException('Original PHP change request is missing.');return provider_php_controls_lifecycle_ledger_for($request['document']);
}
function provider_php_controls_lifecycle_next(string $kind):int {
    provider_php_controls_guard(true);if(!in_array($kind,['read','request'],true))throw new InvalidArgumentException('Only PHP reads and requests have a sequence.');$s=provider_php_controls_lifecycle_history();if(!$s)throw new RuntimeException('Prepare PHP change lifecycle storage first.');$next=$s['counts'][$kind]+1;provider_php_controls_lifecycle_seq($next);return $next;
}
function provider_php_controls_lifecycle_latest(string $linkId,string $kind):?array {
    provider_php_controls_lifecycle_id($linkId);if(!in_array($kind,['current','available'],true))throw new InvalidArgumentException('Unknown PHP observation kind.');$s=provider_php_controls_lifecycle_history();if($s===null)return null;$row=one('SELECT id FROM '.table('provider_php_control_reads').' WHERE link_id=? AND BINARY kind=? ORDER BY seq DESC LIMIT 1',[$linkId,$kind]);if(!$row)return null;return ['read'=>provider_php_controls_lifecycle_decode('read',$row['id'],$s),'result'=>provider_php_controls_lifecycle_decode('result',$row['id'],$s)];
}
function provider_php_controls_lifecycle_pending(string $profileId,int $packageId):?array {
    provider_php_controls_lifecycle_id($profileId);if($packageId<1)throw new InvalidArgumentException('Invalid PHP package.');$s=provider_php_controls_lifecycle_history();if($s===null)return null;$row=one('SELECT id FROM '.table('provider_php_changes').' WHERE profile_id=? AND package_id=? ORDER BY seq DESC LIMIT 1',[$profileId,$packageId]);if(!$row||provider_php_controls_lifecycle_release($row['id'],$s)!==null)return null;return ['request'=>provider_php_controls_lifecycle_decode('request',$row['id'],$s),'terminal'=>provider_php_controls_lifecycle_decode('terminal',$row['id'],$s)];
}
/** Caller owns the transaction and must roll it back on any exception; no partial record is returned. */
function provider_php_controls_lifecycle_insert(string $kind,array $document):array {
    provider_php_controls_guard(true);provider_php_controls_lifecycle_table($kind);$s=provider_php_controls_lifecycle_history();if($s===null)throw new RuntimeException('Prepare PHP change lifecycle storage first.');$refs=provider_php_controls_lifecycle_sources($kind,$document,$s);$sources=array_column($refs,'document');$d=provider_php_controls_lifecycle_parse($kind,provider_php_controls_lifecycle_json($document),...$sources);$row=provider_php_controls_lifecycle_project($kind,$d,$sources);$id=$row[provider_php_controls_lifecycle_key($kind)];
    if(provider_php_controls_lifecycle_decode($kind,$id,$s)!==null)throw new RuntimeException('This PHP lifecycle record already exists.');if(in_array($kind,['read','request'],true)&&$d['seq']!==$s['counts'][$kind]+1)throw new RuntimeException('PHP lifecycle sequence changed.');
    if($kind==='terminal'&&$d['ledger']!==provider_php_controls_lifecycle_ledger_for($sources[0]))throw new RuntimeException('Capture the exact original PHP ledger before recording its terminal.');
    $row['proof']=provider_php_controls_lifecycle_proof($kind,$row);$columns=array_keys($row);query('INSERT INTO '.table(provider_php_controls_lifecycle_table($kind)).' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')',array_values($row));
    $actor=provider_php_controls_lifecycle_actor_for($kind,$d,$sources);provider_settings_audit($actor['kind']==='staff'?$actor['id']:0,'php_control_lifecycle_'.$kind,provider_php_controls_lifecycle_audit_detail($kind,$row,$actor));save_setting('provider_php_control_lifecycle_'.$kind.'_count',(string)($s['counts'][$kind]+1));
    $after=provider_php_controls_lifecycle_history();return provider_php_controls_lifecycle_decode($kind,$id,$after)??throw new RuntimeException('PHP lifecycle record was not retained.');
}
function provider_php_controls_lifecycle_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Prepare PHP lifecycle outside a transaction.');staff_locked(function()use($password):void{
        unset($GLOBALS['cwp_settings']);$owner=provider_settings_owner($password);if(!provider_php_controls_ready()||!provider_operations_ready())throw new RuntimeException('Prepare PHP delegation and provider operation storage first.');provider_php_controls_lifecycle_witness_engines();$version=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_php_control_lifecycle_schema_version'])['value']??'';
        if($version==='1'){provider_php_controls_lifecycle_ready();return;}
        if($version===''){provider_php_controls_lifecycle_ready();staff_transaction(function()use($owner):void{save_setting('provider_php_control_lifecycle_schema_version','preparing-1');provider_settings_audit((int)$owner['id'],'php_control_lifecycle_started','PHP change lifecycle preparation started');});}elseif($version!=='preparing-1')throw new RuntimeException('Unknown PHP lifecycle preparation state.');
        $n=provider_php_controls_lifecycle_namespaces();$schema=provider_php_control_lifecycle_schema(config()['db']['prefix']);$ordered=array_keys($schema);$expected=array_map(static fn($name)=>config()['db']['prefix'].$name,array_slice($ordered,0,count($n['tables'])));sort($expected,SORT_STRING);
        if($n['tables']!==$expected||$n['settings']!==['provider_php_control_lifecycle_schema_version']||$n['actions']!==['provider.php_control_lifecycle_started']||provider_php_controls_lifecycle_ledgers())throw new RuntimeException('PHP lifecycle preparation needs original history recovery.');provider_php_controls_lifecycle_no_grants();provider_php_controls_lifecycle_preparation_witness('started');
        foreach(provider_php_controls_lifecycle_tables() as $kind=>$name)if(in_array(config()['db']['prefix'].$name,$n['tables'],true)){provider_php_controls_lifecycle_validate_schema($kind);if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Nonempty PHP lifecycle preparation requires recovery.');}
        foreach(provider_php_controls_lifecycle_tables() as $kind=>$name){if(!in_array(config()['db']['prefix'].$name,$n['tables'],true))db()->exec($schema[$name]);provider_php_controls_lifecycle_validate_schema($kind);}
        staff_transaction(function()use($owner):void{foreach(array_keys(provider_php_controls_lifecycle_tables()) as $kind)save_setting('provider_php_control_lifecycle_'.$kind.'_count','0');save_setting('provider_php_control_lifecycle_schema_version','1');provider_settings_audit((int)$owner['id'],'php_control_lifecycle_prepared','PHP change lifecycle storage ready');});provider_php_controls_lifecycle_ready();
    });
}
