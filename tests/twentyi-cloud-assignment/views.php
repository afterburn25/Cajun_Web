<?php
declare(strict_types=1);
define('CWP_BOOT',true);
$view=$argv[2]??__DIR__.'/../../site/admin/views/provider_cloud_links.php';$checks=[];$case='not_observed';
function cv_ok(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function provider_cloud_links(string $profile):array{return [['id'=>str_repeat('a',32),'server_id'=>2601,'client_id'=>1,'revision'=>2,'client_name'=>'Synthetic client','linked_at'=>'2026-10-16 12:00:00','actor_name'=>'Synthetic owner','client_active'=>1]];}
function provider_inventory_clients():array{return [['id'=>1,'name'=>'Synthetic client','email'=>'synthetic@example.invalid']];}
function provider_cloud_assignment_view(string $profile,int $revision,?string $refresh):array{global $case;if($case==='recovery')throw new RuntimeException('Cloud assignment history needs recovery from the original database.');return ['ready'=>$case!=='unprepared','can_approve'=>$case==='current','reason'=>'Refresh cloud inventory before approving an assignment.','current_links'=>[str_repeat('a',32)=>['current'=>$case==='current','context_changed'=>$case==='unprepared','history_status'=>$case==='not_observed'?'not_observed':'historical']]];}
function provider_inventory_cloud_servers(string $id):array{return [['server'=>(object)['id'=>2601,'name'=>'saved-cloud.synthetic.invalid']]];}
function provider_cloud_link_events(string $profile):array{return [];}
function provider_inventory_link_context_matches(array $link,array $record,?array $observation):bool{return false;}
function esc(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function admin_action(string $action):void{echo '<input name="action" value="'.esc($action).'">';}
function admin_input(string $name,string $label,string $value='',string $type='text',string $attrs=''):void{echo '<label>'.esc($label).'<input name="'.esc($name).'" type="'.esc($type).'" '.$attrs.'></label>';}
function cv_render():string{global $view;$profileId=str_repeat('b',32);$record=['revision'=>1,'enabled'=>1,'has_credentials'=>true];$cloudObservation=['id'=>str_repeat('c',32),'profile_revision'=>1];ob_start();try{require $view;return ob_get_contents();}finally{ob_end_clean();}}
try{
    $html=cv_render();cv_ok('Actual owner view distinguishes absent server from historical facts',str_contains($html,'The server is absent from the latest saved observation.')&&!str_contains($html,'Historical specifications remain available'));
    cv_ok('Absent server owner view never claims deletion or offers reapproval',str_contains($html,'does not confirm deletion')&&!str_contains($html,'data-cloud-relink'));
    $case='historical';$html=cv_render();cv_ok('Actual owner view retains historical wording only when facts exist',str_contains($html,'Historical specifications remain available')&&!str_contains($html,'The server is absent'));
    $case='current';$html=cv_render();cv_ok('Actual owner view identifies current reviewed context',str_contains($html,'Approved provider context matches the current observation.')&&!str_contains($html,'Historical specifications remain available'));
    $case='unprepared';$html=cv_render();cv_ok('Unprepared owner view offers explicit preparation only',str_contains($html,'data-cloud-assignment-prepare')&&!str_contains($html,'data-cloud-unlink')&&!str_contains($html,'data-cloud-relink'));
    $case='recovery';$html=cv_render();cv_ok('Actual owner recovery notice preserves page without mutation forms',str_contains($html,'data-cloud-assignment-unavailable')&&str_contains($html,'Restore the original records')&&!str_contains($html,'<form'));
    $result=['scope'=>'Actual owner view include with synthetic saved projection/auth rendering stubs, not native DB/HTTP or a screenshot.','view_sha256'=>hash_file('sha256',$view),'checks'=>$checks];if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($checks)." cloud assignment view checks passed\n";
}catch(Throwable $e){if(isset($argv[1]))file_put_contents($argv[1],json_encode(['checks'=>$checks,'failure'=>$e->getMessage(),'view_sha256'=>hash_file('sha256',$view)],JSON_PRETTY_PRINT));fwrite(STDERR,$e->getMessage()."\n");exit(1);}
