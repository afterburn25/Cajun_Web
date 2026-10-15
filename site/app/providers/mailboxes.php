<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/mailbox_policies.php';
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway,OperationResult,LedgerClaim};
use CajunWebPro\Foundation\TwentyI\Operations\MailboxCreate;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

/** Password proof is installation-keyed and request-bound. No recoverable mailbox password is stored. */
function provider_mailbox_password_proof(string $request,#[SensitiveParameter] string $password):string {
    return hash_hmac('sha256','cwp-mailbox-password-v1|'.$request.'|'.$password,provider_settings_vault_key());
}
function provider_mailbox_request_audit(array $row,array $d):void {
    $actor=$d['actor']['kind']==='owner'?$d['actor']['id']:0;
    provider_settings_audit($actor,'mailbox_started','Request '.$row['id'].' sha256 '.$row['sha256'].' actor '.$d['actor']['kind'].' '.$d['actor']['id']);
}
function provider_mailbox_request(array $row):array {
    $d=provider_mailbox_document($row);
    if(array_keys($d)!==['format','id','policy_id','policy_sha256','actor','client_id','profile_id','profile_revision','created_epoch','expires_epoch','params','password_proof','account_identity','ledger_fingerprint']
        ||$d['policy_id']!==$row['policy_id']||$d['client_id']!==(int)$row['client_id']||$d['profile_id']!==$row['profile_id']||$d['created_epoch']!==(int)$row['created_epoch']
        ||!is_array($d['actor'])||array_keys($d['actor'])!==['kind','id','auth_version']||!in_array($d['actor']['kind'],['owner','client'],true)||!is_int($d['actor']['id'])||$d['actor']['id']<1
        ||!is_string($d['actor']['auth_version'])||!preg_match('/^[a-f0-9]{32}$/D',$d['actor']['auth_version'])||!is_int($d['expires_epoch'])||$d['expires_epoch']<=$d['created_epoch']
        ||!is_array($d['params'])||!is_string($d['password_proof'])||!preg_match('/^[a-f0-9]{64}$/D',$d['password_proof'])||!is_string($d['account_identity']))throw new RuntimeException('Original mailbox request needs recovery.');
    $op=new ProviderOperation(MailboxCreate::OPERATION,'mailbox:'.$d['id'],$d['params']);MailboxCreate::parameters($op);
    if($d['params']['password_ref']!==$d['id']||$d['params']['domain']!==$row['domain_name']||$d['params']['local']!==$row['local_name']
        ||!is_string($d['ledger_fingerprint'])||!hash_equals($d['ledger_fingerprint'],ProviderGateway::requestFingerprint('twenty-i',$d['account_identity'],$op)))throw new RuntimeException('Original mailbox request scope changed.');
    $policyRow=one('SELECT * FROM '.table('provider_mailbox_policies').' WHERE id=?',[$d['policy_id']]);
    if(!$policyRow||$policyRow['sha256']!==$d['policy_sha256'])throw new RuntimeException('Original mailbox approval needs recovery.');
    $policy=provider_mailbox_document($policyRow);
    if($policy['binding']['client_id']!==$d['client_id']||$policy['binding']['profile_id']!==$d['profile_id']||$policy['binding']['profile_revision']!==$d['profile_revision']
        ||$policy['binding']['package_id']!==$d['params']['packageId']||$policy['binding']['domain']!==$d['params']['domain']
        ||$d['account_identity']!==$d['profile_id'].':'.$d['profile_revision'].':'.$policy['binding']['key_fingerprint'])throw new RuntimeException('Original mailbox resource binding changed.');
    $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$d['id']]);$nodes=0;$scope=provider_operations_canonical($d['params'],0,$nodes);
    if(!$grant||$grant['profile_id']!==$d['profile_id']||(int)$grant['profile_revision']!==$d['profile_revision']||$grant['operation']!==$op->name
        ||$grant['scope_params']!==$scope||$grant['scope_fingerprint']!==provider_operations_scope($op->name,$d['params'])||$grant['consumed_key']!==$op->idempotencyKey
        ||(int)$grant['granted_by']!==$policy['owner_id']||(int)$grant['expires_epoch']!==$d['expires_epoch']||$grant['consumed_at']!==gmdate('Y-m-d H:i:s',$d['created_epoch']))throw new RuntimeException('Original mailbox operation grant needs recovery.');
    $audit=all('SELECT user_id FROM '.table('audit').' WHERE action=? AND detail=? LIMIT 2',['provider.mailbox_started','Request '.$row['id'].' sha256 '.$row['sha256'].' actor '.$d['actor']['kind'].' '.$d['actor']['id']]);
    if(count($audit)!==1||(int)$audit[0]['user_id']!==($d['actor']['kind']==='owner'?$d['actor']['id']:0))throw new RuntimeException('Original mailbox request audit is missing.');
    return $d;
}
function provider_mailbox_ledger(array $d):?array {
    if(!provider_operations_ready())throw new RuntimeException('Restore the original provider operation ledger.');
    $r=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',['mailbox:'.$d['id']]);
    if(!$r)return null;
    if($r['fingerprint']!==$d['ledger_fingerprint']||($r['status']==='claimed'?$r['operation']!==null:$r['operation']!==MailboxCreate::OPERATION))throw new RuntimeException('Original mailbox result does not match its request.');
    return $r;
}
/** Only a newly committed request gets dispatch authority. Every old/unfinished request is inert. */
function provider_mailbox_claim(string $policyId,string $request,string $local,#[SensitiveParameter] string $password,?int $clientId,#[SensitiveParameter] ?string $ownerPassword):?array {
    provider_mailbox_id($request);MailboxCreate::local($local);MailboxCreate::password($password);
    return staff_locked(fn()=>staff_transaction(function()use($policyId,$request,$local,$password,$clientId,$ownerPassword):?array {
        [$actor,$policy,$resource]=provider_mailbox_authority($policyId,$clientId,$ownerPassword);
        $proof=provider_mailbox_password_proof($request,$password);
        $old=one('SELECT * FROM '.table('provider_mailbox_requests').' WHERE id=?',[$request]);
        if($old){$d=provider_mailbox_request($old);if($d['policy_id']!==$policyId||$d['actor']!==$actor||$d['params']['local']!==$local||!hash_equals($d['password_proof'],$proof))throw new RuntimeException('This mailbox request was already used for different details. Reload the form.');return null;}
        $binding=$policy['binding'];$at=time();
        if((int)query('SELECT COUNT(*) FROM '.table('provider_mailbox_requests').' WHERE profile_id=? AND domain_name=? AND created_epoch>?',[$binding['profile_id'],$binding['domain'],$at-86400])->fetchColumn()>=$policy['daily_limit'])throw new RuntimeException('The approved daily mailbox request allowance has been reached.');
        foreach(all('SELECT * FROM '.table('provider_mailbox_requests').' WHERE profile_id=? AND domain_name=? AND local_name=?',[$binding['profile_id'],$binding['domain'],$local]) as $prior){
            $priorDoc=provider_mailbox_request($prior);$result=provider_mailbox_ledger($priorDoc);
            if(!$result||$result['status']!=='failed')throw new RuntimeException('A mailbox request for this address already exists. Review its result before creating another.');
        }
        $params=['packageId'=>$binding['package_id'],'domain'=>$binding['domain'],'local'=>$local,'password_ref'=>$request];
        $op=new ProviderOperation(MailboxCreate::OPERATION,'mailbox:'.$request,$params);MailboxCreate::parameters($op);
        $account=$binding['profile_id'].':'.$binding['profile_revision'].':'.$binding['key_fingerprint'];
        $policyRow=one('SELECT sha256 FROM '.table('provider_mailbox_policies').' WHERE id=?',[$policyId]);
        $d=['format'=>1,'id'=>$request,'policy_id'=>$policyId,'policy_sha256'=>$policyRow['sha256'],'actor'=>$actor,'client_id'=>$binding['client_id'],'profile_id'=>$binding['profile_id'],'profile_revision'=>$binding['profile_revision'],'created_epoch'=>$at,'expires_epoch'=>min($policy['expires_epoch'],$at+900),'params'=>$params,'password_proof'=>$proof,'account_identity'=>$account,'ledger_fingerprint'=>ProviderGateway::requestFingerprint('twenty-i',$account,$op)];
        $nodes=0;$scope=provider_operations_canonical($params,0,$nodes);$now=gmdate('Y-m-d H:i:s',$at);
        query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$request,$binding['profile_id'],$op->name,provider_operations_scope($op->name,$params),$binding['profile_revision'],$scope,$policy['owner_id'],$now,$d['expires_epoch'],$op->idempotencyKey,$now]);
        $doc=provider_mailbox_json($d);$row=['id'=>$request,'policy_id'=>$policyId,'profile_id'=>$binding['profile_id'],'client_id'=>$binding['client_id'],'created_epoch'=>$at,'domain_name'=>$binding['domain'],'local_name'=>$local,'document'=>$doc,'sha256'=>hash('sha256',$doc)];
        query('INSERT INTO '.table('provider_mailbox_requests').' (id,policy_id,profile_id,client_id,created_epoch,domain_name,local_name,document,sha256) VALUES (?,?,?,?,?,?,?,?,?)',array_values($row));
        provider_mailbox_request_audit($row,$d);return $d;
    }));
}
function provider_mailbox_reauthorize(array $d):array {
    [$actor,$policy,$resource]=provider_mailbox_authority($d['policy_id'],$d['actor']['kind']==='client'?$d['actor']['id']:null);
    if($actor!==$d['actor']||time()>=$d['expires_epoch'])throw new RuntimeException('Mailbox access changed or the request expired.');
    $row=one('SELECT * FROM '.table('provider_mailbox_requests').' WHERE id=?',[$d['id']]);
    if(!$row||provider_mailbox_request($row)!==$d)throw new RuntimeException('Original mailbox request changed.');
    $grant=one('SELECT revoked_at FROM '.table('provider_operation_grants').' WHERE id=?',[$d['id']]);
    if(!$grant||$grant['revoked_at']!==null)throw new RuntimeException('Mailbox request permission was revoked.');
    return $resource['profile'];
}
/** Save only safe outcomes through the existing ledger, including known pre-send failures. */
function provider_mailbox_finish(array $d,OperationResult $result):void {
    $ledger=new ProviderOperationLedger();$key='mailbox:'.$d['id'];$old=provider_mailbox_ledger($d);
    if(!$old){$claim=$ledger->claim($key,$d['ledger_fingerprint'],time());if($claim->state!==LedgerClaim::CLAIMED)throw new RuntimeException('Original mailbox operation changed.');$old=['status'=>'claimed'];}
    if($old['status']==='claimed')$ledger->record($key,$d['ledger_fingerprint'],$result,time());
    // Existing terminal results are immutable. No raw response or password enters this event.
    $stored=provider_mailbox_ledger($d);
    provider_settings_audit($d['actor']['kind']==='owner'?$d['actor']['id']:0,'mailbox_finished','Request '.$d['id'].' outcome '.($stored['status']??'uncertain'));
}
function provider_mailbox_run(string $policyId,string $request,string $local,#[SensitiveParameter] string $password,?int $clientId=null,#[SensitiveParameter] ?string $ownerPassword=null,?callable $resolver=null,?callable $perform=null):string {
    if(db()->inTransaction())throw new LogicException('Mailbox requests cannot run inside a transaction.');
    $d=provider_mailbox_claim($policyId,$request,$local,$password,$clientId,$ownerPassword);
    if($d===null)return $request;
    $address='';$dnsFailed=false;
    try{$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];}catch(Throwable $e){if($e instanceof PDOException)throw $e;$dnsFailed=true;}
    return staff_locked(function()use($d,$password,$address,$dnsFailed,$perform):string {
        $op=new ProviderOperation(MailboxCreate::OPERATION,'mailbox:'.$d['id'],$d['params']);$dispatched=false;$secret='';
        try{
            $profile=provider_mailbox_reauthorize($d);
            if($dnsFailed){provider_mailbox_finish($d,OperationResult::failed($op->name,'Provider endpoint unavailable before send.','unreachable')->withAttempts(0));return $d['id'];}
            $secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            if(!hash_equals($d['account_identity'],$profile['id'].':'.(int)$profile['revision'].':'.provider_operations_credential_fingerprint($secret)))throw new RuntimeException('Observed provider credential context changed.');
            $authorize=static function()use($d):void{provider_mailbox_reauthorize($d);};
            $resolvePassword=static function(string $ref,ProviderOperation $operation)use($d,$password,$authorize):string {
                $authorize();if($ref!==$d['id']||$operation->params!==$d['params']||!hash_equals($d['password_proof'],provider_mailbox_password_proof($d['id'],$password)))throw new RuntimeException('Original mailbox secret context changed.');return $password;
            };
            $dispatched=true;
            $result=($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$op,$resolvePassword);
            if(!$result instanceof OperationResult||$result->operation!==$op->name)throw new RuntimeException('Unrecognized mailbox result.');
            $authorize();
        }catch(Throwable $e){
            if($e instanceof PDOException)throw $e;
            $result=$dispatched?OperationResult::uncertain($op->name,'access_changed'):OperationResult::failed($op->name,'The request was refused before dispatch.','access_changed')->withAttempts(0);
        }finally{unset($secret,$resolvePassword);}
        provider_mailbox_finish($d,$result);return $d['id'];
    });
}
/** Safe projection, always filtered to the authenticated client when used in the portal. */
function provider_mailbox_history(?int $clientId=null):array {
    return staff_locked(function()use($clientId):array {
        provider_mailbox_actor($clientId);if(!provider_mailbox_ready())return [];
        $rows=all('SELECT * FROM '.table('provider_mailbox_requests').($clientId===null?'':' WHERE client_id=?').' ORDER BY created_epoch DESC,id DESC LIMIT 21',$clientId===null?[]:[$clientId]);
        $out=[];foreach($rows as $row){$d=provider_mailbox_request($row);$r=provider_mailbox_ledger($d);$status=$r['status']??'uncertain';
            $message=match($status){'succeeded'=>'The provider confirmed mailbox creation.','failed'=>'The request was not completed. Contact the studio if you need help.',default=>'The outcome is not confirmed. Contact the studio before trying this address again.'};
            $out[]=['id'=>$d['id'],'address'=>$row['local_name'].'@'.$row['domain_name'],'at'=>gmdate('Y-m-d H:i:s',(int)$row['created_epoch']),'status'=>$status,'message'=>$message];
        }return $out;
    });
}
