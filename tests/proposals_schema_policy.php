<?php
/** Proposal schema metadata policy with synthetic metadata; no database/network. */
declare(strict_types=1);
define('CWP_BOOT',true);
require __DIR__.'/../site/database/schema/proposals_schema.php';
require __DIR__.'/../site/database/schema/proposals_validate_schema.php';
function config():array{return ['db'=>['prefix'=>'fixture_']];}
function table(string $n):string{return '`fixture_'.$n.'`';}
function one(string $sql,array $params=[]):?array{return ['ENGINE'=>$GLOBALS['engine']];}
function all(string $sql,array $params=[]):array{return str_starts_with($sql,'SHOW FULL COLUMNS')?$GLOBALS['columns']:$GLOBALS['indexes'];}
$checks=[];
function ps_ok(string $name,bool $ok):void{global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function ps_deny(string $name,callable $work):void{$denied=false;try{$work();}catch(RuntimeException $e){$denied=str_contains($e->getMessage(),'Existing data was preserved');}ps_ok($name,$denied);}
try{
 $schema=proposals_schema('fixture_');
 foreach($schema as $name=>$sql){
  $engine='InnoDB';$columns=[];$indexes=[];
  preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|MEDIUMTEXT|LONGTEXT|TEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
  foreach($matches as $m)$columns[]=['Field'=>$m[1],'Type'=>strtolower($m[2]),'Null'=>(str_contains($m[3],'NOT NULL')||str_contains($m[3],'PRIMARY KEY'))?'NO':'YES','Extra'=>str_contains($m[3],'AUTO_INCREMENT')?'auto_increment':'','Collation'=>str_contains($m[3],'CHARACTER SET ascii')?'ascii_bin':(str_contains($m[2],'CHAR')||str_contains($m[2],'TEXT')?'utf8mb4_unicode_ci':null)];
  $indexes[]=['Key_name'=>'PRIMARY','Seq_in_index'=>1,'Non_unique'=>0,'Column_name'=>'id','Sub_part'=>null,'Index_type'=>'BTREE'];
  preg_match_all('/(UNIQUE|INDEX) ([a-z_]+)\(([^)]+)\)/',$sql,$keys,PREG_SET_ORDER);
  foreach($keys as $key)foreach(explode(',',$key[3]) as $i=>$column)$indexes[]=['Key_name'=>$key[2],'Seq_in_index'=>$i+1,'Non_unique'=>$key[1]==='UNIQUE'?0:1,'Column_name'=>$column,'Sub_part'=>null,'Index_type'=>'BTREE'];
  if($name==='proposals')$indexes[]=['Key_name'=>'request_key','Seq_in_index'=>1,'Non_unique'=>0,'Column_name'=>'request_key','Sub_part'=>null,'Index_type'=>'BTREE'];
  $savedColumns=$columns;$savedIndexes=$indexes;proposals_validate_schema($name,$sql);ps_ok($name.' accepts exact declared columns and ordinary lookup indexes',true);
  $engine='MyISAM';ps_deny($name.' rejects wrong storage engine',fn()=>proposals_validate_schema($name,$sql));$engine='InnoDB';
  array_pop($columns);ps_deny($name.' rejects missing column',fn()=>proposals_validate_schema($name,$sql));$columns=$savedColumns;
  $columns[0]['Null']='YES';ps_deny($name.' rejects nullable primary key',fn()=>proposals_validate_schema($name,$sql));$columns=$savedColumns;
  $columns[0]['Type']='text';ps_deny($name.' rejects wrong column type',fn()=>proposals_validate_schema($name,$sql));$columns=$savedColumns;
  $columns[0]['Extra']=str_contains($columns[0]['Extra'],'auto_increment')?'':'auto_increment';ps_deny($name.' rejects wrong auto increment policy',fn()=>proposals_validate_schema($name,$sql));$columns=$savedColumns;
  if($columns[0]['Collation']==='ascii_bin'){$columns[0]['Collation']='ascii_general_ci';ps_deny($name.' rejects case-insensitive identity',fn()=>proposals_validate_schema($name,$sql));$columns=$savedColumns;}
  array_pop($indexes);ps_deny($name.' rejects missing index member',fn()=>proposals_validate_schema($name,$sql));$indexes=$savedIndexes;
  $indexes[0]['Non_unique']=1;ps_deny($name.' rejects nonunique primary key',fn()=>proposals_validate_schema($name,$sql));$indexes=$savedIndexes;
  $indexes[0]['Sub_part']=8;ps_deny($name.' rejects prefix index',fn()=>proposals_validate_schema($name,$sql));$indexes=$savedIndexes;
  $indexes[]=['Key_name'=>'unexpected','Seq_in_index'=>1,'Non_unique'=>1,'Column_name'=>'id','Sub_part'=>null,'Index_type'=>'BTREE'];ps_deny($name.' rejects unknown index',fn()=>proposals_validate_schema($name,$sql));$indexes=$savedIndexes;
  if($name==='proposal_versions'){foreach($columns as &$column)if($column['Field']==='deliverables')$column['Type']='text';unset($column);ps_deny('MEDIUMTEXT must not silently narrow to TEXT',fn()=>proposals_validate_schema($name,$sql));}
 }
}catch(Throwable $e){$failure=$e->getMessage();}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual schema validator with synthetic MySQL metadata, not database migration','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;exit(isset($failure)?1:0);
