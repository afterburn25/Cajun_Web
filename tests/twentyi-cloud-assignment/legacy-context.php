<?php
declare(strict_types=1);
require __DIR__.'/../../foundation/twentyi-inventory/InventoryRead.php';
class_alias(CajunWebPro\Foundation\TwentyIInventory\InventoryRead::class,'InventoryRead');
require __DIR__.'/../../foundation/twentyi-inventory/InventoryCloudServer.php';
class_alias(CajunWebPro\Foundation\TwentyIInventory\InventoryCloudServer::class,'InventoryCloudServer');
/** Execute exact production functions with synthetic persistence/auth stubs; not native SQL. */
$source=$argv[2]??__DIR__.'/../../site/app/providers/cloud_assignment.php';
$storage=__DIR__.'/../../site/app/providers/cloud_assignment_storage.php';
$wrapper=$argv[3]??__DIR__.'/../../site/app/providers/cloud_links.php';
$layout=$argv[4]??__DIR__.'/../../site/includes/layout/provider_services.php';
function lc_extract(string $path,array $names):void {
    $tokens=token_get_all(file_get_contents($path));$found=[];
    for($i=0;$i<count($tokens);++$i){if(!is_array($tokens[$i])||$tokens[$i][0]!==T_FUNCTION)continue;$j=$i+1;while(is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)++$j;$name=is_array($tokens[$j])?$tokens[$j][1]:'';if(!in_array($name,$names,true))continue;
        $code='';$depth=0;$opened=false;for(;$i<count($tokens);++$i){$t=$tokens[$i];$code.=is_array($t)?$t[1]:$t;if($t==='{'){++$depth;$opened=true;}elseif($t==='}'&&--$depth===0&&$opened)break;}eval($code);$found[]=$name;
    }sort($found);sort($names);if($found!==$names)throw new RuntimeException('Exact source function set missing.');
}
lc_extract($source,['provider_cloud_assignment_profile_context','provider_cloud_assignment_record_event','provider_cloud_assignment_project','provider_cloud_assignment_retained','provider_cloud_assignment_client_services']);
lc_extract($wrapper,['provider_cloud_client_services']);
lc_extract(__DIR__.'/../../site/app/providers/inventory.php',['provider_inventory_link_context_matches']);
lc_extract($storage,['provider_cloud_assignment_fields','provider_cloud_assignment_event_fields','provider_cloud_assignment_link_fields']);
$checks=[];$queries=0;$opens=0;$record=[];$doc=[];
$client=['id'=>1];$clientActive=true;$inventoryReady=true;$prepared=false;$readyError=null;$linkQueries=0;$factQueries=0;$inspections=0;$writes=0;$hasAssignment=true;$assignmentChecks=0;
function lc_check(string $name,bool $passed):void{global $checks;$checks[]=['name'=>$name,'passed'=>$passed];if(!$passed)throw new RuntimeException($name);}
function provider_cloud_assignment_guard():void{}
function provider_settings_load(string $id):array{global $profile;return $profile;}
function provider_cloud_assignment_append(string $kind,string $id,string $profile,int $actor,bool $legacy,array $source,array $extra):array{global $record;return $record=['kind'=>$kind,'source_id'=>$id,'profile_id'=>$profile,'actor_id'=>$actor,'legacy'=>$legacy,'source'=>$source,'extra'=>$extra];}
function provider_cloud_assignment_event_audit(array $event):array{return ['synthetic'=>'exact-event-audit'];}
function provider_cloud_assignment_owner_auth(int $id):string{return str_repeat('a',64);}
function provider_cloud_assignment_assignment(array $link):array{global $doc;return ['witness'=>['document'=>$doc]];}
function provider_cloud_assignment_decode(array $row):array{return $row;}
function provider_cloud_assignment_error():never{throw new RuntimeException('Cloud assignment history needs recovery from the original database.');}
function table(string $name):string{return $name;}
function one(string $sql,array $params=[]):?array{global $queries,$event,$profile,$clientActive,$factQueries,$hasAssignment,$assignmentChecks;$queries++;
    if(str_contains($sql,'studio_clients'))return $clientActive&&$params===[1]?['id'=>1]:null;
    if(str_contains($sql,'provider_profiles'))return $profile;
    if(str_contains($sql,'provider_cloud_links')){++$assignmentChecks;return $hasAssignment&&$params===[1]?['id'=>str_repeat('1',32)]:null;}
    if(str_contains($sql,'provider_cloud_link_events'))return $event;
    if(str_contains($sql,'provider_refreshes'))return ['id'=>str_repeat('f',32),'started_at'=>'2026-10-16 12:00:00','key_fingerprint'=>str_repeat('a',64),'profile_revision'=>1];
    if(str_contains($sql,'provider_observation_cloud')){++$factQueries;return ['id'=>str_repeat('e',32),'server_id'=>2601,'metadata'=>json_encode(['id'=>2601,'name'=>'old-account.synthetic.invalid','location'=>'saved'])];}
    throw new LogicException('Unexpected synthetic query.');}
