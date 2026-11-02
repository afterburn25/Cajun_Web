<?php
/** Pure derivation from retained composite consent; no SQL, current authority or invoice creation. */
declare(strict_types=1);
if (!defined('CWP_BOOT')) { http_response_code(404); exit; }
require_once CWP_CODE_ROOT.'/app/plans/documents.php';
require_once __DIR__.'/catalog_checkout_document.php';
require_once CWP_CODE_ROOT.'/includes/foundation/recurring-billing/RecurringBillingSchedule.php';

use CajunWebPro\Foundation\RecurringBilling\RecurringBillingTerms;

/** Accept only the exact active two-event checkout prefix, never a later silently substituted revision. */
function hosting_checkout_billing_terms(string $id,ServiceSubscriptionDocument $subscription): RecurringBillingTerms {
    service_plans_id($id);
    $subscription=ServiceSubscriptionDocument::fromJson($subscription->json());$d=$subscription->data();
    if ($d['format']!==2 || $subscription->revision()!==2 || $subscription->model()->state()!=='active') {
        throw new InvalidArgumentException('Billing derivation requires the exact active checkout subscription prefix.');
    }
    $x=$d['checkout'];$publication=HostingCatalogPublication::fromJson($x['publication_json']);
    $consent=HostingCheckoutConsent::fromJson($x['consent_json'],$publication);
    $c=$consent->data();$review=$consent->review();$r=$review->data();$b=$r['billing'];
    $offer=$review->historicalProjection()['offer'];
    $terms=RecurringBillingTerms::fromArray([
        'format'=>2,'id'=>$id,'client_id'=>$c['client_id'],'project_id'=>$x['project_id'],
        'source_subscription'=>['id'=>$d['id'],'revision'=>2,'sha256'=>$subscription->fingerprint()],
        'title'=>$offer['title'],
        'terms'=>$offer['summary']."\nFull agreed period invoiced on confirmation. Payment and owner provisioning are required.",
        'currency'=>$b['currency'],'amount_cents'=>$b['amount_cents'],'cadence'=>$b['cadence'],
        'anchor'=>$b['anchor'],'alignment'=>$b['alignment'],'timezone'=>$b['timezone'],
        'due_after_days'=>$b['due_after_days'],'collection'=>$b['collection'],
        'checkout_origin'=>['request_id'=>$c['request_id'],'request_input_sha256'=>$consent->inputHash(),
            'publication_id'=>$publication->data()['id'],'publication_sha256'=>$publication->fingerprint(),
            'review_sha256'=>$review->fingerprint(),'consent_sha256'=>$consent->fingerprint(),'accepted_at'=>$c['accepted_at']],
    ]);
    $period=$terms->period(0);
    if ($period['start']!==$b['first_period']['start'] || $period['end']!==$b['first_period']['end']
        || $period['due']!==$b['first_period']['due'] || $period['end']!==$b['first_period']['next_start']) {
        throw new InvalidArgumentException('Billing calendar differs from the exact accepted checkout review.');
    }
    return $terms;
}

/** Historical binding may follow later subscription decisions but always uses its original fixed prefix. */
function hosting_checkout_billing_assert(RecurringBillingTerms $terms,ServiceSubscriptionDocument $subscription): void {
    $subscription=ServiceSubscriptionDocument::fromJson($subscription->json());$d=$subscription->data();
    if ($d['format']!==2) throw new InvalidArgumentException('Checkout billing requires retained checkout subscription history.');
    $d['events']=array_slice($d['events'],0,2);
    $prefix=ServiceSubscriptionDocument::fromJson(service_plans_json($d));
    $expected=hosting_checkout_billing_terms($terms->data()['id'],$prefix);
    if (!hash_equals($expected->fingerprint(),$terms->fingerprint()) || $expected->json()!==$terms->json()) {
        throw new InvalidArgumentException('Billing terms differ from the exact retained checkout subscription and composite consent.');
    }
}
