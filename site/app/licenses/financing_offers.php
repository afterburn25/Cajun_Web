<?php
/** Customer-specific finance offers, portal signature, payment handoff and email-draft preparation. */
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/licenses/financing.php';
require_once CWP_CODE_ROOT.'/database/migrations/license_finance_offers_migrate.php';
require_once CWP_CODE_ROOT.'/app/billing/payments.php';
require_once CWP_CODE_ROOT.'/app/mail/drafts.php';
require_once CWP_CODE_ROOT.'/includes/foundation/mail-compose/DraftContent.php';

use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms;
use CajunWebPro\Foundation\MailCompose\DraftContent;

function license_finance_apr_bps(string $value):int{
    $value=trim($value);
    if(!preg_match('/^(?:0|[1-9][0-9]{0,2})(?:\.[0-9]{1,2})?$/D',$value))throw new InvalidArgumentException('Enter APR as a percentage with up to two decimals.');
    [$whole,$fraction]=array_pad(explode('.',$value,2),2,'');
    $bps=((int)$whole*100)+(int)str_pad($fraction,2,'0');
    if($bps<0||$bps>10000)throw new InvalidArgumentException('APR must be between 0% and 100%.');
    return $bps;
}
function license_finance_apr_label(int $bps):string{return number_format($bps/100,2,'.','').'%';}

function license_finance_offer_id(string $id):string{
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Finance agreement unavailable.');
    return $id;
}
function license_finance_offer_row(string $id,?int $clientId=null,bool $lock=false):array{
    license_finance_offer_id($id);
    $sql='SELECT o.*,c.name AS client_name,c.email AS client_email,j.title AS project_title,l.product_id,l.license_key FROM '.table('license_finance_offers').' o JOIN '.table('studio_clients').' c ON c.id=o.client_id JOIN '.table('studio_jobs').' j ON j.id=o.job_id JOIN '.table('website_licenses').' l ON l.id=o.license_id WHERE o.id=?';
    $args=[$id];
    if($clientId!==null){$sql.=' AND o.client_id=?';$args[]=$clientId;}
    if($lock)$sql.=' FOR UPDATE';
    $row=one($sql,$args);
    if(!$row)throw new InvalidArgumentException('Finance agreement unavailable.');
    return $row;
}
function license_finance_offer_terms(array $row):array{
    $json=(string)$row['terms_json'];
    if(strlen($json)>32768||!preg_match('/^[a-f0-9]{64}$/D',(string)$row['terms_sha256'])||!hash_equals((string)$row['terms_sha256'],hash('sha256',$json))){
        throw new RuntimeException('Finance agreement terms are inconsistent.');
    }
    $terms=json_decode($json,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($terms)||($terms['format']??null)!==1||json_encode($terms,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)!==$json){
        throw new RuntimeException('Finance agreement terms are not canonical.');
    }
    return $terms;
}
function license_finance_offer_status(array $row):string{
    if($row['status']==='offered'&&(int)$row['expires_at']<=time())return 'expired';
    return (string)$row['status'];
}
function license_finance_offer_portal_url(string $id):string{return studio_url(['finance'=>license_finance_offer_id($id)]);}

