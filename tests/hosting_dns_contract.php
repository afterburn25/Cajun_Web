<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
require_once __DIR__.'/../foundation/twentyi-package-php/PackagePhpVersionBinding.php';
foreach (['HostingDnsContext','HostingDnsRecord','HostingDnsProposal'] as $class) require_once __DIR__.'/../foundation/twentyi-hosting-dns/'.$class.'.php';
use CajunWebPro\Foundation\TwentyIPackagePhp\PackagePhpVersionBinding;
use CajunWebPro\Foundation\TwentyIHostingDns\{HostingDnsContext,HostingDnsRecord,HostingDnsProposal};

$checks=[];
function dns_ok(string $name,bool $value):void { global $checks; $checks[]=['name'=>$name,'passed'=>$value]; }
function dns_deny(string $name,callable $call):void {
    try { $call(); dns_ok($name,false); }
    catch (InvalidArgumentException $e) { dns_ok($name,!str_contains($e->getMessage(),'PRIVATE_MARKER')); }
}
function dns_restored(string $class,array $values):object {
    $object=(new ReflectionClass($class))->newInstanceWithoutConstructor();
    foreach($values as $key=>$value)(new ReflectionProperty($class,$key))->setValue($object,$value);
    return $object;
}
$packageData=['profile_id'=>str_repeat('1',32),'profile_revision'=>1,'key_fingerprint'=>str_repeat('2',64),'credential_sha256'=>str_repeat('3',64),
    'package_id'=>701,'client_id'=>9,'link_id'=>str_repeat('4',32),'link_sha256'=>str_repeat('5',64),'event_id'=>10,'event_sha256'=>str_repeat('6',64),
    'audit_id'=>11,'audit_sha256'=>str_repeat('7',64),'source_id'=>str_repeat('8',32),'source_seq'=>12,'source_sha256'=>str_repeat('9',64)];
