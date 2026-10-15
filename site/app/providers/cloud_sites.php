<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';

use CajunWebPro\Foundation\Provider\{OperationResult,ProviderOperation,ProviderGateway};
use CajunWebPro\Foundation\TwentyI\Operations\CloudWebsiteCreate;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

function provider_cloud_sites_history_ready(): void {
    $requests=(int)query('SELECT COUNT(*) FROM '.table('provider_operation_grants').' WHERE operation=?',[CloudWebsiteCreate::OPERATION])->fetchColumn();
    $audits=(int)query('SELECT COUNT(*) FROM '.table('audit').' WHERE action=?',['provider.cloudsite_started'])->fetchColumn();
    if($requests!==$audits)throw new RuntimeException('Cloud website approval history is incomplete. Restore the original records before submitting another request.');
}

/** Current owner, exact assignment, latest complete fresh attempt and original account. */
function provider_cloud_sites_context(string $linkId,int $linkRevision,int $profileRevision,string $refreshId,?string $password=null): array {
    $actor=$password===null?provider_settings_manager():provider_settings_owner($password);
    if(!provider_inventory_ready()||!provider_operations_ready())throw new RuntimeException('Prepare the original inventory and operation storage first.');
    provider_cloud_sites_history_ready();
    $link=provider_cloud_link_current($linkId,$linkRevision);
    [, $profile]=provider_checks_authority($link['profile_id'],$profileRevision);
    if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$link['client_id']]))throw new RuntimeException('The assigned client is unavailable.');
    // A newer failed or unfinished attempt invalidates eligibility; old good data cannot hide it.
    $latest=one('SELECT f.id,f.started_epoch,r.state FROM '.table('provider_refreshes').' f LEFT JOIN '.table('provider_refresh_results').' r ON r.id=f.id WHERE f.profile_id=? AND f.operation=? ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 1',[$link['profile_id'],InventoryRead::CLOUD_SERVERS_LIST]);
    if(!$latest||$latest['id']!==$refreshId||$latest['state']!=='complete'||(int)$latest['started_epoch']<time()-900||(int)$latest['started_epoch']>time()+30)throw new RuntimeException('Refresh the cloud server list, then review this form again. A complete observation from the last 15 minutes is required.');
    $observation=provider_cloud_link_observation($link['profile_id'],$profileRevision,$refreshId,(int)$link['server_id']);
    if(!provider_inventory_link_context_matches($link,$profile,$observation))throw new RuntimeException('Review and reapprove this server assignment under the current provider settings.');
    $binding=['link_id'=>$linkId,'link_revision'=>$linkRevision,'client_id'=>(int)$link['client_id'],'server_id'=>(int)$link['server_id'],'profile_revision'=>$profileRevision,'key_fingerprint'=>$link['key_fingerprint'],'refresh_id'=>$refreshId,'credentials_hash'=>hash('sha256',$profile['credentials']),'actor_auth'=>hash('sha256',$actor['auth_version'])];
    return [$actor,$profile,$binding];
}

/** Canonical, HMAC-protected consent metadata is held in the existing approval record. */
function provider_cloud_sites_document(array $grant): array {
    if($grant['operation']!==CloudWebsiteCreate::OPERATION||!is_string($grant['scope_params']))throw new RuntimeException('Cloud website request history is inconsistent.');
    try{$doc=json_decode($grant['scope_params'],true,12,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Cloud website request history is inconsistent.');}
    if(!is_array($doc)||($doc['version']??null)!==1||!is_array($doc['binding']??null)||!is_array($doc['params']??null)||($doc['reviewed']??null)!==true||!hash_equals($grant['scope_fingerprint'],provider_operations_scope(CloudWebsiteCreate::OPERATION,$doc)))throw new RuntimeException('Cloud website request history is inconsistent. Restore the original records.');
    CloudWebsiteCreate::params($doc['params']);
    if(($doc['binding']['server_id']??null)!==$doc['params']['cloudServerId']||$grant['consumed_key']!=='cloudsite:'.$grant['id']||(int)$grant['profile_revision']!==($doc['binding']['profile_revision']??null))throw new RuntimeException('Cloud website request binding is inconsistent.');
    return $doc;
}

function provider_cloud_sites_result(array $grant): ?array {
    $row=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$grant['consumed_key']]);
    if($row){
        $doc=provider_cloud_sites_document($grant);
        $operation=new ProviderOperation(CloudWebsiteCreate::OPERATION,$grant['consumed_key'],$doc['params']);
        $identity=$grant['profile_id'].':'.(int)$grant['profile_revision'].':'.$doc['binding']['key_fingerprint'];
        if(!hash_equals($row['fingerprint'],ProviderGateway::requestFingerprint('twenty-i',$identity,$operation))||($row['operation']!==null&&$row['operation']!==CloudWebsiteCreate::OPERATION))throw new RuntimeException('Cloud website result history is inconsistent.');
    }
    return $row;
}

