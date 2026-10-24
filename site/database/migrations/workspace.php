<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function studio_migrate(): void {
 require_admin();$name='cwp-studio-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);
 if((int)query('SELECT GET_LOCK(?,5)',[$name])->fetchColumn()!==1)throw new RuntimeException('Workspace update is already running.');
 try{foreach(studio_schema(config()['db']['prefix']) as $key=>$sql){
  $exists=one('SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_name=?',[config()['db']['name'],config()['db']['prefix'].$key]);
  if($exists){$columns=all('SHOW COLUMNS FROM '.table($key));$cols=array_column($columns,'Field');preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) (?:INT|BIGINT|VARCHAR|CHAR|TINYINT|DATETIME|LONGTEXT|TEXT)/',$sql,$m);if($cols!==$m[1]||!studio_schema_compatible($key,$sql,$columns))throw new RuntimeException('An incompatible table already uses '.$key.'. No existing table was overwritten.');}
  else db()->exec($sql);
 }
 if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=? AND table_name=?',[config()['db']['name'],config()['db']['prefix'].'audit'])){
  foreach(['action'=>"action VARCHAR(80) COLLATE utf8mb4_bin NOT NULL",'detail'=>"detail VARCHAR(400) COLLATE utf8mb4_bin NOT NULL"] as $column=>$definition){
   $collation=one('SELECT COLLATION_NAME FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?',[config()['db']['name'],config()['db']['prefix'].'audit',$column]);
   if(($collation['COLLATION_NAME']??'')!=='utf8mb4_bin')db()->exec('ALTER TABLE '.table('audit').' MODIFY '.$definition);
  }
  if(!one('SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=? AND table_name=? AND index_name=?',[config()['db']['name'],config()['db']['prefix'].'audit','audit_action']))db()->exec('ALTER TABLE '.table('audit').' ADD INDEX audit_action(action)');
  if(!one('SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema=? AND table_name=? AND index_name=?',[config()['db']['name'],config()['db']['prefix'].'audit','audit_detail']))db()->exec('ALTER TABLE '.table('audit').' ADD INDEX audit_detail(detail(191))');
 }
 save_setting('studio_schema_version','2');log_event('studio.schema','Additive workspace schema 2');}
 finally{query('SELECT RELEASE_LOCK(?)',[$name]);}
}

function studio_schema_compatible(string $key,string $sql,array $columns): bool {
 $row=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$key]);if(strtolower((string)($row['ENGINE']??''))!=='innodb')return false;
 preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT|TEXT)([^,]*)/',$sql,$m,PREG_SET_ORDER);
 foreach($m as $i=>$expected){$actual=$columns[$i]??[];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower((string)($actual['Type']??'')));if($type!==strtolower($expected[2]))return false; $nullable=!str_contains($expected[3],'NOT NULL')&&!str_contains($expected[3],'PRIMARY KEY');if(($actual['Null']??'')!==($nullable?'YES':'NO'))return false;}
 $unique=[];foreach(all('SHOW INDEX FROM '.table($key)) as $index)if((int)$index['Non_unique']===0)$unique[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];
 $expected=$key==='studio_clients'?['PRIMARY'=>['id'],'email'=>['email']]:($key==='studio_versions'?['PRIMARY'=>['id'],'version_lookup'=>['job_id','kind','version_no']]:($key==='studio_invoices'?['PRIMARY'=>['id'],'reference'=>['reference']]:['PRIMARY'=>['id']]));
 foreach($expected as $name=>$fields){if(!isset($unique[$name]))return false;ksort($unique[$name]);if(array_values($unique[$name])!==$fields)return false;}return true;
}
