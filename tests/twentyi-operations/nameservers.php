<?php
declare(strict_types=1);
// Loaded by run.php: pure request tests, never a provider call.
use CajunWebPro\Foundation\TwentyI\Operations\NameserverChange;
use CajunWebPro\Foundation\Provider\{ProviderOperation,TransportResponse,OperationResult};
use CajunWebPro\Foundation\TwentyI\Operations\TwentyIResultClassifier;

check($register->get('domains.nameservers')->shapeComplete,'nameserver operation has the owner-documented array fields');
check(refused(static fn()=>$builder->build(new ProviderOperation('domains.nameservers','k',['packageId'=>1,'domainId'=>2]))),'nameserver operation still refuses missing payload');

$change=NameserverChange::between(['NS1.EXAMPLE.','ns2.example'],['ns2.example','ns3.example']);
check($change->current===['ns1.example','ns2.example']&&$change->desired===['ns2.example','ns3.example'],'nameserver sets preserve canonical original and desired configurations');
check($change->fields()===['ns'=>['ns3.example'],'old-ns'=>['ns1.example']],'only actual additions and removals are sent');
check(!in_array('ns2.example',array_merge($change->added,$change->removed),true),'unchanged nameserver is absent from both delta lists');
check(NameserverChange::between([],['ns1.example'])->fields()===['ns'=>['ns1.example'],'old-ns'=>[]],'known empty starting set permits an addition');
check(NameserverChange::between(['ns1.example'],['ns1.example','ns2.example'])->fields()===['ns'=>['ns2.example'],'old-ns'=>[]],'add-only request keeps existing nameserver out of payload');
check(NameserverChange::between(['ns1.example','ns2.example'],['ns2.example'])->fields()===['ns'=>[],'old-ns'=>['ns1.example']],'remove-only request keeps the remaining desired nameserver out of payload');
check(refused(static fn()=>NameserverChange::between(['ns1.example'],[])),'empty desired nameserver configuration is refused');
check(refused(static fn()=>NameserverChange::between(['ns1.example','NS2.EXAMPLE.'],['ns2.example','NS1.EXAMPLE'])),'reordering case or absolute-dot changes cannot create a no-op write');
foreach([null,false,'ns1.example',['named'=>'ns1.example'],[1=>'ns1.example'],[[]],['ns1.example','NS1.EXAMPLE.'],array_fill(0,65,'ns1.example')] as $i=>$bad) {
    check(refused(static fn()=>NameserverChange::between($bad,['good.example'])),'invalid observed nameserver set refused '.$i);
    check(refused(static fn()=>NameserverChange::between(['good.example'],$bad)),'invalid desired nameserver set refused '.$i);
}
foreach(['','single','-ns.example','ns-.example','a..example','ns.example..','https://ns.example','ns.example:53','ns.example/path','ns.example?query','ns.example#fragment','*.example','127.0.0.1','[::1]','ns.éxample',"ns.example\n",' ns.example','ns.example ',str_repeat('n',64).'.example',str_repeat('a.',126).'zz'] as $i=>$bad) {
    check(refused(static fn()=>NameserverChange::between(['good.example'],[$bad])),'malformed nameserver hostname refused '.$i);
}
check(NameserverChange::between(['old.example'],['NS.XN--EXAMPLE-9D0B.TLD'])->desired===['ns.xn--example-9d0b.tld'],'ASCII encoded nameserver host is treated as a hostname, not a registration request');
$long=str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.'.str_repeat('d',61);
check(strlen($long)===253&&NameserverChange::between([],[$long.'.'])->desired===[$long],'253-byte absolute DNS name remains within the local bound');
$many=[];for($i=0;$i<64;$i++)$many[]='ns'.$i.'.example';
check(count(NameserverChange::between([],$many)->added)===64,'local 64-name structural bound is inclusive and is not a provider eligibility claim');
foreach([[[],[]],[['ns1.example'],['NS1.EXAMPLE']],[['ns1.example','NS1.EXAMPLE'],[]],[[],['ns1.example','NS1.EXAMPLE']]] as $i=>[$add,$remove]) {
    check(refused(static fn()=>NameserverChange::delta($add,$remove)),'invalid or overlapping nameserver delta refused '.$i);
}
$params=['packageId'=>201,'domainId'=>901]+$change->fields();
$nsRequest=$builder->build(new ProviderOperation('domains.nameservers','nameserver-test',$params));
check($nsRequest->method==='POST'&&$nsRequest->url==='https://api.20i.com/package/201/domain/901/nameservers','nameserver request uses exact package and allocated domain path');
check(json_decode($nsRequest->body,true)===$change->fields(),'nameserver request serializes two exact disjoint arrays');
check(!isset($nsRequest->headers['Authorization'])&&$nsRequest->timeoutSeconds===15.0,'nameserver request cannot carry credentials and retains bounded timeout');
check(!$register->allowsGenericApproval('domains.nameservers',$params)&&$register->genericNames()===['packages.update'],'shape completion does not open generic nameserver approval');
foreach(['packageId','domainId'] as $slot)foreach([0,-1,'201','*','../201',false] as $i=>$bad) {
    $wrong=$params;$wrong[$slot]=$bad;
    check(refused(static fn()=>$builder->build(new ProviderOperation('domains.nameservers','bad',$wrong))),'invalid nameserver resource slot refused '.$slot.' '.$i);
}
foreach([
 ['packageId'=>201,'domainId'=>901,'ns'=>['new.example']],
 ['packageId'=>201,'domainId'=>901,'old-ns'=>['old.example']],
 ['packageId'=>201,'domainId'=>901,'ns'=>['same.example'],'old-ns'=>['SAME.EXAMPLE']],
 ['packageId'=>201,'domainId'=>901,'ns'=>[],'old-ns'=>[]],
 ['packageId'=>201,'domainId'=>901,'ns'=>['127.0.0.1'],'old-ns'=>[]],
 ['packageId'=>201,'domainId'=>901,'ns'=>['new.example'],'old-ns'=>[],'force'=>true],
 ['packageId'=>201,'domainId'=>901,'ns'=>['new.example'],'old-ns'=>[],'resellerId'=>'*'],
] as $i=>$wrong)check(refused(static fn()=>$builder->build(new ProviderOperation('domains.nameservers','bad',$wrong))),'widened or ambiguous nameserver request refused '.$i);
$nsCase=$builder->build(new ProviderOperation('domains.nameservers','normalized',['packageId'=>201,'domainId'=>901,'old-ns'=>['OLD.EXAMPLE'],'ns'=>['Z.NEW.EXAMPLE','A.NEW.EXAMPLE']]));
check($nsCase->body==='{"ns":["a.new.example","z.new.example"],"old-ns":["old.example"]}','direct validated nameserver delta has stable canonical ordering');
foreach(['true','false','null','{}','{"result":true}','{"result":901}'] as $i=>$body) {
    $r=TwentyIResultClassifier::fromResponse('domains.nameservers',new TransportResponse(200,[],$body));
    check($r->status===OperationResult::UNCERTAIN&&$r->needsReconciliation(),'unverified nameserver response cannot claim completed DNS change '.$i);
}
