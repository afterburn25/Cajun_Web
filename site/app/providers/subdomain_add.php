<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/document_roots.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/subdomain_add_storage.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/SubdomainAdd.php';
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation};
use CajunWebPro\Foundation\TwentyI\Operations\SubdomainAdd;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

function provider_subdomain_add_grant_values(array $g):array {return array_map(static fn($k)=>(string)($g[$k]??''),['id','seq','profile_id','operation','scope_fingerprint','granted_by','granted_at','expires_epoch','profile_revision','scope_params','consumed_key','consumed_at']);}
/** Keep the shared 4096-byte grant column unchanged; commit to the complete private request. */
function provider_subdomain_add_grant_scope(string $id,string $document):array {
    provider_document_roots_id($id);
    return ['version'=>1,'request_id'=>$id,'request_sha256'=>hash('sha256',$document)];
}
function provider_subdomain_add_decode(string $kind,array $row):array {
    if(strlen($row['document_json'])>16384)throw new RuntimeException('Subdomain add evidence exceeds its bound.');
    try{$d=json_decode($row['document_json'],true,20,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Subdomain add evidence needs recovery.');}
    if(!is_array($d)||($d['version']??null)!==1||provider_document_roots_json($d)!==$row['document_json']||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256']))throw new RuntimeException('Subdomain add evidence needs recovery.');
    $values=$row;unset($values['proof']);$values=array_map(static fn($v)=>(string)$v,$values);
    if($kind==='request'){$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$row['id']]);if(!$g)throw new RuntimeException('Subdomain add approval is missing.');$values['grant']=provider_subdomain_add_grant_values($g);}
    if(!hash_equals($row['proof'],provider_subdomain_add_hmac($kind,$values)))throw new RuntimeException('Subdomain add proof needs recovery.');return $d;
}
function provider_subdomain_add_prove(string $kind,array $row,?array $grant=null):array {
    $values=array_map(static fn($v)=>(string)$v,$row);if($kind==='request'){if(!$grant)throw new LogicException('Approval required.');$values['grant']=provider_subdomain_add_grant_values($grant);}$row['proof']=provider_subdomain_add_hmac($kind,$values);return $row;
}
/** Locate exact saved text; a missing hostname is distinct from an empty path. */
function provider_subdomain_add_entry(array $observation,string $name):?string {
    foreach($observation['entries'] as $entry)if($entry['name']===$name)return $entry['path'];
    return null;
}
function provider_subdomain_add_request(string $id):array {
    provider_document_roots_id($id);$q=one('SELECT * FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$id]);
    if(!$q)throw new RuntimeException('This subdomain request is unavailable.');$d=provider_subdomain_add_decode('request',$q);
    if(array_keys($d)!==['version','binding','anchor_id','anchor_seq','anchor_proof','params','reviewed','review_proof','actor_auth','approved_epoch'])throw new RuntimeException('Subdomain request fields need recovery.');
    try{$b=provider_document_roots_from_data($d['binding']);$params=SubdomainAdd::params($d['params']);$review=provider_subdomain_add_review_document($d['review_proof']);}catch(InvalidArgumentException|TypeError){throw new RuntimeException('Subdomain request binding needs recovery.');}
    $p=$b->data['package'];
    if($d['reviewed']!==true||$params!==$d['params']||$params['packageId']!==$p['package_id']||$q['subdomain']!==$params['subdomain']||$q['link_id']!==$p['link_id']||$q['profile_id']!==$p['profile_id']||(int)$q['package_id']!==$p['package_id']
        ||!is_int($d['anchor_seq'])||!is_int($d['approved_epoch'])||$d['approved_epoch']<$review['issued_epoch']||$d['approved_epoch']>=$review['expires_epoch']
        ||$review['request_id']!==$id||$review['link_id']!==$q['link_id']||$review['actor_id']!==(int)$q['actor_id']||$review['actor_auth']!==$d['actor_auth']
        ||$review['binding_sha256']!==$b->sha256||$review['anchor_id']!==$d['anchor_id']||$review['anchor_proof']!==$d['anchor_proof'])throw new RuntimeException('Subdomain request identity needs recovery.');
    $g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);$scope=provider_subdomain_add_grant_scope($id,$q['document_json']);
    if(!$g||$g['operation']!==SubdomainAdd::OPERATION||$g['scope_params']!==provider_document_roots_json($scope)||!hash_equals($g['scope_fingerprint'],provider_operations_scope(SubdomainAdd::OPERATION,$scope))
        ||(int)$g['profile_revision']!==$p['profile_revision']||$g['consumed_key']!=='subdomainadd:'.$id||(int)$g['expires_epoch']!==$d['approved_epoch']+900
        ||$g['granted_at']!==gmdate('Y-m-d H:i:s',$d['approved_epoch'])||$g['consumed_at']!==$g['granted_at'])throw new RuntimeException('Subdomain approval needs recovery.');
    $source=provider_document_roots_source($d['anchor_id']);if(!$source)throw new RuntimeException('The reviewed hosting map is missing.');$anchor=provider_document_roots_observation($source);
    if($anchor['seq']!==$d['anchor_seq']||!hash_equals($anchor['proof'],$d['anchor_proof'])||!hash_equals($anchor['binding']->sha256,$b->sha256))throw new RuntimeException('The reviewed hosting map needs recovery.');
    provider_subdomain_add_target($b,$anchor,$params['parentDomain'],$params['subdomain']);provider_document_roots_retained_binding($b);
    if($review['previous_id']!==''){$previous=one('SELECT * FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$review['previous_id']]);
        if(!$previous||$previous['profile_id']!==$q['profile_id']||(int)$previous['package_id']!==(int)$q['package_id']||(int)$previous['grant_seq']>=(int)$q['grant_seq']||$previous['proof']!==$review['previous_proof'])throw new RuntimeException('The preceding subdomain request needs recovery.');}
    $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$g['consumed_key']]);
    if($ledger){$op=new ProviderOperation(SubdomainAdd::OPERATION,$g['consumed_key'],$params);$identity=$g['profile_id'].':'.$g['profile_revision'].':'.$p['key_fingerprint'];
        if(!hash_equals($ledger['fingerprint'],ProviderGateway::requestFingerprint('twenty-i',$identity,$op))||($ledger['operation']!==null&&$ledger['operation']!==SubdomainAdd::OPERATION))throw new RuntimeException('Subdomain execution identity needs recovery.');
        if($ledger['operation']===null&&($ledger['status']!=='claimed'||(int)$ledger['attempts']!==0||$ledger['resolved_epoch']!==null))throw new RuntimeException('Subdomain execution claim needs recovery.');}
    return ['row'=>$q,'document'=>$d,'binding'=>$b,'grant'=>$g,'ledger'=>$ledger];
}

