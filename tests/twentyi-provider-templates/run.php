<?php
declare(strict_types=1);

use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderCredential,ProviderOperation,TransportRequest,TransportResponse,TransportException,OperationResult};
use CajunWebPro\Foundation\TwentyIProviderTemplates\{PasswordResetTemplate,PasswordResetTemplateObservation,PasswordResetTemplateReader};
use CajunWebPro\Foundation\TwentyI\Operations\{PasswordResetTemplateChange,TwentyIOperationRegister,TwentyIRequestBuilder,TwentyIOperationAdapter};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;

spl_autoload_register(static function(string $name): void {
    foreach (['CajunWebPro\\Foundation\\TwentyI\\Operations\\'=>'twentyi-operations',
        'CajunWebPro\\Foundation\\TwentyIProviderTemplates\\'=>'twentyi-provider-templates',
        'CajunWebPro\\Foundation\\TwentyI\\'=>'twentyi',
        'CajunWebPro\\Foundation\\Provider\\'=>'provider',
        'CajunWebPro\\Foundation\\MailTransport\\'=>'mail-transport'] as $prefix=>$directory) {
        if (str_starts_with($name, $prefix)) {
            $file=__DIR__.'/../../foundation/'.$directory.'/'.substr($name,strlen($prefix)).'.php';
            if(is_file($file))require_once $file;
            return;
        }
    }
});

$checks=[];
function prt_ok(string $name,bool $passed): void {
    global $checks;$checks[]=['name'=>$name,'passed'=>$passed];
    if(!$passed)throw new RuntimeException('Named assertion failed.');
}
function prt_refuses(string $name,callable $fn,string $class=InvalidArgumentException::class): void {
    try{$fn();prt_ok($name,false);}catch(Throwable $e){if(!$e instanceof $class)throw $e;prt_ok($name,true);}
}
function prt_response(string $body,int $status=200): TransportResponse {return new TransportResponse($status,[],$body);}
function prt_project(string $body,int $status=200): PasswordResetTemplateObservation {
    return PasswordResetTemplateReader::project(prt_response($body,$status),str_repeat('a',32),1700000000);
}
function prt_operation(string $text='reviewed private template'): ProviderOperation {
    return new ProviderOperation(PasswordResetTemplateChange::OPERATION,'template:'.str_repeat('b',32),
        ['resellerId'=>'*','request_id'=>str_repeat('b',32),'content_sha256'=>hash('sha256',$text)]);
}
function prt_restore(object $source,array $replace=[],?string $missing=null): object {
    $class=new ReflectionClass($source);$out=$class->newInstanceWithoutConstructor();
    foreach($class->getProperties() as $property){$name=$property->getName();if($property->isStatic()||$name===$missing)continue;
        $property->setValue($out,array_key_exists($name,$replace)?$replace[$name]:$property->getValue($source));}
    return $out;
}
final class PRTTransport implements HttpTransport {
    public array $sent=[];
    public function __construct(private mixed $answer){}
    public function send(TransportRequest $request): TransportResponse {
        $this->sent[]=$request;if($this->answer instanceof Throwable)throw $this->answer;
        return is_callable($this->answer)?($this->answer)($request):$this->answer;
    }
}

