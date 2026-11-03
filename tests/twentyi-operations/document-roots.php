<?php
declare(strict_types=1);
// Included by run.php. Real request/transport validators and extracted generic
// admission functions; synthetic dependencies only, never DNS, curl or SQL.
use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportRequest,TransportResponse,OperationResult};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\TwentyI\Operations\{DocumentRootChange,TwentyIResultClassifier};
use CajunWebPro\Foundation\TwentyIDocumentRoots\DocumentRootsObservation;

require_once __DIR__.'/../twentyi/bootstrap.php';

/** Exact top-level production function, with no tokenizer extension dependency. */
function dr_contract_function(string $source,string $name):string {
    $start='function '.$name.'(';
    if(substr_count($source,$start)!==1)throw new LogicException('Expected one production function.');
    $offset=strpos($source,$start);$end=strpos($source,"\n}\n",$offset);
    if($end===false)throw new LogicException('Expected closed production function.');
    return substr($source,$offset,$end+3-$offset);
}

set_error_handler(static function(int $severity,string $message,string $file,int $line):never {
    throw new ErrorException($message,0,$severity,$file,$line);
});
try {
    $drParams=['packageId'=>14701,'name'=>'website.synthetic.invalid','path'=>'public_html/newsite'];
    $drBody='{"update":{"website.synthetic.invalid":"public_html/newsite"}}';
    $drUrl='https://api.20i.com/package/14701/web/updateDocRoot';
    $drCold='require '.var_export(__DIR__.'/../../foundation/provider/TransportRequest.php',true).';'
        .'require '.var_export(__DIR__.'/../../foundation/twentyi-operations/DocumentRootChange.php',true).';'
        .'\\CajunWebPro\\Foundation\\TwentyI\\Operations\\DocumentRootChange::wire(new \\CajunWebPro\\Foundation\\Provider\\TransportRequest('
        .var_export('POST',true).','.var_export($drUrl,true).',[],'.var_export($drBody,true).',15.0));echo "cold-ok\\n";';
    $drProcess=proc_open([PHP_BINARY,'-n','-r',$drCold],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$drPipes);
    if(!is_resource($drProcess))throw new RuntimeException('Could not start isolated cold validator.');
    fclose($drPipes[0]);$drColdOut=stream_get_contents($drPipes[1]);$drColdErr=stream_get_contents($drPipes[2]);
    fclose($drPipes[1]);fclose($drPipes[2]);$drColdExit=proc_close($drProcess);
    check($drColdExit===0&&$drColdOut==="cold-ok\n"&&$drColdErr==='',
        'Document root wire cold-load works without eager operation or builder dependencies');
    check(DocumentRootChange::OPERATION==='packages.documentroot'&&$register->get(DocumentRootChange::OPERATION)->shapeComplete,
        'Document root typed operation is registered without a new generic capability');
    foreach([1,14701,999999999999999999] as $drId) {
        $drRequest=$builder->build(new ProviderOperation(DocumentRootChange::OPERATION,'document-root-contract',array_replace($drParams,['packageId'=>$drId])));
        check($drRequest->method==='POST'&&$drRequest->url==='https://api.20i.com/package/'.$drId.'/web/updateDocRoot'
            &&$drRequest->body===$drBody&&$drRequest->timeoutSeconds===15.0
            &&$drRequest->headers===['Content-Type'=>'application/json','Accept'=>'application/json'],
            'Document root exact single-host route canonical body and credential-free headers '.$drId);
        DocumentRootChange::wire($drRequest);
        check(true,'Document root canonical builder output passes its own wire boundary '.$drId);
    }
    foreach([0,-1,1000000000000000000,'14701',14701.0,true,null,[],new stdClass()] as $drIndex=>$drId)
        check(refused(fn()=>DocumentRootChange::params(array_replace($drParams,['packageId'=>$drId]))),
            'Document root rejects nonallocated or noninteger package '.$drIndex);
    foreach(['','localhost','UPPER.invalid','website.invalid.','*.invalid','a..invalid','-a.invalid','a-.invalid',
        '127.0.0.1','[::1]','https://website.invalid','a.invalid:443','a.invalid/path','a.invalid?x=1','a.invalid#x',
        'nämé.invalid',"bad\xff.invalid","a\n.invalid",' a.invalid','a.invalid ',str_repeat('a',64).'.invalid',
        str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.'.str_repeat('d',62),null,1,[]] as $drIndex=>$drName)
        check(refused(fn()=>DocumentRootChange::params(array_replace($drParams,['name'=>$drName]))),
            'Document root rejects malformed or normalized hostname input '.$drIndex);
    $drLongName=str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.'.str_repeat('d',61);
    check(strlen($drLongName)===253&&DocumentRootChange::params(array_replace($drParams,['name'=>$drLongName]))['name']===$drLongName,
        'Document root accepts exact253-byte DNS hostname boundary');
    check(DocumentRootChange::params(array_replace($drParams,['name'=>'xn--example-9d0b.invalid']))['name']==='xn--example-9d0b.invalid',
        'Document root accepts an exact ASCII encoded hostname without IDN conversion');

    // These are defensive local syntax limits, not provider filesystem eligibility.
    foreach(['public_html','public_html/newsite','Web_01/site-2.0','_private/-site', 'a..b',str_repeat('a',1024)] as $drIndex=>$drPath) {
        $drValidated=DocumentRootChange::params(array_replace($drParams,['path'=>$drPath]));
        check($drValidated['path']===$drPath,'Document root valid local path is preserved byte-exact '.$drIndex);
        $drRequest=$builder->build(new ProviderOperation(DocumentRootChange::OPERATION,'local-path',$drValidated));
        DocumentRootChange::wire($drRequest);
        check($drRequest->body===json_encode(['update'=>[$drParams['name']=>$drPath]],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
            'Document root one-host encoding preserves local path without normalization '.$drIndex);
    }
    foreach([null,false,42,1.5,[],new stdClass(),'','/','/public_html','public_html/','public_html//newsite','.', '..',
        './public_html','public_html/.','public_html/../private','.hidden','public_html/.hidden','public_html\\newsite',
        'public_html/%2e%2e/private','public_html/new site',' public_html','public_html ',"public_html\n", "public_html\0x", "public_html\x7f",
        'public_html/café',"public_html/\xff","public_html/\u{202e}site",'C:/public_html','https://example.invalid',str_repeat('a',1025)] as $drIndex=>$drPath)
        check(refused(fn()=>DocumentRootChange::params(array_replace($drParams,['path'=>$drPath]))),
            'Document root rejects unsupported relative path without normalization '.$drIndex);
    foreach(['','/public_html','../private','public_html/café'] as $drIndex=>$drObserved)
        check(DocumentRootsObservation::validPath($drObserved)
            &&refused(fn()=>DocumentRootChange::params(array_replace($drParams,['path'=>$drObserved]))),
            'Document root opaque observation remains broader than local writable subset '.$drIndex);
    foreach([[],['packageId'=>1],['packageId'=>1,'name'=>'a.invalid'],['name'=>'a.invalid','path'=>'public_html'],
        ['packageId'=>1,'update'=>['a.invalid'=>'public_html']],$drParams+['update'=>['other.invalid'=>'private']],
        $drParams+['force'=>true],$drParams+['Authorization'=>'private'],$drParams+['domainId'=>1],
        $drParams+['resellerId'=>'*'],$drParams+['platform'=>'linux']] as $drIndex=>$drBad)
        check(refused(fn()=>$builder->build(new ProviderOperation(DocumentRootChange::OPERATION,'closed-fields',$drBad))),
            'Document root missing extra or multihost local fields refused '.$drIndex);
    $drId=14701;$drName='website.synthetic.invalid';$drPath='public_html/newsite';
    $drDetached=DocumentRootChange::params(['path'=>&$drPath,'packageId'=>&$drId,'name'=>&$drName]);
    $drId=2;$drName='other.invalid';$drPath='private';
    check($drDetached===$drParams,'Document root detached scalar tuple has canonical key order');
    check(refused(fn()=>DocumentRootChange::request(new ProviderOperation('packages.update','wrong-operation',$drParams))),
        'Document root typed builder rejects another operation');

    $drHeaders=['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_DOCUMENT_ROOT_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'];
    $drAuthorizationCalls=0;
    $drFixed=new FixedHttpTransport('8.8.8.8',static function()use(&$drAuthorizationCalls):void {
        ++$drAuthorizationCalls;throw new LogicException('No transport execution in this test.');
    });
    $drValidate=new ReflectionMethod(FixedHttpTransport::class,'validate');
    $drValidate->invoke($drFixed,new TransportRequest('POST',$drUrl,$drHeaders,$drBody,15.0));
    check(true,'Document root fixed transport admits exact request through private validation only');
    foreach(['GET','PUT','PATCH','DELETE','OPTIONS'] as $drMethod)
        check(refused(fn()=>$drValidate->invoke($drFixed,new TransportRequest($drMethod,$drUrl,$drHeaders,$drBody,15.0))),
            'Document root fixed transport rejects alternate method '.$drMethod);
    foreach(['http://api.20i.com/package/14701/web/updateDocRoot','https://api.20i.com.evil.invalid/package/14701/web/updateDocRoot',
        'https://api.20i.com:443/package/14701/web/updateDocRoot','https://user@api.20i.com/package/14701/web/updateDocRoot',
        'https://api.20i.com/package/014701/web/updateDocRoot','https://api.20i.com/package/0/web/updateDocRoot',
        'https://api.20i.com/package/1000000000000000000/web/updateDocRoot',$drUrl.'?x=1',$drUrl.'#x',$drUrl.'/',
        'https://api.20i.com/package/14701/web/updatedocroot','https://api.20i.com/package/14701/web/%75pdateDocRoot',
        'https://api.20i.com/package/14701/web/documentRoots','https://api.20i.com/package/14701/web/phpVersion',
        'https://api.20i.com/reseller/*/updateDocRoot','https://api.20i.com/domain/14701/web/updateDocRoot'] as $drIndex=>$drBadUrl)
        check(refused(fn()=>$drValidate->invoke($drFixed,new TransportRequest('POST',$drBadUrl,$drHeaders,$drBody,15.0))),
            'Document root fixed transport rejects nonexact endpoint '.$drIndex);
    foreach([null,'','null','true','[]','{}','{"update":[]}','{"update":{}}','{"update":"public_html"}',
        '{"update":{"website.synthetic.invalid":null}}','{"update":{"website.synthetic.invalid":true}}',
        '{"update":{"website.synthetic.invalid":["public_html"]}}','{"update":{"website.synthetic.invalid":"public_html","other.invalid":"private"}}',
        '{"update":{"website.synthetic.invalid":"public_html","website.synthetic.invalid":"public_html/newsite"}}',
        '{"update":{"website.synthetic.invalid":"public_html"},"update":{"website.synthetic.invalid":"public_html/newsite"}}',
        '{"update":{"website.synthetic.invalid":"public_html/newsite"},"force":true}',
        '{"update":{"website.synthetic.invalid":"public_html/newsite"},"packageId":14701}',
        '{"update": {"website.synthetic.invalid":"public_html/newsite"}}',$drBody."\n",
        '{"update":{"website.synthetic.invalid":"public_html\\/newsite"}}',
        '{"up\\u0064ate":{"website.synthetic.invalid":"public_html/newsite"}}',
        '{"update":{"Website.synthetic.invalid":"public_html/newsite"}}',
        '{"update":{"website.synthetic.invalid":"../private"}}','{"update":{"website.synthetic.invalid":""}}',
        '{"update":{"website.synthetic.invalid":"\\ud800"}}',str_repeat(' ',8193)] as $drIndex=>$drBadBody)
        check(refused(fn()=>$drValidate->invoke($drFixed,new TransportRequest('POST',$drUrl,$drHeaders,$drBadBody,15.0))),
            'Document root rejects duplicate multihost malformed or noncanonical wire body '.$drIndex);
    foreach([array_diff_key($drHeaders,['Authorization'=>true]),array_replace($drHeaders,['Authorization'=>'Bearer bad!']),
        array_replace($drHeaders,['Accept'=>'text/plain']),array_replace($drHeaders,['Content-Type'=>'text/plain']),
        $drHeaders+['X-Extra'=>'x']] as $drIndex=>$drBadHeaders)
        check(refused(fn()=>$drValidate->invoke($drFixed,new TransportRequest('POST',$drUrl,$drBadHeaders,$drBody,15.0))),
            'Document root fixed transport rejects widened headers '.$drIndex);
    check(refused(fn()=>$drValidate->invoke($drFixed,new TransportRequest('POST',$drUrl,$drHeaders,$drBody,16.0))),
        'Document root fixed transport keeps original timeout bound');
    check($drAuthorizationCalls===0,'Document root validation never calls authorization DNS credential resolution or network');

    foreach(range(200,299) as $drStatus) {
        $drResult=TwentyIResultClassifier::fromResponse(DocumentRootChange::OPERATION,new TransportResponse($drStatus,[],'{"result":true}'));
        check($drResult->status===OperationResult::UNCERTAIN&&$drResult->externalId===null&&$drResult->needsReconciliation(),
            'Document root every HTTP2xx remains unverified '.$drStatus);
    }
    foreach(['true','null','{"result":14701}','{"result":{"website.synthetic.invalid":"public_html/newsite"}}','not json',str_repeat('x',65537)] as $drIndex=>$drResponse)
        check(TwentyIResultClassifier::fromResponse(DocumentRootChange::OPERATION,new TransportResponse(200,[],$drResponse))->status===OperationResult::UNCERTAIN,
            'Document root scalar resource map invalid and oversize acknowledgements remain uncertain '.$drIndex);
    foreach([401,403] as $drStatus) {
        $drResult=TwentyIResultClassifier::fromResponse(DocumentRootChange::OPERATION,new TransportResponse($drStatus,[],'SYNTHETIC_PRIVATE_RESPONSE'));
        check($drResult->status===OperationResult::FAILED&&$drResult->providerCode==='authentication'
            &&!str_contains(json_encode($drResult,JSON_THROW_ON_ERROR),'SYNTHETIC_PRIVATE_RESPONSE'),
            'Document root authentication failure never exposes raw reply '.$drStatus);
    }
    check($register->genericNames()===['packages.update']&&!$register->allowsGenericApproval(DocumentRootChange::OPERATION,$drParams),
        'Document root generic menu and approval remain closed');

    // Execute the unchanged production generic gates with modeled authority,
    // grant read and audit only. No real owner, database or persisted grant exists.
    $drSource=str_replace("\r\n","\n",file_get_contents(__DIR__.'/../../site/app/providers/operations.php'));
    $drCode= <<<'PHP'
namespace DocumentRootGenericAdmissionFixture;
use CajunWebPro\Foundation\Provider\{OperationResult,ProviderOperation};
use CajunWebPro\Foundation\TwentyI\Operations\{TwentyIOperationRegister,TwentyIRequestBuilder};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;
use InvalidArgumentException,RuntimeException,LogicException,PDOException,Throwable;
function db():object{return new class {public function inTransaction():bool{return false;}};}
function staff_locked(callable $fn):mixed{++$GLOBALS['dr_gate']['locks'];return $fn();}
function staff_transaction(callable $fn):mixed{++$GLOBALS['dr_gate']['transactions'];return $fn();}
function provider_checks_authority(string $id,int $revision,?string $password=null):array{++$GLOBALS['dr_gate']['authority'];return [['id'=>7],['id'=>$id,'revision'=>$revision]];}
function provider_operations_ready():bool{return true;}
function provider_settings_vault_key():string{return str_repeat('synthetic-key',4);}
function table(string $name):string{return $name;}
function one(string $sql,array $args):?array{if(!str_contains($sql,'FROM provider_operation_grants'))throw new LogicException('Unexpected read.');++$GLOBALS['dr_gate']['reads'];return $GLOBALS['dr_gate']['grant'];}
function query(string $sql,array $args):never{++$GLOBALS['dr_gate']['writes'];throw new LogicException('No write permitted.');}
function provider_settings_audit(int $actor,string $action,string $detail):void{$GLOBALS['dr_gate']['audits'][]=[$actor,$action];}
function provider_settings_owner(string $password):never{throw new LogicException('Grant must refuse before owner or persistence.');}
function provider_settings_unseal(...$args):never{throw new LogicException('No credential access permitted.');}
PHP;
    foreach(['provider_operations_scope','provider_operations_canonical','provider_operation_grant','provider_operations_execute'] as $drFunction)
        $drCode.="\n".dr_contract_function($drSource,$drFunction);
    eval($drCode);
    $GLOBALS['dr_gate']=['locks'=>0,'transactions'=>0,'authority'=>0,'reads'=>0,'writes'=>0,'audits'=>[],'grant'=>null];
    check(refused(fn()=>\DocumentRootGenericAdmissionFixture\provider_operation_grant(str_repeat('a',32),DocumentRootChange::OPERATION,$drParams,'synthetic-password'))
        &&$GLOBALS['dr_gate']['locks']===0&&$GLOBALS['dr_gate']['writes']===0,
        'Document root actual generic grant refuses before owner lock or SQL');
    $drNodes=0;
    $GLOBALS['dr_gate']['grant']=['id'=>str_repeat('b',32),'operation'=>DocumentRootChange::OPERATION,'revoked_at'=>null,
        'expires_epoch'=>time()+3600,'consumed_key'=>null,'profile_revision'=>1,
        'scope_params'=>\DocumentRootGenericAdmissionFixture\provider_operations_canonical($drParams,0,$drNodes),
        'scope_fingerprint'=>\DocumentRootGenericAdmissionFixture\provider_operations_scope(DocumentRootChange::OPERATION,$drParams)];
    $drResolves=0;$drSends=0;
    $drResult=\DocumentRootGenericAdmissionFixture\provider_operations_execute(str_repeat('a',32),1,str_repeat('b',32),'document-root-generic','synthetic-password',
        static function()use(&$drResolves):never{++$drResolves;throw new LogicException('No DNS permitted.');},
        static function()use(&$drSends):never{++$drSends;throw new LogicException('No send permitted.');});
    check($drResult->status===OperationResult::FAILED&&$drResult->providerCode==='consent_required',
        'Document root actual generic execution refuses even a correctly fingerprinted historical grant');
    check($GLOBALS['dr_gate']['locks']===1&&$GLOBALS['dr_gate']['transactions']===1&&$GLOBALS['dr_gate']['authority']===1
        &&$GLOBALS['dr_gate']['reads']===1&&$GLOBALS['dr_gate']['writes']===0&&$drResolves===0&&$drSends===0
        &&$GLOBALS['dr_gate']['audits']===[[7,'operation_refused']],
        'Document root generic denial precedes consumption DNS credentials and send with only refusal audit');
} finally {
    restore_error_handler();
}
