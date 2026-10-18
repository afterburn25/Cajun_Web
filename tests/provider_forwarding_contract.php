<?php
declare(strict_types=1);
require __DIR__.'/../foundation/twentyi-operations/ForwardingRequest.php';
require __DIR__.'/../foundation/twentyi-operations/TwentyIOperationRegister.php';
use CajunWebPro\Foundation\TwentyI\Operations\{ForwardingRequest,TwentyIOperationRegister};
$checks=[];function fw_check(string $n,bool $ok):void{global $checks;$checks[]=['name'=>$n,'passed'=>$ok];if(!$ok)throw new RuntimeException($n);}
try{
$p=['packageId'=>2706442,'domain'=>'20itesting.com','local'=>'enquiries','remote'=>'testing@20i.com'];$r=ForwardingRequest::request($p);
fw_check('Official screenshot method and API endpoint match exact typed request',$r->method==='POST'&&$r->url==='https://api.20i.com/package/2706442/email/20itesting.com');
fw_check('Exact nested single forward payload has no invented response or mailbox fields',$r->body==='{"new":{"forward":{"local":"enquiries","remote":"testing@20i.com"}}}');
try{\CajunWebPro\Foundation\TwentyI\Operations\MailboxCreate::wire($r);fw_check('Existing mailbox wire admission rejects forwarding payload',false);}catch(InvalidArgumentException){fw_check('Existing mailbox wire admission rejects forwarding payload',true);}
fw_check('Request advertises JSON without embedded authentication',$r->headers===['Content-Type'=>'application/json','Accept'=>'application/json']);
foreach(['wildcard'=>['local'=>'*'],'multiple destinations'=>['remote'=>'a@example.com,b@example.com'],'self forwarding'=>['remote'=>'enquiries@20itesting.com'],'display name'=>['remote'=>'Person <a@example.com>'],'uppercase'=>['remote'=>'A@example.com'],'newline'=>['remote'=>"a@example.com\n"],'extra field'=>['delete'=>true],'package string'=>['packageId'=>'2706442'],'path domain'=>['domain'=>'example.com/escape'],'empty local'=>['local'=>'']] as $label=>$change){try{ForwardingRequest::request(array_replace($p,$change));fw_check('Reject '.$label,false);}catch(InvalidArgumentException|TypeError){fw_check('Reject '.$label,true);}}
$register=file_get_contents(__DIR__.'/../foundation/twentyi-operations/TwentyIOperationRegister.php');$adapter=file_get_contents(__DIR__.'/../foundation/twentyi-operations/TwentyIOperationAdapter.php');$transport=file_get_contents(__DIR__.'/../foundation/twentyi/FixedHttpTransport.php');fw_check('Forwarding is not admitted to registry adapter or network transport',!str_contains($register,'ForwardingRequest')&&!str_contains($adapter,'ForwardingRequest')&&!str_contains($transport,'ForwardingRequest'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,$e->__toString().PHP_EOL);}
@mkdir(__DIR__.'/../qa',0700,true);$ok=!array_filter($checks,fn($r)=>!$r['passed']);file_put_contents(__DIR__.'/../qa/provider-forwarding-contract-results.json',json_encode(['passed'=>$ok,'scope'=>'Offline typed official forwarding request only; no provider dispatch or response claims','checks'=>$checks],JSON_PRETTY_PRINT));echo ($ok?'PASS':'FAIL').': '.count($checks).' forwarding contract assertions'.PHP_EOL;exit($ok?0:1);
