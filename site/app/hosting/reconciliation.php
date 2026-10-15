<?php
/** Trusted internal evidence persistence. No provider verifier, route or network claim. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/hosting/storage.php';
require_once CWP_CODE_ROOT.'/database/schema/hosting_reconciliation_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_reconciliation_migrate.php';
use CajunWebPro\Foundation\Hosting\HostingJournal;
const CWP_HOSTING_MAX_UNKNOWN_OBSERVATIONS=32;

function hosting_reconciliation_ready():bool {
    return hosting_storage_ready('reconciliation');
}
/** Closed observation format; proof is an opaque reference and digest, never raw provider data. */
function hosting_reconciliation_data(array $binding,string $fingerprint,array $input):array {
    $keys=array_keys($input);sort($keys);$expected=['outcome','observed_at','resource_ref','evidence_ref','evidence_sha256'];sort($expected);
    if($keys!==$expected || !in_array($input['outcome'],['unknown','applied','not_applied'],true) || !is_int($input['observed_at']) || $input['observed_at']<$binding['started_epoch'])throw new InvalidArgumentException('Invalid hosting observation.');
    if(!is_string($input['evidence_ref']) || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}\z/',$input['evidence_ref']) || !is_string($input['evidence_sha256']) || !preg_match('/\A[a-f0-9]{64}\z/',$input['evidence_sha256']))throw new InvalidArgumentException('A bounded evidence reference and digest are required.');
    $resource=$input['resource_ref'];
    if($resource!==null && (!is_string($resource) || strlen($resource)>256 || trim($resource)==='' || !preg_match('//u',$resource) || preg_match('/[\x00-\x1f\x7f]/',$resource)))throw new InvalidArgumentException('Invalid observed provider resource.');
    if($binding['operation']==='hosting.provision'){
        if(($input['outcome']==='applied')!==($resource!==null))throw new InvalidArgumentException('Only confirmed provisioning may name its adopted resource.');
    }elseif($resource!==$binding['resource_ref'])throw new InvalidArgumentException('Observation must name the original provider resource.');
    return ['dispatch_fingerprint'=>$fingerprint,'binding'=>$binding,'outcome'=>$input['outcome'],'observed_at'=>$input['observed_at'],'resource_ref'=>$resource,'evidence_ref'=>$input['evidence_ref'],'evidence_sha256'=>$input['evidence_sha256']];
}
function hosting_reconciliation_arguments(array $data):array {
    return ['applied_remotely'=>$data['outcome']==='applied','resource_ref'=>$data['resource_ref'],'evidence'=>$data['evidence_ref'].' sha256:'.$data['evidence_sha256'],'attempt_ref'=>$data['binding']['attempt_ref']];
}
/** Validate observation at its retained journal prefix, including after a later retry. */
function hosting_reconciliation_validate_dispatch(array $row,HostingJournal $journal,array $dispatch,?array $result,array $events):?array {
    $decision=one('SELECT * FROM '.table('hosting_reconciliations').' WHERE id=?',[$dispatch['id']]);
    $observations=all('SELECT * FROM '.table('hosting_observations').' WHERE dispatch_id=? LIMIT '.(CWP_HOSTING_MAX_UNKNOWN_OBSERVATIONS+2),[$dispatch['id']]);
    if(count($observations)>CWP_HOSTING_MAX_UNKNOWN_OBSERVATIONS+1)throw new RuntimeException('Hosting observation history exceeds its bound. Existing data was preserved.');
    $found=false;$unknown=0;$document=json_decode($journal->json(),true);
    foreach($observations as $observation){
        $data=json_decode($observation['observation_json'],true,16,JSON_THROW_ON_ERROR);
        $binding=json_decode($dispatch['binding_json'],true,16,JSON_THROW_ON_ERROR);
        $input=array_intersect_key($data,array_flip(['outcome','observed_at','resource_ref','evidence_ref','evidence_sha256']));
        $expected=hosting_reconciliation_data($binding,hash('sha256',$dispatch['binding_json']),$input);
        $revision=(int)$observation['revision'];$prefix=$document;$prefix['events']=array_slice($document['events'],0,$revision);
        $before=HostingJournal::fromJson(hosting_records_json($prefix),$row['id'],'client:'.$row['client_id']);
        $last=$prefix['events']?end($prefix['events']):null;
        if(!$result || $revision<1 || $revision>$journal->revision() || $observation['observation_json']!==hosting_records_json($expected) || !hash_equals($before->fingerprint(),$observation['journal_sha256']) || !$before->account()->awaitingReconciliation() || $before->account()->pendingAttempt()!==$dispatch['attempt_ref'] || $data['observed_at']>(int)$observation['recorded_epoch'] || (int)$observation['recorded_epoch']<$last['at'])throw new RuntimeException('Saved hosting observation does not match its unresolved dispatch.');
        $original=json_decode($result['result_json'],true,16,JSON_THROW_ON_ERROR);
        if($data['observed_at']<$original['at'])throw new RuntimeException('Hosting evidence predates its original observation.');
        if($data['outcome']==='unknown' && ++$unknown>CWP_HOSTING_MAX_UNKNOWN_OBSERVATIONS)throw new RuntimeException('Hosting unknown observation history exceeds its bound.');
        if($data['outcome']!=='unknown'){
            $event=$decision?($events[$decision['event_id']]??null):null;
            if(!$decision || $decision['observation_id']!==$observation['id'] || !$event || ($document['events'][$revision]['id']??null)!==$decision['event_id'] || $event['command']!=='reconcile_without_dispatch' || $event['actor']!=='staff:'.$dispatch['actor_id'] || $event['at']<$data['observed_at'] || $event['arguments']!==hosting_reconciliation_arguments($data))throw new RuntimeException('Saved conclusive hosting evidence is inconsistent.');
            $decision['data']=$data;$found=true;
        }
    }
    if($decision && !$found)throw new RuntimeException('A hosting reconciliation proof is missing.');
    return $decision;
}
/** Only valid inside a complete operations validation; the canonical event alone is insufficient. */
function hosting_reconciliation_absence(HostingJournal $journal,string $operation):bool {
    if(!hosting_reconciliation_ready())return false;
    $document=json_decode($journal->json(),true);$results=$journal->account()->operations();$last=$results?end($results):null;
    if(!$last || $last->operation!==$operation || !($last->outcome==='timeout' || ($operation==='hosting.provision' && $last->outcome==='applied' && $last->resourceRef===null)))return false;
    foreach(array_reverse($document['events']) as $event){
        if($event['command']!=='reconcile_without_dispatch' || $event['arguments']['attempt_ref']!==$last->attemptRef)continue;
        $proof=one('SELECT r.event_id FROM '.table('hosting_reconciliations').' r JOIN '.table('hosting_dispatches').' d ON d.id=r.id WHERE d.account_id=? AND d.attempt_ref=? AND d.operation=?',[$document['id'],$last->attemptRef,$operation]);
        return $proof && $proof['event_id']===$event['id'] && $event['arguments']['applied_remotely']===false;
    }
    return false;
}
/**
 * Called only after a trusted adapter verifies evidence for this exact committed dispatch.
 * Unknown/partial reads stay unknown. not_applied must also rule out a still-running action.
 * A digest is an integrity reference, not proof that a provider result is authoritative.
 */
