<?php
/** Test-only delegated website-staff revocation; never loaded by the application. */
declare(strict_types=1);
require_once __DIR__.'/current_owner_authority.php';
function cwp_test_website_staff_revoked(callable $work): mixed {
    if(db()->inTransaction())throw new AssertionError('Staff-revocation fixture cannot inherit a transaction.');
    $session=$_SESSION;$get=$_GET;$actor=admin_user();
    if(!staff_ready()||!$actor||($actor['role']??'')!=='administrator'||!admin_session_matches($actor)||!staff_can('website.manage',$actor))
        throw new AssertionError('Start with the same current delegated website administrator.');
    $original=cwp_test_authority_snapshot();
    $profile=one('SELECT role,enabled,permissions FROM '.table('staff_profiles').' WHERE user_id=?',[$actor['id']]);
    if(!$profile||$profile['role']!=='administrator'||(int)$profile['enabled']!==1)throw new AssertionError('Expected current delegated profile.');
    $error=null;
    try {
        query('UPDATE '.table('staff_profiles')." SET permissions='[]' WHERE user_id=?",[$actor['id']]);
        $revoked=cwp_test_authority_snapshot();$current=admin_user();
        if(!$current||(int)$current['id']!==(int)$actor['id']||($current['role']??'')!=='administrator'||!admin_session_matches($current)||staff_can('website.manage',$current))
            throw new AssertionError('Revocation must keep the same authenticated non-owner identity.');
        try{$work();}catch(Throwable $caught){$error=$caught;}
        if(db()->inTransaction())throw new AssertionError('Refused delegated action leaked a transaction.');
        if(cwp_test_authority_snapshot()!==$revoked)throw new AssertionError('Refused delegated action changed stored state or private bytes.');
    } finally {
        if(db()->inTransaction())db()->rollBack();
        query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=?',[$profile['permissions'],$actor['id']]);
        $_SESSION=$session;$_GET=$get;
    }
    if(cwp_test_authority_snapshot()!==$original)throw new AssertionError('Delegated authority fixture did not restore exact original state.');
    if(!$error instanceof RuntimeException||$error instanceof PDOException||$error->getMessage()!=='Current website and inquiry permission is required.')
        throw new AssertionError('Delegated mutation did not produce its actual permission refusal.');
    $GLOBALS['cwp_current_authority_evidence'][]=['mode'=>'website-staff-permission-revoked','actor_id'=>(int)$actor['id'],'tables'=>count($original['tables']),'private_files'=>count($original['files']),'state_unchanged'=>true,'refusal'=>$error->getMessage()];
    throw $error;
}
