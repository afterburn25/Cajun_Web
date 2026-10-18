<?php
/** Native persisted forms/projections. HTTP, CSRF, TLS and Chromium are exercised separately. */
declare(strict_types=1);
require_once __DIR__.'/../site/includes/layout/service_orders.php';
function hv_render(array $c):string {
    $_SERVER['REQUEST_METHOD']='GET';$_POST=[];ob_start();hosting_fulfillment_ui($c['order']);return ob_get_clean();
}
function hv_fields(string $html):array {
    preg_match_all('/<input\b[^>]*type="hidden"[^>]*>/',$html,$tags);$fields=[];
    foreach($tags[0] as $tag)if(preg_match('/name="([^"]+)"/',$tag,$name)&&preg_match('/value="([^"]*)"/',$tag,$value))$fields[html_entity_decode($name[1],ENT_QUOTES,'UTF-8')]=html_entity_decode($value[1],ENT_QUOTES,'UTF-8');
    return $fields;
}
function hv_post(array $c):array {
    global $password;
    return hv_fields(hv_render($c))+['confirm'=>'1','current_password'=>$password,'review_account_type'=>'1','review_defaults'=>'1','review_costs'=>'1','provision_confirmation'=>'CREATE orders.example'];
}
function hv_submit(array $c,array $input,string $command='fulfill_service'):void {
    global $password;$_POST=$input;$_SERVER['REQUEST_METHOD']='POST';
    hosting_fulfillment_form_action($command,$c['order'],1,$password,static fn()=>'8.8.8.8','hf_perform');
}
(static function()use(&$checks,$password):void {
    $start=count($checks);
    try{
        $c=hf_context(70000);$o=$c['order'];$before=hf_snapshot();$calls=HfTransport::$calls;$html=hv_render($c);$fields=hv_fields($html);
        rb_ok('Owner approval renders the exact accepted order and saved mapping',($fields['action']??null)==='service_plans_order_fulfill_service'&&$fields['order_id']===$o->data()['id']&&$fields['order_hash']===$o->fingerprint()&&$fields['link_hash']===$c['link']['binding_sha256']);
        rb_ok('Typed approval presents costs defaults and accepted domain without credential material',str_contains($html,'CREATE orders.example')&&str_contains($html,'name="review_account_type"')&&str_contains($html,'name="review_defaults"')&&str_contains($html,'name="review_costs"')&&!str_contains($html,'synthetic-order-mapping-key')&&!str_contains($html,'Authorization'));
        rb_ok('Owner fulfillment GET changes no history and sends no request',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_client();rb_deny('Client cannot request the private fulfillment projection',fn()=>hosting_fulfillment_view($o->data()['id'],1));rb_staff(2);rb_deny('Financial delegate cannot open owner fulfillment controls',fn()=>hosting_fulfillment_view($o->data()['id'],1));rb_staff();
        rb_deny('Fulfillment view cannot substitute another client',fn()=>hosting_fulfillment_view($o->data()['id'],2));
        rb_deny('Preparation wrapper requires owner password',fn()=>hosting_fulfillment_prepare('wrong'));
        $rows=hf_snapshot(false);hosting_fulfillment_prepare($password);rb_ok('Repeated native setup preserves existing orders and provider attempts',hf_snapshot(false)===$rows&&HfTransport::$calls===$calls);
        $input=hv_post($c);$before=hf_snapshot();
        foreach([['confirm'=>''],['review_account_type'=>''],['review_defaults'=>['1']],['review_costs'=>'true'],['provision_confirmation'=>'CREATE foreign.example'],['order_hash'=>str_repeat('a',64)],['link_hash'=>str_repeat('b',64)],['order_revision'=>'1']] as $i=>$bad)rb_deny('Typed approval form refuses unreviewed or stale intent '.$i,fn()=>hv_submit($c,array_replace($input,$bad)));
        rb_ok('Refused approval forms do not mutate or dispatch',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>$input['action'],'provision_confirmation'=>'<script>bad</script>'];ob_start();hosting_fulfillment_ui($o);$escaped=ob_get_clean();
        rb_ok('Failed approval preserves escaped typed confirmation and no password',str_contains($escaped,'&lt;script&gt;bad&lt;/script&gt;')&&!str_contains($escaped,'<script>bad</script>')&&!str_contains($escaped,$password));
        HfTransport::$body='{"result":866250}';hv_submit($c,$input);
        rb_ok('Rendered approval submits once and shows assigned ID pending confirmation',HfTransport::$calls===$calls+1&&hosting_fulfillment_view($o->data()['id'],1)['mode']==='pending_confirmation'&&hf_read($c)->account()->providerRef()===null&&str_contains(hv_render($c),'866250'));
        $before=hf_snapshot();$calls=HfTransport::$calls;hv_submit($c,$input);rb_ok('Replayed rendered approval cannot create another provider purchase',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        rb_client();ob_start();hosting_order_links_ui($o,false,null);$clientHtml=ob_get_clean();rb_staff();
        rb_ok('Client sees pending setup without private approval provider ID or credential',str_contains($clientHtml,'Hosting setup in progress')&&!str_contains($clientHtml,'866250')&&!str_contains($clientHtml,'data-hosting-fulfillment')&&!str_contains($clientHtml,'review_costs'));
        $failed=hf_context(71000);$failureInput=hv_post($failed);HfTransport::$status=400;
        try{hv_submit($failed,$failureInput);}finally{HfTransport::$status=200;}
        $retry=hv_fields(hv_render($failed))+['confirm'=>'1'];$calls=HfTransport::$calls;
        rb_ok('Definite failure offers a separate deliberate local retry',($retry['action']??null)==='service_plans_order_retry_fulfillment');hv_submit($failed,$retry,'retry_fulfillment');
        rb_ok('Retry form only prepares a fresh approval and performs no provider request',hosting_fulfillment_view($failed['order']->data()['id'],1)['mode']==='approve'&&HfTransport::$calls===$calls&&hf_read($failed)->account()->status()==='pending');
        $lost=hf_context(72000);$lostInput=hv_post($lost);HfTransport::$body='{"result":866251}';
        rb_ok('Actual form retains response when local finalization fails',hf_audit_failure('result_recorded',fn()=>hv_submit($lost,$lostInput)));$calls=HfTransport::$calls;
        $recovery=hv_fields(hv_render($lost))+['confirm'=>'1'];rb_ok('Recovery form reviews the original saved response and approval',($recovery['action']??null)==='service_plans_order_recover_fulfillment'&&$recovery['dispatch_id']===$lostInput['event_id']&&strlen($recovery['provider_hash'])===64);
        hv_submit($lost,$recovery,'recover_fulfillment');rb_ok('Native recovery form records the existing pending ID with no send',hf_read($lost)->account()->acknowledgedResourceRef()==='866251'&&HfTransport::$calls===$calls);
        $before=hf_snapshot();hv_submit($lost,$recovery,'recover_fulfillment');rb_ok('Recovery form replay preserves all rows and request count',hf_snapshot()===$before&&HfTransport::$calls===$calls);
        $unknown=hf_context(73000);$unknownInput=hv_post($unknown);HfTransport::$body='{"result":false}';hv_submit($unknown,$unknownInput);
        $html=hv_render($unknown);rb_ok('Unknown outcome shows review without approval retry or recovery controls',str_contains($html,'outcome is unknown')&&!str_contains($html,'name="action"'));
        rb_client();ob_start();hosting_order_links_ui($unknown['order'],false,null);$html=ob_get_clean();$projection=hosting_order_links_context($unknown['order']->data()['id'],1,true);rb_staff();
        rb_ok('Client gets an honest unknown outcome with the same bounded projection',str_contains($html,'Hosting setup outcome needs review')&&$projection['progress']['status']==='unknown'&&array_keys($projection['progress'])===['account_id','created_at','status','order_cancelled']);
        $before=hf_snapshot();$calls=HfTransport::$calls;db()->exec('RENAME TABLE '.table('hosting_fulfillments').' TO '.table('view_saved_fulfillments'));
        try{rb_deny('Owner GET refuses missing established fulfillment history',fn()=>hosting_fulfillment_view($o->data()['id'],1));rb_deny('Setup form cannot recreate established missing fulfillment history',fn()=>hosting_fulfillment_prepare($password));}
        finally{db()->exec('RENAME TABLE '.table('view_saved_fulfillments').' TO '.table('hosting_fulfillments'));}
        rb_ok('View history restoration preserves all original rows without dispatch',hf_snapshot()===$before&&HfTransport::$calls===$calls);
    }catch(Throwable $e){$checks[]=['name'=>'Unexpected native fulfillment form failure: '.$e->getMessage(),'passed'=>false];throw $e;}
    finally{
        HfTransport::$status=200;HfTransport::$body='{"result":866239}';$_POST=[];$_SERVER['REQUEST_METHOD']='GET';rb_staff();
        $named=array_slice($checks,$start);file_put_contents(__DIR__.'/../qa/hosting-fulfillment-forms-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Real native order storage and rendered form adapters/projections with isolated provider transport. Actual HTTP/CSRF/Chromium and live acceptance are separate.','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
})();
