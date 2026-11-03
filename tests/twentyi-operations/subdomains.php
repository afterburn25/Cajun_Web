<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);

if (($argv[1] ?? '') === '--cold-wire') {
    require __DIR__.'/../../foundation/provider/TransportRequest.php';
    require __DIR__.'/../../foundation/twentyi-operations/SubdomainAdd.php';
    $before = class_exists('CajunWebPro\\Foundation\\Provider\\ProviderOperation', false);
    \CajunWebPro\Foundation\TwentyI\Operations\SubdomainAdd::wire(
        new \CajunWebPro\Foundation\Provider\TransportRequest('POST', 'https://api.20i.com/package/701/web/subdomains', [], '{"add":["api.site.example"],"rem":[]}', 15.0));
    $passed = !$before && !class_exists('CajunWebPro\\Foundation\\Provider\\ProviderOperation', false);
    echo json_encode(['passed'=>$passed,'checks'=>[['name'=>'Cold wire validates without ProviderOperation construction','passed'=>$passed]]], JSON_THROW_ON_ERROR).PHP_EOL;
    exit($passed ? 0 : 1);
}

require __DIR__.'/../twentyi/bootstrap.php';
require __DIR__.'/../provider/FileLedger.php';
foreach (['TwentyIOperation','TwentyIOperationRegister','TwentyIRequestBuilder','TwentyIResultClassifier','TwentyIOperationAdapter'] as $class) {
    require_once __DIR__.'/../../foundation/twentyi-operations/'.$class.'.php';
}
use CajunWebPro\Foundation\Provider\{HttpTransport,ProviderOperation,ProviderCredential,ProviderGateway,TransportRequest,TransportResponse,TransportException,OperationLedger,OperationResult,LedgerClaim};
use CajunWebPro\Foundation\TwentyI\Operations\{SubdomainAdd,TwentyIRequestBuilder,TwentyIOperationRegister,TwentyIOperationAdapter,TwentyIResultClassifier};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;

$checks = []; $names = [];
function sda_check(string $name, bool $passed): void {
    global $checks, $names;
    if (isset($names[$name])) throw new LogicException('Duplicate test name.');
    $names[$name] = true; $checks[] = ['name'=>$name,'passed'=>$passed];
    if (!$passed) throw new RuntimeException('Subdomain contract assertion failed.');
}
function sda_refused(callable $action): bool {
    try { $action(); return false; } catch (InvalidArgumentException) { return true; }
}
final class SubdomainAddFixtureTransport implements HttpTransport {
    public int $calls = 0;
    public ?string $failure = null;
    public ?TransportRequest $last = null;
    public function send(TransportRequest $request): TransportResponse {
        $fixed = new FixedHttpTransport('8.8.8.8', static fn()=>throw new LogicException('No network in contract fixture.'));
        (new ReflectionMethod($fixed,'validate'))->invoke($fixed,$request);
        ++$this->calls; $this->last = $request;
        if ($this->failure !== null) throw new TransportException($this->failure);
        return new TransportResponse(200, [], '{"result":true,"private":"SYNTHETIC_RESPONSE_MARKER"}');
    }
}
final class SubdomainAddRecordFailure implements OperationLedger {
    public function __construct(private FileLedger $inner) {}
    public function claim(string $key,string $fingerprint,int $now): LedgerClaim { return $this->inner->claim($key,$fingerprint,$now); }
    public function record(string $key,string $fingerprint,OperationResult $result,int $now): void { throw new RuntimeException('Synthetic local record failure.'); }
}

