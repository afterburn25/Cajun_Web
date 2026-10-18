<?php
/** Owner-only, bounded saved-data projections and strict nonsecret form state. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once __DIR__.'/catalog_offers.php';
require_once CWP_CODE_ROOT.'/app/plans/forms.php';
require_once __DIR__.'/catalog_offer_setup_views.php';

function hosting_catalog_admin_owner(): array {
    $actor=service_plans_owner(false);$provider=provider_catalog_import_owner();
    if ((int)$actor['id']!==(int)$provider['id']) throw new RuntimeException('Sign in again as the current owner.');
    return $actor;
}
function hosting_catalog_admin_actions(): array {
    return ['hosting_catalog_setup','hosting_catalog_checkout_setup','hosting_catalog_finish_plan','hosting_catalog_preview','hosting_catalog_publish','hosting_catalog_withdraw','hosting_catalog_convert','hosting_catalog_decline'];
}
/** Secrets and confirmation never enter redisplay state. An invalid value becomes empty, not a fresh identity. */
function hosting_catalog_admin_values(array $input): array {
    $out=[];
    foreach (['action'=>40,'product_id'=>10,'plan_id'=>32,'publication_id'=>32,'review_sha256'=>64,'publication_sha256'=>64,'request_id'=>32,'request_sha256'=>64,'decision_id'=>32,'withdrawal_id'=>32,'job_id'=>10,'reason'=>1000,'checkout_mode'=>30,'billing_timezone'=>64,'billing_alignment'=>16] as $key=>$max) {
        $value=$input[$key]??'';
        $out[$key]=is_string($value)&&strlen($value)<=$max&&preg_match('//u',$value)===1&&!str_contains($value,"\0")?$value:'';
    }
    return $out+hosting_catalog_setup_values($input);
}
function hosting_catalog_admin_page(array $query,string $field): int {
    $page=service_plans_form_number([$field=>$query[$field]??'1'],$field,100000);
    if ($page<1) throw new InvalidArgumentException('Choose an available page.');
    return $page;
}
function hosting_catalog_admin_positive(array $input,string $field): int {
    $id=service_plans_form_number($input,$field,2147483647);
    service_plans_actor($id);return $id;
}
/** Explicit mode selection is part of the owner review; absent mode preserves old request forms. */
function hosting_catalog_admin_checkout_policy(array $input): ?array {
    $mode=array_key_exists('checkout_mode',$input)?service_plans_form_text($input,'checkout_mode',30):'request-only';
    if ($mode==='request-only') return null;
    if ($mode!=='invoice-on-confirmation') throw new InvalidArgumentException('Choose requests or invoice checkout.');
    return hosting_checkout_policy(['mode'=>'invoice-on-confirmation','client'=>'primary-only','project'=>'existing-or-new-hosting',
        'collection'=>'invoice-only','first_period'=>'full-period-today','requires_verified_payment'=>true,'requires_owner_provisioning'=>true,
        'timezone'=>service_plans_form_text($input,'billing_timezone',64),'alignment'=>service_plans_form_text($input,'billing_alignment',16),'review_ttl_seconds'=>900]);
}
/** The complete intent is parsed before any service mutation. No server-generated retry identity. */
function hosting_catalog_admin_intent(string $action,array $input): array {
    if (!in_array($action,hosting_catalog_admin_actions(),true)) throw new InvalidArgumentException('Unknown hosting offer action.');
    if ($action==='hosting_catalog_finish_plan') return ['action'=>$action]+hosting_catalog_setup_intent($input);
    $result=['action'=>$action];
    if (in_array($action,['hosting_catalog_preview','hosting_catalog_publish'],true)) {
        $result+=['publication_id'=>service_plans_form_id($input,'publication_id'),'product_id'=>hosting_catalog_admin_positive($input,'product_id'),'plan_id'=>service_plans_form_id($input,'plan_id')];
        if ($action==='hosting_catalog_publish') $result['review_sha256']=service_plans_form_hash($input,'review_sha256');
        $policy=hosting_catalog_admin_checkout_policy($input);if ($policy!==null) $result['checkout_policy']=$policy;
    } elseif ($action==='hosting_catalog_withdraw') {
        $result+=['withdrawal_id'=>service_plans_form_id($input,'withdrawal_id'),'publication_id'=>service_plans_form_id($input,'publication_id'),'publication_sha256'=>service_plans_form_hash($input,'publication_sha256'),'reason'=>service_plans_form_text($input,'reason',500,true)];
        if ($result['reason']==='') throw new InvalidArgumentException('Explain why this offer is being withdrawn.');
    } elseif (in_array($action,['hosting_catalog_convert','hosting_catalog_decline'],true)) {
        $result+=['decision_id'=>service_plans_form_id($input,'decision_id'),'request_id'=>service_plans_form_id($input,'request_id'),'request_sha256'=>service_plans_form_hash($input,'request_sha256')];
        if ($action==='hosting_catalog_convert') $result['job_id']=hosting_catalog_admin_positive($input,'job_id');
        else $result['reason']=service_plans_form_text($input,'reason',1000,true);
    }
    if ($action!=='hosting_catalog_preview') service_plans_form_confirm($input);
    return $result;
}
function hosting_catalog_admin_plan_matches(array $product,ServicePlanDocument $plan): bool {
    $model=$plan->model();
    return $product['status']==='draft'&&$product['family']==='hosting'&&(int)$product['setup_cents']===0&&(int)$product['amount_cents']>0
        &&$model->status()==='active'&&$model->name===$product['title']&&$model->priceAmount===(int)$product['amount_cents']
        &&$model->currency===$product['currency']&&$model->cadence===(['month'=>'monthly','year'=>'annually'][$product['term']]??null);
}
function hosting_catalog_admin_request_item(string $id): array {
    hosting_catalog_guard();
    $request=hosting_catalog_request_document($id);$d=$request->data();
    $publication=hosting_catalog_publication($d['publication_id']);$request->assertPublication($publication);
    $client=service_plans_client_row($d['client_id'],false);
    $decision=one('SELECT kind,agreement_id,job_id,document_json FROM '.table('hosting_catalog_decisions').' WHERE request_id=?',[$id]);
    return ['id'=>$id,'sha256'=>$request->fingerprint(),'created_at'=>$d['created_at'],
        'client'=>['id'=>(int)$client['id'],'name'=>$client['name'],'active'=>(int)$client['active']===1],
        'offer'=>$publication->historicalProjection(),'status'=>$decision['kind']??'pending',
        'agreement_id'=>$decision['agreement_id']??null,'job_id'=>$decision&&$decision['job_id']!==null?(int)$decision['job_id']:null,
        'reason'=>$decision?json_decode($decision['document_json'],true,16,JSON_THROW_ON_ERROR)['reason']:''];
}
/** Each selection is bounded; availability and exact source are rechecked by the eventual service action. */
function hosting_catalog_admin_data(array $query,array $post=[]): array {
    $values=hosting_catalog_admin_values($post);$action=$values['action'];
    $tab=service_plans_form_text($query,'tab',12);
    if ($tab==='') $tab='offers';
    if (!in_array($tab,['offers','requests'],true)) throw new InvalidArgumentException('Choose offers or client requests.');
    if (in_array($action,['hosting_catalog_preview','hosting_catalog_publish','hosting_catalog_withdraw','hosting_catalog_finish_plan'],true)) $tab='offers';
    if (in_array($action,['hosting_catalog_convert','hosting_catalog_decline'],true)) $tab='requests';
    $productText=service_plans_form_text($query,'product_id',10);
    $requestText=service_plans_form_text($query,'request_id',32);
    if (in_array($action,['hosting_catalog_preview','hosting_catalog_publish','hosting_catalog_finish_plan'],true)) $productText=$values['product_id'];
    if (in_array($action,['hosting_catalog_convert','hosting_catalog_decline'],true)) $requestText=$values['request_id'];
    $productId=$productText===''?null:hosting_catalog_admin_positive(['product_id'=>$productText],'product_id');
    if ($requestText!=='') service_plans_id($requestText);
    $selectedPlan=service_plans_form_text($query,'plan_id',32);
    if ($selectedPlan!=='') service_plans_id($selectedPlan);
    $page=hosting_catalog_admin_page($query,'p');$planPage=hosting_catalog_admin_page($query,'plan_page');$jobPage=hosting_catalog_admin_page($query,'job_page');
    $search=service_plans_form_text($query,'search',120,true);$planSearch=service_plans_form_text($query,'plan_search',120,true);$jobSearch=service_plans_form_text($query,'job_search',160,true);
    return recurring_billing_locked(function()use($tab,$productId,$requestText,$page,$planPage,$jobPage,$search,$planSearch,$jobSearch,$action,$values,$selectedPlan):array {
        hosting_catalog_admin_owner();
        $data=['tab'=>$tab,'page'=>$page,'plan_page'=>$planPage,'job_page'=>$jobPage,'search'=>$search,'plan_search'=>$planSearch,'job_search'=>$jobSearch,
            'ready'=>hosting_catalog_ready(),'products'=>[],'requests'=>[],'has_more'=>false,'product'=>null,'latest'=>null,'withdrawal_offer'=>null,
            'plans'=>[],'plans_more'=>false,'selected_plan_id'=>'','plan_notice'=>'','request'=>null,'jobs'=>[],'jobs_more'=>false];
        if (!$data['ready']) return $data;
        if ($tab==='requests') {
            $rows=all('SELECT r.id FROM '.table('hosting_catalog_requests').' r JOIN '.table('studio_clients').' c ON c.id=r.client_id WHERE LOCATE(?,c.name)>0 ORDER BY r.created_epoch DESC,r.id DESC LIMIT 21 OFFSET '.(($page-1)*20),[$search]);
            $data['has_more']=count($rows)>20;
            foreach (array_slice($rows,0,20) as $row) $data['requests'][]=hosting_catalog_admin_request_item($row['id']);
            if ($requestText!=='') {
                $data['request']=hosting_catalog_admin_request_item($requestText);$client=$data['request']['client'];
                $rows=all('SELECT id,title FROM '.table('studio_jobs').' WHERE client_id=? AND LOCATE(?,title)>0 ORDER BY id DESC LIMIT 21 OFFSET '.(($jobPage-1)*20),[$client['id'],$jobSearch]);
                $data['jobs_more']=count($rows)>20;
                foreach (array_slice($rows,0,20) as $row) $data['jobs'][]=['id'=>(int)$row['id'],'title'=>$row['title']];
            }
            return $data;
        }
        if (!provider_catalog_import_ready()||!catalog_ready()) throw new RuntimeException('Prepare imported hosting drafts in Products first.');
        $rows=all('SELECT p.id,p.title,p.sku,p.amount_cents,p.currency,p.term FROM '.table('catalog_products').' p JOIN '.table('catalog_provider_imports')." i ON i.product_id=p.id WHERE p.family='hosting' AND p.status='draft' AND (LOCATE(?,p.title)>0 OR LOCATE(?,p.sku)>0) ORDER BY p.sort_order,p.id LIMIT 21 OFFSET ".(($page-1)*20),[$search,$search]);
        $data['has_more']=count($rows)>20;
        foreach (array_slice($rows,0,20) as $row) {$row['id']=(int)$row['id'];$row['amount_cents']=$row['amount_cents']===null?null:(int)$row['amount_cents'];$data['products'][]=$row;}
        if ($action==='hosting_catalog_withdraw') {
            $id=service_plans_form_id($values,'publication_id');$doc=hosting_catalog_publication($id);
            $productId=$doc->data()['product']['id'];$data['withdrawal_offer']=$doc->historicalProjection();
        }
        if ($productId!==null) {
            $row=one('SELECT p.id,p.revision,p.sku,p.title,p.family,p.status,p.term,p.currency,p.amount_cents,p.setup_cents FROM '.table('catalog_products').' p JOIN '.table('catalog_provider_imports').' i ON i.product_id=p.id WHERE p.id=?',[$productId]);
            if (!$row) throw new InvalidArgumentException('Choose an imported hosting product.');
            foreach (['id','revision','amount_cents','setup_cents'] as $key) $row[$key]=$row[$key]===null?null:(int)$row[$key];
            $data['product']=$row;
            $latest=one('SELECT id FROM '.table('hosting_catalog_publications').' WHERE product_id=? ORDER BY seq DESC LIMIT 1',[$productId]);
            if ($latest) {
                $doc=hosting_catalog_publication($latest['id']);
                $data['latest']=['offer'=>$doc->historicalProjection(),'withdrawn'=>one('SELECT id FROM '.table('hosting_catalog_withdrawals').' WHERE publication_id=?',[$latest['id']])!==null];
            }
            service_plans_require_ready();
            // Canonical name bytes occur in every current matching journal. Old draft names may also match;
            // validate at most twenty complete journals and filter exact current terms below.
            $needle='"name":'.json_encode($row['title'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $searchNeedle=substr(json_encode($planSearch,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),1,-1);
            $plans=all('SELECT * FROM '.table('service_plans').' WHERE LOCATE(?,journal_json)>0 AND (LOCATE(?,journal_json)>0 OR LOCATE(?,id)>0) ORDER BY created_epoch DESC,id DESC LIMIT 21 OFFSET '.(($planPage-1)*20),[$needle,$searchNeedle,$planSearch]);
            $data['plans_more']=count($plans)>20;
            foreach (array_slice($plans,0,20) as $saved) {
                $doc=service_plans_validate($saved);
                if (hosting_catalog_admin_plan_matches($row,$doc)) $data['plans'][]=['id'=>$doc->data()['id'],'revision'=>$doc->revision(),'name'=>$doc->model()->name,'created_at'=>$doc->data()['created_at']];
            }
            if ($selectedPlan!=='') {
                // A completed setup may be outside this search page; load only the exact requested plan.
                try {
                    $selected=hosting_catalog_admin_selected_plan($row,$selectedPlan);
                    if ($selected!==null) {
                        if (!in_array($selectedPlan,array_column($data['plans'],'id'),true)) $data['plans'][]=$selected;
                        $data['selected_plan_id']=$selectedPlan;
                    } else $data['plan_notice']='That plan no longer matches this draft. Choose a matching plan below.';
                } catch (RuntimeException|InvalidArgumentException|LogicException $e) {
                    if ($e instanceof PDOException) throw $e;
                    $data['plan_notice']='That plan is unavailable. Review the saved service plan before continuing.';
                }
            }
        }
        return $data;
    });
}

/** Exact current journal lookup; a query-string selection never grants publication eligibility. */
function hosting_catalog_admin_selected_plan(array $product,string $id): ?array {
    service_plans_id($id);
    $row=one('SELECT * FROM '.table('service_plans').' WHERE id=?',[$id]);
    if (!$row) return null;
    $doc=service_plans_validate($row);
    if (!hosting_catalog_admin_plan_matches($product,$doc)) return null;
    return ['id'=>$doc->data()['id'],'revision'=>$doc->revision(),'name'=>$doc->model()->name,'created_at'=>$doc->data()['created_at']];
}
