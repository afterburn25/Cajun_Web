<?php
declare(strict_types=1);
define('CWP_BOOT',true);require __DIR__.'/../site/app/providers/schedule_document.php';
$checks=[];
function psd_ok(string $name,bool $ok):void {global $checks;$checks[]=['name'=>$name,'passed'=>$ok];if(!$ok)throw new RuntimeException($name);}
function psd_deny(string $name,string $bytes):void {try{provider_schedule_document($bytes);}catch(RuntimeException){psd_ok($name,true);return;}psd_ok($name,false);}
try{
    $state=['approval'=>null,'approval_sha256'=>null,'pending'=>null,'next'=>0,'failures'=>0,'paused'=>true];
    $setup=['format'=>1,'sequence'=>1,'id'=>str_repeat('a',32),'kind'=>'setup','subject'=>str_repeat('0',32),'actor'=>1,'at'=>1700000000,'previous'=>str_repeat('0',64),'data'=>[],'state'=>$state];
    psd_ok('Setup journal round trips without optional authority',provider_schedule_document(provider_schedule_json($setup))===$setup);
    $approval=$setup;$approval['sequence']=2;$approval['kind']='approval';$approval['data']=['profile_id'=>str_repeat('b',32),'profile_revision'=>1,'credential_sha256'=>str_repeat('c',64),'owner_auth_sha256'=>str_repeat('d',64),'document_root'=>'/srv/www','installation_root'=>'/srv/www/site','enabled'=>true,'interval'=>900];$approval['state']=['approval'=>$approval['id'],'approval_sha256'=>null,'pending'=>null,'next'=>$approval['at'],'failures'=>0,'paused'=>false];
    psd_ok('Exact read approval document round trips',provider_schedule_document(provider_schedule_json($approval))===$approval);
    foreach(['null','[]','{}','"x"','{"format":1}',provider_schedule_json($setup).' ',str_repeat('x',16385)] as $i=>$bad)psd_deny('Malformed journal is refused '.$i,$bad);
    foreach(['format'=>2,'sequence'=>'1','id'=>[],'kind'=>'write','actor'=>0,'at'=>-1,'previous'=>'','data'=>null,'state'=>false,'extra'=>'unrecognized'] as $key=>$v){$bad=$setup;$bad[$key]=$v;psd_deny('Invalid event field refused '.$key,provider_schedule_json($bad));}
    foreach(['profile_id'=>[],'profile_revision'=>0,'credential_sha256'=>'secret','owner_auth_sha256'=>'','document_root'=>"/srv/\nsecret",'installation_root'=>str_repeat('x',2049),'enabled'=>'1','interval'=>60,'operation'=>'POST/package'] as $key=>$v){$bad=$approval;$bad['data'][$key]=$v;psd_deny('Invalid approval scope refused '.$key,provider_schedule_json($bad));}
    foreach(['approval'=>null,'approval_sha256'=>str_repeat('e',64),'pending'=>str_repeat('f',32),'next'=>0,'failures'=>1,'paused'=>true] as $key=>$v){$bad=$approval;$bad['state'][$key]=$v;psd_deny('Invalid approval projection refused '.$key,provider_schedule_json($bad));}
    $start=$approval;$start['kind']='start';$start['sequence']=3;$start['id']=str_repeat('e',32);$start['subject']=str_repeat('f',32);$start['data']=['approval_id'=>$approval['id'],'approval_sha256'=>str_repeat('1',64),'profile_id'=>$approval['data']['profile_id'],'profile_revision'=>1,'lease_until'=>$start['at']+120];$start['state']['approval_sha256']=str_repeat('1',64);$start['state']['pending']=$start['subject'];$start['state']['next']=0;
    psd_ok('Bounded start lease round trips',provider_schedule_document(provider_schedule_json($start))===$start);
    $bad=$start;$bad['data']['lease_until']++;psd_deny('Unbounded lease refused',provider_schedule_json($bad));
    $finish=$start;$finish['kind']='finish';$finish['sequence']=4;$finish['id']=str_repeat('2',32);$finish['state']['pending']=null;$finish['state']['paused']=true;$finish['data']=['start_id'=>$start['id'],'start_sha256'=>str_repeat('3',64),'result_sha256'=>str_repeat('4',64),'outcome'=>'refused','reason'=>'interrupted','recovered'=>true];
    psd_ok('Reviewed interruption retains bounded outcome',provider_schedule_document(provider_schedule_json($finish))===$finish);
    foreach(['outcome'=>'complete','reason'=>'private provider response','recovered'=>'1','start_sha256'=>'','result_sha256'=>[]] as $k=>$v){$bad=$finish;$bad['data'][$k]=$v;psd_deny('Malformed recovery evidence refused '.$k,provider_schedule_json($bad));}
}catch(Throwable $e){$checks[]=['name'=>'Schedule document failure: '.$e->getMessage(),'passed'=>false];}
@mkdir(__DIR__.'/../qa',0700,true);$mode=php_ini_loaded_file()||php_ini_scanned_files()?'default':'minimal';file_put_contents(__DIR__.'/../qa/provider-schedule-document-'.$mode.'-results.json',json_encode(['checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$failed=array_filter($checks,fn($c)=>!$c['passed']);echo count($checks).' schedule document checks '.($failed?'failed':'passed').PHP_EOL;exit($failed?1:0);
