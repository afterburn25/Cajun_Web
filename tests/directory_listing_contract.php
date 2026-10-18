<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIDirectoryListing\{DirectoryListingBinding,DirectoryListingObservation,DirectoryListingReader};

foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException','ProviderAdapter'] as $class)require __DIR__.'/../foundation/provider/'.$class.'.php';
foreach(['DirectoryListingBinding','DirectoryListingObservation','DirectoryListingReader'] as $class)require __DIR__.'/../foundation/twentyi-directory-listing/'.$class.'.php';

$checks=[];
function ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)fwrite(STDERR,"FAIL $name\n");}
function rejects(string $name,callable $work):void{try{$work();ok($name,false);}catch(InvalidArgumentException){ok($name,true);}}
function facts():array{return ['profile_id'=>str_repeat('a',32),'profile_revision'=>2,'key_fingerprint'=>str_repeat('b',64),'credential_sha256'=>str_repeat('3',64),'package_id'=>123,'client_id'=>4,'link_id'=>str_repeat('c',32),'link_sha256'=>str_repeat('d',64),'event_id'=>5,'event_sha256'=>str_repeat('e',64),'audit_id'=>6,'audit_sha256'=>str_repeat('f',64),'source_id'=>str_repeat('1',32),'source_seq'=>7,'source_sha256'=>str_repeat('2',64)];}
function binding():DirectoryListingBinding{return new DirectoryListingBinding(facts());}
function project(string $body,int $status=200):DirectoryListingObservation{return DirectoryListingReader::project(new TransportResponse($status,[],$body),binding(),1700000000);}
/** Simulate restoration bypassing constructors, then run the public validators. */
function restored(object $original,array $overrides=[],?string $missing=null,bool $roundtrip=true):object{
    $class=new ReflectionClass($original);$out=$class->newInstanceWithoutConstructor();
    foreach(array_replace(get_object_vars($original),$overrides) as $key=>$value)if($key!==$missing)$class->getProperty($key)->setValue($out,$value);
    return $roundtrip?unserialize(serialize($out),['allowed_classes'=>[DirectoryListingBinding::class,DirectoryListingObservation::class]]):$out;
}
/** Exact review reproduction: restored readonly object can contain referenced array elements. */
function referenced_binding(int &$external):DirectoryListingBinding{
    $original=binding();$data=facts();$data['package_id']=&$external;
    $class=new ReflectionClass($original);$out=$class->newInstanceWithoutConstructor();
    foreach(array_replace(get_object_vars($original),['data'=>$data]) as $key=>$value)$class->getProperty($key)->setValue($out,$value);
    return $out;
}
final class DirectoryListingTransport implements HttpTransport{
    public array $sent=[];
    public function __construct(private TransportResponse|Throwable $response){}
    public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;if($this->response instanceof Throwable)throw $this->response;return $this->response;}
}

