<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
define('CWP_BOOT',true);define('CWP_CODE_ROOT',__DIR__.'/../../site');
require CWP_CODE_ROOT.'/includes/security/input.php';require CWP_CODE_ROOT.'/includes/layout/recurring_billing.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
function csrf_field():string{return '<input type="hidden" name="csrf" value="synthetic-csrf">';}
function admin_url(string $view,array $params=[]):string{return '/existing/admin/index.php?'.http_build_query(['view'=>$view]+$params);}
function site_url(string $path=''):string{return '/existing/'.$path;}
function rf_html(callable $fn):string{ob_start();try{$fn();return ob_get_contents();}finally{ob_end_clean();}}
$checks=[];
function rf_ok(string $name,bool $pass):void{global $checks;$checks[]=['name'=>$name,'passed'=>$pass];if(!$pass)throw new RuntimeException($name);}
function rf_deny(string $name,callable $fn):void{try{$fn();}catch(InvalidArgumentException|RuntimeException|LogicException){rf_ok($name,true);return;}rf_ok($name,false);}
try{
    $post=['action'=>'recurring_offer','job_id'=>'5','client_id'=>'2','request_id'=>str_repeat('a',32),'source_id'=>str_repeat('b',32),'source_revision'=>'2','source_sha256'=>str_repeat('c',64),'previous_id'=>'','previous_sha256'=>'','title'=>'Care <script>','terms'=>" Exact terms\r\nRetained\t ",'anchor'=>'2028-01-31','alignment'=>'day-of-month','timezone'=>'America/Chicago','due_after_days'=>'14','intent'=>'preview'];
    $source=['currency'=>'USD','amount_cents'=>12501,'cadence'=>'monthly'];$a=recurring_ui_capture($post);$fields=recurring_ui_offer_fields($a,$source);$terms=RecurringBillingTerms::fromArray($fields);
    rf_ok('Offer uses exact server source cents and cadence',$fields['amount_cents']===12501&&$fields['cadence']==='monthly');
    rf_ok('Offer retains original pins and normalizes browser CRLF without trimming text',$fields['source_subscription']===['id'=>$post['source_id'],'revision'=>2,'sha256'=>$post['source_sha256']]&&$fields['id']===$post['request_id']&&$fields['terms']===" Exact terms\nRetained\t "&&$a['terms']===$post['terms']);
    rf_ok('Clamped leap February is an exact original boundary',recurring_ui_period($terms,'2028-02-29')===1);
    rf_ok('Original day returns in March instead of drifting',recurring_ui_period($terms,'2028-03-31')===2);
    foreach(['2028-02-28','2028-03-29','2028-02-30','2027-12-31','2028-2-29',' 2028-02-29'] as $value)rf_deny('Invalid or non-boundary date '.$value.' refuses',fn()=>recurring_ui_period($terms,$value));
    foreach(['','0','01','-1','+1','1e2','1.0','2147483648','999999999999999999999',[],1,true] as $i=>$value)rf_deny('Malformed integer '.$i.' refuses without truncation',fn()=>recurring_ui_number($value));
    rf_ok('Zero revision and maximum ID are exact',recurring_ui_number('0',256,true)===0&&recurring_ui_number('2147483647')===2147483647);
    foreach(['job_id'=>[],'client_id'=>'0','request_id'=>str_repeat('A',32),'source_revision'=>'257','source_sha256'=>str_repeat('c',65),'terms'=>"\xff",'title'=>str_repeat('x',121),'timezone'=>'CST','alignment'=>'original-day','anchor'=>'2028-02-30','due_after_days'=>'61','amount_cents'=>'1','currency'=>'EUR','previous_id'=>str_repeat('d',32),'owner_password'=>[]] as $key=>$value){
        if($key==='owner_password'){rf_ok('Owner password is never retained in an offer draft',!array_key_exists('owner_password',recurring_ui_capture(array_replace($post,[$key=>$value]))));continue;}
        rf_deny('Invalid or untrusted offer field '.$key.' refuses',fn()=>recurring_ui_offer_fields(recurring_ui_capture(array_replace($post,[$key=>$value])),$source));
    }
    $bad=recurring_ui_capture(array_replace($post,['terms'=>str_repeat('x',16385)]));rf_ok('Oversized field is flagged while bounded text and original pins remain',$bad['terms']===''&&in_array('terms',$bad['invalid'],true)&&$bad['title']===$post['title']&&$bad['source_sha256']===$post['source_sha256']);
    $q=recurring_ui_query(['view'=>'recurring-billing','job'=>'5','source'=>str_repeat('b',32)],true);rf_ok('Exact admin project and source route parses',$q['job']===5&&$q['source']===str_repeat('b',32));
    foreach([['job'=>[]],['job'=>'5x'],['p'=>'-1'],['p'=>'10001'],['source'=>'bad'],['unexpected'=>'1'],['schedule'=>str_repeat('a',32)]] as $i=>$delta)rf_deny('Malformed or conflicting query '.$i.' refuses',fn()=>recurring_ui_query(array_replace(['view'=>'recurring-billing','job'=>'5','source'=>str_repeat('b',32)],$delta),true));
    rf_deny('Client cannot choose offer source',fn()=>recurring_ui_query(['recurring'=>'1','job'=>'5','source'=>str_repeat('b',32)],false));
    rf_deny('Record without project is refused',fn()=>recurring_ui_query(['recurring'=>'1','schedule'=>str_repeat('a',32)],false));
    rf_deny('Wrong admin view cannot receive mutation',fn()=>recurring_ui_query(['view'=>'dashboard','job'=>'5'],true));
    $doc=RecurringBillingSchedule::create($terms,1,strtotime('2027-12-01 UTC'));$form=recurring_ui_capture(['action'=>'recurring_command','job_id'=>'5','client_id'=>'2','schedule_id'=>$fields['id'],'request_id'=>str_repeat('d',32),'revision'=>'0','sha256'=>$doc->fingerprint(),'terms_sha256'=>$terms->fingerprint(),'command'=>'accept','confirm'=>'1','effective_date'=>'2028-02-29','period_date'=>'2028-02-29','reason'=>'Keep my exact reason <tag>']);
    $parsed=recurring_ui_command($form,$doc,false);rf_ok('Client acceptance sends the exact presented terms hash',$parsed['args']===['terms_sha256'=>$terms->fingerprint()]);
    $future=$doc->append(str_repeat('1',32),'accept','client:2',strtotime('2027-12-02 UTC'),['terms_sha256'=>$terms->fingerprint()])->append(str_repeat('2',32),'activate','staff:1',strtotime('2027-12-03 UTC'),['period_index'=>3]);
    rf_ok('Default changes honor a later scheduled boundary while cancellation can end earlier',recurring_ui_next_dates($future,strtotime('2027-12-04 UTC'))===['next_date'=>'2028-01-31','change_date'=>'2028-04-30']);
    foreach(['activate','pause','resume','withdraw','issue'] as $command)rf_deny('Client form rejects staff action '.$command,fn()=>recurring_ui_command(array_replace($form,['command'=>$command]),$doc,false));
    foreach(['accept','decline'] as $command)rf_deny('Staff form cannot record client '.$command,fn()=>recurring_ui_command(array_replace($form,['command'=>$command]),$doc,true));
    rf_deny('Unchecked decision refuses',fn()=>recurring_ui_command(array_replace($form,['confirm'=>'']),$doc,false));
    $resume=recurring_ui_command(array_replace($form,['command'=>'resume']),$doc,true);rf_ok('Resume retains reason and resolves date against original calendar',$resume['args']===['period_index'=>1,'reason'=>$form['reason']]);
    rf_deny('Malformed effective date cannot become a period index',fn()=>recurring_ui_command(array_replace($form,['command'=>'end','effective_date'=>'2028-02-28']),$doc,false));
    $issue=recurring_ui_command(array_replace($form,['command'=>'issue']),$doc,true);rf_ok('Invoice action keeps reviewed revision hash request and period',$issue['revision']===0&&$issue['sha256']===$doc->fingerprint()&&$issue['request_id']===$form['request_id']&&$issue['args']['period_index']===1);
    $html=rf_html(fn()=>recurring_ui_terms($fields));rf_ok('Presented terms escape active markup and explain whole periods and invoice-only collection',str_contains($html,'Care &lt;script&gt;')&&!str_contains($html,'<script>')&&str_contains($html,'$125.01 USD')&&str_contains($html,'America/Chicago')&&str_contains($html,'automatic payments')&&str_contains($html,'future whole billing periods'));
    $retained=array_replace($form,['command'=>'end','revision'=>'7','effective_date'=>'2028-03-31']);$html=rf_html(fn()=>recurring_ui_command_form($retained,true));
    rf_ok('Retained failed command preserves original revision hash request date and reason',str_contains($html,'name="revision" value="7"')&&str_contains($html,'value="'.$form['sha256'].'"')&&str_contains($html,'value="'.$form['request_id'].'"')&&str_contains($html,'value="2028-03-31"')&&str_contains($html,'Keep my exact reason &lt;tag&gt;'));
    rf_ok('Retained command needs fresh explicit confirmation and has labeled dates',str_contains($html,'name="confirm" value="1" required')&&!str_contains($html,' checked')&&str_contains($html,'Effective billing period start'));
    $html=rf_html(fn()=>recurring_ui_command_form(array_replace($form,['command'=>'issue'])));rf_ok('Invoice form pins a read-only period in its action URL and submitted fields',str_contains($html,'period_date=2028-02-29')&&str_contains($html,'type="hidden" name="period_date" value="2028-02-29"')&&!str_contains($html,'type="date" name="period_date"'));
    $html=rf_html(fn()=>recurring_ui_invoice_card(['id'=>19,'reference'=>'RI-TEST','period'=>['start'=>'2028-02-29','end'=>'2028-03-31','due'=>'2028-03-14','timezone'=>'America/Chicago'],'paid_cents'=>10000,'due_cents'=>2501,'status'=>'issued'],false));
    rf_ok('Client invoice links use existing ledger and show payments separately',str_contains($html,'invoice=19')&&str_contains($html,'$100.00 USD')&&str_contains($html,'$25.01 USD')&&str_contains($html,'end exclusive'));
    foreach([['focus_event'=>[]],['focus_event'=>'bad'],['focus_event'=>str_repeat('A',32)],['focus_event'=>str_repeat('a',32),'schedule'=>'']] as $i=>$delta)rf_deny('Invalid focus event query refuses '.$i,fn()=>recurring_ui_query(array_replace(['recurring'=>'1','job'=>'5','schedule'=>str_repeat('b',32)],$delta),false));
    $focus=recurring_ui_query(['recurring'=>'1','job'=>'5','schedule'=>str_repeat('b',32),'focus_event'=>str_repeat('a',32)],false);rf_ok('Exact focus ID survives strict recurring query parsing',$focus['focus_event']===str_repeat('a',32));
    rf_deny('Focused event without original agreement refuses',fn()=>recurring_ui_query(['recurring'=>'1','job'=>'5','focus_event'=>str_repeat('a',32)],false));
    rf_deny('Missing focused event cannot fall back to a different page',fn()=>recurring_ui_history_page($doc,0,str_repeat('a',32)));
    rf_ok('Unfocused recurring history preserves requested page',recurring_ui_history_page($doc,4,'')===4);
}catch(Throwable $e){$checks[]=['name'=>'Unexpected failure: '.$e->getMessage(),'passed'=>false];fwrite(STDERR,(string)$e.PHP_EOL);}
$passed=!in_array(false,array_column($checks,'passed'),true);$report=['passed'=>$passed,'scope'=>'Isolated recurring form/date/render behavior; no database, HTTP or payment acceptance.','php'=>PHP_VERSION,'checks'=>$checks];
if(isset($argv[1]))file_put_contents($argv[1],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo ($passed?'PASS':'FAIL').': '.count($checks).' recurring form checks'.PHP_EOL;exit($passed?0:1);
