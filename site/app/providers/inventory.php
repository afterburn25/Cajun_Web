<?php
/**
 * Explicit owner-triggered 20i inventory refresh plus durable observation
 * storage and owner-approved client/service linking. Every refresh writes a
 * durable intent row before any network work, re-verifies the current
 * authority and exact profile revision before credentials open and again
 * before results persist, and never replaces or mutates an earlier
 * observation — failed or ambiguous refreshes cannot erase the last good
 * one. No background or implicit refresh exists.
 */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use CajunWebPro\Foundation\Provider\ProviderCredential;
use CajunWebPro\Foundation\Provider\TransportException;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyIInventory\InventoryPackage;
use CajunWebPro\Foundation\TwentyIInventory\InventoryCloudServer;
use CajunWebPro\Foundation\TwentyIInventory\InventoryRead;
use CajunWebPro\Foundation\TwentyIInventory\InventorySnapshot;
use CajunWebPro\Foundation\TwentyIInventoryReader\InventoryReader;

require_once CWP_CODE_ROOT.'/app/providers/settings.php';
require_once CWP_CODE_ROOT.'/app/providers/checks.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_inventory_migrate.php';
foreach(['ProviderCredential','TransportRequest','TransportResponse','TransportException','HttpTransport'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/provider/'.$class.'.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi/FixedHttpTransport.php';
foreach(['InventoryRead','InventoryPackage','InventoryCloudServer','InventorySnapshot','InventoryProjector'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/'.$class.'.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory-reader/InventoryReader.php';

final class ProviderInventoryAccessChanged extends RuntimeException {}

function provider_inventory_ready(): bool {
    $version=(one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_inventory_schema_version'])['value']??'');
    // Version 5 adds explicit cloud ownership and immutable approval history.
    // Older installations must retain the explicit owner preparation action.
    if($version!=='5')return false;
    $prefix=config()['db']['prefix'];$names=array_map(fn($name)=>$prefix.$name,array_keys(provider_inventory_schema($prefix)));
    if((int)query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('.implode(',',array_fill(0,count($names),'?')).')',$names)->fetchColumn()!==count($names))return false;
    return one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$prefix.'provider_refreshes','operation'])!==null;
}

/** A credential fingerprint fences a context; it does not establish account identity. */
function provider_inventory_link_context_matches(array $link,array $profile,?array $observation): bool {
    return $observation!==null && (int)$link['profile_revision']===(int)$profile['revision']
        && (int)$observation['profile_revision']===(int)$profile['revision']
        && ($link['key_fingerprint']??'')!==''
        && hash_equals($link['key_fingerprint'],(string)($observation['key_fingerprint']??''));
}

/** Deterministic credential-context fingerprint — installation-bound, never the key itself. */
function provider_inventory_fingerprint(#[\SensitiveParameter] string $secret): string {
    return hash_hmac('sha256',$secret,provider_settings_vault_key());
}

/** Manager or owner authority, exact current profile revision, enabled with credentials, storage ready. */
function provider_inventory_authority(string $id,int $revision,#[\SensitiveParameter] ?string $password=null): array {
    if(!provider_inventory_ready())throw new RuntimeException('Prepare 20i inventory storage first.');
    [$actor,$row]=provider_checks_authority($id,$revision,$password);
    return [$actor,$row];
}

/**
 * Internal only. Builds the credential with an access-gated resolver and
 * performs one bounded authenticated read over the injected transport.
 */
function provider_inventory_read(array $row,#[\SensitiveParameter] string $secret,string $address,callable $authorize,string $operation=InventoryRead::PACKAGES_LIST): InventorySnapshot {
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return InventoryReader::read(new FixedHttpTransport($address,$authorize),$credential,$operation,0,time());
}

/** Result states that carry package observations. */
function provider_inventory_observed(string $state): bool {return in_array($state,['complete','partial'],true);}

function provider_inventory_record(array $started,string $state,string $reason,string $scope,string $coverage,int $packages,int $skipped,string $skipReasons,int $elapsed,?InventorySnapshot $snapshot,string $fingerprint=''): void {
    staff_transaction(function()use($started,$state,$reason,$scope,$coverage,$packages,$skipped,$skipReasons,$elapsed,$snapshot,$fingerprint):void {
        query('INSERT INTO '.table('provider_refresh_results').' (id,state,reason,scope,coverage,package_count,skipped,skip_reasons,finished_at,elapsed_ms,key_fingerprint) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$started['id'],$state,$reason,$scope,$coverage,$packages,$skipped,$skipReasons,gmdate('Y-m-d H:i:s'),max(0,min($elapsed,2147483647)),$fingerprint]);
        if($snapshot!==null&&provider_inventory_observed($state)) {
            foreach($snapshot->packages as $package) {
                if(($started['operation']??InventoryRead::PACKAGES_LIST)===InventoryRead::CLOUD_SERVERS_LIST) {
                    if(!$package instanceof InventoryCloudServer)throw new LogicException('Only validated cloud records persist for cloud reads.');
                    $metadata=['id'=>$package->id,'name'=>$package->name,'location'=>$package->location,'productSpec'=>$package->productSpec,'configuration'=>$package->configuration,'packageIds'=>$package->packageIds];
                    query('INSERT INTO '.table('provider_observation_cloud').' (id,refresh_id,server_id,metadata,unmapped_count) VALUES (?,?,?,?,?)',[bin2hex(random_bytes(16)),$started['id'],$package->id,json_encode($metadata,JSON_THROW_ON_ERROR),$package->unmappedCount]);
                    continue;
                }
                if(!$package instanceof InventoryPackage)throw new LogicException('Only validated package records persist.');
                query('INSERT INTO '.table('provider_observation_packages').' (id,refresh_id,package_id,name,names,enabled,created_at,created_epoch,type_ref,package_type_name,stack_users,package_labels,has_spec,unmapped_keys,unmapped_count) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [bin2hex(random_bytes(16)),$started['id'],$package->id,$package->name,json_encode(array_values($package->names)),$package->enabled?1:0,$package->createdAt,$package->createdEpoch,$package->typeRef,$package->packageTypeName,json_encode(array_values($package->stackUsers)),json_encode(array_values($package->packageLabels)),$package->hasProductSpec?1:0,json_encode(array_values($package->unmappedKeys)),$package->unmappedCount]);
            }
        }
        provider_settings_audit((int)$started['actor_id'],'refresh_finished','Refresh '.$started['id'].' '.$state.($reason!==''?' '.$reason:''));
    });
}

/**
 * One explicit refresh of the hosting package list. Returns a bounded
 * state code: complete|partial|unavailable|refused|access_changed.
 * Resolver/transport are internal test seams, never HTTP input.
 */
function provider_inventory_refresh(string $id,int $revision,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null,string $operation=InventoryRead::PACKAGES_LIST): string {
    return provider_inventory_refresh_attempt($id,$revision,$password,$resolver,$transport,$operation)['state'];
}
/** Internal receipt exposes the exact durable refresh, never a latest-row guess. */
function provider_inventory_refresh_attempt(string $id,int $revision,#[\SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null,string $operation=InventoryRead::PACKAGES_LIST,?callable $scopeAuthority=null):array {
    if(!InventoryReader::supports($operation))throw new InvalidArgumentException('This inventory reader is not available.');
    if(db()->inTransaction())throw new LogicException('Provider refreshes cannot run inside a transaction.');
    $started=staff_locked(fn()=>staff_transaction(function()use($id,$revision,$password,$operation,$scopeAuthority):array {
        if($scopeAuthority)$scopeAuthority();
        [$actor]=provider_inventory_authority($id,$revision,$password);$now=time();
        if(one('SELECT id FROM '.table('provider_refreshes').' WHERE profile_id=? AND started_epoch>? LIMIT 1',[$id,$now-30]))throw new RuntimeException('Wait 30 seconds between refreshes of this profile.');
        if((int)query('SELECT COUNT(*) FROM '.table('provider_refreshes').' WHERE actor_id=? AND started_epoch>?',[$actor['id'],$now-3600])->fetchColumn()>=20)throw new RuntimeException('Inventory refresh limit reached. Try again later.');
        $started=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$id,'profile_revision'=>$revision,'actor_id'=>(int)$actor['id'],'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now,'operation'=>$operation];
        query('INSERT INTO '.table('provider_refreshes').' (id,profile_id,profile_revision,actor_id,started_at,started_epoch,operation) VALUES (?,?,?,?,?,?,?)',array_values($started));
        provider_settings_audit((int)$actor['id'],'refresh_started','Refresh '.$started['id'].' profile '.$id.' revision '.$revision);return $started;
    }));
    $begin=hrtime(true);$code=null;$address='';
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){if($e instanceof PDOException)throw $e;$code='unavailable';}
    $state=staff_locked(function()use($started,$password,$address,$code,$begin,$transport,$scopeAuthority):string {
        $authorize=static function()use($started,$scopeAuthority):void {
            try{if($scopeAuthority)$scopeAuthority();[$actor]=provider_inventory_authority($started['profile_id'],$started['profile_revision']);if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;throw new ProviderInventoryAccessChanged('Current provider access changed.');}
        };
        $elapsed=static fn():int=>(int)((hrtime(true)-$begin)/1e6);
        try{[$actor,$row]=provider_inventory_authority($started['profile_id'],$started['profile_revision'],$password);if((int)$actor['id']!==$started['actor_id'])throw new RuntimeException('Changed actor.');}
        catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;provider_inventory_record($started,'access_changed','access_changed','','unknown',0,0,'[]',$elapsed(),null);return 'access_changed';}
        $snapshot=null;$fingerprint='';
        if($code===null) {
            try{$authorize();$secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));$fingerprint=provider_inventory_fingerprint($secret);}
            catch(ProviderInventoryAccessChanged){$code='access_changed';}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$code='credential';}
            if($code===null) {
                try{
                    $snapshot=($transport??'provider_inventory_read')($row,$secret,$address,$authorize,$started['operation']);
                    if(!$snapshot instanceof InventorySnapshot)throw new LogicException('Reader returned no snapshot.');
                    if($snapshot->scope!=='twenty-i|'.$started['profile_id'].'|'.$started['operation'])throw new LogicException('Reader scope does not match its intent.');
                    $code=$snapshot->state;
                }catch(ProviderInventoryAccessChanged){$code='access_changed';}
                catch(TransportException){$code='unavailable';}
                catch(LogicException|RuntimeException){$code='refused';}
                catch(Throwable $e){if($e instanceof PDOException)throw $e;$code='refused';}
                finally{unset($secret);}
            }
        }
        // Even a returned snapshot cannot persist after access changed.
        try{$authorize();}catch(ProviderInventoryAccessChanged){$code='access_changed';}
        if(!provider_inventory_observed($code)) {
            // Refusals, unavailable reads and access changes persist a bounded
            // result with no package rows; the snapshot's own fields are
            // retained only when it classified itself.
            $self=$snapshot!==null&&$snapshot->state===$code;
            provider_inventory_record($started,$code,$self?$snapshot->reason:$code,$self?$snapshot->scope:'','unknown',0,$self?$snapshot->skipped:0,$self?json_encode(array_values($snapshot->skipReasons)):'[]',$elapsed(),null,$fingerprint);
            return $code;
        }
        provider_inventory_record($started,$code,'',$snapshot->scope,$snapshot->coverage,count($snapshot->packages),$snapshot->skipped,json_encode(array_values($snapshot->skipReasons)),$elapsed(),$snapshot,$fingerprint);
        return $code;
    });
    return ['id'=>$started['id'],'state'=>$state];
}

/** Latest finished refresh of any state — the freshness display. */
function provider_inventory_latest(string $profileId,string $operation=InventoryRead::PACKAGES_LIST): ?array {
    provider_settings_manager();
    if(!InventoryReader::supports($operation))throw new InvalidArgumentException('Unknown inventory operation.');
    return one('SELECT r.*,f.profile_id,f.profile_revision,f.actor_id,f.started_at,f.started_epoch,u.name AS actor_name FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id LEFT JOIN '.table('users').' u ON u.id=f.actor_id WHERE f.profile_id=? AND COALESCE(f.operation,\'packages.list\')=? ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 1',[$profileId,$operation]);
}

/** Last good observation: the newest refresh that actually carried packages. */
function provider_inventory_observation(string $profileId,string $operation=InventoryRead::PACKAGES_LIST): ?array {
    provider_settings_manager();
    if(!InventoryReader::supports($operation))throw new InvalidArgumentException('Unknown inventory operation.');
    return one('SELECT r.*,f.profile_id,f.profile_revision,f.actor_id,f.started_at,f.started_epoch,u.name AS actor_name FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id LEFT JOIN '.table('users').' u ON u.id=f.actor_id WHERE f.profile_id=? AND COALESCE(f.operation,\'packages.list\')=? AND r.state IN (\'complete\',\'partial\') ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 1',[$profileId,$operation]);
}

/** Validated package rows of one recorded observation. */
function provider_inventory_packages(string $refreshId): array {
    provider_settings_manager();
    $rows=all('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? ORDER BY package_id',[$refreshId]);
    foreach($rows as &$row){$row['names']=json_decode($row['names'],true);$row['stack_users']=json_decode($row['stack_users'],true);$row['package_labels']=json_decode($row['package_labels'],true);$row['unmapped_keys']=json_decode($row['unmapped_keys'],true);}unset($row);
    return $rows;
}

function provider_inventory_links(string $profileId): array {
    provider_settings_manager();
    return all('SELECT l.*,p.revision AS current_revision,c.name AS client_name,c.email AS client_email,u.name AS linked_by_name FROM '.table('provider_package_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id JOIN '.table('provider_profiles').' p ON p.id=l.profile_id LEFT JOIN '.table('users').' u ON u.id=l.linked_by WHERE l.profile_id=? ORDER BY l.package_id',[$profileId]);
}

/** Bounded refresh history for one profile — every recorded result, newest first. */
function provider_inventory_history(string $profileId,int $page=1,string $operation=InventoryRead::PACKAGES_LIST): array {
    provider_settings_manager();$page=max(1,min($page,100000));
    return all('SELECT f.id,f.started_at,f.started_epoch,f.profile_revision,r.state,r.reason,r.scope,r.coverage,r.package_count,r.skipped,r.finished_at,r.elapsed_ms,u.name AS actor_name FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id LEFT JOIN '.table('users').' u ON u.id=f.actor_id WHERE f.profile_id=? AND COALESCE(f.operation,\'packages.list\')=? ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 21 OFFSET '.(($page-1)*20),[$profileId,$operation]);
}

function provider_inventory_link_events(string $profileId,int $page=1): array {
    provider_settings_manager();$page=max(1,min($page,100000));
    return all('SELECT e.*,u.name AS actor_name,c.name AS client_name FROM '.table('provider_package_link_events').' e LEFT JOIN '.table('users').' u ON u.id=e.actor_id LEFT JOIN '.table('studio_clients').' c ON c.id=e.client_id WHERE e.profile_id=? ORDER BY e.recorded_at DESC,e.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$profileId]);
}

/**
 * Explicit owner-approved mapping of one observed provider package to one
 * active client account. The package must be present in the profile's last
 * good observation recorded under the exact current profile revision —
 * nothing is inferred from names, domains or other provider fields. The
 * mapping is bound to the credential context that produced the approved
 * observation; any later configuration or credential change makes the link
 * stale until the owner re-approves it under a fresh observation.
 */
function provider_inventory_link(string $profileId,int $packageId,int $clientId,int $revision,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($profileId,$packageId,$clientId,$revision,$password):void {
        [$actor]=provider_inventory_authority($profileId,$revision,$password);
        if($packageId<=0)throw new InvalidArgumentException('Provider package identity unavailable.');
        $observation=provider_inventory_observation($profileId);
        if(!$observation||(int)$observation['profile_revision']!==$revision)throw new RuntimeException('The saved observation is from an earlier provider configuration. Refresh under the current settings before linking.');
        if(!one('SELECT id FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['id'],$packageId]))throw new RuntimeException('That package is not in the last good observation for this profile. Refresh first.');
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new InvalidArgumentException('Active client account not found.');
        if(one('SELECT id FROM '.table('provider_package_links').' WHERE profile_id=? AND package_id=?',[$profileId,$packageId]))throw new RuntimeException('That provider package is already linked. Unlink it first.');
        query('INSERT INTO '.table('provider_package_links').' (id,profile_id,package_id,client_id,linked_by,linked_at,key_fingerprint,profile_revision) VALUES (?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$profileId,$packageId,$clientId,(int)$actor['id'],gmdate('Y-m-d H:i:s'),$observation['key_fingerprint'],$revision]);
        query('INSERT INTO '.table('provider_package_link_events').' (profile_id,package_id,client_id,event,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$profileId,$packageId,$clientId,'link',(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'service_linked','Profile '.$profileId.' package '.$packageId.' client '.$clientId);
    }));
}

/** Explicit owner unlink; the mapping is removed and an event is recorded. */
function provider_inventory_unlink(string $linkId,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($linkId,$password):void {
        [$actor]=provider_inventory_authority_args($password);
        $link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
        if(!$link)throw new InvalidArgumentException('Link not found.');
        query('DELETE FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
        query('INSERT INTO '.table('provider_package_link_events').' (profile_id,package_id,client_id,event,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$link['profile_id'],$link['package_id'],$link['client_id'],'unlink',(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'service_unlinked','Link '.$linkId.' profile '.$link['profile_id'].' package '.$link['package_id']);
    }));
}

/**
 * Explicit owner re-approval of an existing mapping after the provider
 * configuration or credential context changed. Requires a current-revision
 * good observation that still contains the package; a package absent under
 * the new context can only be unlinked, never silently carried over.
 */
function provider_inventory_relink(string $linkId,int $revision,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($linkId,$revision,$password):void {
        [$actor]=provider_inventory_authority_args($password);
        $link=one('SELECT * FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
        if(!$link)throw new InvalidArgumentException('Link not found.');
        $row=provider_settings_load($link['profile_id']);
        if(!$row||(int)$row['revision']!==$revision)throw new RuntimeException('This provider profile changed. Reload before re-approving.');
        $observation=provider_inventory_observation($link['profile_id']);
        if(!$observation||(int)$observation['profile_revision']!==$revision)throw new RuntimeException('Refresh the inventory under the current provider settings before re-approving this link.');
        if(!one('SELECT id FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['id'],$link['package_id']]))throw new RuntimeException('That package is not in the current observation. Remove the link instead.');
        query('UPDATE '.table('provider_package_links').' SET key_fingerprint=?,profile_revision=? WHERE id=?',[$observation['key_fingerprint'],$revision,$linkId]);
        query('INSERT INTO '.table('provider_package_link_events').' (profile_id,package_id,client_id,event,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$link['profile_id'],$link['package_id'],$link['client_id'],'relink',(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'service_relinked','Link '.$linkId.' profile '.$link['profile_id'].' package '.$link['package_id'].' revision '.$revision);
    }));
}

function provider_inventory_domain_links(string $profileId): array {
    provider_settings_manager();
    return all('SELECT l.*,p.revision AS current_revision,c.name AS client_name,c.email AS client_email,u.name AS linked_by_name FROM '.table('provider_domain_links').' l JOIN '.table('studio_clients').' c ON c.id=l.client_id JOIN '.table('provider_profiles').' p ON p.id=l.profile_id LEFT JOIN '.table('users').' u ON u.id=l.linked_by WHERE l.profile_id=? ORDER BY l.domain',[$profileId]);
}

function provider_inventory_domain_link_events(string $profileId,int $page=1): array {
    provider_settings_manager();$page=max(1,min($page,100000));
    return all('SELECT e.*,u.name AS actor_name,c.name AS client_name FROM '.table('provider_domain_link_events').' e LEFT JOIN '.table('users').' u ON u.id=e.actor_id LEFT JOIN '.table('studio_clients') .' c ON c.id=e.client_id WHERE e.profile_id=? ORDER BY e.recorded_at DESC,e.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$profileId]);
}

/**
 * Explicit owner-approved mapping of one observed domain to one active
 * client account, separate from the hosting package that carries it — a
 * reseller can host several clients' domains on one package. The domain
 * must appear in the observed names of a package row in the profile's
 * last good observation under the exact current profile revision; nothing
 * is inferred from registration, WHOIS or address fields. The mapping is
 * bound to the credential context like a package link.
 */
function provider_inventory_domain_link(string $profileId,int $packageId,string $domain,int $clientId,int $revision,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($profileId,$packageId,$domain,$clientId,$revision,$password):void {
        [$actor]=provider_inventory_authority($profileId,$revision,$password);
        $domain=strtolower(trim($domain));
        if($packageId<=0)throw new InvalidArgumentException('Provider package identity unavailable.');
        if($domain===''||strlen($domain)>253||!preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/D',$domain)||str_contains($domain,'..'))throw new InvalidArgumentException('Enter a valid observed domain name.');
        $observation=provider_inventory_observation($profileId);
        if(!$observation||(int)$observation['profile_revision']!==$revision)throw new RuntimeException('The saved observation is from an earlier provider configuration. Refresh under the current settings before linking.');
        $package=one('SELECT names FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['id'],$packageId]);
        if(!$package||!in_array($domain,array_map('strtolower',array_map('strval',json_decode($package['names'],true)?:[])),true))throw new RuntimeException('That domain is not in the last good observation for this package. Refresh first.');
        if(!one('SELECT id FROM '.table('studio_clients').' WHERE id=? AND active=1',[$clientId]))throw new InvalidArgumentException('Active client account not found.');
        if(one('SELECT id FROM '.table('provider_domain_links').' WHERE profile_id=? AND domain=?',[$profileId,$domain]))throw new RuntimeException('That domain is already linked. Unlink it first.');
        query('INSERT INTO '.table('provider_domain_links').' (id,profile_id,package_id,domain,client_id,linked_by,linked_at,key_fingerprint,profile_revision) VALUES (?,?,?,?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$profileId,$packageId,$domain,$clientId,(int)$actor['id'],gmdate('Y-m-d H:i:s'),$observation['key_fingerprint'],$revision]);
        query('INSERT INTO '.table('provider_domain_link_events').' (profile_id,package_id,domain,client_id,event,actor_id,recorded_at) VALUES (?,?,?,?,?,?,?)',[$profileId,$packageId,$domain,$clientId,'link',(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'domain_linked','Profile '.$profileId.' domain '.$domain.' client '.$clientId);
    }));
}

/** Explicit owner domain unlink; the mapping is removed and an event is recorded. */
function provider_inventory_domain_unlink(string $linkId,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($linkId,$password):void {
        [$actor]=provider_inventory_authority_args($password);
        $link=one('SELECT * FROM '.table('provider_domain_links').' WHERE id=?',[$linkId]);
        if(!$link)throw new InvalidArgumentException('Link not found.');
        query('DELETE FROM '.table('provider_domain_links').' WHERE id=?',[$linkId]);
        query('INSERT INTO '.table('provider_domain_link_events').' (profile_id,package_id,domain,client_id,event,actor_id,recorded_at) VALUES (?,?,?,?,?,?,?)',[$link['profile_id'],$link['package_id'],$link['domain'],$link['client_id'],'unlink',(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'domain_unlinked','Domain link '.$linkId.' profile '.$link['profile_id'].' domain '.$link['domain']);
    }));
}

/**
 * Explicit owner re-approval of a domain mapping after the provider
 * configuration or credential context changed. Requires a current-revision
 * good observation that still lists the domain on the same package; a
 * domain absent under the new context can only be unlinked.
 */
function provider_inventory_domain_relink(string $linkId,int $revision,#[\SensitiveParameter] string $password): void {
    staff_locked(fn()=>staff_transaction(function()use($linkId,$revision,$password):void {
        [$actor]=provider_inventory_authority_args($password);
        $link=one('SELECT * FROM '.table('provider_domain_links').' WHERE id=?',[$linkId]);
        if(!$link)throw new InvalidArgumentException('Link not found.');
        $row=provider_settings_load($link['profile_id']);
        if(!$row||(int)$row['revision']!==$revision)throw new RuntimeException('This provider profile changed. Reload before re-approving.');
        $observation=provider_inventory_observation($link['profile_id']);
        if(!$observation||(int)$observation['profile_revision']!==$revision)throw new RuntimeException('Refresh the inventory under the current provider settings before re-approving this link.');
        $package=one('SELECT names FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['id'],$link['package_id']]);
        if(!$package||!in_array($link['domain'],array_map('strtolower',array_map('strval',json_decode($package['names'],true)?:[])),true))throw new RuntimeException('That domain is not in the current observation for this package. Remove the link instead.');
        query('UPDATE '.table('provider_domain_links').' SET key_fingerprint=?,profile_revision=? WHERE id=?',[$observation['key_fingerprint'],$revision,$linkId]);
        query('INSERT INTO '.table('provider_domain_link_events').' (profile_id,package_id,domain,client_id,event,actor_id,recorded_at) VALUES (?,?,?,?,?,?,?)',[$link['profile_id'],$link['package_id'],$link['domain'],$link['client_id'],'relink',(int)$actor['id'],gmdate('Y-m-d H:i:s')]);
        provider_settings_audit((int)$actor['id'],'domain_relinked','Domain link '.$linkId.' profile '.$link['profile_id'].' domain '.$link['domain'].' revision '.$revision);
    }));
}

function provider_inventory_authority_args(#[\SensitiveParameter] string $password): array {
    $actor=provider_settings_owner($password);
    if(!provider_inventory_ready())throw new RuntimeException('Prepare 20i inventory storage first.');
    return [$actor];
}

/**
 * Portal read model for one authenticated client: only packages this owner
 * explicitly linked to that account, and only while the mapping's approved
 * provider context still matches the current profile configuration. A
 * changed configuration or credential suspends the mapping — the client
 * sees an honest pending-review marker, never data from another context.
 * Each row carries the package's last good observed state or an honest
 * absent marker — absence from an incomplete or unknown observation never
 * implies deletion or suspension.
 */
function provider_inventory_client_services(int $clientId): array {
    if(!provider_inventory_ready())return [];
    $links=all('SELECT l.* FROM '.table('provider_package_links').' l WHERE l.client_id=? ORDER BY l.package_id',[$clientId]);
    $out=[];
    foreach($links as $link) {
        $profile=one('SELECT revision FROM '.table('provider_profiles').' WHERE id=?',[$link['profile_id']]);
        $current=$profile!==null&&(int)$profile['revision']===(int)$link['profile_revision'];
        $observation=$current?one('SELECT r.id AS refresh_id,r.state,r.coverage,f.started_at FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id WHERE f.profile_id=? AND COALESCE(f.operation,\'packages.list\')=\'packages.list\' AND r.state IN (\'complete\',\'partial\') AND r.key_fingerprint=? ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 1',[$link['profile_id'],$link['key_fingerprint']]):null;
        $package=$observation?one('SELECT * FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['refresh_id'],$link['package_id']]):null;
        if($package){$package['names']=json_decode($package['names'],true);$package['package_labels']=json_decode($package['package_labels'],true);unset($package['stack_users'],$package['unmapped_keys']);}
        $out[]=['link'=>$link,'package'=>$package,'observed_at'=>$observation['started_at']??null,'observation'=>$observation===null?null:'present','present'=>$package!==null,'context_changed'=>!$current];
    }
    return $out;
}

/**
 * Client-facing view of domains explicitly linked to the account. Each
 * row carries the last good observed package that carried the domain or
 * an honest absent marker — absence from an incomplete or unknown
 * observation never implies the domain was removed.
 */
function provider_inventory_client_domains(int $clientId): array {
    if(!provider_inventory_ready())return [];
    $links=all('SELECT l.* FROM '.table('provider_domain_links').' l WHERE l.client_id=? ORDER BY l.domain',[$clientId]);
    $out=[];
    foreach($links as $link) {
        $profile=one('SELECT revision FROM '.table('provider_profiles').' WHERE id=?',[$link['profile_id']]);
        $current=$profile!==null&&(int)$profile['revision']===(int)$link['profile_revision'];
        $observation=$current?one('SELECT r.id AS refresh_id,f.started_at FROM '.table('provider_refresh_results').' r JOIN '.table('provider_refreshes').' f ON f.id=r.id WHERE f.profile_id=? AND COALESCE(f.operation,\'packages.list\')=\'packages.list\' AND r.state IN (\'complete\',\'partial\') AND r.key_fingerprint=? ORDER BY f.started_epoch DESC,f.seq DESC LIMIT 1',[$link['profile_id'],$link['key_fingerprint']]):null;
        $package=$observation?one('SELECT package_id,names,enabled FROM '.table('provider_observation_packages').' WHERE refresh_id=? AND package_id=?',[$observation['refresh_id'],$link['package_id']]):null;
        $present=false;
        if($package){$names=array_map('strtolower',array_map('strval',json_decode($package['names'],true)?:[]));$present=in_array($link['domain'],$names,true);unset($package['names']);}
        if(!$present)$package=null;
        $out[]=['link'=>$link,'package'=>$package,'domain'=>$link['domain'],'observed_at'=>$observation['started_at']??null,'present'=>$present,'context_changed'=>!$current];
    }
    return $out;
}

/** Selectable active client accounts for the link form. */
function provider_inventory_clients(): array {
    provider_settings_manager();
    return all('SELECT id,name,email FROM '.table('studio_clients').' WHERE active=1 ORDER BY name,id');
}

/** Sanitized cloud records for an owner-visible, explicitly cloud-scoped read. */
function provider_inventory_cloud_servers(string $refreshId): array {
    provider_settings_manager();
    if(!provider_inventory_ready())return [];
    $rows=all('SELECT c.* FROM '.table('provider_observation_cloud').' c JOIN '.table('provider_refreshes').' f ON f.id=c.refresh_id WHERE c.refresh_id=? AND f.operation=? ORDER BY c.server_id',[$refreshId,InventoryRead::CLOUD_SERVERS_LIST]);
    foreach($rows as &$row){$row['server']=InventoryCloudServer::fromItem(json_decode($row['metadata'],false,16,JSON_THROW_ON_ERROR));if($row['server']->id!==(int)$row['server_id'])throw new RuntimeException('Cloud observation identity is inconsistent. Restore the original database.');unset($row['metadata']);}unset($row);
    return $rows;
}

require_once CWP_CODE_ROOT.'/app/providers/cloud_links.php';