try {
    $examples=['',"  Subject\r\n\tBody\n\r  ",'{{unknown}} ${opaque} <script>not executed</script>',"Café e\u{0301} 日本語 📨",'0'];
    foreach($examples as $i=>$text){$doc=PasswordResetTemplate::fromString($text);$o=prt_project(json_encode($text,JSON_THROW_ON_ERROR));
        prt_ok('Exact opaque text and byte digest '.$i,$doc->text()===$text&&$doc->bytes===strlen($text)&&$doc->sha256===hash('sha256',$text)
            &&$o->state==='complete'&&$o->template()->text()===$text&&$o->observedAt===1700000000);}
    $max=str_repeat('é',32768);prt_ok('Decoded 65536-byte UTF-8 boundary preserved',PasswordResetTemplate::fromString($max)->bytes===65536);
    prt_refuses('Decoded text over local byte limit refuses',fn()=>PasswordResetTemplate::fromString($max.'x'));
    prt_refuses('Invalid UTF-8 refuses',fn()=>PasswordResetTemplate::fromString("\xff"));
    foreach([0,1,8,11,12,14,31,127] as $control)prt_refuses('Unsupported local editor control refuses '.$control,fn()=>PasswordResetTemplate::fromString('before'.chr($control).'after'));
    $text='private-template-marker';$doc=PasswordResetTemplate::fromString($text);$text='mutated';
    prt_ok('Content document detaches caller scalar',$doc->text()==='private-template-marker');
    $o=prt_project('"private-template-marker"');
    ob_start();var_dump($doc,$o);$dump=ob_get_clean();
    prt_ok('Default JSON debug and print forms redact content',!str_contains(json_encode([$doc,$o]).$dump.print_r($doc,true).print_r($o,true),'private-template-marker'));
    prt_refuses('Document serialization refuses',fn()=>serialize($doc),LogicException::class);
    prt_refuses('Observation serialization refuses',fn()=>serialize($o),LogicException::class);
    foreach(['sha256'=>str_repeat('0',64),'bytes'=>1,'content'=>'tampered'] as $field=>$bad){$restored=prt_restore($doc,[$field=>$bad]);prt_refuses('Restored document inconsistency refuses '.$field,fn()=>$restored->text());}
    $restored=prt_restore($doc,[],'content');prt_refuses('Incomplete restored document refuses',fn()=>$restored->text());
    foreach(['scope'=>'wrong','state'=>'unavailable','reason'=>'raw private diagnostic'] as $field=>$bad){$restored=prt_restore($o,[$field=>$bad]);prt_refuses('Restored observation inconsistency refuses '.$field,fn()=>$restored->template());}
    $restored=prt_restore($o,[],'templateValue');prt_refuses('Incomplete restored observation refuses',fn()=>$restored->validated());
    prt_refuses('Unavailable cannot retain private text',fn()=>new PasswordResetTemplateObservation(str_repeat('a',32),0,'unavailable','invalid_value',$doc));
    prt_refuses('Complete cannot omit string document',fn()=>new PasswordResetTemplateObservation(str_repeat('a',32),0,'complete','',null));
    foreach(['null','false','true','0','1','1.0','[]','["text"]','{"content":"text"}','{"result":"text"}'] as $i=>$body){$r=prt_project($body);prt_ok('Read rejects scalar or envelope '.$i,$r->state==='unavailable'&&$r->reason==='unexpected_shape'&&$r->template()===null);}
    foreach(['','"unterminated','"first" "second"',"\xef\xbb\xbf\"text\"",'"\ud800"',"\"\xff\""] as $i=>$body)prt_ok('Read rejects malformed JSON '.$i,prt_project($body)->reason==='malformed_body');
    foreach(['"\u0000"','"\u000b"',json_encode(str_repeat('x',65537))] as $i=>$body)prt_ok('Read rejects uneditable value without normalization '.$i,prt_project($body)->reason==='invalid_value');
    prt_ok('Escaped maximum decoded content remains exact',prt_project('"'.str_repeat('\\u0061',65536).'"')->template()->text()===str_repeat('a',65536));
    prt_ok('Read wire limit is finite',prt_project(str_repeat(' ',PasswordResetTemplateReader::MAX_BODY_BYTES+1))->reason==='response_limit');
    foreach([201=>'unexpected_status',204=>'unexpected_status',302=>'unexpected_status',401=>'authentication',403=>'authentication',429=>'rate_limited',500=>'remote_error'] as $status=>$reason)prt_ok('Read status classification '.$status,prt_project('"text"',$status)->reason===$reason);
    prt_refuses('Malformed profile refuses even error response',fn()=>PasswordResetTemplateReader::project(prt_response('x',500),'other',0));
    prt_refuses('Negative observation date refuses',fn()=>PasswordResetTemplateReader::project(prt_response('"text"'),str_repeat('a',32),-1));
    $descriptor=PasswordResetTemplateReader::request();
    prt_ok('Read descriptor is fixed self-account bodyless GET',$descriptor->method==='GET'&&$descriptor->url===PasswordResetTemplateChange::URL&&$descriptor->headers===['Accept'=>'application/json']&&$descriptor->body===null&&$descriptor->timeoutSeconds===15.0);
    $resolved=0;$credential=new ProviderCredential('twenty-i',str_repeat('a',32),function()use(&$resolved):string{$resolved++;return 'synthetic-key-that-is-private';});
    $transport=new PRTTransport(prt_response('"read value"'));$r=PasswordResetTemplateReader::read($transport,$credential,str_repeat('a',32),1);
    prt_ok('Read resolves one credential and preserves exact text',$resolved===1&&count($transport->sent)===1&&$r->template()->text()==='read value'
        &&$transport->sent[0]->headers===['Authorization'=>'Bearer '.base64_encode('synthetic-key-that-is-private'),'Accept'=>'application/json']);
    foreach([['other',str_repeat('a',32)],['twenty-i',str_repeat('c',32)]] as $i=>[$provider,$profile]){
        $foreign=new ProviderCredential($provider,$profile,function()use(&$resolved):string{$resolved++;return 'unused';});
        prt_refuses('Foreign read identity refuses before opening credential '.$i,fn()=>PasswordResetTemplateReader::read($transport,$foreign,str_repeat('a',32),0));}
    prt_ok('Foreign reads produce no credential or transport effect',$resolved===1&&count($transport->sent)===1);
    foreach(['','x'."\n",str_repeat('k',4097),"\xff"] as $i=>$bad){$c=new ProviderCredential('twenty-i',str_repeat('a',32),fn()=>$bad);$t=new PRTTransport(prt_response('"text"'));
        prt_refuses('Unusable read credential refuses '.$i,fn()=>PasswordResetTemplateReader::read($t,$c,str_repeat('a',32),0));prt_ok('Unusable credential sends nothing '.$i,$t->sent===[]);}
    $echoCases=[['secret-key',json_encode('text secret-key')],['secret-key',json_encode(base64_encode('secret-key'))],['97','"stack-user:\u0039\u0037"'],['97','"\u004f\u0054\u0063\u003d"']];
    foreach($echoCases as $i=>[$key,$body]){$c=new ProviderCredential('twenty-i',str_repeat('a',32),fn()=>$key);$t=new PRTTransport(prt_response($body));$r=PasswordResetTemplateReader::read($t,$c,str_repeat('a',32),0);
        prt_ok('Raw base64 or decoded escaped credential echo refuses '.$i,$r->state==='unavailable'&&$r->reason==='invalid_value'&&$r->template()===null);}
    foreach(['connect','tls','timeout','protocol','unavailable','unknown'] as $kind){$r=PasswordResetTemplateReader::read(new PRTTransport(new TransportException($kind)),$credential,str_repeat('a',32),0);prt_ok('Read transport failure remains bounded '.$kind,$r->reason==='transport_'.$kind);}
    $r=PasswordResetTemplateReader::read(new PRTTransport(new RuntimeException('private provider diagnostic')),$credential,str_repeat('a',32),0);
    prt_ok('Unexpected read exception is sanitized',$r->reason==='transport_unknown'&&!str_contains(json_encode($r),'private provider diagnostic'));

    $op=prt_operation();$p=PasswordResetTemplateChange::params($op->params);
    prt_ok('Write scope contains only protected reference and digest',array_keys($p)===['resellerId','request_id','content_sha256']&&!str_contains(json_encode($p),'reviewed private template'));
    foreach(array_keys($p) as $missing){$bad=$p;unset($bad[$missing]);prt_refuses('Missing compact field refuses '.$missing,fn()=>PasswordResetTemplateChange::params($bad));}
    foreach([['resellerId'=>1],['resellerId'=>'123'],['request_id'=>str_repeat('A',32)],['request_id'=>str_repeat('b',31)],['content_sha256'=>str_repeat('G',64)],['content'=>'raw secret'],['before'=>'old']] as $i=>$bad)
        prt_refuses('Altered or expanded compact metadata refuses '.$i,fn()=>PasswordResetTemplateChange::params(array_replace($p,$bad)));
    $calls=[];$supplier=function(string $id,ProviderOperation $o)use(&$calls):string{$calls[]=[$id,$o];return 'reviewed private template';};
    $request=PasswordResetTemplateChange::request($op,$supplier);PasswordResetTemplateChange::wire($request);
    prt_ok('Protected supplier receives exact request and operation once',count($calls)===1&&$calls[0][0]===$p['request_id']&&$calls[0][1]===$op);
    prt_ok('Write is exact documented fixed canonical body',$request->method==='POST'&&$request->url===PasswordResetTemplateChange::URL&&$request->body==='{"content":"reviewed private template"}'&&$request->timeoutSeconds===15.0&&$request->headers===['Content-Type'=>'application/json','Accept'=>'application/json']);
    prt_refuses('Missing protected supplier refuses',fn()=>PasswordResetTemplateChange::request($op,null));
    prt_refuses('Changed private content refuses digest',fn()=>PasswordResetTemplateChange::request($op,fn()=>'different'));
    prt_refuses('Nonstring protected content refuses',fn()=>PasswordResetTemplateChange::request($op,fn()=>false));
    prt_refuses('Protected supplier exception is sanitized',fn()=>PasswordResetTemplateChange::request($op,function(){throw new RuntimeException('private supplier diagnostic');}));
    prt_refuses('Empty observed string has no write/reset semantics',fn()=>PasswordResetTemplateChange::request(prt_operation(''),fn()=>''));
    prt_refuses('Wrong operation cannot open content',fn()=>PasswordResetTemplateChange::request(new ProviderOperation('packages.update','wrong',$p),$supplier));
    $long=str_repeat('"',65536);$large=PasswordResetTemplateChange::request(prt_operation($long),fn()=>$long);PasswordResetTemplateChange::wire($large);
    prt_ok('Full local-size text passes canonical request roundtrip',json_decode($large->body,true)['content']===$long&&strlen($large->body)>8192&&strlen($large->body)<=PasswordResetTemplateChange::MAX_BODY_BYTES);
    foreach(['{"content":"x","content":"x"}','{ "content":"x"}','{"content":"x","send":true}','{"content":null}','{"content":1}','[]','{"result":"x"}','{"content":"\\u0078"}','{"content":""}'] as $i=>$body)
        prt_refuses('Noncanonical or expanded write body refuses '.$i,fn()=>PasswordResetTemplateChange::wire(new TransportRequest('POST',PasswordResetTemplateChange::URL,[],$body)));
    foreach(['https://evil.invalid/reseller/*/passwordResetEmail',PasswordResetTemplateChange::URL.'?send=1',str_replace('/*/','/123/',PasswordResetTemplateChange::URL),PasswordResetTemplateChange::URL.'/',str_replace('passwordResetEmail','resetPassword',PasswordResetTemplateChange::URL)] as $i=>$url)
        prt_refuses('Alternate fixed write path refuses '.$i,fn()=>PasswordResetTemplateChange::wire(new TransportRequest('POST',$url,[],$request->body)));
    prt_refuses('Write timeout expansion refuses',fn()=>PasswordResetTemplateChange::wire(new TransportRequest('POST',PasswordResetTemplateChange::URL,[],$request->body,16.0)));
    prt_refuses('Wire body limit refuses before decoding',fn()=>PasswordResetTemplateChange::wire(new TransportRequest('POST',PasswordResetTemplateChange::URL,[],str_repeat('x',PasswordResetTemplateChange::MAX_BODY_BYTES+1))));
    foreach(['1','2'," \n1\t",(string)PHP_INT_MAX] as $i=>$body){$r=PasswordResetTemplateChange::result(prt_response($body),'synthetic-key');prt_ok('Positive integer acknowledgement requires verification '.$i,$r->status===OperationResult::ACCEPTED_PENDING&&$r->externalId===null&&$r->needsReconciliation());}
    $r=PasswordResetTemplateChange::result(prt_response('0'),'synthetic-key');prt_ok('Zero row count does not prove a change',$r->status===OperationResult::UNCERTAIN&&$r->providerCode==='no_rows_reported');
    foreach(['true','false','null','"1"','1.0','1e0','-1','{"result":1}','{"count":1}','[1]','9223372036854775808','1 trailing',''] as $i=>$body){$r=PasswordResetTemplateChange::result(prt_response($body),'synthetic-key');prt_ok('Unverified acknowledgement shape remains uncertain '.$i,$r->status===OperationResult::UNCERTAIN&&$r->isFinal()&&$r->externalId===null);}
    prt_ok('Numeric credential echo cannot become accepted acknowledgement',PasswordResetTemplateChange::result(prt_response('97'),'97')->status===OperationResult::UNCERTAIN);
    prt_ok('Oversize write response remains uncertain',PasswordResetTemplateChange::result(prt_response(str_repeat(' ',129)),'key')->providerCode==='response_limit');
    foreach([201,202,204,302,408,409,429,500] as $status)prt_ok('Other status cannot acknowledge integer '.$status,PasswordResetTemplateChange::result(prt_response('1',$status),'key')->status===OperationResult::UNCERTAIN);
    foreach([400,401,403] as $status)prt_ok('Definite request rejection is bounded '.$status,PasswordResetTemplateChange::result(prt_response('private response',$status),'key')->status===OperationResult::FAILED);

    $register=new TwentyIOperationRegister();$spec=$register->get(PasswordResetTemplateChange::OPERATION);
    prt_ok('Actual operation register retains exact new request contract',$spec!==null&&$spec->shapeComplete&&$spec->path==='/reseller/{resellerId}/passwordResetEmail'&&$spec->fields===['content'=>'string']);
    prt_ok('Actual generic owner approval refuses template content',!$register->allowsGenericApproval(PasswordResetTemplateChange::OPERATION,$p));
    $builder=new TwentyIRequestBuilder($register,null,$supplier);$built=$builder->build($op);
    prt_ok('Actual builder uses protected content seam',$built->body===$request->body&&$built->url===$request->url);
    prt_refuses('Actual builder without supplier refuses',fn()=>(new TwentyIRequestBuilder($register))->build($op));
    $plain=new TwentyIOperationAdapter();$adapter=new TwentyIOperationAdapter(null,$supplier);
    prt_ok('Actual adapter capability requires dedicated supplier',!$plain->supports($op->name)&&$adapter->supports($op->name));
    $t=new PRTTransport(prt_response('1'));$r=$adapter->execute($t,$credential,$op);
    prt_ok('Actual adapter sends exactly once and retains pending acknowledgement',count($t->sent)===1&&$r->status===OperationResult::ACCEPTED_PENDING&&$t->sent[0]->body===$request->body&&array_keys($t->sent[0]->headers)===['Authorization','Accept','Content-Type']);
    $t=new PRTTransport(prt_response('1'));$badAdapter=new TwentyIOperationAdapter(null,fn()=>'changed');$r=$badAdapter->execute($t,$credential,$op);
    prt_ok('Actual adapter refuses changed protected content before send',$r->status===OperationResult::FAILED&&$t->sent===[]);
    $t=new PRTTransport(new TransportException('timeout'));$r=$adapter->execute($t,$credential,$op);
    prt_ok('Actual adapter timeout does not retry',count($t->sent)===1&&$r->status===OperationResult::UNCERTAIN);
    $authorizationCalls=0;$fixed=new FixedHttpTransport('8.8.8.8',function()use(&$authorizationCalls){$authorizationCalls++;});
    $validate=new ReflectionMethod($fixed,'validate');$headers=['Authorization'=>'Bearer '.base64_encode('synthetic-key'),'Accept'=>'application/json','Content-Type'=>'application/json'];
    $validate->invoke($fixed,new TransportRequest('POST',$request->url,$headers,$request->body));
    $validate->invoke($fixed,new TransportRequest('POST',$large->url,$headers,$large->body));
    $validate->invoke($fixed,new TransportRequest('GET',$descriptor->url,array_slice($headers,0,2),null));
    prt_ok('Actual fixed validator accepts only typed large write and bodyless read without effects',$authorizationCalls===0);
    foreach(['{"content":"x","content":"x"}','{"content":"x","send":true}',str_repeat('x',PasswordResetTemplateChange::MAX_BODY_BYTES+1)] as $i=>$body)
        prt_refuses('Actual fixed validator refuses altered template body '.$i,fn()=>$validate->invoke($fixed,new TransportRequest('POST',$request->url,$headers,$body)));
    prt_refuses('Actual fixed validator refuses extra header',fn()=>$validate->invoke($fixed,new TransportRequest('POST',$request->url,$headers+['X-Extra'=>'value'],$request->body)));
    prt_refuses('Actual fixed validator refuses alternate template account',fn()=>$validate->invoke($fixed,new TransportRequest('POST',str_replace('/*/','/123/',$request->url),$headers,$request->body)));
    prt_refuses('Actual fixed validator keeps old endpoint body limit',fn()=>$validate->invoke($fixed,new TransportRequest('POST','https://api.20i.com/reseller/*/updatePackage',$headers,str_repeat('x',8193))));
    prt_refuses('Actual fixed validator refuses read body',fn()=>$validate->invoke($fixed,new TransportRequest('GET',$descriptor->url,array_slice($headers,0,2),'{}')));
    prt_refuses('Actual fixed validator refuses read query',fn()=>$validate->invoke($fixed,new TransportRequest('GET',$descriptor->url.'?x=1',array_slice($headers,0,2),null)));
    prt_ok('Offline fixture never invokes provider transport send or authority callback',$authorizationCalls===0);
    $result=['passed'=>true,'count'=>count($checks),'checks'=>$checks,'scope'=>'Offline real foundation and shared request-validator checks; no database, DNS, provider HTTP or mail delivery'];
} catch(Throwable $e) {
    $result=['passed'=>false,'count'=>count($checks),'checks'=>$checks,'error_class'=>get_class($e),'scope'=>'Offline foundation checks only'];
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($result['passed']?0:1);