function provider_subdomain_add_terminal(array $request):?array {
    $row=one('SELECT * FROM '.table('provider_subdomain_add_terminals').' WHERE id=?',[$request['row']['id']]);if(!$row)return null;$d=provider_subdomain_add_decode('terminal',$row);
    $keys=['version','request_proof','phase','ended_epoch','ledger_sha256'];if(array_key_exists('recovered_from_ledger',$d))$keys[]='recovered_from_ledger';
    if(array_keys($d)!==$keys||!is_int($d['ended_epoch'])||$d['ended_epoch']<$request['document']['approved_epoch']||$d['ended_epoch']>2147483647||(isset($d['recovered_from_ledger'])&&$d['recovered_from_ledger']!==true))throw new RuntimeException('Subdomain dispatch document needs recovery.');
    if(($d['request_proof']??null)!==$request['row']['proof']||($d['phase']??null)!==$row['disposition']||($d['ledger_sha256']??null)!==$row['ledger_sha256']||!hash_equals($row['ledger_sha256'],provider_subdomain_add_ledger_digest($request['ledger']))||(int)$row['actor_id']!==(int)$request['row']['actor_id'])throw new RuntimeException('Subdomain dispatch evidence needs recovery.');
    return ['row'=>$row,'document'=>$d];
}
function provider_subdomain_add_context(DocumentRootsBinding $original,bool $fresh=true):array {
    $actor=provider_subdomain_add_actor();
    if(!provider_subdomain_add_ready())throw new RuntimeException('Prepare subdomain additions first.');
    provider_document_roots_retained_binding($original);
    if(!provider_document_roots_assignment_matches($original))throw new RuntimeException('The original hosting assignment or account changed.');
    $current=$fresh?provider_document_roots_binding($original->data['package']['link_id']):$original;
    [, $profile]=provider_checks_authority($original->data['package']['profile_id'],$original->data['package']['profile_revision']);
    return [$actor,$profile,$current];
}

function provider_subdomain_add_authenticated_history():void {
    foreach(provider_subdomain_add_tables() as $kind=>$tableName){
        $after=null;$seen=0;
        do{
            // The first page includes even an empty/space-trimmed corrupt CHAR identifier.
            $rows=$after===null?all('SELECT * FROM '.table($tableName).' ORDER BY BINARY id LIMIT 64')
                :all('SELECT * FROM '.table($tableName).' WHERE BINARY id>BINARY ? ORDER BY BINARY id LIMIT 64',[$after]);
            foreach($rows as $row){
                ++$seen;
                if($kind==='request')provider_subdomain_add_request($row['id']);
                elseif($kind==='terminal')provider_subdomain_add_terminal(provider_subdomain_add_request($row['id']));
                else provider_subdomain_add_check_document($row,provider_subdomain_add_request($row['request_id']));
                $after=$row['id'];
            }
        }while(count($rows)===64);
        if($seen!==provider_subdomain_add_counter($kind))throw new RuntimeException('Subdomain add history counts disagree.');
    }
}
/** Every unresolved approval fences the resource, even after client or key changes. */
function provider_subdomain_add_pending(string $profileId,int $packageId,string $subdomain):?array {
    provider_subdomain_add_guard(false);if(!DocumentRootsObservation::validName($subdomain))throw new InvalidArgumentException('Invalid subdomain.');
    $q=table('provider_subdomain_add_requests');$t=table('provider_subdomain_add_terminals');$c=table('provider_subdomain_add_checks');
    return one("SELECT q.* FROM $q q LEFT JOIN $t t ON t.id=q.id WHERE q.profile_id=? AND q.package_id=? AND BINARY q.subdomain=BINARY ? AND (t.id IS NULL OR BINARY t.disposition<>'not_sent') AND NOT EXISTS (SELECT 1 FROM $c c WHERE c.request_id=q.id AND BINARY c.disposition='observed') ORDER BY q.grant_seq DESC LIMIT 1",[$profileId,$packageId,$subdomain]);
}

