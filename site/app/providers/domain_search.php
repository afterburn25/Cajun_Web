<?php
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/providers/inventory.php';
require_once CWP_CODE_ROOT.'/database/migrations/provider_domain_search_migrate.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory-reader/DomainSearch.php';

use CajunWebPro\Foundation\TwentyIInventoryReader\DomainSearch;
use CajunWebPro\Foundation\Provider\ProviderCredential;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

final class ProviderDomainSearchAccessChanged extends RuntimeException {}

function provider_domain_search_ready(): bool {
    if((one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_search_schema_version'])['value']??'')!=='1')return false;
    foreach(array_keys(provider_domain_search_schema(config()['db']['prefix'])) as $name)
        if(!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]))return false;
    return true;
}
/** Offered suffixes are owner-configured filters, never provider eligibility or price claims. */
function provider_domain_search_tlds(mixed $value): array {
    if(!is_string($value)||strlen($value)>4096)throw new InvalidArgumentException('Enter up to 50 domain extensions, one per line.');
    $out=[];
    foreach(preg_split('/[\r\n,]+/',trim($value)) as $suffix) {
        $suffix=strtolower(trim($suffix));
        if($suffix==='')continue;
        if(strlen($suffix)>120 || str_starts_with($suffix,'.'))throw new InvalidArgumentException('Use extensions such as com or co.uk, without a leading dot.');
        DomainSearch::name('check.'.$suffix);$out[$suffix]=$suffix;
        if(count($out)>50)throw new InvalidArgumentException('Enter up to 50 domain extensions.');
    }
    sort($out,SORT_STRING);return array_values($out);
}
function provider_domain_search_offered(string $name,array $tlds): bool {
    foreach($tlds as $tld)if(str_ends_with($name,'.'.$tld)&&strlen($name)>strlen($tld)+1)return true;
    return false;
}
function provider_domain_search_configuration(): array {
    provider_settings_manager();return provider_domain_search_configuration_record();
}
/** Internal configuration parser; callers establish owner or client policy authority. */
function provider_domain_search_configuration_record(): array {
    $raw=one('SELECT value FROM '.table('settings').' WHERE name=?',['provider_domain_search_configuration'])['value']??'';
    if($raw==='')return ['revision'=>0,'enabled'=>false,'profile_id'=>'','profile_revision'=>0,'tlds'=>[]];
    try{$v=json_decode($raw,true,8,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Domain search settings need recovery.');}
    if(!is_array($v)||array_keys($v)!==['revision','enabled','profile_id','profile_revision','tlds']
        ||!is_int($v['revision'])||$v['revision']<1||!is_bool($v['enabled'])
        ||!is_string($v['profile_id'])||preg_match('/^[a-f0-9]{32}$/D',$v['profile_id'])!==1
        ||!is_int($v['profile_revision'])||$v['profile_revision']<1||!is_array($v['tlds'])
        ||!array_is_list($v['tlds'])||count($v['tlds'])>50)throw new RuntimeException('Domain search settings need recovery.');
    foreach($v['tlds'] as $suffix)if(!is_string($suffix))throw new RuntimeException('Domain search settings need recovery.');
    try{$valid=provider_domain_search_tlds(implode("\n",$v['tlds']));}catch(InvalidArgumentException){throw new RuntimeException('Domain search settings need recovery.');}
    if($valid!==$v['tlds']||($v['enabled']&&$valid===[]))throw new RuntimeException('Domain search settings need recovery.');
    return $v;
}
function provider_domain_search_configure(string $id,int $profileRevision,int $revision,bool $enabled,mixed $tlds,#[\SensitiveParameter] string $password): void {
    $tlds=provider_domain_search_tlds($tlds);
    if($enabled&&$tlds===[])throw new InvalidArgumentException('Choose at least one offered extension before enabling lookups.');
    staff_locked(fn()=>staff_transaction(function()use($id,$profileRevision,$revision,$enabled,$tlds,$password):void {
        $actor=provider_settings_owner($password);
        if(!provider_domain_search_ready())throw new RuntimeException('Prepare domain search storage first.');
        $profile=provider_settings_load($id);$old=provider_domain_search_configuration();
        if($profileRevision<1||(int)$profile['revision']!==$profileRevision||$revision!==$old['revision']||$revision>=2147483647)throw new RuntimeException('Domain search settings changed. Reload before saving.');
        $v=['revision'=>$revision+1,'enabled'=>$enabled,'profile_id'=>$id,'profile_revision'=>$profileRevision,'tlds'=>$tlds];
        save_setting('provider_domain_search_configuration',json_encode($v,JSON_THROW_ON_ERROR));
        provider_settings_audit((int)$actor['id'],'domain_search_configured','Search settings revision '.($revision+1).' profile '.$id.' revision '.$profileRevision);
    }));
}
function provider_domain_search_authority(string $id,int $profileRevision,int $configRevision): array {
    [$actor,$profile]=provider_checks_authority($id,$profileRevision);
    if(!provider_domain_search_ready())throw new RuntimeException('Prepare domain search storage first.');
    $cfg=provider_domain_search_configuration();
    if(!$cfg['enabled']||$cfg['revision']!==$configRevision||$cfg['profile_id']!==$id||$cfg['profile_revision']!==$profileRevision)throw new RuntimeException('Domain search permission or settings changed. Reload the current form.');
    return [$actor,$profile,$cfg];
}
/** Internal seam; no endpoint, callback, address or credentials come from an HTTP form. */
function provider_domain_search_transport(array $row,#[\SensitiveParameter] string $secret,string $address,callable $authorize,string $query): array {
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return DomainSearch::read(new FixedHttpTransport($address,$authorize),$credential,$query,time());
}
function provider_domain_search_record(array $intent,string $state,string $reason,?array $result,string $fingerprint,int $elapsed): void {
    staff_transaction(function()use($intent,$state,$reason,$result,$fingerprint,$elapsed):void {
        $now=time();
        query('INSERT INTO '.table('provider_domain_search_results').' (id,state,reason,result,key_fingerprint,finished_at,finished_epoch,elapsed_ms) VALUES (?,?,?,?,?,?,?,?)',
            [$intent['id'],$state,$reason,json_encode($result,JSON_THROW_ON_ERROR),$fingerprint,gmdate('Y-m-d H:i:s',$now),$now,max(0,min($elapsed,2147483647))]);
        provider_settings_audit((int)$intent['actor_id'],'domain_search_finished','Search '.$intent['id'].' '.$state.' '.$reason);
    });
}
/** One explicitly requested, context-bound read; shared durable limits include incomplete attempts. */
function provider_domain_search_run(string $id,int $profileRevision,int $configRevision,mixed $query,?callable $resolver=null,?callable $transport=null): string {
    $query=DomainSearch::name($query);
    if(db()->inTransaction())throw new LogicException('Domain lookups cannot run inside a transaction.');
    $intent=staff_locked(fn()=>staff_transaction(function()use($id,$profileRevision,$configRevision,$query):array {
        [$actor,,$cfg]=provider_domain_search_authority($id,$profileRevision,$configRevision);$now=time();
        if(!provider_domain_search_offered($query,$cfg['tlds']))throw new InvalidArgumentException('That extension is not in your configured offering.');
        provider_domain_search_limits($id,$now);
        $intent=['id'=>bin2hex(random_bytes(16)),'profile_id'=>$id,'profile_revision'=>$profileRevision,'config_revision'=>$configRevision,'actor_id'=>(int)$actor['id'],'query_name'=>$query,'tlds'=>json_encode($cfg['tlds'],JSON_THROW_ON_ERROR),'started_at'=>gmdate('Y-m-d H:i:s',$now),'started_epoch'=>$now];
        query('INSERT INTO '.table('provider_domain_searches').' (id,profile_id,profile_revision,config_revision,actor_id,query_name,tlds,started_at,started_epoch) VALUES (?,?,?,?,?,?,?,?,?)',array_values($intent));
        provider_settings_audit((int)$actor['id'],'domain_search_started','Search '.$intent['id'].' profile '.$id.' revision '.$profileRevision);return $intent;
    }));
    $authority=static function()use($intent):array {
        $a=provider_domain_search_authority($intent['profile_id'],$intent['profile_revision'],$intent['config_revision']);
        if((int)$a[0]['id']!==$intent['actor_id'])throw new RuntimeException('Actor changed.');return $a;
    };
    return provider_domain_search_execute($intent,$authority,'provider_domain_search_record',$resolver,$transport);
}
/** One shared transport/persistence pipeline. Callbacks are internal authority and storage adapters. */
function provider_domain_search_execute(array $intent,callable $authority,callable $record,?callable $resolver=null,?callable $transport=null):string {
    $begin=hrtime(true);$address='';$reason='';
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}
    catch(Throwable $e){if($e instanceof PDOException)throw $e;$reason='destination_unavailable';}
    return staff_locked(function()use($intent,$authority,$record,$address,$reason,$begin,$transport):string {
        $authorize=static function()use($authority):array {
            try{return $authority();}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;throw new ProviderDomainSearchAccessChanged('Domain search access changed.');}
        };
        $elapsed=static fn():int=>(int)((hrtime(true)-$begin)/1e6);
        $result=null;$fingerprint='';$state='unavailable';
        try{[,$profile]=$authorize();}catch(ProviderDomainSearchAccessChanged){$reason='access_changed';$state='access_changed';}
        if($reason==='') {
            try{$secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));$fingerprint=provider_inventory_fingerprint($secret);}
            catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$reason='credential';}
            if($reason==='') {
                try{
                    $result=($transport??'provider_domain_search_transport')($profile,$secret,$address,$authorize,$intent['query_name']);
                    if(!is_array($result)||($result['query']??null)!==$intent['query_name']||($result['scope']??null)!=='twenty-i|'.$intent['profile_id'].'|domains.search'
                        ||!in_array($result['state']??null,['checked','partial','unavailable'],true))throw new LogicException('Invalid search result.');
                    $tlds=json_decode($intent['tlds'],true,8,JSON_THROW_ON_ERROR);
                    $result['suggestions']=array_values(array_filter($result['suggestions'],static fn(string $name):bool=>provider_domain_search_offered($name,$tlds)));
                    $state=$result['state'];$reason=$result['reason'];
                }catch(ProviderDomainSearchAccessChanged){$state='access_changed';$reason='access_changed';$result=null;}
                catch(Throwable $e){if($e instanceof PDOException)throw $e;$state='unavailable';$reason='read_unavailable';$result=null;}
                finally{unset($secret);}
            }
        }
        try{$authorize();}catch(ProviderDomainSearchAccessChanged){$state='access_changed';$reason='access_changed';$result=null;$fingerprint='';}
        $record($intent,$state,$reason,$result,$fingerprint,$elapsed());
        return $intent['id'];
    });
}
function provider_domain_search_history(string $id,int $page=1): array {
    provider_settings_manager();provider_settings_load($id);
    if(!provider_domain_search_ready())return [];
    $page=max(1,min($page,100000));
    return all('SELECT s.*,r.state,r.reason,r.result,r.finished_at,r.elapsed_ms,u.name AS actor_name FROM '.table('provider_domain_searches').' s LEFT JOIN '.table('provider_domain_search_results').' r ON r.id=s.id LEFT JOIN '.table('users').' u ON u.id=s.actor_id WHERE s.profile_id=? ORDER BY s.started_epoch DESC,s.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$id]);
}

/** Staff and client requests consume the same durable provider budget, including incomplete attempts. */
function provider_domain_search_limits(string $profile,int $now):void {
    $sources=[['provider_domain_searches','provider_domain_search_results']];
    if(provider_domain_client_family())$sources[]=['provider_domain_client_searches','provider_domain_client_results'];
    $count=0;
    foreach($sources as [$requests,$results]){
        if(one('SELECT id FROM '.table($requests).' WHERE profile_id=? AND started_epoch>? LIMIT 1',[$profile,$now-30]))throw new RuntimeException('Wait 30 seconds between domain lookups.');
        $count+=(int)query('SELECT COUNT(*) FROM '.table($requests).' WHERE profile_id=? AND started_epoch>?',[$profile,$now-3600])->fetchColumn();
        if(one('SELECT r.id FROM '.table($results).' r JOIN '.table($requests).' s ON s.id=r.id WHERE s.profile_id=? AND r.reason=? AND r.finished_epoch>? LIMIT 1',[$profile,'rate_limited',$now-86400]))throw new RuntimeException('20i limited a recent lookup. Domain searches are paused for 24 hours from that response.');
    }
    if($count>=20)throw new RuntimeException('The shared limit of 20 domain lookups per hour has been reached.');
}
require_once CWP_CODE_ROOT.'/app/providers/domain_search_clients.php';
