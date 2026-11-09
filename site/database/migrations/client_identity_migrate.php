<?php
/** Reviewed additive migration for public client identity. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/database/schema/client_identity_schema.php';

function client_identity_schema_error(): never {
    throw new RuntimeException('Incompatible public client identity storage. Existing data was preserved.');
}

function client_identity_validate_table(string $name): void {
    $expected=[
        'studio_client_registrations'=>[
            ['client_id','int unsigned','NO',null],
            ['email_fingerprint','char(64)','NO','ascii_bin'],
            ['verified_at','bigint unsigned','YES',null],
            ['created_at','bigint unsigned','NO',null],
        ],
        'studio_client_tokens'=>[
            ['token_digest','char(64)','NO','ascii_bin'],
            ['client_id','int unsigned','NO',null],
            ['purpose','varchar(10)','NO','ascii_bin'],
            ['email_fingerprint','char(64)','NO','ascii_bin'],
            ['auth_version','char(32)','NO','ascii_bin'],
            ['resume_json','varchar(300)','NO','ascii_bin'],
            ['expires_at','bigint unsigned','NO',null],
            ['created_at','bigint unsigned','NO',null],
        ],
    ][$name]??null;
    if($expected===null)client_identity_schema_error();
    $table=config()['db']['prefix'].$name;
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$table]);
    if(strtolower((string)($engine['ENGINE']??''))!=='innodb')client_identity_schema_error();
    $columns=all('SHOW FULL COLUMNS FROM '.table($name));
    if(count($columns)!==count($expected))client_identity_schema_error();
    foreach($expected as $i=>$definition){
        [$field,$type,$null,$collation]=$definition;$column=$columns[$i];
        $actualType=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower((string)$column['Type']));
        if($column['Field']!==$field||$actualType!==$type||$column['Null']!==$null||($collation!==null&&$column['Collation']!==$collation))client_identity_schema_error();
    }
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $index)$actual[$index['Key_name']][(int)$index['Seq_in_index']]=[(int)$index['Non_unique'],$index['Column_name']];
    foreach($actual as &$parts)ksort($parts);unset($parts);
    $required=$name==='studio_client_registrations'
        ?['PRIMARY'=>[[0,'client_id']],'registration_email'=>[[0,'email_fingerprint']]]
        :['PRIMARY'=>[[0,'token_digest']],'token_client_purpose'=>[[0,'client_id'],[0,'purpose']],'token_expiry'=>[[1,'expires_at']]];
    $actualNames=array_keys($actual);$requiredNames=array_keys($required);sort($actualNames);sort($requiredNames);if($actualNames!==$requiredNames)client_identity_schema_error();
    foreach($required as $key=>$parts)if(array_values($actual[$key]??[])!==$parts)client_identity_schema_error();
    $foreign=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$table]);
    $relation=$foreign[0]??[];
    if(count($foreign)!==1||($relation['COLUMN_NAME']??'')!=='client_id'||($relation['REFERENCED_TABLE_NAME']??'')!==config()['db']['prefix'].'studio_clients'||($relation['REFERENCED_COLUMN_NAME']??'')!=='id'||!in_array($relation['DELETE_RULE']??'',['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE']??'',['RESTRICT','NO ACTION'],true))client_identity_schema_error();
}

function client_identity_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function()use($password):void {
        $actor=payments_owner($password);
        if(!studio_ready()||setting('contacts_schema_version')!=='1')throw new RuntimeException('Prepare the client workspace and contacts before public client identity.');
        $version=setting('client_identity_schema_version');
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unrecognized public client identity schema version '.$version.'. Existing data was preserved.');
        $schema=client_identity_schema(config()['db']['prefix']);$missing=[];
        foreach($schema as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))client_identity_validate_table($name);
            else $missing[$name]=$sql;
        }
        if($version==='1'&&$missing)throw new RuntimeException('Marked public client identity storage is incomplete. Existing data was preserved.');
        if($version===''&&count($missing)!==count($schema))foreach(array_keys($schema) as $name)if(!isset($missing[$name])&&(int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn()!==0)throw new RuntimeException('Unrecognized public client identity records exist. No data was adopted.');
        foreach($missing as $sql)db()->exec($sql);
        foreach(array_keys($schema) as $name)client_identity_validate_table($name);
        staff_transaction(function()use($actor,$version):void {
            if(one('SELECT c.id FROM '.table('studio_clients').' c JOIN '.table('studio_contacts').' x ON x.email=c.email LIMIT 1'))throw new RuntimeException('Primary and contact login email records overlap. Resolve the retained identity conflict before enabling public registration.');
            if($version!=='1'){
                save_setting('client_identity_schema_version','1');
                payments_audit((int)$actor['id'],'client_identity.setup','Additive public client identity schema 1; no legacy accounts adopted');
            }
        });
    });
}
