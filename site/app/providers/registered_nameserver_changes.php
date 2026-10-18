<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/registered_nameservers.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/providers/registered_nameserver_changes_storage.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/RegistrarNameserverChange.php';
use CajunWebPro\Foundation\TwentyIRegistrarNameservers\RegisteredNameserverBinding;
use CajunWebPro\Foundation\TwentyI\Operations\RegistrarNameserverChange;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

function registered_nameserver_changes_grant_values(array $g):array {return array_map(static fn($k)=>(string)($g[$k]??''),['id','seq','profile_id','operation','scope_fingerprint','granted_by','granted_at','expires_epoch','profile_revision','scope_params','consumed_key','consumed_at']);}
function registered_nameserver_changes_decode(string $kind,array $row):array {
    if(array_keys($row)!==registered_nameserver_changes_columns($kind))throw new RuntimeException('Nameserver change row needs recovery.');
    try{$d=json_decode($row['document_json'],true,20,JSON_THROW_ON_ERROR);}catch(JsonException){throw new RuntimeException('Nameserver change evidence needs recovery.');}
    if(!is_array($d)||($d['version']??null)!==1||registered_nameservers_json($d)!==$row['document_json']||!hash_equals(hash('sha256',$row['document_json']),$row['document_sha256']))throw new RuntimeException('Nameserver change evidence needs recovery.');
    $values=$row;unset($values['proof']);$values=array_map(static fn($v)=>(string)$v,$values);
    if($kind==='request'){$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$row['id']]);if(!$g)throw new RuntimeException('Nameserver change approval is missing.');$values['grant']=registered_nameserver_changes_grant_values($g);}
    if(!hash_equals($row['proof'],registered_nameserver_changes_hmac($kind,$values)))throw new RuntimeException('Nameserver change proof needs recovery.');return $d;
}
function registered_nameserver_changes_prove(string $kind,array $row,?array $grant=null):array {
    $values=array_map(static fn($v)=>(string)$v,$row);if($kind==='request'){if(!$grant)throw new LogicException('Approval required.');$values['grant']=registered_nameserver_changes_grant_values($grant);}$row['proof']=registered_nameserver_changes_hmac($kind,$values);return $row;
}
function registered_nameserver_changes_request(string $id):array {
    registered_nameservers_id($id);$q=one('SELECT * FROM '.table('provider_nameserver_change_requests').' WHERE id=?',[$id]);if(!$q)throw new RuntimeException('This nameserver change is unavailable.');$d=registered_nameserver_changes_decode('request',$q);
    if(array_keys($d)!==['version','binding','anchor_id','anchor_seq','anchor_sha256','before_nameservers','params','reviewed','actor_auth','approved_epoch'])throw new RuntimeException('Nameserver change document needs recovery.');
    try{$b=new RegisteredNameserverBinding($d['binding']);$params=RegistrarNameserverChange::params($d['params']);registered_nameservers_id($d['anchor_id']);}catch(InvalidArgumentException|TypeError){throw new RuntimeException('Nameserver change binding needs recovery.');}
    if($d['params']!==$params||$d['reviewed']!==true||$d['before_nameservers']!==$params['old-ns']||$params['domainId']!==$b->data['domain_id']||$q['link_id']!==$b->data['link_id']||$q['profile_id']!==$b->data['profile_id']||(int)$q['domain_id']!==$b->data['domain_id']||!is_int($d['anchor_seq'])||$d['anchor_seq']<1||!is_string($d['anchor_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['anchor_sha256'])||!is_string($d['actor_auth'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['actor_auth'])||!is_int($d['approved_epoch'])||$d['approved_epoch']<1||$d['approved_epoch']>2147482747)throw new RuntimeException('Nameserver change binding needs recovery.');
    $g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);
    if($g['operation']!==RegistrarNameserverChange::OPERATION||!hash_equals($g['scope_fingerprint'],provider_operations_scope(RegistrarNameserverChange::OPERATION,$d))||(int)$g['profile_revision']!==$b->data['profile_revision']||$g['consumed_key']!=='nameservers:'.$id||$g['scope_params']!==$q['document_json']||(int)$g['granted_by']!==(int)$q['actor_id']||(int)$g['seq']!==(int)$q['grant_seq']||$g['profile_id']!==$q['profile_id']||$g['granted_at']!==gmdate('Y-m-d H:i:s',$d['approved_epoch'])||$g['consumed_at']!==$g['granted_at']||(int)$g['expires_epoch']!==$d['approved_epoch']+900)throw new RuntimeException('Nameserver change approval needs recovery.');
    $anchor=registered_nameservers_read_row($d['anchor_id']);
    if($anchor['state']!=='complete'||$anchor['seq']!==$d['anchor_seq']||$anchor['nameservers']!==$params['old-ns']||!hash_equals(registered_nameserver_changes_receipt_hash($anchor),$d['anchor_sha256'])||!hash_equals($anchor['binding']->sha256,$b->sha256))throw new RuntimeException('The complete reviewed nameserver set needs recovery.');
    $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$g['consumed_key']]);
    if($ledger){$op=new ProviderOperation(RegistrarNameserverChange::OPERATION,$g['consumed_key'],$params);$identity=$g['profile_id'].':'.$g['profile_revision'].':'.$b->data['key_fingerprint'];$claimed=$ledger['operation']===null&&$ledger['status']==='claimed'&&(int)$ledger['attempts']===0&&$ledger['resolved_epoch']===null;if(!hash_equals($ledger['fingerprint'],ProviderGateway::requestFingerprint('twenty-i',$identity,$op))||(!$claimed&&$ledger['operation']!==RegistrarNameserverChange::OPERATION))throw new RuntimeException('Nameserver execution identity needs recovery.');}
    return ['row'=>$q,'document'=>$d,'binding'=>$b,'grant'=>$g,'ledger'=>$ledger];
}
function registered_nameserver_changes_terminal(array $request):?array {
    $row=one('SELECT * FROM '.table('provider_nameserver_change_terminals').' WHERE id=?',[$request['row']['id']]);if(!$row)return null;$d=registered_nameserver_changes_decode('terminal',$row);
    $keys=['version','request_proof','phase','ended_epoch','ledger_sha256'];if(array_key_exists('recovered_from_ledger',$d))$keys[]='recovered_from_ledger';
    if(array_keys($d)!==$keys||($d['recovered_from_ledger']??true)!==true||!is_int($d['ended_epoch'])||$d['ended_epoch']<$request['document']['approved_epoch']||!in_array($d['phase'],['not_sent','attempted'],true)||$d['request_proof']!==$request['row']['proof']||$d['phase']!==$row['disposition']||$d['ledger_sha256']!==$row['ledger_sha256']||!hash_equals($row['ledger_sha256'],registered_nameserver_changes_ledger_digest($request['ledger']))||(int)$row['actor_id']!==(int)$request['row']['actor_id']||($d['phase']==='not_sent'&&($request['ledger']!==null||array_key_exists('recovered_from_ledger',$d))))throw new RuntimeException('Nameserver dispatch evidence needs recovery.');
    if(($d['recovered_from_ledger']??false)===true)registered_nameserver_changes_finished_ledger($request,$d['ended_epoch']);
    return ['row'=>$row,'document'=>$d];
}
function registered_nameserver_changes_context(RegisteredNameserverBinding $original,?string $password=null):array {
    $actor=registered_nameserver_changes_actor($password);
    if(!registered_nameserver_changes_ready())throw new RuntimeException('Prepare nameserver change storage first.');
    $current=registered_nameservers_binding($original->data['link_id'],$original->data['link_revision']);
    foreach($original->data as $key=>$value)if(!str_starts_with($key,'source_')&&$current->data[$key]!==$value)throw new RuntimeException('The nameserver change assignment or account changed.');
    [, $profile]=provider_checks_authority($current->data['profile_id'],$current->data['profile_revision']);return [$actor,$profile,$current];
}
/** Authenticate every retained index before it can hide a resource or release a pending fence.
 * Keyset pages bound PHP memory; no history is repaired or cached by a read. */
