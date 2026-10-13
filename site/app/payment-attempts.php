<?php
/** Owner-only sandbox trials. Sandbox results never settle an actual invoice. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;

function attempts_schema(string $prefix): array {
    if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,19}$/D',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $t=fn($n)=>'`'.$prefix.$n.'`';$e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        'payment_attempts'=>'CREATE TABLE '.$t('payment_attempts').' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id INT UNSIGNED NOT NULL UNIQUE,invoice_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,attempt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,reference CHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,environment VARCHAR(10) NOT NULL,account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,gateway_revision INT UNSIGNED NOT NULL,amount_cents INT UNSIGNED NOT NULL,currency CHAR(3) NOT NULL,state VARCHAR(30) NOT NULL,transaction_id VARCHAR(20) NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE gateway_transaction(environment,account_hash,transaction_id),INDEX invoice_state(invoice_id,state))'.$e,
        'payment_attempt_events'=>'CREATE TABLE '.$t('payment_attempt_events').' (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,attempt_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,event VARCHAR(30) NOT NULL,created_at DATETIME NOT NULL,INDEX attempt_history(attempt_id,id))'.$e,
    ];
}
function attempts_ready(): bool {return payments_ready()&&setting('payment_attempts_schema_version')==='1';}
function attempts_migrate(#[\SensitiveParameter] string $password): void {
    staff_locked(function() use($password): void {
        $actor=payments_owner($password);if(!payments_ready())throw new RuntimeException('Enable payment preparation first.');
        $missing=[];
        foreach(attempts_schema(config()['db']['prefix']) as $name=>$sql){
            if(one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name])){
                catalog_validate_schema($name,$sql,$name==='payment_attempts'?['request_id'=>['request_id'],'attempt_key'=>['attempt_key'],'reference'=>['reference'],'gateway_transaction'=>['environment','account_hash','transaction_id']]:[]);
                foreach(all('SHOW FULL COLUMNS FROM '.table($name)) as $c)if(in_array($c['Field'],['attempt_key','reference','account_hash'],true)&&$c['Collation']!=='ascii_bin')throw new RuntimeException('Incompatible sandbox identifiers.');
            }else $missing[]=$sql;
        }
        foreach($missing as $sql)db()->exec($sql);
        staff_transaction(function() use($actor): void {
            if(setting('payment_attempts_schema_version')!=='1')foreach(array_keys(attempts_schema(config()['db']['prefix'])) as $name)if((int)query('SELECT COUNT(*) FROM '.table($name))->fetchColumn())throw new RuntimeException('Unrecognized sandbox records exist. No data was adopted.');
            save_setting('payment_attempts_schema_version','1');payments_audit((int)$actor['id'],'sandbox.setup','Additive trial ledger 1; real invoice settlement disabled');
        });
    });
}
/** Caller holds the staff mutex and transaction, shared by all financial changes. */
function attempts_assert_clear(int $invoice): void {
    if(attempts_ready()&&one('SELECT id FROM '.table('payment_attempts')." WHERE invoice_id=? AND state IN ('submitting','unknown','held') LIMIT 1",[$invoice]))throw new RuntimeException('Verify the unresolved sandbox attempt before changing this invoice or its request.');
}
function attempts_event(int $id,int $actor,string $event): void {
    query('INSERT INTO '.table('payment_attempt_events').' (attempt_id,actor_id,event,created_at) VALUES (?,?,?,?)',[$id,$actor,$event,gmdate('Y-m-d H:i:s')]);
    payments_audit($actor,'sandbox.'.$event,'Attempt '.$id.'; no real invoice settlement');
}
function attempts_account(#[\SensitiveParameter] array $credentials): string {return hash('sha256','sandbox|'.$credentials['api_login_id']);}
function attempts_credentials(int $revision): array {
    $g=one('SELECT * FROM '.table('payment_gateways')." WHERE environment='sandbox'");
    if(!$g||(int)$g['revision']!==$revision||$g['validation']!=='authenticated')throw new RuntimeException('Check the current sandbox credentials before running a trial.');
    return payments_unseal('sandbox',$g['credentials']);
}
function attempts_require(#[\SensitiveParameter] string $password,?callable $transport): array {
    if($transport!==null&&PHP_SAPI!=='cli')throw new LogicException('Fixture transport is CLI-only.');
    if(!attempts_ready())throw new RuntimeException('Enable sandbox trials first.');
    if(PHP_SAPI!=='cli'&&!https_request())throw new RuntimeException('Sandbox trials require HTTPS.');
    return payments_owner($password);
}
/** Commit the intent before sending; one attempt per request, with no automatic retries. */
function attempts_submit(int $request,int $revision,string $key,#[\SensitiveParameter] string $nonce,#[\SensitiveParameter] string $password,?callable $transport=null): int {
    if(!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-zA-Z0-9+\/=_.-]{1,4096}$/D',$nonce))throw new InvalidArgumentException('Obtain a fresh sandbox payment token.');
    $start=staff_locked(function() use($request,$revision,$key,$password,$transport): array {
        $actor=attempts_require($password,$transport);
        $prior=one('SELECT * FROM '.table('payment_attempts').' WHERE request_id=? OR attempt_key=?',[$request,$key]);
        if($prior){if((int)$prior['request_id']!==$request||!hash_equals($prior['attempt_key'],$key)||(int)$prior['actor_id']!==(int)$actor['id'])throw new RuntimeException('This request already has a sandbox attempt. Review its history.');return ['id'=>(int)$prior['id']];}
        if(!consume_rate('sandbox-submit-'.$actor['id'],10,900))throw new RuntimeException('Wait before starting more sandbox trials.');
        return ['credentials'=>attempts_credentials($revision)];
    });
    if(isset($start['id']))return $start['id'];
    $credentials=$start['credentials'];
    // Reporting preflight cannot charge. Require a USD-only sandbox merchant outside test mode.
    if(!authorize_sandbox_merchant($credentials,$transport))throw new RuntimeException('Sandbox merchant could not be verified as USD-only with test mode off. No transaction was submitted.');
    $attempt=staff_locked(fn()=>staff_transaction(function() use($request,$revision,$key,$password,$transport,$credentials): array {
        $actor=attempts_require($password,$transport);$current=attempts_credentials($revision);
        if(!hash_equals(attempts_account($current),attempts_account($credentials)))throw new RuntimeException('Sandbox account changed. Reload.');
        $prior=one('SELECT * FROM '.table('payment_attempts').' WHERE request_id=? OR attempt_key=?',[$request,$key]);
        if($prior){if((int)$prior['request_id']!==$request||!hash_equals($prior['attempt_key'],$key)||(int)$prior['actor_id']!==(int)$actor['id'])throw new RuntimeException('This request already has a sandbox attempt.');return ['existing'=>(int)$prior['id']];}
        $r=one('SELECT * FROM '.table('payment_requests').' WHERE id=? FOR UPDATE',[$request]);
        if(!$r||payments_request_state($r)!=='prepared')throw new RuntimeException('Review a current prepared request before starting a trial.');
        attempts_assert_clear((int)$r['invoice_id']);$now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('payment_attempts').' (request_id,invoice_id,actor_id,attempt_key,reference,environment,account_hash,gateway_revision,amount_cents,currency,state,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$request,$r['invoice_id'],$actor['id'],$key,'CW'.bin2hex(random_bytes(9)),'sandbox',attempts_account($credentials),$revision,$r['amount_cents'],'USD','submitting',$now,$now]);
        $id=(int)db()->lastInsertId();attempts_event($id,(int)$actor['id'],'submitted');return one('SELECT * FROM '.table('payment_attempts').' WHERE id=?',[$id]);
    }));
    if(isset($attempt['existing']))return $attempt['existing'];
    // Only this process owns the dispatch. Failure/crash leaves a durable unresolved intent.
    $transaction=authorize_sandbox_charge($credentials,$attempt,$nonce,$transport);
    $facts=$transaction!==null?authorize_sandbox_details($credentials,$attempt,$transaction,$transport):['state'=>'unknown','transaction_id'=>null];
    attempts_record($attempt,$facts,(int)$attempt['actor_id'],'response');return (int)$attempt['id'];
}
/** Persist only verified safe facts; stale network replies cannot regress a newer observation. */
function attempts_record(array $expected,array $facts,int $actor,string $source): void {
    staff_locked(fn()=>staff_transaction(function() use($expected,$facts,$actor,$source): void {
        $r=one('SELECT * FROM '.table('payment_attempts').' WHERE id=? FOR UPDATE',[$expected['id']]);
        if(!$r||$r['state']!==$expected['state']||$r['updated_at']!==$expected['updated_at']||$r['transaction_id']!==$expected['transaction_id'])return;
        // Another response may have appended an identical state within the same second.
        if((int)query('SELECT COUNT(*) FROM '.table('payment_attempt_events').' WHERE attempt_id=?',[$r['id']])->fetchColumn()!==(int)($expected['event_count']??1))return;
        $state=$facts['state'];$transaction=$facts['transaction_id']??$r['transaction_id'];
        if(!in_array($state,['unknown','held','captured','settled','declined','voided','expired'],true))throw new LogicException('Invalid sandbox observation.');
        if(in_array($r['state'],['settled','declined','voided','expired'],true)&&$state!==$r['state'])throw new RuntimeException('Conflicting terminal observation. Retained result unchanged; investigate in the sandbox portal.');
        if($r['state']==='captured'&&!in_array($state,['captured','settled','voided'],true))throw new RuntimeException('Conflicting capture observation. Retained result unchanged.');
        if($transaction!==null&&one('SELECT id FROM '.table('payment_attempts').' WHERE environment=? AND account_hash=? AND transaction_id=? AND id<>?',[$r['environment'],$r['account_hash'],$transaction,$r['id']])){$state='unknown';$transaction=$r['transaction_id'];}
        query('UPDATE '.table('payment_attempts').' SET state=?,transaction_id=?,updated_at=? WHERE id=?',[$state,$transaction,gmdate('Y-m-d H:i:s'),$r['id']]);
        attempts_event((int)$r['id'],$actor,$source.'.'.$state);
    }));
}
function attempts_reconcile(int $id,string $transaction,int $revision,#[\SensitiveParameter] string $password,?callable $transport=null): void {
    if(!preg_match('/^[1-9][0-9]{0,19}$/D',$transaction))throw new InvalidArgumentException('Enter the sandbox transaction ID to verify.');
    [$actor,$attempt,$credentials]=staff_locked(function() use($id,$transaction,$revision,$password,$transport): array {
        $actor=attempts_require($password,$transport);$r=one('SELECT * FROM '.table('payment_attempts').' WHERE id=?',[$id]);
        if(!$r||$r['environment']!=='sandbox')throw new RuntimeException('Sandbox attempt not found.');
        if($r['transaction_id']!==null&&!hash_equals($r['transaction_id'],$transaction))throw new RuntimeException('Use the transaction already attached to this attempt.');
        if(!consume_rate('sandbox-reconcile-'.$actor['id'],20,900))throw new RuntimeException('Wait before requesting more sandbox checks.');
        $credentials=attempts_credentials($revision);if(!hash_equals($r['account_hash'],attempts_account($credentials)))throw new RuntimeException('Restore credentials for the original sandbox account before reconciling.');
        $r['event_count']=(int)query('SELECT COUNT(*) FROM '.table('payment_attempt_events').' WHERE attempt_id=?',[$id])->fetchColumn();
        return [$actor,$r,$credentials];
    });
    if(!authorize_sandbox_merchant($credentials,$transport))throw new RuntimeException('Sandbox merchant could not be verified. Attempt unchanged.');
    $facts=authorize_sandbox_details($credentials,$attempt,$transaction,$transport);
    // Failed lookups do not erase a previously verified observation or bind a typed ID.
    if($facts['state']==='unknown')throw new RuntimeException('The transaction could not be matched and verified. Attempt unchanged; do not retry the charge.');
    attempts_record($attempt,$facts,(int)$actor['id'],'verified');
}
/** Existing free-form invoices now use the same mutex and strict audit as catalog/payment changes. */
function payments_manual_paid(int $job,int $invoice,string $note): void {
    if(strlen(trim($note))<5||strlen($note)>500)throw new InvalidArgumentException('Record a verified payment reference (5–500 characters).');
    staff_locked(fn()=>staff_transaction(function() use($job,$invoice,$note): void {
        if(support_current())throw new RuntimeException('Payment changes are unavailable during a support session.');
        $actor=staff_require('clients.manage');$r=one('SELECT * FROM '.table('studio_invoices').' WHERE id=? AND job_id=? FOR UPDATE',[$invoice,$job]);
        if(!$r||$r['status']!=='issued')throw new RuntimeException('Invoice not found or already recorded as paid.');
        if(catalog_snapshot($invoice))throw new RuntimeException('Use the catalog invoice payment action.');
        attempts_assert_clear($invoice);
        query('UPDATE '.table('studio_invoices')." SET status='paid',paid_note=?,paid_at=? WHERE id=?",[$note,gmdate('Y-m-d H:i:s'),$invoice]);
        payments_audit((int)$actor['id'],'manual.paid','Invoice '.$invoice.' project '.$job);
    }));
}
