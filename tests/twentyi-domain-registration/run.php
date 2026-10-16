<?php
declare(strict_types=1);
use CajunWebPro\Foundation\TwentyIDomainRegistration\{RegistrationContact,RegistrationDraft,RegistrationContract};
use CajunWebPro\Foundation\TwentyI\Operations\{TwentyIOperation,TwentyIOperationRegister};
require __DIR__.'/../../foundation/twentyi-domain-registration/RegistrationContract.php';
require __DIR__.'/../../foundation/twentyi-operations/TwentyIOperation.php';
require __DIR__.'/../../foundation/twentyi-operations/TwentyIOperationRegister.php';
$checks=[];
function ok(string $name,bool $value):void {global $checks;$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}
function denies(callable $f):bool {try{$f();return false;}catch(InvalidArgumentException){return true;}}
function draft():array {return ['version'=>1,'id'=>str_repeat('a',32),'client_id'=>7,'domain'=>'purchase.example.com','requested_years'=>2,'privacy_service'=>false,'contact'=>['organisation'=>'Synthetic Studio','name'=>'Synthetic Registrant','address'=>'1 Synthetic Street','telephone'=>'+1.2025550100','email'=>'registrant@example.invalid','cc'=>'US','pc'=>'00000','sp'=>'State','city'=>'Fixture City','extension'=>[]],'nameservers'=>['ns1.example.invalid','ns2.example.invalid'],'registry_acceptance'=>null];}
try {
    $input=draft();$d=RegistrationDraft::fromArray($input);$json=$d->privateJson();
    ok('Exact private canonical document round trip',RegistrationDraft::fromPrivateJson($json)->privateJson()===$json);
    $reordered=array_reverse($input,true);$reordered['contact']=array_reverse($input['contact'],true);
    ok('Caller map order does not change immutable scope',RegistrationDraft::fromArray($reordered)->fingerprint()===$d->fingerprint());
    ok('Fingerprint is exact private bytes equality only',$d->fingerprint()===hash('sha256',$json));
    ok('Selection keeps requested term and false privacy explicit',$d->selection()===['domain'=>'purchase.example.com','requested_years'=>2,'privacy_service'=>false,'nameservers'=>['ns1.example.invalid','ns2.example.invalid'],'ca_acceptance_recorded'=>false]);
    ok('Documented review fields contain no invented registration years',$d->documentedFields()===['name'=>'purchase.example.com','contact'=>$input['contact'],'nameservers'=>$input['nameservers'],'privacyService'=>false]);
    ok('No implied CA registry agreement for ordinary domain',!array_key_exists('caRegistryAgreement',$d->documentedFields()));
    ok('Term and response contract missing always prevent dispatch',!RegistrationContract::canDispatch()&&in_array('registration_term_wire_contract',RegistrationContract::prerequisites($d),true)&&in_array('registration_response_contract',RegistrationContract::prerequisites($d),true));
    ok('No local price or invoice silently enters registration document',!array_key_exists('amount',$d->privateDocument())&&!array_key_exists('paid',$d->privateDocument()));
    foreach(['usd_amount'=>2499,'currency'=>'USD','years'=>2,'resellerId'=>'*','stackUser'=>'x','otherContacts'=>[],'limits'=>[],'authcode'=>'x','emulateYears'=>true,'caRegistryAgreement'=>true] as $key=>$value){
        $bad=$input;$bad[$key]=$value;ok('Unsupported or commercial top-level field refused '.$key,denies(fn()=>RegistrationDraft::fromArray($bad)));
    }
    foreach(array_keys($input) as $key){$bad=$input;unset($bad[$key]);ok('Missing immutable draft field refused '.$key,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach(['UPPER.example.com','example.com.',' example.com','example.com/path','example.com?x=1','example.com#x','example..com','-x.com','x-.com','localhost','192.0.2.1','bücher.example','xn--bcher-kva.example','host.xn--p1ai',str_repeat('a',64).'.com',str_repeat('a.',127).'com'] as $i=>$name){$bad=$input;$bad['domain']=$name;ok('Noncanonical or IDN registration refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach([null,true,0,1.0,[],(object)[]] as $i=>$name){$bad=$input;$bad['domain']=$name;ok('Nonstring registration name refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach([0,-1,11,'2',2.0,true,null] as $i=>$value){$bad=$input;$bad['requested_years']=$value;ok('Invalid local requested term refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach([1,10] as $years){$in=$input;$in['requested_years']=$years;$v=RegistrationDraft::fromArray($in);ok('Local requested term bound never enters provider fields '.$years,$v->selection()['requested_years']===$years&&!isset($v->documentedFields()['years'])&&$v->fingerprint()!==$d->fingerprint());}
    foreach([null,0,1,'true','false',[]] as $i=>$value){$bad=$input;$bad['privacy_service']=$value;ok('Undecided or coerced privacy refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    $in=$input;$in['privacy_service']=true;$yes=RegistrationDraft::fromArray($in);ok('Chargeable privacy selection changes immutable scope',$yes->fingerprint()!==$d->fingerprint()&&$yes->documentedFields()['privacyService']===true);
    foreach([0,-1,'7',7.0,true,1000000000000000000] as $i=>$value){$bad=$input;$bad['client_id']=$value;ok('Invalid direct client identifier refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach(['',str_repeat('A',32),str_repeat('a',31),str_repeat('g',32),true,[]] as $i=>$value){$bad=$input;$bad['id']=$value;ok('Invalid immutable draft identifier refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach([[],['ns1.example.invalid','ns1.example.invalid'],['x'=>'ns1.example.invalid'],['Ns1.example.invalid'],[null],array_fill(0,14,'ns1.example.invalid')] as $i=>$value){$bad=$input;$bad['nameservers']=$value;ok('Invalid or duplicate explicit nameservers refused '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    $in=$input;$in['nameservers']=array_reverse($input['nameservers']);ok('Explicit nameserver order is preserved rather than assumed immaterial',RegistrationDraft::fromArray($in)->fingerprint()!==$d->fingerprint());
    $contact=$input['contact'];
    foreach(array_keys($contact) as $key){$bad=$contact;unset($bad[$key]);ok('Missing documented contact field refused '.$key,denies(fn()=>RegistrationContact::fromArray($bad)));}
    foreach(['password','years','billing','authcode'] as $key){$bad=$contact;$bad[$key]='synthetic-private';ok('Undocumented contact field refused '.$key,denies(fn()=>RegistrationContact::fromArray($bad)));}
    foreach(['name','address','telephone','email','cc','city'] as $key){$bad=$contact;$bad[$key]='';ok('Empty locally required contact text refused '.$key,denies(fn()=>RegistrationContact::fromArray($bad)));}
    foreach(['organisation','pc','sp'] as $key){$in=$contact;$in[$key]='';ok('Optional local contact text may be explicitly empty '.$key,RegistrationContact::fromArray($in)->privateFields()[$key]==='');}
    foreach([null,1,true,[],(object)[],"bad\nline","bad\0text","bad\x7ftext","\xff",' trailing ',str_repeat('x',201)] as $i=>$value){$bad=$contact;$bad['name']=$value;ok('Malformed bounded contact text refused '.$i,denies(fn()=>RegistrationContact::fromArray($bad)));}
    foreach(['us','USA','1A','',null] as $i=>$value){$bad=$contact;$bad['cc']=$value;ok('Country syntax requires explicit uppercase two letters '.$i,denies(fn()=>RegistrationContact::fromArray($bad)));}
    foreach(['bad','a@','a b@example.invalid',"a@example.invalid\r\nX: y"] as $i=>$value){$bad=$contact;$bad['email']=$value;ok('Malformed contact email refused '.$i,denies(fn()=>RegistrationContact::fromArray($bad)));}
    foreach(RegistrationContact::EXTENSIONS as $key){$in=$contact;$in['extension']=[$key=>'documented bounded value'];ok('Documented extension field retained without invented value semantics '.$key,RegistrationContact::fromArray($in)->privateFields()['extension']===[$key=>'documented bounded value']);}
    foreach([null,true,'x',['unknown'=>'x'],['type'=>true],['type'=>null],['type'=>[]],['type'=>''],['type'=>"x\n"],['type'=>str_repeat('x',513)],[0=>'x']] as $i=>$value){$bad=$contact;$bad['extension']=$value;ok('Malformed or unknown contact extension refused '.$i,denies(fn()=>RegistrationContact::fromArray($bad)));}
    $in=$contact;$in['extension']=['type'=>'IND','co-no'=>'123'];$a=RegistrationContact::fromArray($in);$in['extension']=array_reverse($in['extension'],true);ok('Registry extension map canonical ordering is stable',$a->privateFields()===RegistrationContact::fromArray($in)->privateFields());
    $ca=$input;$ca['domain']='registry.example.ca';$caDraft=RegistrationDraft::fromArray($ca);
    ok('CA draft without acceptance cannot silently assert agreement',in_array('direct_registrant_ca_agreement',RegistrationContract::prerequisites($caDraft),true)&&!isset($caDraft->documentedFields()['caRegistryAgreement']));
    $receipt=['domain'=>$ca['domain'],'accepted_by'=>'client:7','accepted_epoch'=>1800000000,'terms_sha256'=>str_repeat('b',64)];$ca['registry_acceptance']=$receipt;$caAccepted=RegistrationDraft::fromArray($ca);
    ok('Explicit matching CA record alone projects documented true flag',$caAccepted->documentedFields()['caRegistryAgreement']===true&&!in_array('direct_registrant_ca_agreement',RegistrationContract::prerequisites($caAccepted),true)&&!RegistrationContract::canDispatch());
    foreach(['accepted_by'=>'client:8','domain'=>'other.example.ca','accepted_epoch'=>0,'terms_sha256'=>'bad','extra'=>true] as $key=>$value){$bad=$ca;$bad['registry_acceptance'][$key]=$value;ok('CA receipt wrong or malformed scope refused '.$key,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    foreach(['staff:7','7',7,true,null] as $i=>$actor){$bad=$ca;$bad['registry_acceptance']['accepted_by']=$actor;ok('CA agreement never coerces actor identity '.$i,denies(fn()=>RegistrationDraft::fromArray($bad)));}
    $bad=$input;$bad['registry_acceptance']=$receipt;ok('CA agreement cannot be silently attached to another registry',denies(fn()=>RegistrationDraft::fromArray($bad)));
    $id=$input['id'];$who=$contact['name'];$ns='ns1.example.invalid';$ext='IND';$ref=$input;$ref['id']=&$id;$ref['contact']['name']=&$who;$ref['nameservers'][0]=&$ns;$ref['contact']['extension']=['type'=>&$ext];$detached=RegistrationDraft::fromArray($ref);$before=$detached->privateJson();$id=str_repeat('f',32);$who='changed';$ns='changed.example.invalid';$ext='OTHER';ok('Nested caller references cannot mutate captured scope',$detached->privateJson()===$before);
    $copy=$d->privateDocument();$copy['contact']['name']='changed';$copy['nameservers'][]='ns3.example.invalid';ok('Explicit exports cannot mutate immutable original',$d->privateJson()===$json);
    foreach([$json.' ',str_replace('"version":1','"version":1,"version":1',$json),str_replace('"requested_years":2','"requested_years":2.0',$json),'null','[]','{}','bad',str_repeat('x',32769)] as $i=>$bad)ok('Noncanonical or duplicate JSON cannot recreate draft '.$i,denies(fn()=>RegistrationDraft::fromPrivateJson($bad)));
    foreach([$d,RegistrationContact::fromArray($contact)] as $i=>$object){
        ob_start();var_dump($object);$debug=ob_get_clean();$printed=print_r($object,true);$auto=json_encode($object);
        ok('Normal automatic diagnostics redact private contact '.$i,!str_contains($debug,$contact['email'])&&!str_contains($printed,$contact['address'])&&!str_contains($auto,$contact['name'])&&str_contains($auto,'redacted'));
        try{serialize($object);$refused=false;}catch(LogicException){$refused=true;}ok('Implicit PHP serialization of private record refused '.$i,$refused);
    }
    foreach([200,201,204,400,403,409,422,429,500] as $status)foreach(['true','false','null','{"result":true}','{"id":123}','synthetic credential and contact echo'] as $i=>$body){$out=RegistrationContract::unverifiedResponse($status,$body);ok('Unverified registration response never becomes completion or retry '.$status.'/'.$i,$out===['state'=>'unverified','reason'=>'registration_response_contract_missing','may_retry'=>false,'charge_proven_absent'=>false,'registered'=>null]&&!str_contains(json_encode($out),'synthetic'));}
    $register=new TwentyIOperationRegister();foreach(['domains.register','domains.transfer'] as $operation){ok('Existing operation stays shape-incomplete '.$operation,$register->get($operation)->shapeComplete===false);ok('Generic approval remains excluded '.$operation,!$register->allowsGenericApproval($operation,['resellerId'=>'*','name'=>'purchase.example.com']));}
    foreach([RegistrationContact::class,RegistrationDraft::class,RegistrationContract::class] as $class){$methods=get_class_methods($class);ok('Pure component contains no sender or credential interface '.substr($class,strrpos($class,'\\')+1),!array_intersect($methods,['send','read','request','execute','resolve','credential','build']));}
    ok('Operation route is a source fact only',RegistrationContract::OPERATION==='domains.register'&&RegistrationContract::METHOD==='POST'&&RegistrationContract::ROUTE==='/reseller/*/addDomain');
    ok('All pure outcome labels are distinct',count($checks)===count(array_unique(array_column($checks,'name'))));
    echo json_encode(['passed'=>true,'scope'=>'Pure private registration draft/contact and missing-contract boundaries only. No request, registry acceptance, billing, native database, browser or live provider evidence.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){echo json_encode(['passed'=>false,'checks'=>$checks,'error'=>get_class($e),'failed_case'=>$checks?end($checks)['name']:'setup'],JSON_PRETTY_PRINT).PHP_EOL;exit(1);}

