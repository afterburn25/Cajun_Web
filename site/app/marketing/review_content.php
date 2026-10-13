<?php
/** Human-authored review content. Validation only; no URL fetching or delivery. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\ReviewRequests\WebsiteAddress;
use CajunWebPro\Foundation\ReviewRequests\ReviewFinding;
use CajunWebPro\Foundation\ReviewRequests\ReviewReport;

foreach (['WebsiteAddress','ReviewContact','ReviewFinding','ReviewStage','ReviewRequest','ReviewReport','ReviewInbox'] as $class) {
    require_once CWP_CODE_ROOT.'/includes/foundation/review-requests/'.$class.'.php';
}
function reviews_text(mixed $value,string $label,int $max,bool $required=true,bool $multiline=false): string {
    if (!is_string($value) || strlen($value)>$max || !preg_match('//u',$value)
        || preg_match($multiline?'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/':'/[\x00-\x1F\x7F]/',$value)) {
        throw new InvalidArgumentException('Check '.$label.' and its length limit.');
    }
    $value=trim($value);
    if ($required && $value==='') throw new InvalidArgumentException('Complete '.$label.'.');
    return $value;
}
function reviews_address(mixed $value): WebsiteAddress {
    $raw=reviews_text($value,'the website address',500);
    $address=new WebsiteAddress($raw);safe_url($address->url());return $address;
}
function reviews_time(mixed $value,string $label,int $minimum=0): int {
    $value=reviews_text($value,$label,19);$format=strlen($value)===16?'Y-m-d\TH:i':'Y-m-d\TH:i:s';
    $date=DateTimeImmutable::createFromFormat('!'.$format,$value,new DateTimeZone('UTC'));
    if (!$date || $date->format($format)!==$value || $date->getTimestamp()<$minimum || $date->getTimestamp()>time()) {
        throw new InvalidArgumentException('Enter a valid '.$label.' in UTC, not in the future.'.($minimum?' Delivery cannot predate the saved report.':''));
    }
    return $date->getTimestamp();
}
function reviews_findings(mixed $input): array {
    if (!is_array($input) || !array_is_list($input) || count($input)<1 || count($input)>20) throw new InvalidArgumentException('Add from 1 to 20 findings.');
    $out=[];
    foreach ($input as $item) {
        if (!is_array($item)) throw new InvalidArgumentException('Check the finding fields.');
        $title=reviews_text($item['title']??null,'each finding title',160);
        $detail=reviews_text($item['detail']??null,'each finding detail',5000,true,true);
        $evidence=reviews_text($item['evidence']??null,'the evidence type',30);
        $source=reviews_text($item['source']??'','the evidence source',500,false);
        $observed=reviews_text($item['observed_at']??'','the observation time',19,false);
        $at=$observed===''?null:reviews_time($observed,'observation time');
        $finding=new ReviewFinding($title,$detail,$evidence,$at,$source===''?null:$source);
        $out[]=['title'=>$finding->title,'detail'=>$finding->detail,'evidence'=>$finding->evidence,'observed_at'=>$finding->observedAt,'source'=>$finding->source];
    }
    return $out;
}
function reviews_report_model(int $leadId,array $row): ReviewReport {
    $items=json_decode($row['findings'],true,32,JSON_THROW_ON_ERROR);$findings=[];
    foreach ($items as $item) $findings[]=new ReviewFinding($item['title'],$item['detail'],$item['evidence'],$item['observed_at'],$item['source']);
    return new ReviewReport((string)$leadId,$row['prepared_name'],(int)$row['prepared_at'],$findings,$row['delivered_at']===null?null:(int)$row['delivered_at']);
}
