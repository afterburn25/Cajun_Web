<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_registration_period_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_registration_period_guard(bool $transaction=false):void {
    $key='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
    if(($transaction&&!db()->inTransaction())||(int)query('SELECT IS_USED_LOCK(?)=CONNECTION_ID()',[$key])->fetchColumn()!==1)throw new LogicException('Registration period evidence requires the owning account guard.');
}
function provider_registration_period_error():never {throw new RuntimeException('Original registration period history needs recovery. Restore the original database.');}
function provider_registration_period_json(array $value):string {return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
function provider_registration_period_schema_validate(string $name,string $sql):void {
    $reads=$name==='provider_registration_period_reads';catalog_validate_schema($name,$sql,$reads?['read_ordinal'=>['ordinal']]:[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){
        $collation=$c['Field']==='document_json'?'utf8mb4_bin':(in_array($c['Field'],['id','profile_id','credential_sha256','document_sha256','result_sha256'],true)?'ascii_bin':null);
        if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==$collation)throw new RuntimeException('Registration period columns differ. Existing data was preserved.');
    }
    $expected=$reads?['PRIMARY'=>[0,['id']],'read_ordinal'=>[0,['ordinal']],'profile_id'=>[1,['profile_id']],'actor_id'=>[1,['actor_id']]]:['PRIMARY'=>[0,['id']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i){if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Registration period indexes differ.');$actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];}
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Registration period indexes differ.');
    $expected=$reads?['id'=>'provider_refreshes','profile_id'=>'provider_profiles','actor_id'=>'users']:['id'=>'provider_registration_period_reads'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Registration period relationships differ.');$seen=[];
    foreach($relations as $r){$target=$expected[$r['COLUMN_NAME']]??null;if(!$target||isset($seen[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Registration period relationships differ.');$seen[$r['COLUMN_NAME']]=true;}
}
/** Shared result fields are string-projected in a fixed order, matching SQL JSON_QUOTE byte for byte. */
function provider_registration_period_result_columns():array {return ['id','state','reason','scope','coverage','package_count','skipped','skip_reasons','finished_at','elapsed_ms','key_fingerprint'];}
function provider_registration_period_result_hash(array $result):string {$d=[];foreach(provider_registration_period_result_columns() as $key)$d[$key]=(string)$result[$key];return hash('sha256',provider_registration_period_json($d));}
function provider_registration_period_result_sql():string {
    $parts=[];foreach(provider_registration_period_result_columns() as $i=>$key){$parts[]="'".($i?',':'{').'"'.$key.'":'."'";$parts[]='JSON_QUOTE(CAST(o.`'.$key.'` AS CHAR))';}$parts[]="'}'";
    return 'CAST(SHA2(CONCAT('.implode(',',$parts).'),256) AS BINARY)';
}
/** Fixed-query witnesses detect later paired loss without fetching an unbounded history. */
function provider_registration_period_ready():bool {
    provider_registration_period_guard();
    $marker=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registration_period_version'])['value']??null;
    $counter=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registration_period_count'])['value']??null;
    $finished=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_registration_period_finished'])['value']??null;
    $schema=provider_registration_period_schema(config()['db']['prefix']);$exists=[];$rows=0;
    foreach($schema as $name=>$sql){$exists[$name]=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);if($exists[$name])$rows+=(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn();}
    $actions=['provider.registration_period_prepared','provider.registration_period_started','provider.registration_period_saved'];
    $audits=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action IN (?,?,?)',$actions)->fetchColumn();
    $intents=provider_inventory_ready()?(int)query('SELECT COUNT(*) FROM '.table('provider_refreshes').' WHERE operation=?',[\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::DOMAIN_REGISTRATION_PERIODS])->fetchColumn():0;
    if($marker===null&&$counter===null&&$finished===null&&$rows===0&&$audits===0&&$intents===0)return false;
    foreach([$counter,$finished] as $n)if(!is_string($n)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$n)||(int)$n>2147483647)provider_registration_period_error();
    if($marker!=='1'||in_array(false,$exists,true)||!provider_inventory_ready())provider_registration_period_error();
    foreach($schema as $name=>$sql)provider_registration_period_schema_validate($name,$sql);
    $reads=table('provider_registration_period_reads');$observations=table('provider_observation_registration_periods');$refreshes=table('provider_refreshes');$results=table('provider_refresh_results');$audit=table('audit');
    $count=(int)$counter;$summary=one('SELECT COUNT(*) AS total,COALESCE(MAX(ordinal),0) AS last_ordinal,COALESCE(MIN(ordinal),0) AS first_ordinal FROM '.$reads);
    if($count!==$intents||(int)$summary['total']!==$count||(int)$summary['last_ordinal']!==$count||(int)$summary['first_ordinal']!==($count?1:0))provider_registration_period_error();
    $done=(int)query('SELECT COUNT(*) FROM '.$observations)->fetchColumn();
    if((int)$finished!==$done||$done>$count)provider_registration_period_error();
    foreach([$actions[0]=>1,$actions[1]=>$count,$actions[2]=>$done] as $action=>$expected)if((int)query('SELECT COUNT(*) FROM '.$audit.' WHERE CAST(action AS BINARY)=CAST(? AS BINARY)',[$action])->fetchColumn()!==$expected)provider_registration_period_error();
    foreach([$reads=>8192,$observations=>2097152] as $tableName=>$max)if(one('SELECT id FROM '.$tableName.' WHERE OCTET_LENGTH(document_json)>'.$max.' OR JSON_VALID(document_json)=0 OR CAST(SHA2(document_json,256) AS BINARY)<>CAST(document_sha256 AS BINARY) LIMIT 1'))provider_registration_period_error();
    $j=static fn(string $field):string=>"CAST(JSON_UNQUOTE(JSON_EXTRACT(c.document_json,'$.".$field."')) AS BINARY)";
    $different=[];foreach(['id','ordinal','profile_id','profile_revision','actor_id','started_epoch','refresh_seq','credential_sha256'] as $key)$different[]='NOT (CAST(c.'.$key.' AS CHAR) <=> '.$j($key).')';
    if(one('SELECT c.id FROM '.$reads.' c WHERE '.implode(' OR ',$different).' LIMIT 1'))provider_registration_period_error();
    if(one('SELECT c.id FROM '.$reads.' c LEFT JOIN '.$refreshes.' r ON r.id=c.id WHERE r.id IS NULL OR CAST(r.operation AS BINARY)<>CAST(? AS BINARY) OR r.profile_id<>c.profile_id OR r.profile_revision<>c.profile_revision OR r.actor_id<>c.actor_id OR r.started_epoch<>c.started_epoch OR r.seq<>c.refresh_seq OR r.started_at<>TIMESTAMPADD(SECOND,r.started_epoch,\'1970-01-01 00:00:00\') LIMIT 1',[\CajunWebPro\Foundation\TwentyIInventory\InventoryRead::DOMAIN_REGISTRATION_PERIODS]))provider_registration_period_error();
    if(one('SELECT c.id FROM '.$reads.' c WHERE (SELECT COUNT(*) FROM '.$audit." a WHERE CAST(a.action AS BINARY)=CAST('provider.registration_period_started' AS BINARY) AND a.user_id=c.actor_id AND CAST(a.detail AS BINARY)=CAST(CONCAT('Registration periods ',c.id,' sha256 ',c.document_sha256) AS BINARY))<>1 OR (SELECT COUNT(*) FROM ".$audit." a WHERE CAST(a.action AS BINARY)=CAST('provider.refresh_started' AS BINARY) AND a.user_id=c.actor_id AND CAST(a.detail AS BINARY)=CAST(CONCAT('Refresh ',c.id,' profile ',c.profile_id,' revision ',c.profile_revision) AS BINARY))<>1 LIMIT 1"))provider_registration_period_error();
    if(one('SELECT c.id FROM '.$reads.' c LEFT JOIN '.$results.' o ON o.id=c.id LEFT JOIN '.$observations.' s ON s.id=c.id WHERE (o.id IS NULL AND s.id IS NOT NULL) OR (o.id IS NOT NULL AND s.id IS NULL) OR (s.id IS NOT NULL AND CAST(s.result_sha256 AS BINARY)<>'.provider_registration_period_result_sql().') LIMIT 1'))provider_registration_period_error();
    if(one('SELECT s.id FROM '.$observations.' s JOIN '.$reads.' c ON c.id=s.id JOIN '.$results.' o ON o.id=s.id WHERE (SELECT COUNT(*) FROM '.$audit." a WHERE CAST(a.action AS BINARY)=CAST('provider.registration_period_saved' AS BINARY) AND a.user_id=c.actor_id AND CAST(a.detail AS BINARY)=CAST(CONCAT('Registration periods ',s.id,' sha256 ',s.document_sha256,' result ',s.result_sha256) AS BINARY))<>1 OR (SELECT COUNT(*) FROM ".$audit." a WHERE CAST(a.action AS BINARY)=CAST('provider.refresh_finished' AS BINARY) AND a.user_id=c.actor_id AND CAST(a.detail AS BINARY)=CAST(CONCAT('Refresh ',o.id,' ',o.state,IF(o.reason='','',' '),o.reason) AS BINARY))<>1 LIMIT 1"))provider_registration_period_error();
    // A lost result plus lost snapshot must not look pending while its independent finish audit survives.
    if(one('SELECT c.id FROM '.$reads.' c LEFT JOIN '.$results.' o ON o.id=c.id WHERE o.id IS NULL AND EXISTS (SELECT 1 FROM '.$audit." a WHERE CAST(a.action AS BINARY)=CAST('provider.refresh_finished' AS BINARY) AND a.detail LIKE CONCAT('Refresh ',c.id,' %')) LIMIT 1"))provider_registration_period_error();
    return true;
}
function provider_registration_period_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Registration period preparation requires its own transaction boundary.');
    staff_locked(function()use($password):void{
        $owner=provider_settings_owner($password);if(!provider_inventory_ready())throw new RuntimeException('Prepare provider inventory first.');
        $ready=provider_registration_period_ready();
        foreach(provider_registration_period_schema(config()['db']['prefix']) as $name=>$sql){if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);provider_registration_period_schema_validate($name,$sql);}
        if(!$ready)staff_transaction(function()use($owner):void{save_setting('provider_registration_period_version','1');save_setting('provider_registration_period_count','0');save_setting('provider_registration_period_finished','0');provider_settings_audit((int)$owner['id'],'registration_period_prepared','Registration period observation storage ready');});
        provider_registration_period_ready();
    });
}
