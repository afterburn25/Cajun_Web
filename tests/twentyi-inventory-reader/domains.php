<?php
declare(strict_types=1);
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,TransportRequest,TransportResponse,TransportException};
use CajunWebPro\Foundation\TwentyIInventoryReader\DomainSearch;
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
foreach(['HttpTransport','ProviderCredential','TransportRequest','TransportResponse','TransportException','ProviderAdapter'] as $c)require __DIR__.'/../../foundation/provider/'.$c.'.php';
require __DIR__.'/../../foundation/twentyi-inventory-reader/DomainSearch.php';
require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';
require __DIR__.'/../../foundation/twentyi/TwentyIAdapter.php';
require __DIR__.'/../../foundation/twentyi/FixedHttpTransport.php';
$checks=[];
function ds_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function ds_deny(string $name,callable $work):void{try{$work();}catch(InvalidArgumentException){ds_ok($name,true);return;}ds_ok($name,false);}
function ds_project(string $body,int $status=200):array{return DomainSearch::project(new TransportResponse($status,[],$body),'example.invalid',1700000000,'twenty-i|synthetic|domains.search');}
final class SearchFixtureTransport implements HttpTransport{
    public array $requests=[];
    public function __construct(private TransportResponse|Throwable $result){}
    public function send(TransportRequest $request):TransportResponse{$this->requests[]=$request;if($this->result instanceof Throwable)throw $this->result;return $this->result;}
}
try{
    $header='{"header":{"names":["example.invalid"]}}';$result='{"name":"example.invalid","can":"register"}';
    $available=$header."\n".$result."\n";
    ds_ok('ASCII input normalizes case without accepting extra query text',DomainSearch::name('EXAMPLE.Invalid')==='example.invalid');
    foreach(['','single','example.invalid.','.invalid','a..invalid','-a.invalid','a-.invalid','a_1.invalid','a/invalid','a.invalid?x=1','a.invalid#x','a.invalid,another.invalid','a.invalid%00','a.invalid\evil','a.invalid:443','127.0.0.1','é.invalid','xn--caf-dma.invalid',"a.invalid\n",str_repeat('a',64).'.invalid',str_repeat('a.',125).'invalid',null,[],42] as $n=>$bad)ds_deny('Invalid exact search name '.$n,fn()=>DomainSearch::name($bad));
    $r=ds_project($available);ds_ok('Complete NDJSON exact result confirms observed registration availability',$r['state']==='checked'&&$r['registration_available']&&$r['query']==='example.invalid'&&$r['scope']==='twenty-i|synthetic|domains.search');
    ds_ok('Result packet may precede the header',ds_project($result."\n".$header)['registration_available']);
    ds_ok('Official console JSON array shape is accepted',ds_project('['.$header.','.$result.']')['registration_available']);
    ds_ok('CRLF packets and empty lines remain bounded',ds_project($header."\r\n\r\n".$result."\r\n")['registration_available']);
    $r=ds_project($header."\n".'{"name":"suggested.invalid","can":"register","untrusted":"NEVER_RETAIN"}' ."\n".$result);
    ds_ok('Additional names remain unverified suggestions despite can register',$r['registration_available']&&$r['suggestions']===['suggested.invalid']&&!str_contains(json_encode($r),'NEVER_RETAIN'));
    foreach([$result,$header,$header."\n".$result."\n".$result,$header."\n".$header."\n".$result,'{"header":{"names":["foreign.invalid"]}}'."\n".$result,$header."\n".'{"name":"example.invalid","can":"register","suggestion":true}',$header."\n".'{"name":"example.invalid","can":"suggestion"}',$header."\n".'{"name":"example.invalid","can":"transfer"}',$header."\n".'{"name":"example.invalid","can":true}',$header."\n".'{"name":"example.invalid","can":["register"]}',$available.'{"error":"NEVER_RETAIN"}',$available.'null',$available.'false'] as $n=>$body){$r=ds_project($body);ds_ok('Incomplete ambiguous or unknown response never confirms availability '.$n,!$r['registration_available']&&$r['state']==='partial'&&!str_contains(json_encode($r),'NEVER_RETAIN'));}
    foreach(['[','{','false','[]','',"\xff",$available.'{truncated'] as $n=>$body)ds_ok('Invalid or incomplete stream cannot yield availability '.$n,!ds_project($body)['registration_available']);
    foreach([str_repeat(' ',DomainSearch::MAX_BODY_BYTES+1),str_repeat(' ',4097).$result,str_repeat($header."\n",257),'['.implode(',',array_fill(0,257,'{}')).']'] as $n=>$body)ds_ok('Byte line and item bounds are enforced '.$n,ds_project($body)['reason']==='response_limit');
    foreach([401=>'authentication',403=>'authentication',429=>'rate_limited',503=>'remote_error',202=>'unexpected_status',301=>'unexpected_status'] as $status=>$reason){$r=ds_project($available,$status);ds_ok('HTTP '.$status.' cannot become availability',$r['reason']===$reason&&!$r['registration_available']);}
    $resolves=0;$credential=new ProviderCredential('twenty-i','profile-a',function()use(&$resolves):string{$resolves++;return 'synthetic-search-key';});
    $t=new SearchFixtureTransport(new TransportResponse(200,[],$available));$r=DomainSearch::read($t,$credential,'EXAMPLE.invalid',1700000000);
    ds_ok('Reader resolves once and sends exactly one canonical bounded GET',$resolves===1&&count($t->requests)===1&&$t->requests[0]->url==='https://api.20i.com/domain-search/example.invalid'&&$t->requests[0]->method==='GET'&&$t->requests[0]->body===null&&$t->requests[0]->timeoutSeconds===15.0);
    ds_ok('Only outbound authorization holds the credential',$t->requests[0]->headers===['Authorization'=>'Bearer '.base64_encode('synthetic-search-key'),'Accept'=>'application/json']&&!str_contains(json_encode($r),'synthetic-search-key'));
    ds_deny('Invalid query is refused before credential access',fn()=>DomainSearch::read($t,$credential,'bad/query',1700000000));
    ds_ok('Invalid query performs no network or credential work',$resolves===1&&count($t->requests)===1);
    foreach([new TransportException('timeout'),new RuntimeException('NEVER_RETAIN')] as $n=>$error){$t=new SearchFixtureTransport($error);$r=DomainSearch::read($t,$credential,'example.invalid',1700000000);ds_ok('Transport failure is bounded and never retried '.$n,count($t->requests)===1&&$r['state']==='unavailable'&&!str_contains(json_encode($r),'NEVER_RETAIN'));}
    $fixed=new FixedHttpTransport('8.8.8.8',fn()=>null);$validate=new ReflectionMethod($fixed,'validate');$headers=['Authorization'=>'Bearer '.base64_encode('synthetic-key'),'Accept'=>'application/json'];
    $validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/domain-search/example.invalid',$headers,null,15.0));ds_ok('Existing fixed transport admits only the canonical exact search',true);
    foreach(['https://api.20i.com/domain-search/example.invalid?x=1','https://api.20i.com/domain-search/example.invalid/../package','https://api.20i.com/domain-search/example.invalid%2F..','https://api.20i.com/domain-search/example.invalid,other.invalid','https://api.20i.com/domain-search/EXAMPLE.invalid','https://other.invalid/domain-search/example.invalid','http://api.20i.com/domain-search/example.invalid','https://api.20i.com/vps/1/start'] as $n=>$url)ds_deny('Search transport rejects destination or operation widening '.$n,fn()=>$validate->invoke($fixed,new TransportRequest('GET',$url,$headers,null,15.0)));
    ds_deny('Search cannot carry a request body',fn()=>$validate->invoke($fixed,new TransportRequest('GET','https://api.20i.com/domain-search/example.invalid',$headers,'{}',15.0)));
    ds_deny('Search does not authorize a POST operation',fn()=>$validate->invoke($fixed,new TransportRequest('POST','https://api.20i.com/domain-search/example.invalid',$headers,'{}',15.0)));
    echo json_encode(['passed'=>true,'checks'=>$checks,'scope'=>'Synthetic bounded exact-name search parser and existing transport. Native persistence, screens and live provider acceptance are separate.'],JSON_PRETTY_PRINT),"\n";
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT),"\n";exit(1);}