function license_finance_offer_build_terms(array $license,array $schedule,RecurringBillingTerms $billing,string $title,
    int $cash,int $initial,int $financed,int $financeCharge,int $aprBps,int $installments,int $graceDays,
    int $paymentLinkDays,array $rights,string $agreementText):array{
    $title=trim($title);$agreementText=trim(str_replace(["\r\n","\r"],"\n",$agreementText));
    if(strlen($title)<3||strlen($title)>190||strlen($agreementText)<20||strlen($agreementText)>12000||preg_match('//u',$agreementText)!==1){
        throw new InvalidArgumentException('Enter a finance title and agreement terms.');
    }
    if($graceDays<0||$graceDays>90||$paymentLinkDays<1||$paymentLinkDays>30||$aprBps<0||$aprBps>10000){
        throw new InvalidArgumentException('Finance terms are outside supported limits.');
    }
    $b=$billing->data();$installment=(int)$b['amount_cents'];
    $math=license_financing_terms_math($cash,$initial,$financed,$financeCharge,$installment,$installments);
    $normalizedRights=license_financing_rights(
        (bool)($rights['resale']??false),(bool)($rights['distribution']??false),
        (bool)($rights['exclusive_resale']??false),(bool)($rights['exclusive_distribution']??false)
    );
    $terms=[
        'format'=>1,'title'=>$title,'license_id'=>(int)$license['id'],'client_id'=>(int)$license['customer_id'],
        'job_id'=>(int)$schedule['job_id'],'schedule_id'=>(string)$schedule['id'],'schedule_revision'=>(int)$schedule['revision'],
        'schedule_sha256'=>(string)$schedule['journal_sha256'],'schedule_terms_sha256'=>$billing->fingerprint(),
        'cash_price_cents'=>$cash,'initial_payment_cents'=>$initial,'amount_financed_cents'=>$financed,
        'finance_charge_cents'=>$financeCharge,'installment_total_cents'=>$math['installment_total_cents'],
        'total_customer_cost_cents'=>$math['total_customer_cost_cents'],'apr_basis_points'=>$aprBps,
        'installments'=>$installments,'installment_amount_cents'=>$installment,'cadence'=>$b['cadence'],
        'anchor'=>$b['anchor'],'due_after_days'=>(int)$b['due_after_days'],'timezone'=>$b['timezone'],
        'grace_days'=>$graceDays,'payment_link_days'=>$paymentLinkDays,
        'rights'=>$normalizedRights,'agreement_text'=>$agreementText
    ];
    return $terms;
}

function license_finance_offer_create(int $licenseId,string $scheduleId,string $title,int $cash,int $initial,int $financed,
    int $financeCharge,int $aprBps,int $installments,int $graceDays,int $offerDays,int $paymentLinkDays,array $rights,
    string $agreementText,#[\SensitiveParameter]string $password):array{
    if(!license_finance_offers_ready())throw new RuntimeException('Prepare finance agreements first.');
    if($offerDays<1||$offerDays>30)throw new InvalidArgumentException('Agreement review window must be 1–30 days.');
    if(db()->inTransaction())throw new LogicException('Finance offer creation owns its transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($licenseId,$scheduleId,$title,$cash,$initial,$financed,$financeCharge,$aprBps,$installments,$graceDays,$offerDays,$paymentLinkDays,$rights,$agreementText,$password):array{
        if(support_current())throw new RuntimeException('Leave support mode before offering financing.');
        $actor=staff_owner($password);recurring_billing_require();
        $license=one('SELECT l.*,c.active AS customer_active FROM '.table('website_licenses').' l JOIN '.table('studio_clients').' c ON c.id=l.customer_id WHERE l.id=? FOR UPDATE',[$licenseId]);
        if(!$license||(int)$license['customer_active']!==1||(int)$license['is_master']===1)throw new InvalidArgumentException('Choose an active customer website license.');
        $softwareTable=(bool)one('SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
            [config()['db']['prefix'].'software_entitlements']);
        if($softwareTable&&one('SELECT license_id FROM '.table('software_entitlements').' WHERE license_id=?',[$licenseId])){
            throw new InvalidArgumentException('Use software subscription billing for software entitlements; financed-site agreements are for website licenses.');
        }
        if(one('SELECT license_id FROM '.table('license_financing').' WHERE license_id=?',[$licenseId]))throw new RuntimeException('This website license already has active financing.');
        $schedule=recurring_billing_row($scheduleId);$doc=recurring_billing_validate($schedule);
        if((int)$schedule['client_id']!==(int)$license['customer_id']||$doc->phase()!=='offered')throw new RuntimeException('Choose this customer’s offered recurring installment schedule.');
        $billing=RecurringBillingTerms::fromArray($doc->data()['terms']);
        $b=$billing->data();
        if((string)$b['currency']!=='USD'||$b['collection']!=='invoice-only')throw new RuntimeException('Financing requires the retained USD invoice-only schedule.');
        if($b['anchor']<=gmdate('Y-m-d'))throw new RuntimeException('The financing installment schedule must begin on a future date so recurring enrollment can be completed before the first charge.');
        if(one('SELECT id FROM '.table('license_finance_offers').' WHERE schedule_id=?',[$scheduleId]))throw new RuntimeException('This installment schedule already belongs to a finance agreement.');
        $terms=license_finance_offer_build_terms($license,$schedule,$billing,$title,$cash,$initial,$financed,$financeCharge,$aprBps,$installments,$graceDays,$paymentLinkDays,$rights,$agreementText);
        $json=json_encode($terms,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
        $id=bin2hex(random_bytes(16));$now=time();$startEpoch=strtotime($b['anchor'].' 00:00:00 UTC');
        if($startEpoch===false)throw new RuntimeException('Financing schedule start date is invalid.');
        $expires=min($now+$offerDays*86400,$startEpoch-60);
        if($expires<=$now)throw new RuntimeException('The agreement review window ends before the financing schedule can begin.');
        query('INSERT INTO '.table('license_finance_offers').' (id,license_id,client_id,job_id,schedule_id,schedule_revision,schedule_sha256,status,title,cash_price_cents,initial_payment_cents,amount_financed_cents,finance_charge_cents,apr_basis_points,installments,grace_days,payment_link_days,terms_json,terms_sha256,created_by,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id,$licenseId,$license['customer_id'],$schedule['job_id'],$scheduleId,$schedule['revision'],$schedule['journal_sha256'],'offered',$title,$cash,$initial,$financed,$financeCharge,$aprBps,$installments,$graceDays,$paymentLinkDays,$json,$hash,$actor['id'],$expires,gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')]);
        license_audit((int)$actor['id'],'financing.offer','Finance agreement '.$id.' offered for license '.$licenseId.' schedule '.$scheduleId.' terms '.$hash);
        return license_finance_offer_row($id,null,false);
    }));
}

