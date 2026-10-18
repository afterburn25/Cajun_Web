<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',dirname(__DIR__).'/site');
require CWP_CODE_ROOT.'/app/providers/php_controls_lifecycle_document.php';
use CajunWebPro\Foundation\TwentyIPackagePhp\{PackagePhpVersionBinding,PackagePhpOptionSet};

// Execute the actual screen, authority, review, admission, access and summary bodies.
// SQL/history durability, session lookup and locks below are explicit portable models.
$sources=[];
function ps_body(string $file,string $start,?string $end=null):void {
    $raw=file_get_contents(CWP_CODE_ROOT.'/app/providers/'.$file);$a=strpos($raw,$start);
    if ($a===false || substr_count($raw,$start)!==1) throw new LogicException('Production body marker changed.');
    $body=substr($raw,$a);
    if ($end!==null) { $b=strpos($body,$end);if ($b===false || substr_count($body,$end)!==1) throw new LogicException('Production body end changed.');$body=substr($body,0,$b); }
    $GLOBALS['sources'][$file]=hash('sha256',$raw);eval($body);
}
ps_body('php_controls_authority.php','/** These internal contexts');
ps_body('php_controls_changes.php','/** Only real owner/primary-client','/** Recheck this exact');
ps_body('php_controls_changes.php','/** Internal receipt projection','/** One owning mutex');
ps_body('php_controls_screen.php','/** Closed eligibility denials only.');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never {throw new ErrorException($message,0,$severity,$file,$line);});
$checks=[];$env=[];
function ps_check(bool $ok,string $name):void {if(!$ok)throw new RuntimeException($name);$GLOBALS['checks'][]=['name'=>$name,'passed'=>true];}
function ps_refuse(callable $fn,string $name,string $class=RuntimeException::class,?string $message=null):void {
    try{$fn();}catch(RuntimeException|LogicException $e){ps_check($e instanceof $class && ($message===null||$e->getMessage()===$message),$name);return;}
    throw new RuntimeException('Expected refusal: '.$name);
}
function ps_id(int $n):string{return str_pad(dechex($n),32,'0',STR_PAD_LEFT);}
function ps_hash(int $n):string{return str_pad(dechex($n),64,'0',STR_PAD_LEFT);}
function ps_reset():void {
    $_SESSION=[];$_GET=[];
    $GLOBALS['env']=['lock'=>0,'tx'=>false,'events'=>[],'manager'=>true,'support'=>false,'session_client'=>7,
        'client_active'=>1,'client_password'=>'synthetic-client-password-hash','client_auth'=>str_repeat('b',32),
        'owner_role'=>'owner','owner_auth'=>str_repeat('a',32),'owner_password'=>'synthetic-owner-password-hash',
        'staff_ready'=>true,'php'=>true,'clients'=>true,'operations'=>true,'delegation'=>true,'lifecycle'=>true,
        'damaged'=>null,'assignment'=>true,'binding_error'=>null,'matches'=>true,'revision'=>1,'source_seq'=>10,
        'permission'=>null,'store'=>array_fill_keys(['read','result','request','terminal','check'],[]),'ledger'=>[],
        'pending'=>false,'get_ids'=>[],'selects'=>[],'normalizations'=>0,'history_batches'=>0];
    ps_permission(true);
}
function ps_binding():PackagePhpVersionBinding {
    return new PackagePhpVersionBinding(['profile_id'=>ps_id(1),'profile_revision'=>$GLOBALS['env']['revision'],'key_fingerprint'=>ps_hash(2),'credential_sha256'=>ps_hash(3),'package_id'=>701,'client_id'=>7,'link_id'=>ps_id(4),'link_sha256'=>ps_hash(5),'event_id'=>6,'event_sha256'=>ps_hash(7),'audit_id'=>8,'audit_sha256'=>ps_hash(9),'source_id'=>ps_id(10),'source_seq'=>$GLOBALS['env']['source_seq'],'source_sha256'=>ps_hash(11)]);
}
function ps_permission(bool $enabled):void {
    $GLOBALS['env']['permission']=['document'=>['id'=>ps_id(12),'client_id'=>7,'enabled'=>$enabled,'owner_id'=>5,'owner_auth_version'=>str_repeat('a',32),'client_auth_version'=>str_repeat('b',32)],'binding'=>ps_binding(),'sha256'=>ps_hash(13)];
}
function ps_context(string $kind='staff'):array {
    return provider_php_controls_context(['kind'=>$kind,'id'=>$kind==='staff'?5:7,'auth_version'=>str_repeat($kind==='staff'?'a':'b',32)],ps_binding(),$kind==='staff'?null:$GLOBALS['env']['permission']);
}
final class PsDatabase {public function inTransaction():bool{return $GLOBALS['env']['tx'];}}
function db():PsDatabase{return new PsDatabase();}
function staff_locked(callable $fn):mixed {$GLOBALS['env']['lock']++;try{return $fn();}finally{$GLOBALS['env']['lock']--;}}
function staff_transaction(callable $fn):mixed {
    if($GLOBALS['env']['tx']||$GLOBALS['env']['lock']<1)throw new LogicException('Invalid modeled transaction.');
    $GLOBALS['env']['tx']=true;try{return $fn();}finally{$GLOBALS['env']['tx']=false;}
}
function provider_php_controls_guard(bool $tx=false):void {if($GLOBALS['env']['lock']<1||($tx&&!$GLOBALS['env']['tx']))throw new LogicException('Missing modeled lock/transaction.');}
function provider_php_controls_json(array $d):string{return provider_php_controls_lifecycle_json($d);}
function provider_php_id(string $id):void{provider_php_controls_lifecycle_id($id);}
function support_current():bool{return $GLOBALS['env']['support'];}
function provider_settings_manager(bool $ready=true):array {
    provider_php_controls_guard(true);if(isset($GLOBALS['cwp_settings']))throw new LogicException('Authority reused settings cache.');
    if(!$GLOBALS['env']['manager']||support_current()||array_key_exists('support',$_GET))throw new RuntimeException('An active owner account is required for provider settings.');
    return ['id'=>5,'auth_version'=>str_repeat('a',32)];
}
function studio_client():?array {
    $GLOBALS['env']['normalizations']++;unset($_SESSION['contact_id'],$_SESSION['contact_auth']);
    return $GLOBALS['env']['session_client']===null?null:['id'=>$GLOBALS['env']['session_client'],'active'=>$GLOBALS['env']['client_active'],'password_hash'=>$GLOBALS['env']['client_password'],'auth_version'=>$GLOBALS['env']['client_auth']];
}
function staff_ready():bool{return $GLOBALS['env']['staff_ready'];}
function staff_profile(array $row):?array{return $row+['role'=>$GLOBALS['env']['owner_role']];}
function provider_php_controls_ready():bool{provider_php_controls_guard();if($GLOBALS['env']['damaged']==='delegation')throw new RuntimeException('PHP delegation history counts disagree.');return $GLOBALS['env']['delegation'];}
function provider_php_controls_lifecycle_ready():bool{provider_php_controls_guard();if($GLOBALS['env']['damaged']==='lifecycle')throw new RuntimeException('PHP lifecycle correspondence needs recovery.');return $GLOBALS['env']['lifecycle'];}
function provider_php_ready():bool{if($GLOBALS['env']['damaged']==='php')throw new RuntimeException('PHP observation history counts disagree.');return $GLOBALS['env']['php'];}
function studio_ready():bool{return $GLOBALS['env']['clients'];}
function provider_operations_ready():bool{if($GLOBALS['env']['damaged']==='operations')throw new RuntimeException('Operation history needs recovery.');return $GLOBALS['env']['operations'];}
function provider_php_controls_permission(string $link):?array{provider_php_controls_guard();return $GLOBALS['env']['permission'];}
function provider_php_binding(string $link):PackagePhpVersionBinding {
    provider_php_controls_guard(true);
    if(!$GLOBALS['env']['assignment']||$link!==ps_id(4))throw new RuntimeException('This hosting assignment is unavailable.');
    if($GLOBALS['env']['binding_error']!==null)throw $GLOBALS['env']['binding_error'];return ps_binding();
}
function provider_php_assignment_matches(PackagePhpVersionBinding $b):bool{return $GLOBALS['env']['matches'];}
function table(string $n):string{return $n;}
function one(string $sql,array $p=[]):?array {
    provider_php_controls_guard(true);$GLOBALS['env']['selects'][]=$sql;
    if($sql==='SELECT profile_id,package_id,client_id FROM provider_package_links WHERE id=?')return $GLOBALS['env']['assignment']&&$p[0]===ps_id(4)?['profile_id'=>ps_id(1),'package_id'=>'701','client_id'=>'7']:null;
    if($sql==='SELECT * FROM studio_clients WHERE id=? AND active=1')return $p[0]===7&&$GLOBALS['env']['client_active']===1?['id'=>7,'password_hash'=>$GLOBALS['env']['client_password'],'auth_version'=>$GLOBALS['env']['client_auth']]:null;
    if($sql==='SELECT * FROM users WHERE id=?')return $p[0]===5?['id'=>5,'password_hash'=>$GLOBALS['env']['owner_password'],'auth_version'=>$GLOBALS['env']['owner_auth']]:null;
    if($sql==='SELECT id FROM provider_php_changes WHERE profile_id=? AND package_id=? ORDER BY seq DESC LIMIT 1'){
        $r=array_values($GLOBALS['env']['store']['request']);usort($r,fn($a,$b)=>$b['document']['seq']<=>$a['document']['seq']);return $r?['id'=>$r[0]['document']['id']]:null;
    }
    if($sql==="SELECT id FROM provider_php_change_checks WHERE request_id=? AND BINARY disposition='observed'"){
        foreach($GLOBALS['env']['store']['check'] as $id=>$r)if($r['document']['request_id']===$p[0]&&$r['document']['disposition']==='observed')return ['id'=>$id];return null;
    }
    if($sql==='SELECT c.id FROM provider_php_change_checks c JOIN provider_php_control_reads r ON r.id=c.read_id WHERE c.request_id=? ORDER BY r.seq DESC LIMIT 1'){
        $r=array_filter($GLOBALS['env']['store']['check'],fn($r)=>$r['document']['request_id']===$p[0]);return $r?['id'=>array_key_last($r)]:null;
    }
    throw new LogicException('Unexpected modeled query: '.$sql);
}
function all(string $sql,array $p=[]):array {
    provider_php_controls_guard(true);$GLOBALS['env']['history_batches']++;
    if(!preg_match('/\ASELECT id,seq FROM provider_php_changes WHERE link_id=\?( AND actor_client_id=\?)?( AND seq<\?)? ORDER BY seq DESC LIMIT 32\z/',$sql,$m))throw new LogicException('Unexpected modeled history query.');
    $client=str_contains($sql,'actor_client_id')?$p[1]:null;$before=str_contains($sql,'seq<?')?$p[count($p)-1]:PHP_INT_MAX;
    $r=array_filter($GLOBALS['env']['store']['request'],fn($r)=>$r['document']['review']['context']['binding']['link_id']===$p[0]&&($client===null||($r['document']['review']['context']['actor']['kind']==='client'&&$r['document']['review']['context']['actor']['id']===$client))&&$r['document']['seq']<$before);
    usort($r,fn($a,$b)=>$b['document']['seq']<=>$a['document']['seq']);return array_map(fn($r)=>['id'=>$r['document']['id'],'seq'=>(string)$r['document']['seq']],array_slice($r,0,32));
}
function provider_php_controls_lifecycle_latest(string $link,string $kind):?array {
    provider_php_controls_guard(true);$r=array_filter($GLOBALS['env']['store']['read'],fn($r)=>$r['document']['context']['binding']['link_id']===$link&&$r['document']['kind']===$kind);
    usort($r,fn($a,$b)=>$b['document']['seq']<=>$a['document']['seq']);return $r?['read'=>$r[0],'result'=>$GLOBALS['env']['store']['result'][$r[0]['document']['id']]??null]:null;
}
function provider_php_controls_lifecycle_get(string $kind,string $id):?array{provider_php_controls_guard(true);$GLOBALS['env']['get_ids'][]=[$kind,$id];return $GLOBALS['env']['store'][$kind][$id]??null;}
function provider_php_controls_lifecycle_pending(string $profile,int $package):?array{provider_php_controls_guard(true);if($profile!==ps_id(1)||$package!==701)throw new LogicException('Wrong pending resource.');return $GLOBALS['env']['pending']?['request'=>['private'=>'never project']]:null;}
function provider_php_controls_change_ledger(array $r,int $now,bool $lock=false):?array{provider_php_controls_guard(true);return $GLOBALS['env']['ledger'][$r['id']]??null;}
// Unexpected mutation, schema, credential or external access is always a test failure.
function query(string $sql,array $p=[]):never{throw new LogicException('Unexpected SQL or mutation.');}
function provider_settings_unseal(...$args):never{throw new LogicException('Unexpected credential access.');}
function provider_php_controls_lifecycle_insert(...$args):never{throw new LogicException('Unexpected lifecycle write.');}
function provider_php_controls_lifecycle_next(...$args):never{throw new LogicException('Unexpected sequence allocation.');}
function provider_php_controls_prepare(...$args):never{throw new LogicException('Unexpected schema preparation.');}
function ps_record(string $kind,string $id,array $d,int $audit):void{$GLOBALS['env']['store'][$kind][$id]=['row'=>['private'=>'must not project'],'document'=>$d,'sha256'=>provider_php_controls_lifecycle_sha($d),'audit_id'=>$audit];}
function ps_read(int $n,string $kind,array $context,?string $state='complete',int $age=5,?array $options=null):string {
    $id=ps_id(100+$n);$read=provider_php_controls_lifecycle_read(['format'=>1,'id'=>$id,'seq'=>$n,'kind'=>$kind,'context'=>$context['document'],'started_epoch'=>time()-$age]);ps_record('read',$id,$read,$n+100);
    if($state!==null){$set=PackagePhpOptionSet::fromNormalizedRows($options??[['value'=>'php84','label'=>'PHP 8.4','handler'=>'synthetic handler'],['value'=>'php85','label'=>'PHP 8.5','handler'=>'synthetic handler']]);
        $r=provider_php_controls_lifecycle_result(['format'=>1,'read_id'=>$id,'read_sha256'=>provider_php_controls_lifecycle_sha($read),'state'=>$state,'reason'=>$state==='complete'?'':'unexpected_response','finished_epoch'=>time()-$age+1,'value'=>$state==='complete'&&$kind==='current'?'php84':null,'options'=>$state==='complete'&&$kind==='available'?$set->options:null,'options_sha256'=>$state==='complete'&&$kind==='available'?$set->sha256:null],$read);ps_record('result',$id,$r,$n+101);}
    return $id;
}
function ps_pair(string $kind='staff'):void{ps_read(1,'current',ps_context($kind));ps_read(2,'available',ps_context($kind));}
function ps_request(int $n,string $kind='staff',?array $context=null):string {
    $id=ps_id(1000+$n);$c=$context??ps_context($kind);
    ps_record('request',$id,['id'=>$id,'seq'=>$n,'review'=>['context'=>$c['document'],'current_read_id'=>ps_id(101),'options_read_id'=>ps_id(102),'before_value'=>'php84','selected'=>['value'=>'php85']],'approved_epoch'=>time()-30],$n+300);return $id;
}
function ps_screen(string $kind='staff',int $page=1):array{return $kind==='staff'?provider_php_controls_screen_owner(ps_id(4),$page):provider_php_controls_screen_client(7,ps_id(4),$page);}