$binding=binding();$binding->validate();
ok('Canonical binding is independent of input key order',(new DirectoryListingBinding(array_reverse(facts(),true)))->json===$binding->json);
ok('Distinct Directory listing operation and scope',DirectoryListingReader::OPERATION==='package.directory-indexing'&&$binding->scope==='twenty-i|'.str_repeat('a',32).'|package.directory-indexing|'.hash('sha256',$binding->json));
$data=facts();$package=123;$profile=str_repeat('a',32);$data['package_id']=&$package;$data['profile_id']=&$profile;$detached=new DirectoryListingBinding($data);$package=999;$profile=str_repeat('9',32);
ok('Binding detaches referenced caller integers and strings',$detached->data['package_id']===123&&$detached->data['profile_id']===str_repeat('a',32)&&$detached->json===$binding->json);$detached->validate();
foreach(facts() as $key=>$value){$data=facts();$data[$key]=is_int($value)?$value+1:str_repeat($value[0]==='9'?'8':'9',strlen($value));ok('Binding hash covers '.$key,(new DirectoryListingBinding($data))->sha256!==$binding->sha256);}
$data=facts();$data['permission']='php-sharing';rejects('PHP sharing and unexpected binding fields are not inherited',fn()=>new DirectoryListingBinding($data));
foreach(array_keys(facts()) as $key){$data=facts();unset($data[$key]);rejects('Missing binding field refused '.$key,fn()=>new DirectoryListingBinding($data));}
foreach([0,-1,true,1.5,'123','1/../2','1?x=1',1000000000000000000] as $i=>$bad){$data=facts();$data['package_id']=$bad;rejects('Noncanonical allocated package refused '.$i,fn()=>new DirectoryListingBinding($data));}
foreach(['profile_revision','client_id','event_id','audit_id','source_seq'] as $key){$data=facts();$data[$key]='1';rejects('Sequence coercion refused '.$key,fn()=>new DirectoryListingBinding($data));}
foreach(['profile_id','link_id','source_id','key_fingerprint','credential_sha256','link_sha256','event_sha256','audit_sha256','source_sha256'] as $key){$data=facts();$data[$key]=str_repeat('A',strlen($data[$key]));rejects('Noncanonical witness refused '.$key,fn()=>new DirectoryListingBinding($data));}
$descriptor=DirectoryListingReader::request($binding);
ok('Fixed bodyless GET descriptor only',$descriptor->method==='GET'&&$descriptor->url==='https://api.20i.com/package/123/web/directoryIndexing'&&$descriptor->headers===['Accept'=>'application/json']&&$descriptor->body===null&&$descriptor->timeoutSeconds===15.0);
$roundtrip=unserialize(serialize($binding));$roundtrip->validate();ok('Exact restored binding remains usable',DirectoryListingReader::request($roundtrip)->url===$descriptor->url);
$external=123;$referenced=referenced_binding($external);$referenced->validate();$snapshot=$referenced->validated();
$retained=new DirectoryListingObservation($referenced,1700000000,'complete','',false);
$projected=DirectoryListingReader::project(new TransportResponse(200,[],'false'),$referenced,1700000000);
$external=456;
ok('Exact restored reference reproduction mutates original object',$referenced->data['package_id']===456&&json_decode($referenced->json,true)['package_id']===123);
ok('Validated binding snapshot detaches restored reference',$snapshot->data['package_id']===123&&$snapshot->json===$binding->json&&$snapshot->scope===$binding->scope);
ok('Constructed observation detaches restored binding reference',$retained->binding->data['package_id']===123&&$retained->binding->json===$binding->json&&$retained->scope===$binding->scope);
ok('Projected observation detaches restored binding reference',$projected->binding->data['package_id']===123&&$projected->value===false&&$projected->scope===$binding->scope);
$external=123;$referenced=referenced_binding($external);$restoredObservation=restored($retained,['binding'=>$referenced],null,false);
$detachedObservation=$restoredObservation->validated();$external=456;
ok('Validated observation snapshot remains detached after restoration',$restoredObservation->binding->data['package_id']===456&&$detachedObservation->binding->data['package_id']===123&&$detachedObservation->value===false&&$detachedObservation->scope===$binding->scope);
foreach(['json'=>'{}','sha256'=>str_repeat('0',64),'scope'=>'twenty-i|other|package.php-version|wrong','data'=>array_replace(facts(),['package_id'=>124])] as $field=>$value){$bad=restored($binding,[$field=>$value]);rejects('Restored inconsistent binding field refused '.$field,fn()=>DirectoryListingReader::request($bad));}
foreach(['data','json','sha256','scope'] as $key){$bad=restored($binding,[], $key);rejects('Restored incomplete binding refused '.$key,fn()=>DirectoryListingReader::request($bad));}