function provider_cloud_sites_authorize(array $grant,array $doc): array {
    $b=$doc['binding'];
    [$actor,$profile,$binding]=provider_cloud_sites_context($b['link_id'],$b['link_revision'],$b['profile_revision'],$b['refresh_id']);
    $current=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$grant['id']]);
    if(!$current||$current!==$grant||(int)$actor['id']!==(int)$grant['granted_by']||$grant['revoked_at']!==null||(int)$grant['expires_epoch']<=time()||!hash_equals(provider_operations_scope('cloudsites.binding',$binding),provider_operations_scope('cloudsites.binding',$b)))throw new ProviderOperationAccessChanged('Cloud website approval or assignment changed.');
    return $profile;
}

/** Shared durable ledger: record known pre-send failures without opening a retry path. */
function provider_cloud_sites_finish(array $grant,OperationResult $result): OperationResult {
    $doc=provider_cloud_sites_document($grant);$op=new ProviderOperation(CloudWebsiteCreate::OPERATION,$grant['consumed_key'],$doc['params']);
    $fingerprint=ProviderGateway::requestFingerprint('twenty-i',$grant['profile_id'].':'.(int)$grant['profile_revision'].':'.$doc['binding']['key_fingerprint'],$op);
    $ledger=new ProviderOperationLedger();$current=provider_cloud_sites_result($grant);
    if(!$current){$claim=$ledger->claim($op->idempotencyKey,$fingerprint,time());if($claim->state==='claimed')$ledger->record($op->idempotencyKey,$fingerprint,$result,time());}
    provider_settings_audit((int)$grant['granted_by'],'cloudsite_result','Cloud website request '.$grant['id'].' '.$result->status);
    return $result;
}

/** One explicit owner approval, one immutable request, at most one provider send. */
function provider_cloud_sites_create(string $requestId,string $linkId,int $linkRevision,int $profileRevision,string $refreshId,string $domain,array $extra,bool $reviewed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null): OperationResult {
    if(db()->inTransaction())throw new LogicException('Cloud website creation cannot run inside a transaction.');
    if(!preg_match('/^[a-f0-9]{32}$/D',$requestId)||!$reviewed)throw new InvalidArgumentException('Review the server capacity, domain permission and website request before continuing.');
    $started=staff_locked(fn()=>staff_transaction(function()use($requestId,$linkId,$linkRevision,$profileRevision,$refreshId,$domain,$extra,$password):array {
        [$actor,$profile,$binding]=provider_cloud_sites_context($linkId,$linkRevision,$profileRevision,$refreshId,$password);
        $params=CloudWebsiteCreate::params(['cloudServerId'=>$binding['server_id'],'domain_name'=>$domain,'extra_domain_names'=>$extra]);
        $doc=['version'=>1,'binding'=>$binding,'params'=>$params,'reviewed'=>true];$scope=provider_operations_scope(CloudWebsiteCreate::OPERATION,$doc);
        $existing=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$requestId]);
        if($existing){provider_cloud_sites_document($existing);if($existing['profile_id']!==$profile['id']||(int)$existing['granted_by']!==(int)$actor['id']||!hash_equals($existing['scope_fingerprint'],$scope))throw new RuntimeException('This request was already used for different details.');return [$existing,false];}
        // A fresh nonce must not recreate a website after an accepted, uncertain or interrupted attempt.
        foreach(all('SELECT * FROM '.table('provider_operation_grants').' WHERE profile_id=? AND operation=?',[$profile['id'],CloudWebsiteCreate::OPERATION]) as $previous){$old=provider_cloud_sites_document($previous);if(array_intersect([$domain,...$extra],[$old['params']['domain_name'],...$old['params']['extra_domain_names']])){$outcome=provider_cloud_sites_result($previous);if(!$outcome||$outcome['status']!=='failed')throw new RuntimeException('A prior request for one of these domains needs review. It cannot be submitted again automatically.');}}
        $nodes=0;$json=provider_operations_canonical($doc,0,$nodes);if(strlen($json)>4096)throw new InvalidArgumentException('The reviewed website request is too large.');
        query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$requestId,$profile['id'],CloudWebsiteCreate::OPERATION,$scope,$profileRevision,$json,(int)$actor['id'],gmdate('Y-m-d H:i:s'),time()+900,'cloudsite:'.$requestId,gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'cloudsite_started','Cloud website request '.$requestId.' client '.$binding['client_id'].' link '.$linkId);
        return [one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$requestId]),true];
    }));
    [$grant,$fresh]=$started;$doc=provider_cloud_sites_document($grant);
    if(!$fresh){$row=provider_cloud_sites_result($grant);return match($row['status']??'uncertain'){'accepted_pending'=>OperationResult::acceptedPending(CloudWebsiteCreate::OPERATION,$row['external_id']),'failed'=>OperationResult::failed(CloudWebsiteCreate::OPERATION,'The earlier request was refused. Review its history before creating a new request.'),default=>OperationResult::uncertain(CloudWebsiteCreate::OPERATION)};}
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){if($e instanceof PDOException)throw $e;return provider_cloud_sites_finish($grant,OperationResult::failed(CloudWebsiteCreate::OPERATION,'Provider endpoint unavailable before send.','unreachable')->withAttempts(0));}
    return staff_locked(function()use($grant,$doc,$address,$perform):OperationResult {
        $authorize=static function()use($grant,$doc):void {provider_cloud_sites_authorize($grant,$doc);};$dispatched=false;
        try{
            $profile=provider_cloud_sites_authorize($grant,$doc);$secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            try{
                if(!hash_equals($doc['binding']['key_fingerprint'],provider_operations_credential_fingerprint($secret)))throw new ProviderOperationAccessChanged('The observed provider account changed.');
                $authorize();$dispatched=true;$op=new ProviderOperation(CloudWebsiteCreate::OPERATION,$grant['consumed_key'],$doc['params']);
                $result=($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$op);$authorize();
            }finally{unset($secret);}
        }catch(Throwable $e){if($e instanceof PDOException)throw $e;$result=$dispatched?OperationResult::uncertain(CloudWebsiteCreate::OPERATION,'access_changed'):OperationResult::failed(CloudWebsiteCreate::OPERATION,'Current access, assignment or credentials could not be confirmed before send.','access_changed')->withAttempts(0);}
        return provider_cloud_sites_finish($grant,$result);
    });
}