try {
    ps_reset();$GLOBALS['cwp_settings']=['stale'=>true];ps_pair();$s=ps_screen();
    ps_check(array_keys($s)===['link_id','setup','access','blocked','can_select','current','available','delegation','history','page','has_more'],'Closed top-level screen keys');
    ps_check($s['setup']==='ready'&&$s['access']&&$s['can_select']&&!$s['blocked'],'Current owner with fresh choices can select a different value');
    ps_check(array_keys($s['current'])===['id','state','reason','finished_epoch','value','usable']&&array_keys($s['available'])===['id','state','reason','finished_epoch','options','usable'],'Closed observation keys');
    ps_check($s['delegation']['enabled']&&$s['delegation']['current']&&array_keys($s['delegation']['disable'])===['expected_permission_id','review_sha256'],'Current delegation has only safe review identities');
    ps_check(!str_contains(json_encode($s),'auth_version')&&!str_contains(json_encode($s),'credential')&&!str_contains(json_encode($s),'private'),'No private context or row fields leave projection');
    ps_check($GLOBALS['env']['lock']===0&&!$GLOBALS['env']['tx'],'GET releases modeled lock and transaction');
    ps_reset();ps_pair('client');$s=ps_screen('client');ps_check($s['access']&&$s['can_select']&&$s['delegation']===null,'Actual primary client receives its own context without owner controls');
    foreach(['contact_id','contact_auth'] as $key){ps_reset();$_SESSION[$key]='stale';ps_refuse(fn()=>ps_screen('client'),'Reject '.$key.' before client normalization');ps_check($GLOBALS['env']['normalizations']===0,'No normalization before '.$key.' denial');}
    foreach(['staff','client'] as $kind){ps_reset();$_GET['support']='';ps_refuse(fn()=>ps_screen($kind),'Reject support query for '.$kind);ps_reset();$GLOBALS['env']['support']=true;ps_refuse(fn()=>ps_screen($kind),'Reject active support for '.$kind);}
    foreach(['session_client'=>8,'client_active'=>0,'client_password'=>'','client_auth'=>'invalid'] as $key=>$value){ps_reset();$GLOBALS['env'][$key]=$value;ps_refuse(fn()=>ps_screen('client'),'Reject invalid primary client '.$key);}
    ps_reset();$GLOBALS['env']['manager']=false;ps_refuse(fn()=>ps_screen(),'Require actual current owner');
    ps_reset();$GLOBALS['env']['tx']=true;ps_refuse(fn()=>ps_screen(),'Refuse inherited transaction',LogicException::class);ps_check($GLOBALS['env']['lock']===0,'Inherited transaction refusal precedes mutex');
    ps_reset();ps_refuse(fn()=>provider_php_controls_screen_owner('bad'),'Reject malformed selected link',InvalidArgumentException::class);
    foreach(['php','clients','operations'] as $key){ps_reset();$GLOBALS['env'][$key]=false;ps_check(ps_screen()['setup']==='prerequisites','Prerequisite state for absent '.$key);}
    ps_reset();$GLOBALS['env']['delegation']=false;$GLOBALS['env']['lifecycle']=false;ps_check(ps_screen()['setup']==='delegation','Owner can see explicit delegation setup');
    ps_reset();$GLOBALS['env']['lifecycle']=false;ps_check(ps_screen()['setup']==='lifecycle','Owner can see explicit lifecycle setup');
    foreach(['delegation','lifecycle','php','operations'] as $key){ps_reset();$GLOBALS['env']['damaged']=$key;ps_refuse(fn()=>ps_screen(),'Propagate established '.$key.' corruption');}
    ps_reset();$GLOBALS['env']['assignment']=false;ps_check(!ps_screen()['access'],'Owner can reach setup/unavailable assignment screen');ps_refuse(fn()=>ps_screen('client'),'Client cannot select missing or other-client assignment');
    ps_reset();$GLOBALS['env']['binding_error']=new RuntimeException('Refresh hosting inventory for this exact assignment first.');$s=ps_screen();
    ps_check(!$s['access']&&!$s['delegation']['current']&&$s['delegation']['enable']===null&&$s['delegation']['disable']!==null,'Stale inventory retains owner revocation without claiming current grant');
    foreach(['owner_role'=>'administrator','owner_auth'=>str_repeat('c',32),'owner_password'=>'','client_auth'=>str_repeat('c',32),'client_password'=>'','client_active'=>0,'matches'=>false] as $key=>$value){ps_reset();$GLOBALS['env'][$key]=$value;$s=ps_screen();ps_check(!$s['delegation']['current']&&$s['delegation']['disable']!==null,'Stale grant '.$key.' remains revocable but not current');}
    ps_reset();ps_permission(false);$s=ps_screen('client');ps_check(!$s['access']&&$s['history']===[],'Revoked client sees no historical request details');
    foreach([new RuntimeException('Hosting assignment audit history needs recovery.'),new PDOException('Refresh hosting inventory for this exact assignment first.')] as $error){ps_reset();$GLOBALS['env']['binding_error']=$error;ps_refuse(fn()=>ps_screen(),'Unknown or SQL context failure propagates '.get_class($error),get_class($error),$error->getMessage());}
    ps_reset();ps_pair();ps_read(3,'current',ps_context(),'unavailable');$s=ps_screen();ps_check($s['current']['id']===ps_id(103)&&$s['current']['value']===null&&!$s['current']['usable']&&!$s['can_select'],'Latest failed current read never falls back');
    ps_reset();ps_pair();ps_read(3,'available',ps_context(),null);$s=ps_screen();ps_check($s['available']['state']==='pending'&&$s['available']['finished_epoch']===null&&$s['available']['options']===[]&&!$s['can_select'],'Latest unfinished choices never fall back');
    ps_reset();ps_pair();ps_read(3,'available',ps_context(),'unknown');$s=ps_screen();ps_check($s['available']['state']==='unknown'&&$s['available']['reason']==='unexpected_response'&&$s['available']['options']===[]&&!$s['can_select'],'Latest unknown options expose finite reason but no older choices');
    ps_reset();ps_pair();ps_read(3,'current',ps_context('client'));$s=ps_screen();ps_check($s['current']===null&&!$s['can_select'],'Newer different actor context hides older successful current value');
    ps_reset();ps_pair();$GLOBALS['env']['source_seq']++;$s=ps_screen();ps_check($s['current']===null&&$s['available']===null,'Changed inventory context does not reuse old observations');
    ps_reset();ps_pair('client');$GLOBALS['env']['permission']['document']['id']=ps_id(99);$s=ps_screen('client');ps_check($s['current']===null&&$s['available']===null,'A new grant identity cannot reuse prior client reads');
    ps_reset();ps_read(1,'current',ps_context(),'complete',901);ps_read(2,'available',ps_context());$s=ps_screen();ps_check($s['current']['value']==='php84'&&!$s['current']['usable']&&!$s['can_select'],'Expired scalar is historical and cannot admit change');
    ps_reset();ps_read(1,'current',ps_context());ps_read(2,'available',ps_context(),'complete',5,[]);$s=ps_screen();ps_check($s['available']['usable']&&!$s['can_select'],'Complete empty choices admit no change');
    ps_reset();ps_read(1,'current',ps_context());ps_read(2,'available',ps_context(),'complete',5,[['value'=>'php84','label'=>'Same','handler'=>'synthetic']]);ps_check(!ps_screen()['can_select'],'Only already-observed choice admits no change');
    ps_reset();ps_pair();$GLOBALS['env']['pending']=true;$s=ps_screen();ps_check($s['blocked']&&!$s['can_select']&&!str_contains(json_encode($s),'never project'),'Resource pending fence exposes no inaccessible request');
    ps_reset();ps_pair();$r=ps_request(1);ps_record('terminal',$r,['phase'=>'not_sent','ledger'=>null,'recovered_from_ledger'=>false],400);ps_check(!ps_screen()['can_select'],'Released request still requires new observations after its audit');
    ps_reset();ps_pair();for($i=1;$i<=23;$i++)ps_request($i);$GLOBALS['env']['pending']=true;$a=ps_screen();$b=ps_screen('staff',2);$c=ps_screen('staff',3);
    ps_check(count($a['history'])===10&&$a['has_more']&&$a['history'][0]['id']===ps_id(1023),'First history page is newest ten');
    ps_check(count($b['history'])===10&&$b['has_more']&&$b['history'][0]['id']===ps_id(1013),'Second history page follows exact request order');
    ps_check(count($c['history'])===3&&!$c['has_more']&&$c['history'][0]['id']===ps_id(1003),'Final history page has exact remainder');
    ps_check(ps_screen('staff',0)['page']===1&&ps_screen('staff',PHP_INT_MAX)['page']===100000,'Page integers are bounded');
    ps_reset();ps_pair('client');$mine=ps_request(1,'client');ps_request(2,'staff');$old=ps_context('client');$old['document']['actor']['auth_version']=str_repeat('c',32);ps_request(3,'client',$old);$GLOBALS['env']['pending']=true;$s=ps_screen('client');
    ps_check(array_column($s['history'],'id')===[$mine],'Client history excludes owner and stale original client authorization');
    ps_check(!in_array(['request',ps_id(1002)],$GLOBALS['env']['get_ids'],true),'Client query excludes owner requests before document projection');
    ps_reset();ps_pair();for($i=1;$i<=40;$i++){$ctx=ps_context();if($i>2)$ctx['document']['binding']['profile_revision']=2;ps_request($i,'staff',$ctx);}$GLOBALS['env']['pending']=true;$s=ps_screen();
    ps_check(count($s['history'])===2&&!$s['has_more']&&$GLOBALS['env']['history_batches']===2,'Keyset traversal skips inaccessible history across batches');
    ps_reset();ps_pair();$r=ps_request(1);$GLOBALS['env']['pending']=true;$s=ps_screen();ps_check(!$s['history'][0]['can_verify']&&$s['history'][0]['recovery_sha256']===null,'Missing ledger and terminal cannot verify or recover');
    ps_record('terminal',$r,['phase'=>'not_sent','ledger'=>null,'recovered_from_ledger'=>false],500);$s=ps_screen();ps_check(!$s['history'][0]['can_verify']&&$s['history'][0]['phase']==='not_sent','No-send terminal never offers provider verification');unset($GLOBALS['env']['store']['terminal'][$r]);
    $GLOBALS['env']['ledger'][$r]=['status'=>'claimed'];$s=ps_screen();ps_check($s['history'][0]['recovery_sha256']===null,'Claimed ledger cannot authorize recovery');
    $GLOBALS['env']['ledger'][$r]=['status'=>'uncertain','private'=>'synthetic-ledger'];$s=ps_screen();ps_check($s['history'][0]['recovery_sha256']===provider_php_controls_lifecycle_sha($GLOBALS['env']['ledger'][$r])&&$s['history'][0]['phase']==='pending','Finished ledger offers exact owner recovery hash without claiming terminal success');
    ps_record('terminal',$r,['phase'=>'attempted','ledger'=>['status'=>'uncertain'],'recovered_from_ledger'=>true],500);$s=ps_screen();ps_check($s['history'][0]['can_verify']&&$s['history'][0]['recovery_sha256']===null&&$s['history'][0]['readback']==='pending','Attempted terminal enables separate verification only');
    $GLOBALS['env']['store']['terminal'][$r]['document']['ledger']['status']='claimed';ps_check(!ps_screen()['history'][0]['can_verify'],'Claimed terminal cannot offer verification');$GLOBALS['env']['store']['terminal'][$r]['document']['ledger']['status']='uncertain';
    ps_record('check',ps_id(5000),['request_id'=>$r,'disposition'=>'different','value'=>'php83','recorded_epoch'=>time()],501);$s=ps_screen();ps_check($s['history'][0]['can_verify']&&$s['history'][0]['readback']==='different'&&$s['blocked'],'Different readback retains pending fence and separate observed value');
    $GLOBALS['env']['store']['check'][ps_id(5000)]['document']['disposition']='observed';$GLOBALS['env']['store']['check'][ps_id(5000)]['document']['value']='php85';$s=ps_screen();ps_check(!$s['history'][0]['can_verify']&&$s['history'][0]['readback']==='observed','Observed readback needs no duplicate verification action');
    ps_check(array_keys($s['history'][0])===['id','link_id','before_value','desired_value','approved_epoch','phase','readback','observed_value','readback_epoch','recovered_from_ledger','can_verify','recovery_sha256'],'Closed safe history fields preserve backend distinctions');
    ps_record('check',ps_id(5001),['request_id'=>$r,'disposition'=>'different','value'=>'php83','recorded_epoch'=>time()],502);ps_check(!ps_screen()['history'][0]['can_verify'],'Any retained observed check prevents offering a verification the backend would only replay');
    ps_reset();ps_pair('client');$r=ps_request(1,'client');$GLOBALS['env']['ledger'][$r]=['status'=>'uncertain'];$GLOBALS['env']['pending']=true;$s=ps_screen('client');ps_check($s['history'][0]['recovery_sha256']===null,'Client never receives owner recovery review');
    ps_check($GLOBALS['env']['lock']===0&&!$GLOBALS['env']['tx'],'Final screen leaves no held model lock or transaction');
    echo json_encode(['passed'=>true,'count'=>count($checks),'checks'=>$checks,'sources'=>$sources,'scope'=>'Actual production screen/authority/review/access bodies with modeled SQL, session, immutable history and locks; no native, HTTP or provider acceptance.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) {
    echo json_encode(['passed'=>false,'checks'=>$checks,'error'=>get_class($e).': '.$e->getMessage(),'sources'=>$sources],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit(1);
}
