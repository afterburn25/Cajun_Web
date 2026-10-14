<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/hosting/HostingAccount.php';
use CajunWebPro\Foundation\Hosting\{HostingAccount,HostingPackage,OperationResult};
$checks=[];
function hi_check(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];}
function hi_denied(callable $work):bool {try{$work();return false;}catch(InvalidArgumentException|LogicException){return true;}}
function hi_package(string $sku='plan'):HostingPackage {return new HostingPackage($sku,'Plan','type','region',['disk_mb'=>20]);}
function hi_active():HostingAccount {$a=new HostingAccount('input-account',hi_package(),'client',100);$a->beginProvisioning('staff',101);$a->provisionResult(OperationResult::applied(HostingAccount::OP_PROVISION,'receipt',102,$a->id,'resource',$a->pendingAttempt()),'staff',102);return $a;}
foreach(["plan\n","plan\r\n","plan\0",' plan','plan/other','plan😀'] as $i=>$bad)hi_check('Reject exact SKU syntax '.$i,hi_denied(fn()=>hi_package($bad)));
foreach(['plan','Plan_1.2-test'] as $i=>$good)hi_check('Keep valid SKU '.$i,hi_package($good)->sku===$good);
foreach(["example.com\n","example.com\r\n",'first..example.com','example-.com','a.-b.com',str_repeat('a',64).'.com',str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.'.str_repeat('d',62).'.com','example.com.','example.c','example.123','https://example.com'] as $i=>$bad) {
    $a=hi_active();$before=serialize($a);
    hi_check('Malformed domain rejected without any state or history change '.$i,hi_denied(fn()=>$a->linkDomain($bad,'staff',103))&&serialize($a)===$before);
}
foreach(['EXAMPLE.COM','a-b.example.com',str_repeat('a',63).'.com',str_repeat('a',63).'.'.str_repeat('b',63).'.'.str_repeat('c',63).'.'.str_repeat('d',57).'.com'] as $i=>$good) {
    $a=hi_active();$a->linkDomain($good,'staff',103);hi_check('Valid bounded ASCII domain retained '.$i,$a->primaryDomain()===strtolower($good)&&$a->status()===HostingAccount::ACTIVE&&count($a->history())===4);
}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);echo json_encode(['passed'=>$passed,'scope'=>'Synthetic exact SKU/domain input and no-mutation boundaries only; no provider or persistence','checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";exit($passed?0:1);
