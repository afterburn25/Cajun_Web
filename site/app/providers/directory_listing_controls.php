<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/directory_listing.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/directory_listing_controls_storage.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/DirectoryIndexingChange.php';
use CajunWebPro\Foundation\TwentyIDirectoryListing\DirectoryListingBinding;
use CajunWebPro\Foundation\TwentyI\Operations\DirectoryIndexingChange;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

function provider_directory_indexing_controls_grant_values(array $g):array {return array_map(static fn($k)=>(string)($g[$k]??''),['id','seq','profile_id','operation','scope_fingerprint','granted_by','granted_at','expires_epoch','profile_revision','scope_params','consumed_key','consumed_at']);}
function provider_directory_indexing_controls_decode(string $kind,array $row):array {
    try{$d=json_decode($row['document_json'],true,20,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Directory listing change evidence needs recovery.');}
    if(!is_array($d)||!in_array($d['version']??null,$kind==='request'?[1,2]:[1],true)||provider_directory_indexing_json($d)!==$row['document_json']||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256']))throw new RuntimeException('Directory listing change evidence needs recovery.');
    $values=$row;unset($values['proof']);$values=array_map(static fn($v)=>(string)$v,$values);
    if($kind==='request'){$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$row['id']]);if(!$g)throw new RuntimeException('Directory listing change approval is missing.');$values['grant']=provider_directory_indexing_controls_grant_values($g);}
    if(!hash_equals($row['proof'],provider_directory_indexing_controls_hmac($kind,$values)))throw new RuntimeException('Directory listing change proof needs recovery.');return $d;
}
function provider_directory_indexing_controls_prove(string $kind,array $row,?array $grant=null):array {
    $values=array_map(static fn($v)=>(string)$v,$row);if($kind==='request'){if(!$grant)throw new LogicException('Approval required.');$values['grant']=provider_directory_indexing_controls_grant_values($grant);}$row['proof']=provider_directory_indexing_controls_hmac($kind,$values);return $row;
}
function provider_directory_indexing_controls_request(string $id):array {
    provider_directory_indexing_id($id);$q=one('SELECT * FROM '.table('provider_directory_changes').' WHERE id=?',[$id]);if(!$q)throw new RuntimeException('This Directory listing change is unavailable.');$d=provider_directory_indexing_controls_decode('request',$q);
    try{$b=new DirectoryListingBinding($d['binding']??[]);$params=DirectoryIndexingChange::params($d['params']??[]);}catch(InvalidArgumentException|TypeError){throw new RuntimeException('Directory listing change binding needs recovery.');}
    if($d['reviewed']!==true||!is_bool($d['before_value']??null)||$d['before_value']===$params['value']||$params['packageId']!==$b->data['package_id']||$q['link_id']!==$b->data['link_id']||$q['profile_id']!==$b->data['profile_id']||(int)$q['package_id']!==$b->data['package_id']||!is_int($d['anchor_seq']??null)||!preg_match('/\A[a-f0-9]{64}\z/',$d['actor_auth']??''))throw new RuntimeException('Directory listing change binding needs recovery.');
    $g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);
    if($g['operation']!==DirectoryIndexingChange::OPERATION||!hash_equals($g['scope_fingerprint'],provider_operations_scope(DirectoryIndexingChange::OPERATION,$d))||(int)$g['profile_revision']!==$b->data['profile_revision']||$g['consumed_key']!=='directoryindexing:'.$id)throw new RuntimeException('Directory listing change approval needs recovery.');
    $source=provider_directory_indexing_source($d['anchor_id']);if(!$source)throw new RuntimeException('The reviewed Directory listing flag is missing.');$anchor=provider_directory_indexing_observation($source);
    if($anchor['seq']!==$d['anchor_seq']||$anchor['value']!==$d['before_value']||!hash_equals($anchor['proof'],$d['anchor_proof'])||!hash_equals($anchor['binding']->sha256,$b->sha256))throw new RuntimeException('The reviewed Directory listing flag needs recovery.');
    provider_directory_indexing_retained_binding($b);
    $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$g['consumed_key']]);
    if($ledger){$op=new ProviderOperation(DirectoryIndexingChange::OPERATION,$g['consumed_key'],$params);$identity=$g['profile_id'].':'.$g['profile_revision'].':'.$b->data['key_fingerprint'];if(!hash_equals($ledger['fingerprint'],ProviderGateway::requestFingerprint('twenty-i',$identity,$op))||($ledger['operation']!==null&&$ledger['operation']!==DirectoryIndexingChange::OPERATION))throw new RuntimeException('Directory listing execution identity needs recovery.');}
    $request=['row'=>$q,'document'=>$d,'binding'=>$b,'grant'=>$g,'ledger'=>$ledger];
    if($d['version']===2){require_once CWP_CODE_ROOT.'/app/providers/directory_listing_requests.php';provider_directory_indexing_requests_validate_origin($request);}elseif(array_key_exists('client_origin',$d))throw new RuntimeException('Direct Directory listing approval cannot claim a client origin.');
    return $request;
}
function provider_directory_indexing_controls_terminal(array $request):?array {
    $row=one('SELECT * FROM '.table('provider_directory_dispatch_ends').' WHERE id=?',[$request['row']['id']]);if(!$row)return null;$d=provider_directory_indexing_controls_decode('terminal',$row);
    if(($d['request_proof']??null)!==$request['row']['proof']||($d['phase']??null)!==$row['disposition']||($d['ledger_sha256']??null)!==$row['ledger_sha256']||!hash_equals($row['ledger_sha256'],provider_directory_indexing_controls_ledger_digest($request['ledger']))||(int)$row['actor_id']!==(int)$request['row']['actor_id'])throw new RuntimeException('Directory listing dispatch evidence needs recovery.');
    return ['row'=>$row,'document'=>$d];
}
function provider_directory_indexing_controls_context(DirectoryListingBinding $original,?string $password=null):array {
    $actor=$password===null?provider_settings_manager():provider_settings_owner($password);
    if(!provider_directory_indexing_controls_ready())throw new RuntimeException('Prepare Directory listing change storage first.');
    provider_directory_indexing_retained_binding($original);if(!provider_directory_indexing_assignment_matches($original))throw new RuntimeException('The Directory listing change assignment or account changed.');
    $current=provider_directory_indexing_binding($original->data['link_id']);[, $profile]=provider_checks_authority($current->data['profile_id'],$current->data['profile_revision']);
    return [$actor,$profile,$current];
}
/** Authenticate every retained index before it can hide a resource or release a pending fence.
 * Keyset pages bound PHP memory; no history is repaired or cached by a read. */
