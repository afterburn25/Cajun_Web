<?php
/** Actual saved-check and owner predicates with modeled read-only SQL; actual view fragment rendering. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);
$base=dirname(__DIR__);$checks=[];$reads=0;$history=[];$profiles=[];$owner=null;$support=false;$staffReady=true;$settingsReady=true;
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
require $base.'/site/includes/security/input.php';
require $base.'/site/includes/urls.php';
$GLOBALS['cwp_config']=['site_url'=>'https://studio.synthetic.invalid/subfolder'];

/** Load these exact production bodies without loading a database or provider transport. */
function pce_load(string $path,string $name):void {
    $source=file_get_contents($path);$needle='function '.$name.'(';
    if(substr_count($source,$needle)!==1)throw new LogicException('Production function is not unique.');
    $tokens=token_get_all('<?php '.substr($source,strpos($source,$needle)));$code='';$depth=0;$opened=false;
    foreach($tokens as $token){
        if(is_array($token)&&$token[0]===T_OPEN_TAG)continue;
        $text=is_array($token)?$token[1]:$token;$code.=$text;
        if($token==='{'){$opened=true;$depth++;}
        elseif($token==='}'&&--$depth===0&&$opened){eval($code);return;}
    }
    throw new LogicException('Incomplete production function.');
}
function pce_ok(string $name,bool $passed):void {global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function pce_denied(string $name,callable $call):void {
    $denied=false;try{$call();}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=true;}pce_ok($name,$denied);
}
function support_current():bool {global $support;return $support;}
function admin_user():?array {global $owner;return $owner;}
function staff_ready():bool {global $staffReady;return $staffReady;}
function staff_can(string $permission,array $actor):bool {return $permission==='staff.manage'&&$actor['manage']===true;}
function provider_settings_ready():bool {global $settingsReady;return $settingsReady;}
function table(string $name):string {return 'pce_'.$name;}
function one(string $sql,array $params=[]):?array {
    global $profiles,$reads;$reads++;
    if($sql!=='SELECT * FROM pce_provider_profiles WHERE id=?')throw new LogicException('Unexpected SQL in saved-check projection.');
    return $profiles[$params[0]]??null;
}
function all(string $sql,array $params=[]):array {
    global $history,$reads;$reads++;
    if($sql!=='SELECT c.profile_revision,c.started_epoch,r.state,r.result_code,r.finished_at FROM pce_provider_checks c LEFT JOIN pce_provider_check_results r ON r.id=c.id WHERE c.profile_id=? ORDER BY c.started_epoch DESC,c.id DESC LIMIT 2')throw new LogicException('Unexpected saved-intent query.');
    $rows=array_values(array_filter($history,static fn($row)=>$row['profile_id']===$params[0]));
    usort($rows,static fn($a,$b)=>$b['started_epoch']<=>$a['started_epoch']?:strcmp($b['id'],$a['id']));
    return array_slice($rows,0,2);
}
function admin_action(string $action):void {echo '<input type="hidden" name="action" value="'.esc($action).'">';}
function admin_input(string $name,string $label,mixed $value,string $type='text',string $attributes=''):void {
    echo '<label>'.esc($label).'<input name="'.esc($name).'" type="'.esc($type).'" value="'.esc($value).'" '.$attributes.'></label>';
}
function pce_render(string $path,array $profile):string {
    $source=file_get_contents($path);$start='<section class="workspace-card"><h2>Check the saved connection</h2>';
    $end='<section class="workspace-card"><h2>Connection availability</h2>';
    if(substr_count($source,$start)!==1||substr_count($source,$end)!==1)throw new LogicException('Current connection view boundary changed.');
    $startAt=strpos($source,$start);$endAt=strpos($source,$end);if($endAt<=$startAt)throw new LogicException('Invalid view boundary.');
    $record=$profile+['has_credentials'=>$profile['credentials']!==''];$profileId=$record['id'];
    ob_start();try{eval('?>'.substr($source,$startAt,$endAt-$startAt));return ob_get_contents();}finally{ob_end_clean();}
}
function pce_attempt(string $id,int $epoch,?string $state='verified',?string $code='connected',int $revision=3):array {
    return ['id'=>$id,'profile_id'=>str_repeat('a',32),'profile_revision'=>$revision,'started_epoch'=>$epoch,'state'=>$state,'result_code'=>$code,'finished_at'=>$state===null?null:'2026-10-18 12:00:00'];
}
try{
    foreach(['provider_settings_manager','provider_settings_load'] as $name)pce_load($base.'/site/app/providers/settings.php',$name);
    pce_load($base.'/site/app/providers/checks.php','provider_checks_current_success');
    $id=str_repeat('a',32);$profile=['id'=>$id,'provider_code'=>'twenty-i','environment'=>'live','revision'=>3,'enabled'=>1,'credentials'=>'PRIVATE_CIPHERTEXT'];
    $profiles=[$id=>$profile];$owner=['id'=>7,'role'=>'owner','manage'=>true];$_GET=[];
    pce_ok('No saved check does not offer import',!provider_checks_current_success($id));
    $history=[pce_attempt('older',100),pce_attempt('latest',200)];
    pce_ok('Newest completed success under current settings offers import',provider_checks_current_success($id));
    $_GET=['check_page'=>'90000','history_page'=>'2','result_code'=>'connected'];
    pce_ok('History pagination and query claims cannot select the check',provider_checks_current_success($id));
    foreach([['authentication_failed','authentication'],['unknown','timeout'],[null,null]] as [$state,$code]){
        $history=[pce_attempt('old-success',100),pce_attempt('new',200,$state,$code)];
        pce_ok('Newer '.($code??'pending').' intent masks older success',!provider_checks_current_success($id));
    }
    foreach([['verified','timeout'],['unknown','connected']] as [$state,$code]){
        $history=[pce_attempt('new',200,$state,$code)];pce_ok('Inconsistent result pair refuses '.$state.'/'.$code,!provider_checks_current_success($id));
    }
    $history=[pce_attempt('new',200)];$history[0]['finished_at']=null;
    pce_ok('Missing terminal finish does not establish success',!provider_checks_current_success($id));
    $history=[pce_attempt('new',200,'verified','connected',2),pce_attempt('old-current',100)];
    pce_ok('Latest settings revision mismatch cannot fall back',!provider_checks_current_success($id));
    $history=[pce_attempt('new',200)];$profiles[$id]['revision']=4;
    pce_ok('Changed current settings invalidate the old success',!provider_checks_current_success($id));$profiles[$id]=$profile;
    foreach([['enabled',0],['credentials','']] as [$key,$value]){
        $profiles[$id][$key]=$value;$before=$reads;
        pce_ok('Unavailable saved profile refuses '.$key,!provider_checks_current_success($id)&&$reads===$before+1);$profiles[$id]=$profile;
    }
    $history=[pce_attempt('z',200),pce_attempt('a',200,null,null)];
    pce_ok('Ambiguous latest intent timestamp does not select a success',!provider_checks_current_success($id));
    $history=[pce_attempt('current',200),pce_attempt('foreign',300,null,null)];$history[1]['profile_id']=str_repeat('b',32);
    pce_ok('Another profile does not replace the current profile check',provider_checks_current_success($id));
    foreach(['support_query','support_session','anonymous','administrator','no_manage','staff_unready','settings_unready'] as $case){
        $owner=['id'=>7,'role'=>'owner','manage'=>true];$_GET=[];$support=false;$staffReady=true;$settingsReady=true;
        match($case){'support_query'=>$_GET['support']='','support_session'=>$support=true,'anonymous'=>$owner=null,'administrator'=>$owner['role']='administrator','no_manage'=>$owner['manage']=false,'staff_unready'=>$staffReady=false,'settings_unready'=>$settingsReady=false};
        $before=$reads;pce_denied('Current authority refuses '.$case,fn()=>provider_checks_current_success($id));pce_ok('Denied authority reads no profile or history '.$case,$reads===$before);
    }
    $owner=['id'=>7,'role'=>'owner','manage'=>true];$_GET=[];$support=false;$staffReady=true;$settingsReady=true;
    pce_denied('Unknown profile identity refuses',fn()=>provider_checks_current_success(str_repeat('c',32)));
    $history=[pce_attempt('new',200)];$_POST=['current_password'=>'PRIVATE_PASSWORD','general_key'=>'PRIVATE_KEY'];
    $view=$base.'/site/admin/views/providers.php';$html=pce_render($view,$profile);
    pce_ok('Actual connection card has one exact import CTA',substr_count($html,'>Import 20i Catalog</a>')===1);
    pce_ok('CTA preserves subfolder and exact catalog source',str_contains($html,'href="https://studio.synthetic.invalid/subfolder/admin/index.php?view=catalog&amp;import_source=package.types"'));
    pce_ok('Connection form password stays blank and private inputs remain absent',str_contains($html,'name="current_password" type="password" value=""')&&!str_contains($html,'PRIVATE_'));
    foreach([['unknown','timeout'],[null,null]] as [$state,$code]){
        $history=[pce_attempt('old',100),pce_attempt('new',200,$state,$code)];pce_ok('Actual card hides import after '.($code??'pending'),!str_contains(pce_render($view,$profile),'Import 20i Catalog'));
    }
    $history=[pce_attempt('new',200)];$profiles[$id]['revision']=4;
    pce_ok('Actual card hides import after a settings change',!str_contains(pce_render($view,$profiles[$id]),'Import 20i Catalog'));
}catch(Throwable $e){$checks[]=['name'=>'Unexpected connection-entry failure: '.$e->getMessage(),'passed'=>false];}
$passed=!array_filter($checks,static fn($row)=>$row['passed']!==true);
echo json_encode(['passed'=>$passed,'scope'=>'Exact production owner/current-check bodies and view fragment with modeled read-only SQL; no database, HTTP, provider or browser acceptance.','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;exit($passed?0:1);
