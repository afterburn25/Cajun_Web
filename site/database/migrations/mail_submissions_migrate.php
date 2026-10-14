<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_submissions_validate_schema(string $name,string $sql):void {
    $unique=$name==='mail_submissions'?['attempt_id'=>['attempt_id'],'draft_revision'=>['draft_id','draft_revision']]:['submission_id'=>['submission_id']];
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $collation=match($column['Field']) {
            'id','attempt_id','draft_id','account_id','submission_id','snapshot_fingerprint','mime_sha256','mime_bytes','scope','status','phase','reason'=>'ascii_bin',
            'payload_json'=>'utf8mb4_bin',default=>null,
        };
        if($column['Collation']!==$collation || $column['Default']!==null || $column['Extra']!=='')throw new RuntimeException('Incompatible submission columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    foreach($unique as $key=>$fields)$expected[$key]=[0,$fields];
    if($name==='mail_submissions')$expected+=['owner_history'=>[1,['owner_id','created_at','id']],'account_id'=>[1,['account_id']]];
    elseif($name==='mail_submission_dispatches')$expected['started_epoch']=[1,['started_epoch']];
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible submission indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible submission indexes. Existing data was preserved.');
    $expected=$name==='mail_submissions'?['owner_id'=>'users','draft_id'=>'mail_drafts','account_id'=>'mail_accounts']:['submission_id'=>'mail_submissions'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible submission relationships. Existing data was preserved.');
    $database=(string)query('SELECT DATABASE()')->fetchColumn();$seen=[];
    foreach($relations as $relation) {
        $target=$expected[$relation['COLUMN_NAME']]??null;
        if(!$target || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_SCHEMA']!==$database || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target || $relation['REFERENCED_COLUMN_NAME']!=='id' || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible submission relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}
function mail_submissions_migrate(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void {
        $actor=mail_accounts_owner($password);
        if(!mail_drafts_ready())throw new RuntimeException('Prepare local drafts before submission storage.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_submissions_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('This submission storage version is not supported. Existing data was preserved.');
        foreach(mail_submissions_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            mail_submissions_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!mail_submissions_ready()) {
                foreach(array_keys(mail_submissions_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized submission records exist. No data was adopted.');
                save_setting('mail_submissions_schema_version','1');
            }
            mail_submissions_audit((int)$actor['id'],'setup','Additive submission storage verified');
        });
    });
}
