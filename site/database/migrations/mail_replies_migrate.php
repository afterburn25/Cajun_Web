<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

require_once CWP_CODE_ROOT.'/database/schema/mail_replies_schema.php';

function mail_replies_validate_schema(string $name,string $sql):void {
    catalog_validate_schema($name,$sql,[]);
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column){
        $collation=match($column['Field']){
            'id','source_account_id','source_read_id','status','origin_json','content_fingerprint'=>'ascii_bin',
            'content_json'=>'utf8mb4_unicode_ci',default=>null,
        };
        if($column['Collation']!==$collation||$column['Default']!==null||$column['Extra']!=='')throw new RuntimeException('Incompatible reply draft columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']],'source_account_id'=>[1,['source_account_id']],'source_read_id'=>[1,['source_read_id']]];$actual=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $index){
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible reply draft indexes. Existing data was preserved.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('Incompatible reply draft indexes. Existing data was preserved.');
    $targets=['id'=>'mail_drafts','source_account_id'=>'mail_accounts','source_read_id'=>'mail_message_reads'];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($targets))throw new RuntimeException('Incompatible reply draft relationships. Existing data was preserved.');
    $database=(string)query('SELECT DATABASE()')->fetchColumn();$seen=[];
    foreach($relations as $relation){$target=$targets[$relation['COLUMN_NAME']]??null;
        if(!$target||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_SCHEMA']!==$database||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$target||$relation['REFERENCED_COLUMN_NAME']!=='id'||!in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible reply draft relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}

/** Every protected row has one exact content-free legacy placeholder and vice versa. */
function mail_replies_validate_rows():void {
    $missing=(int)query('SELECT COUNT(*) FROM '.table('mail_drafts').' d LEFT JOIN '.table('mail_reply_drafts').' r ON r.id=d.id WHERE d.status=\'source\' AND r.id IS NULL')->fetchColumn();
    $invalid=(int)query('SELECT COUNT(*) FROM '.table('mail_reply_drafts').' r LEFT JOIN '.table('mail_drafts').' d ON d.id=r.id WHERE d.id IS NULL OR d.status<>\'source\' OR d.subject<>\'Copied message draft\' OR d.body<>\'\' OR d.to_json<>\'[]\' OR d.cc_json<>\'[]\' OR d.bcc_json<>\'[]\' OR d.revision<>r.revision')->fetchColumn();
    if($missing!==0||$invalid!==0)throw new RuntimeException('Protected reply draft rows are incomplete or inconsistent. Existing data was preserved.');
}

function mail_replies_migrate(#[\SensitiveParameter] string $password):void {
    staff_locked(function()use($password):void{
        $actor=mail_accounts_owner($password);
        if(!mail_drafts_ready()||!mail_messages_ready())throw new RuntimeException('Prepare local drafts and exact message reading first.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_replies_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unsupported reply storage version. Existing data was preserved.');
        foreach(mail_replies_schema(config()['db']['prefix']) as $name=>$sql){
            $exists=one('SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[config()['db']['prefix'].$name]);
            if(!$exists){if($version==='1')throw new RuntimeException('Reply storage is incomplete. Existing data was preserved.');db()->exec($sql);}
            mail_replies_validate_schema($name,$sql);
        }
        mail_replies_validate_rows();
        staff_transaction(function()use($actor,$version):void{
            if($version===''){
                if((int)query('SELECT COUNT(*) FROM '.table('mail_reply_drafts'))->fetchColumn()||one('SELECT id FROM '.table('mail_drafts')." WHERE status='source' LIMIT 1"))throw new RuntimeException('Unrecognized copied drafts exist. No data was adopted.');
                save_setting('mail_replies_schema_version','1');
            }
            mail_drafts_audit((int)$actor['id'],'reply_setup','Additive protected copied-message storage verified');
        });
    });
}