function registered_nameserver_changes_authenticated_history():void {
    foreach(registered_nameserver_changes_tables() as $kind=>$tableName){
        $after=null;$seen=0;
        do{
            // The first page includes even an empty/space-trimmed corrupt CHAR identifier.
            $rows=$after===null?all('SELECT * FROM '.table($tableName).' ORDER BY BINARY id LIMIT 64')
                :all('SELECT * FROM '.table($tableName).' WHERE BINARY id>BINARY ? ORDER BY BINARY id LIMIT 64',[$after]);
            foreach($rows as $row){
                ++$seen;
                if($kind==='request')registered_nameserver_changes_request($row['id']);
                elseif($kind==='terminal')registered_nameserver_changes_terminal(registered_nameserver_changes_request($row['id']));
                else registered_nameserver_changes_check_document($row,registered_nameserver_changes_request($row['request_id']));
                $after=$row['id'];
            }
        }while(count($rows)===64);
        if($seen!==registered_nameserver_changes_counter($kind))throw new RuntimeException('Nameserver change history counts disagree.');
    }
}
/** Every unresolved approval fences the resource, even after client or key changes. */
function registered_nameserver_changes_pending(string $profileId,int $domainId):?array {
    registered_nameservers_guard();registered_nameservers_id($profileId);RegistrarNameserverChange::id($domainId);
    if(!registered_nameserver_changes_ready())return null;
    $q=table('provider_nameserver_change_requests');$t=table('provider_nameserver_change_terminals');$c=table('provider_nameserver_change_checks');
    return one("SELECT q.* FROM $q q LEFT JOIN $t t ON t.id=q.id WHERE BINARY q.profile_id=? AND q.domain_id=? AND (t.id IS NULL OR BINARY t.disposition<>'not_sent') AND NOT EXISTS (SELECT 1 FROM $c c WHERE c.request_id=q.id AND BINARY c.disposition='observed') ORDER BY q.grant_seq DESC LIMIT 1",[$profileId,$domainId]);
}
/** Called from the existing saved projection; never prepares storage or exposes request details. */
function registered_nameserver_changes_current(string $linkId):bool {
    registered_nameservers_guard();registered_nameservers_id($linkId);if(!registered_nameserver_changes_ready())return true;$l=one('SELECT profile_id,domain_id FROM '.table('provider_registrar_links').' WHERE id=?',[$linkId]);
    return $l!==null&&registered_nameserver_changes_pending($l['profile_id'],(int)$l['domain_id'])===null;
}
function registered_nameserver_changes_precondition(string $linkId,string $anchorId,string $bindingHash):array {
    $l=one('SELECT revision FROM '.table('provider_registrar_links').' WHERE id=?',[$linkId]);if(!$l)throw new RuntimeException('This registered-domain assignment changed. Reload it.');
    $binding=registered_nameservers_binding($linkId,(int)$l['revision']);$r=registered_nameserver_changes_latest($linkId);
    if(!$r||$r['id']!==$anchorId||$r['state']!=='complete'||$r['nameservers']===[]||$r['observed_epoch']<time()-900||$r['observed_epoch']>time()+30||!hash_equals($binding->sha256,$bindingHash)||!hash_equals($binding->sha256,$r['binding']->sha256))throw new RuntimeException('Refresh and review the latest complete nameserver set before changing it.');
    return [$binding,$r];
}
/** Current actor and original grant are checked before every credential/send boundary. */
function registered_nameserver_changes_authorize(array $request):array {
    registered_nameservers_guard(false);$request=registered_nameserver_changes_request($request['row']['id']);
    [$actor,$profile,$current]=registered_nameserver_changes_context($request['binding']);$g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$request['row']['id']]);
    if(!$g||$g['revoked_at']!==null||(int)$g['expires_epoch']<=time()||(int)$actor['id']!==(int)$request['row']['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$request['document']['actor_auth'])||!hash_equals($current->sha256,$request['binding']->sha256)||registered_nameserver_changes_terminal($request)!==null)throw new ProviderOperationAccessChanged('Nameserver change approval is no longer current.');
    registered_nameserver_changes_request($request['row']['id']);registered_nameserver_changes_precondition($request['row']['link_id'],$request['document']['anchor_id'],$request['binding']->sha256);return $profile;
}
function registered_nameserver_changes_end(array $request,string $phase):void {
    registered_nameservers_guard();if(!in_array($phase,['not_sent','attempted'],true))throw new LogicException('Unknown dispatch phase.');
    $request=registered_nameserver_changes_request($request['row']['id']);$ledger=$request['ledger'];if($phase==='not_sent'&&$ledger!==null)throw new RuntimeException('No-send evidence conflicts with an execution claim.');
    $digest=registered_nameserver_changes_ledger_digest($ledger);$d=['version'=>1,'request_proof'=>$request['row']['proof'],'phase'=>$phase,'ended_epoch'=>time(),'ledger_sha256'=>$digest];$json=registered_nameservers_json($d);
    $row=['id'=>$request['row']['id'],'actor_id'=>(int)$request['row']['actor_id'],'disposition'=>$phase,'ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];registered_nameserver_changes_insert('terminal',registered_nameserver_changes_prove('terminal',$row));
}
/** One owning mutex spans admission, DNS, gateway and the immutable terminal witness. */
function registered_nameserver_changes_change(string $requestId,string $linkId,string $anchorId,string $bindingHash,array $desired,bool $reviewed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Nameserver changes cannot run in a transaction.');registered_nameservers_id($requestId);registered_nameservers_id($linkId);registered_nameservers_id($anchorId);
    if(!$reviewed||!preg_match('/\A[a-f0-9]{64}\z/',$bindingHash))throw new InvalidArgumentException('Review this exact nameserver change first.');
    $desired=\CajunWebPro\Foundation\TwentyI\Operations\NameserverChange::observed($desired);
    return staff_locked(function()use($requestId,$linkId,$anchorId,$bindingHash,$desired,$password,$resolver,$perform):array{
        $fresh=staff_transaction(function()use($requestId,$linkId,$anchorId,$bindingHash,$desired,$password):bool{
            $actor=registered_nameserver_changes_actor($password);if(!registered_nameserver_changes_ready())throw new RuntimeException('Prepare nameserver change storage first.');
            if(one('SELECT id FROM '.table('provider_nameserver_change_requests').' WHERE id=?',[$requestId])){$old=registered_nameserver_changes_request($requestId);registered_nameserver_changes_context($old['binding']);if($old['row']['link_id']!==$linkId||$old['document']['anchor_id']!==$anchorId||!hash_equals($old['binding']->sha256,$bindingHash)||$old['document']['params']['ns']!==$desired||(int)$old['row']['actor_id']!==(int)$actor['id']||!hash_equals($old['document']['actor_auth'],hash('sha256',$actor['auth_version'])))throw new RuntimeException('This nameserver request identifier was already used.');return false;}
            [$binding,$anchor]=registered_nameserver_changes_precondition($linkId,$anchorId,$bindingHash);registered_nameserver_changes_context($binding);
            // Registrar semantics are whole, nonempty, disjoint sets. Never a package delta.
            $params=RegistrarNameserverChange::params(['domainId'=>$binding->data['domain_id'],'ns'=>$desired,'old-ns'=>$anchor['nameservers']]);
            if(registered_nameserver_changes_pending($binding->data['profile_id'],$binding->data['domain_id']))throw new RuntimeException('A previous nameserver change is unresolved. It cannot be sent again.');
            $previous=one('SELECT * FROM '.table('provider_nameserver_change_requests').' WHERE profile_id=? AND domain_id=? ORDER BY grant_seq DESC LIMIT 1',[$binding->data['profile_id'],$binding->data['domain_id']]);
            if($previous){$old=registered_nameserver_changes_request($previous['id']);if($anchor['seq']<=$old['document']['anchor_seq'])throw new RuntimeException('Save a new nameserver observation before approving another change.');}
            $now=time();if($now<1||$now>2147482747)throw new RuntimeException('The local nameserver approval clock is outside its supported range.');
            $d=['version'=>1,'binding'=>$binding->data,'anchor_id'=>$anchor['id'],'anchor_seq'=>$anchor['seq'],'anchor_sha256'=>registered_nameserver_changes_receipt_hash($anchor),'before_nameservers'=>$anchor['nameservers'],'params'=>$params,'reviewed'=>true,'actor_auth'=>hash('sha256',$actor['auth_version']),'approved_epoch'=>$now];$json=registered_nameservers_json($d);if(strlen($json)>4096)throw new InvalidArgumentException('This complete nameserver change exceeds the local approval size limit.');
            query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$requestId,$binding->data['profile_id'],RegistrarNameserverChange::OPERATION,provider_operations_scope(RegistrarNameserverChange::OPERATION,$d),$binding->data['profile_revision'],$json,(int)$actor['id'],gmdate('Y-m-d H:i:s',$now),$now+900,'nameservers:'.$requestId,gmdate('Y-m-d H:i:s',$now)]);
            $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$requestId]);$q=['id'=>$requestId,'grant_seq'=>(int)$grant['seq'],'profile_id'=>$binding->data['profile_id'],'domain_id'=>$binding->data['domain_id'],'link_id'=>$linkId,'actor_id'=>(int)$actor['id'],'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];registered_nameserver_changes_insert('request',registered_nameserver_changes_prove('request',$q,$grant));return true;
        });
        if(!$fresh)return staff_transaction(fn()=>registered_nameserver_changes_summary($requestId));
        $request=registered_nameserver_changes_request($requestId);$entered=false;
        try{
            $authorize=static fn()=>registered_nameserver_changes_authorize($request);$profile=$authorize();$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];$profile=$authorize();
            $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            try{if(!hash_equals(provider_operations_credential_fingerprint($secret),$request['binding']->data['key_fingerprint']))throw new ProviderOperationAccessChanged('Provider account changed.');$authorize();$op=new ProviderOperation(RegistrarNameserverChange::OPERATION,$request['grant']['consumed_key'],$request['document']['params']);$entered=true;($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$op);}finally{unset($secret);}
        }catch(Throwable $e){if($e instanceof PDOException)throw $e;/* Never save exception text or infer no-send after performer entry. */}
        staff_transaction(fn()=>registered_nameserver_changes_end($request,$entered?'attempted':'not_sent'));
        return staff_transaction(fn()=>registered_nameserver_changes_summary($requestId));
    });
}
function registered_nameserver_changes_check_document(array $row,array $request):array {
    $d=registered_nameserver_changes_decode('check',$row);$terminal=registered_nameserver_changes_terminal($request);$r=registered_nameservers_read_row($row['receipt_id']);
    if(array_keys($d)!==['version','request_proof','terminal_proof','floor','receipt_id','receipt_seq','receipt_sha256','binding_sha256','disposition','nameservers','recorded_epoch']||!$terminal||$terminal['row']['disposition']!=='attempted'||!is_int($d['floor'])||$d['floor']<0||!is_int($d['receipt_seq'])||!is_int($d['recorded_epoch'])||$d['recorded_epoch']<$terminal['document']['ended_epoch']||$d['request_proof']!==$request['row']['proof']||$d['terminal_proof']!==$terminal['row']['proof']||$d['receipt_id']!==$row['receipt_id']||$d['receipt_seq']!==$r['seq']||$d['disposition']!==$row['disposition']||!hash_equals($d['receipt_sha256'],registered_nameserver_changes_receipt_hash($r))||$d['receipt_seq']<=$d['floor'])throw new RuntimeException('Nameserver readback evidence needs recovery.');
    registered_nameserver_changes_finished_ledger($request,$terminal['document']['ended_epoch']);
    if(!hash_equals($d['binding_sha256'],$r['binding']->sha256))throw new RuntimeException('Nameserver readback binding needs recovery.');
    foreach($request['binding']->data as $key=>$value)if(!str_starts_with($key,'source_')&&$r['binding']->data[$key]!==$value)throw new RuntimeException('Nameserver readback assignment needs recovery.');
    [, $original]=registered_nameservers_original($r['id']);
    if((int)$original['actor_id']!==(int)$row['actor_id']||(int)$original['started_epoch']<$terminal['document']['ended_epoch']||$d['floor']<$request['document']['anchor_seq'])throw new RuntimeException('Nameserver readback actor or order needs recovery.');
    if($r['state']==='complete'){if($d['nameservers']!==$r['nameservers']||$row['disposition']!==($r['nameservers']===$request['document']['params']['ns']?'observed':'different'))throw new RuntimeException('Nameserver readback set needs recovery.');}
    elseif($r['state']==='unfinished'||$d['nameservers']!==null||$row['disposition']!=='unavailable')throw new RuntimeException('Unavailable nameserver readback cannot confirm a set.');return $d;
}
/** Internal atomic save of this exact newly returned receipt, never a latest-row replacement. */
function registered_nameserver_changes_save_check(array $plan,array $receipt):array {
    registered_nameservers_guard();$request=registered_nameserver_changes_request($plan['request_id']);[$actor,,$current]=registered_nameserver_changes_context($request['binding']);$terminal=registered_nameserver_changes_terminal($request);
    if((int)$actor['id']!==$plan['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$plan['actor_auth'])||!$terminal||$terminal['row']['disposition']!=='attempted'||!hash_equals($plan['terminal_proof'],$terminal['row']['proof'])||!hash_equals($current->sha256,$plan['binding']->sha256))throw new RuntimeException('Nameserver verification authority changed.');
    registered_nameserver_changes_finished_ledger($request,$terminal['document']['ended_epoch']);
    if(array_keys($receipt)!==['id','state']||!is_string($receipt['id']))throw new LogicException('An exact nameserver refresh receipt is required.');$r=registered_nameservers_read_row($receipt['id']);$latest=registered_nameserver_changes_latest($request['row']['link_id']);[, $original]=registered_nameservers_original($r['id']);
    if(!$latest||$latest['id']!==$receipt['id']||$r['state']==='unfinished'||$r['state']!==$receipt['state']||$r['seq']<=$plan['floor']||(int)$original['actor_id']!==$plan['actor_id']||(int)$original['started_epoch']<max(time()-900,$terminal['document']['ended_epoch'])||(int)$original['started_epoch']>time()+30||$r['observed_epoch']<time()-900||$r['observed_epoch']>time()+30)throw new RuntimeException('The exact nameserver verification read is no longer current.');
    if(!hash_equals($current->sha256,$r['binding']->sha256))throw new RuntimeException('The nameserver verification context changed.');
    $names=$r['state']==='complete'?$r['nameservers']:null;$state=$names!==null?($names===$request['document']['params']['ns']?'observed':'different'):'unavailable';
    $d=['version'=>1,'request_proof'=>$request['row']['proof'],'terminal_proof'=>$terminal['row']['proof'],'floor'=>$plan['floor'],'receipt_id'=>$r['id'],'receipt_seq'=>$r['seq'],'receipt_sha256'=>registered_nameserver_changes_receipt_hash($r),'binding_sha256'=>$r['binding']->sha256,'disposition'=>$state,'nameservers'=>$names,'recorded_epoch'=>time()];$json=registered_nameservers_json($d);
    $row=['id'=>$plan['check_id'],'request_id'=>$request['row']['id'],'actor_id'=>(int)$actor['id'],'receipt_id'=>$r['id'],'disposition'=>$state,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];registered_nameserver_changes_insert('check',registered_nameserver_changes_prove('check',$row));return registered_nameserver_changes_summary($request['row']['id']);
}
/** Internal: request/grant/binding proof is validated by the owning caller first. */
function registered_nameserver_changes_finished_ledger(array $request,int $now):string {
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
        ||$row['idempotency_key']!=='nameservers:'.$request['row']['id']
        ||$row['idempotency_key']!==$request['grant']['consumed_key']
        ||$row['operation']!==RegistrarNameserverChange::OPERATION||!in_array($row['status'],['failed','uncertain'],true)
        ||$attempts!==1||$claimed<$request['document']['approved_epoch']||$resolved<$claimed||$resolved>$now+30
        ||$row['claimed_at']!==gmdate('Y-m-d H:i:s',$claimed)
        ||$row['external_id']!==null||$row['reconciled_outcome']!==null||$row['reconciled_epoch']!==null)$fail();
    if(!is_string($row['result_message'])||strlen($row['result_message'])>200||preg_match('//u',$row['result_message'])!==1||preg_match('/[\x00-\x1f\x7f]/',$row['result_message'])
        ||($row['provider_code']!==null&&(!is_string($row['provider_code'])||strlen($row['provider_code'])>64||preg_match('/[\x00-\x1f\x7f]/',$row['provider_code']))))$fail();
    $params=RegistrarNameserverChange::params($request['document']['params']);
    $operation=new ProviderOperation(RegistrarNameserverChange::OPERATION,$request['grant']['consumed_key'],$params);
    $b=$request['binding']->data;
    $fingerprint=ProviderGateway::requestFingerprint('twenty-i',$b['profile_id'].':'.$b['profile_revision'].':'.$b['key_fingerprint'],$operation);
    if(!is_string($row['fingerprint'])||!hash_equals($fingerprint,$row['fingerprint']))$fail();
    return registered_nameserver_changes_ledger_digest($row);
}

/** Explicit original-owner action; reconstruct only a missing local witness, never a provider result. */
function registered_nameserver_changes_recover_finished(string $requestId,string $reviewedLedgerHash,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('Nameserver dispatch recovery cannot inherit a transaction.');
    registered_nameservers_id($requestId);
    if(preg_match('/\A[a-f0-9]{64}\z/',$reviewedLedgerHash)!==1)throw new InvalidArgumentException('Reload the completed-dispatch recovery form.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$reviewedLedgerHash,$password):array {
        $actor=registered_nameserver_changes_actor($password);
        if(!registered_nameserver_changes_ready())throw new RuntimeException('Restore the original Nameserver change storage first.');
        $request=registered_nameserver_changes_request($requestId);
        registered_nameserver_changes_context($request['binding']);
        if((int)$actor['id']!==(int)$request['row']['actor_id']||!hash_equals($request['document']['actor_auth'],hash('sha256',$actor['auth_version']))||$request['grant']['revoked_at']!==null)
            throw new RuntimeException('The original Nameserver approval owner or context changed.');
        // Freeze the finished source row during reconstruction; missing and claimed rows are refused below.
        one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=? FOR UPDATE',[$request['grant']['consumed_key']]);
        $request=registered_nameserver_changes_request($requestId);
        $digest=registered_nameserver_changes_finished_ledger($request,time());
        if(!hash_equals($reviewedLedgerHash,$digest))throw new RuntimeException('The reviewed dispatch completion changed. Reload its history.');
        if(registered_nameserver_changes_terminal($request)!==null)throw new RuntimeException('This request already has a dispatch record. No recovery is needed.');
        $document=['version'=>1,'request_proof'=>$request['row']['proof'],'phase'=>'attempted','ended_epoch'=>time(),'ledger_sha256'=>$digest,'recovered_from_ledger'=>true];
        $json=registered_nameservers_json($document);
        $row=['id'=>$requestId,'actor_id'=>(int)$actor['id'],'disposition'=>'attempted','ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];
        registered_nameserver_changes_insert('terminal',registered_nameserver_changes_prove('terminal',$row));
        registered_nameserver_changes_ready();
        return registered_nameserver_changes_summary($requestId);
    }));
}

