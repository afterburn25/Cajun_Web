<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('CWP_BOOT',true);

$state=['tx'=>false,'locked'=>false,'support'=>false,'audit_fail'=>false,'next'=>10,'messages'=>[
    ['id'=>1,'job_id'=>1,'author_type'=>'admin','author_id'=>1,'body'=>'Public admin','created_at'=>'2026-01-01 00:00:00'],
    ['id'=>2,'job_id'=>1,'author_type'=>'client','author_id'=>2,'body'=>'Public client','created_at'=>'2026-01-01 00:00:01'],
    ['id'=>3,'job_id'=>1,'author_type'=>'contact','author_id'=>3,'body'=>'Public contact','created_at'=>'2026-01-01 00:00:02'],
    ['id'=>4,'job_id'=>1,'author_type'=>'internal','author_id'=>7,'body'=>'Private marker','created_at'=>'2026-01-01 00:00:03'],
    ['id'=>5,'job_id'=>1,'author_type'=>'future','author_id'=>8,'body'=>'Unknown marker','created_at'=>'2026-01-01 00:00:04'],
],'audit'=>[],'permissions'=>[],'queries'=>[]];
$checks=[];
function note_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function note_refuses(string $name,callable $work,string $class=InvalidArgumentException::class):void{try{$work();}catch(Throwable $e){note_ok($name,$e instanceof $class);return;}note_ok($name,false);}
function table(string $name):string{return 'ci_'.$name;}
function staff_locked(callable $work):mixed{global $state;if($state['locked'])throw new LogicException('nested lock');$state['locked']=true;try{return $work();}finally{$state['locked']=false;}}
function staff_transaction(callable $work):mixed{global $state;if($state['tx'])throw new LogicException('nested transaction');$before=$state;$state['tx']=true;try{$result=$work();$state['tx']=false;return $result;}catch(Throwable $e){$state=$before;throw $e;}}
function staff_require(string $permission):array{global $state;$state['permissions'][]=['permission'=>$permission,'in_tx'=>$state['tx']];return ['id'=>7];}
function support_current():bool{global $state;return $state['support'];}
function one(string $sql,array $params=[]):?array{global $state;$state['queries'][]=$sql;return ($params[0]??0)===1?['id'=>1]:null;}
final class NoteDb{public function lastInsertId():string{global $state;return (string)$state['next'];}}
function db():NoteDb{return new NoteDb();}
function query(string $sql,array $params=[]):object{global $state;if(!$state['tx'])throw new RuntimeException('write outside transaction');if(substr_count($sql,'?')!==count($params))throw new RuntimeException('placeholder mismatch');$state['queries'][]=$sql;
    if(str_contains($sql,'ci_studio_messages')){$state['next']++;$state['messages'][]=['id'=>$state['next'],'job_id'=>$params[0],'author_type'=>'internal','author_id'=>$params[1],'body'=>$params[2],'created_at'=>$params[3]];return new stdClass();}
    if(str_contains($sql,'ci_audit')){if($state['audit_fail'])throw new RuntimeException('synthetic audit failure');$state['audit'][]=['user_id'=>$params[0],'action'=>'studio.note','detail'=>$params[1],'created_at'=>$params[2]];return new stdClass();}
    throw new RuntimeException('unexpected query');
}
function all(string $sql,array $params=[]):array{global $state;$state['queries'][]=$sql;$job=$params[0];$rows=array_values(array_filter($state['messages'],fn($row)=>$row['job_id']===$job));
    if(str_contains($sql,"author_type IN ('admin','client','contact')"))$rows=array_values(array_filter($rows,fn($row)=>in_array($row['author_type'],['admin','client','contact'],true)));
    elseif(str_contains($sql,"author_type='internal'"))$rows=array_values(array_filter($rows,fn($row)=>$row['author_type']==='internal'));
    foreach($rows as &$row)if($row['author_type']==='internal')$row['staff_name']='Synthetic Staff';unset($row);return array_reverse($rows);
}

