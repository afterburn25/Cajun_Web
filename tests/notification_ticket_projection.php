<?php
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
function studio_url(array $query=[]):string {return 'portal?'.http_build_query($query);}
function admin_url(string $view,array $query=[]):string {return 'admin?'.http_build_query(['view'=>$view]+$query);}
require_once CWP_CODE_ROOT.'/app/notifications/events.php';

$checks=0;$failures=[];
function ntp_check(string $name,bool $ok):void {global $checks,$failures;$checks++;if(!$ok)$failures[]=$name;}
function ntp_throws(string $name,callable $work):void {try{$work();ntp_check($name,false);}catch(InvalidArgumentException){ntp_check($name,true);}}
$ticket=str_repeat('a',32);$event=str_repeat('b',32);

$staff=notification_ticket_projection($ticket,7,$event,'staff','reply');
ntp_check('staff reply targets owning client',$staff['audience']==='client'&&$staff['recipient_id']===7&&$staff['permission']===null);
ntp_check('staff reply uses typed ticket resource',$staff['ref']==='ticket:'.$ticket&&$staff['kind']==='support-reply');
ntp_check('staff reply binds immutable event id',$staff['event_key']==='ticket-event:'.$event);
ntp_check('client payload contains no conversation content',!str_contains($staff['title'],'Subject')&&!str_contains($staff['body'],'secret'));

$client=notification_ticket_projection($ticket,7,$event,'client','opened');
ntp_check('client opening targets current ticket staff',$client['audience']==='staff'&&$client['recipient_id']===null&&$client['permission']==='support.tickets');
ntp_check('staff payload stays generic',$client['title']==='New support conversation'&&$client['body']==='A client support conversation has new activity.');
ntp_check('support-session reply is suppressed',notification_ticket_projection($ticket,7,$event,'support','reply')===[]);
ntp_check('private note is suppressed',notification_ticket_projection($ticket,7,$event,'staff','note')===[]);
ntp_check('assignment is suppressed',notification_ticket_projection($ticket,7,$event,'staff','assign')===[]);

$parameters=array_map(static fn(ReflectionParameter $p)=>$p->getName(),(new ReflectionFunction('notification_ticket_projection'))->getParameters());
ntp_check('projection accepts identities only',$parameters===['ticket','client','event','actor','command']);
ntp_check('client ticket destination uses current portal route',notification_url('client','ticket:'.$ticket)==='portal?ticket='.$ticket);
ntp_check('staff ticket destination uses current desk route',notification_url('staff','ticket:'.$ticket)==='admin?view=ticket&ticket='.$ticket);
ntp_check('malformed ticket destination fails closed',notification_url('client','ticket:bad!')==='#');
ntp_throws('invalid ticket rejected',fn()=>notification_ticket_projection('bad',7,$event,'staff','reply'));
ntp_throws('invalid event rejected',fn()=>notification_ticket_projection($ticket,7,'bad','staff','reply'));
ntp_throws('invalid client rejected',fn()=>notification_ticket_projection($ticket,0,$event,'staff','reply'));
ntp_throws('invalid actor rejected',fn()=>notification_ticket_projection($ticket,7,$event,'contact','reply'));
ntp_throws('invalid command rejected',fn()=>notification_ticket_projection($ticket,7,$event,'staff','delete'));

if($failures){fwrite(STDERR,'FAIL: '.count($failures).' of '.$checks." checks\n - ".implode("\n - ",$failures)."\n");exit(1);}
echo 'PASS: '.$checks." ticket notification projection checks\n";
