<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIDomainContracts\DomainContractBinding;
use CajunWebPro\Foundation\TwentyIDomainExpiry\{DomainExpiry,DomainExpiryBinding,DomainExpiryReader};
foreach(['TransportRequest','TransportResponse','TransportException','HttpTransport','ProviderCredential','ProviderAdapter'] as $file)require __DIR__.'/../foundation/provider/'.$file.'.php';
require __DIR__.'/../foundation/twentyi-inventory/InventoryDomain.php';
require __DIR__.'/../foundation/twentyi-domain-contracts/DomainContractBinding.php';
foreach(['DomainExpiryBinding','DomainExpiryObservation','DomainExpiry','DomainExpiryReader'] as $file)require __DIR__.'/../foundation/twentyi-domain-expiry/'.$file.'.php';
require __DIR__.'/../foundation/mail-transport/EndpointPolicy.php';require __DIR__.'/../foundation/twentyi/TwentyIAdapter.php';require __DIR__.'/../foundation/twentyi/FixedHttpTransport.php';
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
$checks=[];
function er_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)fwrite(STDERR,'FAIL '.$name.PHP_EOL);}
function er_reject(string $name,callable $call):void {try{$call();er_ok($name,false);}catch(InvalidArgumentException){er_ok($name,true);}}
$registrar=new DomainContractBinding(['profile_id'=>str_repeat('a',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('b',64),'domain_id'=>701,'domain'=>'owned.synthetic.invalid','link_id'=>str_repeat('c',32),'link_revision'=>1,'client_id'=>3,'link_event_id'=>4,'link_event_sha256'=>str_repeat('d',64),'link_audit_id'=>5,'link_audit_sha256'=>str_repeat('e',64),'source_refresh_id'=>str_repeat('f',32),'source_seq'=>6,'source_sha256'=>str_repeat('0',64)]);
$binding=new DomainExpiryBinding($registrar,str_repeat('1',64));
er_ok('Sealed credential change produces new immutable scope',(new DomainExpiryBinding($registrar,str_repeat('2',64)))->scope!==$binding->scope);
foreach(['',str_repeat('A',64),str_repeat('a',63),str_repeat('a',65),'PRIVATE_MARKER'] as $i=>$digest)er_reject('Invalid sealed credential digest '.$i,fn()=>new DomainExpiryBinding($registrar,$digest));
final class ExpiryReaderTransport implements HttpTransport {
    public int $calls=0;public ?TransportRequest $request=null;
    public function __construct(private string $body,private ?string $failure=null){}
    public function send(TransportRequest $request):TransportResponse {$this->calls++;$this->request=$request;if($this->failure==='raw')throw new RuntimeException('PRIVATE_MARKER');if($this->failure!==null)throw new TransportException($this->failure);return new TransportResponse(200,['X-Private'=>'PRIVATE_MARKER'],$this->body);}
}
$url='https://api.20i.com/domain/owned.synthetic.invalid/upstreamExpiryDate';
er_ok('Exact canonical registrar expiry route admitted',DomainExpiryReader::acceptsUrl($url));
foreach([$url.'?x=1',$url.'#part',str_replace('owned.','OWNED.',$url),str_replace('owned.','owned%2e',$url),str_replace('owned.synthetic.invalid','701',$url),str_replace('upstreamExpiryDate','authCode',$url),str_replace('/domain/','/package/1/domain/',$url),str_replace('https:','http:',$url),str_replace('api.20i.com','api.20i.com.evil.invalid',$url),str_replace('owned.synthetic.invalid','../owned.synthetic.invalid',$url)] as $i=>$bad)er_ok('Noncanonical or unrelated expiry route refused '.$i,!DomainExpiryReader::acceptsUrl($bad));
$at=1700000000;$resolved=0;$secret='SYNTHETIC_PRIVATE_KEY';$transport=new ExpiryReaderTransport('"2028-02-29T01:02:03.000+05:45"');
$credential=new ProviderCredential('twenty-i',$registrar->data['profile_id'],function()use(&$resolved,$secret):string{$resolved++;return $secret;});
$result=DomainExpiryReader::read($transport,$credential,$binding,$at);
er_ok('One secret resolution and exact bounded authenticated GET',$resolved===1&&$transport->calls===1&&$transport->request->url===$url&&$transport->request->method==='GET'&&$transport->request->body===null&&$transport->request->timeoutSeconds===15.0&&$transport->request->headers===['Authorization'=>'Bearer '.base64_encode($secret),'Accept'=>'application/json']);
er_ok('Reader preserves supported timestamp and drops private response fields',$result->state==='complete'&&$result->upstreamExpiryDate==='2028-02-29T01:02:03.000+05:45'&&!str_contains(json_encode($result),'PRIVATE'));
$echoes=[
 ['literal timestamp credential','2028-01-01T00:00:00Z','"2028-01-01T00:00:00Z"'],
 ['JSON escaped timestamp credential','2028-01-01T00:00:00Z','"2028-01-01T00:00:00\u005a"'],
 ['date substring','2028-01-01','"2028-01-01T00:00:00Z"'],
 ['time substring','00:00:00','"2028-01-01T00:00:00Z"'],
 // Reader-only binary input; the settings Unicode validator does not accept this key.
 ['encoded substring reader-only',base64_decode('2028',true),'"2028-01-01T00:00:00Z"'],
];
foreach($echoes as [$label,$key,$body]){$t=new ExpiryReaderTransport($body);$r=DomainExpiryReader::read($t,new ProviderCredential('twenty-i',$registrar->data['profile_id'],fn()=>$key),$binding,$at);er_ok('Credential echo refuses saved scalar '.$label,$t->calls===1&&$r->state==='unavailable'&&$r->upstreamExpiryDate===null&&$r->reason==='timestamp_unsupported');}
foreach(['connect','timeout','tls','protocol','unavailable','unknown','raw'] as $failure){$t=new ExpiryReaderTransport('',$failure);$r=DomainExpiryReader::read($t,$credential,$binding,$at);er_ok('Finite transport refusal '.$failure,$t->calls===1&&$r->state==='unavailable'&&$r->upstreamExpiryDate===null&&$r->reason==='transport_'.($failure==='raw'?'unknown':$failure)&&!str_contains(json_encode($r),'PRIVATE'));}
foreach(['wrong-provider','wrong-account','negative-time'] as $label){$count=0;$t=new ExpiryReaderTransport('null');$c=new ProviderCredential($label==='wrong-provider'?'other':'twenty-i',$label==='wrong-account'?str_repeat('e',32):$registrar->data['profile_id'],function()use(&$count):string{$count++;return 'PRIVATE';});er_reject('Account/clock validation precedes secret '.$label,fn()=>DomainExpiryReader::read($t,$c,$binding,$label==='negative-time'?-1:$at));er_ok('No secret or send after invalid account '.$label,$count===0&&$t->calls===0);}
foreach(['',"bad\nkey",str_repeat('a',4097)] as $i=>$key){$t=new ExpiryReaderTransport('null');er_reject('Invalid credential refused '.$i,fn()=>DomainExpiryReader::read($t,new ProviderCredential('twenty-i',$registrar->data['profile_id'],fn()=>$key),$binding,$at));er_ok('Invalid credential never sends '.$i,$t->calls===0);}
$validate=new ReflectionMethod(CajunWebPro\Foundation\TwentyI\FixedHttpTransport::class,'validate');$fixed=new CajunWebPro\Foundation\TwentyI\FixedHttpTransport('8.8.8.8',fn()=>null);$headers=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_PRIVATE_KEY'),'Accept'=>'application/json'];
$validate->invoke($fixed,new TransportRequest('GET',$url,$headers,null,15));er_ok('Actual fixed transport admits exact expiry GET',true);
foreach([$url.'?x=1',$url.'#part',str_replace('owned.','OWNED.',$url),str_replace('owned.','owned%2e',$url),str_replace('owned.synthetic.invalid','701',$url),str_replace('upstreamExpiryDate','authCode',$url),str_replace('/domain/','/package/1/domain/',$url)] as $i=>$bad)er_reject('Actual fixed transport rejects noncanonical expiry route '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$bad,$headers,null,15)));
er_reject('Actual fixed transport rejects expiry GET body',fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$headers,'{}',15)));
er_reject('Actual fixed transport rejects expiry POST',fn()=>$validate->invoke($fixed,new TransportRequest('POST',$url,$headers,'{}',15)));
er_ok('Reader checks have unique labels',count($checks)===count(array_unique(array_column($checks,'name'))));
echo json_encode(['checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(array_filter($checks,fn($c)=>!$c['passed'])?1:0);
