<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/password_reset_template_storage.php';
foreach(['PasswordResetTemplate','PasswordResetTemplateObservation','PasswordResetTemplateReader'] as $class)require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-provider-templates/'.$class.'.php';
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-operations/PasswordResetTemplateChange.php';

use CajunWebPro\Foundation\TwentyIProviderTemplates\{PasswordResetTemplate,PasswordResetTemplateObservation,PasswordResetTemplateReader};
use CajunWebPro\Foundation\TwentyI\Operations\{PasswordResetTemplateChange,TwentyIOperationAdapter};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\Provider\{ProviderCredential,ProviderGateway,ProviderOperation,OperationResult};
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

/** App bounds, not provider promises. Every provider request is an explicit password action. */
const PROVIDER_RESET_TEMPLATE_FRESH_SECONDS=900;
const PROVIDER_RESET_TEMPLATE_ZERO='00000000000000000000000000000000';

function provider_password_reset_template_actor(#[SensitiveParameter] ?string $password=null,bool $ready=true):array {
    foreach(['client_id','client_auth','contact_id','contact_auth','support','support_id','support_session'] as $key)if(array_key_exists($key,$_SESSION??[]))throw new RuntimeException('Leave client access before editing the reset email template.');
    if(array_key_exists('support',$_GET)||support_current())throw new RuntimeException('Leave client support before editing the reset email template.');
    return $password===null?provider_settings_manager($ready):provider_settings_owner($password,$ready);
}
function provider_password_reset_template_context(string $profile,int $revision,#[SensitiveParameter] ?string $password=null):array {
    $actor=provider_password_reset_template_actor($password);[, $row]=provider_checks_authority($profile,$revision);
    return [['profile_id'=>$profile,'revision'=>$revision,'credential_sha256'=>hash('sha256',$row['credentials']),'owner_id'=>(int)$actor['id'],'owner_auth_sha256'=>hash('sha256',$actor['auth_version'])],$row];
}
function provider_password_reset_template_authority(array $context,#[SensitiveParameter] ?string $password=null):array {
    [$now,$row]=provider_password_reset_template_context($context['profile_id'],$context['revision'],$password);
    if($now!==$context)throw new ProviderOperationAccessChanged('The original template approval owner or provider account changed.');return $row;
}
function provider_password_reset_template_keys(array $value,array $keys):bool {return array_keys($value)===$keys;}
function provider_password_reset_template_context_valid(array $c):bool {
    return provider_password_reset_template_keys($c,['profile_id','revision','credential_sha256','owner_id','owner_auth_sha256'])
        &&is_string($c['profile_id'])&&preg_match('/\A[a-f0-9]{32}\z/',$c['profile_id'])===1&&is_int($c['revision'])&&$c['revision']>0&&$c['revision']<=2147483647
        &&is_int($c['owner_id'])&&$c['owner_id']>0&&is_string($c['credential_sha256'])&&preg_match('/\A[a-f0-9]{64}\z/',$c['credential_sha256'])===1
        &&is_string($c['owner_auth_sha256'])&&preg_match('/\A[a-f0-9]{64}\z/',$c['owner_auth_sha256'])===1;
}
function provider_password_reset_template_grant_digest(array $g):string {
    $v=[];foreach(['id','seq','profile_id','operation','scope_fingerprint','scope_params','granted_by','granted_at','expires_epoch','profile_revision','consumed_key','consumed_at'] as $k)$v[$k]=(string)($g[$k]??'');
    return hash('sha256',provider_password_reset_template_json($v));
}
function provider_password_reset_template_ledger_digest(?array $ledger):string {
    if($ledger===null)return str_repeat('0',64);$v=[];
    foreach(['id','idempotency_key','fingerprint','operation','status','result_message','provider_code','external_id','attempts','claimed_at','claimed_epoch','resolved_epoch','reconciled_outcome','reconciled_epoch'] as $key){if(!array_key_exists($key,$ledger))throw new RuntimeException('Template execution history needs recovery.');$v[$key]=$ledger[$key]===null?null:(string)$ledger[$key];}
    return hash('sha256',provider_password_reset_template_json($v));
}
function provider_password_reset_template_operation(array $request):ProviderOperation {
    return new ProviderOperation(PROVIDER_RESET_TEMPLATE_OPERATION,'reset-template:'.$request['document']['id'],['resellerId'=>'*','request_id'=>$request['document']['id'],'content_sha256'=>$request['document']['data']['content_sha256']]);
}
function provider_password_reset_template_scope(array $data):array {
    $scope=$data;unset($scope['grant_sha256']);return $scope;
}
function provider_password_reset_template_finished(array $request):string {
    $l=$request['ledger'];$d=$request['document'];
    if(!$l||$l['operation']!==PROVIDER_RESET_TEMPLATE_OPERATION||$l['idempotency_key']!=='reset-template:'.$d['id']||!is_string($l['id'])||preg_match('/\A[a-f0-9]{32}\z/',$l['id'])!==1
        ||!is_string($l['result_message'])||strlen($l['result_message'])>200||preg_match('//u',$l['result_message'])!==1||preg_match('/[\x00-\x1f\x7f]/',$l['result_message'])
        ||($l['provider_code']!==null&&(!is_string($l['provider_code'])||strlen($l['provider_code'])>64||preg_match('/[\x00-\x1f\x7f]/',$l['provider_code'])))
        ||!in_array($l['status'],['accepted_pending','uncertain','failed'],true)||(string)$l['attempts']!=='1'||$l['resolved_epoch']===null
        ||!ctype_digit((string)$l['claimed_epoch'])||!ctype_digit((string)$l['resolved_epoch'])||(int)$l['claimed_epoch']<$d['at']||(int)$l['resolved_epoch']<(int)$l['claimed_epoch']||(int)$l['resolved_epoch']>time()+30
        ||$l['claimed_at']!==gmdate('Y-m-d H:i:s',(int)$l['claimed_epoch'])||$l['external_id']!==null||$l['reconciled_outcome']!==null||$l['reconciled_epoch']!==null)throw new RuntimeException('This template change has no eligible durable dispatch completion.');
    return provider_password_reset_template_ledger_digest($l);
}
/** Authenticate the whole bounded graph before selecting by resource or releasing an unresolved fence. */
function provider_password_reset_template_graph():?array {
    $events=provider_password_reset_template_events();if($events===null)return null;
    $starts=[];$reads=[];$requests=[];$last=[];$latest=[];$seenGrant=[];
    $fail=static function():never{throw new RuntimeException('Password-reset template history needs recovery.');};
    foreach($events as $id=>$e){
        $d=$e['document'];$v=$d['data'];$rid=$d['request_id'];
        if($d['kind']==='read_start'){
            if(!provider_password_reset_template_keys($v,['context'])||!is_array($v['context'])||!provider_password_reset_template_context_valid($v['context'])||$v['context']['profile_id']!==$d['profile_id']||$v['context']['owner_id']!==$d['actor_id']||($rid!==PROVIDER_RESET_TEMPLATE_ZERO&&!isset($requests[$rid]))||$e['row']['ciphertext_bytes']!==0)$fail();
            if($rid!==PROVIDER_RESET_TEMPLATE_ZERO&&$requests[$rid]['document']['data']['context']!==$v['context'])$fail();
            $starts[$id]=$e;$latest[$d['profile_id']]=['start'=>$e,'end'=>null];continue;
        }
        if($d['kind']==='read_end'){
            if(!provider_password_reset_template_keys($v,['start_id','state','reason','observed_epoch','content_sha256','content_bytes'])||!is_string($v['start_id'])||!isset($starts[$v['start_id']])||isset($reads[$v['start_id']])||!in_array($v['state'],['complete','unavailable'],true)||!is_string($v['reason'])||preg_match('/\A[a-z_]{0,40}\z/',$v['reason'])!==1||!is_int($v['observed_epoch'])||$v['observed_epoch']<1||!is_int($v['content_bytes'])||$v['content_bytes']<0||$v['content_bytes']>65536)$fail();
            $start=$starts[$v['start_id']]['document'];
            if($start['profile_id']!==$d['profile_id']||$start['request_id']!==$rid||$start['actor_id']!==$d['actor_id']||$v['observed_epoch']<$start['at']||$v['observed_epoch']>$d['at']+30)$fail();
            if($v['state']==='complete'){
                if($v['reason']!==''||!is_string($v['content_sha256'])||preg_match('/\A[a-f0-9]{64}\z/',$v['content_sha256'])!==1||$e['row']['ciphertext_bytes']===0)$fail();
            }elseif($v['content_sha256']!==null||$v['content_bytes']!==0||$e['row']['ciphertext_bytes']!==0||$v['reason']==='')$fail();
            $reads[$v['start_id']]=$e;if(($latest[$d['profile_id']]['start']['document']['id']??null)===$v['start_id'])$latest[$d['profile_id']]['end']=$e;continue;
        }
        if($d['kind']==='request'){
            if($rid!==$id||!provider_password_reset_template_keys($v,['context','anchor_id','anchor_sha256','content_sha256','content_bytes','review_sha256','previous_request','grant_sha256'])||!is_array($v['context'])||!provider_password_reset_template_context_valid($v['context'])||$v['context']['profile_id']!==$d['profile_id']||$v['context']['owner_id']!==$d['actor_id']||!is_string($v['anchor_id'])||!isset($events[$v['anchor_id']])||$events[$v['anchor_id']]['document']['kind']!=='read_end'||!is_int($v['content_bytes'])||$v['content_bytes']<1||$v['content_bytes']>65536||$e['row']['ciphertext_bytes']===0)$fail();
            foreach(['anchor_sha256','content_sha256','review_sha256','grant_sha256'] as $key)if(!is_string($v[$key])||preg_match('/\A[a-f0-9]{64}\z/',$v[$key])!==1)$fail();
            $anchor=$events[$v['anchor_id']];$a=$anchor['document'];$start=$starts[$a['data']['start_id']]??null;
            if(!$start||$a['seq']>=$d['seq']||$a['data']['state']!=='complete'||$start['document']['data']['context']!==$v['context']||$a['profile_id']!==$d['profile_id']||$anchor['row']['document_sha256']!==$v['anchor_sha256']||$v['previous_request']!==($last[$d['profile_id']]??null)||$a['data']['content_sha256']===$v['content_sha256'])$fail();
            $review=['request_id'=>$id,'context'=>$v['context'],'anchor_id'=>$v['anchor_id'],'anchor_sha256'=>$v['anchor_sha256'],'content_sha256'=>$v['content_sha256'],'content_bytes'=>$v['content_bytes'],'previous_request'=>$v['previous_request']];
            if(!hash_equals(provider_password_reset_template_hmac('review',provider_password_reset_template_json($review)),$v['review_sha256'])||$a['data']['observed_epoch']<$d['at']-PROVIDER_RESET_TEMPLATE_FRESH_SECONDS||$a['data']['observed_epoch']>$d['at']+30)$fail();
            $g=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);$scope=provider_password_reset_template_scope($v);$scopeJson=provider_password_reset_template_json($scope);
            if(!$g||$g['operation']!==PROVIDER_RESET_TEMPLATE_OPERATION||$g['profile_id']!==$d['profile_id']||(int)$g['profile_revision']!==$v['context']['revision']||(int)$g['granted_by']!==$d['actor_id']||$g['granted_at']!==gmdate('Y-m-d H:i:s',$d['at'])||$g['consumed_at']!==$g['granted_at']||(int)$g['expires_epoch']!==$d['at']+900||$g['consumed_key']!=='reset-template:'.$id||$g['scope_params']!==$scopeJson||!hash_equals(provider_operations_scope(PROVIDER_RESET_TEMPLATE_OPERATION,$scope),$g['scope_fingerprint'])||!hash_equals(provider_password_reset_template_grant_digest($g),$v['grant_sha256']))$fail();
            $l=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$g['consumed_key']]);$r=$e+['grant'=>$g,'ledger'=>$l,'terminal'=>null,'checks'=>[],'dismissal'=>null];
            if($l){$c=$v['context'];$fingerprint=ProviderGateway::requestFingerprint('twenty-i',$c['profile_id'].':'.$c['revision'].':'.$c['credential_sha256'],provider_password_reset_template_operation($r));if(!hash_equals($fingerprint,$l['fingerprint'])||($l['operation']!==null&&$l['operation']!==PROVIDER_RESET_TEMPLATE_OPERATION))$fail();}
            $requests[$id]=$r;$last[$d['profile_id']]=$id;$seenGrant[$id]=true;continue;
        }
        if(!isset($requests[$rid])||$requests[$rid]['document']['profile_id']!==$d['profile_id']||$requests[$rid]['document']['actor_id']!==$d['actor_id']||$e['row']['ciphertext_bytes']!==0)$fail();
        $r=&$requests[$rid];
        if($d['kind']==='terminal'){
            if($r['terminal']!==null||!provider_password_reset_template_keys($v,['phase','request_sha256','ledger_sha256'])||!in_array($v['phase'],['not_sent','attempted'],true)||$v['request_sha256']!==$r['row']['document_sha256']||$v['ledger_sha256']!==provider_password_reset_template_ledger_digest($r['ledger'])||($v['phase']==='not_sent'&&$r['ledger']!==null))$fail();
            $r['terminal']=$e;
        }elseif($d['kind']==='dismissal'){
            if($r['dismissal']!==null||!provider_password_reset_template_keys($v,['request_sha256','disposition'])||$v['disposition']!=='dismissed'||$v['request_sha256']!==$r['row']['document_sha256'])fail();
            $r['dismissal']=$e;
        }else{
            if(!provider_password_reset_template_keys($v,['receipt_id','disposition','request_sha256','ledger_sha256'])||!is_string($v['receipt_id'])||!isset($events[$v['receipt_id']])||$events[$v['receipt_id']]['document']['kind']!=='read_end'||!in_array($v['disposition'],['observed','different','unavailable'],true)||$v['request_sha256']!==$r['row']['document_sha256']||!$r['terminal']||$r['terminal']['document']['data']['phase']!=='attempted'||$v['ledger_sha256']!==provider_password_reset_template_finished($r))$fail();
            $receipt=$events[$v['receipt_id']];$rd=$receipt['document'];$start=$starts[$rd['data']['start_id']]??null;
            if(!$start||$rd['seq']>=$d['seq']||$rd['request_id']!==$rid||$start['document']['seq']<=$r['terminal']['document']['seq']||$start['document']['data']['context']!==$r['document']['data']['context'])$fail();
            $actual=$rd['data']['state']!=='complete'?'unavailable':($rd['data']['content_sha256']===$r['document']['data']['content_sha256']?'observed':'different');if($actual!==$v['disposition'])$fail();
            foreach($r['checks'] as $old)if($old['document']['data']['receipt_id']===$v['receipt_id'])$fail();$r['checks'][]=$e;
        }unset($r);
    }
    foreach(all('SELECT id FROM '.table('provider_operation_grants').' WHERE BINARY operation=?',[PROVIDER_RESET_TEMPLATE_OPERATION]) as $g)if(!isset($seenGrant[$g['id']]))$fail();
    foreach(all('SELECT idempotency_key FROM '.table('provider_operations').' WHERE BINARY operation=? OR LEFT(idempotency_key,15)=?',[PROVIDER_RESET_TEMPLATE_OPERATION,'reset-template:']) as $l)if(!str_starts_with($l['idempotency_key'],'reset-template:')||strlen($l['idempotency_key'])!==47||!isset($seenGrant[substr($l['idempotency_key'],15)]))$fail();
    return ['events'=>$events,'starts'=>$starts,'reads'=>$reads,'requests'=>$requests,'last'=>$last,'latest'=>$latest];
}
function provider_password_reset_template_prepare(#[SensitiveParameter] string $password):void {
    if(db()->inTransaction())throw new LogicException('Template preparation cannot inherit a transaction.');
    staff_locked(function()use($password):void{
        $actor=provider_password_reset_template_actor($password);if(!provider_operations_ready())throw new RuntimeException('Prepare provider operation storage first.');
        if(provider_password_reset_template_graph()!==null)return;
        provider_settings_vault_key();
        if(!provider_password_reset_template_exists())db()->exec(provider_password_reset_template_schema(config()['db']['prefix'])[PROVIDER_RESET_TEMPLATE_TABLE]);
        provider_password_reset_template_schema_validate();
        staff_transaction(function()use($actor):void{
            $p=provider_password_reset_template_json(['version'=>1,'actor_id'=>(int)$actor['id'],'at'=>time()]);$proof=provider_password_reset_template_hmac('prepared',$p);$head=str_repeat('0',64);
            foreach(['version'=>'1','count'=>'0','head'=>$head,'preparation'=>$p,'preparation_proof'=>$proof,'checkpoint'=>provider_password_reset_template_hmac('checkpoint','0|'.$head.'|'.$proof)] as $k=>$v)save_setting(PROVIDER_RESET_TEMPLATE_SETTING.$k,$v);
            provider_settings_audit((int)$actor['id'],'reset_template.prepared','Password-reset template storage '.$proof);
        });
    });
}
function provider_password_reset_template_pending(array $graph,string $profile):?array {
    foreach(array_reverse($graph['requests'],true) as $r){
        if($r['document']['profile_id']!==$profile)continue;
        if($r['terminal']&&$r['terminal']['document']['data']['phase']==='not_sent')continue;
        if($r['dismissal'])continue;
        $observed=false;foreach($r['checks'] as $c)if($c['document']['data']['disposition']==='observed')$observed=true;
        if(!$observed)return $r;
    }return null;
}
function provider_password_reset_template_precondition(array $graph,string $profile,int $revision,string $observation):array {
    [$context,$row]=provider_password_reset_template_context($profile,$revision);$latest=$graph['latest'][$profile]??null;$end=$latest['end']??null;
    if(!$end||$end['document']['id']!==$observation||$end['document']['data']['state']!=='complete'||$latest['start']['document']['data']['context']!==$context||$end['document']['data']['observed_epoch']<time()-PROVIDER_RESET_TEMPLATE_FRESH_SECONDS||$end['document']['data']['observed_epoch']>time()+30)throw new RuntimeException('Read and review the latest template for this provider account first.');
    if(provider_password_reset_template_pending($graph,$profile))throw new RuntimeException('A previous template change remains unresolved. Check its saved result before another change.');
    $previous=$graph['last'][$profile]??null;if($previous&&$end['document']['seq']<=$graph['requests'][$previous]['document']['seq'])throw new RuntimeException('Read the template again before reviewing another change.');
    return [$context,$row,$end,$previous];
}
function provider_password_reset_template_review_data(string $id,array $context,array $anchor,?string $previous,PasswordResetTemplate $template):array {
    return ['request_id'=>$id,'context'=>$context,'anchor_id'=>$anchor['document']['id'],'anchor_sha256'=>$anchor['row']['document_sha256'],'content_sha256'=>$template->sha256,'content_bytes'=>$template->bytes,'previous_request'=>$previous];
}
function provider_password_reset_template_review(string $requestId,string $profileId,int $revision,string $observationId,#[SensitiveParameter] string $content):array {
    provider_password_reset_template_id($requestId);provider_password_reset_template_id($observationId);
    return staff_locked(function()use($requestId,$profileId,$revision,$observationId,$content):array{
        provider_password_reset_template_actor();$g=provider_password_reset_template_graph();if($g===null)throw new RuntimeException('Prepare template storage first.');
        if(isset($g['events'][$requestId]))throw new RuntimeException('This request identifier was already used. Open its saved history.');
        [$ctx,,$anchor,$previous]=provider_password_reset_template_precondition($g,$profileId,$revision,$observationId);$template=PasswordResetTemplate::fromString($content);
        if($template->bytes===0)throw new InvalidArgumentException('Enter the complete replacement template. An empty value cannot reset the provider default here.');
        $before=provider_password_reset_template_open($anchor);if(hash_equals($before,$content))throw new InvalidArgumentException('The replacement matches the latest saved template.');
        $proof=provider_password_reset_template_hmac('review',provider_password_reset_template_json(provider_password_reset_template_review_data($requestId,$ctx,$anchor,$previous,$template)));
        return ['request_id'=>$requestId,'profile_id'=>$profileId,'revision'=>$revision,'observation_id'=>$observationId,'review_sha256'=>$proof,'before_content'=>$before,'content'=>$content];
    });
}
function provider_password_reset_template_read_transport(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize,string $operation):PasswordResetTemplateObservation {
    if($operation!==PasswordResetTemplateReader::OPERATION)throw new LogicException('Unsupported template read.');
    $credential=new ProviderCredential('twenty-i',$row['id'],static function()use($secret,$authorize):string{$authorize();return $secret;});
    return PasswordResetTemplateReader::read(new FixedHttpTransport($address,$authorize),$credential,$row['id'],time());
}
/** Existing caller holds the mutex, but never a SQL transaction across DNS or HTTP. */
function provider_password_reset_template_read_locked(array $context,string $requestId,?callable $resolver,?callable $transport):array {
    provider_password_reset_template_guard();if(db()->inTransaction())throw new LogicException('Template reads cannot run in a transaction.');
    $g=provider_password_reset_template_graph();if($g===null)throw new RuntimeException('Prepare template storage first.');provider_password_reset_template_authority($context);
    if(count($g['events'])>PROVIDER_RESET_TEMPLATE_LIMIT-3)throw new RuntimeException('Template history is full.');$now=time();$count=0;
    foreach($g['starts'] as $s){$d=$s['document'];if($d['profile_id']===$context['profile_id']&&$d['at']>$now-30)throw new RuntimeException('Wait 30 seconds between template reads for this profile.');if($d['actor_id']===$context['owner_id']&&$d['at']>$now-3600)++$count;}
    if($count>=20)throw new RuntimeException('Template read limit reached. Try again later.');
    $start=staff_transaction(fn()=>provider_password_reset_template_append('read_start',bin2hex(random_bytes(16)),$context['profile_id'],$requestId,$context['owner_id'],['context'=>$context]));
    $state='unavailable';$reason='unknown';$content=null;$at=time();
    try{
        $authorize=static fn()=>provider_password_reset_template_authority($context);$authorize();$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];$row=$authorize();
        $secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));
        try{$authorize();$result=($transport??'provider_password_reset_template_read_transport')($row,$secret,$address,$authorize,PasswordResetTemplateReader::OPERATION);$authorize();}finally{unset($secret);}
        if(!$result instanceof PasswordResetTemplateObservation)throw new RuntimeException('Unexpected template read result.');$result->validated();
        if($result->profileId!==$context['profile_id']||$result->observedAt<$start['document']['at']||$result->observedAt>time()+30)throw new RuntimeException('Unexpected template read identity.');
        $at=$result->observedAt;$state=$result->state;$reason=$result->reason;if($state==='complete')$content=$result->template()?->text();
        if($state==='complete'&&!is_string($content))throw new RuntimeException('Unexpected template read result.');
    }catch(Throwable $e){if($e instanceof PDOException)throw $e;$state='unavailable';$reason=$e instanceof ProviderOperationAccessChanged?'access_changed':'unavailable';$content=null;$at=time();}
    return staff_transaction(function()use($context,$requestId,$start,$state,$reason,$content,$at):array{
        try{provider_password_reset_template_authority($context);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$state='unavailable';$reason='access_changed';$content=null;}
        $g=provider_password_reset_template_graph();if(!$g||!isset($g['starts'][$start['document']['id']])||isset($g['reads'][$start['document']['id']]))throw new RuntimeException('Template read history changed.');
        return provider_password_reset_template_append('read_end',bin2hex(random_bytes(16)),$context['profile_id'],$requestId,$context['owner_id'],['start_id'=>$start['document']['id'],'state'=>$state,'reason'=>$state==='complete'?'':$reason,'observed_epoch'=>$at,'content_sha256'=>$content===null?null:hash('sha256',$content),'content_bytes'=>$content===null?0:strlen($content)],$content);
    });
}
function provider_password_reset_template_refresh(string $profileId,int $revision,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Template reads cannot inherit a transaction.');
    return staff_locked(function()use($profileId,$revision,$password,$resolver,$transport):array{[$c]=provider_password_reset_template_context($profileId,$revision,$password);$r=provider_password_reset_template_read_locked($c,PROVIDER_RESET_TEMPLATE_ZERO,$resolver,$transport);return ['id'=>$r['document']['id'],'state'=>$r['document']['data']['state'],'reason'=>$r['document']['data']['reason']];});
}
function provider_password_reset_template_request(string $id):array {
    provider_password_reset_template_id($id);$g=provider_password_reset_template_graph();
    if(!$g||!isset($g['requests'][$id]))throw new RuntimeException('This template change is unavailable.');return $g['requests'][$id];
}
function provider_password_reset_template_authorize_request(string $id,bool $send=false):array {
    provider_password_reset_template_guard();$r=provider_password_reset_template_request($id);$c=$r['document']['data']['context'];$row=provider_password_reset_template_authority($c);
    if($r['grant']['revoked_at']!==null)throw new ProviderOperationAccessChanged('The template approval was revoked.');
    if($send){
        if($r['terminal']!==null||(int)$r['grant']['expires_epoch']<=time())throw new ProviderOperationAccessChanged('The template approval is no longer current.');
        $g=provider_password_reset_template_graph();$latest=$g['latest'][$c['profile_id']]??null;$end=$latest['end']??null;
        if(!$end||$end['document']['id']!==$r['document']['data']['anchor_id']||$end['document']['data']['state']!=='complete'||$latest['start']['document']['data']['context']!==$c||$end['document']['data']['observed_epoch']<time()-PROVIDER_RESET_TEMPLATE_FRESH_SECONDS)throw new ProviderOperationAccessChanged('The reviewed template observation changed.');
    }
    return [$r,$row];
}
/** Dedicated supplier keeps content outside ProviderOperation params, grants, ledger rows and ordinary diagnostics. */
function provider_password_reset_template_perform(array $row,#[SensitiveParameter] string $secret,string $address,callable $authorize,ProviderOperation $operation,callable $contentSupplier):OperationResult {
    $identity=$row['id'].':'.(int)$row['revision'].':'.hash('sha256',$row['credentials']);
    $credential=new ProviderCredential('twenty-i',$identity,static function()use($secret,$authorize):string{$authorize();return $secret;});
    return (new ProviderGateway(new TwentyIOperationAdapter(null,$contentSupplier),new FixedHttpTransport($address,$authorize),new ProviderOperationLedger()))->perform($credential,$operation);
}
function provider_password_reset_template_terminal(array $request,string $phase):array {
    provider_password_reset_template_guard(true);$r=provider_password_reset_template_request($request['document']['id']);
    if($r['terminal']!==null||!in_array($phase,['not_sent','attempted'],true)||($phase==='not_sent'&&$r['ledger']!==null))throw new RuntimeException('Template dispatch history changed.');
    return provider_password_reset_template_append('terminal',bin2hex(random_bytes(16)),$r['document']['profile_id'],$r['document']['id'],$r['document']['actor_id'],['phase'=>$phase,'request_sha256'=>$r['row']['document_sha256'],'ledger_sha256'=>provider_password_reset_template_ledger_digest($r['ledger'])]);
}
function provider_password_reset_template_change(string $requestId,string $profileId,int $revision,string $observationId,string $reviewSha,#[SensitiveParameter] string $content,bool $reviewed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Template changes cannot inherit a transaction.');
    provider_password_reset_template_id($requestId);provider_password_reset_template_id($observationId);provider_password_reset_template_digest($reviewSha);
    if(!$reviewed)throw new InvalidArgumentException('Review and approve this complete replacement first.');
    $template=PasswordResetTemplate::fromString($content);if($template->bytes===0)throw new InvalidArgumentException('An empty replacement is not supported.');
    return staff_locked(function()use($requestId,$profileId,$revision,$observationId,$reviewSha,$content,$password,$resolver,$perform,$template):array{
        $fresh=staff_transaction(function()use($requestId,$profileId,$revision,$observationId,$reviewSha,$content,$password,$template):bool{
            $actor=provider_password_reset_template_actor($password);$g=provider_password_reset_template_graph();if($g===null)throw new RuntimeException('Prepare template storage first.');
            if(isset($g['requests'][$requestId])){
                $old=$g['requests'][$requestId];$v=$old['document']['data'];provider_password_reset_template_authorize_request($requestId);
                if($old['document']['profile_id']!==$profileId||$v['context']['revision']!==$revision||$v['anchor_id']!==$observationId||!hash_equals($v['review_sha256'],$reviewSha)||!hash_equals($v['content_sha256'],$template->sha256)||$v['content_bytes']!==$template->bytes||$old['document']['actor_id']!==(int)$actor['id'])throw new RuntimeException('This template request identifier was already used with different details.');return false;
            }
            if(isset($g['events'][$requestId]))throw new RuntimeException('This template request identifier was already used.');
            [$ctx,,$anchor,$previous]=provider_password_reset_template_precondition($g,$profileId,$revision,$observationId);
            $expected=provider_password_reset_template_hmac('review',provider_password_reset_template_json(provider_password_reset_template_review_data($requestId,$ctx,$anchor,$previous,$template)));
            if(!hash_equals($expected,$reviewSha))throw new RuntimeException('This exact replacement needs a current review.');
            if(hash_equals($anchor['document']['data']['content_sha256'],$template->sha256))throw new InvalidArgumentException('The latest saved template already matches this replacement.');
            if(count($g['events'])>PROVIDER_RESET_TEMPLATE_LIMIT-5)throw new RuntimeException('Template history is full.');$now=time();$count=0;
            foreach($g['requests'] as $r)if($r['document']['profile_id']===$profileId&&$r['document']['at']>$now-86400)++$count;
            if($count>=10)throw new RuntimeException('Template change limit reached. Try again tomorrow.');
            $data=['context'=>$ctx,'anchor_id'=>$observationId,'anchor_sha256'=>$anchor['row']['document_sha256'],'content_sha256'=>$template->sha256,'content_bytes'=>$template->bytes,'review_sha256'=>$reviewSha,'previous_request'=>$previous];$json=provider_password_reset_template_json($data);
            query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$requestId,$profileId,PROVIDER_RESET_TEMPLATE_OPERATION,provider_operations_scope(PROVIDER_RESET_TEMPLATE_OPERATION,$data),$revision,$json,(int)$actor['id'],gmdate('Y-m-d H:i:s',$now),$now+900,'reset-template:'.$requestId,gmdate('Y-m-d H:i:s',$now)]);
            $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$requestId]);$data['grant_sha256']=provider_password_reset_template_grant_digest($grant);
            provider_password_reset_template_append('request',$requestId,$profileId,$requestId,(int)$actor['id'],$data,$content,$now);return true;
        });
        if(!$fresh)return provider_password_reset_template_summary(provider_password_reset_template_request($requestId));
        $entered=false;
        try{
            $authorize=static fn()=>provider_password_reset_template_authorize_request($requestId,true)[1];$authorize();$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];$row=$authorize();
            $secret=provider_settings_key_value(provider_settings_unseal($row['id'],$row['environment'],$row['credentials']));
            try{
                [$request]=provider_password_reset_template_authorize_request($requestId,true);$operation=provider_password_reset_template_operation($request);
                $supplier=static function(string $id,ProviderOperation $op)use($requestId):string{
                    if($id!==$requestId)throw new ProviderOperationAccessChanged('The private template request changed.');[$r]=provider_password_reset_template_authorize_request($id,true);
                    if($op->name!==PROVIDER_RESET_TEMPLATE_OPERATION||$op->idempotencyKey!=='reset-template:'.$id||$op->params!==provider_password_reset_template_operation($r)->params)throw new ProviderOperationAccessChanged('The private template request changed.');return provider_password_reset_template_open($r);
                };
                $authorize();$entered=true;($perform??'provider_password_reset_template_perform')($row,$secret,$address,$authorize,$operation,$supplier);$authorize();
            }finally{unset($secret);}
        }catch(Throwable $e){if($e instanceof PDOException)throw $e;/* Never persist provider text or infer no-send after entering the gateway. */}
        staff_transaction(fn()=>provider_password_reset_template_terminal(provider_password_reset_template_request($requestId),$entered?'attempted':'not_sent'));
        return provider_password_reset_template_summary(provider_password_reset_template_request($requestId));
    });
}
function provider_password_reset_template_verify(string $requestId,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $transport=null):array {
    if(db()->inTransaction())throw new LogicException('Template verification cannot inherit a transaction.');
    return staff_locked(function()use($requestId,$password,$resolver,$transport):array{
        provider_password_reset_template_actor($password);[$r]=provider_password_reset_template_authorize_request($requestId);
        if(!$r['terminal']||$r['terminal']['document']['data']['phase']!=='attempted')throw new RuntimeException('Restore eligible dispatch completion before checking this change.');$ledger=provider_password_reset_template_finished($r);
        $receipt=provider_password_reset_template_read_locked($r['document']['data']['context'],$requestId,$resolver,$transport);
        staff_transaction(function()use($requestId,$receipt,$ledger):void{
            [$r]=provider_password_reset_template_authorize_request($requestId);
            if(!hash_equals($ledger,provider_password_reset_template_finished($r))||!$r['terminal']||$r['terminal']['document']['data']['phase']!=='attempted')throw new RuntimeException('Template dispatch history changed during the read.');
            $data=$receipt['document']['data'];$disposition='unavailable';
            if($data['state']==='complete')$disposition=hash_equals(provider_password_reset_template_open($r),provider_password_reset_template_open($receipt))?'observed':'different';
            provider_password_reset_template_append('check',bin2hex(random_bytes(16)),$r['document']['profile_id'],$requestId,$r['document']['actor_id'],['receipt_id'=>$receipt['document']['id'],'disposition'=>$disposition,'request_sha256'=>$r['row']['document_sha256'],'ledger_sha256'=>$ledger]);
        });
        return provider_password_reset_template_summary(provider_password_reset_template_request($requestId));
    });
}
function provider_password_reset_template_recover_finished(string $requestId,string $reviewedLedgerHash,#[SensitiveParameter] string $password):array {
    provider_password_reset_template_digest($reviewedLedgerHash);if(db()->inTransaction())throw new LogicException('Template recovery cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$reviewedLedgerHash,$password):array{
        provider_password_reset_template_actor($password);[$r]=provider_password_reset_template_authorize_request($requestId);
        if($r['terminal']!==null)throw new RuntimeException('This dispatch already has completion history.');
        one('SELECT id FROM '.table('provider_operations').' WHERE idempotency_key=? FOR UPDATE',[$r['grant']['consumed_key']]);$r=provider_password_reset_template_request($requestId);
        if(!hash_equals($reviewedLedgerHash,provider_password_reset_template_finished($r)))throw new RuntimeException('The reviewed dispatch completion changed. Reload its history.');
        provider_password_reset_template_terminal($r,'attempted');return provider_password_reset_template_summary(provider_password_reset_template_request($requestId));
    }));
}
function provider_password_reset_template_dismiss(string $requestId,#[SensitiveParameter] string $password):array {
    provider_password_reset_template_id($requestId);if(db()->inTransaction())throw new LogicException('Template dismissal cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($requestId,$password):array{
        $actor=provider_password_reset_template_actor($password);$r=provider_password_reset_template_request($requestId);
        if($r['dismissal']!==null)throw new RuntimeException('This request is already closed.');
        $observed=false;foreach($r['checks'] as $c)if($c['document']['data']['disposition']==='observed')$observed=true;
        if($observed||($r['terminal']&&$r['terminal']['document']['data']['phase']==='not_sent'))throw new RuntimeException('This request is already resolved.');
        provider_password_reset_template_append('dismissal',bin2hex(random_bytes(16)),$r['document']['profile_id'],$requestId,(int)$actor['id'],['request_sha256'=>$r['row']['document_sha256'],'disposition'=>'dismissed']);
        return provider_password_reset_template_summary(provider_password_reset_template_request($requestId));
    }));
}
function provider_password_reset_template_summary(array $r):array {
    $current=false;try{provider_password_reset_template_authority($r['document']['data']['context']);$current=$r['grant']['revoked_at']===null;}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;}
    $digest=null;try{$digest=provider_password_reset_template_finished($r);}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;}
    $status='interrupted';$last=null;
    if($r['terminal'])$status=$r['terminal']['document']['data']['phase']==='not_sent'?'not_sent':(($r['ledger']['status']??null)==='accepted_pending'?'accepted_pending':'unknown');
    foreach($r['checks'] as $c){$last=['disposition'=>$c['document']['data']['disposition'],'recorded_epoch'=>$c['document']['at']];$status=$last['disposition']==='observed'?'observed':($last['disposition']==='different'?'different':'unknown');}
    $dismissed=$r['dismissal']!==null;if($dismissed&&$status!=='observed')$status='dismissed';
    return ['id'=>$r['document']['id'],'status'=>$status,'requested_epoch'=>$r['document']['at'],'content_sha256'=>$r['document']['data']['content_sha256'],'content_bytes'=>$r['document']['data']['content_bytes'],'current_context'=>$current,'dismissed'=>$dismissed,'dismissed_epoch'=>$dismissed?$r['dismissal']['document']['at']:null,'can_verify'=>!$dismissed&&$current&&$digest!==null&&$r['terminal']!==null&&$r['terminal']['document']['data']['phase']==='attempted','can_recover'=>!$dismissed&&$current&&$digest!==null&&$r['terminal']===null,'can_dismiss'=>!$dismissed&&!in_array($status,['observed','not_sent'],true),'recovery_digest'=>!$dismissed&&$current&&$r['terminal']===null?$digest:null,'last_check'=>$last];
}
function provider_password_reset_template_screen(string $profileId,int $page=1):array {
    if($page<1||$page>10000)throw new InvalidArgumentException('Choose a valid template history page.');
    return staff_locked(function()use($profileId,$page):array{
        provider_password_reset_template_actor();$profile=provider_settings_load($profileId);$g=provider_password_reset_template_graph();
        $out=['ready'=>$g!==null,'profile'=>provider_settings_projection($profile),'latest'=>null,'review'=>null,'items'=>[],'page'=>$page,'has_more'=>false];if($g===null)return $out;
        $latest=$g['latest'][$profileId]??null;$end=$latest['end']??null;
        if($end){$d=$end['document'];$out['latest']=['id'=>$d['id'],'state'=>$d['data']['state'],'reason'=>$d['data']['reason'],'observed_epoch'=>$d['data']['observed_epoch']];
            if($d['data']['state']==='complete'){
                $current=false;
                try{provider_password_reset_template_authority($latest['start']['document']['data']['context']);$current=true;}
                catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;/* Stale content does not become a current editor. */}
                if($current)$out['latest']['content']=provider_password_reset_template_open($end);
                try{provider_password_reset_template_precondition($g,$profileId,(int)$profile['revision'],$d['id']);$out['review']=['request_id'=>bin2hex(random_bytes(16)),'profile_id'=>$profileId,'revision'=>(int)$profile['revision'],'observation_id'=>$d['id']];}
                catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}
            }
        }
        $items=[];foreach(array_reverse($g['requests'],true) as $r)if($r['document']['profile_id']===$profileId)$items[]=provider_password_reset_template_summary($r);
        $out['has_more']=count($items)>$page*20;$out['items']=array_slice($items,($page-1)*20,20);return $out;
    });
}