function license_finance_offer_withdraw(string $id,#[\SensitiveParameter]string $password):void{
    staff_locked(fn()=>staff_transaction(function()use($id,$password):void{
        $actor=staff_owner($password);$row=license_finance_offer_row($id,null,true);
        if(license_finance_offer_status($row)!=='offered')throw new RuntimeException('Only an unsigned current finance offer can be withdrawn.');
        $schedule=recurring_billing_row((string)$row['schedule_id']);$doc=recurring_billing_validate($schedule);
        if($doc->phase()==='offered'){
            recurring_billing_append($schedule,(int)$schedule['revision'],bin2hex(random_bytes(16)),'withdraw','staff:'.(int)$actor['id'],
                ['reason'=>'Finance agreement '.$id.' withdrawn before client signature']);
        }elseif($doc->phase()!=='withdrawn'){
            throw new RuntimeException('The financing schedule changed. Review it before withdrawing this offer.');
        }
        query('UPDATE '.table('license_finance_offers').' SET status=?,updated_at=? WHERE id=?',['withdrawn',gmdate('Y-m-d H:i:s'),$id]);
        license_audit((int)$actor['id'],'financing.offer.withdraw','Finance agreement '.$id.' and its offered installment schedule were withdrawn');
    }));
}

function license_finance_offer_sign(string $id,int $clientId,string $signer,bool $agree,bool $electronic):array{
    $signer=trim($signer);
    if(!$agree||!$electronic||strlen($signer)<2||strlen($signer)>190||preg_match('//u',$signer)!==1){
        throw new InvalidArgumentException('Type your name and confirm both financing and electronic-signature statements.');
    }
    if(support_current())throw new RuntimeException('Finance agreements must be signed from the client’s own account.');
    $signed=staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$signer):array{
        service_plans_current_client($clientId);
        $row=license_finance_offer_row($id,$clientId,true);$status=license_finance_offer_status($row);
        if($status==='signed')return $row;
        if($status!=='offered')throw new RuntimeException('This finance agreement is no longer available for signature.');
        $terms=license_finance_offer_terms($row);
        if((int)$terms['client_id']!==$clientId||(int)$terms['license_id']!==(int)$row['license_id'])throw new RuntimeException('Finance agreement ownership is inconsistent.');
        $creator=one('SELECT u.id FROM '.table('users').' u JOIN '.table('staff_profiles').' p ON p.user_id=u.id WHERE u.id=? AND p.role=? AND p.enabled=1 AND u.password_hash<>\'\' FOR UPDATE',
            [$row['created_by'],'owner']);
        if(!$creator)throw new RuntimeException('The finance agreement owner is no longer authorized. Ask the studio to reissue it.');
        $schedule=recurring_billing_row((string)$row['schedule_id']);$doc=recurring_billing_validate($schedule);
        if($doc->phase()!=='offered'||(int)$schedule['revision']!==(int)$row['schedule_revision']
            ||!hash_equals((string)$row['schedule_sha256'],$doc->fingerprint())){
            throw new RuntimeException('The installment schedule changed. Ask the studio for a new finance agreement.');
        }
        $billing=RecurringBillingTerms::fromArray($doc->data()['terms']);
        if(!hash_equals((string)$terms['schedule_terms_sha256'],$billing->fingerprint()))throw new RuntimeException('The installment terms changed.');
        recurring_billing_append($schedule,(int)$schedule['revision'],bin2hex(random_bytes(16)),'accept','client:'.$clientId,
            ['terms_sha256'=>$billing->fingerprint()]);
        $schedule=recurring_billing_row((string)$row['schedule_id']);$accepted=recurring_billing_validate($schedule);
        recurring_billing_append($schedule,(int)$schedule['revision'],bin2hex(random_bytes(16)),'activate','staff:'.(int)$row['created_by'],
            ['period_index'=>0]);
        $schedule=recurring_billing_row((string)$row['schedule_id']);$running=recurring_billing_validate($schedule);
        if($running->phase()!=='running')throw new RuntimeException('The signed financing schedule did not activate.');
        if(one('SELECT license_id FROM '.table('license_financing').' WHERE license_id=? FOR UPDATE',[$row['license_id']])){
            throw new RuntimeException('This license already has a financing binding.');
        }
        $rights=$terms['rights'];$now=gmdate('Y-m-d H:i:s');
        $financeState=(int)$row['initial_payment_cents']>0?'pending_initial':'active';
        query('INSERT INTO '.table('license_financing').' (license_id,schedule_id,terms_sha256,installments,grace_days,state,paid_off_at,allow_resale,allow_distribution,exclusive_resale,exclusive_distribution,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$row['license_id'],$row['schedule_id'],license_financing_terms_hash($running),(int)$row['installments'],(int)$row['grace_days'],$financeState,null,
             $rights['resale']?1:0,$rights['distribution']?1:0,$rights['exclusive_resale']?1:0,$rights['exclusive_distribution']?1:0,
             $row['created_by'],$now,$now]);
        $client=one('SELECT email FROM '.table('studio_clients').' WHERE id=?',[$clientId]);
        if(!$client||!filter_var($client['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Client email is unavailable for signature evidence.');
        $evidence=hash('sha256',json_encode([$id,$clientId,$signer,$client['email'],$row['terms_sha256'],$now],JSON_THROW_ON_ERROR));
        query('UPDATE '.table('license_finance_offers').' SET status=?,signed_name=?,signed_email=?,signed_at=?,signed_terms_sha256=?,signature_evidence_hash=?,updated_at=? WHERE id=?',
            ['signed',$signer,$client['email'],$now,$row['terms_sha256'],$evidence,$now,$id]);
        license_audit($clientId,'financing.offer.signed','Finance agreement '.$id.' signed by client '.$clientId.' terms '.$row['terms_sha256']);
        return license_finance_offer_row($id,$clientId,false);
    }));
    if((int)$signed['initial_payment_cents']===0){
        return ['offer'=>$signed,'payment'=>null,'payment_pending'=>false];
    }
    // The electronic signature above is already committed and immutable.
    // Any failure after that boundary belongs to payment setup, never to the
    // legal signature itself. Retain the signed agreement and let the client
    // or studio prepare/recover the current payment request later.
    try{
        $payment=license_finance_offer_payment_prepare($id,$clientId);
        return ['offer'=>$signed,'payment'=>$payment,'payment_pending'=>false];
    }catch(Throwable $paymentError){
        report_error($paymentError);
        return ['offer'=>$signed,'payment'=>null,'payment_pending'=>true];
    }
}

