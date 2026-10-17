<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function mail_notices_validate_schema(string $name): void {
    $identity=['account_id'=>['char(32)','NO'],'account_revision'=>['int unsigned','NO'],'folder'=>['varchar(64)','NO'],'uidvalidity'=>['varchar(10)','NO']];
    $columns=match($name) {
        'mail_notice_windows'=>$identity+['request_id'=>['char(32)','NO'],'checked_at'=>['datetime','NO']],
        'mail_notice_items'=>['handle'=>['char(32)','NO']]+$identity+['uid'=>['varchar(10)','NO'],'slot'=>['int unsigned','NO'],'server_seen'=>['int unsigned','NO'],'flags_observed_at'=>['datetime','NO']],
        'mail_notice_states'=>['handle'=>['char(32)','NO'],'actor_id'=>['int unsigned','NO'],'opened_at'=>['datetime','YES'],'dismissed_at'=>['datetime','YES']],
        default=>throw new LogicException('Unknown notice table.'),
    };
    $required=match($name) {
        'mail_notice_windows'=>['PRIMARY'=>[1=>'account_id']],
        'mail_notice_items'=>['PRIMARY'=>[1=>'handle'],'message_identity'=>[1=>'account_id',2=>'account_revision',3=>'folder',4=>'uidvalidity',5=>'uid']],
        'mail_notice_states'=>['PRIMARY'=>[1=>'handle',2=>'actor_id']],
    };
    $actual=[];foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column) {
        if($column['Extra']!=='')throw new RuntimeException('Incompatible email notice columns. Existing data was preserved.');
        $actual[$column['Field']]=[preg_replace('/\b(int|bigint)\([0-9]+\)/','$1',strtolower($column['Type'])),$column['Null']];
    }
    $keys=[];foreach(all('SHOW INDEX FROM '.table($name)) as $key)if((int)$key['Non_unique']===0){if($key['Sub_part']!==null)throw new RuntimeException('Partial email notice identity index is unsupported.');$keys[$key['Key_name']][(int)$key['Seq_in_index']]=$key['Column_name'];}
    foreach($keys as &$fields)ksort($fields);unset($fields);ksort($keys);ksort($required);
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower($engine['ENGINE']??'')!=='innodb'||$columns!==$actual||$keys!==$required)throw new RuntimeException('Incompatible email notice storage. Existing data was preserved.');
}
function mail_notices_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=mail_accounts_owner($password);
        if(!mail_inbox_ready()||!mail_messages_ready())throw new RuntimeException('Prepare inbox and message reading before email notices.');
        $expected=[
            'mail_notice_windows'=>['account_id'=>['mail_accounts','id','RESTRICT'],'request_id'=>['mail_inbox_reads','id','RESTRICT']],
            'mail_notice_items'=>['account_id'=>['mail_notice_windows','account_id','RESTRICT']],
            'mail_notice_states'=>['handle'=>['mail_notice_items','handle','CASCADE'],'actor_id'=>['users','id','RESTRICT']],
        ];
        foreach(mail_notices_schema(config()['db']['prefix']) as $name=>$sql) {
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])) {
                if(in_array($name,['mail_notice_windows','mail_notice_items'],true) && strtolower((string)(one('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[config()['db']['prefix'].$name,'folder'])['COLUMN_TYPE']??''))==='varchar(5)')
                    db()->exec('ALTER TABLE `'.config()['db']['prefix'].$name.'` MODIFY COLUMN folder VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
            } else db()->exec($sql);
            mail_notices_validate_schema($name);
            foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column)if(in_array($column['Field'],['account_id','handle','request_id','folder','uidvalidity','uid'],true)&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible email notice identifiers. Existing data was preserved.');
            $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
            if(count($relations)!==count($expected[$name]))throw new RuntimeException('Incompatible email notice relationships. Existing data was preserved.');
            $seen=[];foreach($relations as $relation) {
                $e=$expected[$name][$relation['COLUMN_NAME']]??null;
                if(!$e||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0]||$relation['REFERENCED_COLUMN_NAME']!==$e[1]||!in_array($relation['DELETE_RULE'],$e[2]==='RESTRICT'?['RESTRICT','NO ACTION']:[$e[2]],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible email notice relationships. Existing data was preserved.');
                $seen[$relation['COLUMN_NAME']]=true;
            }
        }
        staff_transaction(function()use($actor):void {
            $version=(string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['mail_notices_schema_version'])['value']??'');
            if($version==='') {
                foreach(array_keys(mail_notices_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized email notice records exist. No data was adopted.');
            } elseif($version!=='1'&&$version!=='2') {
                throw new RuntimeException('Unrecognized email notice storage version. Existing data was preserved.');
            }
            save_setting('mail_notices_schema_version','2');
            mail_accounts_audit((int)$actor['id'],'notice_setup','Additive email notice storage verified');
        });
    });
}
