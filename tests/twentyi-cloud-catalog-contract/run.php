<?php
declare(strict_types=1);
use CajunWebPro\Foundation\TwentyICloudCatalog\{CloudProviderCatalogBinding, CloudProviderCatalogRequest};
require __DIR__.'/../../foundation/twentyi-cloud-catalog/CloudProviderCatalogRequest.php';
$checks=[];
function ok(string $name, bool $passed):void { global $checks; $checks[]=['name'=>$name,'passed'=>$passed]; if(!$passed)fwrite(STDERR,"FAIL $name\n"); }
function rejects(string $name, callable $work):void { try{$work();ok($name,false);}catch(InvalidArgumentException){ok($name,true);} }
function identifiers():array { return ['cloud.Exact-1','123','Cloud.Exact-1']; }
function context():array { return ['profile_id'=>str_repeat('a',32),'profile_revision'=>2,
    'key_fingerprint'=>str_repeat('b',64),'credential_sha256'=>str_repeat('c',64),
    'source_id'=>str_repeat('d',32),'source_seq'=>7,'source_sha256'=>str_repeat('e',64),'source_proof'=>str_repeat('f',64),
    'operation'=>'cloud.providers.list','scope'=>'twenty-i|'.str_repeat('a',32).'|cloud.providers.list',
    'state'=>'complete','coverage'=>'unknown','skipped'=>0,'reason'=>'','started_epoch'=>1700000000,'observed_epoch'=>1700000001,
    'latest_id'=>str_repeat('d',32),'identifiers_sha256'=>hash('sha256',json_encode(identifiers(),JSON_THROW_ON_ERROR))]; }
