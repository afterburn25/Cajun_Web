<?php
/** Strict form parsing only; controllers and storage retain CSRF/current-authority checks. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/documents.php';
require_once CWP_CODE_ROOT.'/app/marketing/growth.php';

function service_plans_form_text(array $input,string $field,int $limit,bool $trim=false):string {
    $value=$input[$field]??'';
    if(!is_string($value) || strlen($value)>$limit || str_contains($value,"\0") || !preg_match('//u',$value)) {
        throw new InvalidArgumentException('Check the length and format of '.str_replace('_',' ',$field).'.');
    }
    return $trim?trim($value):$value;
}
function service_plans_form_number(array $input,string $field,int $maximum):int {
    $value=service_plans_form_text($input,$field,10);
    if(!preg_match('/\A(?:0|[1-9][0-9]*)\z/',$value) || strlen($value)>strlen((string)$maximum) || (strlen($value)===strlen((string)$maximum) && strcmp($value,(string)$maximum)>0)) {
        throw new InvalidArgumentException('Reload and review the current '.str_replace('_',' ',$field).'.');
    }
    return (int)$value;
}
function service_plans_form_id(array $input,string $field):string {
    $value=service_plans_form_text($input,$field,32);service_plans_id($value);return $value;
}
function service_plans_form_hash(array $input,string $field):string {
    $value=service_plans_form_text($input,$field,64);
    if(!preg_match('/\A[a-f0-9]{64}\z/',$value))throw new InvalidArgumentException('Reload and review the exact terms before continuing.');
    return $value;
}
function service_plans_form_confirm(array $input):void {
    if(($input['confirm']??null)!=='1')throw new InvalidArgumentException('Review the details and explicitly confirm this action.');
}
function service_plans_form_lines(array $input,string $field):array {
    $text=service_plans_form_text($input,$field,33000,true);
    if($text==='')return [];
    $lines=preg_split('/\r\n|\n|\r/',$text);
    // Reject accidental empty lines inside the list instead of silently changing its terms.
    if(count($lines)>32)throw new InvalidArgumentException('Use no more than 32 lines for '.str_replace('_',' ',$field).'.');
    return array_map('trim',$lines);
}
function service_plans_form_terms(array $input):array {
    $price=gcents(service_plans_form_text($input,'price',12,true));
    if($price===null)throw new InvalidArgumentException('Enter a price, including 0.00 for a free service.');
    if(array_key_exists('currency',$input) && $input['currency']!=='USD')throw new InvalidArgumentException('Service plans currently use USD.');
    $limits=[];
    foreach(service_plans_form_lines($input,'limits') as $line) {
        if(!preg_match('/\A([a-z][a-z0-9_]{0,63})\s*=\s*(0|[1-9][0-9]{0,9})\z/',$line,$match))throw new InvalidArgumentException('Write each usage limit as name = whole number, such as updates = 4.');
        $cap=service_plans_form_number(['cap'=>$match[2]],'cap',2147483647);
        if(array_key_exists($match[1],$limits))throw new InvalidArgumentException('Give each usage limit a unique name.');
        $limits[$match[1]]=$cap;
    }
    return service_plans_terms([
        'name'=>service_plans_form_text($input,'name',120,true),
        'cadence'=>service_plans_form_text($input,'cadence',12),
        'price'=>$price,'currency'=>'USD',
        'inclusions'=>service_plans_form_lines($input,'inclusions'),
        'exclusions'=>service_plans_form_lines($input,'exclusions'),
        'limits'=>$limits,'support'=>service_plans_form_text($input,'support',1000,true),
    ]);
}
/** Do not replace a failed request's old identity/revision with the latest saved values. */
function service_plans_form_intent(array $input):array {
    return [
        'record_id'=>service_plans_form_id($input,'record_id'),
        'revision'=>service_plans_form_number($input,'revision',255),
        'event_id'=>service_plans_form_id($input,'event_id'),
    ];
}
/** Re-display only allowlisted nonsecret fields. Values must still be HTML escaped by the view. */
function service_plans_form_values(array $input):array {
    $values=[];
    foreach(['name'=>120,'cadence'=>12,'price'=>12,'inclusions'=>33000,'exclusions'=>33000,'limits'=>33000,'support'=>1000,'reason'=>1000,'record_id'=>32,'event_id'=>32,'revision'=>10,'client_id'=>10,'plan_hash'=>64,'terms_hash'=>64] as $key=>$limit) {
        $value=$input[$key]??'';
        $values[$key]=is_string($value)&&strlen($value)<=$limit&&preg_match('//u',$value)&&!str_contains($value,"\0")?$value:'';
    }
    return $values;
}