function provider_subdomain_add_current(string $linkId,string $subdomain):bool {
    provider_document_roots_guard();if(!provider_subdomain_add_ready())return true;$l=one('SELECT profile_id,package_id FROM '.table('provider_package_links').' WHERE id=?',[$linkId]);
    return $l!==null&&provider_subdomain_add_pending($l['profile_id'],(int)$l['package_id'],$subdomain)===null;
}

function provider_subdomain_add_precondition(string $linkId,string $anchorId,string $bindingHash):array {
    $binding=provider_document_roots_binding($linkId);$r=provider_document_roots_latest($linkId);
    if(!$r||$r['id']!==$anchorId||$r['state']!=='complete'||!service_observations_fresh($r)||!hash_equals($binding->sha256,$bindingHash))throw new RuntimeException('Refresh and review the latest subdomain map before changing it.');
    $anchor=provider_document_roots_observation($r);if(!hash_equals($binding->sha256,$anchor['binding']->sha256)||$anchor['observed_epoch']>time()+30)throw new RuntimeException('Refresh and review the latest subdomain map before changing it.');
    return [$binding,$anchor];
}
/** Current actor and original grant are checked before every credential/send boundary. */
function provider_subdomain_add_authorize(array $request):array {
    provider_subdomain_add_guard(false);
    return staff_transaction(function()use($request):array{
        $request=provider_subdomain_add_request($request['row']['id']);[$actor,$profile,$current]=provider_subdomain_add_context($request['binding']);$g=$request['grant'];
        if($g['revoked_at']!==null||(int)$g['expires_epoch']<=time()||(int)$actor['id']!==(int)$request['row']['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$request['document']['actor_auth'])
            ||!hash_equals($current->sha256,$request['binding']->sha256)||provider_subdomain_add_terminal($request)!==null)throw new ProviderOperationAccessChanged('Subdomain approval is no longer current.');
        [$binding,$anchor]=provider_subdomain_add_precondition($request['row']['link_id'],$request['document']['anchor_id'],$request['binding']->sha256);
        provider_subdomain_add_target($binding,$anchor,$request['document']['params']['parentDomain'],$request['document']['params']['subdomain']);return $profile;
    });
}

function provider_subdomain_add_end(array $request,string $phase):void {
    provider_document_roots_guard();if(!in_array($phase,['not_sent','attempted'],true))throw new LogicException('Unknown dispatch phase.');
    $request=provider_subdomain_add_request($request['row']['id']);$ledger=$request['ledger'];if($phase==='not_sent'&&$ledger!==null)throw new RuntimeException('No-send evidence conflicts with an execution claim.');
    $digest=provider_subdomain_add_ledger_digest($ledger);$d=['version'=>1,'request_proof'=>$request['row']['proof'],'phase'=>$phase,'ended_epoch'=>time(),'ledger_sha256'=>$digest];$json=provider_document_roots_json($d);
    $row=['id'=>$request['row']['id'],'actor_id'=>(int)$request['row']['actor_id'],'disposition'=>$phase,'ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];provider_subdomain_add_insert('terminal',provider_subdomain_add_prove('terminal',$row));
}
/** One owning mutex spans admission, DNS, gateway and the immutable terminal witness. */
/** One explicit hostname only. No directory, DNS, certificate or content success is inferred. */
function provider_subdomain_add_add(string $requestId,string $linkId,string $parentDomain,string $subdomain,string $reviewProof,bool $confirmed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Subdomain additions cannot run in a transaction.');
    provider_document_roots_id($requestId);provider_document_roots_id($linkId);
    if(!$confirmed)throw new InvalidArgumentException('Confirm this exact subdomain addition first.');$review=provider_subdomain_add_review_document($reviewProof);
    if($review['request_id']!==$requestId||$review['link_id']!==$linkId)throw new InvalidArgumentException('Reload the subdomain review.');
    return staff_locked(function()use($requestId,$linkId,$parentDomain,$subdomain,$reviewProof,$review,$password,$resolver,$perform):array{
        $fresh=staff_transaction(function()use($requestId,$linkId,$parentDomain,$subdomain,$reviewProof,$review,$password):bool{
            $actor=provider_subdomain_add_actor($password);if(!provider_subdomain_add_ready())throw new RuntimeException('Prepare subdomain additions first.');
            if(one('SELECT id FROM '.table('provider_subdomain_add_requests').' WHERE id=?',[$requestId])){
                $old=provider_subdomain_add_request($requestId);provider_subdomain_add_context($old['binding'],false);
                if($old['row']['link_id']!==$linkId||$old['document']['review_proof']!==$reviewProof||$old['document']['params']['parentDomain']!==$parentDomain||$old['document']['params']['subdomain']!==$subdomain
                    ||(int)$old['row']['actor_id']!==(int)$actor['id']||$old['document']['actor_auth']!==hash('sha256',$actor['auth_version']))throw new RuntimeException('This subdomain request identifier was already used.');return false;
            }
            if($review['actor_id']!==(int)$actor['id']||$review['actor_auth']!==hash('sha256',$actor['auth_version'])||time()<$review['issued_epoch']||time()>=$review['expires_epoch'])throw new RuntimeException('Reload the expired or changed subdomain review.');
            [$binding,$anchor]=provider_subdomain_add_precondition($linkId,$review['anchor_id'],$review['binding_sha256']);provider_subdomain_add_context($binding);
            if($review['anchor_proof']!==$anchor['proof'])throw new RuntimeException('The reviewed hosting map changed.');
            $params=provider_subdomain_add_target($binding,$anchor,$parentDomain,$subdomain);$p=$binding->data['package'];
            if(provider_subdomain_add_pending($p['profile_id'],$p['package_id'],$params['subdomain']))throw new RuntimeException('A previous addition for this hostname is unresolved. It cannot be sent again.');
            $previous=provider_subdomain_add_previous($p['profile_id'],$p['package_id']);
            if(($previous['id']??'')!==$review['previous_id']||($previous['proof']??str_repeat('0',64))!==$review['previous_proof'])throw new RuntimeException('Subdomain history changed. Reload the review.');
            if($previous){$old=provider_subdomain_add_request($previous['id']);if($anchor['seq']<=$old['document']['anchor_seq'])throw new RuntimeException('Save a new hosting map before a separate approval.');}
            $now=time();$d=['version'=>1,'binding'=>$binding->data,'anchor_id'=>$anchor['id'],'anchor_seq'=>$anchor['seq'],'anchor_proof'=>$anchor['proof'],'params'=>$params,
                'reviewed'=>true,'review_proof'=>$reviewProof,'actor_auth'=>hash('sha256',$actor['auth_version']),'approved_epoch'=>$now];$json=provider_document_roots_json($d);
            if(strlen($json)>16384)throw new LogicException('Subdomain approval exceeds its storage contract.');$scope=provider_subdomain_add_grant_scope($requestId,$json);
            query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$requestId,$p['profile_id'],SubdomainAdd::OPERATION,provider_operations_scope(SubdomainAdd::OPERATION,$scope),$p['profile_revision'],provider_document_roots_json($scope),(int)$actor['id'],gmdate('Y-m-d H:i:s',$now),$now+900,'subdomainadd:'.$requestId,gmdate('Y-m-d H:i:s',$now)]);
            $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$requestId]);$q=['id'=>$requestId,'grant_seq'=>(int)$grant['seq'],'profile_id'=>$p['profile_id'],'package_id'=>$p['package_id'],'subdomain'=>$subdomain,'link_id'=>$linkId,'actor_id'=>(int)$actor['id'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
            provider_subdomain_add_insert('request',provider_subdomain_add_prove('request',$q,$grant));return true;
        });
        if(!$fresh)return staff_transaction(fn()=>provider_subdomain_add_summary($requestId));
        $request=provider_subdomain_add_request($requestId);$entered=false;$failure=null;
        try{
            $authorize=static fn()=>provider_subdomain_add_authorize($request);$profile=$authorize();$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];$profile=$authorize();
            $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            try{if(!hash_equals(provider_operations_credential_fingerprint($secret),$request['binding']->data['package']['key_fingerprint']))throw new ProviderOperationAccessChanged('Provider account changed.');
                $authorize();$op=new ProviderOperation(SubdomainAdd::OPERATION,$request['grant']['consumed_key'],$request['document']['params']);$entered=true;($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$op);
            }finally{unset($secret);}
        }catch(Throwable $e){if($e instanceof PDOException)throw $e;$failure=$e;}
        staff_transaction(fn()=>provider_subdomain_add_end($request,$entered?'attempted':'not_sent'));
        if($failure!==null&&!($failure instanceof RuntimeException)&&!($failure instanceof InvalidArgumentException))throw $failure;
        return staff_transaction(fn()=>provider_subdomain_add_summary($requestId));
    });
}

