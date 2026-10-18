<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/includes/foundation/twentyi-inventory/InventoryDomain.php';
function domain_renewals_id(string $id):void {if(!preg_match('/\A[a-f0-9]{32}\z/',$id))throw new InvalidArgumentException('Reload the renewal request.');}
function domain_renewals_json(array $data):string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
/** Local intake limits are not registry eligibility or a provider quotation. */
function domain_renewals_document(array $d):array {
    if(array_keys($d)!==['version','id','client_id','link_id','link_revision','profile_id','profile_revision','key_fingerprint','domain_id','domain','refresh_id','requested_years','renew_privacy','created_epoch']||$d['version']!==1)throw new RuntimeException('Renewal request history is inconsistent.');
    foreach(['id','link_id','profile_id','refresh_id'] as $key){if(!is_string($d[$key]))throw new RuntimeException('Invalid request identity.');domain_renewals_id($d[$key]);}
    foreach(['client_id','link_revision','profile_revision'] as $key)if(!is_int($d[$key])||$d[$key]<1||$d[$key]>4294967295)throw new RuntimeException('Invalid request context.');
    if(!is_string($d['key_fingerprint'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['key_fingerprint'])||!is_int($d['created_epoch'])||$d['created_epoch']<1)throw new RuntimeException('Invalid request context.');
    $domain=\CajunWebPro\Foundation\TwentyIInventory\InventoryDomain::fromItem((object)['id'=>$d['domain_id'],'name'=>$d['domain']]);
    if(!is_int($d['domain_id'])||$domain->name!==$d['domain']||!is_int($d['requested_years'])||$d['requested_years']<1||$d['requested_years']>10||(!is_bool($d['renew_privacy'])&&$d['renew_privacy']!==null))throw new InvalidArgumentException('Request between 1 and 10 years and choose a privacy preference. The studio will verify eligible terms.');
    return $d;
}
function domain_renewals_note(string $note):string {
    $note=trim($note);if(strlen($note)>1000||preg_match('//u',$note)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new InvalidArgumentException('Use a review message of at most 1,000 bytes.');return $note;
}
/** Events cannot approve, purchase, fulfill or mark a domain renewed. */
function domain_renewals_event_document(array $d):array {
    if(array_keys($d)!==['id','request_id','revision','command','actor_kind','actor_id','recorded_epoch','note','previous_sha256'])throw new RuntimeException('Renewal review history is inconsistent.');
    foreach(['id','request_id'] as $key){if(!is_string($d[$key]))throw new RuntimeException('Invalid review identity.');domain_renewals_id($d[$key]);}
    foreach(['revision','actor_id','recorded_epoch'] as $key)if(!is_int($d[$key])||$d[$key]<1)throw new RuntimeException('Invalid review context.');
    if($d['revision']>4294967295||!in_array($d['command'],['reviewed','declined','withdrawn'],true)||$d['actor_kind']!==($d['command']==='withdrawn'?'client':'staff')||!is_string($d['note'])||domain_renewals_note($d['note'])!==$d['note']||!is_string($d['previous_sha256'])||!preg_match('/\A[a-f0-9]{64}\z/',$d['previous_sha256']))throw new RuntimeException('Invalid review event.');
    if(($d['command']==='reviewed'||$d['command']==='declined')&&$d['note']==='')throw new InvalidArgumentException('Add a message for the client.');
    if($d['command']==='withdrawn'&&$d['note']!=='')throw new RuntimeException('Invalid withdrawal event.');return $d;
}