/** No write is retried. This invokes the existing quota-limited GET outside every DB transaction. */
function registered_nameserver_changes_verify(string $requestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Nameserver verification cannot run in a transaction.');registered_nameservers_id($requestId);
    $plan=staff_locked(fn()=>staff_transaction(function()use($requestId,$password):array{
        $actor=registered_nameserver_changes_actor($password);if(!registered_nameserver_changes_ready())throw new RuntimeException('Prepare nameserver change storage first.');$request=registered_nameserver_changes_request($requestId);[,,$binding]=registered_nameserver_changes_context($request['binding']);$terminal=registered_nameserver_changes_terminal($request);
        if(!$terminal)throw new RuntimeException('This interrupted request needs an operator recovery transition. Dispatch and readback remain blocked.');
        if($terminal['row']['disposition']!=='attempted')throw new RuntimeException('This request was refused before provider dispatch. Save a new observation before a separate approval.');
        registered_nameserver_changes_finished_ledger($request,$terminal['document']['ended_epoch']);
        if(one('SELECT id FROM '.table('provider_nameserver_change_checks')." WHERE request_id=? AND BINARY disposition='observed'",[$requestId]))throw new RuntimeException('The desired set was already observed after this request. Use the normal observation refresh for current information.');
        return ['request_id'=>$requestId,'check_id'=>bin2hex(random_bytes(16)),'actor_id'=>(int)$actor['id'],'actor_auth'=>hash('sha256',$actor['auth_version']),'binding'=>$binding,'terminal_proof'=>$terminal['row']['proof'],'floor'=>(int)query('SELECT COALESCE(MAX(seq),0) FROM '.table('provider_refreshes'))->fetchColumn()];
    }));
    $b=$plan['binding']->data;$receipt=registered_nameservers_refresh($b['link_id'],$b['link_revision'],$b['profile_revision'],$b['source_refresh_id'],$password,$resolver,$transport);
    return staff_locked(fn()=>staff_transaction(fn()=>registered_nameserver_changes_save_check($plan,$receipt)));
}
function registered_nameserver_changes_summary(string $requestId):array {
    registered_nameservers_guard();registered_nameserver_changes_actor();if(!registered_nameserver_changes_ready())throw new RuntimeException('Prepare nameserver change storage first.');$request=registered_nameserver_changes_request($requestId);$terminal=registered_nameserver_changes_terminal($request);$check=one('SELECT c.* FROM '.table('provider_nameserver_change_checks').' c JOIN '.table('provider_refreshes').' f ON f.id=c.receipt_id WHERE c.request_id=? ORDER BY f.seq DESC LIMIT 1',[$requestId]);$verified=$check?registered_nameserver_changes_check_document($check,$request):null;
    $state=$terminal===null?'interrupted':($terminal['row']['disposition']==='not_sent'?'not_sent':'uncertain');if($verified&&$verified['disposition']==='observed')$state='observed';
    $current=false;try{registered_nameserver_changes_context($request['binding']);$current=true;}catch(InvalidArgumentException|RuntimeException $e){if(!registered_nameserver_changes_unavailable($e))throw $e;}
    $canVerify=false;if($terminal!==null&&$terminal['row']['disposition']==='attempted'&&$current&&($verified['disposition']??null)!=='observed'){try{registered_nameserver_changes_finished_ledger($request,$terminal['document']['ended_epoch']);$canVerify=true;}catch(RuntimeException $e){if($e instanceof PDOException||$e->getMessage()!=='This request has no eligible durable dispatch completion.')throw $e;}}
    $recovery=null;
    if($terminal===null&&$current){$actor=registered_nameserver_changes_actor();if((int)$actor['id']===(int)$request['row']['actor_id']&&hash_equals($request['document']['actor_auth'],hash('sha256',$actor['auth_version']))&&$request['grant']['revoked_at']===null){try{$recovery=registered_nameserver_changes_finished_ledger($request,time());}catch(RuntimeException $e){if($e instanceof PDOException||$e->getMessage()!=='This request has no eligible durable dispatch completion.')throw $e;}}}
    return ['id'=>$requestId,'link_id'=>$request['row']['link_id'],'domain_id'=>(int)$request['row']['domain_id'],'domain'=>$request['binding']->data['domain'],'old_nameservers'=>$request['document']['params']['old-ns'],'desired_nameservers'=>$request['document']['params']['ns'],'requested_epoch'=>$request['document']['approved_epoch'],'status'=>$state,'current_assignment'=>$current,'can_verify'=>$canVerify,'recovery_ledger_sha256'=>$recovery,'recovered_dispatch'=>$terminal!==null&&($terminal['document']['recovered_from_ledger']??false)===true,'last_check'=>$verified?['disposition'=>$verified['disposition'],'nameservers'=>$verified['nameservers'],'recorded_epoch'=>$verified['recorded_epoch']]:null];
}