function provider_subdomain_add_check_document(array $row,array $request):array {
    $d=provider_subdomain_add_decode('check',$row);
    if(array_keys($d)!==['version','request_proof','terminal_proof','floor','receipt_id','receipt_seq','receipt_sha256','binding_sha256','disposition','path','observation_proof','recorded_epoch']
        ||$row['request_id']!==$request['row']['id']||(int)$row['actor_id']!==(int)$request['row']['actor_id']||!is_int($d['floor'])||$d['floor']<$request['document']['anchor_seq']||!is_int($d['recorded_epoch'])||$d['recorded_epoch']<$request['document']['approved_epoch']||$d['recorded_epoch']>2147483647)throw new RuntimeException('Subdomain check document needs recovery.');
    $terminal=provider_subdomain_add_terminal($request);$r=provider_document_roots_source($row['receipt_id']);
    provider_subdomain_add_finished_ledger($request,time());
    if(!$terminal||$terminal['row']['disposition']!=='attempted'||!$r||$d['request_proof']!==$request['row']['proof']||$d['terminal_proof']!==$terminal['row']['proof']
        ||$d['receipt_id']!==$row['receipt_id']||$d['receipt_seq']!==(int)$r['seq']||$d['disposition']!==$row['disposition']
        ||!hash_equals($d['receipt_sha256'],provider_document_roots_row_hash($r))||$d['receipt_seq']<=$d['floor'])throw new RuntimeException('Subdomain readback evidence needs recovery.');
    $bRow=one('SELECT * FROM '.table('provider_document_roots_reads').' WHERE id=?',[$r['id']]);if(!$bRow)throw new RuntimeException('Subdomain readback binding is missing.');$binding=provider_document_roots_read_binding($r,$bRow);
    $original=$request['binding']->data['package'];$actual=$binding->data['package'];foreach(['source_id','source_seq','source_sha256'] as $key){unset($original[$key],$actual[$key]);}
    if($original!==$actual||(int)$r['actor_id']!==(int)$row['actor_id']||!hash_equals($d['binding_sha256'],$binding->sha256)||$binding->data['package']['link_id']!==$request['row']['link_id'])throw new RuntimeException('Subdomain readback binding needs recovery.');
    if($r['state']==='complete'){
        $o=provider_document_roots_observation($r);$path=provider_subdomain_add_entry($o,$request['document']['params']['subdomain']);
        if($d['path']!==$path||$d['observation_proof']!==$o['proof']||$row['disposition']!==($path!==null?'observed':'different'))throw new RuntimeException('Subdomain readback value needs recovery.');
    }elseif($d['path']!==null||$d['observation_proof']!==null||$row['disposition']!=='unavailable')throw new RuntimeException('Unavailable hosting readback cannot confirm a hostname.');
    return $d;
}
/** Internal atomic save of this exact newly returned receipt, never a latest-row replacement. */
function provider_subdomain_add_save_check(array $plan,array $receipt):array {
    provider_document_roots_guard();$request=provider_subdomain_add_request($plan['request_id']);
    [$actor,,$current]=provider_subdomain_add_context($request['binding']);provider_subdomain_add_original_actor($request,$actor);$terminal=provider_subdomain_add_terminal($request);
    provider_subdomain_add_finished_ledger($request,time());
    if((int)$actor['id']!==$plan['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$plan['actor_auth'])||!$terminal||$terminal['row']['disposition']!=='attempted'
        ||!hash_equals($plan['terminal_proof'],$terminal['row']['proof'])||!hash_equals($current->sha256,$plan['binding']->sha256))throw new RuntimeException('Subdomain verification authority changed.');
    if(array_keys($receipt)!==['id','state']||!is_string($receipt['id']))throw new LogicException('An exact subdomain refresh receipt is required.');
    $r=provider_document_roots_source($receipt['id']);$latest=provider_document_roots_latest($request['row']['link_id']);
    if(!$r||!$latest||$latest['id']!==$receipt['id']||$r['state']===null||$r['state']!==$receipt['state']||(int)$r['seq']<=$plan['floor']||(int)$r['actor_id']!==$plan['actor_id']
        ||(int)$r['started_epoch']<time()-900||(int)$r['started_epoch']>time()+30)throw new RuntimeException('The exact subdomain verification read is no longer current.');
    $bRow=one('SELECT * FROM '.table('provider_document_roots_reads').' WHERE id=?',[$r['id']]);if(!$bRow)throw new RuntimeException('The subdomain verification binding is missing.');
    $b=provider_document_roots_read_binding($r,$bRow);if(!hash_equals($current->sha256,$b->sha256))throw new RuntimeException('The subdomain verification context changed.');
    $o=$r['state']==='complete'?provider_document_roots_observation($r):null;
    $path=$o?provider_subdomain_add_entry($o,$request['document']['params']['subdomain']):null;
    $state=$o?($path!==null?'observed':'different'):'unavailable';
    $d=['version'=>1,'request_proof'=>$request['row']['proof'],'terminal_proof'=>$terminal['row']['proof'],'floor'=>$plan['floor'],'receipt_id'=>$r['id'],'receipt_seq'=>(int)$r['seq'],
        'receipt_sha256'=>provider_document_roots_row_hash($r),'binding_sha256'=>$b->sha256,'disposition'=>$state,'path'=>$path,'observation_proof'=>$o['proof']??null,'recorded_epoch'=>time()];
    $json=provider_document_roots_json($d);$row=['id'=>$plan['check_id'],'request_id'=>$request['row']['id'],'actor_id'=>(int)$actor['id'],'receipt_id'=>$r['id'],'disposition'=>$state,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
    provider_subdomain_add_insert('check',provider_subdomain_add_prove('check',$row));return provider_subdomain_add_summary($request['row']['id']);
}
/** Internal: request/grant/binding proof is validated by the owning caller first. */
function provider_subdomain_add_finished_ledger(array $request,int $now):string {
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
        ||$row['idempotency_key']!=='subdomainadd:'.$request['row']['id']
        ||$row['idempotency_key']!==$request['grant']['consumed_key']
        ||$row['operation']!==SubdomainAdd::OPERATION||!in_array($row['status'],['failed','uncertain'],true)
        ||$attempts!==1||$claimed<$request['document']['approved_epoch']||$resolved<$claimed||$resolved>$now+30
        ||$row['claimed_at']!==gmdate('Y-m-d H:i:s',$claimed)
        ||$row['external_id']!==null||$row['reconciled_outcome']!==null||$row['reconciled_epoch']!==null)$fail();
    if(!is_string($row['result_message'])||strlen($row['result_message'])>200||preg_match('//u',$row['result_message'])!==1||preg_match('/[\x00-\x1f\x7f]/',$row['result_message'])
        ||($row['provider_code']!==null&&(!is_string($row['provider_code'])||strlen($row['provider_code'])>64||preg_match('/[\x00-\x1f\x7f]/',$row['provider_code']))))$fail();
    $params=SubdomainAdd::params($request['document']['params']);
    $operation=new ProviderOperation(SubdomainAdd::OPERATION,$request['grant']['consumed_key'],$params);
    $b=$request['binding']->data['package'];
    $fingerprint=ProviderGateway::requestFingerprint('twenty-i',$b['profile_id'].':'.$b['profile_revision'].':'.$b['key_fingerprint'],$operation);
    if(!is_string($row['fingerprint'])||!hash_equals($fingerprint,$row['fingerprint']))$fail();
    return provider_subdomain_add_ledger_digest($row);
}

/** Explicit original-owner action; reconstruct only a missing local witness, never a provider result. */
function provider_subdomain_add_recover_finished(string $requestId,string $reviewedLedgerHash,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('Subdomain dispatch recovery cannot inherit a transaction.');
    provider_document_roots_id($requestId);
    if(preg_match('/\A[a-f0-9]{64}\z/',$reviewedLedgerHash)!==1)throw new InvalidArgumentException('Reload the completed-dispatch recovery form.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$reviewedLedgerHash,$password):array {
        $actor=provider_subdomain_add_actor($password);
        if(!provider_subdomain_add_ready())throw new RuntimeException('Restore the original Subdomain add storage first.');
        $request=provider_subdomain_add_request($requestId);
        provider_subdomain_add_context($request['binding'],false);
        if((int)$actor['id']!==(int)$request['row']['actor_id']||!hash_equals($request['document']['actor_auth'],hash('sha256',$actor['auth_version']))||$request['grant']['revoked_at']!==null)
            throw new RuntimeException('The original Subdomain approval owner or context changed.');
        // Freeze the finished source row during reconstruction; missing and claimed rows are refused below.
        one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=? FOR UPDATE',[$request['grant']['consumed_key']]);
        $request=provider_subdomain_add_request($requestId);
        $digest=provider_subdomain_add_finished_ledger($request,time());
        if(!hash_equals($reviewedLedgerHash,$digest))throw new RuntimeException('The reviewed dispatch completion changed. Reload its history.');
        if(provider_subdomain_add_terminal($request)!==null)throw new RuntimeException('This request already has a dispatch record. No recovery is needed.');
        $document=['version'=>1,'request_proof'=>$request['row']['proof'],'phase'=>'attempted','ended_epoch'=>time(),'ledger_sha256'=>$digest,'recovered_from_ledger'=>true];
        $json=provider_document_roots_json($document);
        $row=['id'=>$requestId,'actor_id'=>(int)$actor['id'],'disposition'=>'attempted','ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
        provider_subdomain_add_insert('terminal',provider_subdomain_add_prove('terminal',$row));
        provider_subdomain_add_ready();
        return provider_subdomain_add_summary($requestId);
    }));
}

