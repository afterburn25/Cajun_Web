<?php
/** Staff website reviews. Human-authored reports; no remote fetch or sending. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }

use CajunWebPro\Foundation\ReviewRequests\ReviewContact;
use CajunWebPro\Foundation\ReviewRequests\ReviewRequest;
use CajunWebPro\Foundation\ReviewRequests\ReviewInbox;
use CajunWebPro\Foundation\ReviewRequests\WebsiteAddress;

require_once CWP_CODE_ROOT.'/app/clients/workspace.php';
require_once CWP_CODE_ROOT.'/app/staff/accounts.php';
require_once CWP_CODE_ROOT.'/app/marketing/review_content.php';
require_once CWP_CODE_ROOT.'/database/schema/reviews_schema.php';
require_once CWP_CODE_ROOT.'/database/migrations/reviews_migrate.php';

function reviews_ready(): bool {return (one('SELECT value FROM '.table('settings').' WHERE name=?',['website_reviews_schema_version'])['value']??'')==='1';}
function reviews_actor(bool $ready=true): array {
    if (support_current() || array_key_exists('support',$_GET)) throw new RuntimeException('Leave client support before using website reviews.');
    $actor=admin_user();
    if (!$actor || !staff_can('website.manage',$actor)) throw new RuntimeException('Current website and inquiry permission is required.');
    if ($ready && !reviews_ready()) throw new RuntimeException('An owner must enable website reviews first.');
    return $actor;
}
function reviews_audit(int $actor,string $action,string $detail): void {
    query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor,'reviews.'.$action,$detail,gmdate('Y-m-d H:i:s')]);
}
/** Internal load; public staff entry points authorize before reading it. */
function reviews_lead(int $id,bool $lock=false): array {
    $lead=$id>0?one('SELECT * FROM '.table('leads').' WHERE id=?'.($lock?' FOR UPDATE':''),[$id]):null;
    if (!$lead || $lead['service']!=='Website review') throw new InvalidArgumentException('Website review inquiry unavailable.');return $lead;
}
function reviews_row(int $leadId,bool $lock=false): ?array {return one('SELECT * FROM '.table('website_reviews').' WHERE lead_id=?'.($lock?' FOR UPDATE':''),[$leadId]);}
function reviews_report(int $reviewId): ?array {return one('SELECT * FROM '.table('website_review_reports').' WHERE review_id=?',[$reviewId]);}
function reviews_snapshot(int $reviewId,int $actor,string $action): void {
    $row=one('SELECT * FROM '.table('website_reviews').' WHERE id=?',[$reviewId]);$report=reviews_report($reviewId);
    query('INSERT INTO '.table('website_review_revisions').' (review_id,revision,snapshot,action,actor_id,recorded_at) VALUES (?,?,?,?,?,?)',[$reviewId,$row['revision'],json_encode(['review'=>$row,'report'=>$report],JSON_THROW_ON_ERROR),$action,$actor,gmdate('Y-m-d H:i:s')]);
    reviews_audit($actor,$action,'Review '.$reviewId.' revision '.$row['revision']);
}
/** Only called for a just-inserted lead within the intake transaction, or authorized adoption. */
function reviews_capture(int $leadId,string $url,int $actor=0): int {
    if (!db()->inTransaction() || !reviews_ready()) throw new LogicException('Review capture requires prepared storage and an active transaction.');
    $lead=reviews_lead($leadId,true);$address=new WebsiteAddress($url);safe_url($address->url());$now=gmdate('Y-m-d H:i:s');
    reviews_request($lead,['website'=>$address->url(),'stage'=>'received']);
    query('INSERT INTO '.table('website_reviews')." (lead_id,website,stage,revision,updated_by,updated_at,created_at) VALUES (?,?,'received',1,?,?,?)",[$leadId,$address->url(),$actor,$now,$now]);
    $id=(int)db()->lastInsertId();reviews_snapshot($id,$actor,$actor?'adopt':'received');return $id;
}
function reviews_adopt(int $leadId,mixed $url): void {
    staff_locked(fn()=>staff_transaction(function() use ($leadId,$url): void {
        $actor=reviews_actor();reviews_lead($leadId,true);
        if (reviews_row($leadId,true)) throw new RuntimeException('This inquiry already has a review. Reload its current version.');
        reviews_capture($leadId,reviews_address($url)->url(),(int)$actor['id']);
    }));
}
function reviews_get(int $leadId,int $page=1): array {
    reviews_actor();$lead=reviews_lead($leadId);$row=reviews_row($leadId);$page=max(1,min($page,100000));
    return ['lead'=>$lead,'row'=>$row,'report'=>$row?reviews_report((int)$row['id']):null,'history'=>$row?all('SELECT r.*,u.name AS actor_name FROM '.table('website_review_revisions').' r LEFT JOIN '.table('users').' u ON u.id=r.actor_id WHERE r.review_id=? ORDER BY r.revision DESC LIMIT 21 OFFSET '.(($page-1)*20),[$row['id']]):[]];
}
function reviews_request(array $lead,array $row): ReviewRequest {
    return new ReviewRequest((string)$lead['id'],new WebsiteAddress($row['website']),new ReviewContact($lead['name'],$lead['email'],$lead['phone']===''?null:$lead['phone'],(int)$lead['consent']===1),$lead['message'],$row['stage'],(new DateTimeImmutable($lead['created_at'],new DateTimeZone('UTC')))->getTimestamp());
}
function reviews_locked(int $leadId,int $revision,callable $work): void {
    staff_locked(fn()=>staff_transaction(function() use ($leadId,$revision,$work): void {
        $actor=reviews_actor();$lead=reviews_lead($leadId,true);$row=reviews_row($leadId,true);
        if (!$row) throw new InvalidArgumentException('Start this review first.');
        if ($revision<1 || (int)$row['revision']!==$revision) throw new RuntimeException('This review changed. Reload before saving; your submitted values remain below.');
        $work($actor,$lead,$row);
    }));
}
function reviews_bump(array $row,int $actor,string $stage,string $action): void {
    query('UPDATE '.table('website_reviews').' SET revision=?,stage=?,updated_by=?,updated_at=? WHERE id=?',[(int)$row['revision']+1,$stage,$actor,gmdate('Y-m-d H:i:s'),$row['id']]);
    reviews_snapshot((int)$row['id'],$actor,$action);
}
function reviews_stage(int $leadId,int $revision,string $stage): void {
    reviews_locked($leadId,$revision,function(array $actor,array $lead,array $row) use ($stage): void {
        if ($stage==='delivered') throw new InvalidArgumentException('Use Record external delivery with the saved report.');
        if ($stage==='report-drafted' && !reviews_report((int)$row['id'])) throw new InvalidArgumentException('Save a report with findings first.');
        $next=(new ReviewInbox())->transition(reviews_request($lead,$row),$stage);
        reviews_bump($row,(int)$actor['id'],$next->status,'stage');
    });
}
function reviews_save_report(int $leadId,int $revision,mixed $summary,mixed $findings): void {
    reviews_locked($leadId,$revision,function(array $actor,array $lead,array $row) use ($summary,$findings): void {
        if (!in_array($row['stage'],['in-review','report-drafted'],true)) throw new InvalidArgumentException('Reports can be edited only while in review or drafted.');
        $summary=reviews_text($summary,'the report summary',8000,true,true);$findings=reviews_findings($findings);
        $encoded=json_encode($findings,JSON_THROW_ON_ERROR);$prepared=time();$name=reviews_text($actor['name'],'your reviewer name',120);
        $values=[$summary,$encoded,(int)$actor['id'],$name,$prepared,(int)$row['id']];
        if (reviews_report((int)$row['id'])) query('UPDATE '.table('website_review_reports').' SET summary=?,findings=?,prepared_by=?,prepared_name=?,prepared_at=? WHERE review_id=?',$values);
        else query('INSERT INTO '.table('website_review_reports')." (summary,findings,prepared_by,prepared_name,prepared_at,review_id,delivery_channel,delivery_reference) VALUES (?,?,?,?,?,?,'','')",$values);
        // A changed drafted report needs a new explicit draft confirmation.
        $stage=$row['stage']==='report-drafted'?(new ReviewInbox())->transition(reviews_request($lead,$row),'in-review')->status:'in-review';
        reviews_report_model((int)$lead['id'],reviews_report((int)$row['id']));
        reviews_bump($row,(int)$actor['id'],$stage,'report');
    });
}
function reviews_deliver(int $leadId,int $revision,mixed $when,mixed $channel,mixed $reference,#[\SensitiveParameter] string $password): void {
    reviews_locked($leadId,$revision,function(array $actor,array $lead,array $row) use ($when,$channel,$reference,$password): void {
        if (!password_verify($password,$actor['password_hash'])) throw new InvalidArgumentException('Your current password is not correct.');
        $report=reviews_report((int)$row['id']);
        if (!$report || $row['stage']!=='report-drafted') throw new InvalidArgumentException('Confirm the saved report as drafted before recording delivery.');
        $channel=reviews_text($channel,'the delivery channel',30);
        if (!in_array($channel,['email','client-portal','in-person','other'],true)) throw new InvalidArgumentException('Choose an external delivery channel.');
        $reference=reviews_text($reference,'the delivery reference or note',500);
        // Preserve the entered timestamp; never round it forward to imply delivery.
        $at=reviews_time($when,'delivery time',(int)$report['prepared_at']);
        $confirmed=reviews_report_model((int)$lead['id'],$report)->withDelivery($at);
        $next=(new ReviewInbox())->transition(reviews_request($lead,$row),'delivered',$confirmed);
        query('UPDATE '.table('website_review_reports').' SET delivered_at=?,delivery_channel=?,delivery_reference=? WHERE review_id=?',[$at,$channel,$reference,$row['id']]);
        reviews_bump($row,(int)$actor['id'],$next->status,'delivery');
    });
}
/** Existing explicit inquiry deletion also removes dependent review data by verified FKs. */
function reviews_delete_lead(int $leadId): void {
    staff_locked(fn()=>staff_transaction(function() use ($leadId): void {
        $actor=reviews_actor(false);$lead=one('SELECT id FROM '.table('leads').' WHERE id=? FOR UPDATE',[$leadId]);
        if (!$lead) throw new InvalidArgumentException('Inquiry unavailable.');
        query('DELETE FROM '.table('leads').' WHERE id=?',[$leadId]);reviews_audit((int)$actor['id'],'inquiry_delete','Inquiry '.$leadId.' and its dependent review records deleted');
    }));
}
