<?php
/** Test-only current-owner denial fixture. Never loaded by the application. */
declare(strict_types=1);

/** Hash all rows and table definitions in this disposable test's exact namespace. */
function cwp_test_authority_snapshot(): array {
    $cfg=config();$prefix=$cfg['db']['prefix']??'';
    if(PHP_SAPI!=='cli'||getenv('CWP_TEST_DATABASE')!=='cwp_test'
        ||($cfg['db']['name']??'')!=='cwp_test'||!in_array($cfg['db']['host']??'',['127.0.0.1','localhost'],true)
        ||!is_string($prefix)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D',$prefix)) {
        throw new AssertionError('Current-owner fixture requires disposable loopback SQL.');
    }
    $tables=[];
    foreach(all('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND LEFT(TABLE_NAME,?)=? AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME',[strlen($prefix),$prefix]) as $entry){
        $name=$entry['TABLE_NAME'];
        if(!is_string($name)||!str_starts_with($name,$prefix)||!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new AssertionError('Unsafe synthetic table identity.');
        $rows=array_map(static fn(array $row):string=>serialize($row),all('SELECT * FROM `'.$name.'`'));sort($rows,SORT_STRING);
        $schema=one('SHOW CREATE TABLE `'.$name.'`');
        // Sequence gaps are not data loss; every stored row and structural definition remains covered.
        $definition=preg_replace('/ AUTO_INCREMENT=[0-9]+\b/','',(string)($schema['Create Table']??''));
        if($definition==='')throw new AssertionError('Missing synthetic schema evidence.');
        $tables[$name]=['rows'=>count($rows),'sha256'=>hash('sha256',serialize($rows)),'schema'=>hash('sha256',$definition)];
    }
    if(!$tables)throw new AssertionError('Empty authority fixture cannot prove retained state.');
    ksort($tables);
    $storage=realpath((string)($cfg['storage']??''));$runtime=realpath(__DIR__.'/../test-runtime');
    if(!$storage||!$runtime||!str_starts_with($storage,$runtime.DIRECTORY_SEPARATOR))throw new AssertionError('Private test storage must remain under test-runtime.');
    $files=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage,FilesystemIterator::SKIP_DOTS)) as $file){
        if($file->isLink())throw new AssertionError('Private fixture symlinks are not allowed.');
        if($file->isFile())$files[substr($file->getPathname(),strlen($storage)+1)]=[hash_file('sha256',$file->getPathname()),$file->getPerms()&0777];
    }
    ksort($files);return ['tables'=>$tables,'files'=>$files];
}

/**
 * Execute the unchanged mutation callback after revoking the SAME signed-in
 * owner's role. Re-throw only an actual recognized authority refusal. A silent
 * success, unrelated error, SQL fault, invalid fixture or changed row/key fails
 * with AssertionError, which the legacy denial helpers do not treat as success.
 */
function cwp_test_owner_revoked(callable $work): mixed {
    if(db()->inTransaction())throw new AssertionError('Owner-revocation fixture cannot inherit a transaction.');
    $session=$_SESSION;$get=$_GET;$actor=admin_user();$prepared=staff_ready();
    if(!$actor||($actor['role']??'')!=='owner'||!admin_session_matches($actor))throw new AssertionError('Start the refusal with a genuine current owner session.');
    $original=cwp_test_authority_snapshot();$profile=null;$error=null;$after=null;
    if($prepared){
        $profile=one('SELECT role,permissions FROM '.table('staff_profiles').' WHERE user_id=?',[$actor['id']]);
        if(!$profile||$profile['role']!=='owner')throw new AssertionError('Expected current owner profile.');
    }
    try {
        if($prepared)query('UPDATE '.table('staff_profiles')." SET role='administrator',permissions='[]' WHERE user_id=?",[$actor['id']]);
        else $_GET['support']='synthetic-initial-owner-boundary';
        $revoked=cwp_test_authority_snapshot();
        try{$work();}catch(Throwable $caught){$error=$caught;}
        if(db()->inTransaction())throw new AssertionError('Refused operation leaked a transaction.');
        $after=cwp_test_authority_snapshot();
        if($after!==$revoked)throw new AssertionError('Refused operation changed stored rows, schema or private bytes.');
    } finally {
        if(db()->inTransaction())db()->rollBack();
        if($profile!==null)query('UPDATE '.table('staff_profiles').' SET role=?,permissions=? WHERE user_id=?',[$profile['role'],$profile['permissions'],$actor['id']]);
        $_SESSION=$session;$_GET=$get;
    }
    if(cwp_test_authority_snapshot()!==$original)throw new AssertionError('Authority fixture did not restore the original stored state.');
    $messages=$prepared?[
        'Your account does not have access to this action.',
        'An active owner account is required for provider settings.',
        'Only an owner can manage email accounts and assignments.',
        'An active owner account and prepared client workspace are required.',
        'A currently authorized owner account is required.',
        'Only an owner can verify administrator access.',
    ]:['Your administrator session is no longer valid. Sign in again.'];
    if(!($error instanceof RuntimeException||$error instanceof InvalidArgumentException)
        ||$error instanceof PDOException||!in_array($error->getMessage(),$messages,true)){
        throw new AssertionError('Mutation did not produce its current-authority refusal: '.($error?get_class($error).': '.$error->getMessage():'operation returned successfully'));
    }
    $GLOBALS['cwp_current_authority_evidence'][]=['mode'=>$prepared?'owner-role-revoked':'initial-owner-support-denied','tables'=>count($original['tables']),'private_files'=>count($original['files']),'state_unchanged'=>true,'refusal'=>$error->getMessage()];
    throw $error;
}
