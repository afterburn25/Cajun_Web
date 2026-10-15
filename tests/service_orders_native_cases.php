<?php
/** Extends the existing disposable MariaDB/MySQL recurring fixture; never a production seed. */
declare(strict_types=1);
require_once __DIR__.'/../site/app/hosting/order_views.php';
require_once __DIR__.'/../site/includes/layout/plans.php';
require_once __DIR__.'/../site/includes/layout/service_orders.php';
$orderCheckStart=count($checks);
function so_source(int $n=9000,int $amount=2500,string $cadence='one-time'):ServiceSubscriptionDocument {
    global $password;rb_staff();
    $terms=['name'=>'Synthetic shared hosting','cadence'=>$cadence,'price'=>$amount,'currency'=>'USD','inclusions'=>['One website'],'exclusions'=>['Domain registration'],'limits'=>['websites'=>1],'support'=>'Studio business hours'];
    $p=service_plans_create(rb_id($n+1),$terms,$password);$p=service_plans_change(rb_id($n+1),0,rb_id($n+2),'activate',[],$password);
    $s=service_plans_offer(rb_id($n),1,rb_id($n+1),$p->revision(),$p->fingerprint(),$password);
    rb_client();$s=service_plans_client_change(rb_id($n),1,0,rb_id($n+3),'consent',['terms_hash'=>$p->model()->termsHash()],true);
    rb_staff();return service_plans_subscription_change(rb_id($n),1,$s->revision(),rb_id($n+4),'activate',[],$password);
}
function so_invoice(int $amount=2500,int $job=1):int {
    query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',[$job,'SO-'.bin2hex(random_bytes(8)),'Initial hosting',$amount,'USD','Hosting agreement invoice',gmdate('Y-m-d H:i:s')]);return (int)db()->lastInsertId();
}
function so_read(int $id=9100):ServiceOrderDocument {return recurring_billing_locked(fn()=>service_orders_validate(service_orders_row(rb_id($id))));}
function so_offer(ServiceSubscriptionDocument $source,int $invoice,int $id=9100):ServiceOrderDocument {
    global $password;$hash=ledger_invoice_hash(ledger_invoice($invoice));
    return service_orders_offer(rb_id($id),$source->data()['id'],1,$source->revision(),$source->fingerprint(),$invoice,$hash,$password);
}
function so_snapshot():string {
    $out=[];foreach(['service_orders','service_order_events','studio_invoices','invoice_payments','audit'] as $name)$out[$name]=all('SELECT * FROM '.table($name).' ORDER BY id');return service_plans_json($out);
}
function so_assess(ServiceOrderDocument $doc,?int $at=null):array {return recurring_billing_locked(fn()=>service_orders_assess($doc,$at??time()));}
try{
    rb_staff();$beforeSchema=service_plans_json(all('SELECT * FROM '.table('studio_invoices').' ORDER BY id'));
    rb_ok('Order storage is not prepared by a read',!service_orders_ready());
    rb_deny('Order setup rejects incorrect owner password',fn()=>service_orders_migrate('wrong'));
    rb_staff(2);rb_deny('Financial administrator cannot prepare hosting orders',fn()=>service_orders_migrate($password));rb_staff();
    service_orders_migrate($password);rb_ok('Owner prepares additive empty order storage',service_orders_ready()&&rb_count('service_orders')===0);
    service_orders_migrate($password);rb_ok('Order preparation preserves existing invoices',$beforeSchema===service_plans_json(all('SELECT * FROM '.table('studio_invoices').' ORDER BY id')));
    db()->exec('ALTER TABLE '.table('service_orders').' ADD INDEX unexpected(revision)');rb_deny('Order schema refuses unexpected index',fn()=>service_orders_migrate($password));db()->exec('ALTER TABLE '.table('service_orders').' DROP INDEX unexpected');
    $source=so_source();$invoice=so_invoice();$otherInvoice=so_invoice(2500,2);$wrongPrice=so_invoice(2501);
    rb_deny('Order refuses another client invoice',fn()=>so_offer($source,$otherInvoice));
    rb_deny('Order refuses invoice with a different service price',fn()=>so_offer($source,$wrongPrice));
    rb_deny('Order refuses stale source hash',fn()=>service_orders_offer(rb_id(9100),$source->data()['id'],1,$source->revision(),str_repeat('a',64),$invoice,ledger_invoice_hash(ledger_invoice($invoice)),$password));
    rb_deny('Order refuses stale invoice hash',fn()=>service_orders_offer(rb_id(9100),$source->data()['id'],1,$source->revision(),$source->fingerprint(),$invoice,str_repeat('a',64),$password));
    $before=so_snapshot();rb_ok('Order offer audit failure rolls back',rb_fail(fn()=>so_offer($source,$invoice))&&so_snapshot()===$before);
    $offer=so_offer($source,$invoice);$before=so_snapshot();
    rb_ok('Order pins exact source invoice client and original offer',$offer->state()['phase']==='offered'&&$offer->data()['agreement']['sha256']===$source->fingerprint()&&$offer->data()['invoice']['id']===$invoice&&so_read()->json()===$offer->json());
    rb_ok('Exact offer replay creates no second order or audit',so_offer($source,$invoice)->json()===$offer->json()&&so_snapshot()===$before);
    rb_deny('A second initial order cannot consume the same agreement',fn()=>so_offer($source,so_invoice(),9101));
    $second=so_source(9200);rb_deny('A second agreement cannot reuse the consumed invoice',fn()=>so_offer($second,$invoice,9201));
    rb_ok('Host domain normalizes only explicit ASCII input',service_orders_domain(' Example.COM. ')==='example.com');
    foreach(['https://example.com','127.0.0.1','localhost','*.example.com','bad..example.com','x.example/','example.com:22','-x.example','x.123','éxample.com'] as $bad)rb_deny('Domain rejects '.$bad,fn()=>service_orders_domain($bad));
    $args=['domain_name'=>'client.example','review_sha256'=>$offer->fingerprint()];
    rb_staff();rb_deny('Owner cannot manufacture client hosting acceptance',fn()=>service_orders_change(rb_id(9100),1,0,rb_id(9110),'accept',$args,$password));
    rb_client(2);rb_deny('Another client cannot read hosting order',fn()=>service_orders_context($source->data()['id'],1,true));
    rb_deny('Another client cannot accept hosting order',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,true));rb_client();
    rb_deny('Hosting acceptance requires explicit client confirmation',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,false));
    $_GET['support']='synthetic';rb_deny('Support session cannot accept hosting',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,true));rb_client();
    $_SESSION['client_auth']='revoked';rb_deny('Revoked client session cannot accept hosting',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,true));rb_client();
    rb_deny('Client acceptance pins exact displayed offer',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',array_replace($args,['review_sha256'=>str_repeat('b',64)]),true));
    $before=so_snapshot();rb_ok('Client acceptance audit failure rolls back',rb_fail(fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,true))&&so_snapshot()===$before);
    $accepted=service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,true);$before=so_snapshot();
    rb_ok('Client confirms initial hosting domain and invoice',$accepted->state()['phase']==='accepted'&&$accepted->state()['domain_name']==='client.example');
    rb_ok('Exact client acceptance replay has no additional effects',service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',$args,true)->json()===$accepted->json()&&so_snapshot()===$before);
    rb_deny('Reused event cannot change hosting domain',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9110),'accept',array_replace($args,['domain_name'=>'changed.example']),true));
    rb_deny('Stale order action refuses',fn()=>service_orders_decide(rb_id(9100),1,0,rb_id(9111),'cancel',['reason'=>'No longer needed'],true));
    rb_staff();$a=so_assess($accepted);rb_ok('Unpaid accepted order is not commercially ready',!$a['eligible']&&in_array('verified-payment-incomplete',$a['blockers'],true));
    $receipt=ledger_receive($invoice,2500,'bank','Synthetic hosting receipt 9100',gmdate('Y-m-d'),hash('sha256','hosting-receipt-9100'),ledger_review_hash(ledger_invoice($invoice)));
    $a=so_assess($accepted);rb_ok('Exact verified receipt makes commercial review ready without dispatch',$a['eligible']&&$a['basis']==='verified-payment'&&rb_count('provider_profiles')===0);
    ledger_reverse($receipt,'Synthetic mistaken hosting payment',hash('sha256','hosting-reversal-9100'),ledger_review_hash(ledger_invoice($invoice)),$password);
    rb_ok('Receipt reversal immediately removes commercial readiness',!so_assess($accepted)['eligible']);
    $policy=['reason'=>'Synthetic private manual arrangement','expires_at'=>time()+3600];
    rb_deny('Manual policy requires current owner password',fn()=>service_orders_change(rb_id(9100),1,1,rb_id(9112),'allow_manual',$policy,'wrong'));
    rb_deny('Manual policy cannot exceed one day',fn()=>service_orders_change(rb_id(9100),1,1,rb_id(9112),'allow_manual',array_replace($policy,['expires_at'=>time()+90000]),$password));
    $paymentsBefore=service_plans_json(all('SELECT * FROM '.table('invoice_payments').' ORDER BY id'));
    $manual=service_orders_change(rb_id(9100),1,1,rb_id(9112),'allow_manual',$policy,$password);$a=so_assess($manual);
    rb_ok('Explicit manual policy is separate from unpaid invoice',$a['eligible']&&$a['basis']==='owner-manual-policy'&&$a['payment']['due_cents']===2500&&service_plans_json(all('SELECT * FROM '.table('invoice_payments').' ORDER BY id'))===$paymentsBefore);
    rb_ok('Manual policy expires exactly at its deadline',!so_assess($manual,$policy['expires_at'])['eligible']);
    rb_client();$clientCtx=service_orders_context($source->data()['id'],1,true);
    rb_ok('Client payment assessment omits staff policy and receipt details',array_keys($clientCtx['assessment'])===['eligible','blockers','basis']);
    ob_start();service_orders_ui($source,false);$html=ob_get_clean();
    rb_ok('Client order card shows domain invoice and cancellation without manual reason',str_contains($html,'client.example')&&str_contains($html,'Cancel hosting request')&&!str_contains($html,$policy['reason'])&&!str_contains($html,'Permit manual fulfillment'));
    rb_staff();$manual=service_orders_change(rb_id(9100),1,2,rb_id(9113),'revoke_manual',['reason'=>'Revoke the temporary arrangement'],$password);
    rb_ok('Manual revocation removes commercial readiness',!so_assess($manual)['eligible']);
    $savedEvent=one('SELECT * FROM '.table('service_order_events').' WHERE id=?',[rb_id(9110)]);
    query('UPDATE '.table('service_order_events').' SET before_sha256=? WHERE id=?',[str_repeat('f',64),rb_id(9110)]);rb_deny('Corrupted order event refuses read',fn()=>so_read());
    query('UPDATE '.table('service_order_events').' SET before_sha256=? WHERE id=?',[$savedEvent['before_sha256'],rb_id(9110)]);
    query('UPDATE '.table('studio_invoices').' SET title=? WHERE id=?',['Altered order obligation',$invoice]);
    rb_deny('Changed invoice cannot authorize hosting',fn()=>so_assess($manual));
    rb_client();$cancelled=service_orders_decide(rb_id(9100),1,3,rb_id(9114),'cancel',['reason'=>'Stop this initial hosting request'],true);
    rb_ok('Client may cancel stale invoice order while retaining billing',$cancelled->state()['phase']==='cancelled'&&one('SELECT id FROM '.table('studio_invoices').' WHERE id=?',[$invoice])!==null);
    rb_staff();rb_deny('Cancelled order cannot regain manual eligibility',fn()=>service_orders_change(rb_id(9100),1,4,rb_id(9115),'allow_manual',$policy,$password));
    query('UPDATE '.table('studio_invoices').' SET title=? WHERE id=?',['Initial hosting',$invoice]);
    $offered2=so_offer($second,so_invoice(),9201);rb_client();
    $declined=service_orders_decide(rb_id(9201),1,0,rb_id(9210),'decline',['reason'=>'Hosting is no longer required'],true);
    rb_ok('Client may decline without payment or provisioning',$declined->state()['phase']==='declined');rb_staff();
    $monthlySource=so_source(9300,1001,'monthly');
    rb_deny('Monthly service cannot consume unrelated same-amount invoice',fn()=>so_offer($monthlySource,so_invoice(1001),9310));
    rb_historical_source(9400);$monthlySource=service_plans_validate(service_plans_row(rb_id(9400),true),true);
    $billing=rb_historical_billing(9500,9400);$initialInvoice=rb_issue(9500,9510,0);$laterInvoice=rb_issue(9500,9511,1);
    rb_deny('Later recurring period cannot buy another initial service',fn()=>so_offer($monthlySource,$laterInvoice['invoice_id'],9520));
    $monthly=so_offer($monthlySource,$initialInvoice['invoice_id'],9520);
    rb_ok('Recurring hosting pins original first period schedule invoice and source',$monthly->data()['invoice']['basis']==='initial-recurring-period'&&$monthly->data()['invoice']['period_index']===0&&$monthly->data()['invoice']['schedule_id']===rb_id(9500));
    $before=so_snapshot();
    foreach(array_keys(service_orders_schema(config()['db']['prefix'])) as $name){
        $retained='retained_'.$name;db()->exec('RENAME TABLE '.table($name).' TO '.table($retained));
        try{
            rb_ok('Missing '.$name.' disables order readiness',!service_orders_ready());
            rb_deny('Missing '.$name.' refuses setup before empty recreation',fn()=>service_orders_migrate($password));
            rb_ok('Missing '.$name.' was not recreated',!one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[config()['db']['prefix'].$name]));
            save_setting('service_orders_schema_version','');rb_deny('Lost marker cannot reset established '.$name,fn()=>service_orders_migrate($password));save_setting('service_orders_schema_version','1');
        }finally{db()->exec('RENAME TABLE '.table($retained).' TO '.table($name));}
        rb_ok('Restored '.$name.' preserves exact order and receipt history',so_snapshot()===$before);
    }
    service_orders_migrate($password);
    rb_ok('Repeated populated order preparation preserves original order documents',so_read()->json()===$cancelled->json());
    rb_ok('Order actions never create payment attempts or provider profiles',rb_count('payment_attempts')===0&&rb_count('provider_profiles')===0);
    rb_ok('Order audit excludes passwords and manual policy reasons',!str_contains(service_plans_json(all('SELECT * FROM '.table('audit'))),$policy['reason'])&&!str_contains(service_plans_json(all('SELECT * FROM '.table('audit'))),$password));
}catch(Throwable $e){$checks[]=['name'=>'Order fixture unexpected failure: '.$e->getMessage(),'passed'=>false];throw $e;}
finally{
    $named=array_slice($checks,$orderCheckStart);file_put_contents(__DIR__.'/../qa/service-orders-native-results.json',json_encode(['passed'=>!in_array(false,array_column($named,'passed'),true),'scope'=>'Synthetic native shared-hosting order, current owner/client authority, exact invoice/receipt binding, manual policy, event recovery and rendered server-side forms. No HTTP/browser/provider execution.','checks'=>$named],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
}