require __DIR__.'/../site/app/projects/internal_notes.php';
try{
    $id=project_internal_note_save(1,"  Private note body  ");
    note_ok('Note identity returned',$id===11);
    note_ok('Authority is rechecked inside transaction',$state['permissions'][0]===['permission'=>'clients.manage','in_tx'=>true]);
    note_ok('Stored note is normalized and attributed to exact staff',end($state['messages'])['body']==='Private note body'&&end($state['messages'])['author_id']===7&&end($state['messages'])['author_type']==='internal');
    note_ok('Strict audit is privacy-safe and shares note transaction',$state['audit']=== [['user_id'=>7,'action'=>'studio.note','detail'=>'Project 1 internal note 11','created_at'=>$state['audit'][0]['created_at']]]&&!str_contains($state['audit'][0]['detail'],'Private note body'));
    $beforeMessages=$state['messages'];$beforeAudit=$state['audit'];$state['audit_fail']=true;
    note_refuses('Audit failure refuses whole operation',fn()=>project_internal_note_save(1,'Must roll back'),RuntimeException::class);
    note_ok('Audit failure rolls back note and audit',$state['messages']===$beforeMessages&&$state['audit']===$beforeAudit);
    $state['audit_fail']=false;$state['support']=true;
    note_refuses('Support mode cannot write staff-only note',fn()=>project_internal_note_save(1,'Support must not write'),RuntimeException::class);
    note_refuses('Support mode cannot read staff-only notes',fn()=>project_internal_notes(1),RuntimeException::class);
    note_ok('Support refusals leave no durable change',$state['messages']===$beforeMessages&&$state['audit']===$beforeAudit);
    $state['support']=false;
    note_refuses('Missing project refuses before insert',fn()=>project_internal_note_save(2,'Missing project'));
    foreach(['',' ',"x\n",str_repeat('x',5001),"\xff","ok\0hidden","bad\x7ftext",null,7] as $i=>$bad)note_refuses('Invalid note body refused '.$i,fn()=>project_internal_note_save(1,$bad));
    note_refuses('Invalid project identity refused',fn()=>project_internal_note_save(0,'Valid body'));
    $public=project_public_messages(1,0,false);
    note_ok('Public projection includes exact established author types',array_column($public,'author_type')===['contact','client','admin']);
    note_ok('Public projection excludes internal and unknown future types',!in_array('Private marker',array_column($public,'body'),true)&&!in_array('Unknown marker',array_column($public,'body'),true));
    note_ok('Public SQL is an allow-list, never a negative internal filter',str_contains($state['queries'][array_key_last($state['queries'])],"author_type IN ('admin','client','contact')")&&!str_contains($state['queries'][array_key_last($state['queries'])],'<>'));
    $contactPublic=project_public_messages(1,5,true);
    note_ok('Contact-enabled projection retains the same allow-list',array_column($contactPublic,'author_type')===['contact','client','admin']&&str_contains($state['queries'][array_key_last($state['queries'])],"m.author_type IN ('admin','client','contact')"));
    $internal=project_internal_notes(1);
    note_ok('Staff reader returns internal rows only',count($internal)===2&&count(array_unique(array_column($internal,'author_type')))===1&&$internal[0]['staff_name']==='Synthetic Staff');
    note_ok('Staff reader checks current client authority',end($state['permissions'])['permission']==='clients.manage');
    note_refuses('Public projection rejects invalid focus',fn()=>project_public_messages(1,-1,false));
    $controller=file_get_contents(__DIR__.'/../site/admin/controllers/studio.php');
    $permissions=file_get_contents(__DIR__.'/../site/app/staff/accounts.php');
    $adminView=file_get_contents(__DIR__.'/../site/admin/views/studio/job.php');
    $portalView=file_get_contents(__DIR__.'/../site/portal/views/workspace.php');
    $activity=file_get_contents(__DIR__.'/../site/app/activity/events.php');
    note_ok('Dispatcher maps note writes to current client authority',str_contains($permissions,"'studio_note'")&&preg_match("/studio_note.+clients\\.manage/s",$permissions)===1);
    note_ok('Controller delegates notes to the atomic helper',str_contains($controller,"if(\$action==='studio_note')")&&str_contains($controller,'project_internal_note_save('));
    note_ok('Controller does not add a second best-effort note audit',str_contains($controller,"if(\$action!=='studio_note')log_event"));
    note_ok('Admin conversation excludes private and unknown author classes',str_contains($adminView,"author_type IN (\\'admin\\',\\'client\\',\\'contact\\')")&&!str_contains($adminView,"author_type<>\\'internal\\'"));
    note_ok('Admin view renders the separately authorized internal-note partial',str_contains($adminView,"require __DIR__.'/internal_notes.php'"));
    note_ok('Portal conversation uses the fail-closed public projection',str_contains($portalView,'project_public_messages('));
    note_ok('Internal notes do not enter existing client activity rules',!str_contains($activity,"author_type='internal'")&&!str_contains($activity,"author_type IN ('admin','client','contact','internal')"));
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'scope'=>'Offline model adapter only; no customer message, notification, live operation, schema or hosted acceptance.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(1);}