function license_finance_offer_decline(string $id,int $clientId,string $reason):void{
    $reason=trim($reason);
    if(strlen($reason)<3||strlen($reason)>500)throw new InvalidArgumentException('Enter a brief reason for declining.');
    if(support_current())throw new RuntimeException('Finance agreements must be declined from the client’s own account.');
    staff_locked(fn()=>staff_transaction(function()use($id,$clientId,$reason):void{
        service_plans_current_client($clientId);$row=license_finance_offer_row($id,$clientId,true);
        if(license_finance_offer_status($row)!=='offered')throw new RuntimeException('This finance agreement can no longer be declined.');
        $schedule=recurring_billing_row((string)$row['schedule_id']);$doc=recurring_billing_validate($schedule);
        if($doc->phase()!=='offered'||(int)$schedule['revision']!==(int)$row['schedule_revision']||!hash_equals((string)$row['schedule_sha256'],$doc->fingerprint())){
            throw new RuntimeException('The installment schedule changed. Ask the studio for a current agreement.');
        }
        recurring_billing_append($schedule,(int)$schedule['revision'],bin2hex(random_bytes(16)),'decline','client:'.$clientId,['reason'=>$reason]);
        query('UPDATE '.table('license_finance_offers').' SET status=?,updated_at=? WHERE id=?',['declined',gmdate('Y-m-d H:i:s'),$id]);
        license_audit($clientId,'financing.offer.declined','Finance agreement '.$id.' declined: '.$reason);
    }));
}

