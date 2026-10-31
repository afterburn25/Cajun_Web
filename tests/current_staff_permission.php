<?php
/** Same-identity delegated-staff refusal fixture; never loaded in the application. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
function cwp_test_staff_permission_revoked(string $permission,callable $work): mixed {
    if($permission!=='website.manage'||db()->inTransaction())throw new AssertionError('Use the isolated website-staff permission fixture without a transaction.');
    $session=$_SESSION;$get=$_GET;$actor=admin_user();
    if(!staff_ready()||!$actor||($actor['role']??'')!=='administrator'||!admin_session_matches($actor)||!staff_can($permission,$actor))throw new AssertionError('Start with the same current, authorized delegated staff member.');
    $original=cwp_test_authority_snapshot();
    $profile=one('SELECT role,permissions FROM '.table('staff_profiles').' WHERE user_id=?',[$actor['id']]);
    if(!$profile||$profile['role']!=='administrator')throw new AssertionError('Expected a delegated administrator profile.');
    $permissions=json_decode($profile['permissions'],true,16,JSON_THROW_ON_ERROR);
    if(!is_array($permissions)||!array_is_list($permissions)||!in_array($permission,$permissions,true))throw new AssertionError('The delegated permission must genuinely exist.');
    $remaining=json_encode(array_values(array_filter($permissions,static fn($p)=>$p!==$permission)),JSON_THROW_ON_ERROR);$error=null;
    try{
        query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=?',[$remaining,$actor['id']]);
        $revoked=cwp_test_authority_snapshot();$current=admin_user();
        if(!$current||(int)$current['id']!==(int)$actor['id']||$current['role']!=='administrator'||!admin_session_matches($current)||staff_can($permission,$current))throw new AssertionError('Permission revocation must preserve the same authenticated delegated identity.');
        try{$work();}catch(Throwable $caught){$error=$caught;}
        if(db()->inTransaction())throw new AssertionError('Refused staff operation leaked a transaction.');
        if(cwp_test_authority_snapshot()!==$revoked)throw new AssertionError('Refused staff operation changed records, schema or private files.');
    }finally{
        if(db()->inTransaction())db()->rollBack();
        query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=?',[$profile['permissions'],$actor['id']]);
        $_SESSION=$session;$_GET=$get;
    }
    if(cwp_test_authority_snapshot()!==$original)throw new AssertionError('Staff fixture did not restore the exact original state.');
    if(!$error instanceof RuntimeException||$error instanceof PDOException||$error->getMessage()!=='Current website and inquiry permission is required.')throw new AssertionError('Staff mutation did not produce its actual permission refusal: '.($error?get_class($error).': '.$error->getMessage():'operation succeeded'));
    $GLOBALS['cwp_current_authority_evidence'][]=['mode'=>'staff-permission-revoked','permission'=>$permission,'actor_id'=>(int)$actor['id'],'tables'=>count($original['tables']),'private_files'=>count($original['files']),'state_unchanged'=>true,'refusal'=>$error->getMessage()];
    throw $error;
}
