<?php
declare(strict_types=1);
/* Diagnostic-only observer. Never records SQL, parameters, body, cookies or headers. */
if (PHP_SAPI !== 'cli-server') { return; }
$cwpDiagRoot = dirname(__DIR__);
$cwpDiagOutput = $cwpDiagRoot.'/evidence/request-timing.jsonl';
$GLOBALS['cwp_diag'] = ['id'=>bin2hex(random_bytes(8)), 'started_ns'=>hrtime(true),
    'started_utc'=>gmdate('Y-m-d\TH:i:s\Z'), 'queries'=>[], 'omitted_queries'=>0];
function cwp_diag_url(string $value): array {
    $parts=parse_url($value);$q=[];parse_str($parts['query']??'', $q);
    $safe=['path'=>$parts['path']??''];
    foreach(['view','audience'] as $key)if(isset($q[$key])&&is_string($q[$key])&&preg_match('/\A[a-z-]{1,80}\z/',$q[$key]))$safe[$key]=$q[$key];
    foreach(['record','agreement'] as $key)if(isset($q[$key]))$safe[$key.'_present']=true;
    return $safe;
}
function cwp_diag_query_begin(string $sql): ?array {
    if(!isset($GLOBALS['cwp_diag']))return null;
    $d=&$GLOBALS['cwp_diag'];
    if(count($d['queries'])>=12000){$d['omitted_queries']++;return null;}
    $u=strtoupper($sql);
    $category=str_contains($u,'INFORMATION_SCHEMA.KEY_COLUMN_USAGE')?'metadata.foreign_keys':
        (str_starts_with($u,'SHOW FULL COLUMNS')?'metadata.columns':
        (str_starts_with($u,'SHOW INDEX')?'metadata.indexes':
        (str_contains($u,'INFORMATION_SCHEMA.TABLES')?'metadata.tables':
        (str_contains($u,'GET_LOCK(')?'lock.acquire':
        (str_contains($u,'RELEASE_LOCK(')?'lock.release':
        (str_contains($u,'FOR UPDATE')?'data.row_lock':
        (str_starts_with($u,'SELECT')?'data.select':
        (str_starts_with($u,'INSERT')?'data.insert':
        (str_starts_with($u,'UPDATE')?'data.update':'other')))))))));
    $stack=[];$base=dirname(__DIR__).'/web/existing/';
    foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,32) as $f){
        $file=$f['file']??'';
        if(!str_starts_with($file,$base))continue;
        $stack[]=['file'=>substr($file,strlen($base)),'line'=>$f['line']??null,'function'=>$f['function']??''];
    }
    $entry=['query_sha256'=>hash('sha256',$sql),'category'=>$category,'stack'=>$stack,'start_ms'=>(hrtime(true)-$d['started_ns'])/1e6];
    $index=count($d['queries']);$d['queries'][]=$entry;
    return [$index,hrtime(true)];
}
function cwp_diag_query_end(?array $started): void {
    if($started===null)return;
    $GLOBALS['cwp_diag']['queries'][$started[0]]['prepare_execute_ms']=(hrtime(true)-$started[1])/1e6;
}
register_shutdown_function(static function()use($cwpDiagOutput):void {
    $d=$GLOBALS['cwp_diag'];$d['elapsed_ms']=(hrtime(true)-$d['started_ns'])/1e6;unset($d['started_ns']);
    $d['request']=cwp_diag_url((string)($_SERVER['REQUEST_URI']??''));
    $d['method']=$_SERVER['REQUEST_METHOD']??'';$d['status']=http_response_code();
    $action=$_POST['action']??null;
    if(is_string($action)&&preg_match('/\A[a-z_]{1,80}\z/',$action))$d['action']=$action;
    foreach(headers_list() as $header)if(str_starts_with(strtolower($header),'location:'))$d['redirect']=cwp_diag_url(trim(substr($header,9)));
    $e=error_get_last();if($e)$d['last_php_diagnostic']=['type'=>$e['type'],'file'=>basename($e['file']),'line'=>$e['line']];
    $raw=json_encode($d,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    file_put_contents($cwpDiagOutput,$raw,FILE_APPEND|LOCK_EX);
});
