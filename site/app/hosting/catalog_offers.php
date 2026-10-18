<?php
/** Explicit local offer publication and requests. Existing agreement/payment/provider engines retain authority. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers_context.php';
require_once CWP_CODE_ROOT.'/database/migrations/hosting_catalog_migrate.php';

function hosting_catalog_publication(string $id): HostingCatalogPublication {
    hosting_catalog_guard();service_plans_id($id);
    $row=one('SELECT document_json,document_sha256 FROM '.table('hosting_catalog_publications').' WHERE id=?',[$id]);
    if (!$row) throw new InvalidArgumentException('Hosting offer unavailable.');
    $doc=HostingCatalogPublication::fromJson($row['document_json']);
    if ($doc->data()['id']!==$id || !hash_equals($doc->fingerprint(),$row['document_sha256'])) throw new RuntimeException('The original hosting offer needs recovery.');
    return $doc;
}
function hosting_catalog_request_document(string $id): HostingCatalogRequest {
    hosting_catalog_guard();service_plans_id($id);
    $row=one('SELECT document_json,document_sha256 FROM '.table('hosting_catalog_requests').' WHERE id=?',[$id]);
    if (!$row) throw new InvalidArgumentException('Hosting request unavailable.');
    $doc=HostingCatalogRequest::fromJson($row['document_json']);
    if ($doc->data()['id']!==$id || !hash_equals($doc->fingerprint(),$row['document_sha256'])) throw new RuntimeException('The original hosting request needs recovery.');
    return $doc;
}
/** Stable owner-review input excludes only publication creation time; no private details leave this hash. */
function hosting_catalog_review_hash(HostingCatalogPublication $publication): string {
    $data=$publication->data();unset($data['created_at']);
    return hash('sha256',service_plans_json($data));
}
function hosting_catalog_candidate(string $id,int $product,string $plan,array $actor): HostingCatalogPublication {
    hosting_catalog_guard();service_plans_id($id);
    $scope=hosting_catalog_read_scope([$product]);
    $source=hosting_catalog_sources($product,$plan,(int)$actor['id'],$scope);
    $source['context']['publication']=['id'=>$id,'sha256'=>null,'enabled'=>true];
    return HostingCatalogPublication::create($id,time(),$source['product'],$source['import'],$source['observation'],$source['plan'],$source['mapping_json'],$source['context']);
}
function hosting_catalog_preview(string $id,int $product,string $plan): array {
    return recurring_billing_locked(function()use($id,$product,$plan):array {
        $actor=provider_catalog_import_owner();service_plans_owner();
        $candidate=hosting_catalog_candidate($id,$product,$plan,$actor);
        return ['review_sha256'=>hosting_catalog_review_hash($candidate),'offer'=>$candidate->historicalProjection()];
    });
}
function hosting_catalog_publish(string $id,int $product,string $plan,string $reviewHash,#[\SensitiveParameter] string $password): HostingCatalogPublication {
    service_plans_id($id);service_plans_id($plan);hosting_catalog_hash($reviewHash);
    return recurring_billing_locked(function()use($id,$product,$plan,$reviewHash,$password):HostingCatalogPublication {
        $actor=provider_catalog_import_owner($password);service_plans_password($password);
        if (!hosting_catalog_ready()) throw new RuntimeException('Prepare hosting offer history first.');
        if (one('SELECT id FROM '.table('hosting_catalog_publications').' WHERE id=?',[$id])) {
            $old=hosting_catalog_publication($id);$data=$old->data();
            if ($data['product']['id']!==$product || ServicePlanDocument::fromJson($data['plan_json'])->data()['id']!==$plan || $data['publisher']['id']!==(int)$actor['id'] || !hash_equals(hosting_catalog_review_hash($old),$reviewHash)) throw new RuntimeException('This publication identity was already used for different details.');
            return $old;
        }
        // Explicit owner publication verifies the current saved key against the observation; public reads never decrypt it.
        provider_catalog_import_context('package.types');
        $publication=hosting_catalog_candidate($id,$product,$plan,$actor);
        if (!hash_equals(hosting_catalog_review_hash($publication),$reviewHash)) throw new RuntimeException('The hosting offer or provider review changed. Review the current offer before publishing.');
        $data=$publication->data();$mapping=provider_plan_catalog_document($data['mapping_json']);
        query('INSERT INTO '.table('hosting_catalog_publications').' (id,product_id,product_revision,plan_id,mapping_id,media_id,created_by,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$product,$data['product']['revision'],$plan,$mapping['id'],$data['product']['media_id'],$actor['id'],$data['created_at'],$publication->json(),$publication->fingerprint()]);
        hosting_catalog_recorded('hosting_catalog_publications',$id);
        return $publication;
    });
}
function hosting_catalog_request(string $id,string $publicationId,string $publicationHash,int $client): HostingCatalogRequest {
    service_plans_id($id);service_plans_id($publicationId);hosting_catalog_hash($publicationHash);
    return recurring_billing_locked(function()use($id,$publicationId,$publicationHash,$client):HostingCatalogRequest {
        service_plans_current_client($client);
        if (!hosting_catalog_ready()) throw new RuntimeException('Hosting requests are unavailable.');
        $currentClient=['id'=>$client,'active'=>true,'support_session'=>false];
        if (one('SELECT id FROM '.table('hosting_catalog_requests').' WHERE id=?',[$id])) {
            $old=hosting_catalog_request_document($id);$old->assertClient($currentClient);$data=$old->data();
            if ($data['publication_id']!==$publicationId || !hash_equals($data['publication_sha256'],$publicationHash)) throw new RuntimeException('This request identity was already used for different details.');
            $old->assertPublication(hosting_catalog_publication($publicationId));return $old;
        }
        $publication=hosting_catalog_publication($publicationId);
        if (!hash_equals($publication->fingerprint(),$publicationHash)) throw new RuntimeException('Review the exact hosting offer again.');
        $scope=hosting_catalog_read_scope([$publication->data()['product']['id']]);
        $context=hosting_catalog_current_context($publication,$scope);
        $request=HostingCatalogRequest::create($id,$publication,$currentClient,time(),$context);$data=$request->data();
        query('INSERT INTO '.table('hosting_catalog_requests').' (id,publication_id,client_id,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?)',[$id,$publicationId,$client,$data['created_at'],$request->json(),$request->fingerprint()]);
        hosting_catalog_recorded('hosting_catalog_requests',$id);
        return $request;
    });
}
function hosting_catalog_convert(string $decisionId,string $requestId,string $requestHash,int $job,#[\SensitiveParameter] string $password): ServiceSubscriptionDocument {
    service_plans_id($decisionId);service_plans_id($requestId);hosting_catalog_hash($requestHash);
    return recurring_billing_locked(function()use($decisionId,$requestId,$requestHash,$job,$password):ServiceSubscriptionDocument {
        $actor=service_plans_password($password);provider_catalog_import_owner($password);
        if (!hosting_catalog_ready()) throw new RuntimeException('Hosting request history is unavailable.');
        $request=hosting_catalog_request_document($requestId);$data=$request->data();
        if (!hash_equals($request->fingerprint(),$requestHash)) throw new RuntimeException('Review the exact client request again.');
        service_plans_client_row($data['client_id']);recurring_billing_job($job,$data['client_id']);
        $old=one('SELECT * FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[$requestId]);
        if ($old) {
            if ($old['id']!==$decisionId || $old['kind']!=='offered' || (int)$old['job_id']!==$job || (int)$old['actor_staff_id']!==(int)$actor['id']) throw new RuntimeException('This hosting request was already handled.');
            return service_plans_validate(service_plans_row($old['agreement_id'],true),true);
        }
        $publication=hosting_catalog_publication($data['publication_id']);$request->assertPublication($publication);
        $scope=hosting_catalog_read_scope([$publication->data()['product']['id']]);
        $publication->assertCurrent(hosting_catalog_current_context($publication,$scope),time());
        $plan=ServicePlanDocument::fromJson($publication->data()['plan_json']);
        $agreement=service_plans_offer_locked(bin2hex(random_bytes(16)),$data['client_id'],$plan->data()['id'],$plan->revision(),$plan->fingerprint(),$password);
        $decision=['format'=>1,'id'=>$decisionId,'request_id'=>$requestId,'request_sha256'=>$request->fingerprint(),'kind'=>'offered','agreement_id'=>$agreement->data()['id'],'job_id'=>$job,'actor_staff_id'=>(int)$actor['id'],'actor_client_id'=>null,'created_at'=>$agreement->data()['created_at'],'reason'=>''];
        $json=hosting_catalog_decision_document($decision);
        query('INSERT INTO '.table('hosting_catalog_decisions').' (id,request_id,kind,agreement_id,job_id,actor_staff_id,actor_client_id,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?)',[$decisionId,$requestId,'offered',$decision['agreement_id'],$job,$actor['id'],null,$decision['created_at'],$json,hash('sha256',$json)]);
        hosting_catalog_recorded('hosting_catalog_decisions',$decisionId);
        return $agreement;
    });
}
function hosting_catalog_withdraw(string $id,string $publicationId,string $publicationHash,string $reason,#[\SensitiveParameter] string $password): void {
    service_plans_id($id);service_plans_id($publicationId);hosting_catalog_hash($publicationHash);
    recurring_billing_locked(function()use($id,$publicationId,$publicationHash,$reason,$password):void {
        $actor=provider_catalog_import_owner($password);service_plans_password($password);
        if (!hosting_catalog_ready()) throw new RuntimeException('Hosting offer history is unavailable.');
        $publication=hosting_catalog_publication($publicationId);
        if (!hash_equals($publication->fingerprint(),$publicationHash)) throw new RuntimeException('Review the exact hosting offer again.');
        $old=one('SELECT * FROM '.table('hosting_catalog_withdrawals').' WHERE publication_id=?',[$publicationId]);
        $data=['format'=>1,'id'=>$id,'publication_id'=>$publicationId,'publication_sha256'=>$publicationHash,'actor_id'=>(int)$actor['id'],'created_at'=>$old?(int)$old['created_epoch']:max(time(),$publication->data()['created_at']),'reason'=>$reason];
        $json=hosting_catalog_withdrawal_document($data);
        if ($old) {
            if ($old['document_json']!==$json) throw new RuntimeException('This hosting offer was already withdrawn.');
            return;
        }
        query('INSERT INTO '.table('hosting_catalog_withdrawals').' (id,publication_id,actor_id,created_epoch,reason,document_json,document_sha256) VALUES (?,?,?,?,?,?,?)',[$id,$publicationId,$actor['id'],$data['created_at'],$reason,$json,hash('sha256',$json)]);
        hosting_catalog_recorded('hosting_catalog_withdrawals',$id);
    });
}
/** Clients may withdraw their own pending request; owners may decline it. Neither action cancels an agreement. */
function hosting_catalog_close_request(string $id,string $requestId,string $requestHash,string $reason,?int $client,#[\SensitiveParameter] ?string $password): void {
    service_plans_id($id);service_plans_id($requestId);hosting_catalog_hash($requestHash);
    if (($client===null)===($password===null)) throw new InvalidArgumentException('Choose one authenticated request action.');
    recurring_billing_locked(function()use($id,$requestId,$requestHash,$reason,$client,$password):void {
        if ($client!==null) {service_plans_current_client($client);$staff=null;$kind='withdrawn';}
        else {$actor=service_plans_password($password);provider_catalog_import_owner($password);$staff=(int)$actor['id'];$kind='declined';}
        if (!hosting_catalog_ready()) throw new RuntimeException('Hosting request history is unavailable.');
        $request=hosting_catalog_request_document($requestId);
        if (!hash_equals($request->fingerprint(),$requestHash)) throw new RuntimeException('Review the exact client request again.');
        if ($client!==null) $request->assertClient(['id'=>$client,'active'=>true,'support_session'=>false]);
        $old=one('SELECT * FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[$requestId]);
        $data=['format'=>1,'id'=>$id,'request_id'=>$requestId,'request_sha256'=>$requestHash,'kind'=>$kind,'agreement_id'=>null,'job_id'=>null,'actor_staff_id'=>$staff,'actor_client_id'=>$client,'created_at'=>$old?(int)$old['created_epoch']:max(time(),$request->data()['created_at']),'reason'=>$reason];
        $json=hosting_catalog_decision_document($data);
        if ($old) {
            if ($old['document_json']!==$json) throw new RuntimeException('This request was already handled. Use the linked agreement for subsequent changes.');
            return;
        }
        query('INSERT INTO '.table('hosting_catalog_decisions').' (id,request_id,kind,agreement_id,job_id,actor_staff_id,actor_client_id,created_epoch,document_json,document_sha256) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$requestId,$kind,null,null,$staff,$client,$data['created_at'],$json,hash('sha256',$json)]);
        hosting_catalog_recorded('hosting_catalog_decisions',$id);
    });
}
/** Closed public cards only. This read has no authentication mutation or provider transport. */
function hosting_catalog_public(int $page=1): array {
    if ($page<1 || $page>100000) throw new InvalidArgumentException('Choose an available hosting page.');
    return recurring_billing_locked(function()use($page):array {
        if (!hosting_catalog_ready()) return ['offers'=>[],'has_more'=>false,'unavailable'=>false];
        $publications=table('hosting_catalog_publications');$withdrawals=table('hosting_catalog_withdrawals');$products=table('catalog_products');
        $rows=all("SELECT p.id,p.product_id FROM $publications p JOIN $products c ON c.id=p.product_id LEFT JOIN $withdrawals w ON w.publication_id=p.id WHERE c.status='draft' AND c.revision=p.product_revision AND w.id IS NULL AND NOT EXISTS (SELECT 1 FROM $publications n WHERE n.product_id=p.product_id AND n.seq>p.seq) ORDER BY c.sort_order,c.id LIMIT 21 OFFSET ".(($page-1)*20));
        $more=count($rows)>20;$rows=array_slice($rows,0,20);
        if (!$rows) return ['offers'=>[],'has_more'=>$more,'unavailable'=>false];
        $scope=hosting_catalog_read_scope(array_map(static fn(array $row):int=>(int)$row['product_id'],$rows));
        $offers=[];$unavailable=false;
        foreach ($rows as $row) {
            $publication=hosting_catalog_publication($row['id']);
            try {$offers[]=$publication->publicProjection(hosting_catalog_current_context($publication,$scope),time());}
            catch (RuntimeException $error) {if ($error instanceof PDOException) throw $error;$unavailable=true;}
        }
        return ['offers'=>$offers,'has_more'=>$more,'unavailable'=>$unavailable];
    });
}
/** Own request history survives later catalog edits, withdrawal and credential replacement. */
function hosting_catalog_client_requests(int $client,int $page=1): array {
    if ($page<1 || $page>100000) throw new InvalidArgumentException('Choose an available request page.');
    return recurring_billing_locked(function()use($client,$page):array {
        service_plans_current_client($client);
        if (!hosting_catalog_ready()) return ['requests'=>[],'has_more'=>false];
        $rows=all('SELECT id FROM '.table('hosting_catalog_requests').' WHERE client_id=? ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$client]);
        $more=count($rows)>20;$rows=array_slice($rows,0,20);$requests=[];
        foreach ($rows as $row) {
            $request=hosting_catalog_request_document($row['id']);$request->assertClient(['id'=>$client,'active'=>true,'support_session'=>false]);
            $publication=hosting_catalog_publication($request->data()['publication_id']);$request->assertPublication($publication);
            $decision=one('SELECT kind,agreement_id,job_id,document_json FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[$row['id']]);
            $reason=$decision?json_decode($decision['document_json'],true,16,JSON_THROW_ON_ERROR)['reason']:'';
            $requests[]=['id'=>$row['id'],'sha256'=>$request->fingerprint(),'created_at'=>$request->data()['created_at'],'offer'=>$publication->historicalProjection(),'status'=>$decision['kind']??'pending','agreement_id'=>$decision['agreement_id']??null,'job_id'=>$decision&&$decision['job_id']!==null?(int)$decision['job_id']:null,'reason'=>$reason];
        }
        return ['requests'=>$requests,'has_more'=>$more];
    });
}
