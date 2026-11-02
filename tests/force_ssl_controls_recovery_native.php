<?php
require_once __DIR__.'/current_owner_authority.php';

/** Included in the disposable native controls fixture after an actual terminal-audit rollback. */
if(!defined('CWP_BOOT')||PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
function fc_recovery_sources(string $id):array {
    return [one('SELECT * FROM '.table('provider_force_ssl_changes').' WHERE id=?',[$id]),one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]),one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['forcessl:'.$id])];
}
function fc_recovery_state():array {
    global $dns,$writes,$sent;
    return [all('SELECT * FROM '.table('provider_force_ssl_dispatch_ends').' ORDER BY id'),all('SELECT * FROM '.table('audit').' ORDER BY id'),all('SELECT name,value FROM '.table('settings')." WHERE name LIKE 'provider_force_ssl_control_%' ORDER BY name"),fc_count('provider_refreshes'),fc_count('provider_force_ssl_permissions'),$dns,$writes,$sent];
}
function fc_recover(string $id,?string $hash=null):array {global $password;return provider_force_ssl_controls_recover_finished($id,$hash??fc_summary($id)['recovery_ledger_sha256'],$password);}
function fc_recovery_worker(string $phase):array {
    global $link,$password;
    fc_seed_response(provider_force_ssl_binding($link['id']),'false');$b=provider_force_ssl_binding($link['id']);$r=provider_force_ssl_latest($link['id']);$id=bin2hex(random_bytes(16));
    $process=proc_open([PHP_BINARY,__DIR__.'/force_ssl_controls_recovery_worker.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,__DIR__);if(!is_resource($process))throw new RuntimeException('Recovery worker could not start.');
    try{
        fwrite($pipes[0],json_encode(['config'=>config(),'password'=>$password,'request_id'=>$id,'link_id'=>$link['id'],'anchor_id'=>$r['id'],'binding_sha256'=>$b->sha256,'phase'=>$phase]).PHP_EOL);fflush($pipes[0]);stream_set_timeout($pipes[1],20);
        $report=json_decode((string)fgets($pipes[1]),true);$running=proc_get_status($process)['running'];
        fc_ok('Real '.$phase.' worker pauses with its actual dispatch mutex',$running&&is_array($report)&&$report['phase']===$phase&&$report['mutex_owned']&&!$report['in_transaction']&&$report['dns']===1&&$report['posts']===1&&$report['status']===($phase==='finished'?'uncertain':'claimed')&&$report['resolved']===($phase==='finished')&&$report['attempts']===($phase==='finished'?1:0));
        $source=fc_recovery_sources($id);$before=fc_recovery_state();
        $digest=provider_force_ssl_controls_ledger_digest($source[2]);
        fc_deny_message('Real '.$phase.' worker denies concurrent recovery on the mutex',fn()=>provider_force_ssl_controls_recover_finished($id,$digest,$password),'Another account change is in progress. Please retry.');
        fc_ok('Concurrent '.$phase.' recovery cannot create a witness or provider request',fc_recovery_sources($id)===$source&&fc_recovery_state()===$before&&proc_get_status($process)['running']);
    }finally{
        // Terminate only the exact process this fixture created; never a name/process-wide kill.
        proc_terminate($process);$until=microtime(true)+5;do{$state=proc_get_status($process);if(!$state['running'])break;usleep(20000);}while(microtime(true)<$until);
        foreach($pipes as $pipe)fclose($pipe);proc_close($process);
    }
    fc_ok('Exact '.$phase.' worker has exited before the next local action',!$state['running']);
    return [$id,$source,$digest];
}

$recoverySources=fc_recovery_sources($terminalId);$recoveryState=fc_recovery_state();$reviewHash=fc_summary($terminalId)['recovery_ledger_sha256'];
fc_ok('Missing local witness with a durable finished ledger offers exact owner recovery',is_string($reviewHash)&&$reviewHash===provider_force_ssl_controls_ledger_digest($terminalLedger)&&fc_summary($terminalId)['status']==='interrupted');
ob_start();provider_force_ssl_controls_admin_render();$recoveryHtml=(string)ob_get_clean();fc_ok('Owner GET offers reviewed recovery without creating evidence',str_contains($recoveryHtml,'data-force-ssl-control-recover')&&str_contains($recoveryHtml,'Restore local dispatch record')&&fc_recovery_state()===$recoveryState);
fc_deny('Revoked owner refuses: Recovery requires the current owner password',fn()=>cwp_test_owner_revoked(fn()=>provider_force_ssl_controls_recover_finished($terminalId,$reviewHash,'wrong')));
fc_actor(2);fc_deny('Administrator cannot recover a completed dispatch',fn()=>fc_recover($terminalId,$reviewHash));fc_actor(3);fc_deny_message('Another owner cannot adopt original approval',fn()=>fc_recover($terminalId,$reviewHash),'The original Force SSL approval owner or context changed.');fc_ok('Other owner receives no recovery form hash',fc_summary($terminalId)['recovery_ledger_sha256']===null);fc_actor();
$_GET['support']='fixture';fc_deny('Support context cannot recover a completed dispatch',fn()=>fc_recover($terminalId,$reviewHash));unset($_GET['support']);
fc_deny_message('Changed reviewed ledger hash refuses locally',fn()=>fc_recover($terminalId,str_repeat('f',64)),'The reviewed dispatch completion changed. Reload its history.');
db()->beginTransaction();try{fc_deny('Recovery rejects a caller transaction',fn()=>fc_recover($terminalId,$reviewHash));fc_ok('Recovery leaves caller transaction intact',db()->inTransaction());}finally{db()->rollBack();}
// Actual changed authority is persisted outside the recovery transaction, then restored exactly.
$oldAuth=one('SELECT auth_version FROM '.table('users').' WHERE id=1')['auth_version'];query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);fc_actor();
try{fc_deny_message('Changed original owner authentication refuses recovery',fn()=>fc_recover($terminalId,$reviewHash),'The original Force SSL approval owner or context changed.');}finally{query('UPDATE '.table('users').' SET auth_version=? WHERE id=1',[$oldAuth]);fc_actor();}
query('UPDATE '.table('provider_operation_grants').' SET revoked_at=? WHERE id=?',[gmdate('Y-m-d H:i:s'),$terminalId]);try{fc_deny_message('Revoked original approval refuses recovery',fn()=>fc_recover($terminalId,$reviewHash),'The original Force SSL approval owner or context changed.');}finally{query('UPDATE '.table('provider_operation_grants').' SET revoked_at=NULL WHERE id=?',[$terminalId]);}
$oldCredentials=provider_settings_load($profile)['credentials'];query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',['changed-synthetic-seal',$profile]);try{fc_deny('Changed sealed provider credential refuses recovery without opening it',fn()=>fc_recover($terminalId,$reviewHash));}finally{query('UPDATE '.table('provider_profiles').' SET credentials=? WHERE id=?',[$oldCredentials,$profile]);}
$originalClient=$link['client_id'];query('UPDATE '.table('provider_package_links').' SET client_id=2 WHERE id=?',[$link['id']]);try{fc_deny('Changed current client assignment refuses captured recovery',fn()=>fc_recover($terminalId,$reviewHash));}finally{query('UPDATE '.table('provider_package_links').' SET client_id=? WHERE id=?',[$originalClient,$link['id']]);}
fc_ok('All owner form and authority refusals preserve exact source and evidence',fc_recovery_sources($terminalId)===$recoverySources&&fc_recovery_state()===$recoveryState);
fc_ok('Recovery terminal audit failure is an actual transactional rollback',fc_fault('force_ssl_control_terminal',fn()=>fc_recover($terminalId,$reviewHash))&&fc_recovery_state()===$recoveryState&&fc_recovery_sources($terminalId)===$recoverySources&&provider_force_ssl_controls_ready());
db()->exec('CREATE TRIGGER fcontrol_recovery_count_fault BEFORE UPDATE ON '.table('settings')." FOR EACH ROW BEGIN IF NEW.name='provider_force_ssl_control_terminal_count' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic recovery count failure'; END IF; END");
try{try{fc_recover($terminalId,$reviewHash);fc_ok('Actual recovery counter failure propagates',false);}catch(PDOException){fc_ok('Actual recovery counter failure propagates',true);}}finally{db()->exec('DROP TRIGGER fcontrol_recovery_count_fault');}
fc_ok('Recovery counter failure rolls back terminal and audit together',fc_recovery_state()===$recoveryState&&fc_recovery_sources($terminalId)===$recoverySources&&provider_force_ssl_controls_ready());
$restored=fc_recover($terminalId,$reviewHash);$recoveredTerminal=one('SELECT * FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$terminalId]);
fc_ok('Recovery appends one attempted witness but keeps effect uncertain',$restored['status']==='uncertain'&&$restored['recovered_dispatch']&&$restored['recovery_ledger_sha256']===null&&$recoveredTerminal['disposition']==='attempted'&&json_decode($recoveredTerminal['document_json'],true)['recovered_from_ledger']===true&&fc_count('provider_force_ssl_dispatch_ends')===$terminalCount+1);
fc_ok('Recovery preserves original request approval ledger sharing and provider counts',fc_recovery_sources($terminalId)===$recoverySources&&fc_count('provider_refreshes')===$recoveryState[3]&&fc_count('provider_force_ssl_permissions')===$recoveryState[4]&&[$dns,$writes,$sent]===array_slice($recoveryState,5)&&fc_count('audit')===count($recoveryState[1])+1&&provider_force_ssl_controls_counter('terminal')===$terminalCount+1);
$afterRecovery=fc_recovery_state();fc_deny_message('Replayed recovery cannot replace an existing witness',fn()=>fc_recover($terminalId,$reviewHash),'This request already has a dispatch record. No recovery is needed.');
fc_ok('Recovery replay preserves the exact witness and audit count',fc_recovery_state()===$afterRecovery);
fc_deny_message('Recovered request still fences a new provider write',fn()=>fc_change(),'A previous Force SSL change is unresolved. It cannot be sent again.');
fc_ok('Restored local record alone cannot make the current client flag authoritative',staff_locked(fn()=>staff_transaction(fn()=>!provider_force_ssl_controls_current($link['id']))));
// A delayed original local completion cannot replace the recovered primary-key witness.
try{staff_locked(fn()=>staff_transaction(fn()=>provider_force_ssl_controls_end(provider_force_ssl_controls_request($terminalId),'attempted')));fc_ok('Delayed original terminal collision is refused',false);}catch(PDOException){fc_ok('Delayed original terminal collision is refused',true);}
fc_ok('Delayed terminal collision rolls back without modifying recovered history',fc_recovery_state()===$afterRecovery&&fc_recovery_sources($terminalId)===$recoverySources&&provider_force_ssl_controls_ready());
// Committed witness loss is not the honest missing-witness window. Preserve actual deletion proof.
fc_ok('Committed recovery witness was actually removed',query('DELETE FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$terminalId])->rowCount()===1);
try{fc_deny_message('Lost committed witness cannot be reconstructed over retained count and audit',fn()=>fc_recover($terminalId,$reviewHash),'Force SSL change history counts disagree.');fc_ok('Denied lost-witness recovery does not repair the missing record',one('SELECT id FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$terminalId])===null);}finally{query('INSERT INTO '.table('provider_force_ssl_dispatch_ends').' ('.implode(',',array_keys($recoveredTerminal)).') VALUES ('.implode(',',array_fill(0,count($recoveredTerminal),'?')).')',array_values($recoveredTerminal));}
fc_ok('Exact committed witness restoration restores all original evidence',fc_recovery_state()===$afterRecovery&&provider_force_ssl_controls_ready());
$recoveredDetail='Force SSL change terminal '.$terminalId.' '.provider_force_ssl_digest(provider_force_ssl_controls_values('terminal',$recoveredTerminal));$recoveredAudit=one('SELECT * FROM '.table('audit').' WHERE action=? AND detail=?',['provider.force_ssl_control_terminal',$recoveredDetail]);
fc_ok('Paired recovery loss removes the exact committed witness and audit',query('DELETE FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$terminalId])->rowCount()===1&&query('DELETE FROM '.table('audit').' WHERE id=? AND action=? AND detail=?',[$recoveredAudit['id'],'provider.force_ssl_control_terminal',$recoveredDetail])->rowCount()===1);
try{fc_deny_message('Independent terminal count fences paired committed witness and audit loss',fn()=>fc_recover($terminalId,$reviewHash),'Force SSL change history counts disagree.');}finally{foreach([['provider_force_ssl_dispatch_ends',$recoveredTerminal],['audit',$recoveredAudit]] as [$tableName,$savedRow])query('INSERT INTO '.table($tableName).' ('.implode(',',array_keys($savedRow)).') VALUES ('.implode(',',array_fill(0,count($savedRow),'?')).')',array_values($savedRow));}
fc_ok('Exact paired restoration preserves original witness audit and independent count',fc_recovery_state()===$afterRecovery&&provider_force_ssl_controls_ready());
foreach(['unavailable',null] as $laterState){$plan=fc_check_plan($terminalId);$receipt=fc_seed_check($plan,'false');fc_seed_force_ssl($plan['binding'],$laterState,time()-40,3);$checksBefore=fc_count('provider_force_ssl_change_checks');fc_deny_message('Recovered dispatch rejects receipt superseded by '.($laterState??'unfinished').' read',fn()=>fc_save_check($plan,$receipt),'The exact Force SSL verification read is no longer current.');fc_ok('Superseded recovered receipt leaves request unresolved '.($laterState??'unfinished'),fc_count('provider_force_ssl_change_checks')===$checksBefore&&fc_summary($terminalId)['status']==='uncertain'&&fc_recovery_sources($terminalId)===$recoverySources);}
// Use the real shared executor GET; recovery itself performed no read. Follow the requested false flag.
$body='false';$last=(int)query('SELECT MAX(started_epoch) FROM '.table('provider_refreshes').' WHERE profile_id=?',[$profile])->fetchColumn();$wait=max(0,$last+31-time());if($wait>0)sleep($wait);$restoredObserved=provider_force_ssl_controls_verify($terminalId,$password,'fc_dns','fc_read');
fc_ok('Recovered attempt needs a separate actual GET before matching observation',$restoredObserved['status']==='observed'&&$restoredObserved['last_check']['value']===false&&$sent===$recoveryState[7]+1&&$writes===$recoveryState[6]&&fc_recovery_sources($terminalId)===$recoverySources&&one('SELECT * FROM '.table('provider_force_ssl_dispatch_ends').' WHERE id=?',[$terminalId])===$recoveredTerminal);
// A real second process is stopped only after proving its durable record boundary and held mutex.
[$workerId,$workerSource,$workerDigest]=fc_recovery_worker('finished');$before=fc_recovery_state();$workerRecovered=fc_recover($workerId,$workerDigest);
fc_ok('Finished worker interruption permits local witness recovery after exit',$workerRecovered['status']==='uncertain'&&$workerRecovered['recovered_dispatch']&&fc_recovery_sources($workerId)===$workerSource&&[$dns,$writes,$sent]===array_slice($before,5));
$q=provider_force_ssl_controls_request($workerId);$delayed=fc_perform(provider_settings_load($profile),$secret,'8.8.8.8',static fn()=>throw new LogicException('A finished replay must not reach dispatch authorization.'),new \CajunWebPro\Foundation\Provider\ProviderOperation('packages.forcessl',$q['grant']['consumed_key'],$q['document']['params']));
fc_ok('Delayed real gateway entry replays finished row without reaching another adapter',$delayed->replayed&&fc_recovery_sources($workerId)===$workerSource&&[$dns,$writes,$sent]===array_slice($before,5));
$plan=fc_check_plan($workerId);$receipt=fc_seed_check($plan,'true');fc_ok('Independent later receipt resolves only recovered worker request',fc_save_check($plan,$receipt)['status']==='observed'&&fc_recovery_sources($workerId)===$workerSource);
[$outstandingId,$outstandingSource,$outstandingDigest]=fc_recovery_worker('outstanding');$before=fc_recovery_state();
fc_deny_message('Stopped outstanding worker has no eligible durable completion',fn()=>fc_recover($outstandingId,$outstandingDigest),'This request has no eligible durable dispatch completion.');
fc_ok('Outstanding worker remains fenced without history repair or provider traffic',fc_summary($outstandingId)['status']==='interrupted'&&fc_summary($outstandingId)['recovery_ledger_sha256']===null&&fc_recovery_sources($outstandingId)===$outstandingSource&&fc_recovery_state()===$before);