function all(string $sql,array $params=[]):array{global $link,$linkQueries;++$linkQueries;if(str_contains($sql,'provider_cloud_links')&&$params===[1])return [$link];throw new LogicException('Unexpected synthetic collection query.');}
function studio_client():?array{global $client;return $client;}
function provider_inventory_ready():bool{global $inventoryReady;return $inventoryReady;}
function provider_cloud_assignment_ready():bool{global $prepared,$readyError;if($readyError instanceof Throwable)throw $readyError;return $prepared;}
function provider_cloud_assignment_inspect(callable $f):mixed{global $inspections,$onInspect;++$inspections;if(is_callable($onInspect))$onInspect();return $f();}
function query(string $sql,array $params=[]):never{global $writes;++$writes;throw new LogicException('Projection attempted mutation.');}
function save_setting(string $name,string $value):never{global $writes;++$writes;throw new LogicException('Projection attempted setup.');}
function provider_cloud_assignment_server(string $id,int $server):array{return ['server'=>(object)['id'=>$server,'name'=>'old-account.synthetic.invalid','location'=>'saved','productSpec'=>null,'configuration'=>null]];}
function provider_cloud_assignment_binding(string $id,?string $refresh=null):never{throw new RuntimeException('Synthetic current authority is deliberately unavailable.');}
function provider_settings_unseal(string $id,string $environment,string $encoded):never{global $opens;++$opens;throw new LogicException('A status path tried to decrypt a credential.');}
function lc_review_required(callable $read):bool{try{$read();}catch(RuntimeException $e){return !$e instanceof PDOException&&$e->getMessage()==='Cloud assignment history requires owner review before client details are available.';}return false;}
/** Exact existing client layout's cloud exception/empty-state fragment, not a whole-page render. */
function lc_cloud_notice():string{global $layout;$text=file_get_contents($layout);$start='$cloudUnavailable=false;';$end='    echo '.chr(39).'<div class="workspace-grid">'.chr(39).';';
    if(substr_count($text,$start)!==1||substr_count($text,$end)!==1)throw new LogicException('Client notice fragment anchors changed.');
    $from=strpos($text,$start);$to=strpos($text,$end);if($to<=$from)throw new LogicException('Client notice fragment order changed.');
    $client=studio_client();$services=[];$domains=[];ob_start();try{eval(substr($text,$from,$to-$from));return (string)ob_get_contents();}finally{ob_end_clean();}
}