$package=new PackagePhpVersionBinding($packageData);
$context=new HostingDnsContext($package,'allocated-domain-reference','zone.synthetic.invalid');
$secret='SYNTHETIC_DNS_PRIVATE_MARKER_KEY';
$valid=[
    'A'=>['host'=>'www','ip'=>'192.0.2.1'],
    'AAAA'=>['host'=>'ipv6','ipv6'=>'2001:db8::1'],
    'CNAME'=>['reuseId'=>'record-reference','host'=>'alias','target'=>'target.synthetic.invalid'],
    'MX'=>['host'=>'@','target'=>'mx.synthetic.invalid','pri'=>'10'],
    'TXT'=>['host'=>'_verification','txt'=>'value "quoted" \\ preserved'],
    'SRV'=>['host'=>'_sip._tcp','pri'=>'0','weight'=>'5','port'=>'443','target'=>'srv.synthetic.invalid'],
];
$records=[];
foreach($valid as $type=>$fields){$r=new HostingDnsRecord($type,$fields);$records[]=$r;dns_ok('Exact local fields retained '.$type,$r->type===$type&&$r->fields===$fields);}
$proposal=new HostingDnsProposal($context,$records,['old-record-reference'],$secret);
dns_ok('Context separates opaque domain reference and canonical zone',$context->data['package_domain_id']==='allocated-domain-reference'&&$context->data['zone_name']==='zone.synthetic.invalid');
dns_ok('Context does not assert package-domain identity',$context->data['identity_status']==='unverified');
dns_ok('Proposal execution remains unavailable',$proposal->data['execution']==='unavailable');
dns_ok('Local proposal is not provider new/delete envelope',!isset($proposal->data['new'],$proposal->data['delete'])&&count($proposal->data['records'])===6);
dns_ok('Proposal digest matches exact reviewed document',hash('sha256',$proposal->json)===$proposal->sha256&&json_decode($proposal->json,true)===$proposal->data);
dns_ok('No credential retained in complete proposal',!str_contains($proposal->json,$secret)&&!str_contains($proposal->json,base64_encode($secret)));
dns_ok('Opaque identifier is not reinterpreted as URL grammar',(new HostingDnsContext($package,'local/opaque?identifier#part','zone.synthetic.invalid'))->data['package_domain_id']==='local/opaque?identifier#part');
dns_ok('Opaque numeric-looking string retains leading zeros',(new HostingDnsContext($package,'00123','zone.synthetic.invalid'))->data['package_domain_id']==='00123');
foreach($packageData as $key=>$value){$changed=$packageData;$changed[$key]=is_int($value)?$value+1:($value[0]==='a'?str_repeat('b',strlen($value)):str_repeat('a',strlen($value)));$other=new HostingDnsContext(new PackagePhpVersionBinding($changed),'allocated-domain-reference','zone.synthetic.invalid');dns_ok('Assignment witness changes context '.$key,$context->sha256!==$other->sha256);}
dns_ok('Domain identifier changes context',$context->sha256!==(new HostingDnsContext($package,'other-reference','zone.synthetic.invalid'))->sha256);
dns_ok('Zone name changes context',$context->sha256!==(new HostingDnsContext($package,'allocated-domain-reference','other.synthetic.invalid'))->sha256);
foreach(['json','sha256','scope'] as $field){$values=['data'=>$package->data,'json'=>$package->json,'sha256'=>$package->sha256,'scope'=>$package->scope];$values[$field].='PRIVATE_MARKER';$bad=dns_restored(PackagePhpVersionBinding::class,$values);dns_deny('Inconsistent restored package '.$field,fn()=>new HostingDnsContext($bad,'ref','zone.synthetic.invalid'));}
foreach(['json','sha256'] as $field){$values=['data'=>$context->data,'json'=>$context->json,'sha256'=>$context->sha256];$values[$field].='PRIVATE_MARKER';$bad=dns_restored(HostingDnsContext::class,$values);dns_deny('Inconsistent restored context '.$field,fn()=>new HostingDnsProposal($bad,$records,[],$secret));}
$changed=$context->data;$changed['identity_status']='verified';$bad=dns_restored(HostingDnsContext::class,['data'=>$changed,'json'=>json_encode($changed),'sha256'=>hash('sha256',json_encode($changed))]);dns_deny('Restored context cannot assert verified identity',fn()=>new HostingDnsProposal($bad,$records,[],$secret));
foreach(['',' whitespace','line\nend',str_repeat('x',129),"bad\x00id","bad\xff"] as $i=>$id){if($id==='line\nend')$id="line\nend";dns_deny('Bounded opaque identifier refusal '.$i,fn()=>HostingDnsContext::opaqueId($id));}
foreach(['UPPER.invalid','example.invalid.','single','192.0.2.1','https://host.invalid','*.example.invalid','a..invalid','-a.invalid','a-.invalid','xn--invalid_.invalid','café.invalid',str_repeat('a',64).'.invalid'] as $i=>$zone)dns_deny('Canonical zone refusal '.$i,fn()=>new HostingDnsContext($package,'ref',$zone));
foreach(['','*','www.','UPPER','a..b','a/b','a?b',"a\n",'-www','www-',str_repeat('a',64)] as $i=>$host)dns_deny('Relative host refusal '.$i,fn()=>new HostingDnsRecord('A',['host'=>$host,'ip'=>'192.0.2.1']));
foreach(['A'=>['192.0.2.999','2001:db8::1','192.0.2.1/24','192.0.2.01','host.invalid'],'AAAA'=>['192.0.2.1','2001:db8::1/64','fe80::1%eth0','2001::db8::1']] as $type=>$ips){foreach($ips as $i=>$ip){$fields=$valid[$type];$fields[$type==='A'?'ip':'ipv6']=$ip;dns_deny('Address family refusal '.$type.' '.$i,fn()=>new HostingDnsRecord($type,$fields));}}
foreach($valid as $type=>$fields){$missing=$fields;unset($missing['host']);dns_deny('Missing evidenced field '.$type,fn()=>new HostingDnsRecord($type,$missing));$extra=$fields;$extra['ttl']='300';dns_deny('Undocumented TTL rejected '.$type,fn()=>new HostingDnsRecord($type,$extra));$bad=$fields;$bad['host']=['PRIVATE_MARKER'];dns_deny('Array field rejected '.$type,fn()=>new HostingDnsRecord($type,$bad));}
foreach(['a','SOA','NS','CAA','PTR','PRIVATE_MARKER'] as $type)dns_deny('Unimplemented type rejected '.$type,fn()=>new HostingDnsRecord($type,['host'=>'www']));
foreach(['-1','65536','00','01','1.0','1e2',' 1','+1','99999999999999999999'] as $value){$fields=$valid['MX'];$fields['pri']=$value;dns_deny('Decimal review policy refusal '.$value,fn()=>new HostingDnsRecord('MX',$fields));}
foreach(['0','65535'] as $value){$fields=$valid['SRV'];$fields['pri']=$fields['weight']=$fields['port']=$value;dns_ok('Decimal review boundary '.$value,(new HostingDnsRecord('SRV',$fields))->fields===$fields);}
$fields=$valid['CNAME'];$fields['host']='@';dns_deny('Root CNAME refused',fn()=>new HostingDnsRecord('CNAME',$fields));
foreach(['.','target','UPPER.invalid','192.0.2.1','target.invalid.'] as $target){$fields=$valid['CNAME'];$fields['target']=$target;dns_deny('Narrow target policy refusal '.$target,fn()=>new HostingDnsRecord('CNAME',$fields));}
foreach(['',"line\nend","tab\tend","bad\xff",str_repeat('x',4097)] as $i=>$txt)dns_deny('Text review policy refusal '.$i,fn()=>new HostingDnsRecord('TXT',['host'=>'_txt','txt'=>$txt]));
dns_ok('UTF8 TXT retained without guessed wire chunking',(new HostingDnsRecord('TXT',['host'=>'_txt','txt'=>'café']))->fields['txt']==='café');
dns_ok('Local TXT upper bound retained',(new HostingDnsRecord('TXT',['host'=>'_txt','txt'=>str_repeat('x',4096)]))->fields['txt']===str_repeat('x',4096));
dns_deny('Empty proposal refused',fn()=>new HostingDnsProposal($context,[],[],$secret));
dns_deny('Associative record collection refused',fn()=>new HostingDnsProposal($context,['x'=>$records[0]],[],$secret));
dns_deny('Associative deletion collection refused',fn()=>new HostingDnsProposal($context,[],['x'=>'id'],$secret));
dns_deny('Raw untyped record refused',fn()=>new HostingDnsProposal($context,[$valid['A']],[],$secret));
dns_deny('Duplicate record refused',fn()=>new HostingDnsProposal($context,[$records[0],$records[0]],[],$secret));
dns_deny('Duplicate deletion reference refused',fn()=>new HostingDnsProposal($context,[],['same','same'],$secret));
dns_deny('Nonstring deletion reference refused',fn()=>new HostingDnsProposal($context,[],[123],$secret));
dns_ok('Deletion-only local review allowed',(new HostingDnsProposal($context,[],['opaque/delete?ref'],$secret))->data['records']===[]);
$atAlias=new HostingDnsRecord('A',['host'=>'alias','ip'=>'192.0.2.1']);
dns_deny('CNAME conflicts with earlier proposal record',fn()=>new HostingDnsProposal($context,[$atAlias,$records[2]],[],$secret));
dns_deny('CNAME conflicts with later proposal record',fn()=>new HostingDnsProposal($context,[$records[2],$atAlias],[],$secret));
$otherCname=new HostingDnsRecord('CNAME',['reuseId'=>'other','host'=>'alias','target'=>'other.invalid']);dns_deny('Two CNAME records on one proposed name refused',fn()=>new HostingDnsProposal($context,[$records[2],$otherCname],[],$secret));
dns_ok('Local multiaddress review does not infer wire array',(new HostingDnsProposal($context,[$records[0],new HostingDnsRecord('A',['host'=>'www','ip'=>'192.0.2.2'])],[],$secret))->data['execution']==='unavailable');
$longHost=str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.'.str_repeat('d',40);dns_deny('Zone plus relative host length refused',fn()=>new HostingDnsProposal($context,[new HostingDnsRecord('A',['host'=>$longHost,'ip'=>'192.0.2.1'])],[],$secret));
$many=[];for($i=0;$i<32;$i++)$many[]=new HostingDnsRecord('A',['host'=>'h'.$i,'ip'=>'192.0.2.1']);dns_ok('Local 32change bound',count((new HostingDnsProposal($context,$many,[],$secret))->data['records'])===32);dns_deny('Local 33change refusal',fn()=>new HostingDnsProposal($context,$many,['one-more'],$secret));
$large=[];for($i=0;$i<8;$i++)$large[]=new HostingDnsRecord('TXT',['host'=>'h'.$i,'txt'=>str_repeat('x',4096)]);dns_deny('Local total document bound',fn()=>new HostingDnsProposal($context,$large,[],$secret));
foreach(['raw'=>$secret,'encoded'=>base64_encode($secret),'decoded JSON'=>json_decode('"SYNTHETIC_DNS_PRIVATE_MARKER_\u004bEY"')] as $label=>$echo){dns_deny('Credential echo rejected '.$label,fn()=>new HostingDnsProposal($context,[new HostingDnsRecord('TXT',['host'=>'_txt','txt'=>'prefix '.$echo.' suffix'])],[],$secret));}
dns_deny('Credential deletion reference rejected',fn()=>new HostingDnsProposal($context,[],[$secret],$secret));
dns_deny('Credential context reference rejected',fn()=>new HostingDnsProposal(new HostingDnsContext($package,$secret,'zone.synthetic.invalid'),$records,[],$secret));
foreach(['',"bad\nkey",str_repeat('x',4097)] as $i=>$key)dns_deny('Invalid protected credential refused '.$i,fn()=>new HostingDnsProposal($context,$records,[],$key));
$fieldSource=$valid['A'];$fieldCopy=new HostingDnsRecord('A',$fieldSource);$fieldSource['ip']='192.0.2.9';dns_ok('Record input array is detached',$fieldCopy->fields['ip']==='192.0.2.1');
$externalIp='192.0.2.8';$referenceFields=['host'=>'ref','ip'=>&$externalIp];$referenceRecord=new HostingDnsRecord('A',$referenceFields);$referenceProposal=new HostingDnsProposal($context,[$referenceRecord],[],$secret);$externalIp='192.0.2.9';
dns_ok('Referenced record scalar is detached',$referenceRecord->fields['ip']==='192.0.2.8');
dns_ok('Referenced record cannot change reviewed proposal',json_decode($referenceProposal->json,true)===$referenceProposal->data&&$referenceProposal->data['records'][0]['fields']['ip']==='192.0.2.8');
$externalRevision=3;$referenceData=$package->data;$referenceData['profile_revision']=&$externalRevision;$referencePackage=new PackagePhpVersionBinding($referenceData);$referenceContext=new HostingDnsContext($referencePackage,'ref','zone.synthetic.invalid');$externalRevision=4;
dns_ok('Referenced assignment scalar is detached',$referenceContext->data['package']['profile_revision']===3&&json_decode($referenceContext->json,true)===$referenceContext->data);
dns_deny('Later inconsistent referenced package is rejected',fn()=>new HostingDnsContext($referencePackage,'ref','zone.synthetic.invalid'));
$restoredRecord=dns_restored(HostingDnsRecord::class,['type'=>'A','fields'=>['host'=>'www','ip'=>'PRIVATE_MARKER']]);dns_deny('Restored malformed record is revalidated',fn()=>new HostingDnsProposal($context,[$restoredRecord],[],$secret));
$packageData['profile_revision']=5;dns_ok('Package input is detached',$context->data['package']['profile_revision']===1);
dns_ok('Canonical field order independent of input order',(new HostingDnsProposal($context,[new HostingDnsRecord('A',array_reverse($valid['A'],true))],[],$secret))->sha256===(new HostingDnsProposal($context,[$records[0]],[],$secret))->sha256);
dns_ok('Changing reviewed record changes fingerprint',(new HostingDnsProposal($context,[$records[0]],[],$secret))->sha256!==(new HostingDnsProposal($context,[$atAlias],[],$secret))->sha256);
dns_ok('No request builder method',!method_exists(HostingDnsProposal::class,'request')&&!method_exists(HostingDnsContext::class,'request'));
dns_ok('No response or success classifier',!method_exists(HostingDnsProposal::class,'result')&&!method_exists(HostingDnsProposal::class,'project'));
$passed=!array_filter($checks,fn($c)=>!$c['passed']);
echo json_encode(['passed'=>$passed,'scope'=>'Pure local DNS proposal validation; no provider request, response schema, authority, persistence or application acceptance','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($passed?0:1);