function make(?array $data=null, ?array $ids=null, string $selected='cloud.Exact-1', int $now=1700000002):CloudProviderCatalogBinding {
    return CloudProviderCatalogBinding::fromSavedList($data??context(),$ids??identifiers(),$selected,$now);
}
$binding=make();$request=CloudProviderCatalogRequest::forBinding($binding,$binding->sha256,1700000002);
ok('Only documented lookup method and fixed self-reseller URL',$request->method==='POST'&&$request->url==='https://api.20i.com/reseller/*/cloudProviders');
ok('Only provider string appears in exact JSON body',$request->body==='{"provider":"cloud.Exact-1"}'&&array_keys(json_decode($request->body,true))===['provider']);
ok('No credential or arbitrary headers in description',$request->headers===['Accept'=>'application/json','Content-Type'=>'application/json']);
ok('Request retains exact reviewed binding hash',$request->bindingSha256===$binding->sha256);
ok('Canonical binding ignores input key order',make(array_reverse(context(),true))->json===$binding->json);
ok('Canonical request hash covers complete supplied list and provider',$binding->sha256===hash('sha256',$binding->json)&&$binding->data['identifiers']===identifiers()&&$binding->data['provider']==='cloud.Exact-1');
$data=context();$ids=identifiers();$detached=make($data,$ids);$data['source_seq']=99;$ids[0]='changed';
ok('Binding detaches caller arrays',$detached->data['source']['source_seq']===7&&$detached->data['identifiers'][0]==='cloud.Exact-1');
foreach(['123','Cloud.Exact-1'] as $selected){$b=make(selected:$selected);$r=CloudProviderCatalogRequest::forBinding($b,$b->sha256,1700000002);ok('Exact selected provider string '.$selected,json_decode($r->body,true)['provider']===$selected&&$b->sha256!==$binding->sha256);}
foreach(['CLOUD.Exact-1','cloud.exact-1','missing'] as $selected)rejects('Unobserved exact provider refused '.$selected,fn()=>make(selected:$selected));
foreach(['profile_revision'=>3,'key_fingerprint'=>str_repeat('1',64),'credential_sha256'=>str_repeat('2',64),'source_seq'=>8,'source_sha256'=>str_repeat('3',64),'source_proof'=>str_repeat('4',64),'started_epoch'=>1699999999,'observed_epoch'=>1700000002] as $key=>$value){$c=context();$c[$key]=$value;$changed=make($c);ok('Binding changes for '.$key,$changed->sha256!==$binding->sha256);rejects('Old reviewed request refuses changed '.$key,fn()=>CloudProviderCatalogRequest::forBinding($changed,$binding->sha256,1700000002));}
$c=context();$c['profile_id']=str_repeat('1',32);$c['scope']='twenty-i|'.$c['profile_id'].'|cloud.providers.list';$b=make($c);ok('Binding covers different profile and matching scope',$b->sha256!==$binding->sha256);
rejects('Old reviewed request refuses different profile',fn()=>CloudProviderCatalogRequest::forBinding($b,$binding->sha256,1700000002));
$c=context();$c['source_id']=$c['latest_id']=str_repeat('1',32);$b=make($c);ok('Binding covers replacement latest source',$b->sha256!==$binding->sha256);
rejects('Old reviewed request refuses replacement source',fn()=>CloudProviderCatalogRequest::forBinding($b,$binding->sha256,1700000002));
$ids=array_reverse(identifiers());$c=context();$c['identifiers_sha256']=hash('sha256',json_encode($ids));$b=make($c,$ids);ok('Binding covers exact list order and document hash',$b->sha256!==$binding->sha256);
rejects('Old reviewed request refuses changed list document',fn()=>CloudProviderCatalogRequest::forBinding($b,$binding->sha256,1700000002));
foreach(array_keys(context()) as $key){$c=context();unset($c[$key]);rejects('Missing source field '.$key,fn()=>make($c));}
foreach(['provider','url','reseller_id','spec','zone','configuration','price','extra'] as $key){$c=context();$c[$key]='unexpected';rejects('Unexpected source field '.$key,fn()=>make($c));}
foreach(['profile_id','source_id','latest_id'] as $key)foreach(['',str_repeat('a',31),str_repeat('A',32),123] as $i=>$bad){$c=context();$c[$key]=$bad;rejects('Invalid identity '.$key.' '.$i,fn()=>make($c));}
foreach(['key_fingerprint','credential_sha256','source_sha256','source_proof','identifiers_sha256'] as $key){$c=context();$c[$key]=str_repeat('z',64);rejects('Invalid witness '.$key,fn()=>make($c));}
foreach(['profile_revision','source_seq','started_epoch','observed_epoch'] as $key)foreach([0,-1,'2',true,2.5] as $i=>$bad){$c=context();$c[$key]=$bad;rejects('Invalid integer '.$key.' '.$i,fn()=>make($c));}
foreach(['state'=>'unavailable','coverage'=>'complete','skipped'=>1,'reason'=>'invalid_shape','operation'=>'packages.list','scope'=>'other','latest_id'=>str_repeat('9',32)] as $key=>$bad){$c=context();$c[$key]=$bad;rejects('Noncurrent incomplete or foreign source '.$key,fn()=>make($c));}
foreach(['unfinished','partial','refused','credential','access_changed'] as $state){$c=context();$c['state']=$state;rejects('Unsupported source state '.$state,fn()=>make($c));}
foreach([null,'0',false] as $i=>$bad){$c=context();$c['skipped']=$bad;rejects('Skipped count must be exact zero '.$i,fn()=>make($c));}
foreach([[],['cloud.Exact-1','cloud.Exact-1'],['cloud.Exact-1',123],['cloud.Exact-1','bad/path'],['cloud.Exact-1',' trailing '],['x'=>'cloud.Exact-1']] as $i=>$ids){$c=context();$c['identifiers_sha256']=hash('sha256',json_encode($ids));rejects('Unsupported or unselected provider list '.$i,fn()=>make($c,$ids));}
rejects('Changed list cannot reuse prior document hash',fn()=>make(ids:['cloud.Exact-1']));
foreach(['','../provider','white space',"bad\nvalue",str_repeat('a',129)] as $i=>$value)rejects('Unsupported selected identifier '.$i,fn()=>make(selected:$value));
ok('Exact local freshness lower boundary accepted',make(now:1700000900)->sha256===$binding->sha256);
rejects('Older observation refused at construction',fn()=>make(now:1700000901));
rejects('Time is rechecked before describing request',fn()=>CloudProviderCatalogRequest::forBinding($binding,$binding->sha256,1700000901));
$c=context();$c['started_epoch']=$c['observed_epoch']=1700000032;ok('Exact future tolerance accepted',make($c)->data['source']['observed_epoch']===1700000032);
$c['observed_epoch']=1700000033;rejects('Observation beyond future tolerance refused',fn()=>make($c));
$c['started_epoch']=$c['observed_epoch']=1700000033;rejects('Intent beyond future tolerance refused',fn()=>make($c));
$c=context();$c['observed_epoch']=1699999999;rejects('Observation before intent refused',fn()=>make($c));
foreach([0,-1,PHP_INT_MAX] as $i=>$now)rejects('Unsupported current clock '.$i,fn()=>make(now:$now));
foreach(['',str_repeat('A',64),str_repeat('9',64)] as $i=>$hash)rejects('Missing malformed or stale reviewed hash '.$i,fn()=>CloudProviderCatalogRequest::forBinding($binding,$hash,1700000002));
ok('Pure request exposes no send or response parser',!method_exists(CloudProviderCatalogRequest::class,'send')&&!method_exists(CloudProviderCatalogRequest::class,'project')&&!method_exists(CloudProviderCatalogRequest::class,'read'));
$reflection=new ReflectionClass(CloudProviderCatalogRequest::class);ok('Request constructor is private and object immutable',$reflection->isReadOnly()&&$reflection->getConstructor()->isPrivate());
$reflection=new ReflectionClass(CloudProviderCatalogBinding::class);ok('Binding constructor is private and object immutable',$reflection->isReadOnly()&&$reflection->getConstructor()->isPrivate());
$names=array_column($checks,'name');ok('Every contract check has a distinct name',count($names)===count(array_unique($names)));
$passed=!in_array(false,array_column($checks,'passed'),true);$report=['passed'=>$passed,'scope'=>'Pure unsent request and supplied-context contract; no current account authority, response schema or network acceptance.','checks'=>$checks];
$json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if(isset($argv[1]))file_put_contents($argv[1],$json);else echo $json;
exit($passed?0:1);
