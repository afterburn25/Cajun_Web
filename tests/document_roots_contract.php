<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
foreach (['HttpTransport','TransportRequest','TransportResponse','TransportException','ProviderCredential'] as $class) require_once __DIR__.'/../foundation/provider/'.$class.'.php';
require_once __DIR__.'/../foundation/twentyi-package-php/PackagePhpVersionBinding.php';
foreach (['DocumentRootsBinding','DocumentRootsObservation','DocumentRootsReader'] as $class) require_once __DIR__.'/../foundation/twentyi-document-roots/'.$class.'.php';
use CajunWebPro\Foundation\Provider\{HttpTransport,TransportRequest,TransportResponse,TransportException,ProviderCredential};
use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;
use CajunWebPro\Foundation\TwentyIDocumentRoots\{DocumentRootsBinding,DocumentRootsObservation,DocumentRootsReader};

$checks=[];
function roots_ok(string $name, bool $passed): void { global $checks; $checks[]=['name'=>$name,'passed'=>$passed]; }
function roots_deny(string $name, callable $call): void {
    try { $call(); roots_ok($name,false); }
    catch (InvalidArgumentException $e) { roots_ok($name,!str_contains($e->getMessage(),'PRIVATE_MARKER')); }
}
function roots_restored(string $class, array $values): object {
    $object=(new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach ($values as $key=>$value) (new ReflectionProperty($class,$key))->setValue($object,$value);
    return $object;
}
final class RootsFixtureTransport implements HttpTransport {
    public array $requests=[];
    public function __construct(public string $body, public int $status=200, public ?Throwable $failure=null) {}
    public function send(TransportRequest $request): TransportResponse {
        $this->requests[]=$request;
        if ($this->failure) throw $this->failure;
        return new TransportResponse($this->status,['X-Private'=>'PRIVATE_MARKER'],$this->body);
    }
}
$packageData=['profile_id'=>str_repeat('1',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('2',64),'credential_sha256'=>str_repeat('3',64),
    'package_id'=>701,'client_id'=>9,'link_id'=>str_repeat('4',32),'link_sha256'=>str_repeat('5',64),'event_id'=>10,'event_sha256'=>str_repeat('6',64),
    'audit_id'=>11,'audit_sha256'=>str_repeat('7',64),'source_id'=>str_repeat('8',32),'source_seq'=>12,'source_sha256'=>str_repeat('9',64)];
$package=new PackagePhpVersionBinding($packageData);$binding=new DocumentRootsBinding($package);$secret='SYNTHETIC_ROOTS_PRIVATE_MARKER_KEY';
function roots_read(string $body,int $status=200,?string $key=null,?Throwable $failure=null): DocumentRootsObservation {
    global $binding,$secret;
    return DocumentRootsReader::read(new RootsFixtureTransport($body,$status,$failure),new ProviderCredential('twenty-i',$binding->data['package']['profile_id'],fn()=>$key??$secret),$binding,1760000000);
}
function roots_unavailable(string $name,string $body,string $reason,int $status=200,?string $key=null,?Throwable $failure=null): void {
    $r=roots_read($body,$status,$key,$failure);
    roots_ok($name,$r->state==='unavailable'&&$r->reason===$reason&&$r->entries===[]&&!str_contains(json_encode($r),'PRIVATE_MARKER'));
}
set_error_handler(static function(int $level,string $message):never { throw new ErrorException($message,0,$level); });
try {
    $transport=new RootsFixtureTransport('{"site.synthetic.invalid":"public_html","alias.synthetic.invalid":"folder/é exact"}');$resolved=0;
    $credential=new ProviderCredential('twenty-i',$packageData['profile_id'],function()use(&$resolved,$secret):string{$resolved++;return $secret;});
    roots_ok('Constructing types makes no automatic provider call',$transport->requests===[]&&$resolved===0);
    $r=DocumentRootsReader::read($transport,$credential,$binding,1760000000);$request=$transport->requests[0];
    roots_ok('One explicit read resolves credential and sends once',$resolved===1&&count($transport->requests)===1);
    roots_ok('Fixed package GET has no caller URL or domain identifier',$request->method==='GET'&&$request->url==='https://api.20i.com/package/701/web/documentRoots'&&$request->body===null);
    roots_ok('Fixed authentication and JSON headers with bounded timeout',$request->headers===['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json']&&$request->timeoutSeconds===15.0);
    roots_ok('Direct map becomes exact ordered observations',$r->state==='complete'&&$r->reason===''&&$r->entries===[['name'=>'site.synthetic.invalid','path'=>'public_html'],['name'=>'alias.synthetic.invalid','path'=>'folder/é exact']]);
    roots_ok('Observation retains exact operation scope and time',$r->scope===$binding->scope&&$r->observedAt===1760000000&&$r->binding->sha256===$binding->sha256);
    roots_ok('No response headers or credential enter observation',!str_contains(json_encode($r),'PRIVATE_MARKER')&&!str_contains(json_encode($r),base64_encode($secret)));
    roots_ok('Operation binding does not inherit PHP authority',$binding->data['operation']==='package.document-roots'&&$binding->scope!==$package->scope&&!str_contains($binding->scope,'package.php-version'));
    roots_ok('Binding canonical document and digest agree',json_decode($binding->json,true)===$binding->data&&hash('sha256',$binding->json)===$binding->sha256);
    foreach (['{}'," { \r\n\t } "] as $i=>$body) {$empty=roots_read($body);roots_ok('Validated empty object remains explicit empty map '.$i,$empty->state==='complete'&&$empty->entries===[]&&$empty->reason==='');}
    foreach (['[]'," [ \r\n\t ] "] as $i=>$body) roots_unavailable('Documented empty-array error is unavailable '.$i,$body,'provider_empty_array');
    foreach (['null','false','0','"text"','["public_html"]','{"site.invalid":null}','{"site.invalid":1}','{"site.invalid":true}','{"site.invalid":[]}','{"site.invalid":{}}','{"records":{"site.invalid":"path"}}'] as $i=>$body) roots_unavailable('Unsupported envelope or value type '.$i,$body,'shape_unsupported');
    foreach (['','{','{"site.invalid":"path",}','{"site.invalid":"path"} trailing','{"site.invalid":"bad\q"}',"{\"site.invalid\":\"bad\x00text\"}","{\"site.invalid\":\"bad\xff\"}",' {"site.invalid":"\uD800"}'] as $i=>$body) roots_unavailable('Malformed JSON is unavailable '.$i,$body,'malformed_body');
    foreach (['{"site.invalid":"first","site.invalid":"second"}','{"site.invalid":"first","\u0073ite.invalid":"second"}','{"site.invalid":"same","site.invalid":"same"}'] as $i=>$body) roots_unavailable('Duplicate decoded name refused before map collapse '.$i,$body,'duplicate_name');
    $escaped=roots_read('{"\u0073ite.invalid":'.json_encode('folder/one\\two "quoted"').'}');
    roots_ok('JSON escaped keys and values retain decoded exact text',$escaped->entries===[['name'=>'site.invalid','path'=>'folder/one\\two "quoted"']]);
    foreach (['','  directory  ','../outside','/absolute/observed','C:\\observed\\directory','folder/../other','$(untrusted text)','<script>text only</script>','é/目录'] as $i=>$path) {
        $observed=roots_read(json_encode(['site.invalid'=>$path],JSON_THROW_ON_ERROR));
        roots_ok('Path is preserved as opaque observed text '.$i,$observed->state==='complete'&&$observed->entries===[['name'=>'site.invalid','path'=>$path]]);
    }
    foreach (['UPPER.invalid','single','192.0.2.1','site.invalid.','*.site.invalid','a..invalid','-a.invalid','a-.invalid','café.invalid',str_repeat('a',64).'.invalid','site/escape.invalid','site_invalid.test'] as $i=>$name) roots_unavailable('Local DNS-name subset refusal '.$i,json_encode([$name=>'path'],JSON_THROW_ON_ERROR),'entry_unsupported');
    foreach (["bad\npath","bad\tpath","bad\x7fpath",str_repeat('a',1025)] as $i=>$path) roots_unavailable('Bounded observed path refusal '.$i,json_encode(['site.invalid'=>$path],JSON_THROW_ON_ERROR),'entry_unsupported');
    roots_ok('Exact path byte boundary accepted',roots_read(json_encode(['site.invalid'=>str_repeat('a',1024)]))->entries[0]['path']===str_repeat('a',1024));
    roots_unavailable('One invalid row refuses entire map','{"good.invalid":"safe","bad.invalid":false}','shape_unsupported');
    $map=[];for($i=0;$i<256;$i++)$map['n'.$i.'.invalid']='p';$many=roots_read(json_encode($map));roots_ok('Exact entry boundary accepted',count($many->entries)===256&&$many->state==='complete');$map['n256.invalid']='p';roots_unavailable('Entry overflow refuses entire map',json_encode($map),'body_limit');
    roots_ok('Exact body byte boundary accepted',roots_read('{}'.str_repeat(' ',65534))->state==='complete');roots_unavailable('Body byte overflow refused before parsing','{}'.str_repeat(' ',65535),'body_limit');
    foreach ([401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'upstream_unavailable',503=>'upstream_unavailable',201=>'http_status',204=>'http_status',301=>'http_status',404=>'http_status'] as $status=>$reason) roots_unavailable('Finite HTTP reason '.$status,'PRIVATE_MARKER body',$reason,$status);
    foreach (['connect','timeout','tls','protocol','unavailable','unknown','PRIVATE_MARKER'] as $kind) roots_unavailable('Finite transport reason '.$kind,'{}','transport_'.($kind==='PRIVATE_MARKER'?'unknown':$kind),200,null,new TransportException($kind));
    roots_unavailable('Unknown transport exception does not expose text','{}','transport_unknown',200,null,new RuntimeException('PRIVATE_MARKER internal transport detail'));
    foreach ([['secret.invalid','path','secret'],['site.invalid','prefix secret suffix','secret'],['YWJj.invalid','path','abc'],['site.invalid','prefixYWJjsuffix','abc']] as $i=>[$name,$path,$key]) roots_unavailable('Credential echo refused in decoded key or value '.$i,json_encode([$name=>$path]),'credential_echo',200,$key);
    roots_unavailable('JSON escaped raw credential is refused','{"site.invalid":"\u0073ecret"}','credential_echo',200,'secret');
    roots_unavailable('JSON escaped Base64 credential is refused','{"site.invalid":"\u0059WJj"}','credential_echo',200,'abc');
    roots_unavailable('Later echoed row discards earlier safe rows','{"safe.invalid":"safe","second.invalid":"secret"}','credential_echo',200,'secret');
    foreach (['',"bad\nkey",str_repeat('a',4097)] as $i=>$key) {$t=new RootsFixtureTransport('{}');roots_deny('Invalid credential refused '.$i,fn()=>DocumentRootsReader::read($t,new ProviderCredential('twenty-i',$packageData['profile_id'],fn()=>$key),$binding,1));roots_ok('Invalid credential never reaches transport '.$i,$t->requests===[]);}
    $t=new RootsFixtureTransport('{}');roots_deny('Throwing secret resolver has finite exception',fn()=>DocumentRootsReader::read($t,new ProviderCredential('twenty-i',$packageData['profile_id'],fn()=>throw new RuntimeException('PRIVATE_MARKER')),$binding,1));roots_ok('Throwing resolver never reaches transport',$t->requests===[]);
    foreach ([['other',$packageData['profile_id'],1],['twenty-i',str_repeat('a',32),1],['twenty-i',$packageData['profile_id'],-1]] as $i=>[$provider,$account,$at]) {$count=0;$t=new RootsFixtureTransport('{}');$c=new ProviderCredential($provider,$account,function()use(&$count):string{$count++;return 'secret';});roots_deny('Wrong account or time refused '.$i,fn()=>DocumentRootsReader::read($t,$c,$binding,$at));roots_ok('Wrong context precedes secret and transport '.$i,$count===0&&$t->requests===[]);}
    foreach ($packageData as $key=>$value) {$changed=$packageData;$changed[$key]=is_int($value)?$value+1:str_repeat('a',strlen($value));$other=new DocumentRootsBinding(new PackagePhpVersionBinding($changed));roots_ok('Every assignment witness changes document-roots binding '.$key,$other->sha256!==$binding->sha256&&$other->scope!==$binding->scope);}
    foreach (['json','sha256','scope'] as $field) {$values=['data'=>$package->data,'json'=>$package->json,'sha256'=>$package->sha256,'scope'=>$package->scope];$values[$field].='PRIVATE_MARKER';$bad=roots_restored(PackagePhpVersionBinding::class,$values);roots_deny('Forged package derived field refused '.$field,fn()=>new DocumentRootsBinding($bad));}
    foreach (['json','sha256','scope','operation'] as $field) {$values=['data'=>$binding->data,'json'=>$binding->json,'sha256'=>$binding->sha256,'scope'=>$binding->scope];if($field==='operation')$values['data']['operation']='package.php-version';else$values[$field].='PRIVATE_MARKER';$bad=roots_restored(DocumentRootsBinding::class,$values);$count=0;$t=new RootsFixtureTransport('{}');$c=new ProviderCredential('twenty-i',$packageData['profile_id'],function()use(&$count):string{$count++;return 'secret';});roots_deny('Forged document-roots derived field refused '.$field,fn()=>DocumentRootsReader::read($t,$c,$bad,1));roots_ok('Forged binding precedes secret and transport '.$field,$count===0&&$t->requests===[]);}
    $externalRevision=1;$referenced=$packageData;$referenced['profile_revision']=&$externalRevision;$referencePackage=new PackagePhpVersionBinding($referenced);$referenceBinding=new DocumentRootsBinding($referencePackage);$externalRevision=2;
    roots_ok('Binding detaches referenced package scalar',$referenceBinding->data['package']['profile_revision']===1&&json_decode($referenceBinding->json,true)===$referenceBinding->data);roots_deny('Later mutated package witness refuses reconstruction',fn()=>new DocumentRootsBinding($referencePackage));
    $path='original';$entries=[['name'=>'site.invalid','path'=>&$path]];$snapshot=new DocumentRootsObservation($binding,'complete',$entries,'',1);$path='changed';roots_ok('Observation detaches referenced path scalar',$snapshot->entries[0]['path']==='original');
    foreach ([['unknown',[],'',1],['complete',[],'http_status',1],['unavailable',[],'PRIVATE_MARKER',1],['unavailable',[['name'=>'site.invalid','path'=>'p']],'http_status',1],['complete',[],'',-1],['complete',[['name'=>'site.invalid','path'=>[]]],'',1],['complete',[['name'=>'site.invalid','path'=>'p','extra'=>'x']],'',1]] as $i=>[$state,$rows,$reason,$at]) roots_deny('Impossible observation shape refused '.$i,fn()=>new DocumentRootsObservation($binding,$state,$rows,$reason,$at));
    roots_deny('Duplicate observation rows refused',fn()=>new DocumentRootsObservation($binding,'complete',[['name'=>'site.invalid','path'=>'a'],['name'=>'site.invalid','path'=>'b']],'',1));
    roots_ok('Observation exposes no filesystem or mutation methods',!method_exists(DocumentRootsObservation::class,'resolve')&&!method_exists(DocumentRootsObservation::class,'write')&&!method_exists(DocumentRootsReader::class,'change'));
} catch (Throwable $e) { $checks[]=['name'=>'Unexpected fixture failure: '.$e->getMessage(),'passed'=>false]; }
finally { restore_error_handler(); }
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['passed'=>$passed,'scope'=>'Pure fixed-request and observed-text parser with synthetic transport only; no current authority, native storage, filesystem or live provider acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($passed?0:1);
