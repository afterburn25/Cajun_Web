<?php
/** Hosting checkout payment intents and append-only attempts. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

function hosting_payments_schema(string $prefix):array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=static fn(string $name):string=>'`'.$prefix.$name.'`';$id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';
    $end=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'hosting_payment_charges'=>'CREATE TABLE '.$t('hosting_payment_charges').' (request_id '.$id.' PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL UNIQUE,client_id INT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,invoice_sha256 '.$hash.' NOT NULL,state VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,current_attempt_key '.$hash.' NOT NULL,environment VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,account_sha256 '.$hash.' NOT NULL,gateway_revision INT UNSIGNED NOT NULL,transaction_id VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,receipt_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE receipt(receipt_id),INDEX client_state(client_id,state),FOREIGN KEY(request_id) REFERENCES '.$t('hosting_checkout_results').'(id) ON DELETE RESTRICT,FOREIGN KEY(invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT,FOREIGN KEY(client_id) REFERENCES '.$t('studio_clients').'(id) ON DELETE RESTRICT,FOREIGN KEY(receipt_id) REFERENCES '.$t('gateway_receipts').'(id) ON DELETE RESTRICT)'.$end,
        'hosting_payment_attempts'=>'CREATE TABLE '.$t('hosting_payment_attempts').' (attempt_key '.$hash.' PRIMARY KEY,request_id '.$id.' NOT NULL,invoice_id INT UNSIGNED NOT NULL,client_id INT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,environment VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,account_sha256 '.$hash.' NOT NULL,gateway_revision INT UNSIGNED NOT NULL,state VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,transaction_id VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE gateway_transaction(environment,account_sha256,transaction_id),INDEX request_history(request_id,created_at,attempt_key),INDEX invoice_state(invoice_id,state),FOREIGN KEY(request_id) REFERENCES '.$t('hosting_payment_charges').'(request_id) ON DELETE RESTRICT,FOREIGN KEY(invoice_id) REFERENCES '.$t('studio_invoices').'(id) ON DELETE RESTRICT,FOREIGN KEY(client_id) REFERENCES '.$t('studio_clients').'(id) ON DELETE RESTRICT)'.$end,
        'hosting_payment_events'=>'CREATE TABLE '.$t('hosting_payment_events').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id '.$id.' NOT NULL,attempt_key '.$hash.' NOT NULL,event VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,detail_sha256 '.$hash.' NOT NULL,created_at DATETIME NOT NULL,INDEX request_history(request_id,id),FOREIGN KEY(request_id) REFERENCES '.$t('hosting_payment_charges').'(request_id) ON DELETE RESTRICT,FOREIGN KEY(attempt_key) REFERENCES '.$t('hosting_payment_attempts').'(attempt_key) ON DELETE RESTRICT)'.$end,
    ];
}
function hosting_payments_unique_keys(string $name):array {
    return match($name){
        'hosting_payment_charges'=>['PRIMARY'=>['request_id'],'invoice_id'=>['invoice_id'],'receipt'=>['receipt_id']],
        'hosting_payment_attempts'=>['PRIMARY'=>['attempt_key'],'gateway_transaction'=>['environment','account_sha256','transaction_id']],
        'hosting_payment_events'=>['PRIMARY'=>['id']],
        default=>throw new LogicException('Unknown hosting payment table.')
    };
}
function hosting_payments_foreign_keys(string $name):array {
    return match($name){
        'hosting_payment_charges'=>['request_id'=>['hosting_checkout_results','id'],'invoice_id'=>['studio_invoices','id'],'client_id'=>['studio_clients','id'],'receipt_id'=>['gateway_receipts','id']],
        'hosting_payment_attempts'=>['request_id'=>['hosting_payment_charges','request_id'],'invoice_id'=>['studio_invoices','id'],'client_id'=>['studio_clients','id']],
        'hosting_payment_events'=>['request_id'=>['hosting_payment_charges','request_id'],'attempt_key'=>['hosting_payment_attempts','attempt_key']],
        default=>throw new LogicException('Unknown hosting payment table.')
    };
}
function hosting_payments_secondary_keys(string $name):array {
    return match($name){
        'hosting_payment_charges'=>['client_state'=>['client_id','state']],
        'hosting_payment_attempts'=>['request_history'=>['request_id','created_at','attempt_key'],'invoice_state'=>['invoice_id','state']],
        'hosting_payment_events'=>['request_history'=>['request_id','id']],
        default=>throw new LogicException('Unknown hosting payment table.')
    };
}
function hosting_payments_validate_schema(string $name,string $sql):void {
    $expected=hosting_payments_unique_keys($name);$columns=all('SHOW FULL COLUMNS FROM '.table($name));
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Hosting payment tables require InnoDB.');
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
    if(count($columns)!==count($matches))throw new RuntimeException('Incompatible hosting payment columns. Existing data was preserved.');
    foreach($matches as $index=>$match){$column=$columns[$index];$type=preg_replace('/\b(int|bigint)\([0-9]+\)/','$1',strtolower($column['Type']));$nullable=!str_contains($match[3],'NOT NULL')&&!str_contains($match[3],'PRIMARY KEY');
        if($column['Field']!==$match[1]||$type!==strtolower($match[2])||$column['Null']!==($nullable?'YES':'NO')||(str_contains($match[3],'AUTO_INCREMENT')&&!str_contains($column['Extra'],'auto_increment')))throw new RuntimeException('Incompatible hosting payment columns. Existing data was preserved.');
        if(str_contains($match[0],'COLLATE ascii_bin')&&$column['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible hosting payment identifiers.');
        if(str_contains($match[0],'COLLATE utf8mb4_bin')&&$column['Collation']!=='utf8mb4_bin')throw new RuntimeException('Incompatible hosting payment documents.');
    }
    $actual=[];foreach(all('SHOW INDEX FROM '.table($name)) as $row)if((int)$row['Non_unique']===0)$actual[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];
    foreach($actual as &$fields){ksort($fields);$fields=array_values($fields);}unset($fields);ksort($actual);ksort($expected);
    if($actual!==$expected)throw new RuntimeException('Incompatible hosting payment unique keys. Existing data was preserved.');
    $indexes=[];foreach(all('SHOW INDEX FROM '.table($name)) as $row)$indexes[$row['Key_name']][(int)$row['Seq_in_index']]=$row['Column_name'];
    foreach($indexes as &$fields){ksort($fields);$fields=array_values($fields);}unset($fields);
    foreach(hosting_payments_secondary_keys($name) as $key=>$fields)if(($indexes[$key]??null)!==$fields)throw new RuntimeException('Incompatible hosting payment indexes. Existing data was preserved.');
    $expectedForeign=hosting_payments_foreign_keys($name);$seen=[];
    $relations=all('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_SCHEMA,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[config()['db']['prefix'].$name]);
    foreach($relations as $relation){$link=$expectedForeign[$relation['COLUMN_NAME']]??null;if(!$link||isset($seen[$relation['COLUMN_NAME']])||$relation['REFERENCED_TABLE_SCHEMA']!==config()['db']['name']||$relation['REFERENCED_TABLE_NAME']!==config()['db']['prefix'].$link[0]||$relation['REFERENCED_COLUMN_NAME']!==$link[1]||!in_array($relation['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($relation['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('Incompatible hosting payment relationships. Existing data was preserved.');$seen[$relation['COLUMN_NAME']]=true;}
    ksort($seen);ksort($expectedForeign);if(array_keys($seen)!==array_keys($expectedForeign))throw new RuntimeException('Incompatible hosting payment relationships. Existing data was preserved.');
}
