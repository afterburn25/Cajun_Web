<?php
/** Independent PR111 review after its unmodified synthetic native fixture; no network. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('CWP_TEST_DATABASE') !== 'cwp_test') exit(2);
define('CWP_BOOT', true);
require __DIR__.'/../site/includes/bootstrap.php';
require_once __DIR__.'/../site/app/providers/operations.php';
require_once __DIR__.'/../site/admin/controllers/providers.php';
require_once __DIR__.'/../site/includes/layout/admin.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,OperationResult,ProviderCredential,ProviderGateway,ProviderOperation,TransportRequest,TransportResponse};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationAdapter;

$private = realpath(__DIR__.'/../test-runtime/provider-execution-private');
if (!$private) throw new RuntimeException('Run the supplied execution fixture first.');
$GLOBALS['cwp_config'] = ['site_url'=>'http://127.0.0.1:8091/existing','storage'=>$private,'key'=>str_repeat('synthetic-exec-key-',4),'db'=>['host'=>getenv('CWP_TEST_HOST')?:'127.0.0.1','port'=>(int)(getenv('CWP_TEST_PORT')?:33306),'name'=>'cwp_test','user'=>getenv('CWP_TEST_USER')?:'cwp_test','pass'=>getenv('CWP_TEST_PASSWORD')?:'local-ci-only','prefix'=>'pex_']];
$_SERVER += ['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8091','SCRIPT_NAME'=>'/index.php','REQUEST_METHOD'=>'GET'];
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__.'/../site'); start_session();
$owner = one('SELECT * FROM '.table('users').' WHERE id=1');
$_SESSION = ['uid'=>1,'auth_version'=>$owner['auth_version'],'login_at'=>time(),'last_activity'=>time()];
$password = 'Synthetic execution owner passphrase';
$expectedSecret = 'SYNTHETIC_PRIVATE_GENERAL_KEY';
$checks = []; $observations = []; $sent = []; $responses = [];
function er_check(string $name, bool $passed): void {
    global $checks; $checks[] = ['name'=>$name,'passed'=>$passed];
}
final class ExecutionReviewTransport implements HttpTransport {
    public function send(#[SensitiveParameter] TransportRequest $request): TransportResponse {
        global $sent,$responses;
        $sent[] = $request;
        return array_shift($responses) ?? new TransportResponse(200, [], '{}');
    }
}
function er_perform(array $row, #[SensitiveParameter] string $secret, string $address, callable $authorize, ProviderOperation $operation): OperationResult {
    global $expectedSecret;
    if ($secret !== $expectedSecret || $address !== '8.8.8.8') throw new LogicException('Review boundary mismatch.');
    // Same accountIdentity and credential resolver as production provider_operations_perform.
    $credential = new ProviderCredential('twenty-i', $row['id'], static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(), new ExecutionReviewTransport(), new ProviderOperationLedger()))->perform($credential, $operation);
}
function er_resolve(): string {
    if (db()->inTransaction()) throw new LogicException('Unexpected transaction at resolver seam.');
    return '8.8.8.8'; // Test seam only: never calls DNS or a network transport.
}
function er_execute(string $profile,int $revision,string $grant,string $key,?callable $resolver=null):OperationResult {
    global $password;
    return provider_operations_execute($profile,$revision,$grant,$key,$password,$resolver??'er_resolve','er_perform');
}
try {
    $profile = provider_settings_profile();
    if (!$profile || !provider_operations_ready()) throw new RuntimeException('Supplied fixture did not complete.');
    $id = $profile['id']; $revision = (int)$profile['revision'];
    er_check('Supplied execution fixture has an enabled owner profile', $profile['enabled']===1 || $profile['enabled']==='1');
    $register = new CajunWebPro\Foundation\TwentyI\Operations\TwentyIOperationRegister();
    er_check('Namespaced register is loaded and has buildable operations', count($register->buildableNames())===2);

    // Render the actual view, with the real bootstrap/controller/layout and native owner fixture.
    // No class alias or replacement view is installed to hide file-scoped import defects.
    $viewError = null; ob_start();
    try { require CWP_CODE_ROOT.'/admin/views/operations.php'; }
    catch (Throwable $e) { $viewError = get_class($e).': '.$e->getMessage(); }
    finally { $view = ob_get_clean(); }
    $observations['actual_view'] = ['error'=>$viewError,'rendered_approval_heading'=>str_contains($view,'Approved 20i operations')];
    er_check('Actual operations view renders for the configured owner', $viewError===null && str_contains($view,'Approved 20i operations'));

    $scope = ['resellerId'=>'*','type'=>'synthetic','domain_name'=>'original-account.example'];
    $grant = provider_operation_grant($id,'packages.create',$scope,$password);
    $result = er_execute($id,$revision,$grant,'review-original-account');
    er_check('Unchanged approved account completes one scripted request', $result->status===OperationResult::SUCCEEDED && count($sent)===1 && !$result->replayed);
    er_check('Normal completed result is durably recorded', one('SELECT status FROM '.table('provider_operations').' WHERE idempotency_key=?',['review-original-account'])['status']==='succeeded');

    $before = (int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn();
    $refused = false;
    try { provider_operation_grant($id,'packages.create',['resellerId'=>'*'],$password); }
    catch (InvalidArgumentException) { $refused=true; }
    $after = (int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn();
    $observations['missing_required_fields'] = ['approval_refused'=>$refused,'added_grants'=>$after-$before];
    er_check('Approval rejects missing required request fields before storing a grant', $refused && $after===$before);

    $oldScope = ['resellerId'=>'*','type'=>'synthetic','domain_name'=>'approved-before-replacement.example'];
    $oldGrant = provider_operation_grant($id,'packages.create',$oldScope,$password);
    provider_settings_save($id,$revision,'Replacement account fixture',true,'SYNTHETIC_REPLACEMENT_GENERAL_KEY',$password);
    $revision = (int)provider_settings_load($id)['revision'];
    $expectedSecret = 'SYNTHETIC_REPLACEMENT_GENERAL_KEY';
    $before = count($sent); $refused = false; $result=null;
    try { $result = er_execute($id,$revision,$oldGrant,'review-old-approval-new-account'); }
    catch (InvalidArgumentException|RuntimeException $e) { if($e instanceof PDOException)throw $e; $refused=true; }
    $delta = count($sent)-$before;
    $observations['credential_replacement'] = ['old_grant_refused'=>$refused,'status'=>$result?->status,'sends'=>$delta,'replacement_authorization_used'=>$delta>0 && $sent[array_key_last($sent)]->headers['Authorization']==='Bearer '.base64_encode($expectedSecret)];
    er_check('An approval for the old credential cannot dispatch on its replacement', $delta===0 && ($refused || $result?->status!==OperationResult::SUCCEEDED));

    $freshScope = ['resellerId'=>'*','type'=>'synthetic','domain_name'=>'new-account-approval.example'];
    $freshGrant = provider_operation_grant($id,'packages.create',$freshScope,$password);
    $before = count($sent); $result = er_execute($id,$revision,$freshGrant,'review-fresh-account-approval');
    er_check('Fresh approval works on the replacement credential', $result->status===OperationResult::SUCCEEDED && !$result->replayed && count($sent)===$before+1);
    $freshGrant = provider_operation_grant($id,'packages.create',$scope,$password);
    $before = count($sent); $result = er_execute($id,$revision,$freshGrant,'review-original-account');
    $observations['cross_account_replay'] = ['status'=>$result->status,'replayed'=>$result->replayed,'sends'=>count($sent)-$before];
    er_check('A reused key cannot replay the previous configured account result as current success', $result->status===OperationResult::CONFLICT && count($sent)===$before);

    $revokeGrant = provider_operation_grant($id,'packages.create',['resellerId'=>'*','type'=>'synthetic','domain_name'=>'revoked-before-send.example'],$password);
    $before = count($sent); $revokedBeforeSend = false;
    $resolver = static function()use($revokeGrant,$password,&$revokedBeforeSend):string {
        er_resolve(); provider_operation_revoke($revokeGrant,$password);
        $revokedBeforeSend = one('SELECT revoked_at FROM '.table('provider_operation_grants').' WHERE id=?',[$revokeGrant])['revoked_at']!==null;
        return '8.8.8.8';
    };
    $result = er_execute($id,$revision,$revokeGrant,'review-revoked-during-resolution',$resolver);
    $observations['revoked_before_send'] = ['revocation_committed_before_transport'=>$revokedBeforeSend,'status'=>$result->status,'sends'=>count($sent)-$before];
    er_check('Review successfully revokes the approval before any scripted send', $revokedBeforeSend);
    er_check('Revocation during address resolution prevents a later provider send', count($sent)===$before && $result->status!==OperationResult::SUCCEEDED);

    $pendingScope = ['resellerId'=>'*','type'=>'synthetic','domain_name'=>'accepted-pending.example'];
    $pendingGrant = provider_operation_grant($id,'packages.create',$pendingScope,$password);
    $responses = [new TransportResponse(202, [], '{"status":"pending"}')];
    $before = count($sent); $result = er_execute($id,$revision,$pendingGrant,'review-http202');
    $stored = one('SELECT status FROM '.table('provider_operations').' WHERE idempotency_key=?',['review-http202']);
    $observations['accepted_pending'] = ['status'=>$result->status,'needs_reconciliation'=>$result->needsReconciliation(),'stored_status'=>$stored['status'],'sends'=>count($sent)-$before];
    er_check('HTTP 202 pending requires reconciliation in both the result and durable ledger', $result->needsReconciliation() && $stored['status']==='uncertain' && count($sent)===$before+1);
    $pendingGrant = provider_operation_grant($id,'packages.create',$pendingScope,$password);
    $before = count($sent); $result = er_execute($id,$revision,$pendingGrant,'review-http202');
    er_check('Replay preserves the pending reconciliation requirement without resending', $result->needsReconciliation() && $result->replayed && count($sent)===$before);
    $durable = json_encode([all('SELECT * FROM '.table('provider_operations')),all('SELECT * FROM '.table('provider_operation_grants')),all('SELECT * FROM '.table('audit'))],JSON_THROW_ON_ERROR);
    er_check('Review operations persist no plaintext credential or Authorization value', !str_contains($durable,'SYNTHETIC_PRIVATE_GENERAL_KEY') && !str_contains($durable,$expectedSecret) && !str_contains($durable,'Bearer '));

    // Reconstruct the preceding execution branch's v2 grant schema from its populated
    // synthetic table: same columns/order as eaf4444, before profile_revision existed.
    $rowsBefore = (int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn();
    db()->exec('ALTER TABLE '.table('provider_operation_grants').' DROP COLUMN profile_revision');
    save_setting('provider_operations_schema_version','2');
    $migrationError = null;
    try { provider_operations_migrate($password); }
    catch (RuntimeException $e) { if($e instanceof PDOException)throw $e; $migrationError=$e->getMessage(); }
    $rowsAfter = (int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants'))->fetchColumn();
    $observations['prior_execution_v2_upgrade'] = ['error'=>$migrationError,'ready'=>provider_operations_ready(),'rows_before'=>$rowsBefore,'rows_after'=>$rowsAfter];
    er_check('The preceding execution v2 schema upgrades to the current schema', $migrationError===null && provider_operations_ready());
    er_check('Upgrade attempt preserves the populated synthetic approval records', $rowsBefore>0 && $rowsAfter===$rowsBefore);
} catch (Throwable $e) {
    $checks[] = ['name'=>'Unexpected fixture failure: '.$e->getMessage(),'passed'=>false];
    fwrite(STDERR,$e->__toString().PHP_EOL);
}
$passed = !array_filter($checks,static fn($c)=>!$c['passed']);
file_put_contents(__DIR__.'/../qa/provider-execution-review-results.json',json_encode(['passed'=>$passed,'source'=>'6c1feec8f5815974a8bcfd5c3efbf1f55f263f20','php'=>PHP_VERSION,'scope'=>'Independent native MariaDB and actual PHP view review with real gateway/adapter/ledger over scripted transport. No DNS, provider call or real credentials. Failed behavioral checks are review findings, not acceptance.','checks'=>$checks,'observations'=>$observations],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo ($passed?'PASS':'FINDINGS').': '.count($checks).' independent execution checks'.PHP_EOL;
exit($passed?0:1);
