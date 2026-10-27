<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
/** Actual production parsers with explicit in-memory history seams; not a SQL acceptance test. */
$root=$argv[1]??dirname(__DIR__);
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation};
use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;
use CajunWebPro\Foundation\TwentyI\Operations\SubdomainAdd;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};
foreach(['provider/ProviderOperation','provider/ProviderGateway','twentyi-package-php/PackagePhpVersionBinding','twentyi-document-roots/DocumentRootsBinding','twentyi-document-roots/DocumentRootsObservation','twentyi-operations/SubdomainAdd'] as $file)require_once $root.'/foundation/'.$file.'.php';
define('CWP_BOOT',true);require $root.'/site/database/schema/provider_subdomain_add_schema.php';
function sc_extract(string $file,array $names):void {
    $s=file_get_contents($file);if($s===false)throw new RuntimeException('Missing production source.');
    foreach($names as $name){
        if(preg_match('/^function '.preg_quote($name,'/').'\([^\n]*[\s\S]*?(?=^function |\z)/m',$s,$m)!==1)throw new RuntimeException('Missing exact production function.');
        eval('use CajunWebPro\\Foundation\\TwentyIDocumentRoots\\{DocumentRootsBinding,DocumentRootsObservation}; use CajunWebPro\\Foundation\\TwentyI\\Operations\\SubdomainAdd; use CajunWebPro\\Foundation\\Provider\\{ProviderOperation,ProviderGateway}; '.$m[0]);
    }
}
sc_extract($root.'/site/app/providers/subdomain_add_storage.php',['provider_subdomain_add_hmac','provider_subdomain_add_columns','provider_subdomain_add_values','provider_subdomain_add_ledger_values','provider_subdomain_add_ledger_digest']);
sc_extract($root.'/site/app/providers/subdomain_add.php',['provider_subdomain_add_grant_values','provider_subdomain_add_grant_scope','provider_subdomain_add_decode','provider_subdomain_add_prove','provider_subdomain_add_entry','provider_subdomain_add_request','provider_subdomain_add_terminal','provider_subdomain_add_target','provider_subdomain_add_review_document','provider_subdomain_add_finished_ledger','provider_subdomain_add_check_document','provider_subdomain_add_pending','provider_subdomain_add_original_actor','provider_subdomain_add_actor']);
$checks=[];
function sc_check(string $name,bool $passed):void {global $checks;if(isset($checks[$name]))throw new LogicException('Duplicate check.');$checks[$name]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function sc_refuse(string $name,callable $f):void {try{$f();}catch(InvalidArgumentException|RuntimeException $e){sc_check($name,true);return;}throw new RuntimeException($name.' did not refuse');}
function provider_document_roots_json(array $d):string{return json_encode($d,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function provider_document_roots_id(string $id):void {if(preg_match('/\A[a-f0-9]{32}\z/',$id)!==1)throw new InvalidArgumentException('Invalid ID.');}
function provider_document_roots_digest(array $a):string {$s='';foreach($a as $v){$v=(string)$v;$s.=strlen($v).':'.$v;}return hash('sha256',$s);}
function provider_settings_vault_key():string{return str_repeat('synthetic-portable-only',3);}
function provider_document_roots_from_data(array $d):DocumentRootsBinding{return new DocumentRootsBinding(new PackagePhpVersionBinding($d['package']));}
function provider_document_roots_retained_binding(DocumentRootsBinding $b):void {if($GLOBALS['lost_source'])throw new RuntimeException('Source missing.');}
function provider_subdomain_add_package(DocumentRootsBinding $b):array{return $GLOBALS['package'];}
function provider_document_roots_source(string $id):?array{return $GLOBALS['sources'][$id]??null;}
function provider_document_roots_observation(array $r):array{return $GLOBALS['observations'][$r['id']];}
function provider_document_roots_read_binding(array $r,array $row):DocumentRootsBinding{return $GLOBALS['binding'];}
function provider_document_roots_row_hash(?array $row):string{if(!$row)throw new RuntimeException('Missing row.');return hash('sha256',provider_document_roots_json($row));}
function provider_subdomain_add_guard(bool $tx=true):void {if(!$GLOBALS['lock'])throw new LogicException('Missing lock.');}
function table(string $name):string{return $name;}
function one(string $sql,array $args=[]):?array {
    if(str_contains($sql,'LEFT JOIN')){$GLOBALS['pending_sql']=[$sql,$args];return null;}
    foreach(['provider_subdomain_add_requests','provider_operation_grants','provider_operations','provider_subdomain_add_terminals','provider_document_roots_reads'] as $table){if(str_contains($sql,'FROM '.$table.' '))return $GLOBALS['rows'][$table][$args[0]]??null;}
    throw new LogicException('Unmodeled query.');
}
function provider_settings_manager():array {++$GLOBALS['actor_calls'];if($GLOBALS['denied'])throw new RuntimeException('Owner denied.');return $GLOBALS['actor'];}
function provider_settings_owner(string $password):array {if($password!=='synthetic-owner-pass')throw new InvalidArgumentException('Wrong password.');return provider_settings_manager();}
function sc_proof(array $d):string{return rtrim(strtr(base64_encode(provider_document_roots_json($d)),'+/','-_'),'=').'.'.provider_subdomain_add_hmac('review',$d);}

try{
    $id=str_repeat('1',32);$link=str_repeat('2',32);$anchorId=str_repeat('3',32);$profile=str_repeat('4',32);$now=time();$hash=str_repeat('a',64);
    $binding=new DocumentRootsBinding(new PackagePhpVersionBinding(['profile_id'=>$profile,'profile_revision'=>1,'key_fingerprint'=>$hash,'credential_sha256'=>$hash,'package_id'=>5701,'client_id'=>2,'link_id'=>$link,'link_sha256'=>$hash,'event_id'=>3,'event_sha256'=>$hash,'audit_id'=>4,'audit_sha256'=>$hash,'source_id'=>str_repeat('5',32),'source_seq'=>5,'source_sha256'=>$hash]));
    $GLOBALS['binding']=$binding;$GLOBALS['package']=['enabled'=>true,'name'=>'example.test','names'=>['example.test']];$GLOBALS['lost_source']=false;$GLOBALS['lock']=true;
    $GLOBALS['actor']=['id'=>7,'auth_version'=>'synthetic-auth'];$GLOBALS['actor_calls']=0;$GLOBALS['denied']=false;$_SESSION=[];
    $anchor=['id'=>$anchorId,'seq'=>7,'entries'=>[['name'=>'example.test','path'=>'']],'binding'=>$binding,'observed_epoch'=>$now,'proof'=>$hash];
    $review=['version'=>1,'request_id'=>$id,'link_id'=>$link,'actor_id'=>7,'actor_auth'=>hash('sha256','synthetic-auth'),'binding_sha256'=>$binding->sha256,'anchor_id'=>$anchorId,'anchor_proof'=>$hash,'previous_id'=>'','previous_proof'=>str_repeat('0',64),'issued_epoch'=>$now,'expires_epoch'=>$now+300];
    $proof=sc_proof($review);sc_check('Signed form round trips exact current binding',provider_subdomain_add_review_document($proof)===$review);
    foreach(['request_id','link_id','actor_auth','binding_sha256','anchor_proof','expires_epoch'] as $field){$d=$review;$d[$field]=is_int($d[$field])?$d[$field]+1:str_repeat('b',strlen($d[$field]));$bad=rtrim(strtr(base64_encode(provider_document_roots_json($d)),'+/','-_'),'=').'.'.substr($proof,strrpos($proof,'.')+1);sc_refuse('Unsigned changed '.$field.' refuses',fn()=>provider_subdomain_add_review_document($bad));}
    foreach(['version'=>2,'actor_id'=>0,'expires_epoch'=>$now+301,'previous_proof'=>$hash,'issued_epoch'=>-1,'extra'=>true] as $field=>$value){$d=$review;$d[$field]=$value;sc_refuse('Signed malformed '.$field.' refuses',fn()=>provider_subdomain_add_review_document(sc_proof($d)));}
    sc_refuse('Oversized signed form refuses',fn()=>provider_subdomain_add_review_document(str_repeat('a',4097)));
    sc_refuse('Base64 padding is noncanonical',fn()=>provider_subdomain_add_review_document(explode('.',$proof)[0].'=.'.explode('.',$proof)[1]));
    $old=$review;$old['issued_epoch']=$now-1000;$old['expires_epoch']=$now-700;sc_check('Historical parser retains expired signed review',provider_subdomain_add_review_document(sc_proof($old))===$old);
    $params=provider_subdomain_add_target($binding,$anchor,'example.test','new.example.test');sc_check('Exact attached parent and missing strict child accepted',$params===['packageId'=>5701,'parentDomain'=>'example.test','subdomain'=>'new.example.test']);
    sc_check('Empty observed parent path is present',provider_subdomain_add_entry($anchor,'example.test')==='');
    sc_refuse('Unrelated parent refuses',fn()=>provider_subdomain_add_target($binding,$anchor,'other.test','new.other.test'));
    sc_refuse('Nested extra labels refuse',fn()=>provider_subdomain_add_target($binding,$anchor,'example.test','two.new.example.test'));
    sc_refuse('Uppercase child refuses',fn()=>provider_subdomain_add_target($binding,$anchor,'example.test','New.example.test'));
    $a=$anchor;$a['entries'][]=['name'=>'new.example.test','path'=>''];sc_refuse('Already attached child with empty path refuses',fn()=>provider_subdomain_add_target($binding,$a,'example.test','new.example.test'));
    $GLOBALS['package']['names'][]='new.example.test';sc_refuse('Child in package source but omitted from map refuses',fn()=>provider_subdomain_add_target($binding,$anchor,'example.test','new.example.test'));array_pop($GLOBALS['package']['names']);
    $GLOBALS['package']['enabled']=false;sc_refuse('Disabled package refuses',fn()=>provider_subdomain_add_target($binding,$anchor,'example.test','new.example.test'));$GLOBALS['package']['enabled']=true;
    $d=['version'=>1,'binding'=>$binding->data,'anchor_id'=>$anchorId,'anchor_seq'=>7,'anchor_proof'=>$hash,'params'=>$params,'reviewed'=>true,'review_proof'=>$proof,'actor_auth'=>$review['actor_auth'],'approved_epoch'=>$now];
    $json=provider_document_roots_json($d);$scope=provider_subdomain_add_grant_scope($id,$json);
    sc_check('Compact shared grant commits exact complete document',strlen(provider_document_roots_json($scope))<4096&&$scope['request_sha256']===hash('sha256',$json));
    // The shared operation grant uses its own keyed scope function; this portable model pins that exact call.
    function provider_operations_scope(string $name,array $data):string{return hash_hmac('sha256',$name.'|'.provider_document_roots_json($data),'synthetic-scope');}
    $g=['id'=>$id,'seq'=>8,'profile_id'=>$profile,'operation'=>SubdomainAdd::OPERATION,'scope_fingerprint'=>provider_operations_scope(SubdomainAdd::OPERATION,$scope),'granted_by'=>7,'granted_at'=>gmdate('Y-m-d H:i:s',$now),'expires_epoch'=>$now+900,'profile_revision'=>1,'scope_params'=>provider_document_roots_json($scope),'consumed_key'=>'subdomainadd:'.$id,'consumed_at'=>gmdate('Y-m-d H:i:s',$now),'revoked_at'=>null];
    $q=['id'=>$id,'grant_seq'=>8,'profile_id'=>$profile,'package_id'=>5701,'subdomain'=>'new.example.test','link_id'=>$link,'actor_id'=>7,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];$q=provider_subdomain_add_prove('request',$q,$g);
    $GLOBALS['rows']=['provider_subdomain_add_requests'=>[$id=>$q],'provider_operation_grants'=>[$id=>$g],'provider_operations'=>[],'provider_subdomain_add_terminals'=>[],'provider_document_roots_reads'=>[]];
    $GLOBALS['sources']=[$anchorId=>['id'=>$anchorId]];$GLOBALS['observations']=[$anchorId=>$anchor];
    $request=provider_subdomain_add_request($id);sc_check('Actual request parser authenticates original graph',$request['document']===$d);
    $GLOBALS['rows']['provider_subdomain_add_requests'][$id]['subdomain']='other.example.test';sc_refuse('Fence lookup column cannot diverge from signed request',fn()=>provider_subdomain_add_request($id));$GLOBALS['rows']['provider_subdomain_add_requests'][$id]=$q;
    unset($GLOBALS['rows']['provider_operation_grants'][$id]);sc_refuse('Lost original shared grant refuses',fn()=>provider_subdomain_add_request($id));$GLOBALS['rows']['provider_operation_grants'][$id]=$g;
    $GLOBALS['lost_source']=true;sc_refuse('Lost original source refuses history',fn()=>provider_subdomain_add_request($id));$GLOBALS['lost_source']=false;
    $GLOBALS['rows']['provider_operation_grants'][$id]['scope_params']='{}';sc_refuse('Changed compact grant refuses',fn()=>provider_subdomain_add_request($id));$GLOBALS['rows']['provider_operation_grants'][$id]=$g;
    $op=new ProviderOperation(SubdomainAdd::OPERATION,'subdomainadd:'.$id,$params);
    $ledger=['id'=>str_repeat('6',32),'idempotency_key'=>$op->idempotencyKey,'fingerprint'=>ProviderGateway::requestFingerprint('twenty-i',$profile.':1:'.$hash,$op),'operation'=>SubdomainAdd::OPERATION,'status'=>'uncertain','result_message'=>'No confirmed outcome.','provider_code'=>null,'external_id'=>null,'attempts'=>1,'claimed_at'=>gmdate('Y-m-d H:i:s',$now),'claimed_epoch'=>$now,'resolved_epoch'=>$now,'reconciled_outcome'=>null,'reconciled_epoch'=>null];
    $request['ledger']=$ledger;sc_check('Exact original one-attempt finished ledger eligible',provider_subdomain_add_finished_ledger($request,$now)===provider_subdomain_add_ledger_digest($ledger));
    foreach(['status'=>'claimed','attempts'=>0,'operation'=>null,'resolved_epoch'=>null,'external_id'=>'fabricated','reconciled_outcome'=>'success','fingerprint'=>str_repeat('0',64),'idempotency_key'=>'wrong'] as $field=>$value){$r=$request;$r['ledger'][$field]=$value;sc_refuse('Ineligible ledger '.$field.' cannot release fence',fn()=>provider_subdomain_add_finished_ledger($r,$now));}
    $r=$request;$r['ledger']=null;sc_refuse('Missing ledger cannot verify or recover',fn()=>provider_subdomain_add_finished_ledger($r,$now));
    $r=$request;$r['ledger']['reconciled_epoch']=$now;sc_check('Reconciliation metadata participates in immutable ledger digest',provider_subdomain_add_ledger_digest($r['ledger'])!==provider_subdomain_add_ledger_digest($ledger));
    $GLOBALS['rows']['provider_operations'][$op->idempotencyKey]=$ledger;
    $claim=$ledger;$claim['operation']=null;$claim['status']='claimed';$claim['attempts']=0;$claim['resolved_epoch']=null;$GLOBALS['rows']['provider_operations'][$op->idempotencyKey]=$claim;
    sc_check('Actual gateway transient claim remains parseable',provider_subdomain_add_request($id)['ledger']===$claim);
    $claim['attempts']=1;$GLOBALS['rows']['provider_operations'][$op->idempotencyKey]=$claim;sc_refuse('Malformed null-operation claim refuses',fn()=>provider_subdomain_add_request($id));$GLOBALS['rows']['provider_operations'][$op->idempotencyKey]=$ledger;
    $terminal=['version'=>1,'request_proof'=>$q['proof'],'phase'=>'attempted','ended_epoch'=>$now,'ledger_sha256'=>provider_subdomain_add_ledger_digest($ledger)];$tj=provider_document_roots_json($terminal);
    $t=provider_subdomain_add_prove('terminal',['id'=>$id,'actor_id'=>7,'disposition'=>'attempted','ledger_sha256'=>$terminal['ledger_sha256'],'document_json'=>$tj,'document_sha256'=>hash('sha256',$tj)]);$GLOBALS['rows']['provider_subdomain_add_terminals'][$id]=$t;
    $readId=str_repeat('7',32);$r=['id'=>$readId,'seq'=>10,'state'=>'complete','actor_id'=>7];$GLOBALS['sources'][$readId]=$r;$GLOBALS['rows']['provider_document_roots_reads'][$readId]=['id'=>$readId];
    $obs=$anchor;$obs['id']=$readId;$obs['seq']=10;$obs['entries'][]=['name'=>'new.example.test','path'=>''];$GLOBALS['observations'][$readId]=$obs;
    $check=['version'=>1,'request_proof'=>$q['proof'],'terminal_proof'=>$t['proof'],'floor'=>9,'receipt_id'=>$readId,'receipt_seq'=>10,'receipt_sha256'=>provider_document_roots_row_hash($r),'binding_sha256'=>$binding->sha256,'disposition'=>'observed','path'=>'','observation_proof'=>$obs['proof'],'recorded_epoch'=>$now];
    $make=static function(array $d)use($id,$readId):array{$j=provider_document_roots_json($d);return provider_subdomain_add_prove('check',['id'=>str_repeat('8',32),'request_id'=>$id,'actor_id'=>7,'receipt_id'=>$readId,'disposition'=>$d['disposition'],'document_json'=>$j,'document_sha256'=>hash('sha256',$j)]);};
    sc_check('Exact hostname with empty provider path is observed',provider_subdomain_add_check_document($make($check),$request)['disposition']==='observed');
    $originalBinding=$binding;$changed=$binding->data['package'];$changed['client_id']=3;$GLOBALS['binding']=new DocumentRootsBinding(new PackagePhpVersionBinding($changed));$changedCheck=$check;$changedCheck['binding_sha256']=$GLOBALS['binding']->sha256;
    sc_refuse('Historical readback cannot switch original client even with same link',fn()=>provider_subdomain_add_check_document($make($changedCheck),$request));$GLOBALS['binding']=$originalBinding;
    $GLOBALS['sources'][$readId]['actor_id']=8;$changedCheck=$check;$changedCheck['receipt_sha256']=provider_document_roots_row_hash($GLOBALS['sources'][$readId]);sc_refuse('Historical readback must retain actual original owner',fn()=>provider_subdomain_add_check_document($make($changedCheck),$request));$GLOBALS['sources'][$readId]=$r;
    $d2=$check;$d2['floor']=10;sc_refuse('Pre-plan receipt cannot confirm',fn()=>provider_subdomain_add_check_document($make($d2),$request));
    $GLOBALS['observations'][$readId]['entries']=$anchor['entries'];sc_refuse('Observation of another name cannot confirm',fn()=>provider_subdomain_add_check_document($make($check),$request));
    $d2=$check;$d2['disposition']='different';$d2['path']=null;sc_check('Complete map omission remains different',provider_subdomain_add_check_document($make($d2),$request)['disposition']==='different');
    $GLOBALS['sources'][$readId]['state']='unavailable';$d2['disposition']='unavailable';$d2['observation_proof']=null;$d2['receipt_sha256']=provider_document_roots_row_hash($GLOBALS['sources'][$readId]);sc_check('Failed read remains unavailable',provider_subdomain_add_check_document($make($d2),$request)['disposition']==='unavailable');
    provider_subdomain_add_pending($profile,5701,'new.example.test');[$sql,$args]=$GLOBALS['pending_sql'];sc_check('Fence query includes exact hostname across assignments',$args===[$profile,5701,'new.example.test']&&str_contains($sql,'BINARY q.subdomain=BINARY ?')&&!str_contains($sql,'q.link_id=')&&!str_contains($sql,'profile_revision'));
    sc_check('Only observed check releases an attempted hostname fence',str_contains($sql,"BINARY c.disposition='observed'")&&!str_contains($sql,"BINARY c.disposition='different'"));
    $schema=provider_subdomain_add_schema('abcdefghijklmnopqrst');sc_check('New schema has three explicit transactional tables',count($schema)===3&&count(array_filter($schema,fn($s)=>str_contains($s,'ENGINE=InnoDB')))===3);
    sc_check('Exact hostname resource index is retained',str_contains($schema['provider_subdomain_add_requests'],'subdomain_add_resource(profile_id,package_id,subdomain)'));
    foreach(array_keys($schema) as $name)sc_check('Maximum-prefix identifier fits '.$name,strlen('abcdefghijklmnopqrst'.$name.'_ibfk_1')<=64);
    sc_refuse('Invalid schema prefix refuses',fn()=>provider_subdomain_add_schema('bad-prefix'));
    sc_check('Current original actor accepted',(provider_subdomain_add_original_actor($request,$GLOBALS['actor'])??true)===true);
    $actor=$GLOBALS['actor'];$actor['auth_version']='changed';sc_refuse('Changed original actor auth refuses',fn()=>provider_subdomain_add_original_actor($request,$actor));
    $_SESSION['contact_id']=null;$before=$GLOBALS['actor_calls'];sc_refuse('Present null contact marker refuses before normalization',fn()=>provider_subdomain_add_actor());sc_check('Contact refusal never normalizes owner',$GLOBALS['actor_calls']===$before);$_SESSION=[];
    $GLOBALS['cwp_settings']=['staff_schema_version'=>'stale'];provider_subdomain_add_actor();sc_check('Owner authority clears cached settings',!isset($GLOBALS['cwp_settings']));
    sc_refuse('Wrong password refuses',fn()=>provider_subdomain_add_actor('wrong'));
    $GLOBALS['denied']=true;sc_refuse('Nonowner or support authority refusal propagates',fn()=>provider_subdomain_add_actor());
    echo json_encode(['passed'=>true,'scope'=>'Actual production parser and closed-history seams; modeled rows only, no SQL/provider/native acceptance.','checks'=>array_values($checks)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");echo json_encode(['passed'=>false,'checks'=>array_values($checks)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(1);}
