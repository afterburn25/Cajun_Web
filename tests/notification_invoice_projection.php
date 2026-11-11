<?php
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require_once CWP_CODE_ROOT.'/app/notifications/events.php';

$checks=0;$failures=[];
function nip_check(string $name,bool $ok):void {global $checks,$failures;$checks++;if(!$ok)$failures[]=$name;}
function nip_throws(string $name,callable $work):void {try{$work();nip_check($name,false);}catch(InvalidArgumentException){nip_check($name,true);}}

$plan=notification_invoice_projection(7,31,9);
nip_check('issued invoice targets owning client',$plan['audience']==='client'&&$plan['recipient_id']===9&&$plan['kind']==='billing');
nip_check('issued invoice uses current resource gate',$plan['ref']==='invoice:31');
nip_check('issued invoice binds immutable identity',$plan['event_key']==='invoice-issued:31');
nip_check('issued invoice payload stays generic',$plan['title']==='New invoice available'&&$plan['body']==='Sign in to review the new invoice in your account.');
$parameters=array_map(static fn(ReflectionParameter $p)=>$p->getName(),(new ReflectionFunction('notification_invoice_projection'))->getParameters());
nip_check('projection accepts identities only',$parameters===['job','invoice','client']);
nip_throws('invalid job rejected',fn()=>notification_invoice_projection(0,31,9));
nip_throws('invalid invoice rejected',fn()=>notification_invoice_projection(7,0,9));
nip_throws('invalid client rejected',fn()=>notification_invoice_projection(7,31,0));

if($failures){fwrite(STDERR,'FAIL: '.count($failures).' of '.$checks." checks\n - ".implode("\n - ",$failures)."\n");exit(1);}
echo 'PASS: '.$checks." invoice notification projection checks\n";
