<?php
declare(strict_types=1);
define('CWP_BOOT',true);
define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require_once CWP_CODE_ROOT.'/app/notifications/events.php';

$checks=0;$failures=[];
function npp_check(string $name,bool $ok):void {global $checks,$failures;$checks++;if(!$ok)$failures[]=$name;}
function npp_throws(string $name,callable $work):void {try{$work();npp_check($name,false);}catch(InvalidArgumentException){npp_check($name,true);}}
$file=str_repeat('a',32);

$created=notification_project_projection(7,9,'created:7','staff');
npp_check('project creation targets owning client',$created['audience']==='client'&&$created['recipient_id']===9&&$created['permission']===null);
npp_check('project creation binds immutable job identity',$created['ref']==='job:7'&&$created['event_key']==='project-created:7');
npp_check('project creation stays generic',$created['title']==='Project workspace opened'&&$created['body']==='Sign in to review your project workspace.');

$version=notification_project_projection(7,9,'version:31','staff');
npp_check('pending version uses approval kind',$version['kind']==='approval-request'&&$version['event_key']==='version-ready:31');
npp_check('pending version stays generic',$version['title']==='Project review ready'&&!str_contains($version['body'],'title'));

$staffFile=notification_project_projection(7,9,'file:'.$file,'staff');
npp_check('staff file targets owning client',$staffFile['audience']==='client'&&$staffFile['recipient_id']===9&&$staffFile['kind']==='project-update');
npp_check('staff file contains no filename',$staffFile['title']==='New project file'&&!str_contains($staffFile['body'],'.pdf'));
$clientFile=notification_project_projection(7,9,'file:'.$file,'client');
npp_check('client file targets current project staff',$clientFile['audience']==='staff'&&$clientFile['recipient_id']===null&&$clientFile['permission']==='clients.manage');
npp_check('contact file uses the same staff boundary',notification_project_projection(7,9,'file:'.$file,'contact')===$clientFile);
npp_check('support file is suppressed',notification_project_projection(7,9,'file:'.$file,'support')===[]);

$parameters=array_map(static fn(ReflectionParameter $p)=>$p->getName(),(new ReflectionFunction('notification_project_projection'))->getParameters());
npp_check('projection accepts identities only',$parameters===['job','client','event','actor']);
npp_throws('invalid job rejected',fn()=>notification_project_projection(0,9,'created:7','staff'));
npp_throws('invalid client rejected',fn()=>notification_project_projection(7,0,'created:7','staff'));
npp_throws('invalid event rejected',fn()=>notification_project_projection(7,9,'version:bad','staff'));
npp_throws('noncanonical event identity rejected',fn()=>notification_project_projection(7,9,'created:07','staff'));
npp_throws('project creation identity must match resource',fn()=>notification_project_projection(7,9,'created:8','staff'));
npp_throws('client cannot claim project creation',fn()=>notification_project_projection(7,9,'created:7','client'));
npp_throws('contact cannot claim version publication',fn()=>notification_project_projection(7,9,'version:31','contact'));
npp_throws('unknown actor rejected',fn()=>notification_project_projection(7,9,'file:'.$file,'other'));

if($failures){fwrite(STDERR,'FAIL: '.count($failures).' of '.$checks." checks\n - ".implode("\n - ",$failures)."\n");exit(1);}
echo 'PASS: '.$checks." project notification projection checks\n";
