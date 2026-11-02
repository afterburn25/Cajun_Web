<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function mail_drafts_validate_schema(string $name,string $sql):void {
    $unique=$name==='mail_draft_revisions'?['draft_revision'=>['draft_id','revision']]:[];
    catalog_validate_schema($name,$sql,$unique);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        $collation=match($column['Field']) {
            'id','account_id','draft_id','status','create_fingerprint','to_json','cc_json','bcc_json','action'=>'ascii_bin',
            'subject','body','body_html'=>'utf8mb4_unicode_ci',
            default=>null,
        };
        if($column['Collation']!==$collation || $column['Default']!==null || $column['Extra']!=='') throw new RuntimeException('Incompatible draft columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']],'account_id'=>[1,['account_id']]];
    if($name==='mail_drafts')$expected['owner_drafts']=[1,['owner_id','account_id','status','updated_at','id']];
    else {$expected['draft_revision']=[0,['draft_id','revision']];$expected['actor_id']=[1,['actor_id']];}
    $actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index) {
        if($index['Sub_part']!==null || $index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible draft indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible draft indexes. Existing data was preserved.');
    $expected=$name==='mail_drafts'?['owner_id'=>'users','account_id'=>'mail_accounts']:['draft_id'=>'mail_drafts','account_id'=>'mail_accounts','actor_id'=>'users'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible draft relationships. Existing data was preserved.');
    $database=(string)query('SELECT DATABASE()')->fetchColumn();$seen=[];
    foreach($relations as $relation) {
        $e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e || isset($seen[$relation['COLUMN_NAME']]) || $relation['REFERENCED_TABLE_SCHEMA']!==$database || $relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e || $relation['REFERENCED_COLUMN_NAME']!=='id' || !in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || !in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible draft relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}
function mail_drafts_migrate(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void {
        $actor=mail_accounts_owner($password);
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_drafts_schema_version'])['value']??'';
        if(!in_array($version,['','1','2'],true))throw new RuntimeException('This draft storage version is not supported. Existing data was preserved.');
        foreach(mail_drafts_schema(config()['db']['prefix']) as $name=>$sql) {
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            elseif($name==='mail_drafts' && !one("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='body_html'",[config()['db']['prefix'].$name])) {
                db()->exec('ALTER TABLE '.table($name).' ADD COLUMN body_html MEDIUMTEXT NULL AFTER body');
            }
            mail_drafts_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor,$version):void {
            if(!mail_drafts_ready()) {
                // A marked version-1 install upgrades in place; only unmarked
                // storage must prove it is empty before adoption.
                if($version==='')foreach(array_keys(mail_drafts_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized draft records exist. No data was adopted.');
                save_setting('mail_drafts_schema_version','1');
            }
            mail_drafts_audit((int)$actor['id'],'setup','Additive local draft storage verified');
        });
    });
}
