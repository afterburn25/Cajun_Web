<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function staff_validate_schema(string $name): void {
    $expected=$name==='staff_profiles'
        ? ['user_id'=>['int unsigned','NO'],'role'=>['varchar(20)','NO'],'enabled'=>['tinyint','NO'],'permissions'=>['text','NO']]
        : ['subject_id'=>['varchar(128)','NO'],'token_hash'=>['char(64)','NO'],'auth_version'=>['varchar(64)','NO'],'expires_at'=>['bigint unsigned','NO'],'consumed_at'=>['bigint unsigned','YES']];
    $row=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    $cols=all('SHOW COLUMNS FROM '.table($name));$actual=[];
    foreach ($cols as $c) $actual[$c['Field']]=[preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($c['Type'])),$c['Null']];
    $keys=[];foreach(all('SHOW INDEX FROM '.table($name)) as $k) if((int)$k['Non_unique']===0)$keys[$k['Key_name']][(int)$k['Seq_in_index']]=$k['Column_name'];
    $required=$name==='staff_profiles'?['PRIMARY'=>[1=>'user_id']]:['PRIMARY'=>[1=>'subject_id'],'token_hash'=>[1=>'token_hash']];
    if (strtolower($row['ENGINE']??'')!=='innodb'||$actual!==$expected) throw new RuntimeException('Incompatible '.$name.' table. Existing data was not replaced.');
    foreach($required as $key=>$fields)if(($keys[$key]??[])!==$fields)throw new RuntimeException('Incompatible '.$name.' keys. Existing data was not replaced.');
    if($name==='staff_invitations')foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['subject_id','token_hash','auth_version'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible invitation collation.');
}
