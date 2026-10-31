<?php
/** Real prune body, private temporary bytes and strict SQL/audit doubles. No database or network. */
declare(strict_types=1);
$source=file_get_contents(__DIR__.'/../../site/app/backups/archive.php');
// This fixture requires the reviewed declaration to remain the final function.
$offset=strpos($source,'function backups_prune(');
if($offset===false||substr_count($source,'function backups_prune(')!==1)throw new RuntimeException('Missing unique prune declaration');
$body=substr($source,$offset);
$root=sys_get_temp_dir().'/cwp-prune-'.bin2hex(random_bytes(8));mkdir($root,0700);
$GLOBALS['prune_fixture']=['root'=>$root,'mode'=>'audit','owner'=>true,'rows'=>[],'audit'=>0,'committed'=>false];
function staff_locked(callable $work):mixed{return $work();}
function backups_owner_session():array{if(!$GLOBALS['prune_fixture']['owner'])throw new RuntimeException('Synthetic owner refusal');return ['id'=>1];}
function backups_directory():string{return $GLOBALS['prune_fixture']['root'];}
function table(string $name):string{return '`prune_fixture_'.$name.'`';}
function all(string $sql,array $parameters):array{return $GLOBALS['prune_fixture']['rows'];}
function query(string $sql,array $parameters):void{$GLOBALS['prune_fixture']['rows']=array_values(array_filter($GLOBALS['prune_fixture']['rows'],fn($r)=>$r['id']!==$parameters[0]));}
function backups_audit(int $actor,string $action,string $detail):void{
    $g=&$GLOBALS['prune_fixture'];$g['audit']++;
    if($g['archive']!==null&&!is_file($g['archive']))throw new LogicException('Archive disappeared before the audit committed');
    if($g['mode']==='audit')throw new RuntimeException('Synthetic audit failure');
}
function staff_transaction(callable $work):mixed{
    $g=&$GLOBALS['prune_fixture'];$before=$g;
    try{$result=$work();if($g['mode']==='commit')throw new RuntimeException('Synthetic commit failure');$g['committed']=true;return $result;}
    catch(Throwable $failure){$g=$before;throw $failure;}
}
eval($body);
$checks=[];
function verify(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new AssertionError($name);}
function seed(string $mode,bool $owner=true):void{
    $g=&$GLOBALS['prune_fixture'];$id=str_repeat('a',32);$file=$g['root'].'/backup-'.$id.'.zip';
    $g=['root'=>$g['root'],'mode'=>$mode,'owner'=>$owner,'rows'=>[['id'=>$id,'archive_file'=>basename($file)]],'archive'=>$file,'audit'=>0,'committed'=>false];file_put_contents($file,'synthetic original archive');
}
try{
    foreach(['audit','commit'] as $mode){
        seed($mode);$before=$GLOBALS['prune_fixture'];$error=null;
        try{backups_prune();}catch(Throwable $e){$error=$e;}
        verify($mode.' failure is not reported as success',$error instanceof RuntimeException&&$error->getMessage()==='Synthetic '.$mode.' failure');
        verify($mode.' rollback retains exact records',$GLOBALS['prune_fixture']['rows']===$before['rows']&&!$GLOBALS['prune_fixture']['committed']);
        verify($mode.' rollback retains original archive bytes',is_file($before['archive'])&&file_get_contents($before['archive'])==='synthetic original archive');
    }
    seed('ok',false);$before=$GLOBALS['prune_fixture'];$error=null;
    try{backups_prune();}catch(Throwable $e){$error=$e;}
    verify('Nonowner is refused before records or files change',$error instanceof RuntimeException&&$error->getMessage()==='Synthetic owner refusal'&&$GLOBALS['prune_fixture']===$before&&is_file($before['archive']));
    seed('ok');$file=$GLOBALS['prune_fixture']['archive'];$removed=backups_prune();
    verify('Successful pruning commits one deletion and one audit',$removed===1&&$GLOBALS['prune_fixture']['committed']&&$GLOBALS['prune_fixture']['rows']===[]&&$GLOBALS['prune_fixture']['audit']===1);
    verify('Archive deletion occurs only after successful commit',!file_exists($file));
    $GLOBALS['prune_fixture']['archive']=null;$removed=backups_prune();verify('Empty pruning stays an explicit zero-result operation',$removed===0);
    seed('ok');$file=$GLOBALS['prune_fixture']['archive'];$GLOBALS['prune_fixture']['rows'][0]['archive_file']='../outside.zip';$before=$GLOBALS['prune_fixture'];$error=null;
    try{backups_prune();}catch(Throwable $e){$error=$e;}
    verify('Damaged archive identity is refused before any mutation',$error instanceof RuntimeException&&str_contains($error->getMessage(),'invalid archive identity')&&$GLOBALS['prune_fixture']===$before&&is_file($file));
}catch(Throwable $e){$failure=get_class($e).': '.$e->getMessage();}
finally{foreach(glob($root.'/*')?:[] as $file)unlink($file);rmdir($root);}
echo json_encode(['passed'=>!isset($failure),'scope'=>'Actual prune function with strict transaction/audit doubles and private synthetic bytes; not SQL acceptance','checks'=>$checks,'failure'=>$failure??null],JSON_PRETTY_PRINT).PHP_EOL;
exit(isset($failure)?1:0);