/** Fresh current owner, including settings changed while waiting for the mutex. */
function registered_nameserver_changes_actor(#[SensitiveParameter] ?string $password=null):array {
    registered_nameservers_guard(false);unset($GLOBALS['cwp_settings']);return $password===null?provider_settings_manager():provider_settings_owner($password);
}
/** Exact safe receipt digest; original reader validates complete source/event/audit history. */
function registered_nameserver_changes_receipt_hash(array $r):string {
    return hash('sha256',registered_nameservers_json(['id'=>$r['id'],'seq'=>$r['seq'],'binding_sha256'=>$r['binding']->sha256,'state'=>$r['state'],'nameservers'=>$r['nameservers'],'observed_epoch'=>$r['observed_epoch']]));
}
function registered_nameserver_changes_latest(string $linkId):?array {
    $r=one('SELECT b.id FROM '.table('registered_nameserver_bindings').' b JOIN '.table('provider_refreshes').' f ON f.id=b.id WHERE b.link_id=? ORDER BY f.seq DESC LIMIT 1',[$linkId]);return $r?registered_nameservers_read_row($r['id']):null;
}
/** Only known current-eligibility denials may hide controls. History/SQL failures propagate. */
function registered_nameserver_changes_unavailable(Throwable $e):bool {
    return !$e instanceof PDOException&&in_array($e->getMessage(),[
        'This registered-domain assignment changed. Reload it.',
        'Refresh registered domains and confirm this assignment first.',
        'The nameserver change assignment or account changed.',
        'This provider profile changed. Reload before checking.',
        'Enable the profile with a saved General API key before checking.',
        'Refresh and review the latest complete nameserver set before changing it.',
    ],true);
}
/** Safe current card projection, no network/credential/SQL mutations. */
function registered_nameserver_changes_form(string $linkId,int $revision):array {
    registered_nameservers_guard();registered_nameserver_changes_actor();registered_nameservers_id($linkId);
    $ready=registered_nameserver_changes_ready();$out=['ready'=>$ready,'eligible'=>false,'reason'=>$ready?'unavailable':'not_prepared','link_id'=>$linkId,'link_revision'=>$revision,'domain'=>null,'anchor_id'=>null,'binding_sha256'=>null,'old_nameservers'=>null,'observed_epoch'=>null,'pending'=>null];if(!$ready)return $out;
    $l=one('SELECT profile_id,domain_id,domain,revision FROM '.table('provider_registrar_links').' WHERE id=?',[$linkId]);if(!$l||(int)$l['revision']!==$revision)return $out;$out['domain']=$l['domain'];
    $pending=registered_nameserver_changes_pending($l['profile_id'],(int)$l['domain_id']);if($pending){$out['reason']='pending';$out['pending']=registered_nameserver_changes_summary($pending['id']);return $out;}
    try{$binding=registered_nameservers_binding($linkId,$revision);registered_nameserver_changes_context($binding);$r=registered_nameserver_changes_latest($linkId);if(!$r)return $out;[$binding,$r]=registered_nameserver_changes_precondition($linkId,$r['id'],$binding->sha256);}
    catch(InvalidArgumentException|RuntimeException $e){if(!registered_nameserver_changes_unavailable($e))throw $e;return $out;}
    $out['eligible']=true;$out['reason']='ready';$out['anchor_id']=$r['id'];$out['binding_sha256']=$binding->sha256;$out['old_nameservers']=$r['nameservers'];$out['observed_epoch']=$r['observed_epoch'];return $out;
}