/** No write is retried. This invokes the existing quota-limited GET outside every DB transaction. */
function provider_subdomain_add_verify(string $requestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Subdomain verification cannot run in a transaction.');provider_document_roots_id($requestId);
    $plan=staff_locked(fn()=>staff_transaction(function()use($requestId,$password):array{
        $actor=provider_subdomain_add_actor($password);if(!provider_subdomain_add_ready())throw new RuntimeException('Prepare Subdomain add storage first.');$request=provider_subdomain_add_request($requestId);[,,$binding]=provider_subdomain_add_context($request['binding']);provider_subdomain_add_original_actor($request,$actor);$terminal=provider_subdomain_add_terminal($request);
        if(!$terminal)throw new RuntimeException('This interrupted request needs an operator recovery transition. Dispatch and reconciliation remain blocked.');
        provider_subdomain_add_finished_ledger($request,time());
        if($terminal['row']['disposition']!=='attempted')throw new RuntimeException('This request was refused before provider dispatch. Save a new observation before a separate approval.');
        if(one('SELECT id FROM '.table('provider_subdomain_add_checks')." WHERE request_id=? AND BINARY disposition='observed'",[$requestId]))throw new RuntimeException('This hostname was already observed after this request. Use the normal hosting map refresh for current information.');
        return ['request_id'=>$requestId,'check_id'=>bin2hex(random_bytes(16)),'actor_id'=>(int)$actor['id'],'actor_auth'=>hash('sha256',$actor['auth_version']),'binding'=>$binding,'terminal_proof'=>$terminal['row']['proof'],'floor'=>(int)query('SELECT COALESCE(MAX(seq),0) FROM '.table('provider_refreshes'))->fetchColumn()];
    }));
    $receipt=provider_document_roots_refresh($plan['binding']->data['package']['link_id'],$plan['binding'],$password,$resolver,$transport);
    return staff_locked(fn()=>staff_transaction(fn()=>provider_subdomain_add_save_check($plan,$receipt)));
}
function provider_subdomain_add_summary(string $requestId):array {
    provider_document_roots_guard();$actor=provider_subdomain_add_actor();if(!provider_subdomain_add_ready())throw new RuntimeException('Prepare subdomain additions first.');
    $request=provider_subdomain_add_request($requestId);$terminal=provider_subdomain_add_terminal($request);
    $check=one('SELECT c.* FROM '.table('provider_subdomain_add_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$requestId]);
    $verified=$check?provider_subdomain_add_check_document($check,$request):null;
    $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'unknown');if($verified&&$verified['disposition']==='observed')$state='observed';
    $current=false;try{provider_subdomain_add_context($request['binding'],false);provider_subdomain_add_original_actor($request,$actor);$current=true;}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
    $recovery=null;$canVerify=false;
    if($current){try{$digest=provider_subdomain_add_finished_ledger($request,time());if($terminal===null)$recovery=$digest;elseif($terminal['row']['disposition']==='attempted'&&$state!=='observed')$canVerify=true;
    }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}}
    return ['id'=>$requestId,'link_id'=>$request['row']['link_id'],'package_id'=>(int)$request['row']['package_id'],'parent_domain'=>$request['document']['params']['parentDomain'],
        'subdomain'=>$request['document']['params']['subdomain'],'created_at'=>gmdate('Y-m-d H:i:s',$request['document']['approved_epoch']),
        'status'=>$state,'current_assignment'=>$current,'can_verify'=>$canVerify,'recovery_ledger_sha256'=>$recovery,
        'last_check'=>$verified?['disposition'=>$verified['disposition'],'recorded_epoch'=>$verified['recorded_epoch']]:null];
}

