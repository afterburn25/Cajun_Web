<?php
declare(strict_types=1);
define('CWP_BOOT',true);require __DIR__.'/app/core.php';boot();require __DIR__.'/app/mail.php';
function inquiry_response(bool $ok,string $message,int $status=200): never {
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')) {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>$ok,'message'=>$message],JSON_INVALID_UTF8_SUBSTITUTE);exit;}
    flash($message,$ok?'success':'error');redirect(page_url($ok?'thanks':'contact'));
}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');inquiry_response(false,'Use the project inquiry form to send a message.',405);}
try {
    check_csrf();if(posted('action',30)!=='inquiry')throw new InvalidArgumentException('Unknown form action.');
    $token=posted('submission_token',48);if(!preg_match('/^[a-f0-9]{48}$/D',$token)||!isset($_SESSION['inquiry_tokens'][$token]))throw new InvalidArgumentException('This form expired. Refresh the contact page before trying again.');
    $issued=(int)$_SESSION['inquiry_tokens'][$token];if(time()-$issued>7200||time()-$issued<2||posted('website_address',500)!=='')throw new InvalidArgumentException('The form could not be accepted. Wait a moment and try again.');
    $requestKey=hash('sha256',$token);$existing=one('SELECT reference FROM '.table('leads').' WHERE request_key=?',[$requestKey]);if($existing)inquiry_response(true,'Your inquiry has already been received. Reference: '.$existing['reference'].'. Thank you for reaching out.');
    $data=['name'=>posted('name',120),'email'=>posted('email',190),'company'=>posted('company',160),'phone'=>posted('phone',80),'service'=>posted('service',100),'budget'=>posted('budget',100),'timeline'=>posted('timeline',100),'message'=>posted('message',6000)];
    if($data['name']===''||!filter_var($data['email'],FILTER_VALIDATE_EMAIL)||strlen($data['message'])<20||$data['service']===''||$data['budget']===''||posted('consent',1)!=='1')throw new InvalidArgumentException('Add your name, valid email, service, budget and at least 20 characters about your project, then agree to the privacy notice.');
    if(!consume_rate(ip_key('inquiry-hour'),5,3600)||!consume_rate(ip_key('inquiry-day'),12,86400))inquiry_response(false,'This connection has submitted several inquiries. Please wait before sending another.',429);
    $reference='CWP-'.strtoupper(bin2hex(random_bytes(5)));$data['reference']=$reference;
    try {query('INSERT INTO '.table('leads').' (reference,request_key,name,email,company,phone,service,budget,timeline,message,consent,ip_hash,status,notes,notification_status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,\'new\',\'\',\'pending\',?)',[$reference,$requestKey,$data['name'],$data['email'],$data['company'],$data['phone'],$data['service'],$data['budget'],$data['timeline'],$data['message'],ip_key('lead'),gmdate('Y-m-d H:i:s')]);$id=(int)db()->lastInsertId();}
    catch(PDOException $e){$previous=one('SELECT reference FROM '.table('leads').' WHERE request_key=?',[$requestKey]);if($previous)inquiry_response(true,'Your inquiry has been received. Reference: '.$previous['reference'].'.');throw $e;}
    /* The inquiry is durable before mail is attempted. Mail failure must never lose a lead. */
    try{$status=notify_lead($data);}catch(Throwable $e){$status='failed';report_error($e);}
    try{query('UPDATE '.table('leads').' SET notification_status=? WHERE id=?',[$status,$id]);}catch(Throwable $e){report_error($e);}
    inquiry_response(true,'Your inquiry has been received. Reference: '.$reference.'. Thank you—we look forward to hearing more about your project.');
}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException){$ref=report_error($e);inquiry_response(false,'We could not save your inquiry. Please try again. Reference: '.$ref,503);}inquiry_response(false,$e->getMessage(),422);}
catch(Throwable $e){$ref=report_error($e);inquiry_response(false,'We could not complete this request. Please try again. Reference: '.$ref,500);}
