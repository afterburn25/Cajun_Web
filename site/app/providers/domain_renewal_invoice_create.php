<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}

/** Project selection is local commercial context, never provider authority. */
function domain_renewal_dispatch_project_hash(array $project):string {
    $data=['id'=>(int)$project['id'],'client_id'=>(int)$project['client_id'],'title'=>$project['title'],'created_at'=>$project['created_at'],'updated_at'=>$project['updated_at']];
    DomainRenewalInvoiceDocument::number($data['id'],1,2147483647);DomainRenewalInvoiceDocument::number($data['client_id'],1,2147483647);
    foreach(['title','created_at','updated_at'] as $key)if(!is_string($data[$key])||$data[$key]===''||strlen($data[$key])>640||preg_match('/[\x00-\x1f\x7f]/',$data[$key]))throw new RuntimeException('The reviewed project identity is unavailable.');
    return hash('sha256',DomainRenewalInvoiceDocument::json($data));
}
function domain_renewal_dispatch_projects(int $client,int $page):array {
    domain_renewal_agreements_locked();service_plans_client_row($client);$page=max(1,min(10000,$page));
    $rows=all('SELECT id,client_id,title,created_at,updated_at FROM '.table('studio_jobs').' WHERE client_id=? ORDER BY id DESC LIMIT 11 OFFSET '.(($page-1)*10),[$client]);$more=count($rows)>10;$out=[];
    foreach(array_slice($rows,0,10) as $row)$out[]=['id'=>(int)$row['id'],'title'=>$row['title'],'sha256'=>domain_renewal_dispatch_project_hash($row)];
    return ['items'=>$out,'has_more'=>$more];
}
function domain_renewal_dispatch_creation_reference(string $id):string {DomainRenewalInvoiceDocument::id($id);return 'DR-'.$id;}
function domain_renewal_dispatch_creation_reference_matches(string $reference):bool {return preg_match('/\ADR-[a-f0-9]{32}\z/',$reference)===1;}
function domain_renewal_dispatch_creation_proof(array $allocation,string $projectHash):string {
    DomainRenewalInvoiceDocument::hash($projectHash);
    return hash_hmac('sha256','renewal-invoice-create|'.DomainRenewalInvoiceDocument::json(['allocation_id'=>$allocation['id'],'allocation_proof'=>$allocation['proof'],'project_sha256'=>$projectHash]),provider_settings_vault_key());
}
function domain_renewal_dispatch_creation_detail(array $allocation,string $projectHash):string {
    return 'Renewal invoice '.$allocation['id'].' project '.$projectHash.' proof '.domain_renewal_dispatch_creation_proof($allocation,$projectHash);
}
/** Original selected project digest is authenticated separately without widening v1 allocations. */
function domain_renewal_dispatch_creation_origin(array $allocation):array {
    domain_renewal_dispatch_identity('allocation',$allocation);$d=DomainRenewalInvoiceDocument::fromJson($allocation['document_json'])->data();
    if($d['invoice']['reference']!==domain_renewal_dispatch_creation_reference($allocation['id']))throw new RuntimeException('This allocation was not created by the renewal invoice action.');
    $rows=all('SELECT id,user_id,detail FROM '.table('audit')." WHERE BINARY action='domain_renewal_dispatch.invoice_created' AND BINARY detail LIKE ? ORDER BY id LIMIT 2",['Renewal invoice '.$allocation['id'].' %']);
    if(count($rows)!==1||!preg_match('/\ARenewal invoice ([a-f0-9]{32}) project ([a-f0-9]{64}) proof ([a-f0-9]{64})\z/',$rows[0]['detail'],$m)||(int)$rows[0]['user_id']!==(int)$allocation['actor_id']||$m[1]!==$allocation['id']||!hash_equals(domain_renewal_dispatch_creation_proof($allocation,$m[2]),$m[3]))throw new RuntimeException('Original renewal invoice creation history needs recovery.');
    $prior=domain_renewal_dispatch_audit('allocation',$allocation);if((int)$rows[0]['id']<=(int)$prior['id'])throw new RuntimeException('Renewal invoice creation audit order needs recovery.');
    $invoice=ledger_invoice((int)$allocation['invoice_id']);if(domain_renewal_dispatch_invoice_snapshot($invoice)!==$d['invoice'])throw new RuntimeException('The created renewal invoice changed.');
    return ['project_sha256'=>$m[2],'audit_id'=>(int)$rows[0]['id']];
}
/** Called only after ordinary v1 witness authentication; it never re-enters readiness. */
function domain_renewal_dispatch_creation_ready():void {
    $invoices=(int)query("SELECT COUNT(*) FROM ".table('studio_invoices')." WHERE OCTET_LENGTH(reference)=35 AND reference COLLATE utf8mb4_bin REGEXP '^DR-[a-f0-9]{32}$'")->fetchColumn();
    $audits=(int)query("SELECT COUNT(*) FROM ".table('audit')." WHERE BINARY action='domain_renewal_dispatch.invoice_created'")->fetchColumn();$matched=0;$created=0;
    $rows=query('SELECT * FROM '.table('domain_renewal_invoices').' ORDER BY id');
    while($row=$rows->fetch(PDO::FETCH_ASSOC)){
        $d=DomainRenewalInvoiceDocument::fromJson($row['document_json'])->data();if(!domain_renewal_dispatch_creation_reference_matches($d['invoice']['reference']))continue;
        if($d['invoice']['reference']===domain_renewal_dispatch_creation_reference($row['id'])){domain_renewal_dispatch_creation_origin($row);++$created;}
        else{
            // A signed older ordinary allocation may coincidentally have used this display shape.
            // Its differing immutable allocation identity cannot represent a new creation request.
            domain_renewal_dispatch_identity('allocation',$row);domain_renewal_dispatch_audit('allocation',$row);
            if(domain_renewal_dispatch_invoice_snapshot(ledger_invoice((int)$row['invoice_id']))!==$d['invoice'])throw new RuntimeException('The original renewal invoice changed.');
        }
        ++$matched;
    }
    $rows->closeCursor();
    if($invoices!==$matched||$audits!==$created)throw new RuntimeException('Renewal invoice creation correspondence needs recovery.');
}
/** Explicit owner action: invoice, permanent v1 allocation and creation audit commit together. */
function domain_renewal_dispatch_create_invoice(string $allocationId,string $requestId,string $bindingHash,string $agreementHash,int $projectId,string $projectHash,bool $confirmed,#[SensitiveParameter] string $password):array {
    if(db()->inTransaction())throw new LogicException('Renewal invoice creation cannot inherit a transaction.');
    DomainRenewalInvoiceDocument::id($allocationId);DomainRenewalInvoiceDocument::id($requestId);foreach([$bindingHash,$agreementHash,$projectHash] as $hash)DomainRenewalInvoiceDocument::hash($hash);DomainRenewalInvoiceDocument::number($projectId,1,2147483647);
    if(!$confirmed)throw new InvalidArgumentException('Confirm creation of this exact agreed renewal invoice.');
    return staff_locked(fn()=>staff_transaction(function()use($allocationId,$requestId,$bindingHash,$agreementHash,$projectId,$projectHash,$password):array{
        $actor=domain_renewal_dispatch_owner($password);$existing=one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE id=?',[$allocationId]);
        if($existing){
            $old=domain_renewal_dispatch_allocation($allocationId);$d=$old['document']->data();$origin=domain_renewal_dispatch_creation_origin($old['row']);service_plans_client_row($d['invoice']['client_id']);
            if($d['actor_id']!==(int)$actor['id']||$d['binding']['request_id']!==$requestId||!hash_equals($d['binding_sha256'],$bindingHash)||!hash_equals($d['agreement_sha256'],$agreementHash)||$d['invoice']['project_id']!==$projectId||!hash_equals($origin['project_sha256'],$projectHash))throw new RuntimeException('This renewal invoice creation identity was already used.');
            return $old;
        }
        $context=domain_renewal_dispatch_offer($requestId);$offer=$context['offer'];$b=$offer['binding'];$agreement=$offer['agreement'];$d=$b->data();
        if(!hash_equals($b->fingerprint(),$bindingHash)||!hash_equals($agreement->fingerprint(),$agreementHash))throw new RuntimeException('Review the current accepted renewal terms.');
        $project=one('SELECT id,client_id,title,created_at,updated_at FROM '.table('studio_jobs').' WHERE id=? AND client_id=? FOR UPDATE',[$projectId,$d['registrar']['client_id']]);
        if(!$project||!hash_equals(domain_renewal_dispatch_project_hash($project),$projectHash))throw new RuntimeException('Review the current project belonging to this renewal client.');
        service_plans_client_row($d['registrar']['client_id']);
        if(one('SELECT id FROM '.table('domain_renewal_invoices').' WHERE binding_id=?',[$d['id']]))throw new RuntimeException('This renewal agreement already has its permanent invoice.');
        if(one('SELECT id FROM '.table('domain_renewal_dispatches').' WHERE request_id=?',[$requestId]))throw new RuntimeException('A renewal dispatch already exists for this request.');
        $reference=domain_renewal_dispatch_creation_reference($allocationId);
        if(one('SELECT id FROM '.table('studio_invoices').' WHERE BINARY reference=?',[$reference]))throw new RuntimeException('Original renewal invoice creation history needs recovery.');
        $instructions='Agreed renewal of '.$d['registrar']['domain'].' for '.$d['requested_years'].' year(s). Privacy: '.($d['renew_privacy']?'renew privacy':'do not renew privacy').'. Payment does not submit the renewal. The studio must separately review eligibility and approve the provider balance charge.';
        query('INSERT INTO '.table('studio_invoices').' (job_id,reference,title,amount_cents,currency,status,instructions,created_at) VALUES (?,?,?,?,?,?,?,?)',[$projectId,$reference,'Domain renewal',$d['price_minor'],'USD','issued',$instructions,gmdate('Y-m-d H:i:s')]);$invoiceId=(int)db()->lastInsertId();
        // All original storage is validated above, under this same mutex/transaction.
        // Do not call readiness while the invoice and its permanent witnesses are being inserted.
        $invoice=ledger_invoice($invoiceId);domain_renewal_dispatch_other_origins($invoiceId);domain_renewal_dispatch_unpaid($invoice);$snapshot=domain_renewal_dispatch_invoice_snapshot($invoice);
        $allocation=domain_renewal_dispatch_record_allocation_locked($allocationId,$requestId,$bindingHash,$agreementHash,$actor,$offer,$snapshot);
        query('INSERT INTO '.table('audit').' (user_id,action,detail,created_at) VALUES (?,?,?,?)',[$actor['id'],'domain_renewal_dispatch.invoice_created',domain_renewal_dispatch_creation_detail($allocation['row'],$projectHash),gmdate('Y-m-d H:i:s')]);
        if(!domain_renewal_dispatch_ready())throw new RuntimeException('Renewal invoice storage needs recovery.');return $allocation;
    }));
}
