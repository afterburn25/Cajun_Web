<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_migrate(): void {
 require_admin();$name='cwp-studio-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
 if((int)query('SELECT GET_LOCK(?,5)',[$name])->fetchColumn()!==1)throw new RuntimeException('Workspace update is already running.');
 try{
  // Read the recorded marker/table family before DDL. studio_version_files
  // is established only at marker 3; a v2 site may still add it, but a v3
  // site missing that binding history must recover it instead of silently
  // recreating an empty table.
  $marker=(string)(one('SELECT value FROM '.table('settings').' WHERE name=?',['studio_schema_version'])['value']??'');
  if(!in_array($marker,['','0','1','2','3'],true))throw new RuntimeException('This workspace schema version is not supported by this installation. No changes were made.');
  $schema=studio_schema(config()['db']['prefix']);$present=[];
  foreach($schema as $key=>$sql)$present[$key]=(bool)one('SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_name=?',[config()['db']['name'],config()['db']['prefix'].$key]);
  $v2Tables=array_values(array_diff(array_keys($schema),['studio_version_files']));
  $required=$marker==='3'?array_keys($schema):($marker==='2'?$v2Tables:[]);
  foreach($required as $key)if(!$present[$key])throw new RuntimeException('Established workspace history is missing '.config()['db']['prefix'].$key.'. Recover the table instead of recreating empty records. No changes were made.');
  foreach($schema as $key=>$sql){
   if($present[$key]){$columns=all('SHOW COLUMNS FROM '.table($key));$cols=array_column($columns,'Field');preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) (?:INT|BIGINT|VARCHAR|CHAR|TINYINT|DATETIME|LONGTEXT|TEXT)/',$sql,$m);if($cols!==$m[1]||!studio_schema_compatible($key,$sql,$columns))throw new RuntimeException('An incompatible table already uses '.$key.'. No existing table was overwritten.');}
   else db()->exec($sql);
  }save_setting('studio_schema_version','3');log_event('studio.schema','Additive workspace schema 3');}
 finally{query('SELECT RELEASE_LOCK(?)',[$name]);}
}

function studio_schema_compatible(string $key,string $sql,array $columns): bool {
 $row=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$key]);if(strtolower((string)($row['ENGINE']??''))!=='innodb')return false;
 preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT|TEXT)([^,]*)/',$sql,$m,PREG_SET_ORDER);
 foreach($m as $i=>$expected){$actual=$columns[$i]??[];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower((string)($actual['Type']??'')));if($type!==strtolower($expected[2]))return false; $nullable=!str_contains($expected[3],'NOT NULL')&&!str_contains($expected[3],'PRIMARY KEY');if(($actual['Null']??'')!==($nullable?'YES':'NO'))return false;}
 $unique=[];foreach(all('SHOW INDEX FROM '.table($key)) as $index)if((int)$index['Non_unique']===0)$unique[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];
 $expected=$key==='studio_clients'?['PRIMARY'=>['id'],'email'=>['email']]:($key==='studio_versions'?['PRIMARY'=>['id'],'version_lookup'=>['job_id','kind','version_no']]:($key==='studio_invoices'?['PRIMARY'=>['id'],'reference'=>['reference']]:($key==='studio_version_files'?['PRIMARY'=>['id'],'version_binding'=>['version_id'],'file_binding'=>['file_id']]:['PRIMARY'=>['id']])));
 foreach($expected as $name=>$fields){if(!isset($unique[$name]))return false;ksort($unique[$name]);if(array_values($unique[$name])!==$fields)return false;}return true;
}
