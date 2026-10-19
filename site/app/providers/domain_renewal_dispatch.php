<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_agreements.php';
require_once CWP_CODE_ROOT.'/app/providers/operations.php';
require_once CWP_CODE_ROOT.'/app/billing/ledger_fulfillment.php';
require_once CWP_CODE_ROOT.'/app/billing/recurring_billing_invoices.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch_document.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_dispatch_storage.php';
require_once CWP_CODE_ROOT.'/app/providers/domain_renewal_invoice_create.php';
use CajunWebPro\Foundation\TwentyI\Operations\DomainRenewal;
use CajunWebPro\Foundation\Provider\{ProviderOperation,ProviderGateway};
use CajunWebPro\Foundation\TwentyI\FixedHttpTransport;
use CajunWebPro\Foundation\MailTransport\EndpointPolicy;

function domain_renewal_dispatch_owner(#[SensitiveParameter] ?string $password=null,bool $ready=true):array {
    $actor=$password===null?provider_settings_manager():provider_settings_owner($password);service_plans_owner();
    if(!staff_can('payments.manage',$actor)||!payments_ready()||!ledger_ready())throw new RuntimeException('Current owner payment access and original payment history are required.');
    if($ready&&!domain_renewal_dispatch_ready())throw new RuntimeException('Prepare renewal dispatch first.');return $actor;
}
function domain_renewal_dispatch_audit(string $kind,array $row):array {
    return domain_contracts_audit_row((int)$row['actor_id'],'domain_renewal_dispatch.'.$kind,'Renewal '.$kind.' '.$row['id'].' '.domain_renewal_dispatch_digest(domain_renewal_dispatch_values($kind,$row)));
}
function domain_renewal_dispatch_offer(string $requestId):array {
    $history=domain_renewal_agreements_history_locked($requestId);$offers=$history['offers'];if(!$offers)throw new RuntimeException('Agree to the exact renewal terms first.');$offer=end($offers);$binding=$offer['binding'];$agreement=$offer['agreement'];$model=$agreement->model();
    domain_renewal_agreements_current($history,$binding,false);
    if($model->state()!=='active'||$model->consent()===null||$model->consent()['by']!=='client:'.$binding->data()['registrar']['client_id']||$model->plan->cadence!=='one-time'||$model->plan->currency!=='USD'||$model->agreedPrice<1)throw new RuntimeException('The active renewal agreement needs this client’s recorded consent and positive one-time USD terms.');
    return ['history'=>$history,'offer'=>$offer,'authority'=>domain_renewal_agreements_authority($history['request'])];
}
function domain_renewal_dispatch_invoice_snapshot(array $i):array {
    return ['id'=>(int)$i['id'],'project_id'=>(int)$i['job_id'],'client_id'=>(int)$i['client_id'],'sha256'=>ledger_invoice_hash($i),'reference'=>$i['reference'],'title'=>$i['title'],'amount_minor'=>(int)$i['amount_cents'],'currency'=>$i['currency'],'instructions'=>$i['instructions'],'created_at'=>$i['created_at']];
}
/** No amount/client-only allocation: independently exclude every existing invoice purpose. */
function domain_renewal_dispatch_other_origins(int $invoice):void {
    domain_renewal_agreements_locked();$a=table('audit');
    foreach(['catalog_invoice_snapshots'=>'catalog_schema_version','service_orders'=>'service_orders_schema_version'] as $name=>$marker){
        $version=one('SELECT value FROM '.table('settings').' WHERE name=?',[$marker])['value']??'';
        if($version!==''&&!domain_renewal_dispatch_exists($name))throw new RuntimeException('Established invoice purpose history needs recovery.');
        if(domain_renewal_dispatch_exists($name)&&one('SELECT id FROM '.table($name).' WHERE invoice_id=? LIMIT 1',[$invoice]))throw new RuntimeException('This invoice already belongs to another service.');
    }
    if(one("SELECT id FROM $a WHERE (BINARY action='catalog.invoice.issue' AND BINARY detail LIKE ?) OR (BINARY action='service_orders.offered' AND BINARY detail LIKE ?) LIMIT 1",['Invoice '.$invoice.' project %','% invoice '.$invoice.' client %']))throw new RuntimeException('This invoice has another original service purpose.');
    $i=ledger_invoice($invoice);recurring_billing_invoice_guard($i);if(recurring_billing_invoice_origin($invoice)!==null||str_starts_with($i['reference'],'RI-')||(domain_renewal_dispatch_exists('recurring_billing_invoices')&&one('SELECT id FROM '.table('recurring_billing_invoices').' WHERE invoice_id=?',[$invoice])))throw new RuntimeException('A recurring invoice cannot fund a one-time renewal.');
    // The integrated licensing lane may add invoice_id; read only, without preparing or executing it.
    if(domain_renewal_dispatch_exists('website_licenses')&&one('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[config()['db']['prefix'].'website_licenses','invoice_id'])&&one('SELECT id FROM '.table('website_licenses').' WHERE invoice_id=? LIMIT 1',[$invoice]))throw new RuntimeException('This invoice already funded a software product.');
}
/** Reciprocal shared-hosting admission hook; absence before first preparation is harmless. */
function domain_renewal_dispatch_exclude_invoice(int $invoice):void {
    domain_renewal_agreements_locked();if(!domain_renewal_dispatch_ready())return;
    if(one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE invoice_id=?',[$invoice]))throw new RuntimeException('This invoice is permanently reserved for a domain renewal.');
}
/** Sandbox audits omit invoice identity. Reject orphan history globally before inferring pristine ownership. */
function domain_renewal_dispatch_payment_origins():void {
    domain_renewal_agreements_locked();$a=table('audit');$r=table('payment_requests');$count=static fn($sql)=>(int)query($sql)->fetchColumn();
    if(!domain_renewal_dispatch_exists('payment_requests')||$count("SELECT COUNT(*) FROM $r")!==$count("SELECT COUNT(*) FROM $a WHERE BINARY action='payments.request.prepare'"))throw new RuntimeException('Original payment request history needs recovery.');
    if(one("SELECT r.id FROM $r r WHERE (SELECT COUNT(*) FROM $a a WHERE BINARY a.action='payments.request.prepare' AND a.user_id=r.actor_id AND BINARY a.detail=BINARY CONCAT(BINARY 'Request ',r.id,BINARY ' invoice ',r.invoice_id,BINARY '; charging disabled'))<>1 LIMIT 1"))throw new RuntimeException('Original payment request correspondence needs recovery.');
    $v=one('SELECT value FROM '.table('settings').' WHERE name=?',['payment_attempts_schema_version'])['value']??'';$hasAttempts=domain_renewal_dispatch_exists('payment_attempts');$hasEvents=domain_renewal_dispatch_exists('payment_attempt_events');
    $auditWhere="BINARY action LIKE 'payments.sandbox.%' AND (BINARY detail LIKE 'Attempt %' OR BINARY action<>'payments.sandbox.check')";$audits=$count("SELECT COUNT(*) FROM $a WHERE $auditWhere");
    if(!in_array($v,['','1'],true)||($v==='1'&&(!$hasAttempts||!$hasEvents))||(($hasAttempts!==$hasEvents)||(!$hasEvents&&$audits)))throw new RuntimeException('Original sandbox history needs recovery.');
    if(!$hasAttempts)return;$p=table('payment_attempts');$e=table('payment_attempt_events');
    if($audits!==$count("SELECT COUNT(*) FROM $e")||one("SELECT p.id FROM $p p LEFT JOIN $r r ON r.id=p.request_id AND r.invoice_id=p.invoice_id AND r.amount_cents=p.amount_cents AND BINARY r.currency=BINARY p.currency WHERE r.id IS NULL OR (SELECT COUNT(*) FROM $e e WHERE e.attempt_id=p.id AND BINARY e.event='submitted' AND e.actor_id=p.actor_id)<>1 LIMIT 1")||one("SELECT e.id FROM $e e LEFT JOIN $p p ON p.id=e.attempt_id WHERE p.id IS NULL LIMIT 1"))throw new RuntimeException('Original sandbox mapping history needs recovery.');
    // Repeated observations can legitimately have the same action/detail; compare complete group multiplicity.
    if(one("SELECT g.attempt_id FROM (SELECT attempt_id,actor_id,BINARY event AS event,COUNT(*) AS n FROM $e GROUP BY attempt_id,actor_id,BINARY event) g WHERE g.n<>(SELECT COUNT(*) FROM $a a WHERE a.user_id=g.actor_id AND BINARY a.action=BINARY CONCAT(BINARY 'payments.sandbox.',g.event) AND BINARY a.detail=BINARY CONCAT(BINARY 'Attempt ',g.attempt_id,BINARY '; no real invoice settlement')) LIMIT 1"))throw new RuntimeException('Original sandbox audit correspondence needs recovery.');
}
function domain_renewal_dispatch_unpaid(array $i):void {
    domain_renewal_dispatch_payment_origins();$id=(int)$i['id'];if($i['status']!=='issued'||$i['paid_at']!==null||$i['paid_note']!==''||(int)$i['active']!==1||$i['currency']!=='USD'||(int)$i['amount_cents']<1)throw new RuntimeException('Allocate an unpaid USD invoice before any payment activity.');
    foreach(['invoice_payments','payment_requests','payment_attempts'] as $name)if(domain_renewal_dispatch_exists($name)&&one('SELECT id FROM '.table($name).' WHERE invoice_id=? LIMIT 1',[$id]))throw new RuntimeException('Allocate the invoice before any payment or payment request.');
    $version=one('SELECT value FROM '.table('settings').' WHERE name=?',['payment_attempts_schema_version'])['value']??'';if($version!==''&&!domain_renewal_dispatch_exists('payment_attempts'))throw new RuntimeException('Payment-attempt history needs recovery.');
    if(one('SELECT id FROM '.table('audit')." WHERE (BINARY action IN ('payments.ledger.manual','payments.ledger.legacy','payments.ledger.reversal') AND BINARY detail LIKE ?) OR (BINARY action='payments.manual.paid' AND BINARY detail LIKE ?) OR (BINARY action='payments.request.prepare' AND BINARY detail LIKE ?) LIMIT 1",['Entry % invoice '.$id.' amount %','Invoice '.$id.' project %','Request % invoice '.$id.'; charging disabled']))throw new RuntimeException('Original payment activity prevents invoice reallocation.');
    if(ledger_balance($i)!==['paid_cents'=>0,'due_cents'=>(int)$i['amount_cents'],'revision'=>0])throw new RuntimeException('This invoice already has payment history.');
}
function domain_renewal_dispatch_allocation(string $id):array {
    DomainRenewalInvoiceDocument::id($id);$row=one('SELECT * FROM '.table('domain_renewal_invoices').' WHERE id=?',[$id]);if(!$row)throw new RuntimeException('Renewal invoice allocation is unavailable.');
    domain_renewal_dispatch_decode('allocation',$row);$doc=DomainRenewalInvoiceDocument::fromJson($row['document_json']);$d=$doc->data();$b=$d['binding'];
    if($row['id']!==$d['id']||(int)$row['invoice_id']!==$d['invoice']['id']||$row['binding_id']!==$b['id']||$row['request_id']!==$b['request_id']||(int)$row['client_id']!==$b['registrar']['client_id']||(int)$row['actor_id']!==$d['actor_id'])throw new RuntimeException('Renewal invoice indexed identity needs recovery.');
    $history=domain_renewal_agreements_history_locked($b['request_id']);$offer=null;foreach($history['offers'] as $candidate)if($candidate['binding']->data()['id']===$b['id'])$offer=$candidate;
    if(!$offer||$offer['binding']->json()!==DomainRenewalInvoiceDocument::json($b)||$d['agreement_revision']>$offer['agreement']->revision())throw new RuntimeException('Original accepted renewal terms need recovery.');
    $prefix=$offer['agreement']->data();$prefix['events']=array_slice($prefix['events'],0,$d['agreement_revision']);$accepted=ServiceSubscriptionDocument::fromJson(service_plans_json($prefix));$model=$accepted->model();
    if(!hash_equals($accepted->fingerprint(),$d['agreement_sha256'])||$model->state()!=='active'||$model->consent()===null||$model->consent()['by']!=='client:'.$b['registrar']['client_id'])throw new RuntimeException('Original renewal consent needs recovery.');
    $audit=domain_renewal_dispatch_audit('allocation',$row);$event=end($prefix['events']);[$kind,$actor]=explode(':',$event['actor'],2);$prior=domain_contracts_audit_row($kind==='staff'?(int)$actor:0,'service_plans.subscription_changed','Record '.$b['agreement_id'].' event '.$event['id'].' revision '.$d['agreement_revision'].' '.$event['command'].' client '.$b['registrar']['client_id']);
    if($prior['id']>=$audit['id']||$event['at']>$d['created_epoch'])throw new RuntimeException('Renewal allocation precedes accepted terms.');
    $invoice=ledger_invoice($d['invoice']['id']);if(domain_renewal_dispatch_invoice_snapshot($invoice)!==$d['invoice'])throw new RuntimeException('The original renewal invoice changed.');domain_renewal_dispatch_other_origins($d['invoice']['id']);
    return ['row'=>$row,'document'=>$doc,'audit'=>$audit,'history'=>$history,'offer'=>$offer,'invoice'=>$invoice];
}
/** Final v1 witness writer shared by allocation and atomic invoice creation. */
function domain_renewal_dispatch_record_allocation_locked(string $id,string $requestId,string $bindingHash,string $agreementHash,array $actor,array $offer,array $snapshot):array {
    domain_renewal_agreements_locked();$b=$offer['binding'];$agreement=$offer['agreement'];$bd=$b->data();$invoiceId=$snapshot['id'];
$events=$agreement->data()['events'];$at=max(time(),$events[array_key_last($events)]['at']);$doc=DomainRenewalInvoiceDocument::fromArray(['version'=>1,'id'=>$id,'binding'=>$bd,'binding_sha256'=>$bindingHash,'agreement_revision'=>$agreement->revision(),'agreement_sha256'=>$agreementHash,'invoice'=>$snapshot,'actor_id'=>(int)$actor['id'],'created_epoch'=>$at]);
        $row=['id'=>$id,'invoice_id'=>$invoiceId,'binding_id'=>$bd['id'],'request_id'=>$requestId,'client_id'=>$bd['registrar']['client_id'],'actor_id'=>(int)$actor['id'],'document_json'=>$doc->jsonBytes(),'document_sha256'=>$doc->fingerprint()];domain_renewal_dispatch_insert('allocation',domain_renewal_dispatch_prove('allocation',$row));return domain_renewal_dispatch_allocation($id);
}
function domain_renewal_dispatch_allocate(string $id,string $requestId,string $bindingHash,string $agreementHash,int $invoiceId,string $invoiceHash,bool $confirmed,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('Renewal allocation cannot inherit a transaction.');DomainRenewalInvoiceDocument::id($id);DomainRenewalInvoiceDocument::id($requestId);foreach([$bindingHash,$agreementHash,$invoiceHash] as $hash)DomainRenewalInvoiceDocument::hash($hash);if(!$confirmed)throw new InvalidArgumentException('Explicitly reserve this unpaid invoice for the exact renewal terms.');
    return staff_locked(fn()=>staff_transaction(function()use($id,$requestId,$bindingHash,$agreementHash,$invoiceId,$invoiceHash,$password):array{
        $actor=domain_renewal_dispatch_owner($password);$existing=one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE id=?',[$id]);
        if($existing){$old=domain_renewal_dispatch_allocation($id);$d=$old['document']->data();if($d['invoice']['reference']===domain_renewal_dispatch_creation_reference($id))throw new RuntimeException('Use the original renewal invoice creation request.');if($d['actor_id']!==(int)$actor['id']||$d['binding']['request_id']!==$requestId||$d['binding_sha256']!==$bindingHash||$d['agreement_sha256']!==$agreementHash||$d['invoice']['id']!==$invoiceId||$d['invoice']['sha256']!==$invoiceHash)throw new RuntimeException('This allocation identity was already used.');return $old;}
        $context=domain_renewal_dispatch_offer($requestId);$offer=$context['offer'];$b=$offer['binding'];$agreement=$offer['agreement'];if(!hash_equals($b->fingerprint(),$bindingHash)||!hash_equals($agreement->fingerprint(),$agreementHash))throw new RuntimeException('Review the current accepted renewal terms.');
        $invoice=one('SELECT i.*,j.client_id,c.active FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE i.id=? FOR UPDATE',[$invoiceId]);if(!$invoice)throw new RuntimeException('Choose an existing invoice.');$snapshot=domain_renewal_dispatch_invoice_snapshot($invoice);$bd=$b->data();
        if($snapshot['client_id']!==$bd['registrar']['client_id']||$snapshot['currency']!=='USD'||$snapshot['amount_minor']!==$bd['price_minor']||!hash_equals($snapshot['sha256'],$invoiceHash))throw new RuntimeException('Choose this client’s exact agreed one-time USD invoice.');
        if(domain_renewal_dispatch_creation_reference_matches($snapshot['reference']))throw new RuntimeException('A created renewal invoice cannot be allocated again.');
        domain_renewal_dispatch_other_origins($invoiceId);domain_renewal_dispatch_unpaid($invoice);domain_renewal_dispatch_exclude_invoice($invoiceId);
        if(one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE binding_id=?',[$bd['id']]))throw new RuntimeException('This renewal agreement already has its permanent invoice.');
        if(one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE request_id=?',[$requestId]))throw new RuntimeException('A renewal dispatch already exists for this request.');
        return domain_renewal_dispatch_record_allocation_locked($id,$requestId,$bindingHash,$agreementHash,$actor,$offer,$snapshot);
    }));
}
/** Payment receipts are only usable after this purpose origin, with a complete audit bijection. */
function domain_renewal_dispatch_payment(array $allocation):array {
    domain_renewal_dispatch_payment_origins();$d=$allocation['document']->data();$invoice=$d['invoice']['id'];$rows=all('SELECT * FROM '.table('invoice_payments').' WHERE invoice_id=? ORDER BY id LIMIT 4097',[$invoice]);if(count($rows)>4096)throw new RuntimeException('Renewal payment history exceeds its review bound.');
    $audits=all('SELECT id,user_id,action,detail FROM '.table('audit')." WHERE BINARY action IN ('payments.ledger.manual','payments.ledger.legacy','payments.ledger.reversal') AND BINARY detail LIKE ? ORDER BY id LIMIT 8193",['Entry % invoice '.$invoice.' amount %']);if(count($audits)!==count($rows))throw new RuntimeException('Renewal receipt origin history needs recovery.');$seen=[];
    foreach($rows as $r){$detail='Entry '.$r['id'].' invoice '.$invoice.' amount '.$r['amount_cents'].' '.$r['currency'].($r['kind']==='reversal'?' reverses '.$r['reversal_of']:'');$matches=array_values(array_filter($audits,static fn($a)=>$a['action']==='payments.ledger.'.$r['kind']&&$a['detail']===$detail&&(int)$a['user_id']===(int)$r['actor_id']));if(count($matches)!==1||isset($seen[$matches[0]['id']])||(int)$matches[0]['id']<=$allocation['audit']['id'])throw new RuntimeException('Renewal receipt must follow its original purpose allocation.');$seen[$matches[0]['id']]=true;}
    $payment=ledger_fulfillment_evidence($invoice,$d['invoice']['client_id']);if(!$payment['payment_evidence_ready'])throw new RuntimeException('Verified payment of this allocated renewal invoice is required.');
    $witness=array_map(static fn($a)=>['id'=>(int)$a['id'],'actor_id'=>(int)$a['user_id'],'action'=>$a['action'],'detail'=>$a['detail']],$audits);
    $payment['review_sha256']=hash('sha256',DomainRenewalInvoiceDocument::json(['ledger_review_sha256'=>$payment['review_sha256'],'allocation_audit_id'=>(int)$allocation['audit']['id'],'receipt_audits'=>$witness]));return $payment;
}
function domain_renewal_dispatch_current(array $allocation):array {
    $d=$allocation['document']->data();$c=domain_renewal_dispatch_offer($d['binding']['request_id']);$b=$c['offer']['binding'];$agreement=$c['offer']['agreement'];
    if(!hash_equals($b->fingerprint(),$d['binding_sha256'])||!hash_equals($agreement->fingerprint(),$d['agreement_sha256'])||$agreement->revision()!==$d['agreement_revision'])throw new RuntimeException('The allocated renewal terms changed.');
    $payment=domain_renewal_dispatch_payment($allocation);$review=['allocation_sha256'=>$allocation['document']->fingerprint(),'agreement_sha256'=>$agreement->fingerprint(),'authority'=>$c['authority'],'payment_review_sha256'=>$payment['review_sha256']];
    return $c+['payment'=>$payment,'review_sha256'=>hash('sha256',DomainRenewalInvoiceDocument::json($review))];
}
function domain_renewal_dispatch_request(string $id):array {
    DomainRenewalInvoiceDocument::id($id);$row=one('SELECT * FROM '.table('domain_renewal_dispatches').' WHERE id=?',[$id]);if(!$row)throw new RuntimeException('Renewal dispatch is unavailable.');domain_renewal_dispatch_decode('approval',$row);$doc=DomainRenewalDispatchDocument::fromJson($row['document_json']);$d=$doc->data();$allocation=domain_renewal_dispatch_allocation($d['allocation_id']);$ad=$allocation['document']->data();$b=$ad['binding'];
    if($id!==$d['id']||$row['allocation_id']!==$d['allocation_id']||$row['request_id']!==$d['request_id']||$row['profile_id']!==$d['registrar']['profile_id']||(int)$row['domain_id']!==$d['registrar']['domain_id']||$row['domain']!==$d['params']['name']||(int)$row['client_id']!==$d['client_id']||(int)$row['actor_id']!==$d['actor_id']||$b['id']!==$d['binding_id']||$b['request_id']!==$d['request_id']||$ad['agreement_sha256']!==$d['agreement_sha256']||$ad['agreement_revision']!==$d['agreement_revision']||!hash_equals($allocation['document']->fingerprint(),$d['allocation_sha256'])||$d['params']['years']!==$b['requested_years']||$d['params']['renewPrivacy']!==$b['renew_privacy'])throw new RuntimeException('Renewal approval indexed identity needs recovery.');
    foreach($b['registrar'] as $key=>$value)if(!str_starts_with($key,'source_')&&$d['registrar'][$key]!==$value)throw new RuntimeException('Renewal approval account identity changed.');if($d['credential_sha256']!==$b['credential_sha256'])throw new RuntimeException('Renewal approval credential identity changed.');
    $source=domain_contracts_source($d['registrar']['source_refresh_id'],$d['registrar']['domain_id']);if($source['seq']!==$d['registrar']['source_seq']||hash('sha256',domain_contracts_json($source))!==$d['registrar']['source_sha256'])throw new RuntimeException('Original renewal registrar source needs recovery.');
    $grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);if(!$grant||(int)$grant['seq']!==(int)$row['grant_seq']||$grant['operation']!==DomainRenewal::OPERATION||!hash_equals($grant['scope_fingerprint'],provider_operations_scope(DomainRenewal::OPERATION,$d))||(int)$grant['profile_revision']!==$d['registrar']['profile_revision']||$grant['scope_params']!==$doc->jsonBytes()||$grant['consumed_key']!=='domain-renewal:'.$id||(int)$grant['expires_epoch']!==$d['expires_epoch']||(int)$grant['granted_by']!==$d['actor_id'])throw new RuntimeException('Renewal approval grant needs recovery.');
    $audit=domain_renewal_dispatch_audit('approval',$row);if($audit['id']<=$allocation['audit']['id'])throw new RuntimeException('Renewal approval precedes invoice allocation.');
    $ledger=one('SELECT * FROM '.table('provider_operations').' WHERE idempotency_key=?',[$grant['consumed_key']]);if($ledger){$identity=$d['registrar']['profile_id'].':'.$d['registrar']['profile_revision'].':'.$d['registrar']['key_fingerprint'];$op=new ProviderOperation(DomainRenewal::OPERATION,$grant['consumed_key'],$d['params']);if(!hash_equals($ledger['fingerprint'],ProviderGateway::requestFingerprint('twenty-i',$identity,$op))||($ledger['operation']!==null&&$ledger['operation']!==DomainRenewal::OPERATION))throw new RuntimeException('Renewal execution identity needs recovery.');}
    return ['row'=>$row,'document'=>$doc,'allocation'=>$allocation,'grant'=>$grant,'ledger'=>$ledger];
}
function domain_renewal_dispatch_terminal(array $request):?array {
    $r=one('SELECT * FROM '.table('domain_renewal_dispatch_ends').' WHERE id=?',[$request['row']['id']]);if(!$r)return null;$d=domain_renewal_dispatch_decode('terminal',$r);
    DomainRenewalInvoiceDocument::keys($d,['version','request_proof','phase','authority_current','ended_epoch','ledger_sha256']);if($d['version']!==1||$d['request_proof']!==$request['row']['proof']||!in_array($d['phase'],['not_sent','attempted'],true)||$d['phase']!==$r['disposition']||!is_bool($d['authority_current'])||!is_int($d['ended_epoch'])||$d['ended_epoch']<$request['document']->data()['approved_epoch']||$d['ledger_sha256']!==$r['ledger_sha256']||!hash_equals($d['ledger_sha256'],domain_renewal_dispatch_ledger_digest($request['ledger']))||(int)$r['actor_id']!==(int)$request['row']['actor_id']||($d['phase']==='not_sent'&&$request['ledger']!==null))throw new RuntimeException('Renewal terminal witness needs recovery.');domain_renewal_dispatch_audit('terminal',$r);return $d;
}
function domain_renewal_dispatch_authorize(array $request):array {
    return staff_transaction(function()use($request):array{
        $actor=domain_renewal_dispatch_owner();$fresh=domain_renewal_dispatch_request($request['row']['id']);$d=$fresh['document']->data();$g=$fresh['grant'];$c=domain_renewal_dispatch_current($fresh['allocation']);
        if((int)$actor['id']!==$d['actor_id']||!hash_equals(hash('sha256',$actor['auth_version']),$d['actor_auth'])||$g['revoked_at']!==null||(int)$g['expires_epoch']<=time()||$c['review_sha256']!==$d['review_sha256']||$c['authority']['registrar']!==$d['registrar']||$c['authority']['credential_sha256']!==$d['credential_sha256']||domain_renewal_dispatch_terminal($fresh)!==null)throw new ProviderOperationAccessChanged('Renewal approval is no longer current.');
        [, $profile]=provider_checks_authority($d['registrar']['profile_id'],$d['registrar']['profile_revision']);return $profile;
    });
}
/** Any unresolved attempted domain charge remains fenced across reassignment/account changes. */
function domain_renewal_dispatch_fence(string $domain):void {
    foreach(all('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE BINARY domain=? ORDER BY grant_seq LIMIT 129',[$domain]) as $n=>$r){if($n>=128)throw new RuntimeException('Renewal dispatch history needs owner review.');$q=domain_renewal_dispatch_request($r['id']);$t=domain_renewal_dispatch_terminal($q);if(!$t||$t['phase']!=='attempted'||!$t['authority_current']||($q['ledger']['status']??null)!=='succeeded'||($q['ledger']['provider_code']??null)!=='renewal_reported_success')throw new RuntimeException('A previous renewal attempt needs review and cannot be sent again.');}
}
function domain_renewal_dispatch_send(string $id,string $allocationId,string $reviewHash,array $reviewed,#[SensitiveParameter] string $password,?callable $resolver=null,?callable $perform=null):array {
    if(db()->inTransaction())throw new LogicException('Renewal dispatch cannot inherit a transaction.');DomainRenewalInvoiceDocument::id($id);DomainRenewalInvoiceDocument::id($allocationId);DomainRenewalInvoiceDocument::hash($reviewHash);DomainRenewalInvoiceDocument::keys($reviewed,['eligible_years','privacy_scope','provider_cost','balance_charge']);foreach($reviewed as $v)if($v!==true)throw new InvalidArgumentException('Review eligibility, privacy, provider cost and the balance charge explicitly.');
    return staff_locked(function()use($id,$allocationId,$reviewHash,$reviewed,$password,$resolver,$perform):array{
        $new=staff_transaction(function()use($id,$allocationId,$reviewHash,$reviewed,$password):bool{
            $actor=domain_renewal_dispatch_owner($password);if(one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE id=?',[$id])){$old=domain_renewal_dispatch_request($id);$d=$old['document']->data();if($d['allocation_id']!==$allocationId||$d['review_sha256']!==$reviewHash||$d['reviewed']!==$reviewed||$d['actor_id']!==(int)$actor['id'])throw new RuntimeException('This renewal approval identity was already used.');return false;}
            $allocation=domain_renewal_dispatch_allocation($allocationId);$ad=$allocation['document']->data();$b=$ad['binding'];$c=domain_renewal_dispatch_current($allocation);if(!hash_equals($c['review_sha256'],$reviewHash))throw new RuntimeException('Reload and review the current paid renewal.');domain_renewal_dispatch_fence($b['registrar']['domain']);
            if(one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE allocation_id=? OR request_id=?',[$allocationId,$b['request_id']]))throw new RuntimeException('This paid renewal already has one dispatch approval.');
            $at=time();$params=DomainRenewal::params(['resellerId'=>'*','name'=>$b['registrar']['domain'],'years'=>$b['requested_years'],'renewPrivacy'=>$b['renew_privacy']]);$doc=DomainRenewalDispatchDocument::fromArray(['version'=>1,'id'=>$id,'allocation_id'=>$allocationId,'allocation_sha256'=>$allocation['document']->fingerprint(),'request_id'=>$b['request_id'],'binding_id'=>$b['id'],'client_id'=>$b['registrar']['client_id'],'registrar'=>$c['authority']['registrar'],'credential_sha256'=>$c['authority']['credential_sha256'],'agreement_revision'=>$ad['agreement_revision'],'agreement_sha256'=>$ad['agreement_sha256'],'payment_review_sha256'=>$c['payment']['review_sha256'],'receipt_ids'=>$c['payment']['receipt_ids'],'params'=>$params,'reviewed'=>$reviewed,'review_sha256'=>$reviewHash,'actor_id'=>(int)$actor['id'],'actor_auth'=>hash('sha256',$actor['auth_version']),'approved_epoch'=>$at,'expires_epoch'=>$at+900]);$d=$doc->data();
            query('INSERT INTO '.table('provider_operation_grants').' (id,profile_id,operation,scope_fingerprint,profile_revision,scope_params,granted_by,granted_at,expires_epoch,consumed_key,consumed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$id,$d['registrar']['profile_id'],DomainRenewal::OPERATION,provider_operations_scope(DomainRenewal::OPERATION,$d),$d['registrar']['profile_revision'],$doc->jsonBytes(),$actor['id'],gmdate('Y-m-d H:i:s',$at),$at+900,'domain-renewal:'.$id,gmdate('Y-m-d H:i:s',$at)]);$grant=one('SELECT * FROM '.table('provider_operation_grants').' WHERE id=?',[$id]);
            $row=['id'=>$id,'allocation_id'=>$allocationId,'request_id'=>$b['request_id'],'grant_seq'=>(int)$grant['seq'],'profile_id'=>$d['registrar']['profile_id'],'domain_id'=>$d['registrar']['domain_id'],'domain'=>$params['name'],'client_id'=>$d['client_id'],'actor_id'=>(int)$actor['id'],'document_json'=>$doc->jsonBytes(),'document_sha256'=>$doc->fingerprint()];domain_renewal_dispatch_insert('approval',domain_renewal_dispatch_prove('approval',$row));return true;
        });
        if(!$new)return staff_transaction(fn()=>domain_renewal_dispatch_summary($id));$request=staff_transaction(fn()=>domain_renewal_dispatch_request($id));$entered=false;$current=false;
        try{$authorize=static fn()=>domain_renewal_dispatch_authorize($request);$profile=$authorize();$address=EndpointPolicy::addresses([($resolver??[FixedHttpTransport::class,'resolve'])()])[0];$profile=$authorize();$secret=provider_settings_key_value(provider_settings_unseal($profile['id'],$profile['environment'],$profile['credentials']));
            try{if(!hash_equals(provider_operations_credential_fingerprint($secret),$request['document']->data()['registrar']['key_fingerprint']))throw new ProviderOperationAccessChanged('Renewal account changed.');$authorize();$op=new ProviderOperation(DomainRenewal::OPERATION,$request['grant']['consumed_key'],$request['document']->data()['params']);$entered=true;($perform??'provider_operations_perform')($profile,$secret,$address,$authorize,$op);$authorize();$current=true;}finally{unset($secret);}
        }catch(Throwable $e){if($e instanceof PDOException)throw $e;/* Retain only bounded phase/result, never exception contents. */}
        staff_transaction(function()use($id,$entered,$current):void{$q=domain_renewal_dispatch_request($id);$digest=domain_renewal_dispatch_ledger_digest($q['ledger']);$d=['version'=>1,'request_proof'=>$q['row']['proof'],'phase'=>$entered?'attempted':'not_sent','authority_current'=>$current,'ended_epoch'=>max(time(),$q['document']->data()['approved_epoch']),'ledger_sha256'=>$digest];$json=DomainRenewalInvoiceDocument::json($d);$row=['id'=>$id,'actor_id'=>(int)$q['row']['actor_id'],'disposition'=>$d['phase'],'ledger_sha256'=>$digest,'document_json'=>$json,'document_sha256'=>hash('sha256',$json)];domain_renewal_dispatch_insert('terminal',domain_renewal_dispatch_prove('terminal',$row));});
        return staff_transaction(fn()=>domain_renewal_dispatch_summary($id));
    });
}
function domain_renewal_dispatch_summary(string $id):array {
    domain_renewal_agreements_locked();if(!domain_renewal_dispatch_ready())throw new RuntimeException('Renewal dispatch is unavailable.');$q=domain_renewal_dispatch_request($id);$t=domain_renewal_dispatch_terminal($q);$d=$q['document']->data();$status='uncertain';
    if($t&&$t['phase']==='not_sent')$status='not_sent';elseif($t&&$t['authority_current']&&($q['ledger']['status']??null)==='succeeded'&&($q['ledger']['provider_code']??null)==='renewal_reported_success')$status='provider_reported_success';elseif($t&&$t['authority_current']&&($q['ledger']['status']??null)==='failed')$status=($q['ledger']['provider_code']??null)==='renewal_reported_failure'?'provider_reported_failure':'failed';
    return ['id'=>$id,'allocation_id'=>$d['allocation_id'],'request_id'=>$d['request_id'],'domain'=>$d['params']['name'],'years'=>$d['params']['years'],'renew_privacy'=>$d['params']['renewPrivacy'],'status'=>$status,'approved_epoch'=>$d['approved_epoch']];
}
/** Original invoice purpose is retained even after terms end; this never asserts current eligibility. */
function domain_renewal_dispatch_public(array $allocation):array {
    $d=$allocation['document']->data();$b=$d['binding'];$r=one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE allocation_id=?',[$d['id']]);
    return ['allocation_id'=>$d['id'],'request_id'=>$b['request_id'],'agreement_id'=>$b['agreement_id'],'invoice_id'=>$d['invoice']['id'],'invoice_reference'=>$d['invoice']['reference'],'domain'=>$b['registrar']['domain'],'years'=>$b['requested_years'],'renew_privacy'=>$b['renew_privacy'],'price_minor'=>$b['price_minor'],'currency'=>'USD','allocated_epoch'=>$d['created_epoch'],'agreement_state'=>$allocation['offer']['agreement']->model()->state(),'dispatch'=>$r?domain_renewal_dispatch_summary($r['id']):null];
}
/** Billing mutation hook. A retained reservation is never released by cancellation, correction or restore. */
function domain_renewal_dispatch_invoice_guard(int $invoice):void {
    domain_renewal_agreements_locked();if(!domain_renewal_dispatch_ready())return;$r=one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE invoice_id=?',[$invoice]);if($r)domain_renewal_dispatch_allocation($r['id']);
}
/** Authenticated invoice projection, saved facts only; never resolves provider credentials or sends. */
function domain_renewal_dispatch_invoice_context(int $invoice,int $client,bool $asClient):?array {
    if(db()->inTransaction())throw new LogicException('Renewal invoice display cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($invoice,$client,$asClient):?array{
        if($asClient){if(support_current())throw new RuntimeException('Renewal payment details are unavailable in support sessions.');$actor=studio_client();if(!$actor||(int)$actor['id']!==$client||(int)$actor['active']!==1)throw new RuntimeException('This client invoice is unavailable.');}else provider_settings_manager(false);
        if(!domain_renewal_dispatch_ready())return null;if($asClient)service_plans_current_client($client);else domain_renewal_dispatch_owner();
        $r=one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE invoice_id=? AND client_id=?',[$invoice,$client]);if(!$r)return null;$a=domain_renewal_dispatch_allocation($r['id']);if((int)$a['invoice']['client_id']!==$client)throw new RuntimeException('This client invoice is unavailable.');return domain_renewal_dispatch_public($a);
    }));
}
/** Outcome-aware request admission hook; acceptance/payment alone never ends a pending renewal. */
function domain_renewal_dispatch_completed(string $request):bool {
    domain_renewal_agreements_locked();if(!domain_renewal_dispatch_ready())return false;$r=one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE request_id=?',[$request]);return $r!==null&&domain_renewal_dispatch_summary($r['id'])['status']==='provider_reported_success';
}
function domain_renewal_dispatch_owner_request(string $request,int $page=1,int $projectPage=1):array {
    if(db()->inTransaction())throw new LogicException('Renewal dispatch display cannot inherit a transaction.');
    return staff_locked(fn()=>staff_transaction(function()use($request,$page,$projectPage):array{
        provider_settings_manager(false);service_plans_owner(false);$original=domain_renewals_load($request);$prepared=domain_renewal_dispatch_ready();$out=['prepared'=>$prepared,'request'=>$original,'offer'=>null,'allocations'=>[],'invoices'=>[],'page'=>max(1,min(10000,$page)),'create_invoice'=>null,'project_page'=>max(1,min(10000,$projectPage)),'projects'=>[],'projects_has_more'=>false,'message'=>null];if(!$prepared)return $out;domain_renewal_dispatch_owner();
        foreach(all('SELECT id FROM '.table('domain_renewal_invoices').' WHERE request_id=? ORDER BY id LIMIT 129',[$request]) as $n=>$row){if($n>=128)throw new RuntimeException('Renewal invoice history exceeds its limit.');$a=domain_renewal_dispatch_allocation($row['id']);$public=domain_renewal_dispatch_public($a);$review=null;$message=null;
            if($public['dispatch']===null)try{$current=domain_renewal_dispatch_current($a);domain_renewal_dispatch_fence($public['domain']);$review=$current['review_sha256'];}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$message='Dispatch is unavailable. Review the active accepted terms, current domain assignment and verified payment history.';}
            $out['allocations'][]=['public'=>$public,'review_sha256'=>$review,'message'=>$message];
        }
        try{$context=domain_renewal_dispatch_offer($request);$offer=$context['offer'];$b=$offer['binding'];$d=$b->data();$out['offer']=['binding_sha256'=>$b->fingerprint(),'agreement_sha256'=>$offer['agreement']->fingerprint(),'domain'=>$d['registrar']['domain'],'years'=>$d['requested_years'],'renew_privacy'=>$d['renew_privacy'],'price_minor'=>$d['price_minor']];
            if(!one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE binding_id=?',[$d['id']])&&!one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE request_id=?',[$request])){
                $out['create_invoice']=$out['offer'];$projects=domain_renewal_dispatch_projects($d['registrar']['client_id'],$out['project_page']);$out['projects']=$projects['items'];$out['projects_has_more']=$projects['has_more'];
                foreach(all('SELECT i.*,j.client_id,c.active FROM '.table('studio_invoices').' i JOIN '.table('studio_jobs').' j ON j.id=i.job_id JOIN '.table('studio_clients').' c ON c.id=j.client_id WHERE j.client_id=? AND i.status=? AND i.currency=? AND i.amount_cents=? ORDER BY i.id DESC LIMIT 10 OFFSET '.(($out['page']-1)*10),[$d['registrar']['client_id'],'issued','USD',$d['price_minor']]) as $i){try{domain_renewal_dispatch_other_origins((int)$i['id']);domain_renewal_dispatch_unpaid($i);domain_renewal_dispatch_exclude_invoice((int)$i['id']);if(domain_renewal_dispatch_creation_reference_matches($i['reference']))continue;$out['invoices'][]=domain_renewal_dispatch_invoice_snapshot($i);}catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;}}
            }
        }catch(InvalidArgumentException|RuntimeException $e){if($e instanceof PDOException)throw $e;$out['message']='Current, active renewal terms with this client’s recorded consent are required before allocating an invoice.';}
        return $out;
    }));
}