function hosting_reconciliation_record(string $dispatchId,string $fingerprint,string $observationId,array $input):bool {
    hosting_records_id($dispatchId);hosting_records_id($observationId);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$fingerprint))throw new InvalidArgumentException('Invalid hosting dispatch fingerprint.');
    return staff_locked(fn()=>staff_transaction(function()use($dispatchId,$fingerprint,$observationId,$input):bool {
        hosting_operations_require_ready();
        hosting_storage_require('reconciliation');
        $dispatch=one('SELECT * FROM '.table('hosting_dispatches').' WHERE id=?',[$dispatchId]);
        if(!$dispatch || !hash_equals(hash('sha256',$dispatch['binding_json']),$fingerprint))throw new InvalidArgumentException('No matching committed hosting dispatch exists.');
        $binding=json_decode($dispatch['binding_json'],true,16,JSON_THROW_ON_ERROR);
        $data=hosting_reconciliation_data($binding,$fingerprint,$input);$bytes=hosting_records_json($data);
        $row=hosting_records_row($dispatch['account_id'],(int)$binding['client_id'],true);$journal=hosting_operations_validate($row);
        $old=one('SELECT * FROM '.table('hosting_observations').' WHERE id=?',[$observationId]);
        if($old){if($old['dispatch_id']!==$dispatchId || $old['observation_json']!==$bytes)throw new InvalidArgumentException('This hosting observation identity was already used.');return false;}
        $result=one('SELECT * FROM '.table('hosting_operation_results').' WHERE id=?',[$dispatchId]);
        if(!$result || !$journal->account()->awaitingReconciliation() || $journal->account()->pendingAttempt()!==$dispatch['attempt_ref'])throw new RuntimeException('Only this retained unresolved result can be reconciled. A missing result needs separate verified recovery.');
        if($data['outcome']==='unknown' && (int)query('SELECT COUNT(*) FROM '.table('hosting_observations').' WHERE dispatch_id=?',[$dispatchId])->fetchColumn()>=CWP_HOSTING_MAX_UNKNOWN_OBSERVATIONS)throw new RuntimeException('This unresolved attempt has reached its observation limit. Verified conclusive resolution remains available.');
        $original=json_decode($result['result_json'],true,16,JSON_THROW_ON_ERROR);
        $document=json_decode($journal->json(),true);$last=end($document['events']);$recorded=max(time(),$last['at']);
        if($data['observed_at']>time() || $data['observed_at']<$original['at'])throw new InvalidArgumentException('Hosting evidence time is outside the observed operation.');
        query('INSERT INTO '.table('hosting_observations').' (id,dispatch_id,revision,journal_sha256,recorded_epoch,observation_json) VALUES (?,?,?,?,?,?)',[$observationId,$dispatchId,$journal->revision(),$journal->fingerprint(),$recorded,$bytes]);
        if($data['outcome']!=='unknown'){
            $event=bin2hex(random_bytes(16));
            hosting_records_append_locked($row,$event,'reconcile_without_dispatch',hosting_reconciliation_arguments($data),(int)$dispatch['actor_id']);
            query('INSERT INTO '.table('hosting_reconciliations').' (id,observation_id,event_id) VALUES (?,?,?)',[$dispatchId,$observationId,$event]);
            if($dispatch['operation']==='hosting.provision' && $data['outcome']==='applied')query('INSERT INTO '.table('hosting_resources').' (id,account_id,profile_id,resource_ref) VALUES (?,?,?,?)',[$dispatchId,$row['id'],$dispatch['profile_id'],$data['resource_ref']]);
        }
        hosting_records_audit((int)$dispatch['actor_id'],'reconciliation_recorded','Account '.$row['id'].' dispatch '.$dispatchId.' observation '.$observationId.' '.$data['outcome']);
        return true;
    }));
}
