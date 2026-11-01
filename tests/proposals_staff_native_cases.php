<?php
/** Retain exact proposal records when a delegated manager loses permission or carries support context. */
declare(strict_types=1);
p_actor(2);$probe=proposal_create(1,p_input('Staff authorization fixture'),bin2hex(random_bytes(32)));
$permissionRow=one('SELECT permissions FROM '.table('staff_profiles').' WHERE user_id=2');
$operations=['create'=>fn()=>proposal_create(1,p_input('Refused creation'),bin2hex(random_bytes(32))),'revise'=>fn()=>proposal_revise($probe,array_replace(p_input(),['schedule_notes'=>'Refused revision'])),'send'=>fn()=>proposal_send($probe),'withdraw'=>fn()=>proposal_withdraw($probe)];
foreach($operations as $operation=>$invoke){foreach(['permission','support-selector'] as $mode){
    p_actor(2);$_GET=[];$before=p_snapshot();$error=null;
    if($mode==='permission')query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['[]']);else $_GET['support']='invalid';
    try{$invoke();}catch(Throwable $e){$error=$e;}finally{query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',[$permissionRow['permissions']]);$_GET=[];}
    $expected=$mode==='permission'?'does not have access to this action':'Leave support before managing proposals';
    p_ok('Proposal '.$operation.' refuses '.$mode.' without changing history',$error instanceof RuntimeException&&!($error instanceof PDOException)&&str_contains($error->getMessage(),$expected)&&p_snapshot()===$before&&!db()->inTransaction());
}}
proposal_withdraw($probe);p_actor(2);