function license_finance_offer_payment_prepare(string $id,int $clientId):?array{
    $preview=license_finance_offer_row($id,$clientId,false);
    if((string)$preview['status']!=='signed')throw new RuntimeException('Sign the finance agreement before preparing payment.');
    // Zero-down agreements do not need payment-link storage at signature time.
    // They become active from the signed schedule and can enroll installments
    // later when gateway/payment infrastructure is available.
    if((int)$preview['initial_payment_cents']===0)return null;
    if(!payments_ready())throw new RuntimeException('Payment links must be prepared before financing can collect an initial payment.');
    $invoice=staff_locked(fn()=>staff_transaction(function()use($id,$clientId):?array{
        $row=license_finance_offer_row($id,$clientId,true);
        if((string)$row['status']!=='signed')throw new RuntimeException('Sign the finance agreement before preparing payment.');
        if((int)$row['initial_payment_cents']===0)return null;
        if($row['activation_invoice_id']!==null){
            $i=ledger_invoice((int)$row['activation_invoice_id']);
            if((int)$i['client_id']!==$clientId||(int)$i['job_id']!==(int)$row['job_id'])throw new RuntimeException('Finance initial invoice ownership changed.');
            return $i;
        }
        $reference='FIA-'.strtoupper(bin2hex(random_bytes(10)));$now=gmdate('Y-m-d H:i:s');
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,instructions,created_at) VALUES (?,?,?,?,?,?,?)',
            [$row['job_id'],$reference,'Finance agreement initial payment · '.$row['title'],(int)$row['initial_payment_cents'],'USD',
             'Initial payment for signed finance agreement '.$id.'. Financing terms and electronic acceptance are retained in the client portal.',$now]);
        $invoiceId=(int)db()->lastInsertId();
        query('UPDATE '.table('license_finance_offers').' SET activation_invoice_id=?,updated_at=? WHERE id=?',
            [$invoiceId,$now,$id]);
        return ledger_invoice($invoiceId);
    }));
    if($invoice===null)return null;
    $row=license_finance_offer_row($id,$clientId,false);
    $request=payments_financing_request((int)$invoice['id'],(int)$row['created_by'],(int)$row['payment_link_days'],(string)$row['signature_evidence_hash']);
    staff_locked(fn()=>staff_transaction(function()use($id,$request):void{
        $row=license_finance_offer_row($id,null,true);
        if($row['payment_request_id']!==null&&(int)$row['payment_request_id']!==(int)$request['id']){
            $previous=one('SELECT * FROM '.table('payment_requests').' WHERE id=?',[$row['payment_request_id']]);
            if(!$previous||payments_request_state($previous)==='prepared')throw new RuntimeException('A current financing payment request already exists.');
        }
        query('UPDATE '.table('license_finance_offers').' SET payment_request_id=?,updated_at=? WHERE id=?',
            [$request['id'],gmdate('Y-m-d H:i:s'),$id]);
    }));
    return $request;
}

