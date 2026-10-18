<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);
require __DIR__.'/../site/app/providers/stackcp_documents.php';
require __DIR__.'/../site/app/providers/stackcp_auth_vault.php';
$checks=[];
function sp_ok(string $name,bool $passed): void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function sp_deny(string $name,callable $fn,string $class=InvalidArgumentException::class): void {try {$fn();sp_ok($name,false);}catch (Throwable $e) {sp_ok($name,$e instanceof $class&&!str_contains($e->getMessage(),'PRIVATE_FIXTURE'));}}
$auth=['version'=>1,'kind'=>'auth','id'=>str_repeat('a',32),'ordinal'=>1,'scope_id'=>str_repeat('b',32),'profile_id'=>str_repeat('b',32),'revision'=>1,'actor_id'=>7,'actor_auth_version'=>str_repeat('c',32),'recorded_epoch'=>1760000000,'previous_sha256'=>str_repeat('0',64),'profile_revision'=>3,'profile_credentials_sha256'=>str_repeat('d',64),'enabled'=>true,'ciphertext_sha256'=>str_repeat('e',64)];
$assignment=array_slice($auth,0,11,true)+['link_id'=>str_repeat('f',32),'package_id'=>701,'client_id'=>9,'client_auth_version'=>str_repeat('1',32),'state'=>'unverified','stack_identity'=>'stack-user:97','profile_revision'=>3,'profile_credentials_sha256'=>str_repeat('d',64),'key_fingerprint'=>str_repeat('2',64),'link_sha256'=>str_repeat('3',64),'event_id'=>12,'event_sha256'=>str_repeat('4',64),'audit_id'=>13,'audit_sha256'=>str_repeat('5',64),'auth_id'=>str_repeat('a',32),'auth_sha256'=>str_repeat('6',64)];$assignment['kind']='assignment';$assignment['scope_id']=$assignment['profile_id'].':701';
foreach (['auth'=>$auth,'assignment'=>$assignment] as $kind=>$input) {
    sp_ok($kind.' document round trip is exact',provider_stackcp_document($kind,$input)===$input);
    foreach (array_keys($input) as $key) { $bad=$input;unset($bad[$key]);sp_deny($kind.' missing '.$key,fn()=>provider_stackcp_document($kind,$bad)); }
    $bad=$input;$bad['extra']='PRIVATE_FIXTURE';sp_deny($kind.' extra field refuses',fn()=>provider_stackcp_document($kind,$bad));
    $bad=array_reverse($input,true);sp_deny($kind.' noncanonical key order refuses',fn()=>provider_stackcp_document($kind,$bad));
    foreach (['id','actor_auth_version','profile_id'] as $key) { $bad=$input;$bad[$key]=str_repeat('A',32);sp_deny($kind.' uppercase '.$key.' refuses',fn()=>provider_stackcp_document($kind,$bad)); }
    foreach (['ordinal','revision','actor_id','recorded_epoch'] as $key) foreach ([0,2147483648,'1',true] as $i=>$value) { $bad=$input;$bad[$key]=$value;sp_deny($kind.' invalid '.$key.' variant '.$i,fn()=>provider_stackcp_document($kind,$bad)); }
    $ref=$input['actor_id'];$input['actor_id']=&$ref;$saved=provider_stackcp_document($kind,$input);$bytes=provider_stackcp_json($saved);$ref=99;sp_ok($kind.' detached scalar reference cannot alter saved document',$saved['actor_id']===7&&provider_stackcp_json($saved)===$bytes);
}
foreach (['', 'stack-user:0','stack-user:01','stack-user:-1','stack-user:2147483648','stack-user:7<package:701>','stack-user:7,8','user:7',"stack-user:7\n",97,null] as $i=>$value) sp_deny('Unsupported local Stack-user identity '.$i,fn()=>provider_stackcp_identity($value));
foreach (['stack-user:1','stack-user:2147483647'] as $value) sp_ok('Supported bounded identity '.$value,provider_stackcp_identity($value)===$value);
foreach (['verified','ready','enabled',true,null] as $i=>$value) {$bad=$assignment;$bad['state']=$value;sp_deny('Assignment never accepts login-ready state '.$i,fn()=>provider_stackcp_document('assignment',$bad));}
$revoked=$assignment;$revoked['state']='revoked';sp_ok('Explicit revoked state remains distinct from unverified',provider_stackcp_document('assignment',$revoked)['state']==='revoked');
foreach ([1,0,'true',null] as $i=>$value) {$bad=$auth;$bad['enabled']=$value;sp_deny('Auth enabled is strict boolean '.$i,fn()=>provider_stackcp_document('auth',$bad));}
$keyCalls=0;$encryptCalls=0;$decryptCalls=0;$key=str_repeat('K',32);$secret='AUTH_PRIVATE_FIXTURE_KEY';
$vault=new ProviderStackCpAuthVault(function()use(&$keyCalls,$key){++$keyCalls;return $key;},function($plain,$master,$iv,$aad)use(&$encryptCalls,$key){++$encryptCalls;if($master!==$key||strlen($iv)!==12)throw new LogicException('Wrong boundary');return [$plain,str_repeat('T',16)];},function($cipher,$master,$iv,$tag,$aad)use(&$decryptCalls){++$decryptCalls;return $cipher;});
$context=ProviderStackCpAuthVault::context('test_',str_repeat('b',32),str_repeat('a',32),1);$encoded=$vault->seal($context,$secret);sp_ok('Narrow vault uses exactly one master and encrypt boundary',$keyCalls===1&&$encryptCalls===1&&$decryptCalls===0);
$proof=$vault->proof('test_|auth',provider_stackcp_json($auth));sp_ok('HMAC verification does not decrypt credentials',$keyCalls===1&&$decryptCalls===0&&strlen($proof)===64);
sp_ok('Purpose and canonical bytes both separate history proof',$proof!==$vault->proof('test_|assignment',provider_stackcp_json($auth))&&$proof!==$vault->proof('test_|auth',provider_stackcp_json($auth).' '));
sp_ok('Explicit internal open is observable by the injected counter',$vault->open($context,$encoded)===$secret&&$decryptCalls===1);$decryptCalls=0;
ob_start();var_dump($vault);$debug=ob_get_clean();sp_ok('Vault debug hides master and credential closures',!str_contains($debug,$key)&&!str_contains($debug,$secret));sp_deny('Vault cannot serialize key-bearing closures',fn()=>serialize($vault),LogicException::class);sp_deny('Vault cannot be cloned',fn()=>clone $vault,Error::class);
foreach (['',str_repeat('x',4097),"bad\nkey","bad\0key","\xff",' ','  ',' leading','trailing ','embedded space',"tab\tkey"] as $i=>$value) sp_deny('Client key rejects unsupported bytes '.$i,fn()=>ProviderStackCpAuthVault::keyValue($value));
sp_ok('Client key preserves accepted symbols without trimming',ProviderStackCpAuthVault::keyValue('+/raw-key=')==='+/raw-key=');
foreach (['', '../bad','x',str_repeat('x',21)] as $i=>$prefix) sp_deny('Context rejects installation prefix '.$i,fn()=>ProviderStackCpAuthVault::context($prefix,str_repeat('b',32),str_repeat('a',32),1));
$contexts=[ProviderStackCpAuthVault::context('test2_',str_repeat('b',32),str_repeat('a',32),1),ProviderStackCpAuthVault::context('test_',str_repeat('c',32),str_repeat('a',32),1),ProviderStackCpAuthVault::context('test_',str_repeat('b',32),str_repeat('c',32),1),ProviderStackCpAuthVault::context('test_',str_repeat('b',32),str_repeat('a',32),2)];
sp_ok('AAD separates installation profile record and revision',count(array_unique([$context,...$contexts]))===5&&!str_starts_with($context,'cwp-provider-v1|'));
foreach (['','*',base64_encode(str_repeat('x',28)),base64_encode(str_repeat('x',30))."\n"] as $i=>$wire) sp_deny('Invalid sealed value refuses before decrypt '.$i,fn()=>$vault->open($context,$wire),RuntimeException::class);
sp_ok('All malformed envelopes are refused before decrypt',$decryptCalls===0);
$badVault=new ProviderStackCpAuthVault(fn()=>'short');sp_deny('Wrong master length never signs history',fn()=>$badVault->proof('auth','{}'),RuntimeException::class);
$passed=!array_filter($checks,fn($c)=>!$c['passed']);$out=['passed'=>$passed,'scope'=>'Pure documents and injected credential boundary; no database, provider call or real encryption acceptance.','checks'=>$checks];$path=$argv[1]??__DIR__.'/../qa/stackcp-prerequisites-contract-results.json';if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo ($passed?'PASS':'FAIL').': '.count($checks).' StackCP prerequisite pure checks'.PHP_EOL;exit($passed?0:1);
