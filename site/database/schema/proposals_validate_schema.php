<?php
/** Validate proposal tables without confusing lookup indexes with uniqueness. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
function proposals_validate_schema(string $name,string $sql): void {
    $expected=match($name){
        'proposals'=>['PRIMARY'=>[0,['id']],'request_key'=>[0,['request_key']],'proposals_client'=>[1,['client_id','status']],'proposals_status'=>[1,['status','created_at']]],
        'proposal_versions'=>['PRIMARY'=>[0,['id']],'proposal_version'=>[0,['proposal_id','version']],'proposal_content'=>[0,['proposal_id','content_hash']]],
        'proposal_events'=>['PRIMARY'=>[0,['id']],'proposal_history'=>[1,['proposal_id','id']]],
        'proposal_decisions'=>['PRIMARY'=>[0,['id']],'proposal_version_decision'=>[0,['proposal_id','version']]],
        default=>throw new LogicException('Unknown proposal schema.')
    };
    $engine=one('SELECT ENGINE FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]);
    if(strtolower((string)($engine['ENGINE']??''))!=='innodb')throw new RuntimeException('Proposal tables require InnoDB. Existing data was preserved.');
    preg_match_all('/(?:\(|,)\s*([a-z_][a-z0-9_]*) ((?:INT|BIGINT|TINYINT)(?: UNSIGNED)?|(?:VARCHAR|CHAR)\([0-9]+\)|DATETIME|MEDIUMTEXT|LONGTEXT|TEXT)([^,]*)/',$sql,$matches,PREG_SET_ORDER);
    $columns=all('SHOW FULL COLUMNS FROM '.table($name));
    if(!$matches||count($columns)!==count($matches))throw new RuntimeException('Incompatible proposal columns. Existing data was preserved.');
    foreach($matches as $i=>$m){
        $actual=$columns[$i];$type=preg_replace('/\b(tinyint|int|bigint)\([0-9]+\)/','$1',strtolower($actual['Type']));
        $nullable=!str_contains($m[3],'NOT NULL')&&!str_contains($m[3],'PRIMARY KEY');
        $auto=str_contains($m[3],'AUTO_INCREMENT');
        if($actual['Field']!==$m[1]||$type!==strtolower($m[2])||$actual['Null']!==($nullable?'YES':'NO')||str_contains($actual['Extra'],'auto_increment')!==$auto
            ||(str_contains($m[3],'CHARACTER SET ascii')&&$actual['Collation']!=='ascii_bin'))throw new RuntimeException('Incompatible proposal column definition. Existing data was preserved.');
    }
    $indexes=[];
    foreach(all('SHOW INDEX FROM '.table($name)) as $item){
        $key=(string)$item['Key_name'];$seq=(int)$item['Seq_in_index'];$unique=(int)$item['Non_unique'];
        if($seq<1||!in_array($unique,[0,1],true)||($item['Sub_part']??null)!==null||strtoupper((string)($item['Index_type']??''))!=='BTREE'
            ||(isset($indexes[$key])&&$indexes[$key][0]!==$unique)||isset($indexes[$key][1][$seq]))throw new RuntimeException('Incompatible proposal indexes. Existing data was preserved.');
        $indexes[$key][0]=$unique;$indexes[$key][1][$seq]=$item['Column_name'];
    }
    foreach($indexes as &$index){ksort($index[1]);$index[1]=array_values($index[1]);}unset($index);
    ksort($indexes);ksort($expected);
    if($indexes!==$expected)throw new RuntimeException('Incompatible proposal indexes. Existing data was preserved.');
}
