<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

function project_progress_validate_schema(string $name): void
{
    $columns=[
        'studio_progress_events'=>[
            ['id','char(32)','NO','ascii_bin',null,''],['job_id','int unsigned','NO',null,null,''],
            ['client_id','int unsigned','NO',null,null,''],['staff_id','int unsigned','NO',null,null,''],
            ['revision','int unsigned','NO',null,null,''],['kind','varchar(20)','NO','ascii_bin',null,''],
            ['created_at','datetime','NO',null,null,''],['event_json','longtext','NO','utf8mb4_bin',null,''],
            ['event_sha256','char(64)','NO','ascii_bin',null,''],
        ],
        'studio_progress_requests'=>[
            ['id','char(32)','NO','ascii_bin',null,''],['input_json','longtext','NO','utf8mb4_bin',null,''],
            ['input_sha256','char(64)','NO','ascii_bin',null,''],
        ],
        'studio_progress_heads'=>[
            ['id','int unsigned','NO',null,null,''],['event_id','char(32)','NO','ascii_bin',null,''],
            ['event_sha256','char(64)','NO','ascii_bin',null,''],['revision','int unsigned','NO',null,null,''],
        ],
    ];
    $indexes=[
        'studio_progress_events'=>['PRIMARY'=>[0,['id']],'event_job'=>[0,['id','job_id']],'job_revision'=>[0,['job_id','revision']],'job_client'=>[1,['job_id','client_id']],'client_updates'=>[1,['client_id','created_at','id']],'staff_id'=>[1,['staff_id']]],
        'studio_progress_requests'=>['PRIMARY'=>[0,['id']]],
        'studio_progress_heads'=>['PRIMARY'=>[0,['id']],'head_event'=>[0,['event_id']],'event_job'=>[1,['event_id','id']]],
    ];
    $relations=[
        'studio_progress_events'=>[
            [['job_id','client_id'],'studio_jobs',['id','client_id']],
            [['client_id'],'studio_clients',['id']],
            [['staff_id'],'users',['id']],
        ],
        'studio_progress_requests'=>[[['id'],'studio_progress_events',['id']]],
        'studio_progress_heads'=>[
            [['id'],'studio_jobs',['id']],
            [['event_id','id'],'studio_progress_events',['id','job_id']],
        ],
    ];
    if(!isset($columns[$name]))throw new LogicException('Unknown project progress table.');
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower((string)($engine['ENGINE']??''))!=='innodb')throw new RuntimeException('Project progress requires InnoDB. Existing data was preserved.');
    $actual=[];
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $column)$actual[]=[
        (string)$column['Field'],preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower((string)$column['Type'])),
        (string)$column['Null'],$column['Collation'],$column['Default'],(string)$column['Extra'],
    ];
    if($actual!==$columns[$name])throw new RuntimeException('Incompatible project progress columns. Existing data was preserved.');
    $actualIndexes=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $item){
        $key=(string)$item['Key_name'];$seq=(int)$item['Seq_in_index'];$unique=(int)$item['Non_unique'];
        if($seq<1||!in_array($unique,[0,1],true)||($item['Sub_part']??null)!==null||strtoupper((string)($item['Index_type']??''))!=='BTREE'
            ||(isset($actualIndexes[$key])&&$actualIndexes[$key][0]!==$unique)||isset($actualIndexes[$key][1][$seq]))throw new RuntimeException('Incompatible project progress indexes. Existing data was preserved.');
        $actualIndexes[$key][0]=$unique;$actualIndexes[$key][1][$seq]=(string)$item['Column_name'];
    }
    foreach($actualIndexes as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    $expectedIndexes=$indexes[$name];ksort($actualIndexes);ksort($expectedIndexes);
    if($actualIndexes!==$expectedIndexes)throw new RuntimeException('Incompatible project progress indexes. Existing data was preserved.');
    $database=(string)query('SELECT DATABASE()')->fetchColumn();
    $rows=all('SELECT k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION',[config()['db']['prefix'].$name]);
    $actualRelations=[];
    foreach($rows as $relation){
        $constraint=(string)$relation['CONSTRAINT_NAME'];$position=(int)$relation['ORDINAL_POSITION'];
        if($constraint===''||$position<1||(string)$relation['REFERENCED_TABLE_SCHEMA']!==$database
            ||!in_array((string)$relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)
            ||!in_array((string)$relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true)
            ||isset($actualRelations[$constraint]['local'][$position]))throw new RuntimeException('Incompatible project progress relationships. Existing data was preserved.');
        $actualRelations[$constraint]['local'][$position]=(string)$relation['COLUMN_NAME'];
        $actualRelations[$constraint]['remote'][$position]=(string)$relation['REFERENCED_COLUMN_NAME'];
        $target=(string)$relation['REFERENCED_TABLE_NAME'];
        if(isset($actualRelations[$constraint]['target'])&&$actualRelations[$constraint]['target']!==$target)throw new RuntimeException('Incompatible project progress relationships. Existing data was preserved.');
        $actualRelations[$constraint]['target']=$target;
    }
    $actual=[];
    foreach($actualRelations as $relation){ksort($relation['local']);ksort($relation['remote']);$actual[]=[array_values($relation['local']),$relation['target'],array_values($relation['remote'])];}
    $expected=array_map(fn(array $relation):array=>[$relation[0],config()['db']['prefix'].$relation[1],$relation[2]],$relations[$name]);
    $order=static fn(array $left,array $right):int=>strcmp(json_encode($left,JSON_THROW_ON_ERROR),json_encode($right,JSON_THROW_ON_ERROR));
    usort($actual,$order);usort($expected,$order);
    if($actual!==$expected)throw new RuntimeException('Incompatible project progress relationships. Existing data was preserved.');
}
function project_progress_validate_workspace_identity(): void
{
    $indexes=[];
    foreach(all('SHOW INDEX FROM '.table('studio_jobs')) as $item){
        if((string)$item['Key_name']!=='project_progress_owner')continue;
        $position=(int)$item['Seq_in_index'];
        if((int)$item['Non_unique']!==0||$position<1||($item['Sub_part']??null)!==null
            ||strtoupper((string)($item['Index_type']??''))!=='BTREE'||isset($indexes[$position]))throw new RuntimeException('Incompatible project progress workspace identity. Existing data was preserved.');
        $indexes[$position]=(string)$item['Column_name'];
    }
    ksort($indexes);
    if(array_values($indexes)!==['id','client_id'])throw new RuntimeException('Incompatible project progress workspace identity. Existing data was preserved.');
}
