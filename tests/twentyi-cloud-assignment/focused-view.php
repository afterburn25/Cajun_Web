<?php
/** Real provider templates with synthetic projections; no database, routing or browser claim. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',realpath(__DIR__.'/../../site'));
require CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryRead.php';
$view=$argv[2]??CWP_CODE_ROOT.'/admin/views/providers.php';$checks=[];$case='current';$calls=[];
function cf_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];}
function esc(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function badge(string $v):string{return esc($v);}
function admin_url(string $view,array $params=[]):string{return '/existing/admin/index.php?'.http_build_query(['view'=>$view]+$params);}
function admin_action(string $action):void{echo '<input name="csrf" value="synthetic-csrf"><input name="action" value="'.esc($action).'">';}
function admin_input(string $name,string $label,string $value='',string $type='text',string $attrs=''):void{echo '<label>'.esc($label).'<input name="'.esc($name).'" type="'.esc($type).'" value="'.esc($value).'" '.$attrs.'></label>';}
function provider_settings_profile():?array{global $case;return $case==='no-profile'?null:['id'=>str_repeat('b',32),'revision'=>7,'enabled'=>1,'has_credentials'=>true];}
function provider_inventory_ready():bool{global $case;return $case!=='no-storage';}
function provider_cloud_site_request_confirmation_render():void{throw new LogicException('Unrelated provider projection reached.');}
function provider_inventory_latest(string $profile,string $operation):array{return ['state'=>'complete','started_at'=>'2026-10-28 23:00:00','profile_revision'=>7,'reason'=>''];}
function provider_inventory_observation(string $profile,string $operation):array{return ['id'=>str_repeat('c',32),'state'=>'complete','started_at'=>'2026-10-28 23:00:00','profile_revision'=>7];}
function provider_inventory_cloud_servers(string $id):array{global $calls;$calls[]='servers';return [['server'=>(object)['id'=>2601,'name'=>'<cloud.synthetic.invalid>','location'=>null,'productSpec'=>null,'configuration'=>null,'packageIds'=>[9991]],'unmapped_count'=>0]];}
function provider_inventory_clients():array{return [['id'=>1,'name'=>'Synthetic client','email'=>'synthetic@example.invalid']];}
function provider_cloud_links(string $profile):array{return [['id'=>str_repeat('a',32),'server_id'=>2601,'client_name'=>'Synthetic client','client_active'=>1,'linked_at'=>'2026-10-28 23:00:00','actor_name'=>'Synthetic owner','revision'=>2]];}
function provider_cloud_assignment_view(string $profile,int $revision,?string $refresh):array{global $case,$calls;$calls[]='assignment';if($case==='recovery')throw new RuntimeException('Cloud assignment history needs recovery from the original database.');return ['ready'=>$case!=='unprepared','can_approve'=>$case==='current','reason'=>'A fresh complete cloud read is required.','current_links'=>[str_repeat('a',32)=>['current'=>$case==='current','context_changed'=>false,'history_status'=>'historical']]];}
function provider_cloud_link_events(string $profile):array{return [['recorded_at'=>'2026-10-28 23:00:00','event'=>'link','server_id'=>2601,'client_name'=>'Synthetic client','actor_name'=>'Synthetic owner','revision'=>2]];}
function provider_inventory_history(string $profile,int $page,string $operation):array{return [['started_at'=>'2026-10-28 23:00:00','state'=>'complete','package_count'=>1,'skipped'=>0,'profile_revision'=>7]];}
function cf_render():string{global $view;ob_start();try{require $view;return ob_get_contents();}finally{ob_end_clean();}}
try{
    $_GET=['focus'=>'cloud-assignment'];$html=cf_render();
    cf_ok('Focused actual providers template reaches cloud inventory and assignment controls',str_contains($html,'data-provider-cloud-refresh')&&str_contains($html,'data-cloud-assignments')&&str_contains($html,'data-cloud-unlink'));
    cf_ok('Focused workspace retains explicit return to the complete provider page',str_contains($html,'href="/existing/admin/index.php?view=provider-settings"')&&str_contains($html,'Return to all 20i settings'));
    cf_ok('Focused view excludes package editor and cloud website creation work',!str_contains($html,'data-provider-editor')&&!str_contains($html,'data-provider-inventory')&&!str_contains($html,'data-cloudsite-create'));
    cf_ok('Cloud assignment and refresh history remain visible',str_contains($html,'Cloud assignment history')&&str_contains($html,'Cloud refresh history')&&str_contains($html,'assignment version 2'));
    cf_ok('Focused rendering escapes saved cloud names',str_contains($html,'&lt;cloud.synthetic.invalid&gt;')&&!str_contains($html,'<cloud.synthetic.invalid>'));
    cf_ok('Focused forms retain CSRF and blank owner password inputs',substr_count($html,'name="csrf"')===2&&substr_count($html,'name="current_password" type="password" value=""')===2);
    cf_ok('Current assignment projection is called once',count(array_filter($calls,fn($v)=>$v==='assignment'))===1);
    $case='historical';$html=cf_render();cf_ok('Focused historical context preserves warning and refuses reapproval',str_contains($html,'Historical specifications remain available')&&!str_contains($html,'data-cloud-relink'));
    $case='unprepared';$html=cf_render();cf_ok('Focused unprepared context keeps explicit preparation and no removal form',str_contains($html,'data-cloud-assignment-prepare')&&!str_contains($html,'data-cloud-unlink'));
    $case='recovery';$html=cf_render();cf_ok('Focused history recovery preserves error and hides assignment mutations',str_contains($html,'data-cloud-assignment-unavailable')&&!str_contains($html,'data-cloud-link')&&!str_contains($html,'data-cloud-unlink')&&!str_contains($html,'data-cloud-assignment-prepare'));
    foreach(['no-profile','no-storage'] as $case){$calls=[];$html=cf_render();cf_ok('Focused '.$case.' stays read-only with return navigation',str_contains($html,'Return to all 20i settings')&&!str_contains($html,'<form')&&$calls===[]);}
    $case='current';
    foreach([null,'other',['cloud-assignment']] as $focus){$_GET=$focus===null?[]:['focus'=>$focus];$denied=false;try{cf_render();}catch(LogicException $e){$denied=$e->getMessage()==='Unrelated provider projection reached.';}cf_ok('Only exact explicit focus bypasses ordinary rendering '.json_encode($focus),$denied);}
}catch(Throwable $e){$checks[]=['name'=>'Focused renderer completes without unrelated projections','passed'=>false,'failure'=>$e->getMessage()];}
$passed=!array_filter($checks,fn($c)=>!$c['passed']);$result=['passed'=>$passed,'scope'=>'Real providers, cloud inventory and assignment templates with synthetic saved projection/HTML helpers. No SQL, auth, HTTP, browser or provider acceptance.','view_sha256'=>hash_file('sha256',$view),'checks'=>$checks];
if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo ($passed?'PASS':'FAIL').': '.count($checks).' focused cloud view checks'.PHP_EOL;exit($passed?0:1);
