<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/provider_mailbox_schema.php';
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';

function provider_mailbox_id(string $id):void {
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Reload the mailbox form.');
}
function provider_mailbox_json(array $value):string {return json_encode($value,JSON_THROW_ON_ERROR);}
function provider_mailbox_document(array $row):array {
    if(!is_string($row['document']??null)||strlen($row['document'])>16384||!is_string($row['sha256']??null)||!hash_equals($row['sha256'],hash('sha256',$row['document'])))throw new RuntimeException('Original mailbox records need recovery.');
    try{$d=json_decode($row['document'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Original mailbox records need recovery.');}
    if(!is_array($d)||($d['id']??null)!==$row['id']||($d['format']??null)!==1||provider_mailbox_json($d)!==$row['document'])throw new RuntimeException('Original mailbox records need recovery.');
    return $d;
}
function provider_mailbox_has_history():bool {
    return one('SELECT id FROM '.table('audit').' WHERE action IN (?,?,?) LIMIT 1',['provider.mailbox_prepared','provider.mailbox_policy','provider.mailbox_started'])!==null
        ||(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_operation_grants'])!==null&&one('SELECT id FROM '.table('provider_operation_grants').' WHERE operation=? LIMIT 1',['mailboxes.create'])!==null)
        ||(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].'provider_operations'])!==null&&one('SELECT id FROM '.table('provider_operations').' WHERE operation=? OR (operation IS NULL AND idempotency_key LIKE \'mailbox:%\') LIMIT 1',['mailboxes.create'])!==null)
        ||one('SELECT name FROM '.table('settings').' WHERE name LIKE ? LIMIT 1',['provider_mailbox_policy_%'])!==null;
}
/** A GET never creates tables, and lost history never reopens a creation allowance. Caller holds the staff lock. */
function provider_mailbox_ready():bool {
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_mailbox_schema_version'])['value']??'';
    $found=0;foreach(array_keys(provider_mailbox_schema(config()['db']['prefix'])) as $n)if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$n]))$found++;
    if($v===''&&$found===0&&!provider_mailbox_has_history())return false;
    if($v!=='1'||$found!==2)throw new RuntimeException('Mailbox records need recovery. Restore the original database or finish initial preparation.');
    foreach(['provider_mailbox_policies'=>'provider.mailbox_policy','provider_mailbox_requests'=>'provider.mailbox_started'] as $n=>$action) {
        if((int)query('SELECT COUNT(*) FROM '.table($n))->fetchColumn()!==(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',[$action])->fetchColumn())throw new RuntimeException('Original mailbox history is incomplete. Restore the original database.');
    }
    if(!provider_operations_ready())throw new RuntimeException('Restore the original provider operation history before managing mailboxes.');
    // Independent durable witnesses survive partial restores of the mailbox family.
    // A lost request and its start audit must not reopen an already attempted address.
    if(one('SELECT g.id FROM '.table('provider_operation_grants').' g LEFT JOIN '.table('provider_mailbox_requests').' r ON r.id=g.id WHERE g.operation=? AND r.id IS NULL LIMIT 1',['mailboxes.create'])
        ||one('SELECT o.id FROM '.table('provider_operations').' o LEFT JOIN '.table('provider_mailbox_requests').' r ON o.idempotency_key=CONCAT(\'mailbox:\',r.id) WHERE (o.operation=? OR (o.operation IS NULL AND o.idempotency_key LIKE \'mailbox:%\')) AND r.id IS NULL LIMIT 1',['mailboxes.create']))throw new RuntimeException('Original mailbox operation requests are missing. Restore the original database.');
    return true;
}
function provider_mailbox_validate_schema(string $name,string $sql):void {
    if(!array_key_exists($name,provider_mailbox_schema(config()['db']['prefix'])))throw new LogicException('Unknown mailbox storage.');
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){
        $collation=in_array($c['Field'],['id','link_id','policy_id','profile_id','domain_name','local_name','document','sha256'],true)?'ascii_bin':null;
        if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==$collation)throw new RuntimeException('Incompatible mailbox storage. Existing records were preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    if($name==='provider_mailbox_policies')$expected+=['link_revision'=>[0,['link_id','revision']],'policy_owner'=>[1,['owner_id']]];
    else $expected+=['mailbox_target'=>[1,['profile_id','domain_name','local_name']],'mailbox_client'=>[1,['client_id','created_epoch']],'mailbox_policy'=>[1,['policy_id']]];
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $i){
        if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible mailbox indexes.');
        $actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];
    }
    foreach($actual as &$i){ksort($i[1]);$i[1]=array_values($i[1]);}unset($i);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible mailbox indexes.');
    $expected=$name==='provider_mailbox_policies'?['owner_id'=>['users','id']]:['id'=>['provider_operation_grants','id'],'policy_id'=>['provider_mailbox_policies','id'],'profile_id'=>['provider_profiles','id'],'client_id'=>['studio_clients','id']];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible mailbox relationships.');
    $seen=[];foreach($relations as $r){$e=$expected[$r['COLUMN_NAME']]??null;
        if(!$e||isset($seen[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0]||$r['REFERENCED_COLUMN_NAME']!==$e[1]||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible mailbox relationships.');$seen[$r['COLUMN_NAME']]=true;
    }
}
function provider_mailbox_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Mailbox preparation cannot run inside a transaction.');
    staff_locked(function()use($password):void {
        $actor=provider_settings_owner($password);
        if(!provider_operations_ready()||!provider_inventory_ready()||!studio_ready())throw new RuntimeException('Prepare provider operations, hosting inventory and client accounts first.');
        $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_mailbox_schema_version'])['value']??'';
        if(!in_array($v,['','1'],true)||($v===''&&provider_mailbox_has_history()))throw new RuntimeException('Restore the original mailbox history and preparation marker.');
        $schema=provider_mailbox_schema(config()['db']['prefix']);$missing=[];
        foreach($schema as $n=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$n])){if($v==='1')throw new RuntimeException('Restore original mailbox tables.');$missing[]=$sql;continue;}
            provider_mailbox_validate_schema($n,$sql);
            if($v===''&&(int)query('SELECT COUNT(*) FROM '.table($n))->fetchColumn())throw new RuntimeException('Restore the original mailbox preparation marker.');
        }
        foreach($missing as $sql)db()->exec($sql);
        foreach($schema as $n=>$sql)provider_mailbox_validate_schema($n,$sql);
        staff_transaction(function()use($actor):void{save_setting('provider_mailbox_schema_version','1');provider_settings_audit((int)$actor['id'],'mailbox_prepared','Mailbox creation records verified');});
        provider_mailbox_ready();
    });
}
