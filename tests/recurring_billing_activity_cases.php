<?php
/** Synthetic native activity cases after all preserved recurring/ledger/interface assertions. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!defined('CWP_BOOT')||getenv('CWP_TEST_DATABASE')!=='cwp_test')exit(2);
require_once CWP_CODE_ROOT.'/app/activity/events.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
function rba_business():array {
    $out=[];
    foreach(['recurring_billing_sources','recurring_billing_schedules','recurring_billing_events','recurring_billing_invoices','studio_invoices','invoice_payments','service_plans','service_subscriptions'] as $name){
        $hash=hash_init('sha256');$count=0;
        do{$rows=all('SELECT * FROM '.table($name).' ORDER BY id LIMIT 20 OFFSET '.$count);foreach($rows as $row){hash_update($hash,recurring_billing_json($row));$count++;}}while(count($rows)===20);
        $out[$name]=[$count,hash_final($hash)];
    }
    return $out;
}
function rba_rows(array $feed,string $id):array {return array_values(array_filter($feed['rows'],fn($r)=>$r['kind']==='recurring'&&$r['target_id']===$id));}
function rba_read(string $type,int $actor,string $key):?array {return one('SELECT * FROM '.table('activity_reads').' WHERE actor_type=? AND actor_id=? AND event_key=?',[$type,$actor,$key]);}

rb_staff();activity_migrate($password);rb_historical_source(80000);
$rbaId=rb_id(80010);$rbaFields=rb_fields(80010,80000);$rbaTerms=RecurringBillingTerms::fromArray($rbaFields);$rbaAt=time();
// Literal same-second fixture uses the real canonical lifecycle and native journal shape.
$rbaDoc=project_progress_locked(function()use($rbaTerms,$rbaId,$rbaAt):RecurringBillingSchedule {
    $initial=RecurringBillingSchedule::create($rbaTerms,1,$rbaAt);$doc=$initial;
    query('INSERT INTO '.table('recurring_billing_sources').' (id,latest_id,latest_sha256,generations) VALUES (?,NULL,NULL,0)',[rb_id(80000)]);
    query('INSERT INTO '.table('recurring_billing_schedules').' (id,subscription_id,generation,client_id,job_id,created_by,created_epoch,previous_id,previous_sha256,initial_json,journal_json,journal_sha256,revision) VALUES (?,?,1,1,1,1,?,NULL,NULL,?,?,?,0)',[$rbaId,rb_id(80000),$rbaAt,$initial->json(),$initial->json(),$initial->fingerprint()]);
    $commands=[[80011,'accept','client:1',['terms_sha256'=>$rbaTerms->fingerprint()]],[80010,'activate','staff:1',['period_index'=>0]]];
    for($i=0;$i<120;$i++)$commands[]=[81000-$i,$i%2===0?'pause':'resume','staff:1',['period_index'=>0,'reason'=>'Private recurring decision reason']];
    foreach($commands as [$id,$command,$actor,$args]){
        $next=$doc->append(rb_id($id),$command,$actor,$rbaAt,$args);$staff=str_starts_with($actor,'staff:');
        query('INSERT INTO '.table('recurring_billing_events').' (id,schedule_id,actor_staff_id,actor_client_id,revision,recorded_epoch,input_json,before_sha256,after_sha256) VALUES (?,?,?,?,?,?,?,?,?)',[rb_id($id),$rbaId,$staff?1:null,$staff?null:1,$next->revision(),$rbaAt,recurring_billing_json(['revision'=>$doc->revision(),'command'=>$command,'arguments'=>$args]),$doc->fingerprint(),$next->fingerprint()]);$doc=$next;
    }
    query('UPDATE '.table('recurring_billing_schedules').' SET journal_json=?,journal_sha256=?,revision=? WHERE id=?',[$doc->json(),$doc->fingerprint(),$doc->revision(),$rbaId]);
    query('UPDATE '.table('recurring_billing_sources').' SET latest_id=?,latest_sha256=?,generations=1 WHERE id=?',[$rbaId,$doc->fingerprint(),rb_id(80000)]);return $doc;
});
$rbaOffer='recurring-offer:'.$rbaId;$rbaEvent='recurring-event:'.$rbaId;$rbaAccepted='recurring-event:'.rb_id(80011);
rb_ok('Recurring activity fixture retains all same-second canonical decisions',rb_read(80010)->revision()===122);
$rbaBusiness=rba_business();$rbaReads=all('SELECT * FROM '.table('activity_reads').' ORDER BY id');
rb_client();$feed=activity_feed('client');$rbaPage=rba_rows($feed,$rbaId);$expected=array_reverse(array_values(array_filter($rbaDoc->data()['events'],fn($e)=>str_starts_with($e['actor'],'staff:'))));
$expectedKeys=array_map(fn($e)=>'recurring-event:'.$e['id'],$expected);$expectedKeys[]=$rbaOffer;
rb_ok('Recurring feed sorts same-second revisions before descending opaque identifiers',array_column($rbaPage,'event_key')===array_slice($expectedKeys,0,30));
$collected=array_column($rbaPage,'event_key');$cursor=$feed['next'];$pages=0;
while($cursor!==''&&$pages++<8){$older=activity_feed('client',false,$cursor);array_push($collected,...array_column(rba_rows($older,$rbaId),'event_key'));$cursor=$older['next'];}
rb_ok('Recurring complete cursor returns every original notice exactly once',$collected===$expectedKeys&&count(array_unique($collected))===122);
rb_ok('Recurring unread count retains the existing 99-plus cap',activity_count('client')===100);
$encoded=recurring_billing_json($rbaPage);
rb_ok('Recurring feed omits terms reasons amounts hashes and actor identity',!str_contains($encoded,$rbaFields['terms'])&&!str_contains($encoded,'Private recurring decision reason')&&!str_contains($encoded,'amount_cents')&&!str_contains($encoded,'sha256')&&!str_contains($encoded,'actor_staff')&&!str_contains($encoded,'created_by'));
rb_ok('Client receives original offer and separate staff decision for colliding IDs',activity_source('client',$rbaOffer)['item_title']==='Recurring billing offered'&&activity_source('client',$rbaEvent)['item_title']==='Recurring billing updated');
rb_deny('Client does not receive its own recurring consent',fn()=>activity_source('client',$rbaAccepted));
$destination=activity_destination('client',$rbaEvent);$offerDestination=activity_destination('client',$rbaOffer);
parse_str((string)parse_url($destination,PHP_URL_QUERY),$focusQuery);$focused=recurring_ui_context(recurring_ui_query($focusQuery,false),1);
rb_ok('Recurring event destination resolves the original agreement and decision page',str_contains($destination,'schedule='.$rbaId)&&str_contains($destination,'focus_event='.$rbaId)&&str_ends_with($destination,'#recurring-event-'.$rbaId)&&$focused['history']['page']===12&&in_array($rbaId,array_column($focused['history']['items'],'id'),true));
rb_ok('Recurring offer destination uses its separate immutable anchor',str_contains($offerDestination,'schedule='.$rbaId)&&!str_contains($offerDestination,'focus_event')&&str_ends_with($offerDestination,'#recurring-offer-'.$rbaId));
ob_start();recurring_ui_detail($focused);$html=ob_get_clean();
rb_ok('Recurring detail renders separate escaped offer and event anchors',str_contains($html,'id="recurring-offer-'.$rbaId.'"')&&str_contains($html,'id="recurring-event-'.$rbaId.'"'));
rb_ok('Opening recurring notices does not change personal read state',all('SELECT * FROM '.table('activity_reads').' ORDER BY id')===$rbaReads);
$savedTimezone=(string)query('SELECT @@session.time_zone')->fetchColumn();query('SET time_zone=?',['+05:30']);$phpTimezone=date_default_timezone_get();date_default_timezone_set('Pacific/Auckland');
rb_ok('Recurring recorded UTC is independent of SQL and PHP session timezones',activity_source('client',$rbaEvent)['occurred_at']===gmdate('Y-m-d H:i:s',$rbaAt));query('SET time_zone=?',[$savedTimezone]);date_default_timezone_set($phpTimezone);
$_SESSION['client_last']=time()-90;$idle=$_SESSION['client_last'];activity_passive_count('client');rb_ok('Recurring passive bell preserves client idle expiry',$_SESSION['client_last']===$idle);
activity_mark('client',[$rbaOffer],true);activity_mark('client',[$rbaOffer],true);
rb_ok('Recurring offer read is idempotent and cannot mark a same-ID event',rba_read('client',1,$rbaOffer)!==null&&rba_read('client',1,$rbaEvent)===null&&(int)query('SELECT COUNT(*) FROM '.table('activity_reads').' WHERE event_key=?',[$rbaOffer])->fetchColumn()===1);
activity_mark('client',[$rbaEvent],true);activity_mark('client',[$rbaOffer],false);
rb_ok('Recurring offer and event read namespaces remain independent',rba_read('client',1,$rbaOffer)===null&&rba_read('client',1,$rbaEvent)!==null);
$savedReads=all('SELECT * FROM '.table('activity_reads').' ORDER BY id');rb_ok('Recurring read audit failure rolls back every submitted mark',rb_fail(fn()=>activity_mark('client',[$rbaOffer],true))&&all('SELECT * FROM '.table('activity_reads').' ORDER BY id')===$savedReads);
rb_deny('Mixed unavailable recurring keys cannot partially mark visible records',fn()=>activity_mark('client',[$rbaOffer,'recurring-event:'.rb_id(999999)],true));rb_ok('Mixed recurring refusal preserves personal reads',all('SELECT * FROM '.table('activity_reads').' ORDER BY id')===$savedReads);
rb_ok('Recurring list count open and personal reads do not mutate business facts',rba_business()===$rbaBusiness);
$captured=activity_source('client',$rbaOffer);rb_client(2);rb_deny('Foreign client cannot open recurring activity',fn()=>activity_destination('client',$rbaOffer));rb_deny('Foreign client cannot mark recurring activity',fn()=>activity_mark('client',[$rbaOffer],true));rb_deny('Captured recurring projection rechecks current client identity',fn()=>recurring_activity_validate('client',[$captured]));
rb_client();query('UPDATE '.table('studio_jobs').' SET client_id=2 WHERE id=1');rb_deny('Transferred project refuses original captured recurring projection',fn()=>recurring_activity_validate('client',[$captured]));rb_client(2);rb_deny('New project owner cannot inherit original recurring notices',fn()=>activity_source('client',$rbaOffer));query('UPDATE '.table('studio_jobs').' SET client_id=1 WHERE id=1');
rb_client();query('UPDATE '.table('studio_clients').' SET active=0 WHERE id=1');rb_deny('Inactive recurring client cannot read its bell',fn()=>activity_count('client'));query('UPDATE '.table('studio_clients').' SET active=1 WHERE id=1');
rb_client();$_SESSION['client_last']=time()-1801;rb_deny('Expired recurring client cannot list updates',fn()=>activity_feed('client'));rb_client();$_SESSION['client_auth']=str_repeat('f',32);rb_deny('Revoked client login cannot open recurring history notice',fn()=>activity_source('client',$rbaOffer));
rb_staff();rb_deny('Recurring offer publisher does not receive its own notice',fn()=>activity_source('admin',$rbaOffer));rb_ok('Original publisher receives actual client recurring consent',activity_source('admin',$rbaAccepted)['target_id']===$rbaId);
rb_staff(2);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage","payments.manage"]']);
rb_ok('Other authorized financial staff receive recurring offers and changes',activity_source('admin',$rbaOffer)['source_id']===$rbaId&&activity_source('admin',$rbaEvent)['source_id']===$rbaId);
rb_ok('Recurring client reads do not mark a staff account read',rba_read('admin',2,$rbaEvent)===null);activity_mark('admin',[$rbaOffer],true);rb_staff();rb_ok('Recurring staff reads remain separate for each staff identity',rba_read('admin',1,$rbaOffer)===null);
rb_staff(2);$_SESSION['last_activity']=time()-90;$idle=$_SESSION['last_activity'];activity_passive_count('admin');rb_ok('Recurring passive staff bell preserves idle expiry',$_SESSION['last_activity']===$idle);
$captured=activity_source('admin',$rbaOffer);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage"]']);
rb_ok('Financial permission revocation preserves other authorized activity',array_filter(activity_feed('admin')['rows'],fn($r)=>$r['kind']==='recurring')===[]);
rb_deny('Financial permission revocation refuses recurring destination',fn()=>activity_destination('admin',$rbaOffer));rb_deny('Financial permission revocation refuses recurring reads',fn()=>activity_mark('admin',[$rbaOffer],false));rb_deny('Recurring validator rechecks captured staff permissions',fn()=>recurring_activity_validate('admin',[$captured]));query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage","payments.manage"]']);
rb_staff();rb_deny('Captured staff projection rechecks that current identity is not its author',fn()=>recurring_activity_validate('admin',[$captured]));
rb_client();$captured=activity_source('client',$rbaEvent);
foreach(['project_title'=>'changed','item_title'=>'changed','client_id'=>2,'job_id'=>2,'sort_sequence'=>99,'occurred_at'=>'2000-01-01 00:00:00','event_key'=>'recurring-event:'.rb_id(999999),'source_id'=>rb_id(999999)] as $field=>$value)rb_deny('Recurring captured metadata mismatch refuses '.$field,fn()=>recurring_activity_validate('client',[array_replace($captured,[$field=>$value])]));
$oldEvent=one('SELECT * FROM '.table('recurring_billing_events').' WHERE id=?',[rb_id(80011)]);query('UPDATE '.table('recurring_billing_events').' SET before_sha256=? WHERE id=?',[str_repeat('e',64),rb_id(80011)]);
foreach(['list'=>fn()=>activity_feed('client'),'count'=>fn()=>activity_count('client'),'cursor'=>fn()=>activity_feed('client',false,$rbaEvent),'open'=>fn()=>activity_destination('client',$rbaOffer),'mark'=>fn()=>activity_mark('client',[$rbaOffer],true)] as $name=>$call)rb_deny('Older off-page recurring corruption refuses '.$name,$call);
query('UPDATE '.table('recurring_billing_events').' SET before_sha256=? WHERE id=?',[$oldEvent['before_sha256'],rb_id(80011)]);
save_setting('recurring_billing_schema_version','');rb_deny('Missing marker cannot hide populated recurring storage as zero',fn()=>activity_count('client'));save_setting('recurring_billing_schema_version','future');rb_deny('Unsupported recurring marker refuses the feed',fn()=>activity_feed('client'));save_setting('recurring_billing_schema_version','1');
foreach(['client','admin'] as $audience){if($audience==='admin')rb_staff();else rb_client();$_GET['support']='synthetic';rb_deny('Support cannot borrow recurring '.$audience.' activity',fn()=>activity_feed($audience));rb_deny('Support cannot change recurring '.$audience.' reads',fn()=>activity_mark($audience,[$rbaOffer],true));}
rb_staff();for($i=0;$i<10;$i++)rb_action(80010,82000+$i,$i%2===0?'pause':'resume',['period_index'=>0,'reason'=>'Later decision']);
rb_client();$focusedAfter=recurring_ui_context(recurring_ui_query($focusQuery,false),1);
rb_ok('Previously generated event link follows its exact event after later appends',$focusedAfter['history']['page']===13&&in_array($rbaId,array_column($focusedAfter['history']['items'],'id'),true));
activity_mark('client',array_column($rbaPage,'event_key'),true);rb_ok('Marking captured recurring page leaves new arrivals unread',rba_read('client',1,'recurring-event:'.rb_id(82009))===null);
$badFocus=array_replace($focusQuery,['focus_event'=>rb_id(701)]);rb_deny('Foreign schedule event cannot substitute the requested recurring history',fn()=>recurring_ui_context(recurring_ui_query($badFocus,false),1));
rb_staff();$ended=rb_action(80010,83000,'end',['period_index'=>0,'reason'=>'Replacement fixture']);$replacement=rb_fields(83001,80000);recurring_billing_offer($replacement,$rbaId,$ended->fingerprint());
rb_client();rb_ok('Original recurring offer link survives a replacement agreement',activity_destination('client',$rbaOffer)===$offerDestination);
parse_str((string)parse_url($offerDestination,PHP_URL_QUERY),$offerQuery);$original=recurring_ui_context(recurring_ui_query($offerQuery,false),1);rb_ok('Original notice displays immutable old terms rather than the replacement',!$original['current']&&$original['document']->data()['terms']['id']===$rbaId);
$rbaBusiness=rba_business();activity_count('client');activity_feed('client');activity_destination('client',$rbaEvent);activity_mark('client',[$rbaOffer],false);rb_ok('Recurring notification activity preserves all native billing and ledger bytes',rba_business()===$rbaBusiness);rb_staff();

function rba_waiting_worker(string $audience,string $operation,string $key):array {
    $process=proc_open([PHP_BINARY,__DIR__.'/recurring_billing_native.php','activity-worker',$audience,$operation,$key],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Recurring activity worker unavailable.');
    fclose($pipes[0]);stream_set_timeout($pipes[1],20);stream_set_timeout($pipes[2],20);
    rb_ok('Recurring '.$audience.' '.$operation.' worker reaches identity lock',trim((string)fgets($pipes[1]))==='ready');return [$process,$pipes];
}
function rba_finish_worker(array $worker):string {
    [$process,$pipes]=$worker;$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($process)!==0||$errors!=='')throw new RuntimeException('Recurring worker failed: '.$errors);return $output;
}
foreach(['source','mark'] as $operation){
    $worker=staff_locked(function()use($operation,$rbaOffer):array{$worker=rba_waiting_worker('admin',$operation,$rbaOffer);query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage"]']);return $worker;});
    rb_ok('Queued recurring '.$operation.' rechecks financial permission after acquiring lock',rba_finish_worker($worker)==='denied');query('UPDATE '.table('staff_profiles').' SET permissions=? WHERE user_id=2',['["clients.manage","payments.manage"]']);
}
$oldAuth=one('SELECT auth_version FROM '.table('studio_clients').' WHERE id=1')['auth_version'];
$worker=staff_locked(function()use($rbaOffer):array{$worker=rba_waiting_worker('client','source',$rbaOffer);query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[bin2hex(random_bytes(16))]);return $worker;});
rb_ok('Queued recurring source rechecks client authentication after acquiring lock',rba_finish_worker($worker)==='denied');query('UPDATE '.table('studio_clients').' SET auth_version=? WHERE id=1',[$oldAuth]);rb_staff();
