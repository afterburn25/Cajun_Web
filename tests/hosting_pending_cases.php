<?php
/** Native persistence of an assigned-ID acknowledgement, without claiming provider verification. */
declare(strict_types=1);
(static function() use (&$checks,$package,$password):void {
$pendingCheckStart=count($checks);
try{
    hr_actor();$pendingAccount=hr_id(81);hosting_records_create($pendingAccount,1,$package,$password);
    $pendingClaim=hr_claim($pendingAccount,8101);$pb=$pendingClaim['binding'];
    $pendingResult=\CajunWebPro\Foundation\Hosting\OperationResult::acceptedPending('hosting.provision','866239',time(),$pendingAccount,$pb['attempt_ref']);
    $before=hr_full();hr_ok('Pending acknowledgement audit failure rolls back all result history',hr_final_audit_failure('result_recorded',fn()=>hr_record($pendingClaim,$pendingResult))&&hr_full()===$before);
    hr_record($pendingClaim,$pendingResult);$pendingJournal=hr_load($pendingAccount);
    hr_ok('Native acknowledgement retains pending lifecycle and assigned identity',$pendingJournal->account()->status()==='provisioning'&&$pendingJournal->account()->awaitingReconciliation()&&$pendingJournal->account()->acknowledgedResourceRef()==='866239');
    hr_ok('Native acknowledgement creates no verified resource association',$pendingJournal->account()->providerRef()===null&&!one('SELECT id FROM '.table('hosting_resources').' WHERE account_id=?',[$pendingAccount]));
    $before=hr_full();hr_record($pendingClaim,$pendingResult);hr_ok('Exact pending response replay preserves original attempt and all rows',hr_full()===$before);
    hr_deny('Changed acknowledgement cannot overwrite original assigned ID',fn()=>hr_record($pendingClaim,\CajunWebPro\Foundation\Hosting\OperationResult::acceptedPending('hosting.provision','foreign-id',$pendingResult->at,$pendingAccount,$pb['attempt_ref'])));
    hr_deny('Pending assigned resource cannot be dispatched a second time',fn()=>hr_claim($pendingAccount,8102));
    hr_deny('Pending assigned resource cannot be retried as a definite failure',fn()=>hosting_operations_retry_provision($pendingAccount,1,$pendingJournal->revision(),hr_id(8103),$password));
    hr_ok('Pending refusals preserve original service and all evidence',hr_full()===$before);
    $unknown=hr_evidence($pendingClaim);hr_observe($pendingClaim,8104,$unknown);
    hr_ok('Unknown inventory leaves accepted service pending with its original ID',hr_load($pendingAccount)->json()===$pendingJournal->json()&&hr_load($pendingAccount)->account()->acknowledgedResourceRef()==='866239');
    $before=hr_full();hr_deny('Inventory absence cannot undo an accepted provider purchase',fn()=>hr_observe($pendingClaim,8105,hr_evidence($pendingClaim,'not_applied')));
    hr_ok('Refused absence rolls back observation decision and audit',hr_full()===$before);
    hr_deny('Different observed package cannot settle original pending assignment',fn()=>hr_observe($pendingClaim,8106,hr_evidence($pendingClaim,'applied','foreign-id')));
    hr_ok('Wrong resource leaves original pending evidence unchanged',hr_full()===$before);
    $confirmed=hr_evidence($pendingClaim,'applied','866239');
    hr_ok('Confirmation audit failure rolls back adoption and evidence',hr_final_audit_failure('reconciliation_recorded',fn()=>hr_observe($pendingClaim,8107,$confirmed))&&hr_full()===$before);
    hr_observe($pendingClaim,8107,$confirmed);$active=hr_load($pendingAccount);
    hr_ok('Exact pending resource confirmation creates one client-bound active service',$active->account()->status()==='active'&&$active->account()->providerRef()==='866239'&&one('SELECT resource_ref FROM '.table('hosting_resources').' WHERE account_id=?',[$pendingAccount])['resource_ref']==='866239');
    hr_ok('Confirmed pending service keeps original provider acknowledgement',json_decode(one('SELECT result_json FROM '.table('hosting_operation_results').' WHERE id=?',[$pb['id']])['result_json'],true)['outcome']==='accepted_pending'&&count($active->account()->operations())===1);
    $before=hr_full();hr_ok('Pending confirmation replay is inert',!hr_observe($pendingClaim,8107,$confirmed)&&hr_full()===$before);
    $original=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[$pb['id']]);query('DELETE FROM '.table('hosting_operation_results').' WHERE id=?',[$pb['id']]);
    try{hr_deny('Lost pending acknowledgement cannot be reconstructed from active state',fn()=>hr_load($pendingAccount));}
    finally{query('INSERT INTO '.table('hosting_operation_results').' ('.implode(',',array_keys($original)).') VALUES ('.implode(',',array_fill(0,count($original),'?')).')',array_values($original));}
    hr_ok('Restoring original pending response preserves exact confirmed journal',hr_load($pendingAccount)->json()===$active->json());
}catch(Throwable $e){
    $checks[]=['name'=>'Unexpected pending persistence failure: '.$e->getMessage(),'passed'=>false];throw $e;
}finally{
    $named=array_slice($checks,$pendingCheckStart);file_put_contents(__DIR__.'/../qa/hosting-pending-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Native synthetic accepted-ID result persistence, replay, atomic rollback and exact-ID reconciliation; no provider verification or dispatch authority','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
})();
