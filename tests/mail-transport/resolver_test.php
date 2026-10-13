<?php
declare(strict_types=1);
foreach(['EndpointPolicy','ProbeFailure','EndpointResolver'] as $file)require __DIR__.'/../../foundation/mail-transport/'.$file.'.php';
use CajunWebPro\Foundation\MailTransport\EndpointResolver as Resolver;
$checks=[];
function r_ok(string $name,bool $ok): void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function r_deny(string $name,callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException){r_ok($name,true);return;}r_ok($name,false);}
$calls=[];$answer=Resolver::resolve('MAIL.Example.net',function($host)use(&$calls){$calls[]=$host;return [['type'=>'A','ip'=>'8.8.8.8'],['type'=>'AAAA','ipv6'=>'2606:4700:4700::1111']];});
r_ok('Resolve original hostname once and retain safe addresses',$calls===['mail.example.net']&&$answer===['8.8.8.8','2606:4700:4700::1111']);
$calls=[];$answer=Resolver::resolve('mail.example.net',function($host)use(&$calls){$calls[]=$host;return $host==='mail.example.net'?[['type'=>'CNAME','target'=>'Alias.Example.net.']]:[['type'=>'A','ip'=>'8.8.8.8']];});
r_ok('Resolve a normalized CNAME target without losing original transport identity',$calls===['mail.example.net','alias.example.net']&&$answer===['8.8.8.8']);
r_deny('Mixed DNS answers fail closed',fn()=>Resolver::resolve('mail.example.net',fn()=>[['type'=>'A','ip'=>'8.8.8.8'],['type'=>'AAAA','ipv6'=>'::1']]));
r_deny('CNAME cannot redirect to local hostname',fn()=>Resolver::resolve('mail.example.net',fn()=>[['type'=>'CNAME','target'=>'mail.localhost.']]));
r_deny('CNAME cycle fails closed',fn()=>Resolver::resolve('mail.example.net',fn()=>[['type'=>'CNAME','target'=>'mail.example.net.']]));
$count=0;r_deny('CNAME depth is bounded',function()use(&$count){Resolver::resolve('mail.example.net',function()use(&$count){return [['type'=>'CNAME','target'=>'mail'.++$count.'.example.net']];});});r_ok('Resolver depth performs only eight lookups',$count===8);
r_deny('Conflicting CNAME answers fail closed',fn()=>Resolver::resolve('mail.example.net',fn()=>[['type'=>'CNAME','target'=>'one.example.net'],['type'=>'CNAME','target'=>'two.example.net']]));
r_deny('Empty DNS result fails closed',fn()=>Resolver::resolve('mail.example.net',fn()=>[]));
r_deny('Resolver false fails closed',fn()=>Resolver::resolve('mail.example.net',fn()=>false));
r_deny('Malformed DNS address fails closed',fn()=>Resolver::resolve('mail.example.net',fn()=>[['type'=>'A','ip'=>123]]));
r_deny('Large DNS answer list is bounded',fn()=>Resolver::resolve('mail.example.net',fn()=>array_fill(0,65,['type'=>'A','ip'=>'8.8.8.8'])));
try{Resolver::resolve('mail.example.net',fn()=>throw new RuntimeException('Sensitive resolver output'));$sanitized=false;}catch(RuntimeException $e){$sanitized=$e->getMessage()==='connection'&&$e->getPrevious()===null;}
r_ok('Resolver exceptions have no remote text or exception chain',$sanitized);
echo json_encode(['passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