$profile=['revision'=>1,'enabled'=>1,'environment'=>'live','credentials'=>'synthetic-sealed-key-B'];
$link=['id'=>str_repeat('1',32),'profile_id'=>str_repeat('2',32),'server_id'=>2601,'client_id'=>1,'revision'=>1,'linked_by'=>1,'linked_at'=>'2026-10-16 12:00:00','key_fingerprint'=>str_repeat('a',64),'profile_revision'=>1,'refresh_id'=>str_repeat('f',32)];
$event=['id'=>1,'link_id'=>$link['id'],'profile_id'=>$link['profile_id'],'server_id'=>2601,'client_id'=>1,'revision'=>1,'event'=>'link','actor_id'=>1,'recorded_at'=>$link['linked_at'],'key_fingerprint'=>$link['key_fingerprint'],'profile_revision'=>1,'refresh_id'=>$link['refresh_id']];
try {
    provider_cloud_assignment_record_event($event,$link,null,true);$doc=$record;
    lc_check('Same-revision replacement is not imported as original legacy credential identity',$doc['extra']['credentials']===null);
    lc_check('Import preserves original fingerprint and assignment source',$doc['source']['key_fingerprint']===$link['key_fingerprint']&&$doc['extra']['snapshot']===provider_cloud_assignment_link_fields($link));
    $before=$queries;$out=provider_cloud_assignment_project($link);
    lc_check('Old-account facts remain hidden from replacement credential context',$out['server']===null&&$out['context_changed']&&!$out['current']);
    lc_check('Unknown legacy projection does not select retained server facts',$queries===$before);
    $profile['credentials']='synthetic-sealed-key-A';$out=provider_cloud_assignment_project($link);
    lc_check('Restoring bytes alone does not fabricate missing original credential evidence',$out['server']===null&&$out['context_changed']&&!$out['current']);
    lc_check('Retained legacy event validates with unknown credential context',provider_cloud_assignment_retained($doc,['baseline_event_id'=>1])===$doc);
    $forged=$doc;$forged['extra']['credentials']=provider_cloud_assignment_profile_context($profile);$refused=false;try{provider_cloud_assignment_retained($forged,['baseline_event_id'=>1]);}catch(RuntimeException $e){$refused=$e->getMessage()==='Cloud assignment history needs recovery from the original database.';}
    lc_check('Retained validation refuses a credential fabricated onto legacy import',$refused);
    // The owner/read boundary itself is native-test scope; this tests only its event representation.
    provider_cloud_assignment_record_event($event,$link,['synthetic'=>'approved-read'],false);$doc=$record;
    lc_check('New explicitly approved event retains exact sealed credential context',$doc['extra']['credentials']===provider_cloud_assignment_profile_context($profile));
    $out=provider_cloud_assignment_project($link);lc_check('Known original context can retain explicitly historical facts',$out['server']['name']==='old-account.synthetic.invalid'&&!$out['current']&&$out['history_status']==='historical');
    $profile['credentials']='synthetic-sealed-key-B';$before=$queries;$out=provider_cloud_assignment_project($link);lc_check('Later replacement hides newly approved original-account history',$out['server']===null&&$out['context_changed']&&$queries===$before);
    lc_check('Synthetic ordinary status never opens credentials',$opens===0);
    // Exact public wrapper plus exact protected client projection. Readiness/auth
    // and persistence are synthetic; this does not claim mutex or native SQL coverage.
    $before=[$factQueries,$linkQueries];
    lc_check('Unprepared assigned client receives review state instead of original-account facts',lc_review_required(fn()=>provider_cloud_client_services(1)));
    lc_check('Unprepared public projection never selects legacy links or saved server facts',[$factQueries,$linkQueries]===$before);
    $profile['credentials']='synthetic-sealed-key-A';
    lc_check('Restoring original sealed bytes cannot establish unprepared public sharing',lc_review_required(fn()=>provider_cloud_client_services(1)));
    $notice=lc_cloud_notice();lc_check('Existing client layout renders review state for unprepared assignment',str_contains($notice,'data-client-cloud-unavailable')&&str_contains($notice,'Cloud details are temporarily unavailable.')&&!str_contains($notice,'No hosting services are linked')&&!str_contains($notice,'old-account.synthetic.invalid'));
    $hasAssignment=false;lc_check('Genuinely unassigned active client keeps empty projection',provider_cloud_client_services(1)===[]);
    $notice=lc_cloud_notice();lc_check('Existing client layout distinguishes genuinely unassigned client',str_contains($notice,'No hosting services are linked to your account yet.')&&!str_contains($notice,'data-client-cloud-unavailable'));$hasAssignment=true;
    $inventoryReady=false;$before=[$queries,$inspections];lc_check('Unavailable inventory refuses public projection before inspection',provider_cloud_client_services(1)===[]&&[$queries,$inspections]===$before);$inventoryReady=true;
    $client=null;$before=[$queries,$inspections];lc_check('Signed-out public projection refuses before storage access',provider_cloud_client_services(1)===[]&&[$queries,$inspections]===$before);$client=['id'=>1];
    $before=[$queries,$inspections];lc_check('Public projection refuses a different client before storage access',provider_cloud_client_services(2)===[]&&[$queries,$inspections]===$before);
    $clientActive=false;$before=[$linkQueries,$factQueries,$inspections];lc_check('Inactive client cannot reach unprepared or prepared facts',provider_cloud_client_services(1)===[]&&[$linkQueries,$factQueries,$inspections]===$before);$clientActive=true;
    $prepared=true;provider_cloud_assignment_record_event($event,$link,null,true);$doc=$record;$out=provider_cloud_client_services(1);
    lc_check('Prepared imported legacy assignment still hides unknown original credential facts',count($out)===1&&$out[0]['server']===null&&$out[0]['context_changed']&&!$out[0]['current']);
    provider_cloud_assignment_record_event($event,$link,['synthetic'=>'approved-read'],false);$doc=$record;$out=provider_cloud_client_services(1);
    lc_check('Public wrapper preserves explicitly approved historical sharing',count($out)===1&&$out[0]['server']['name']==='old-account.synthetic.invalid'&&!$out[0]['current']&&$out[0]['history_status']==='historical');
    $notice=lc_cloud_notice();lc_check('Protected historical sharing does not render unavailable or unlinked',!str_contains($notice,'data-client-cloud-unavailable')&&!str_contains($notice,'No hosting services are linked'));
    $profile['credentials']='synthetic-sealed-key-B';$before=$factQueries;$out=provider_cloud_client_services(1);
    lc_check('Protected public projection hides facts after later credential replacement',count($out)===1&&$out[0]['server']===null&&$out[0]['context_changed']&&$factQueries===$before);
    $before=[$queries,$inspections];lc_check('Prepared wrapper cannot select another client history',provider_cloud_client_services(2)===[]&&[$queries,$inspections]===$before);
    $readyError=new RuntimeException('Cloud assignment history needs recovery from the original database.');$refused=false;$before=$factQueries;try{provider_cloud_client_services(1);}catch(RuntimeException $e){$refused=$e->getMessage()==='Cloud assignment history needs recovery from the original database.';}
    lc_check('Missing established history propagates recovery without legacy fallback',$refused&&$factQueries===$before);
    $notice=lc_cloud_notice();lc_check('Existing recovery notice still hides facts and suppresses unlinked text',str_contains($notice,'data-client-cloud-unavailable')&&!str_contains($notice,'No hosting services are linked')&&!str_contains($notice,'old-account.synthetic.invalid'));
    $readyError=new PDOException('Synthetic persistence failure.');$refused=false;try{provider_cloud_client_services(1);}catch(PDOException $e){$refused=$e->getMessage()==='Synthetic persistence failure.';}
    lc_check('Public projection preserves persistence exceptions',$refused);$readyError=null;
    lc_check('All public status cases leave credential opening and setup mutations at zero',$opens===0&&$writes===0);
    // Simulate only the state change between outer authentication and inspection entry.
    // These exact-function cases are not real lock or database concurrency execution.
    $profile['credentials']='synthetic-sealed-key-A';$prepared=true;$clientActive=true;
    provider_cloud_assignment_record_event($event,$link,['synthetic'=>'approved-read'],false);$doc=$record;
    $before=[$factQueries,$linkQueries];$onInspect=function():void{global $clientActive;$clientActive=false;};
    $out=provider_cloud_client_services(1);$onInspect=null;$clientActive=true;
    lc_check('Deactivation at guarded entry returns no protected client facts',$out===[]&&[$factQueries,$linkQueries]===$before);
    $onInspect=function():void{global $client;$client=null;};$out=provider_cloud_client_services(1);$onInspect=null;$client=['id'=>1];
    lc_check('Lost client authentication at guarded entry returns no facts',$out===[]&&[$factQueries,$linkQueries]===$before);
    $onInspect=function():void{global $client;$client=['id'=>2];};$out=provider_cloud_client_services(1);$onInspect=null;$client=['id'=>1];
    lc_check('Changed client identity at guarded entry returns no facts',$out===[]&&[$factQueries,$linkQueries]===$before);
    $out=provider_cloud_client_services(1);lc_check('Restoring the same active client retains original historical sharing',count($out)===1&&$out[0]['server']['name']==='old-account.synthetic.invalid'&&!$out[0]['current']&&$out[0]['history_status']==='historical'&&$opens===0&&$writes===0);
    $result=['scope'=>'Exact extracted production record/retained/project/client functions and public wrapper with synthetic auth/persistence; no SQL, mutex, full application or provider execution.','source_sha256'=>hash_file('sha256',$source),'wrapper_sha256'=>hash_file('sha256',$wrapper),'layout_sha256'=>hash_file('sha256',$layout),'checks'=>$checks];
    if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo count($checks)." legacy credential boundary checks passed\n";
}catch(Throwable $e){if(isset($argv[1]))file_put_contents($argv[1],json_encode(['scope'=>'Exact extracted function synthetic regression, not native.','source_sha256'=>hash_file('sha256',$source),'wrapper_sha256'=>hash_file('sha256',$wrapper),'layout_sha256'=>hash_file('sha256',$layout),'checks'=>$checks,'failure'=>$e->getMessage()],JSON_PRETTY_PRINT));fwrite(STDERR,$e->getMessage()."\n");exit(1);}