function license_finance_offer_email_draft(string $id,string $accountId,int $accountRevision):string{
    $row=license_finance_offer_row($id);$status=license_finance_offer_status($row);
    if(!in_array($status,['offered','signed'],true))throw new RuntimeException('This finance agreement is not available for email.');
    if(!mail_drafts_ready())throw new RuntimeException('Prepare local email drafts first.');
    $account=mail_drafts_account($accountId,$accountRevision);
    $url=license_finance_offer_portal_url($id);
    $subject=$status==='signed'?'Your signed financing agreement':'Review and sign your financing agreement';
    $body=$status==='signed'
        ? "Your financing agreement is signed and available in your CajunWebPro client portal.\n\nOpen agreement: ".$url."\n\nSign in with your client account. If a payment is due, the agreement page will take you to the current payment request."
        : "A financing agreement has been prepared for your project.\n\nReview and sign it securely in your CajunWebPro client portal:\n".$url."\n\nThe link itself does not authorize anyone; you must sign in to the matching client account before the agreement can be viewed or signed.";
    $content=new DraftContent([(string)$row['client_email']],[],[],$subject,$body);
    $draft=bin2hex(random_bytes(16));
    mail_drafts_save($draft,0,$account['id'],$accountRevision,$content,false);
    $mailActor=mail_drafts_actor();
    license_audit((int)$mailActor['id'],'financing.offer.email_draft','Finance agreement '.$id.' email draft '.$draft.' prepared for client '.$row['client_id']);
    return $draft;
}

function license_finance_offer_list_client(int $clientId):array{
    if(!license_finance_offers_ready())return [];
    return all('SELECT id,title,status,terms_json,terms_sha256,expires_at,signed_at,activation_invoice_id,payment_request_id FROM '.table('license_finance_offers').' WHERE client_id=? ORDER BY created_at DESC LIMIT 100',[$clientId]);
}
function license_finance_offer_list_admin():array{
    if(!license_finance_offers_ready())return [];
    return all('SELECT o.*,c.name AS client_name,j.title AS project_title FROM '.table('license_finance_offers').' o JOIN '.table('studio_clients').' c ON c.id=o.client_id JOIN '.table('studio_jobs').' j ON j.id=o.job_id ORDER BY o.created_at DESC LIMIT 100');
}
function license_finance_offer_candidates():array{
    if(!recurring_billing_ready())return [];
    $rows=all('SELECT s.*,c.name AS client_name,j.title AS project_title FROM '.table('recurring_billing_schedules').' s JOIN '.table('studio_clients').' c ON c.id=s.client_id JOIN '.table('studio_jobs').' j ON j.id=s.job_id ORDER BY s.created_epoch DESC LIMIT 100');$out=[];
    foreach($rows as $row){
        try{
            $doc=recurring_billing_validate($row);
            if($doc->phase()!=='offered')continue;
            $terms=RecurringBillingTerms::fromArray($doc->data()['terms'])->data();
            $out[]=['id'=>$row['id'],'client_id'=>(int)$row['client_id'],'job_id'=>(int)$row['job_id'],
                'client_name'=>$row['client_name'],'project_title'=>$row['project_title'],
                'title'=>$terms['title'],'amount_cents'=>(int)$terms['amount_cents'],'cadence'=>$terms['cadence'],'anchor'=>$terms['anchor']];
        }catch(Throwable){}
    }
    return $out;
}
