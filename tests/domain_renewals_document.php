<?php
declare(strict_types=1);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',realpath(__DIR__.'/../site'));require CWP_CODE_ROOT.'/app/providers/domain_renewals_document.php';
$checks=[];function rn_check(string $name,bool $value):void{global $checks;$checks[]=['name'=>$name,'passed'=>$value];if(!$value)throw new RuntimeException($name);}function rn_refuse(string $name,callable $f):void{try{$f();}catch(InvalidArgumentException|RuntimeException){rn_check($name,true);return;}rn_check($name,false);}
$d=['version'=>1,'id'=>str_repeat('a',32),'client_id'=>1,'link_id'=>str_repeat('b',32),'link_revision'=>2,'profile_id'=>str_repeat('c',32),'profile_revision'=>3,'key_fingerprint'=>str_repeat('d',64),'domain_id'=>701,'domain'=>'one.example','refresh_id'=>str_repeat('e',32),'requested_years'=>2,'renew_privacy'=>null,'created_epoch'=>1700000000];
try{
 rn_check('Typed local request preserves exact original fields',domain_renewals_document($d)===$d);
 foreach([null,true,false] as $privacy){$x=$d;$x['renew_privacy']=$privacy;rn_check('Privacy preference remains distinct '.var_export($privacy,true),domain_renewals_document($x)['renew_privacy']===$privacy);}
 foreach(['id'=>'wrong','client_id'=>0,'link_revision'=>0,'profile_revision'=>0,'key_fingerprint'=>'private','domain_id'=>'701','domain'=>'ONE.example','requested_years'=>0,'renew_privacy'=>'true','created_epoch'=>0] as $key=>$value){$x=$d;$x[$key]=$value;rn_refuse('Malformed request refuses '.$key,fn()=>domain_renewals_document($x));}
 $x=$d;$x['requested_years']=11;rn_refuse('Local term cap does not silently clamp request',fn()=>domain_renewals_document($x));$x=$d;$x['price']=100;rn_refuse('Unknown financial fields cannot enter request',fn()=>domain_renewals_document($x));
 $e=['id'=>str_repeat('f',32),'request_id'=>$d['id'],'revision'=>1,'command'=>'reviewed','actor_kind'=>'staff','actor_id'=>1,'recorded_epoch'=>1700000001,'note'=>'Checking terms','previous_sha256'=>hash('sha256',domain_renewals_json($d))];rn_check('Typed review retains local actor and original hash',domain_renewals_event_document($e)===$e);
 foreach(['approved','renewed','dispatched','paid'] as $command){$x=$e;$x['command']=$command;rn_refuse('Review cannot imply '.$command,fn()=>domain_renewals_event_document($x));}
 $x=$e;$x['actor_kind']='client';rn_refuse('Client cannot be review actor',fn()=>domain_renewals_event_document($x));$x=$e;$x['command']='withdrawn';$x['actor_kind']='client';$x['note']='';rn_check('Client withdrawal is typed local event',domain_renewals_event_document($x)===$x);$x['note']='changing terms';rn_refuse('Withdrawal cannot alter review message',fn()=>domain_renewals_event_document($x));
 foreach(['',str_repeat('x',1001),"bad\x00note"] as $i=>$note){$x=$e;$x['note']=$note;rn_refuse('Review message refuses invalid value '.$i,fn()=>domain_renewals_event_document($x));}
 rn_check('Review message keeps printable markup for escaping at render',domain_renewals_note(' <terms> & price ')==='<terms> & price');
}catch(Throwable $e){$checks[]=['name'=>'Unexpected: '.$e->getMessage(),'passed'=>false];}
$ok=!array_filter($checks,fn($v)=>!$v['passed']);echo json_encode(['passed'=>$ok,'scope'=>'Pure local request and review validation; no database, provider, payment or dispatch','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;exit($ok?0:1);