/** Owner-only history. Never exposes raw provider responses or credentials. */
function provider_cloud_sites_history(string $profileId): array {
    provider_settings_manager();if(!provider_operations_ready())return [];
    provider_cloud_sites_history_ready();
    $out=[];
    foreach(all('SELECT * FROM '.table('provider_operation_grants').' WHERE profile_id=? AND operation=? ORDER BY seq DESC LIMIT 40',[$profileId,CloudWebsiteCreate::OPERATION]) as $grant){$doc=provider_cloud_sites_document($grant);$row=provider_cloud_sites_result($grant);$out[]=['id'=>$grant['id'],'server_id'=>$doc['binding']['server_id'],'client_id'=>$doc['binding']['client_id'],'domain'=>$doc['params']['domain_name'],'created_at'=>$grant['granted_at'],'status'=>$row['status']??'uncertain','package_id'=>$row['external_id']??null];}
    return $out;
}

/** Actual signed-in client only; private request summaries need a still-current explicit assignment. */
function provider_cloud_sites_client_history(int $serverId): array {
    if(support_current()||array_key_exists('support',$_GET))return [];
    $client=studio_client();if(!$client||!provider_inventory_ready()||!provider_operations_ready())return [];
    $out=[];
    foreach(all('SELECT l.* FROM '.table('provider_cloud_links').' l JOIN '.table('provider_profiles').' p ON p.id=l.profile_id WHERE l.client_id=? AND l.server_id=? AND p.enabled=1 AND p.revision=l.profile_revision',[(int)$client['id'],$serverId]) as $link){
        $profile=provider_settings_load($link['profile_id']);
        foreach(all('SELECT * FROM '.table('provider_operation_grants').' WHERE profile_id=? AND operation=? ORDER BY seq DESC LIMIT 40',[$link['profile_id'],CloudWebsiteCreate::OPERATION]) as $grant){
            $doc=provider_cloud_sites_document($grant);$b=$doc['binding'];
            if($b['link_id']!==$link['id']||$b['client_id']!==(int)$client['id']||$b['server_id']!==$serverId||$b['profile_revision']!==(int)$profile['revision']||!hash_equals($link['key_fingerprint'],$b['key_fingerprint'])||!hash_equals(hash('sha256',$profile['credentials']),$b['credentials_hash']))continue;
            $row=provider_cloud_sites_result($grant);
            $status=match($row['status']??'uncertain'){'accepted_pending'=>'Accepted; website verification pending','failed'=>'Request could not be completed',default=>'The studio is reviewing this request'};
            $out[]=['domain'=>$doc['params']['domain_name'],'created_at'=>$grant['granted_at'],'status'=>$status];
        }
    }
    return $out;
}
