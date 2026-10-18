<?php
/** Bounded, repairable HTML form values; native financial APIs remain authoritative. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/design-payments/DesignPaymentSchedule.php';
use CajunWebPro\Foundation\DesignPayments\DesignPaymentSchedule;
function design_financial_capture(array $post,bool $offer):array {
    $limits=['job_id'=>10,'client_id'=>10,'request_id'=>32,'expected'=>64];
    $limits+=$offer?['proposal_id'=>10,'proposal_version'=>10,'proposal_sha256'=>64,'title'=>160,'terms'=>30000,'form_action'=>80]:['schedule_id'=>32,'fingerprint'=>64,'stage_id'=>32,'choice'=>20,'comment'=>5000,'confirm'=>1];
    $allowed=array_merge(array_keys($limits),['action','csrf'],$offer?['items','stages']:[]);$out=['invalid'=>[]];
    foreach(array_keys($post) as $key)if(!in_array($key,$allowed,true))$out['invalid'][]='unknown field';
    foreach($limits as $key=>$limit){$v=$post[$key]??'';if(!is_string($v)||strlen($v)>$limit||preg_match('//u',$v)!==1){$out['invalid'][]=$key;$v='';}$out[$key]=$v;}
    if($offer)foreach(['items'=>[50,['id'=>32,'description'=>2000,'quantity'=>3,'unit'=>20,'discount'=>20,'tax'=>20]],'stages'=>[32,['id'=>32,'kind'=>20,'title'=>160,'calculation'=>20,'value'=>20,'due_date'=>10,'milestone_id'=>32]]] as $name=>[$max,$fields]){
        $rows=$post[$name]??null;$out[$name]=[];
        if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>$max){$out['invalid'][]=$name;continue;}
        foreach($rows as $row){$saved=[];if(!is_array($row)||array_diff(array_keys($row),array_keys($fields))||array_diff(array_keys($fields),array_keys($row))){$out['invalid'][]=$name;continue;}
            foreach($fields as $key=>$limit){$v=$row[$key];if(!is_string($v)||strlen($v)>$limit||preg_match('//u',$v)!==1){$out['invalid'][]=$name.'.'.$key;$v='';}$saved[$key]=$v;}$out[$name][]=$saved;
        }
    }return $out;
}
function design_financial_number(string $value,int $max=2147483647):int {$limit=(string)$max;if(!preg_match('/\A[1-9][0-9]{0,9}\z/',$value)||strlen($value)>strlen($limit)||(strlen($value)===strlen($limit)&&strcmp($value,$limit)>0))throw new InvalidArgumentException('Use a positive whole number within the supported range.');return (int)$value;}
function design_financial_decimal(string $value):int {
    if(!preg_match('/\A(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,2}))?\z/',$value,$m))throw new InvalidArgumentException('Use an exact decimal with at most two places, without commas or extra characters.');
    return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
}
function design_financial_decimal_text(int $cents):string{return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);}
function design_financial_binding(array $a):array {
    if($a['invalid'])throw new InvalidArgumentException('Some financial values were malformed or oversized and could not be retained. Review the form or reload the saved agreement.');
    foreach(['request_id'] as $field)if(!preg_match('/\A[a-f0-9]{32}\z/',$a[$field]))throw new InvalidArgumentException('The original financial request is unavailable. Reload and review the agreement.');
    if(!preg_match('/\A[a-f0-9]{64}\z/',$a['expected']))throw new InvalidArgumentException('Keep the original reviewed financial version.');
    return ['job_id'=>design_financial_number($a['job_id']),'client_id'=>design_financial_number($a['client_id']),'request_id'=>$a['request_id'],'expected'=>$a['expected']];
}
function design_financial_offer_form(array $a):array {
    $binding=design_financial_binding($a);$items=[];$stages=[];
    foreach($a['items'] as $item)$items[]=['id'=>$item['id'],'description'=>$item['description'],'quantity'=>design_financial_number($item['quantity'],100),'unit_cents'=>design_financial_decimal($item['unit']),'discount_cents'=>design_financial_decimal($item['discount']),'tax_cents'=>design_financial_decimal($item['tax'])];
    foreach($a['stages'] as $stage)$stages[]=['id'=>$stage['id'],'kind'=>$stage['kind'],'title'=>$stage['title'],'calculation'=>$stage['calculation'],'value'=>design_financial_decimal($stage['value']),'due_date'=>$stage['due_date'],'milestone_id'=>$stage['milestone_id']];
    $schedule=DesignPaymentSchedule::fromArray(['format'=>1,'id'=>$a['request_id'],'client_id'=>$binding['client_id'],'project_id'=>$binding['job_id'],'proposal'=>['id'=>design_financial_number($a['proposal_id']),'version'=>design_financial_number($a['proposal_version']),'content_sha256'=>$a['proposal_sha256']],'currency'=>'USD','title'=>$a['title'],'terms'=>$a['terms'],'items'=>$items,'stages'=>$stages]);
    return $binding+['fields'=>$schedule->data(),'quote'=>$schedule->quote(),'stages'=>$schedule->stages()];
}
function design_financial_command_form(array $a):array {
    $binding=design_financial_binding($a);
    if(!preg_match('/\A[a-f0-9]{32}\z/',$a['schedule_id'])||!preg_match('/\A[a-f0-9]{64}\z/',$a['fingerprint']))throw new InvalidArgumentException('The exact agreement is unavailable. Reload and review its terms.');
    return $binding+['schedule_id'=>$a['schedule_id'],'fingerprint'=>$a['fingerprint'],'stage_id'=>$a['stage_id'],'choice'=>$a['choice'],'comment'=>$a['comment'],'confirm'=>$a['confirm']];
}
function design_financial_new_item():array{return ['id'=>bin2hex(random_bytes(16)),'description'=>'','quantity'=>'1','unit'=>'','discount'=>'0.00','tax'=>'0.00'];}
function design_financial_draft_edit(array $a):array {
    design_financial_binding($a);$command=$a['form_action'];
    if($command==='add_item'){if(count($a['items'])>=50)throw new InvalidArgumentException('Use at most 50 priced items.');$a['items'][]=design_financial_new_item();return $a;}
    if($command==='add_stage'){
        if(count($a['stages'])>=32)throw new InvalidArgumentException('Use at most 32 payment stages.');
        $first=count($a['stages'])===1;$last=count($a['stages'])-1;
        if($first&&$a['stages'][0]['kind']==='full'){$a['stages'][0]['kind']='final';if($a['stages'][0]['title']==='Full service')$a['stages'][0]['title']='Final balance';}
        array_splice($a['stages'],$last,0,[['id'=>bin2hex(random_bytes(16)),'kind'=>$first?'deposit':'milestone','title'=>$first?'Deposit':'Milestone installment','calculation'=>'percent','value'=>$first?'25.00':'10.00','due_date'=>'','milestone_id'=>'']]);return $a;
    }
    if(!preg_match('/\A(remove|up|down)_(items|stages):([a-f0-9]{32})\z/',$command,$m))throw new InvalidArgumentException('Choose a financial form action.');
    $ids=array_column($a[$m[2]],'id');if(count($ids)!==count(array_unique($ids)))throw new InvalidArgumentException('Row identities must be unique before editing.');$index=array_search($m[3],$ids,true);
    if($index===false)throw new InvalidArgumentException('That financial row is unavailable.');
    if($m[1]==='remove'){
        if(count($a[$m[2]])===1)throw new InvalidArgumentException('Keep at least one item and one full or final payment.');array_splice($a[$m[2]],$index,1);
        if($m[2]==='stages'&&count($a['stages'])===1&&$a['stages'][0]['kind']==='final'){$a['stages'][0]['kind']='full';if($a['stages'][0]['title']==='Final balance')$a['stages'][0]['title']='Full service';}
    }else{$target=$index+($m[1]==='up'?-1:1);if($target<0||$target>=count($a[$m[2]]))throw new InvalidArgumentException('This row is already at the edge.');[$a[$m[2]][$target],$a[$m[2]][$index]]=[$a[$m[2]][$index],$a[$m[2]][$target]];}
    return $a;
}
