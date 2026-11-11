<?php
/** Immutable authoritative gateway receipts bound to the native invoice ledger. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function gateway_receipts_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t='`'.$prefix.'gateway_receipts`';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    return ['gateway_receipts'=>'CREATE TABLE '.$t.' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ledger_entry_id BIGINT UNSIGNED NOT NULL,invoice_id INT UNSIGNED NOT NULL,job_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,invoice_sha256 '.$hash.' NOT NULL,environment VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,account_sha256 '.$hash.' NOT NULL,gateway_revision INT UNSIGNED NOT NULL,transaction_id VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,source VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,source_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,attempt_key '.$hash.' NOT NULL,transaction_status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,readback_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,readback_sha256 '.$hash.' NOT NULL,status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,created_at DATETIME NOT NULL,reviewed_at DATETIME NULL,UNIQUE ledger_entry(ledger_entry_id),UNIQUE gateway_transaction(environment,account_sha256,transaction_id),UNIQUE source_attempt(source,source_id,attempt_key),INDEX invoice_status(invoice_id,status),INDEX client_created(client_id,created_at,id),FOREIGN KEY(ledger_entry_id) REFERENCES `'.$prefix.'invoice_payments`(id) ON DELETE RESTRICT,FOREIGN KEY(invoice_id) REFERENCES `'.$prefix.'studio_invoices`(id) ON DELETE RESTRICT,FOREIGN KEY(job_id) REFERENCES `'.$prefix.'studio_jobs`(id) ON DELETE RESTRICT,FOREIGN KEY(client_id) REFERENCES `'.$prefix.'studio_clients`(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'];
}
function gateway_receipts_validate_schema():void {
    $name='gateway_receipts';$sql=gateway_receipts_schema(config()['db']['prefix'])[$name];$columns=all('SHOW FULL COLUMNS FROM '.table($name));
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Gateway receipts require InnoDB.');
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
    if(count($columns)!==count($matches))throw new RuntimeException('Incompatible gateway receipt columns. Existing data was preserved.');
    foreach($matches as $index=>$match){$column=$columns[$index];$type=preg_replace('/\b(int|bigint)\([0-9]+\)/','$1',strtolower($column['Type']));$nullable=!str_contains($match[3],'NOT NULL')&&!str_contains($match[3],'PRIMARY KEY');
        if($column['Field']!==$match[1]||$type!==strtolower($match[2])||$column['Null']!==($nullable?'YES':'NO')||(str_contains($match[3],'AUTO_INCREMENT')&&!str_contains($column['Extra'],'auto_increment')))throw new RuntimeException('Incompatible gateway receipt columns. Existing data was preserved.');
        if(str_contains($match[0],'COLLATE ascii_bin')&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible gateway receipt identifiers.');
        if(str_contains($match[0],'COLLATE utf8mb4_bin')&&$column['Collation']!=='utf8mb4_bin')throw new RuntimeException('Incompatible gateway receipt readback.');
    }
    $expected=['PRIMARY'=>['id'],'gateway_transaction'=>['environment','account_sha256','transaction_id'],'ledger_entry'=>['ledger_entry_id'],'source_attempt'=>['source','source_id','attempt_key']];
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $row)if((int)$row['Non_unique']===0)$actual[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];
    foreach($actual as &$fields){ksort($fields);$fields=array_values($fields);}unset($fields);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible gateway receipt unique keys. Existing data was preserved.');
    $indexes=[];foreach(all('SHOW INDEX FROM '.table($name)) as $row)$indexes[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];
    foreach($indexes as &$fields){ksort($fields);$fields=array_values($fields);}unset($fields);
    foreach(['invoice_status'=>['invoice_id','status'],'client_created'=>['client_id','created_at','id']] as $key=>$fields)if(($indexes[$key]??null)!==$fields)throw new RuntimeException('Incompatible gateway receipt indexes. Existing data was preserved.');
    $expectedForeign=['ledger_entry_id'=>['invoice_payments','id'],'invoice_id'=>['studio_invoices','id'],'job_id'=>['studio_jobs','id'],'client_id'=>['studio_clients','id']];$seen=[];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    foreach($relations as $relation){$link=$expectedForeign[$relation['COLUMN_NAME']]??null;if(!$link||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name']||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$link[0]||$relation['REFERENCED_COLUMN_NAME']!==$link[1]||!in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible gateway receipt relationships. Existing data was preserved.');$seen[$relation['COLUMN_NAME']]=true;}
    ksort($seen);ksort($expectedForeign);if(array_keys($seen)!==array_keys($expectedForeign))throw new RuntimeException('Incompatible gateway receipt relationships. Existing data was preserved.');
}