function provider_directory_indexing_controls_authenticated_history():void {
    foreach(provider_directory_indexing_controls_tables() as $kind=>$tableName){
        $after=null;$seen=0;
        do{
            // The first page includes even an empty/space-trimmed corrupt CHAR identifier.
            $rows=$after===null?all('SELECT * FROM '.table($tableName).' ORDER BY BINARY id LIMIT 64')
                :all('SELECT * FROM '.table($tableName).' WHERE BINARY id>BINARY ? ORDER BY BINARY id LIMIT 64',[$after]);
            foreach($rows as $row){
                ++$seen;
                if($kind==='request')provider_directory_indexing_controls_request($row['id']);
                elseif($kind==='terminal')provider_directory_indexing_controls_terminal(provider_directory_indexing_controls_request($row['id']));
                else provider_directory_indexing_controls_check_document($row,provider_directory_indexing_controls_request($row['request_id']));
                $after=$row['id'];
            }
        }while(count($rows)===64);
        if($seen!==provider_directory_indexing_controls_counter($kind))throw new RuntimeException('Directory listing change history counts disagree.');
    }
}
/** Every unresolved approval fences the resource, even after client or key changes. */
function provider_directory_indexing_controls_pending(string $profileId,int $packageId):?array {
    $q=table('provider_directory_changes');$t=table('provider_directory_dispatch_ends');$c=table('provider_directory_change_checks');
    return one("SELECT q.* FROM $q q LEFT JOIN $t t ON t.id=q.id WHERE q.profile_id=? AND q.package_id=? AND (t.id IS NULL OR BINARY t.disposition<>'not_sent') AND NOT EXISTS (SELECT 1 FROM $c c WHERE c.request_id=q.id AND BINARY c.disposition='observed') ORDER BY q.grant_seq DESC LIMIT 1",[$profileId,$packageId]);
}
/** Called from the existing saved projection; never prepares storage or exposes request details. */
function provider_directory_indexing_controls_current(string $linkId):bool {
    provider_directory_indexing_guard();if(!provider_directory_indexing_controls_ready())return true;$l=one('SELECT profile_id,package_id FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
    return $l!==null&&provider_directory_indexing_controls_pending($l['profile_id'],(int)$l['package_id'])===null;
}
function provider_directory_indexing_controls_precondition(string $linkId,string $anchorId,string $bindingHash):array {
    $binding=provider_directory_indexing_binding($linkId);$r=provider_directory_indexing_latest($linkId);
    if(!$r||$r['id']!==$anchorId||$r['state']!=='complete'||!service_observations_fresh($r)||!hash_equals($binding->sha256,$bindingHash))throw new RuntimeException('Refresh and review the latest Directory listing flag before changing it.');
    $anchor=provider_directory_indexing_observation($r);if(!hash_equals($binding->sha256,$anchor['binding']->sha256)||$anchor['observed_epoch']>time()+30)throw new RuntimeException('Refresh and review the latest Directory listing flag before changing it.');
    return [$binding,$anchor];
}
/** Current actor and original grant are checked before every credential/send boundary. */
function provider_directory_indexing_controls_authorize(array $request):array {
    if($request['document']['version']===2)provider_directory_indexing_requests_origin_current($request);
    [$actor,$profile,$current]=provider_directory_indexing_controls_context($request['binding']);$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$request['row']['id']]);
    if(!$g||$g['revoked_at']!==null||(int)$g['expires_epoch']<=time()||(int)$actor['id']!==(int)$request['row']['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$request['document']['actor_auth'])||!hash_equals($current->sha256,$request['binding']->sha256)||provider_directory_indexing_controls_terminal($request)!==null)throw new ProviderOperationAccessChanged('Directory listing change approval is no longer current.');
    provider_directory_indexing_controls_request($request['row']['id']);provider_directory_indexing_controls_precondition($request['row']['link_id'],$request['document']['anchor_id'],$request['binding']->sha256);return $profile;
}
function provider_directory_indexing_controls_end(array $request,string $phase):void {
    provider_directory_indexing_guard();if(!in_array($phase,['not_sent','attempted'],true))throw new LogicException('Unknown dispatch phase.');
    $request=provider_directory_indexing_controls_request($request['row']['id']);$ledger=$request['ledger'];if($phase==='not_sent'&&$ledger!==null)throw new RuntimeException('No-send evidence conflicts with an execution claim.');
    $digest=provider_directory_indexing_controls_ledger_digest($ledger);$d=['version'=>1,'request_proof'=>$request['row']['proof'],'phase'=>$phase,'ended_epoch'=>time(),'ledger_sha256'=>$digest];$json=provider_directory_indexing_json($d);
    $row=['id'=>$request['row']['id'],'actor_id'=>(int)$request['row']['actor_id'],'disposition'=>$phase,'ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];provider_directory_indexing_controls_insert('terminal',provider_directory_indexing_controls_prove('terminal',$row));
}
/** Internal admission; client origin and reciprocal decision commit in the caller transaction. */
function provider_directory_indexing_controls_admit_locked(string $requestId,string $linkId,string $anchorId,string $bindingHash,bool $desired,bool $reviewed,#[SensitiveParameter] string $password,?array $clientOrigin=null):bool {
    provider_directory_indexing_guard();provider_directory_indexing_id($requestId);provider_directory_indexing_id($linkId);provider_directory_indexing_id($anchorId);
    if(!$reviewed||!preg_match('/\A[a-f0-9]{64}\z/',$bindingHash))throw new InvalidArgumentException('Review this exact Directory listing change first.');
    $actor=provider_settings_owner($password);if(!provider_directory_indexing_controls_ready())throw new RuntimeException('Prepare Directory listing change storage first.');
    if(one('SELECT id FROM '.table('provider_directory_changes').' WHERE id=?',[$requestId])){$old=provider_directory_indexing_controls_request($requestId);if(($old['document']['client_origin']??null)!==$clientOrigin)throw new RuntimeException('This Directory listing approval has a different origin.');provider_directory_indexing_controls_context($old['binding']);if($old['row']['link_id']!==$linkId||$old['document']['anchor_id']!==$anchorId||!hash_equals($old['binding']->sha256,$bindingHash)||$old['document']['params']['value']!==$desired||(int)$old['row']['actor_id']!==(int)$actor['id'])throw new RuntimeException('This Directory listing request identifier was already used.');return false;}
    if($clientOrigin!==null)provider_directory_indexing_requests_admission_origin($clientOrigin,$linkId,$desired);
    [$binding,$anchor]=provider_directory_indexing_controls_precondition($linkId,$anchorId,$bindingHash);provider_directory_indexing_controls_context($binding);
    if($desired===$anchor['value'])throw new InvalidArgumentException('The latest saved flag already reports the requested value.');
    if(provider_directory_indexing_controls_pending($binding->data['profile_id'],$binding->data['package_id']))throw new RuntimeException('A previous Directory listing change is unresolved. It cannot be sent again.');
    $previous=one('SELECT * FROM '.table('provider_directory_changes').' WHERE profile_id=? AND package_id=? ORDER BY grant_seq DESC LIMIT 1',[$binding->data['profile_id'],$binding->data['package_id']]);
    if($previous){$old=provider_directory_indexing_controls_request($previous['id']);if($anchor['seq']<=$old['document']['anchor_seq'])throw new RuntimeException('Save a new Directory listing observation before approving another change.');}
    $params=DirectoryIndexingChange::params(['packageId'=>$binding->data['package_id'],'value'=>$desired]);$now=time();$d=['version'=>$clientOrigin===null?1:2,'binding'=>$binding->data,'anchor_id'=>$anchor['id'],'anchor_seq'=>$anchor['seq'],'anchor_proof'=>$anchor['proof'],'before_value'=>$anchor['value'],'params'=>$params,'reviewed'=>true,'actor_auth'=>hash('sha256',$actor['auth_version']),'approved_epoch'=>$now];if($clientOrigin!==null)$d['client_origin']=$clientOrigin;$json=provider_directory_indexing_json($d);if(strlen($json)>4096)throw new LogicException('Directory listing approval exceeds its storage contract.');
    query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$requestId,$binding->data['profile_id'],DirectoryIndexingChange::OPERATION,provider_operations_scope(DirectoryIndexingChange::OPERATION,$d),$binding->data['profile_revision'],$json,(int)$actor['id'],gmdate('Y-m-d H:i:s',$now),$now+900,'directoryindexing:'.$requestId,gmdate('Y-m-d H:i:s',$now)]);
    $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$requestId]);$q=['id'=>$requestId,'grant_seq'=>(int)$grant['seq'],'profile_id'=>$binding->data['profile_id'],'package_id'=>$binding->data['package_id'],'link_id'=>$linkId,'actor_id'=>(int)$actor['id'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];provider_directory_indexing_controls_insert('request',provider_directory_indexing_controls_prove('request',$q,$grant));return true;
}
/** Owning mutex stays held; network is outside a transaction. */
function provider_directory_indexing_controls_send_locked(string $requestId,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Directory listing dispatch cannot inherit a transaction.');
    $mutex='cwp-staff-'.substr(hash('sha256',config()['db']['name'].'|'.config()['db']['prefix']),0,40);if((int)query('SELECT COALESCE(IS_USED_LOCK(?),0)=CONNECTION_ID()',[$mutex])->fetchColumn()!==1)throw new LogicException('Directory listing dispatch requires the owning staff mutex.');
    $request=provider_directory_indexing_controls_request($requestId);$entered=false;
    try{
    $authorize=static fn()=>provider_directory_indexing_controls_authorize($request);$profile=$authorize();$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];$profile=$authorize();
    $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
    try{if(!hash_equals(provider_operations_credential_fingerprint($secret),$request['binding']->data['key_fingerprint']))throw new ProviderOperationAccessChanged('Provider account changed.');$authorize();$op=new ProviderOperation(DirectoryIndexingChange::OPERATION,$request['grant']['consumed_key'],$request['document']['params']);$entered=true;($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$op);}finally{unset($secret);}
    }catch(Throwable $e){if($e instanceof PDOException)throw $e;/* Never persist exception text or infer no-send after entry. */}
    // A failed ledger result, zero attempts or HTTP status cannot turn this phase back into not_sent.
    staff_transaction(fn()=>provider_directory_indexing_controls_end($request,$entered?'attempted':'not_sent'));
    return staff_transaction(fn()=>provider_directory_indexing_controls_summary($requestId));
}
/** The original direct-owner entry keeps version-one admission and replay behavior. */
function provider_directory_indexing_controls_change(string $requestId,string $linkId,string $anchorId,string $bindingHash,bool $desired,bool $reviewed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Directory listing changes cannot run in a transaction.');provider_directory_indexing_id($requestId);provider_directory_indexing_id($linkId);provider_directory_indexing_id($anchorId);
    if(!$reviewed||!preg_match('/\A[a-f0-9]{64}\z/',$bindingHash))throw new InvalidArgumentException('Review this exact Directory listing change first.');
    return staff_locked(function()use($requestId,$linkId,$anchorId,$bindingHash,$desired,$reviewed,$password,$resolver,$perform):array {
        $fresh=staff_transaction(fn()=>provider_directory_indexing_controls_admit_locked($requestId,$linkId,$anchorId,$bindingHash,$desired,$reviewed,$password));
        return $fresh?provider_directory_indexing_controls_send_locked($requestId,$resolver,$perform):staff_transaction(fn()=>provider_directory_indexing_controls_summary($requestId));
    });
}
function provider_directory_indexing_controls_check_document(array $row,array $request):array {
    if($request['document']['version']===2)provider_directory_indexing_controls_finished_ledger($request,time());
    $d=provider_directory_indexing_controls_decode('check',$row);$terminal=provider_directory_indexing_controls_terminal($request);$r=provider_directory_indexing_source($row['receipt_id']);
    if(!$terminal||$terminal['row']['disposition']!=='attempted'||!$r||$d['request_proof']!==$request['row']['proof']||$d['terminal_proof']!==$terminal['row']['proof']||$d['receipt_id']!==$row['receipt_id']||$d['receipt_seq']!==(int)$r['seq']||$d['disposition']!==$row['disposition']||!hash_equals($d['receipt_sha256'],provider_directory_indexing_row_hash($r))||$d['receipt_seq']<=$d['floor'])throw new RuntimeException('Directory listing readback evidence needs recovery.');
    $bRow=one('SELECT * FROM '.table('provider_directory_indexing_reads').' WHERE id=?',[$r['id']]);if(!$bRow)throw new RuntimeException('Directory listing readback binding is missing.');$binding=provider_directory_indexing_read_binding($r,$bRow);
    if(!hash_equals($d['binding_sha256'],$binding->sha256)||$binding->data['link_id']!==$request['row']['link_id'])throw new RuntimeException('Directory listing readback binding needs recovery.');
    if($r['state']==='complete'){$o=provider_directory_indexing_observation($r);if($d['value']!==$o['value']||$d['observation_proof']!==$o['proof']||$row['disposition']!==($o['value']===$request['document']['params']['value']?'observed':'different'))throw new RuntimeException('Directory listing readback value needs recovery.');}
    elseif($d['value']!==null||$d['observation_proof']!==null||$row['disposition']!=='unavailable')throw new RuntimeException('Unavailable Directory listing readback cannot confirm a flag.');return $d;
}
/** Internal atomic save of this exact newly returned receipt, never a latest-row replacement. */
function provider_directory_indexing_controls_save_check(array $plan,array $receipt):array {
    provider_directory_indexing_guard();$request=provider_directory_indexing_controls_request($plan['request_id']);if($request['document']['version']===2){provider_directory_indexing_requests_origin_current($request);provider_directory_indexing_controls_finished_ledger($request,time());}[$actor,,$current]=provider_directory_indexing_controls_context($request['binding']);$terminal=provider_directory_indexing_controls_terminal($request);
    if((int)$actor['id']!==$plan['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$plan['actor_auth'])||!$terminal||$terminal['row']['disposition']!=='attempted'||!hash_equals($plan['terminal_proof'],$terminal['row']['proof'])||!hash_equals($current->sha256,$plan['binding']->sha256))throw new RuntimeException('Directory listing verification authority changed.');
    if(array_keys($receipt)!==['id','state']||!is_string($receipt['id']))throw new LogicException('An exact Directory listing refresh receipt is required.');$r=provider_directory_indexing_source($receipt['id']);$latest=provider_directory_indexing_latest($request['row']['link_id']);
    if(!$r||!$latest||$latest['id']!==$receipt['id']||$r['state']===null||$r['state']!==$receipt['state']||(int)$r['seq']<=$plan['floor']||(int)$r['actor_id']!==$plan['actor_id']||(int)$r['started_epoch']<time()-900||(int)$r['started_epoch']>time()+30)throw new RuntimeException('The exact Directory listing verification read is no longer current.');
    $bRow=one('SELECT * FROM '.table('provider_directory_indexing_reads').' WHERE id=?',[$r['id']]);if(!$bRow)throw new RuntimeException('The Directory listing verification binding is missing.');$b=provider_directory_indexing_read_binding($r,$bRow);if(!hash_equals($current->sha256,$b->sha256))throw new RuntimeException('The Directory listing verification context changed.');
    $o=$r['state']==='complete'?provider_directory_indexing_observation($r):null;$state=$o?($o['value']===$request['document']['params']['value']?'observed':'different'):'unavailable';$d=['version'=>1,'request_proof'=>$request['row']['proof'],'terminal_proof'=>$terminal['row']['proof'],'floor'=>$plan['floor'],'receipt_id'=>$r['id'],'receipt_seq'=>(int)$r['seq'],'receipt_sha256'=>provider_directory_indexing_row_hash($r),'binding_sha256'=>$b->sha256,'disposition'=>$state,'value'=>$o['value']??null,'observation_proof'=>$o['proof']??null,'recorded_epoch'=>time()];$json=provider_directory_indexing_json($d);
    $row=['id'=>$plan['check_id'],'request_id'=>$request['row']['id'],'actor_id'=>(int)$actor['id'],'receipt_id'=>$r['id'],'disposition'=>$state,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];provider_directory_indexing_controls_insert('check',provider_directory_indexing_controls_prove('check',$row));return provider_directory_indexing_controls_summary($request['row']['id']);
}
/** Internal: request/grant/binding proof is validated by the owning caller first. */
function provider_directory_indexing_controls_finished_ledger(array $request,int $now):string {
    $fail=static function():never {throw new RuntimeException('This request has no eligible durable dispatch completion.');};
    $row=$request['ledger']??null;
    if(!is_array($row)||$now<0)$fail();
    foreach(['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch','reconciled_outcome','reconciled_epoch'] as $key)if(!array_key_exists($key,$row))$fail();
    $number=static function(mixed $value)use($fail):int {
        if((!is_int($value)&&!is_string($value))||preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',(string)$value)!==1||(int)$value>2147483647)$fail();
        return (int)$value;
    };
    $attempts=$number($row['attempts']);$claimed=$number($row['claimed_epoch']);$resolved=$number($row['resolved_epoch']);
    if(!is_string($row['id'])||preg_match('/\A[a-f0-9]{32}\z/',$row['id'])!==1
        ||$row['idempotency_key']!=='directoryindexing:'.$request['row']['id']
        ||$row['idempotency_key']!==$request['grant']['consumed_key']
        ||$row['operation']!==DirectoryIndexingChange::OPERATION||!in_array($row['status'],['failed','uncertain'],true)
        ||$attempts!==1||$claimed<$request['document']['approved_epoch']||$resolved<$claimed||$resolved>$now+30
        ||$row['claimed_at']!==gmdate('Y-m-d H:i:s',$claimed)
        ||$row['external_id']!==null||$row['reconciled_outcome']!==null||$row['reconciled_epoch']!==null)$fail();
    if(!is_string($row['result_message'])||strlen($row['result_message'])>200||preg_match('//u',$row['result_message'])!==1||preg_match('/[\x00-\x1f\x7f]/',$row['result_message'])
        ||($row['provider_code']!==null&&(!is_string($row['provider_code'])||strlen($row['provider_code'])>64||preg_match('/[\x00-\x1f\x7f]/',$row['provider_code']))))$fail();
    $params=DirectoryIndexingChange::params($request['document']['params']);
    $operation=new ProviderOperation(DirectoryIndexingChange::OPERATION,$request['grant']['consumed_key'],$params);
    $b=$request['binding']->data;
    $fingerprint=ProviderGateway::requestFingerprint('twenty-i',$b['profile_id'].':'.$b['profile_revision'].':'.$b['key_fingerprint'],$operation);
    if(!is_string($row['fingerprint'])||!hash_equals($fingerprint,$row['fingerprint']))$fail();
    return provider_directory_indexing_controls_ledger_digest($row);
}

/** Explicit original-owner action; reconstruct only a missing local witness, never a provider result. */
function provider_directory_indexing_controls_recover_finished(string $requestId,string $reviewedLedgerHash,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('Directory listing dispatch recovery cannot inherit a transaction.');
    provider_directory_indexing_id($requestId);
    if(preg_match('/\A[a-f0-9]{64}\z/',$reviewedLedgerHash)!==1)throw new InvalidArgumentException('Reload the completed-dispatch recovery form.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$reviewedLedgerHash,$password):array {
        $actor=provider_settings_owner($password);
        if(!provider_directory_indexing_controls_ready())throw new RuntimeException('Restore the original Directory listing change storage first.');
        $request=provider_directory_indexing_controls_request($requestId);
        if($request['document']['version']===2)provider_directory_indexing_requests_origin_current($request);
        provider_directory_indexing_controls_context($request['binding']);
        if((int)$actor['id']!==(int)$request['row']['actor_id']||!hash_equals($request['document']['actor_auth'],hash('sha256',$actor['auth_version']))||$request['grant']['revoked_at']!==null)
            throw new RuntimeException('The original Directory listing approval owner or context changed.');
        // Freeze the finished source row during reconstruction; missing and claimed rows are refused below.
        one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=? FOR UPDATE',[$request['grant']['consumed_key']]);
        $request=provider_directory_indexing_controls_request($requestId);
        $digest=provider_directory_indexing_controls_finished_ledger($request,time());
        if(!hash_equals($reviewedLedgerHash,$digest))throw new RuntimeException('The reviewed dispatch completion changed. Reload its history.');
        if(provider_directory_indexing_controls_terminal($request)!==null)throw new RuntimeException('This request already has a dispatch record. No recovery is needed.');
        $document=['version'=>1,'request_proof'=>$request['row']['proof'],'phase'=>'attempted','ended_epoch'=>time(),'ledger_sha256'=>$digest,'recovered_from_ledger'=>true];
        $json=provider_directory_indexing_json($document);
        $row=['id'=>$requestId,'actor_id'=>(int)$actor['id'],'disposition'=>'attempted','ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
        provider_directory_indexing_controls_insert('terminal',provider_directory_indexing_controls_prove('terminal',$row));
        provider_directory_indexing_controls_ready();
        return provider_directory_indexing_controls_summary($requestId);
    }));
}

/** No write is retried. This invokes the existing quota-limited GET outside every DB transaction. */
function provider_directory_indexing_controls_verify(string $requestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Directory listing verification cannot run in a transaction.');provider_directory_indexing_id($requestId);
    $plan=staff_locked(fn()=>staff_transaction(function()use($requestId,$password):array{
        $actor=provider_settings_owner($password);if(!provider_directory_indexing_controls_ready())throw new RuntimeException('Prepare Directory listing change storage first.');$request=provider_directory_indexing_controls_request($requestId);if($request['document']['version']===2){provider_directory_indexing_requests_origin_current($request);provider_directory_indexing_controls_finished_ledger($request,time());}[,,$binding]=provider_directory_indexing_controls_context($request['binding']);$terminal=provider_directory_indexing_controls_terminal($request);
        if(!$terminal)throw new RuntimeException('This interrupted request needs an operator recovery transition. Dispatch and reconciliation remain blocked.');
        if($terminal['row']['disposition']!=='attempted')throw new RuntimeException('This request was refused before provider dispatch. Save a new observation before a separate approval.');
        if(one('SELECT id FROM '.table('provider_directory_change_checks')." WHERE request_id=? AND BINARY disposition='observed'",[$requestId]))throw new RuntimeException('The desired flag was already observed after this request. Use the normal observation refresh for current information.');
        $plan=['request_id'=>$requestId,'check_id'=>bin2hex(random_bytes(16)),'actor_id'=>(int)$actor['id'],'actor_auth'=>hash('sha256',$actor['auth_version']),'binding'=>$binding,'terminal_proof'=>$terminal['row']['proof'],'floor'=>(int)query('SELECT COALESCE(MAX(seq),0) FROM '.table('provider_refreshes'))->fetchColumn()];if($request['document']['version']===2)$plan['client_origin']=true;return $plan;
    }));
    $receipt=isset($plan['client_origin'])?provider_directory_indexing_requests_refresh($requestId,$plan['binding'],$password,$resolver,$transport):provider_directory_indexing_refresh($plan['binding']->data['link_id'],$plan['binding'],$password,$resolver,$transport);
    return staff_locked(fn()=>staff_transaction(fn()=>provider_directory_indexing_controls_save_check($plan,$receipt)));
}
function provider_directory_indexing_controls_summary(string $requestId):array {
    provider_directory_indexing_guard();provider_settings_manager();if(!provider_directory_indexing_controls_ready())throw new RuntimeException('Prepare Directory listing change storage first.');$request=provider_directory_indexing_controls_request($requestId);$terminal=provider_directory_indexing_controls_terminal($request);$check=one('SELECT c.* FROM '.table('provider_directory_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$requestId]);$verified=$check?provider_directory_indexing_controls_check_document($check,$request):null;
    $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'uncertain');if($verified&&$verified['disposition']==='observed')$state='observed';
    $current=false;try{provider_directory_indexing_controls_context($request['binding']);$current=true;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
    $recovery=null;
    if($terminal===null&&$current){try{$actor=provider_settings_manager();if((int)$actor['id']===(int)$request['row']['actor_id']&&hash_equals($request['document']['actor_auth'],hash('sha256',$actor['auth_version']))&&$request['grant']['revoked_at']===null)$recovery=provider_directory_indexing_controls_finished_ledger($request,time());}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}}
    return ['id'=>$requestId,'link_id'=>$request['row']['link_id'],'package_id'=>(int)$request['row']['package_id'],'requested_value'=>$request['document']['params']['value'],'before_value'=>$request['document']['before_value'],'requested_epoch'=>$request['document']['approved_epoch'],'status'=>$state,'current_assignment'=>$current,'recovery_ledger_sha256'=>$recovery,'recovered_dispatch'=>$terminal!==null&&($terminal['document']['recovered_from_ledger']??false)===true,'last_check'=>$verified?['disposition'=>$verified['disposition'],'value'=>$verified['value'],'recorded_epoch'=>$verified['recorded_epoch']]:null];
}
