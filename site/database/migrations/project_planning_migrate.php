<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/database/schema/catalog_validate_schema.php';
function project_planning_validate_schema(string $name,string $sql):void {
    $events=$name==='studio_plan_events';$heads=$name==='studio_plan_heads';if(!$events&&!$heads&&$name!=='studio_plan_requests')throw new LogicException('Unknown planning table.');
    catalog_validate_schema($name,$sql,$events?['job_revision'=>['job_id','revision']]:($heads?['head_event'=>['event_id']]:[]));
    foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c){
        $collation=match($c['Field']){'id'=>$heads?null:'ascii_bin','event_id','kind','event_sha256','input_sha256','plan_sha256'=>'ascii_bin','event_json','input_json','plan_json'=>'utf8mb4_bin',default=>null};
        if($c['Extra']!==''||$c['Default']!==null||$c['Collation']!==$collation)throw new RuntimeException('Incompatible planning columns. Existing data was preserved.');
    }
    $expected=['PRIMARY'=>[0,['id']]];
    if($events)$expected+=['job_revision'=>[0,['job_id','revision']],'client_updates'=>[1,['client_id','created_at','id']],'staff_id'=>[1,['staff_id']]];
    if($heads)$expected+=['head_event'=>[0,['event_id']]];
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $index){
        if($index['Sub_part']!==null||$index['Index_type']!=='BTREE')throw new RuntimeException('Incompatible planning indexes.');
        $actual[$index['Key_name']][0]=(int)$index['Non_unique'];$actual[$index['Key_name']][1][(int)$index['Seq_in_index']]=$index['Column_name'];
    }
    foreach($actual as &$entry){ksort($entry[1]);$entry[1]=array_values($entry[1]);}unset($entry);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible planning indexes. Existing data was preserved.');
    $expected=$events?['job_id'=>['studio_jobs','id'],'client_id'=>['studio_clients','id'],'staff_id'=>['users','id']]:($heads?['id'=>['studio_jobs','id'],'event_id'=>['studio_plan_events','id']]:['id'=>['studio_plan_events','id']]);
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    if(count($relations)!==count($expected))throw new RuntimeException('Incompatible planning relationships.');$seen=[];
    foreach($relations as $relation){$e=$expected[$relation['COLUMN_NAME']]??null;
        if(!$e||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$e[0]||$relation['REFERENCED_COLUMN_NAME']!==$e[1]||!in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible planning relationships. Existing data was preserved.');
        $seen[$relation['COLUMN_NAME']]=true;
    }
}
function project_planning_migrate(#[\SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Project planning setup cannot inherit a transaction.');
    staff_locked(function()use($password):void {
        $actor=project_planning_staff(false);
        if($actor['role']!=='owner'||!staff_can('staff.manage',$actor)||!password_verify($password,$actor['password_hash']))throw new RuntimeException('A current owner password is required to prepare project planning.');
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['project_planning_schema_version'])['value']??'';
        if(!in_array($version,['','1'],true))throw new RuntimeException('Unrecognized project planning storage version. Existing data was preserved.');
        foreach(project_planning_schema(config()['db']['prefix']) as $name=>$sql){
            if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))db()->exec($sql);
            project_planning_validate_schema($name,$sql);
        }
        staff_transaction(function()use($actor):void {
            if(!project_planning_ready()){
                foreach(array_keys(project_planning_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized project planning exists. No data was adopted.');
                save_setting('project_planning_schema_version','1');
            }
            project_planning_audit((int)$actor['id'],'setup','Additive project planning storage verified');
        });
    });
}