function provider_subdomain_add_actor(?string $password=null):array {
    if(array_key_exists('contact_id',$_SESSION)||array_key_exists('contact_auth',$_SESSION))throw new RuntimeException('Use the owner account to manage subdomains.');
    unset($GLOBALS['cwp_settings']);return $password===null?provider_settings_manager():provider_settings_owner($password);
}
function provider_subdomain_add_original_actor(array $request,array $actor):void {
    if((int)$actor['id']!==(int)$request['row']['actor_id']||hash('sha256',$actor['auth_version'])!==$request['document']['actor_auth']||$request['grant']['revoked_at']!==null)throw new RuntimeException('The original subdomain approval authority changed.');
}
function provider_subdomain_add_previous(string $profileId,int $packageId):?array {
    return one('SELECT * FROM '.table('provider_subdomain_add_requests').' WHERE profile_id=? AND package_id=? ORDER BY grant_seq DESC LIMIT 1',[$profileId,$packageId]);
}
function provider_subdomain_add_package(DocumentRootsBinding $binding):array {
    $p=$binding->data['package'];$source=provider_document_roots_package_source($p['source_id'],$p['package_id']);
    return service_observations_source($source['row'],$p['package_id'])['package'];
}
function provider_subdomain_add_target(DocumentRootsBinding $binding,array $anchor,string $parent,string $child):array {
    $params=SubdomainAdd::params(['packageId'=>$binding->data['package']['package_id'],'parentDomain'=>$parent,'subdomain'=>$child]);$package=provider_subdomain_add_package($binding);
    if(!$package['enabled']||!in_array($parent,$package['names'],true)||provider_subdomain_add_entry($anchor,$parent)===null)throw new InvalidArgumentException('Select an attached parent from the latest hosting map.');
    if(in_array($child,$package['names'],true)||provider_subdomain_add_entry($anchor,$child)!==null)throw new InvalidArgumentException('That hostname is already attached. No addition was sent.');
    return $params;
}
/** Signed form carries only opaque hashes/IDs; no private map, paths or credentials. */
function provider_subdomain_add_review_document(string $proof):array {
    if(strlen($proof)>4096||preg_match('/\A([A-Za-z0-9_-]+)\.([a-f0-9]{64})\z/',$proof,$m)!==1)throw new InvalidArgumentException('Reload the subdomain review.');
    $json=base64_decode(strtr($m[1],'-_','+/'),true);if($json===false||rtrim(strtr(base64_encode($json),'+/','-_'),'=')!==$m[1])throw new InvalidArgumentException('Reload the subdomain review.');
    try{$d=json_decode($json,true,8,JSON_THROW_ON_ERROR);}catch(JsonException){throw new InvalidArgumentException('Reload the subdomain review.');}
    if(!is_array($d)||array_keys($d)!==['version','request_id','link_id','actor_id','actor_auth','binding_sha256','anchor_id','anchor_proof','previous_id','previous_proof','issued_epoch','expires_epoch']
        ||$d['version']!==1||provider_document_roots_json($d)!==$json||!hash_equals(provider_subdomain_add_hmac('review',$d),$m[2]))throw new InvalidArgumentException('Reload the subdomain review.');
    foreach(['request_id','link_id','anchor_id'] as $k)provider_document_roots_id($d[$k]);if($d['previous_id']!=='')provider_document_roots_id($d['previous_id']);
    foreach(['actor_auth','binding_sha256','anchor_proof','previous_proof'] as $k)if(!is_string($d[$k])||preg_match('/\A[a-f0-9]{64}\z/',$d[$k])!==1)throw new InvalidArgumentException('Reload the subdomain review.');
    if(!is_int($d['actor_id'])||$d['actor_id']<1||!is_int($d['issued_epoch'])||$d['issued_epoch']<0||$d['issued_epoch']>2147483347||!is_int($d['expires_epoch'])||$d['expires_epoch']!==$d['issued_epoch']+300
        ||($d['previous_id']===''&&$d['previous_proof']!==str_repeat('0',64)))throw new InvalidArgumentException('Reload the subdomain review.');return $d;
}
function provider_subdomain_add_status():array {
    return staff_locked(fn()=>staff_transaction(function():array{provider_subdomain_add_actor();return ['ready'=>provider_subdomain_add_ready()];}));
}
function provider_subdomain_add_review(string $linkId):array {
    provider_document_roots_id($linkId);
    return staff_locked(fn()=>staff_transaction(function()use($linkId):array{
        $actor=provider_subdomain_add_actor();$out=['ready'=>false,'reason'=>'not_prepared','link_id'=>$linkId,'profile_id'=>'','package_id'=>0,'package_name'=>'','parent_domains'=>[],'request_id'=>'','review_proof'=>'','expires_epoch'=>0,'observed_epoch'=>0];
        if(!provider_subdomain_add_ready())return $out;
        $out['reason']='refresh_required';
        try{$binding=provider_document_roots_binding($linkId);$r=provider_document_roots_latest($linkId);
            if(!$r||$r['state']!=='complete'||!service_observations_fresh($r))return $out;
            [$binding,$anchor]=provider_subdomain_add_precondition($linkId,$r['id'],$binding->sha256);$package=provider_subdomain_add_package($binding);
        }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;return $out;}
        $p=$binding->data['package'];$out['profile_id']=$p['profile_id'];$out['package_id']=$p['package_id'];$out['package_name']=$package['name'];$out['observed_epoch']=$anchor['observed_epoch'];
        $parents=[];foreach($package['names'] as $name)if(DocumentRootsObservation::validName($name)&&provider_subdomain_add_entry($anchor,$name)!==null)$parents[]=$name;
        if(!$package['enabled']||$parents===[]){$out['reason']='no_attached_parent';return $out;}sort($parents,SORT_STRING);
        $previous=provider_subdomain_add_previous($p['profile_id'],$p['package_id']);
        if($previous&&$anchor['seq']<=provider_subdomain_add_request($previous['id'])['document']['anchor_seq'])return $out;
        $now=time();$id=bin2hex(random_bytes(16));$d=['version'=>1,'request_id'=>$id,'link_id'=>$linkId,'actor_id'=>(int)$actor['id'],'actor_auth'=>hash('sha256',$actor['auth_version']),
            'binding_sha256'=>$binding->sha256,'anchor_id'=>$anchor['id'],'anchor_proof'=>$anchor['proof'],'previous_id'=>$previous['id']??'','previous_proof'=>$previous['proof']??str_repeat('0',64),'issued_epoch'=>$now,'expires_epoch'=>$now+300];
        $proof=rtrim(strtr(base64_encode(provider_document_roots_json($d)),'+/','-_'),'=').'.'.provider_subdomain_add_hmac('review',$d);
        return array_replace($out,['ready'=>true,'reason'=>'','parent_domains'=>$parents,'request_id'=>$id,'review_proof'=>$proof,'expires_epoch'=>$now+300]);
    }));
}
function provider_subdomain_add_history(?string $linkId=null,int $page=1):array {
    if($linkId!==null)provider_document_roots_id($linkId);$page=max(1,min(100000,$page));
    return staff_locked(fn()=>staff_transaction(function()use($linkId,$page):array{
        provider_subdomain_add_actor();if(!provider_subdomain_add_ready())return ['items'=>[],'page'=>$page,'has_more'=>false,'ready'=>false];
        $rows=all('SELECT id FROM '.table('provider_subdomain_add_requests').($linkId!==null?' WHERE link_id=?':'').' ORDER BY grant_seq DESC LIMIT 21 OFFSET '.(($page-1)*20),$linkId!==null?[$linkId]:[]);
        return ['items'=>array_map(static fn($row)=>provider_subdomain_add_summary($row['id']),array_slice($rows,0,20)),'page'=>$page,'has_more'=>count($rows)>20,'ready'=>true];
    }));
}
