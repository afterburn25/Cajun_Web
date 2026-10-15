<?php
/** Durable attempt/result boundary only. No network, decryption, route or retry worker. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/records.php';
require_once CWP_CODE_ROOT.'/app/providers/settings.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_operations_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_operations_migrate.php';
require_once CWP_CODE_ROOT.'/app/hosting/reconciliation.php';
use CajunWebPro\Foundation\Hosting\{HostingJournal,OperationResult};

function hosting_operations_ready():bool {
    return hosting_storage_ready('operations');
}
function hosting_operations_require_ready():void {
    hosting_storage_require('records');hosting_storage_require('operations');
    if(!provider_settings_ready())throw new RuntimeException('An owner must prepare provider storage first.');
}
/** Closed mapping. Retry and reconciliation need their own verified evidence path. */
function hosting_operations_commands(string $operation):array {
    return match($operation){
        'hosting.provision'=>['begin_provisioning','record_provision'],
        'hosting.suspend'=>['suspend','record_suspend'],
        'hosting.resume'=>['resume','record_resume'],
        'hosting.deprovision'=>['approve_deprovision','record_deprovision'],
        default=>throw new InvalidArgumentException('Unknown hosting operation.'),
    };
}
function hosting_operations_result_data(OperationResult $result):array {
    return ['operation'=>$result->operation,'outcome'=>$result->outcome,'at'=>$result->at,'subject_ref'=>$result->subjectRef,'receipt'=>$result->receipt,'resource_ref'=>$result->resourceRef,'error'=>$result->error,'retryable'=>$result->retryable,'attempt_ref'=>$result->attemptRef];
}
/** Caller first validates all durable proof. Retry needs a definite safe failure or verified absence. */
function hosting_operations_provision_retryable(HostingJournal $journal):void {
    $account=$journal->account();$observations=$account->operations();$last=$observations?end($observations):null;
    if($account->status()!=='failed' || $account->pendingAttempt()!==null || $account->awaitingReconciliation() || !$last || $last->operation!=='hosting.provision' || !($last->outcome==='failed' && $last->retryable) && !hosting_reconciliation_absence($journal,'hosting.provision'))throw new RuntimeException('Provisioning retry requires a retained safe failure or verified conclusive absence. Unknown outcomes remain blocked.');
}
/** Retry deletion only from retained definite failure, by the original current approver. */
function hosting_operations_deprovision_retryable(HostingJournal $journal,int $actor):void {
    $account=$journal->account();$observations=$account->operations();$last=$observations?end($observations):null;
    if($account->status()!=='deprovision_failed' || $account->pendingAttempt()!==null || $account->awaitingReconciliation() || !$last || $last->operation!=='hosting.deprovision' || !($last->outcome==='failed' && $last->retryable) && !hosting_reconciliation_absence($journal,'hosting.deprovision'))throw new RuntimeException('Deletion retry requires a retained safe failure or verified conclusive non-removal. Unknown outcomes remain blocked.');
    $approval=null;
    foreach(json_decode($journal->json(),true)['events'] as $event)if($event['command']==='approve_deprovision')$approval=$event;
    if(!$approval || $approval['actor']!=='staff:'.$actor || $approval['arguments']!==['retention_confirmed'=>true])throw new RuntimeException('Deletion retry requires the original approver and retained data-retention approval.');
}
/** Preserve old ordinary-dispatch bytes; retries have a distinct canonical request identity. */
function hosting_operations_input(int $revision,string $operation,array $arguments,string $profileId,int $profileRevision,bool $retryDeletion=false):string {
    $input=['revision'=>$revision,'operation'=>$operation,'arguments'=>$arguments,'profile_id'=>$profileId,'profile_revision'=>$profileRevision];
    if($retryDeletion)$input['command']='retry_deprovision';
    return hosting_records_json($input);
}
/** Derive the exact retained dispatch boundary from its canonical event prefix. */
function hosting_operations_binding(array $row,HostingJournal $journal,array $dispatch):array {
    $d=json_decode($journal->json(),true);$revision=(int)$dispatch['revision'];$commands=hosting_operations_commands($dispatch['operation']);
    if($revision<1 || $revision>count($d['events']))throw new RuntimeException('Invalid hosting dispatch revision.');
    $event=$d['events'][$revision-1];$before=$d;$before['events']=array_slice($d['events'],0,$revision-1);
    $beforeJournal=HostingJournal::fromJson(hosting_records_json($before),$row['id'],'client:'.$row['client_id']);
    $after=$before;$after['events'][]=$event;
    $afterJournal=HostingJournal::fromJson(hosting_records_json($after),$row['id'],'client:'.$row['client_id']);
    $retryDeletion=$event['command']==='retry_deprovision';
    if($retryDeletion){
        if($dispatch['operation']!=='hosting.deprovision')throw new RuntimeException('A deletion retry cannot dispatch another operation.');
        hosting_operations_deprovision_retryable($beforeJournal,(int)$dispatch['actor_id']);
    }
    $command=$retryDeletion?'retry_deprovision':$commands[0];
    $input=hosting_operations_input($revision-1,$dispatch['operation'],$event['arguments'],$dispatch['profile_id'],(int)$dispatch['profile_revision'],$retryDeletion);
    if($dispatch['account_id']!==$row['id'] || $event['id']!==$dispatch['id'] || $event['command']!==$command || $event['actor']!=='staff:'.$dispatch['actor_id'] || $event['at']!==(int)$dispatch['started_epoch'] || $afterJournal->account()->pendingAttempt()!==$dispatch['attempt_ref'] || $beforeJournal->account()->pendingAttempt()!==null || $dispatch['input_json']!==$input)throw new RuntimeException('Hosting dispatch does not match its journal event.');
    $profile=provider_settings_load($dispatch['profile_id']);
    $historical=one('SELECT enabled,has_credentials FROM '.table('provider_revisions').' WHERE profile_id=? AND revision=?',[$dispatch['profile_id'],$dispatch['profile_revision']]);
    if((int)$dispatch['profile_revision']<1 || !$historical || (int)$historical['enabled']!==1 || (int)$historical['has_credentials']!==1)throw new RuntimeException('Hosting dispatch profile revision is unavailable.');
    $target=$beforeJournal->account()->providerRef();
    if($dispatch['operation']==='hosting.provision' ? $target!==null : $target===null)throw new RuntimeException('Hosting dispatch target is unavailable.');
    return ['id'=>$dispatch['id'],'account_id'=>$row['id'],'client_id'=>(int)$row['client_id'],'profile_id'=>$profile['id'],'profile_revision'=>(int)$dispatch['profile_revision'],'actor_id'=>(int)$dispatch['actor_id'],'operation'=>$dispatch['operation'],'attempt_ref'=>$dispatch['attempt_ref'],'resource_ref'=>$target,'started_epoch'=>(int)$dispatch['started_epoch'],'before_sha256'=>$beforeJournal->fingerprint(),'after_sha256'=>$afterJournal->fingerprint(),'terms_sha256'=>hash('sha256',$row['initial_json'])];
}
/** No missing fence or orphan result may be silently reconstructed from lifecycle state. */
function hosting_operations_validate(array $row):HostingJournal {
    $journal=hosting_records_journal($row);$d=json_decode($journal->json(),true);$events=[];
    foreach($d['events'] as $event)$events[$event['id']]=$event;
    $covered=[];$resourceExpected=null;
    foreach(all('SELECT * FROM '.table('hosting_dispatches').' WHERE account_id=? ORDER BY revision',[$row['id']]) as $dispatch){
        $binding=hosting_operations_binding($row,$journal,$dispatch);
        if($dispatch['binding_json']!==hosting_records_json($binding))throw new RuntimeException('Saved hosting dispatch binding is inconsistent.');
        $covered[$dispatch['id']]=true;
        $result=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[$dispatch['id']]);
        if($result){
            $event=$events[$result['event_id']]??null;$data=json_decode($result['result_json'],true,16,JSON_THROW_ON_ERROR);
            if(!$event || $event['command']!==hosting_operations_commands($dispatch['operation'])[1] || $event['actor']!=='staff:'.$dispatch['actor_id'] || $event['arguments']!==['result'=>$data] || hosting_records_json($data)!==$result['result_json'] || ($data['operation']??null)!==$dispatch['operation'] || ($data['subject_ref']??null)!==$row['id'] || ($data['attempt_ref']??null)!==$dispatch['attempt_ref'])throw new RuntimeException('Saved hosting result binding is inconsistent.');
            $covered[$result['event_id']]=true;
            if($dispatch['operation']==='hosting.provision' && $data['outcome']==='applied' && $data['resource_ref']!==null)$resourceExpected=['id'=>$dispatch['id'],'account_id'=>$row['id'],'profile_id'=>$dispatch['profile_id'],'resource_ref'=>$data['resource_ref']];
        }
        if(hosting_reconciliation_ready()){
            $decision=hosting_reconciliation_validate_dispatch($row,$journal,$dispatch,$result,$events);
            if($decision){
                $covered[$decision['event_id']]=true;
                if($dispatch['operation']==='hosting.provision' && $decision['data']['outcome']==='applied')$resourceExpected=['id'=>$dispatch['id'],'account_id'=>$row['id'],'profile_id'=>$dispatch['profile_id'],'resource_ref'=>$decision['data']['resource_ref']];
            }
        }
    }
    $resource=one('SELECT id,account_id,profile_id,resource_ref FROM '.table('hosting_resources').' WHERE account_id=?',[$row['id']]);
    if($resource!==$resourceExpected)throw new RuntimeException('Saved hosting resource association is inconsistent.');
    $prefix=$d;$prefix['events']=[];
    foreach($events as $id=>$event){
        if($event['command']==='retry_provision'){
            hosting_operations_provision_retryable(HostingJournal::fromJson(hosting_records_json($prefix),$row['id'],'client:'.$row['client_id']));
            $covered[$id]=true;
        }
        $prefix['events'][]=$event;
        if($event['command']==='reconcile')throw new RuntimeException('This hosting operation history requires a newer adapter.');
        if(!in_array($event['command'],['link_domain','set_grace','open_dispute','close_dispute','cancel_order','request_cancellation'],true) && !isset($covered[$id]))throw new RuntimeException('A hosting dispatch fence or result is missing. Existing data was preserved.');
    }
    return $journal;
}
/** Explicit owner request only returns local pending state, never a network claim. */
function hosting_operations_retry_provision(string $id,int $client,int $revision,string $event,#[\SensitiveParameter] string $password):HostingJournal {
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$revision,$event,$password):HostingJournal {
        $actor=hosting_records_password($password);hosting_operations_require_ready();hosting_records_client($client,true);hosting_records_id($event);
        if($revision<0 || $revision>=HostingJournal::MAX_EVENTS)throw new InvalidArgumentException('Invalid hosting retry revision.');
        $row=hosting_records_row($id,$client,true);$journal=hosting_operations_validate($row);
        $input=hosting_records_json(['revision'=>$revision,'command'=>'retry_provision','arguments'=>[]]);
        $old=one('SELECT * FROM '.table('hosting_record_events').' WHERE id=?',[$event]);
        if($old){
            if($old['account_id']!==$id || (int)$old['actor_id']!==(int)$actor['id'] || $old['input_json']!==$input)throw new InvalidArgumentException('This hosting retry identity was already used.');
            return $journal;
        }
        if($journal->revision()!==$revision)throw new RuntimeException('This hosting record changed. Reload before requesting a retry.');
        hosting_operations_provision_retryable($journal);
        $next=hosting_records_append_locked($row,$event,'retry_provision',[],(int)$actor['id']);
        hosting_records_audit((int)$actor['id'],'provision_retry_requested','Local account '.$id.' retry event '.$event);return $next;
    }));
}
/** Only the invocation committing a NEW fence receives a claim; saved/pending state never does. */
function hosting_operations_claim(string $id,int $client,int $revision,string $request,string $operation,array $arguments,string $profileId,int $profileRevision,#[\SensitiveParameter] string $password):?array {
    return hosting_operations_claim_event($id,$client,$revision,$request,$operation,$arguments,$profileId,$profileRevision,$password,false);
}
/** A deliberate retry commits its fresh attempt and dispatch fence together; no saved claim is returned again. */
function hosting_operations_retry_deprovision(string $id,int $client,int $revision,string $request,string $profileId,int $profileRevision,#[\SensitiveParameter] string $password):?array {
    return hosting_operations_claim_event($id,$client,$revision,$request,'hosting.deprovision',[],$profileId,$profileRevision,$password,true);
}
/** Shared internal transaction. The retry flag is not a user-selected journal command. */
function hosting_operations_claim_event(string $id,int $client,int $revision,string $request,string $operation,array $arguments,string $profileId,int $profileRevision,#[\SensitiveParameter] string $password,bool $retryDeletion):?array {
    if($retryDeletion && ($operation!=='hosting.deprovision' || $arguments!==[]))throw new InvalidArgumentException('Invalid deletion retry command.');
    return staff_locked(fn()=>staff_transaction(function()use($id,$client,$revision,$request,$operation,$arguments,$profileId,$profileRevision,$password,$retryDeletion):?array {
        $actor=hosting_records_password($password);hosting_operations_require_ready();hosting_records_client($client,true);hosting_records_id($request);hosting_records_id($profileId);
        $commands=hosting_operations_commands($operation);
        if($revision<0 || $revision>=HostingJournal::MAX_EVENTS || $profileRevision<1)throw new InvalidArgumentException('Invalid hosting operation revision.');
        $row=hosting_records_row($id,$client,true);$journal=hosting_operations_validate($row);
        $input=hosting_operations_input($revision,$operation,$arguments,$profileId,$profileRevision,$retryDeletion);
        $existing=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[$request]);
        if($existing){
            if($existing['account_id']!==$id || (int)$existing['actor_id']!==(int)$actor['id'] || $existing['input_json']!==$input)throw new InvalidArgumentException('This hosting dispatch identity was already used.');
            return null;
        }
        if($journal->revision()!==$revision)throw new RuntimeException('This hosting record changed. Reload before starting an operation.');
        if($journal->account()->pendingAttempt()!==null || $journal->account()->awaitingReconciliation())throw new RuntimeException('An existing hosting operation must be resolved first.');
        $profile=provider_settings_load($profileId,true);
        if((int)$profile['revision']!==$profileRevision || (int)$profile['enabled']!==1 || $profile['credentials']==='')throw new RuntimeException('The reviewed provider profile changed or is disabled.');
        // Keep an existing account tied to the same provider identity through all later operations.
        $prior=one('SELECT profile_id FROM '.table('hosting_dispatches').' WHERE account_id=? ORDER BY revision LIMIT 1',[$id]);
        if($prior && $prior['profile_id']!==$profileId)throw new RuntimeException('Hosting provider reassignment requires a separate verified migration.');
        if($retryDeletion)hosting_operations_deprovision_retryable($journal,(int)$actor['id']);
        $next=hosting_records_append_locked($row,$request,$retryDeletion?'retry_deprovision':$commands[0],$arguments,(int)$actor['id']);
        $event=json_decode($next->json(),true)['events'][$revision];
        $dispatch=['id'=>$request,'account_id'=>$id,'profile_id'=>$profileId,'profile_revision'=>$profileRevision,'actor_id'=>(int)$actor['id'],'revision'=>$revision+1,'operation'=>$operation,'attempt_ref'=>$next->account()->pendingAttempt(),'started_epoch'=>$event['at'],'input_json'=>$input];
        $binding=hosting_operations_binding($row,$next,$dispatch);$bytes=hosting_records_json($binding);
        query('INSERT INTO '.table('hosting_dispatches').' (id,account_id,profile_id,profile_revision,actor_id,revision,operation,attempt_ref,started_epoch,input_json,binding_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[...array_values($dispatch),$bytes]);
        hosting_records_audit((int)$actor['id'],'dispatch_fenced','Account '.$id.' dispatch '.$request.' '.$operation);
        return ['binding'=>$binding,'fingerprint'=>hash('sha256',$bytes)];
    }));
}
/** Trusted internal finalizer, never an HTTP callback. Preserve reality despite later access loss. */
function hosting_operations_record(string $dispatchId,string $fingerprint,OperationResult $result):void {
    hosting_records_id($dispatchId);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$fingerprint))throw new InvalidArgumentException('Invalid hosting dispatch fingerprint.');
    staff_locked(fn()=>staff_transaction(function()use($dispatchId,$fingerprint,$result):void {
        hosting_operations_require_ready();$dispatch=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[$dispatchId]);
        if(!$dispatch || !hash_equals(hash('sha256',$dispatch['binding_json']),$fingerprint))throw new InvalidArgumentException('No matching committed hosting dispatch exists.');
        $binding=json_decode($dispatch['binding_json'],true,16,JSON_THROW_ON_ERROR);
        $row=hosting_records_row($dispatch['account_id'],(int)$binding['client_id'],true);hosting_operations_validate($row);
        if($result->operation!==$dispatch['operation'] || $result->subjectRef!==$dispatch['account_id'] || $result->attemptRef!==$dispatch['attempt_ref'])throw new InvalidArgumentException('Hosting result does not match its exact operation and attempt.');
        if($result->operation!=='hosting.provision' && $result->resourceRef!==null && $result->resourceRef!==$binding['resource_ref'])throw new InvalidArgumentException('Hosting result names a different provider resource.');
        $data=hosting_operations_result_data($result);$bytes=hosting_records_json($data);
        $old=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[$dispatchId]);
        if($old){if($old['result_json']!==$bytes)throw new InvalidArgumentException('A different hosting result is already recorded.');return;}
        $event=bin2hex(random_bytes(16));
        hosting_records_append_locked($row,$event,hosting_operations_commands($dispatch['operation'])[1],['result'=>$data],(int)$dispatch['actor_id']);
        query('INSERT INTO '.table('hosting_operation_results').' (id,event_id,result_json) VALUES (?,?,?)',[$dispatchId,$event,$bytes]);
        if($result->operation==='hosting.provision' && $result->outcome==='applied' && $result->resourceRef!==null)query('INSERT INTO '.table('hosting_resources').' (id,account_id,profile_id,resource_ref) VALUES (?,?,?,?)',[$dispatchId,$row['id'],$dispatch['profile_id'],$result->resourceRef]);
        hosting_records_audit((int)$dispatch['actor_id'],'result_recorded','Account '.$row['id'].' dispatch '.$dispatchId.' '.$result->outcome);
    }));
}