foreach(['true'=>true,'false'=>false," \ntrue\r\t"=>true,"\t false "=>false] as $body=>$expected){$r=project($body);$r->validate();ok('Boolean preserved without coercion '.json_encode($body),$r->state==='complete'&&$r->value===$expected&&$r->reason===''&&$r->scope===$binding->scope&&$r->observedAt===1700000000);}
$false=project('false');$null=project('null');ok('Reported false is distinct from unavailable null',$false->value===false&&$false->state==='complete'&&$null->value===null&&$null->state==='unavailable'&&$null->reason==='value_null');
foreach(['"true"','"false"','"0"','"1"','0','1','0.0','1.0','[]','[true]','{"value":true}','{"directoryIndexing":false}','{"result":true}','""'] as $i=>$body){$r=project($body);ok('Unexpected scalar or envelope refused '.$i,$r->state==='unavailable'&&$r->reason==='unexpected_shape'&&$r->value===null);}
foreach(['','true false','True','FALSE','{','true trailing',"\xff","\xef\xbb\xbftrue",'[[[[[true]]]]]'] as $i=>$body){$r=project($body);ok('Malformed body refused '.$i,$r->reason==='malformed_body'&&$r->value===null);}
ok('Exact defensive body boundary accepted',project(str_repeat(' ',DirectoryListingReader::MAX_BODY_BYTES-4).'true')->value===true);
ok('Excessive body refused',project(str_repeat(' ',DirectoryListingReader::MAX_BODY_BYTES-3).'true')->reason==='response_limit');
foreach([201=>'unexpected_status',204=>'unexpected_status',301=>'unexpected_status',302=>'unexpected_status',400=>'unexpected_status',401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error',503=>'remote_error'] as $status=>$reason){$r=project('true',$status);ok('Status refuses boolean '.$status,$r->state==='unavailable'&&$r->value===null&&$r->reason===$reason);}
foreach([null,'true','false',0,1,[],new stdClass()] as $i=>$value)rejects('Complete constructor requires actual bool '.$i,fn()=>new DirectoryListingObservation($binding,0,'complete','',$value));
rejects('Complete observation cannot carry an error',fn()=>new DirectoryListingObservation($binding,0,'complete','unexpected_shape',false));
rejects('Unavailable observation cannot carry false',fn()=>new DirectoryListingObservation($binding,0,'unavailable','value_null',false));
rejects('Unrecognized bounded reason refused',fn()=>new DirectoryListingObservation($binding,0,'unavailable','raw response',null));
rejects('Partial is not a boolean observation',fn()=>new DirectoryListingObservation($binding,0,'partial','',null));
rejects('Negative observation date refused',fn()=>DirectoryListingReader::project(new TransportResponse(200,[],'true'),$binding,-1));
$good=project('false');$bad=restored($good,['scope'=>'wrong']);rejects('Restored observation scope revalidated',fn()=>$bad->validate());
$bad=restored($good,['state'=>'unavailable']);rejects('Restored false cannot become unavailable value',fn()=>$bad->validate());
$bad=restored($good,[],'value');rejects('Restored observation missing nullable field refused',fn()=>$bad->validate());
$badBinding=restored($binding,['scope'=>'wrong']);$bad=restored($good,['binding'=>$badBinding]);rejects('Restored nested binding revalidated',fn()=>$bad->validate());

$resolved=0;$credential=new ProviderCredential('twenty-i',str_repeat('a',32),function()use(&$resolved):string{$resolved++;return 'synthetic-directory-listing-key';});
$transport=new DirectoryListingTransport(new TransportResponse(200,[],'false'));$r=DirectoryListingReader::read($transport,$credential,$binding,1700000000);
ok('One authenticated request preserves false',$resolved===1&&count($transport->sent)===1&&$r->state==='complete'&&$r->value===false&&$transport->sent[0]->headers===['Authorization'=>'Bearer '.base64_encode('synthetic-directory-listing-key'),'Accept'=>'application/json']&&$transport->sent[0]->body===null);
ok('Observation excludes raw body headers and credential',!property_exists($r,'body')&&!property_exists($r,'headers')&&!str_contains(json_encode($r),'synthetic-directory-listing-key')&&!str_contains(json_encode($r),base64_encode('synthetic-directory-listing-key')));
foreach([['other-provider',str_repeat('a',32)],['twenty-i',str_repeat('9',32)]] as $i=>[$provider,$account]){$foreign=new ProviderCredential($provider,$account,function()use(&$resolved):string{$resolved++;return 'foreign';});rejects('Foreign identity refused before credential '.$i,fn()=>DirectoryListingReader::read($transport,$foreign,$binding,0));}
rejects('Negative read date refused before credential',fn()=>DirectoryListingReader::read($transport,$credential,$binding,-1));
rejects('Restored binding refused before credential',fn()=>DirectoryListingReader::read($transport,$credential,$badBinding,0));
ok('Rejected reads consume no credential or request',$resolved===1&&count($transport->sent)===1);
$external=123;$referenced=referenced_binding($external);
$mutatingCredential=new ProviderCredential('twenty-i',str_repeat('a',32),function()use(&$external):string{$external=456;return 'synthetic-directory-listing-key';});
$t=new DirectoryListingTransport(new TransportResponse(200,[],'false'));$r=DirectoryListingReader::read($t,$mutatingCredential,$referenced,0);
ok('Credential callback cannot mutate snapshotted request binding',$external===456&&count($t->sent)===1&&$t->sent[0]->url===$descriptor->url&&$r->binding->data['package_id']===123&&$r->binding->json===$binding->json&&$r->scope===$binding->scope);
$external=123;$referenced=referenced_binding($external);
$t=new class($external) implements HttpTransport {
    public array $sent=[];
    public function __construct(private mixed &$external){}
    public function send(TransportRequest $request):TransportResponse{$this->sent[]=$request;$this->external=456;return new TransportResponse(200,[],'true');}
};
$r=DirectoryListingReader::read($t,$credential,$referenced,0);$external=789;
ok('Transport callback and later reference mutation cannot alter result binding',count($t->sent)===1&&$t->sent[0]->url===$descriptor->url&&$r->value===true&&$r->binding->data['package_id']===123&&$r->binding->json===$binding->json&&$r->scope===$binding->scope);
foreach(['',str_repeat('x',4097),"key\n",'key'.chr(0)] as $i=>$secret){$invalid=new ProviderCredential('twenty-i',str_repeat('a',32),fn():string=>$secret);$t=new DirectoryListingTransport(new TransportResponse(200,[],'true'));rejects('Invalid credential refused '.$i,fn()=>DirectoryListingReader::read($t,$invalid,$binding,0));ok('Invalid credential does not send '.$i,$t->sent===[]);}
foreach(['true','false',base64_decode('true',true)] as $i=>$secret){$token=$i===1?'false':'true';$echoCredential=new ProviderCredential('twenty-i',str_repeat('a',32),fn():string=>$secret);$t=new DirectoryListingTransport(new TransportResponse(200,[],$token));$r=DirectoryListingReader::read($t,$echoCredential,$binding,0);ok('Canonical raw or Base64 credential echo refused '.$i,count($t->sent)===1&&$r->state==='unavailable'&&$r->value===null&&$r->reason==='invalid_value');}
foreach(['connect','tls','timeout','protocol','unavailable','unknown','private unexpected transport text'] as $i=>$kind){$t=new DirectoryListingTransport(new TransportException($kind));$r=DirectoryListingReader::read($t,$credential,$binding,0);ok('Finite transport outcome without retry '.$i,count($t->sent)===1&&$r->value===null&&$r->reason==='transport_'.($i===6?'unknown':$kind));}
$t=new DirectoryListingTransport(new RuntimeException('synthetic-directory-listing-key'));$r=DirectoryListingReader::read($t,$credential,$binding,0);ok('Unknown transport exception is redacted',$r->reason==='transport_unknown'&&!str_contains(json_encode($r),'synthetic-directory-listing-key')&&count($t->sent)===1);
$throws=new ProviderCredential('twenty-i',str_repeat('a',32),fn():string=>throw new RuntimeException('private-key-detail'));
try{DirectoryListingReader::read($t,$throws,$binding,0);ok('Credential resolver reason redacted',false);}catch(InvalidArgumentException $e){ok('Credential resolver reason redacted',$e->getMessage()==='Provider credentials are unavailable.'&&$e->getPrevious()===null);}

$labels=array_column($checks,'name');ok('All recorded check labels are unique',count($labels)===count(array_unique($labels)));
$failed=count(array_filter($checks,fn($r)=>!$r['passed']));echo json_encode(['passed'=>$failed===0,'scope'=>'Pure supplied binding, strict reported boolean and synthetic injected transport; no application dispatch, database authority, sharing, visitor exposure or live provider evidence.','checks'=>$checks],JSON_PRETTY_PRINT),"\n";fwrite(STDERR,count($checks)." checks; $failed failed\n");exit($failed?1:0);
