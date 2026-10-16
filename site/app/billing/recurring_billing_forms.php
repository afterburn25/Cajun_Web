<?php
/** Strict, bounded browser input. This module performs no queries or mutations. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/recurring-billing/RecurringBillingSchedule.php';
use CajunWebPro\Foundation\RecurringBilling\{RecurringBillingTerms,RecurringBillingSchedule};
use CajunWebPro\Foundation\BillingCalendar\BillingCalendar;

function recurring_ui_limits():array {
    return ['action'=>40,'job_id'=>10,'client_id'=>10,'request_id'=>32,'schedule_id'=>32,
        'revision'=>3,'sha256'=>64,'terms_sha256'=>64,'source_id'=>32,'source_revision'=>3,
        'source_sha256'=>64,'previous_id'=>32,'previous_sha256'=>64,'title'=>120,
        'terms'=>16384,'anchor'=>10,'alignment'=>20,'timezone'=>100,'due_after_days'=>2,
        'intent'=>10,'command'=>10,'effective_date'=>10,'period_date'=>10,'reason'=>1000,'confirm'=>1];
}
function recurring_ui_capture(array $post):array {
    $out=['invalid'=>[]];$limits=recurring_ui_limits();
    foreach($limits as $key=>$max){$value=$post[$key]??'';
        if(!is_string($value)||strlen($value)>$max||!preg_match('//u',$value)||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)){$out['invalid'][]=$key;$out[$key]='';}
        else $out[$key]=$value;
    }
    foreach($post as $key=>$value)if(!array_key_exists($key,$limits)&&!in_array($key,['csrf','owner_password'],true))$out['invalid'][]='unknown field';
    return $out;
}
function recurring_ui_valid(array $form):void {
    if($form['invalid']??[])throw new InvalidArgumentException('One or more fields are invalid or too long. The other entered details are preserved.');
}
function recurring_ui_number(mixed $value,int $max=2147483647,bool $zero=false):int {
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/',$value)||(int)$value<($zero?0:1)||(int)$value>$max)throw new InvalidArgumentException('Choose a valid record, revision or page.');
    return (int)$value;
}
function recurring_ui_hex(mixed $value,int $length=32,bool $empty=false):string {
    if($empty&&$value==='')return '';
    if(!is_string($value)||!preg_match('/\A[a-f0-9]{'.$length.'}\z/',$value))throw new InvalidArgumentException('Keep the original reviewed form identifiers.');return $value;
}
function recurring_ui_confirm(array $form):void {
    recurring_ui_valid($form);if($form['confirm']!=='1')throw new InvalidArgumentException('Review the details and explicitly confirm this action.');
}
/** Browsers submit textarea line breaks as CRLF; canonical documents retain LF. */
function recurring_ui_lines(string $text):string {return str_replace("\r\n","\n",$text);}
function recurring_ui_query(array $query,bool $staff):array {
    $allowed=['job','schedule','p','sources_page','schedules_page','history_page','invoices_page','period_date','focus_event'];
    $allowed[]=$staff?'view':'recurring';if($staff)$allowed[]='source';
    foreach($query as $key=>$value)if(!in_array($key,$allowed,true)||!is_string($value))throw new InvalidArgumentException('Use a valid recurring billing link.');
    if(($staff&&($query['view']??'')!=='recurring-billing')||(!$staff&&($query['recurring']??'')!=='1'))throw new InvalidArgumentException('Open the recurring billing page to continue.');
    $out=['job'=>isset($query['job'])?recurring_ui_number($query['job']):0,'schedule'=>'','source'=>'','period_date'=>'','focus_event'=>''];
    foreach(['schedule','source','focus_event'] as $key)if(isset($query[$key]))$out[$key]=recurring_ui_hex($query[$key]);
    foreach(['p','sources_page','schedules_page','history_page','invoices_page'] as $key)$out[$key]=recurring_ui_number($query[$key]??'0',10000,true);
    if(isset($query['period_date'])){$value=$query['period_date'];if(!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}\z/',$value))throw new InvalidArgumentException('Choose a billing period start date.');$out['period_date']=$value;}
    if(($out['source']!==''||$out['schedule']!=='')&&!$out['job'])throw new InvalidArgumentException('Choose the related project first.');
    if($out['source']!==''&&$out['schedule']!=='')throw new InvalidArgumentException('Choose an offer or an existing agreement.');
    if($out['focus_event']!==''&&$out['schedule']==='')throw new InvalidArgumentException('Choose the original recurring agreement for this event.');
    return $out;
}
/** Resolve an opaque event against the whole journal after later entries have been appended. */
function recurring_ui_history_page(RecurringBillingSchedule $doc,int $page,string $focus):int {
    if($focus==='')return $page;
    recurring_ui_hex($focus);$events=$doc->data()['events'];
    foreach($events as $i=>$event)if($event['id']===$focus)return intdiv(count($events)-1-$i,10);
    throw new InvalidArgumentException('The original recurring decision is unavailable.');
}
function recurring_ui_period(RecurringBillingTerms $terms,string $date):int {
    $t=$terms->data();$period=(new BillingCalendar($t['anchor'],$t['cadence'],$t['alignment']))->periodAt($date);
    if(!$period||$period['start']!==$date)throw new InvalidArgumentException('Use an exact billing period start date from the original agreement.');
    return $period['index'];
}
function recurring_ui_next_dates(RecurringBillingSchedule $doc,int $now):array {
    $terms=RecurringBillingTerms::fromArray($doc->data()['terms']);$current=$terms->periodAt($now);$next=($current['index']??-1)+1;$change=$next;
    foreach($doc->data()['events'] as $event)if($event['command']!=='end'&&isset($event['arguments']['period_index']))$change=max($change,$event['arguments']['period_index']);
    return ['next_date'=>$terms->period($next)['start'],'change_date'=>$terms->period($change)['start']];
}
function recurring_ui_offer_fields(array $a,array $source):array {
    recurring_ui_valid($a);
    $fields=['format'=>1,'id'=>recurring_ui_hex($a['request_id']),'client_id'=>recurring_ui_number($a['client_id']),
        'project_id'=>recurring_ui_number($a['job_id']),
        'source_subscription'=>['id'=>recurring_ui_hex($a['source_id']),'revision'=>recurring_ui_number($a['source_revision'],256),'sha256'=>recurring_ui_hex($a['source_sha256'],64)],
        'title'=>$a['title'],'terms'=>recurring_ui_lines($a['terms']),'currency'=>$source['currency'],'amount_cents'=>$source['amount_cents'],
        'cadence'=>$source['cadence'],'anchor'=>$a['anchor'],'alignment'=>$a['alignment'],'timezone'=>$a['timezone'],
        'due_after_days'=>recurring_ui_number($a['due_after_days'],60,true),'collection'=>'invoice-only'];
    recurring_ui_hex($a['previous_id'],32,true);recurring_ui_hex($a['previous_sha256'],64,true);
    if(($a['previous_id']==='')!==($a['previous_sha256']===''))throw new InvalidArgumentException('Keep the complete preceding agreement reference.');
    return RecurringBillingTerms::fromArray($fields)->data();
}
function recurring_ui_command(array $a,RecurringBillingSchedule $doc,bool $staff):array {
    recurring_ui_confirm($a);$terms=RecurringBillingTerms::fromArray($doc->data()['terms']);
    $command=$a['command'];$allowed=$staff?['withdraw','activate','pause','resume','end','issue']:['accept','decline','end'];
    if(!in_array($command,$allowed,true))throw new InvalidArgumentException('Choose an available billing action.');
    $args=match($command){
        'accept'=>['terms_sha256'=>recurring_ui_hex($a['terms_sha256'],64)],
        'withdraw','decline'=>['reason'=>recurring_ui_lines($a['reason'])],
        'activate'=>['period_index'=>recurring_ui_period($terms,$a['effective_date'])],
        'pause','resume','end'=>['period_index'=>recurring_ui_period($terms,$a['effective_date']),'reason'=>recurring_ui_lines($a['reason'])],
        'issue'=>['period_index'=>recurring_ui_period($terms,$a['period_date'])],
    };
    return ['job_id'=>recurring_ui_number($a['job_id']),'client_id'=>recurring_ui_number($a['client_id']),
        'schedule_id'=>recurring_ui_hex($a['schedule_id']),'request_id'=>recurring_ui_hex($a['request_id']),
        'revision'=>recurring_ui_number($a['revision'],256,true),'sha256'=>recurring_ui_hex($a['sha256'],64),'command'=>$command,'args'=>$args];
}