try {
    $params = ['packageId'=>701,'parentDomain'=>'site.example','subdomain'=>'api.site.example'];
    $register = new TwentyIOperationRegister(); $builder = new TwentyIRequestBuilder($register);
    $operation = new ProviderOperation(SubdomainAdd::OPERATION,'subdomain-synthetic-1',$params);
    $request = $builder->build($operation);
    sda_check('Operation registered and buildable exactly once', count(array_keys($register->names(),SubdomainAdd::OPERATION,true)) === 1 && count(array_keys($register->buildableNames(),SubdomainAdd::OPERATION,true)) === 1);
    sda_check('Generic owner approval remains closed', !$register->allowsGenericApproval(SubdomainAdd::OPERATION,$params) && $register->genericNames() === ['packages.update']);
    sda_check('Descriptor has only typed local parent and child fields', $register->get(SubdomainAdd::OPERATION)->fields === ['parentDomain'=>'hostname','subdomain'=>'hostname'] && $register->get(SubdomainAdd::OPERATION)->required === ['parentDomain','subdomain']);
    sda_check('Exact numeric package route and bounded method', $request->method === 'POST' && $request->url === 'https://api.20i.com/package/701/web/subdomains' && $request->timeoutSeconds === 15.0);
    sda_check('Canonical one-name add with empty removal list only', $request->body === '{"add":["api.site.example"],"rem":[]}');
    sda_check('Parent stays in local parameters and never becomes provider body or header', !str_contains($request->body,'parentDomain') && $request->headers === ['Content-Type'=>'application/json','Accept'=>'application/json'] && $operation->params === $params);
    $copy = SubdomainAdd::params(['subdomain'=>'api.site.example','parentDomain'=>'site.example','packageId'=>701]);
    sda_check('Input key order normalizes to exact detached scalar context', $copy === $params);
    $aliased = 'api.site.example'; $input = $params; $input['subdomain'] =& $aliased; $copy = SubdomainAdd::params($input); $aliased = 'other.site.example';
    sda_check('Caller reference cannot change already validated context', $copy['subdomain'] === 'api.site.example');
    foreach ([1,999999999999999999] as $id) sda_check('Allocated package edge '.$id, SubdomainAdd::params(array_replace($params,['packageId'=>$id]))['packageId'] === $id);
    foreach ([0,-1,'701',true,1.5,null,1000000000000000000] as $index=>$id) sda_check('Invalid package identity '.$index, sda_refused(fn()=>SubdomainAdd::params(array_replace($params,['packageId'=>$id]))));
    foreach (['packageId','parentDomain','subdomain'] as $field) {
        $missing = $params; unset($missing[$field]);
        sda_check('Missing exact parameter '.$field, sda_refused(fn()=>SubdomainAdd::params($missing)));
    }
    foreach (['add','rem','chg','path','documentRoots','billing','stackUser','Authorization'] as $field) sda_check('Additional parameter refused '.$field, sda_refused(fn()=>SubdomainAdd::params($params+[$field=>'synthetic'])));
    foreach (['SITE.example','site.example.','*.example','127.0.0.1','https://site.example','site','site..example',"site\0.example",'sité.example',[],new stdClass()] as $index=>$name) sda_check('Invalid parent syntax '.$index, sda_refused(fn()=>SubdomainAdd::params(array_replace($params,['parentDomain'=>$name]))));
    foreach (['site.example','evilsite.example','api.site.example.evil','api.other.example','a.b.site.example','API.site.example','*.site.example','127.0.0.1','https://api.site.example','api.site.example.','api..site.example','-api.site.example','api-.site.example','api_name.site.example',"api\n.site.example",'äpi.site.example',str_repeat('x',64).'.site.example',[],null] as $index=>$name) sda_check('Invalid or unrelated child '.$index, sda_refused(fn()=>SubdomainAdd::params(array_replace($params,['subdomain'=>$name]))));
    sda_check('One child of a specifically attached nested parent is structurally valid', SubdomainAdd::params(['packageId'=>701,'parentDomain'=>'app.site.example','subdomain'=>'api.app.site.example'])['subdomain'] === 'api.app.site.example');
    $parent = str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',61); $name = str_repeat('d',63).'.'.$parent;
    sda_check('Exact 253-byte DNS name is admitted without rewriting', strlen($name) === 253 && SubdomainAdd::params(['packageId'=>701,'parentDomain'=>$parent,'subdomain'=>$name])['subdomain'] === $name);
    sda_check('Overlong DNS name is refused', sda_refused(fn()=>SubdomainAdd::params(['packageId'=>701,'parentDomain'=>$parent.'c','subdomain'=>$name.'c'])));
    sda_check('Wrong operation cannot use typed request constructor', sda_refused(fn()=>SubdomainAdd::request(new ProviderOperation('packages.forcessl','other',$params))));

    $headers = ['Authorization'=>'Bearer '.base64_encode('SYNTHETIC_SUBDOMAIN_KEY'),'Accept'=>'application/json','Content-Type'=>'application/json'];
    $wire = new TransportRequest('POST',$request->url,$headers,$request->body,15.0);
    $fixed = new FixedHttpTransport('8.8.8.8', static fn()=>throw new LogicException('No network in wire validation.'));
    $validate = new ReflectionMethod($fixed,'validate');
    SubdomainAdd::wire($wire); $validate->invoke($fixed,$wire);
    sda_check('Actual fixed transport admits the exact authenticated wire before network', true);
    foreach (['https://api.20i.com/package/0701/web/subdomains','https://api.20i.com/package/0/web/subdomains','https://api.20i.com/package/1000000000000000000/web/subdomains','https://api.20i.com/package/701/web/subdomains?x=1','https://api.20i.com/package/701/web/subdomains#x','https://api.20i.com/package/701/names','https://api.20i.com/package/701/web/names','https://api.20i.com/package/701/web/updateDocRoot','http://api.20i.com/package/701/web/subdomains','https://api.20i.com:443/package/701/web/subdomains','https://other.invalid/package/701/web/subdomains'] as $index=>$url) sda_check('Alternate write route refused '.$index, sda_refused(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$url,$headers,$request->body,15.0))));
    foreach (['{}','[]','null','true','{"add":"api.site.example","rem":[]}','{"add":[],"rem":[]}','{"add":["api.site.example","www.site.example"],"rem":[]}','{"add":["api.site.example"],"rem":["old.site.example"]}','{"add":["api.site.example"],"rem":{}}','{"rem":[],"add":["api.site.example"]}','{"add":["api.site.example"], "rem":[]}','{"add":["other.site.example"],"add":["api.site.example"],"rem":[]}','{"add":["api.site.example"],"rem":[],"chg":"api.site.example"}','{"add":["api.site.example"],"rem":[],"parentDomain":"site.example"}','{"add":{"api.site.example":"public_html"},"rem":[]}','{"add":["site.example"],"rem":[]}','{"add":["*.site.example"],"rem":[]}',str_repeat(' ',8193),"\xff"] as $index=>$body) {
        sda_check('Canonical body refusal '.$index, sda_refused(fn()=>SubdomainAdd::wire(new TransportRequest('POST',$wire->url,$headers,$body,15.0))) && sda_refused(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$wire->url,$headers,$body,15.0))));
    }
    sda_check('No new subdomain GET route admitted', sda_refused(fn()=>$validate->invoke($fixed,new TransportRequest('GET',$wire->url,['Authorization'=>$headers['Authorization'],'Accept'=>'application/json'],null,15.0))));
    sda_check('Wrong method refused', sda_refused(fn()=>$validate->invoke($fixed,new TransportRequest('DELETE',$wire->url,$headers,$wire->body,15.0))));
    sda_check('Unknown header refused', sda_refused(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$wire->url,$headers+['X-Other'=>'x'],$wire->body,15.0))));
    sda_check('Wrong JSON content type refused', sda_refused(fn()=>$validate->invoke($fixed,new TransportRequest('POST',$wire->url,array_replace($headers,['Content-Type'=>'text/plain']),$wire->body,15.0))));
    $oldRead = new TransportRequest('GET','https://api.20i.com/package/701/web/documentRoots',['Authorization'=>$headers['Authorization'],'Accept'=>'application/json'],null,15.0);
    $validate->invoke($fixed,$oldRead); sda_check('Existing document-root GET remains the separate readback route', true);
    foreach ([200,201,202,204,408,409,425,429,500] as $status) {
        $result = TwentyIResultClassifier::fromResponse(SubdomainAdd::OPERATION,new TransportResponse($status,[],'{"result":true}'));
        sda_check('HTTP acknowledgement stays uncertain '.$status, $result->status === 'uncertain' && $result->externalId === null);
    }
    foreach (['true','false','null','[]','{}','{"result":701}','{"status":"success"}','{"private":"SYNTHETIC_RESPONSE_MARKER"}','not-json',str_repeat('x',65537)] as $index=>$body) {
        $result = TwentyIResultClassifier::fromResponse(SubdomainAdd::OPERATION,new TransportResponse(200,[],$body));
        sda_check('Malformed or undocumented response has no success or disclosure '.$index, $result->status === 'uncertain' && !str_contains(json_encode($result),'SYNTHETIC_RESPONSE_MARKER'));
    }
    foreach ([401,403,422] as $status) {
        $result = TwentyIResultClassifier::fromResponse(SubdomainAdd::OPERATION,new TransportResponse($status,[],'SYNTHETIC_RESPONSE_MARKER'));
        sda_check('Existing explicit rejection semantics retained '.$status, $result->status === 'failed' && !str_contains(json_encode($result),'SYNTHETIC_RESPONSE_MARKER'));
    }
    $adapter = new TwentyIOperationAdapter();
    $caps = array_values(array_filter($adapter->capabilities(),static fn($cap)=>$cap->operation === SubdomainAdd::OPERATION));
    sda_check('Adapter declares a non-idempotent typed operation', $adapter->supports(SubdomainAdd::OPERATION) && count($caps) === 1 && $caps[0]->idempotent === false);
    $paths = [tempnam(sys_get_temp_dir(),'cwp-subdomain-'),tempnam(sys_get_temp_dir(),'cwp-subdomain-fail-')];
    try {
        $calls = 0; $credential = new ProviderCredential('twenty-i','synthetic-account',static function() use (&$calls):string { ++$calls; return 'SYNTHETIC_SUBDOMAIN_KEY'; });
        $transport = new SubdomainAddFixtureTransport(); $gateway = new ProviderGateway($adapter,$transport,new FileLedger($paths[0]));
        $first = $gateway->perform($credential,$operation); $again = $gateway->perform($credential,$operation);
        sda_check('Actual shared gateway records uncertainty and replay sends only once', $first->status === 'uncertain' && $first->attempts === 1 && $again->replayed && $transport->calls === 1 && $calls === 1);
        sda_check('Adapter passes only canonical provider fields', $transport->last->body === $wire->body && $transport->last->headers === $headers);
        $changed = $params; $changed['parentDomain'] = 'other.example';
        $conflict = $gateway->perform($credential,new ProviderOperation(SubdomainAdd::OPERATION,$operation->idempotencyKey,$changed));
        sda_check('Changed local parent conflicts before credentials or wire', $conflict->status === 'conflict' && $transport->calls === 1 && $calls === 1);
        $transport->failure = 'timeout'; $timed = new ProviderOperation(SubdomainAdd::OPERATION,'subdomain-timeout',$params);
        $failed = $gateway->perform($credential,$timed); $replayed = $gateway->perform($credential,$timed);
        sda_check('Timeout records uncertainty with no automatic or replay resend', $failed->status === 'uncertain' && $failed->attempts === 1 && $replayed->replayed && $transport->calls === 2 && $calls === 2);
        $transport->failure = null;
        $broken = new ProviderGateway($adapter,$transport,new SubdomainAddRecordFailure(new FileLedger($paths[1])));
        $record = new ProviderOperation(SubdomainAdd::OPERATION,'subdomain-record-failure',$params);
        $failed = $broken->perform($credential,$record); $pending = $broken->perform($credential,$record);
        sda_check('Lost local result keeps durable claim and cannot resend', $failed->status === 'uncertain' && $pending->status === 'in_progress' && $transport->calls === 3 && $calls === 3);
        $before = $transport->calls; $beforeCredentials = $calls;
        $invalid = array_replace($params,['subdomain'=>'unrelated.example']);
        $result = $adapter->execute($transport,$credential,new ProviderOperation(SubdomainAdd::OPERATION,'invalid',$invalid));
        sda_check('Malformed typed context refuses before credential opening or transport', $result->status === 'failed' && $calls === $beforeCredentials && $transport->calls === $before);
    } finally { foreach ($paths as $path) if (is_string($path) && is_file($path)) unlink($path); }
} catch (Throwable $error) {
    $checks[] = ['name'=>'Unexpected subdomain contract test exception','passed'=>false,'error_class'=>get_class($error)];
}
$passed = !array_filter($checks,static fn($check)=>$check['passed'] !== true);
$report = ['passed'=>$passed,'scope'=>'Actual typed builder, fixed transport validation and shared file-ledger gateway with synthetic responses. No SQL, DNS, provider request, current account authority or installed UI acceptance.','checks'=>$checks];
$encoded = json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
if (isset($argv[1])) {
    if (!is_dir(dirname($argv[1]))) mkdir(dirname($argv[1]),0700,true);
    if (file_put_contents($argv[1],$encoded) !== strlen($encoded)) throw new RuntimeException('Could not preserve subdomain contract report.');
}
echo $encoded; exit($passed ? 0 : 1);
