<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function support_tickets_validate_schema(string $name,string $sql):void {
    $unique=match($name){'support_tickets'=>[],'support_ticket_events'=>['ticket_revision'=>['ticket_id','revision']],'support_ticket_staff_reads'=>['ticket_reader'=>['ticket_id','user_id']],'support_ticket_client_reads'=>['ticket_reader'=>['ticket_id','client_id']],default=>throw new LogicException('Unknown ticket table.')};
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){
        $collation=match($c['Field']){'id','ticket_id','support_session_id','audience','status','journal_sha256','before_sha256','after_sha256'=>'ascii_bin','initial_json','journal_json','input_json'=>'utf8mb4_bin',default=>null};
        if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==$collation)throw new RuntimeException('Incompatible ticket columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];foreach($unique as $key=>$fields)$expected[$key]=[0,$fields];
    $other=match($name){
        'support_tickets'=>['client_updates'=>['client_id','public_updated_epoch','id'],'staff_updates'=>['staff_updated_epoch','id'],'assigned_updates'=>['assignee_id','staff_updated_epoch','id'],'created_staff_id'=>['created_staff_id'],'created_client_id'=>['created_client_id']],
        'support_ticket_events'=>['actor_staff_id'=>['actor_staff_id'],'actor_client_id'=>['actor_client_id']],
        'support_ticket_staff_reads'=>['user_id'=>['user_id']],
        'support_ticket_client_reads'=>['client_id'=>['client_id']],
    };foreach($other as $key=>$fields)$expected[$key]=[1,$fields];
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $index){
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible ticket indexes.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$entry){ksort($entry[1]);$entry[1]=array_values($entry[1]);}unset($entry);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible ticket indexes. Existing data was preserved.');
    $expected=match($name){
        'support_tickets'=>['client_id'=>['studio_clients','id'],'created_staff_id'=>['users','id'],'created_client_id'=>['studio_clients','id'],'assignee_id'=>['users','id']],
        'support_ticket_events'=>['ticket_id'=>['support_tickets','id'],'actor_staff_id'=>['users','id'],'actor_client_id'=>['studio_clients','id']],
        'support_ticket_staff_reads'=>['ticket_id'=>['support_tickets','id'],'user_id'=>['users','id']],
        'support_ticket_client_reads'=>['ticket_id'=>['support_tickets','id'],'client_id'=>['studio_clients','id']],
    };
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible ticket relationships.');$seen=[];
    foreach($relations as $relation){$e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0]||$relation['REFERENCED_COLUMN_NAME']!==$e[1]||!in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible ticket relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}
function support_tickets_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Ticket setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=support_tickets_staff(false);
        if($actor['role']!=='owner'||!staff_can('staff.manage',$actor)||!password_verify($password,$actor['password_hash']))throw new RuntimeException('A current owner password is required to prepare tickets.');
        $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['support_tickets_schema_version'])['value']??'');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unrecognized ticket storage version. Existing data was preserved.');
        foreach(support_tickets_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            support_tickets_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!support_tickets_ready()){
                foreach(array_keys(support_tickets_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized ticket records exist. No data was adopted.');
                save_setting('support_tickets_schema_version','1');
            }
            support_tickets_audit((int)$actor['id'],'setup','Additive ticket storage verified');
        });
    });
}
