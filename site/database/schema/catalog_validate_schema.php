<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function catalog_validate_schema(string $name,string $sql,?array $requiredKeys=null): void {
    $cols=all('SHOW FULL COLUMNS FROM '.table($name));$engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower($engine['ENGINE']??'')!=='innodb')throw new RuntimeException('Catalog tables require InnoDB.');
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|LONGTEXT|TEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
    if(count($cols)!==count($matches))throw new RuntimeException('Incompatible catalog columns. Existing data was preserved.');
    foreach($matches as $i=>$m){$c=$cols[$i];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($c['Type']));$nullable=!str_contains($m[3],'NOT NULL')&&!str_contains($m[3],'PRIMARY KEY');
        if($c['Field']!==$m[1]||$type!==strtolower($m[2])||$c['Null']!==($nullable?'YES':'NO')||(str_contains($m[3],'AUTO_INCREMENT')&&!str_contains($c['Extra'],'auto_increment')))throw new RuntimeException('Incompatible '.$name.' columns. Existing data was preserved.');
        if(in_array($c['Field'],['request_key','request_hash'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible invoice request identifiers.');
    }
    $unique=[];foreach(all('SHOW INDEX FROM '.table($name)) as $k)if((int)$k['Non_unique']===0)$unique[$k['Key_name']][(int)$k['Seq_in_index']]=$k['Column_name'];
    $expected=['PRIMARY'=>['id']]+($requiredKeys??match($name){'catalog_products'=>['sku'=>['sku']],'catalog_revisions'=>['product_revision'=>['product_id','revision']],default=>['invoice_id'=>['invoice_id'],'request_key'=>['request_key']]});
    foreach($expected as $key=>$fields){$actual=$unique[$key]??[];ksort($actual);if(array_values($actual)!==$fields)throw new RuntimeException('Incompatible catalog unique keys.');}
}
