<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/mail-transport/EndpointPolicy.php';
use CajunWebPro\Foundation\MailTransport\EndpointPolicy as Policy;
$results=[];
function check(string $name,bool $ok): void {global $results;$results[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function rejects(string $name,callable $work): void {try {$work();}catch(InvalidArgumentException) {check($name,true);return;}check($name,false);}
foreach (['0.0.0.0','0.255.255.255','10.0.0.1','100.64.0.0','100.127.255.255','127.255.255.255','169.254.1.2','172.16.0.0','172.31.255.255','192.0.0.9','192.0.2.1','192.88.99.1','192.168.1.1','198.18.0.1','198.19.255.255','198.51.100.1','203.0.113.1','224.0.0.1','239.255.255.255','240.0.0.1','255.255.255.255','::','::1','::ffff:127.0.0.1','::ffff:8.8.8.8','64:ff9b::7f00:1','64:ff9b:1::1','100::1','100:0:0:1::1','2001::1','2001:1ff:ffff::1','2001:db8::1','2002:7f00:1::1','3fff::1','3fff:fff:ffff::1','5f00::1','fc00::1','fdff:ffff::1','fe80::1','ff02::1','fe80::1%eth0','[::1]','2130706433','0177.0.0.1','0x7f000001','127.1',"8.8.8.8\n",'8.8.8.8:993'] as $address) check('Reject non-public/ambiguous destination '.$address,!Policy::publicAddress($address));
check('Benchmarking 2001:2::/48 is excluded by the whole 2001::/23 range',!Policy::publicAddress('2001:2::1'));
foreach (['8.8.8.8','1.1.1.1','100.63.255.255','100.128.0.0','172.15.255.255','172.32.0.0','198.17.255.255','198.20.0.0','223.255.255.255','2001:4860:4860::8888','2606:4700:4700::1111','2001:200::1','3ffe:ffff::1','3fff:1000::1'] as $address) check('Accept public unicast syntax '.$address,Policy::publicAddress($address));
foreach (['localhost','mail.localhost','mail.local','mail.internal','mail.home','mail.test','mail.invalid','mail.example','mail.onion','127.0.0.1','8.8.8.8','::1','[::1]','smtp://mail.example.com','a..example.com','mail.example.com.','mail.example.com/path','mail.example.com:993',"mail.example.com\n",'-a.example.com','a-.example.com','a_b.example.com','user@mail.example.com','a'.str_repeat('b',63).'.example.com'] as $host) rejects('Reject forbidden hostname '.$host,fn()=>Policy::host($host));
check('Normalize hostname case only',Policy::host('Mail.Example.COM')==='mail.example.com');
foreach ([['imap',993,'ssl'],['imap',143,'starttls'],['smtp',465,'ssl'],['smtp',587,'starttls']] as $tuple) {Policy::port(...$tuple);check('Allow standard encrypted mode '.implode('/',$tuple),true);}
foreach ([['imap',993,'starttls'],['smtp',587,'ssl'],['smtp',25,'starttls'],['smtp',465,'plain'],['http',443,'ssl'],['imap',0,'ssl']] as $tuple) rejects('Reject port/mode mismatch '.implode('/',$tuple),fn()=>Policy::port(...$tuple));
rejects('Reject mixed public and private DNS answers',fn()=>Policy::addresses(['8.8.8.8','127.0.0.1']));
rejects('Reject mixed IPv4 and unsafe IPv6',fn()=>Policy::addresses(['8.8.8.8','::ffff:8.8.8.8']));
rejects('Reject no DNS answers',fn()=>Policy::addresses([]));
rejects('Reject oversized answer set',fn()=>Policy::addresses(array_fill(0,33,'8.8.8.8')));
rejects('Reject non-string address',fn()=>Policy::addresses([2130706433]));
check('Deduplicate normalized addresses',Policy::addresses(['8.8.8.8','8.8.8.8','2606:4700:4700:0:0:0:0:1111','2606:4700:4700::1111'])===['8.8.8.8','2606:4700:4700::1111']);
echo json_encode(['passed'=>true,'checks'=>$results],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
