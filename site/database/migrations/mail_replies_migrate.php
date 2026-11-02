<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
function mail_replies_validate_schema(string $name,string $sql):void {
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c) {
        $collation=match($c['Field']){'id','source_account_id','source_read_id','status','origin_json','content_fingerprint'=>'ascii_bin','content_json'=>'utf8mb4_unicode_ci',default=>null};
        if($c['Collation']!==$collation||$c['Default']!==null||$c['Extra']!=='')throw new RuntimeException('Incompatible reply draft columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']],'source_account_id'=>[1,['source_account_id']],'source_read_id'=>[1,['source_read_id']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $i) {
        if($i['Sub_part']!==null||$i['Index_type']!=='BTREE')throw new RuntimeException('Incompatible reply draft indexes. Existing data was preserved.');
        $actual[$i['Key_name']][0]=(int)$i['Non_unique'];$actual[$i['Key_name']][1][(int)$i['Seq_in_index']]=$i['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible reply draft indexes. Existing data was preserved.');
    $expected=['id'=>'mail_drafts','source_account_id'=>'mail_accounts','source_read_id'=>'mail_message_reads'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==3)throw new RuntimeException('Incompatible reply draft relationships. Existing data was preserved.');
    $database=(string)query('SELECT DATABASE()')->fetchColumn();$seen=[];
    foreach($relations as $r){$target=$expected[$r['COLUMN_NAME']]??null;
        if(!$target||isset($seen[$r['COLUMN_NAME']])||$r['REFERENCED_TABLE_SCHEMA']!==$database||$r['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$r['REFERENCED_COLUMN_NAME']!=='id'||!in_array($r['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($r['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible reply draft relationships. Existing data was preserved.');
        $seen[$r['COLUMN_NAME']]=true;
    }
}
function mail_replies_migrate(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void{
        $actor=mail_accounts_owner($password);
        if(!mail_drafts_ready()||!mail_messages_ready())throw new RuntimeException('Prepare local drafts and message reading first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_replies_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported reply storage version. Existing data was preserved.');
        foreach(mail_replies_schema(config()['db']['prefix']) as $name=>$sql){
            $exists=one('SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[config()['db']['prefix'].$name]);
            if(!$exists){if($version==='1')throw new RuntimeException('Reply storage is incomplete. Existing data was preserved.');db()->exec($sql);}
            mail_replies_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor,$version):void{
            if($version===''){
                if((int)query('SELECT COUNT(*) FROM '.table('mail_reply_drafts'))->fetchColumn()||one('SELECT id FROM '.table('mail_drafts')." WHERE status='source' LIMIT 1"))throw new RuntimeException('Unrecognized copied drafts exist. No data was adopted.');
                save_setting('mail_replies_schema_version','1');
            }
            mail_drafts_audit((int)$actor['id'],'reply_setup','Additive protected copied-message storage verified');
        });
    });
}
