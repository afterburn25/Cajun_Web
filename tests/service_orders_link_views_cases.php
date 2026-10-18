<?php
/** Real native form adapter/projections; HTTP/CSRF and visual checks live in the browser suite. */
declare(strict_types=1);
require_once __DIR__.'/../site/app/hosting/order_views.php';
require_once __DIR__.'/../site/includes/layout/plans.php';
require_once __DIR__.'/../site/includes/layout/service_orders.php';
$viewCheckStart=count($checks);
try{
    rb_staff();$viewOrder=sol_manual(sol_accepted(42000),42012);$viewAgreement=$viewOrder->data()['agreement']['id'];
    $unmapped=hosting_order_links_context($viewOrder->data()['id'],1);
    rb_ok('Owner sees ready preparation and safe saved connection',$unmapped['ready']&&$unmapped['progress']===null&&$unmapped['profile']['has_credentials']&&!isset($unmapped['profile']['credentials']));
    rb_client();rb_ok('Unmapped client projection excludes connection and order internals',hosting_order_links_context($viewOrder->data()['id'],1,true)===['ready'=>true,'progress'=>null]);
    rb_deny('Client cannot enter owner preparation',fn()=>hosting_order_links_context($viewOrder->data()['id'],1));
    rb_client(2);rb_deny('Other client cannot read mapped order progress',fn()=>hosting_order_links_context($viewOrder->data()['id'],2,true));
    rb_client();$_SESSION['client_auth']='revoked';rb_deny('Revoked client session cannot read hosting progress',fn()=>hosting_order_links_context($viewOrder->data()['id'],1,true));
    rb_client();$_GET=['support'=>'1'];rb_deny('Support impersonation cannot inspect consent workflow progress',fn()=>hosting_order_links_context($viewOrder->data()['id'],1,true));rb_staff();
    $input=['confirm'=>'1','mapping_only'=>'1','agreement_id'=>$viewAgreement,'order_id'=>$viewOrder->data()['id'],'order_revision'=>(string)$viewOrder->revision(),'order_hash'=>$viewOrder->fingerprint(),'account_id'=>rb_id(42100),'profile_id'=>$solProfile,'profile_revision'=>'2','reseller_id'=>'42','package_type'=>'811','package_label'=>'Reviewed <package>','package_region'=>'Reviewed location','package_websites'=>'1','package_disk_mb'=>'0','current_password'=>$password];
    $p=hosting_order_links_form_package($viewOrder,$input);
    rb_ok('Typed package form preserves explicit zero and omits unknown limits',$p->limits===['websites'=>1,'disk_mb'=>0]&&$p->providerPackageType==='811');
    foreach([['package_type'=>'*'],['package_type'=>'811/other'],['package_type'=>'0'],['package_websites'=>'-1'],['package_websites'=>'1.5'],['package_disk_mb'=>'2147483648'],['package_websites'=>'','package_disk_mb'=>''],['package_region'=>''],['package_label'=>['array']]] as $i=>$bad)rb_deny('Typed mapping rejects malformed fields '.$i,fn()=>hosting_order_links_form_package($viewOrder,array_replace($input,$bad)));
    $before=sol_snapshot();
    foreach([['confirm'=>''],['mapping_only'=>''],['agreement_id'=>rb_id(99999)],['order_hash'=>str_repeat('a',64)],['profile_revision'=>'1'],['current_password'=>'wrong']] as $i=>$bad){$_POST=array_replace($input,$bad);rb_deny('Preparation form refuses stale or unconfirmed intent '.$i,fn()=>service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true));}
    rb_ok('Rejected mapping forms preserve native history',sol_snapshot()===$before);
    // Reproduce an orphan current revision left by the former browser fixture.
    // Missing original history must still roll back the entire service mapping.
    query('UPDATE '.table('provider_profiles').' SET revision=3 WHERE id=?',[$solProfile]);
    try{$_POST=array_replace($input,['profile_revision'=>'3']);rb_deny('Preparation refuses current provider revision without original history',fn()=>service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true));rb_ok('Missing provider revision leaves no service association or audit',sol_snapshot()===$before);}
    finally{query('UPDATE '.table('provider_profiles').' SET revision=2 WHERE id=?',[$solProfile]);}
    rb_staff(2);$_POST=$input;rb_deny('Financial staff cannot submit order mapping form',fn()=>service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true));rb_staff();
    // Exercise the actual server-rendered hidden intent, not just a hand-assembled POST.
    $_POST=[];$_SERVER['REQUEST_METHOD']='GET';ob_start();hosting_order_links_ui($viewOrder,true,['eligible'=>true]);$rendered=ob_get_clean();
    preg_match_all('/<input\b[^>]*type="hidden"[^>]*>/',$rendered,$tags);$hidden=[];
    foreach($tags[0] as $tag){if(preg_match('/name="([^"]+)"/',$tag,$name)&&preg_match('/value="([^"]*)"/',$tag,$value))$hidden[html_entity_decode($name[1],ENT_QUOTES,'UTF-8')]=html_entity_decode($value[1],ENT_QUOTES,'UTF-8');}
    rb_ok('Rendered preparation form retains exact order and provider context',($hidden['order_hash']??null)===$viewOrder->fingerprint()&&($hidden['order_revision']??null)===(string)$viewOrder->revision()&&($hidden['profile_id']??null)===$solProfile&&($hidden['profile_revision']??null)==='2'&&($hidden['agreement_id']??null)===$viewAgreement);
    $input=array_replace($input,$hidden);$viewAccount=$input['account_id'];$_POST=$input;service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true);
    $before=sol_snapshot();service_orders_form_action('service_plans_order_map_service',$viewAgreement,1,true);
    rb_ok('Real form adapter creates one order service and exact replay is inert',sol_snapshot()===$before&&one('SELECT account_id FROM '.table('hosting_order_links').' WHERE id=?',[$viewOrder->data()['id']])['account_id']===$viewAccount);
    $ownerView=hosting_order_links_context($viewOrder->data()['id'],1);
    rb_ok('Owner sees reviewed mapping and current connection',$ownerView['progress']['package']['label']==='Reviewed <package>'&&$ownerView['progress']['profile_current']);
    rb_client();$clientView=hosting_order_links_context($viewOrder->data()['id'],1,true);
    rb_ok('Client progress has only approved service identity time and state',array_keys($clientView['progress'])===['account_id','created_at','status','order_cancelled']&&$clientView['progress']['status']==='pending'&&!$clientView['progress']['order_cancelled']);
    $text=service_plans_json($clientView);rb_ok('Client progress excludes provider mapping profile and manual reason',!str_contains($text,$solProfile)&&!str_contains($text,'Reviewed <package>')&&!str_contains($text,'Synthetic owner approved unpaid setup'));
    $viewOrder=service_orders_decide($viewOrder->data()['id'],1,$viewOrder->revision(),rb_id(42013),'cancel',['reason'=>'Client cancels prepared order'],true);
    rb_ok('Client cancellation retains pending service history honestly',hosting_order_links_context($viewOrder->data()['id'],1,true)['progress']['order_cancelled']&&hosting_order_links_context($viewOrder->data()['id'],1,true)['progress']['status']==='pending');
    rb_staff();$raw=one('SELECT * FROM '.table('hosting_order_links').' WHERE id=?',[$viewOrder->data()['id']]);query('DELETE FROM '.table('hosting_order_links').' WHERE id=?',[$raw['id']]);
    try{rb_deny('Missing service association cannot become an empty preparation screen',fn()=>hosting_order_links_context($viewOrder->data()['id'],1));rb_client();rb_deny('Client missing association returns restoration refusal',fn()=>hosting_order_links_context($viewOrder->data()['id'],1,true));}
    finally{rb_staff();query('INSERT INTO '.table('hosting_order_links').' ('.implode(',',array_keys($raw)).') VALUES ('.implode(',',array_fill(0,count($raw),'?')).')',array_values($raw));}
    db()->exec('RENAME TABLE '.table('hosting_order_links').' TO '.table('view_saved_links'));
    try{rb_deny('Owner progress GET refuses missing table before querying it',fn()=>hosting_order_links_context($viewOrder->data()['id'],1));rb_client();rb_deny('Client progress GET refuses missing table before querying it',fn()=>hosting_order_links_context($viewOrder->data()['id'],1,true));rb_staff();rb_deny('Preparation cannot recreate established missing association history',fn()=>hosting_order_links_prepare($password));}
    finally{db()->exec('RENAME TABLE '.table('view_saved_links').' TO '.table('hosting_order_links'));}
    rb_ok('UI preparation never creates provider dispatch or attempt',rb_count('hosting_dispatches')===0&&rb_count('provider_operations')===0);
    $_POST=[];rb_staff();
}catch(Throwable $e){$checks[]=['name'=>'Order preparation UI native fixture unexpected failure: '.$e->getMessage(),'passed'=>false];throw $e;}
finally{
    $named=array_slice($checks,$viewCheckStart);file_put_contents(__DIR__.'/../qa/service-orders-link-views-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Native order preparation form parsing, owner/client projections, isolation, stale intent, cancellation and recovery. No HTTP, verified provider catalog, dispatch or live acceptance.','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
