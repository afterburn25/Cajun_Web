<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function project_private_notes_validate_schema(): void
{
    $name='project_private_notes';
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower((string)($engine['ENGINE']??''))!=='innodb')throw new RuntimeException('Private project notes require InnoDB. Existing data was preserved.');
    $expected=[
        ['id','bigint unsigned','NO',null,null,'auto_increment'],
        ['project_id','int unsigned','NO',null,null,''],
        ['staff_id','int unsigned','NO',null,null,''],
        ['created_epoch','bigint unsigned','NO',null,null,''],
        ['body','text','NO','utf8mb4_bin',null,''],
    ];
    $actual=[];
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column)$actual[]=[
        (string)$column['Field'],
        preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower((string)$column['Type'])),
        (string)$column['Null'],
        $column['Collation'],
        $column['Default'],
        (string)$column['Extra'],
    ];
    if($actual!==$expected)throw new RuntimeException('Incompatible private project note columns. Existing data was preserved.');

    $indexes=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $item){
        $key=(string)$item['Key_name'];$seq=(int)$item['Seq_in_index'];$unique=(int)$item['Non_unique'];
        if($seq<1||!in_array($unique,[0,1],true)||($item['Sub_part']??null)!==null||strtoupper((string)($item['Index_type']??''))!=='BTREE'
            ||(isset($indexes[$key])&&$indexes[$key][0]!==$unique)||isset($indexes[$key][1][$seq]))throw new RuntimeException('Incompatible private project note indexes. Existing data was preserved.');
        $indexes[$key][0]=$unique;$indexes[$key][1][$seq]=(string)$item['Column_name'];
    }
    foreach($indexes as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    $expectedIndexes=['PRIMARY'=>[0,['id']],'project_notes'=>[1,['project_id','id']],'staff_id'=>[1,['staff_id']]];
    ksort($indexes);ksort($expectedIndexes);
    if($indexes!==$expectedIndexes)throw new RuntimeException('Incompatible private project note indexes. Existing data was preserved.');

    $database=(string)query('SELECT DATABASE()')->fetchColumn();
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    $expectedRelations=['project_id'=>['studio_jobs','id'],'staff_id'=>['users','id']];$seen=[];
    if(count($relations)!==count($expectedRelations))throw new RuntimeException('Incompatible private project note relationships. Existing data was preserved.');
    foreach($relations as $relation){
        $field=(string)$relation['COLUMN_NAME'];$expectedRelation=$expectedRelations[$field]??null;
        if(!$expectedRelation||isset($seen[$field])||(string)$relation['REFERENCED_TABLE_SCHEMA']!==$database
            ||(string)$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$expectedRelation[0]
            ||(string)$relation['REFERENCED_COLUMN_NAME']!==$expectedRelation[1]||!in_array((string)$relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)
            ||!in_array((string)$relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible private project note relationships. Existing data was preserved.');
        $seen[$field]=true;
    }
}
